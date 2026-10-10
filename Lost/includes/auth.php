<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function register($username, $email, $password, $full_name) {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $verificationToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            INSERT INTO users (
                username, email, password, full_name, role,
                email_verification_token, email_verification_expires, email_verification_sent_at
            ) VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE), NOW())
        ");
        $stmt->execute([
            $username,
            $email,
            $hashed_password,
            $full_name,
            'student',
            hash('sha256', $verificationToken)
        ]);

        if ($stmt->rowCount()) {
            $userId = (int) $this->db->lastInsertId();
            logActivity($userId, 'register', 'New user registered');
            return [
                'success' => true,
                'message' => 'Registration successful',
                'verification_token' => $verificationToken
            ];
        }
        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function verifyEmail($token) {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return false;
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->prepare("
            SELECT id, email, full_name
            FROM users
            WHERE email_verification_token = ?
              AND email_verification_expires > NOW()
              AND email_verified_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$tokenHash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE users
            SET email_verified_at = NOW(),
                email_verification_token = NULL,
                email_verification_expires = NULL,
                email_verification_sent_at = NULL
            WHERE email_verification_token = ?
              AND email_verification_expires > NOW()
              AND email_verified_at IS NULL
        ");
        $stmt->execute([$tokenHash]);

        return $stmt->rowCount() === 1 ? $user : false;
    }

    public function releaseEmailVerificationCooldown($email, $token) {
        $stmt = $this->db->prepare("
            UPDATE users
            SET email_verification_sent_at = NULL
            WHERE email = ? AND email_verification_token = ?
              AND email_verified_at IS NULL
        ");
        $stmt->execute([$email, hash('sha256', $token)]);
    }

    public function resendEmailVerification($email) {
        $stmt = $this->db->prepare("
            SELECT id, email, full_name
            FROM users
            WHERE email = ? AND email_verified_at IS NULL
              AND (email_verification_sent_at IS NULL
                   OR email_verification_sent_at <= DATE_SUB(NOW(), INTERVAL 60 SECOND))
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return null;
        }

        $verificationToken = bin2hex(random_bytes(32));
        $stmt = $this->db->prepare("
            UPDATE users
            SET email_verification_token = ?,
                email_verification_expires = DATE_ADD(NOW(), INTERVAL 30 MINUTE),
                email_verification_sent_at = NOW()
            WHERE id = ? AND email_verified_at IS NULL
              AND (email_verification_sent_at IS NULL
                   OR email_verification_sent_at <= DATE_SUB(NOW(), INTERVAL 60 SECOND))
        ");
        $stmt->execute([hash('sha256', $verificationToken), $user['id']]);

        if ($stmt->rowCount() !== 1) {
            return null;
        }

        return [
            'email' => $user['email'],
            'full_name' => $user['full_name'],
            'verification_token' => $verificationToken
        ];
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
            if (empty($user['email_verified_at'])) {
                return [
                    'success' => false,
                    'message' => 'Please verify your email before signing in.',
                    'unverified' => true
                ];
            }

            if (!$user['is_active']) {
                return ['success' => false, 'message' => 'Account is deactivated. Contact admin.'];
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];

            logActivity($user['id'], 'login', 'User logged in');
            return ['success' => true, 'message' => 'Login successful'];
        }
        return ['success' => false, 'message' => 'Invalid username or password'];
    }

    public function logout() {
        if (isset($_SESSION['user_id'])) {
            logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        session_destroy();
        redirect(SITE_URL . '/login.php');
    }

    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }

    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }

    public function isStaff() {
        return isset($_SESSION['role']) && in_array($_SESSION['role'], ['staff', 'admin']);
    }

    public function requireLogin() {
        if (!$this->isLoggedIn()) {
            setFlash('warning', 'Please login to continue');
            redirect(SITE_URL . '/login.php');
        }
    }

    public function requireAdmin() {
        $this->requireLogin();
        if (!$this->isAdmin()) {
            setFlash('danger', 'Access denied. Admin only.');
            redirect(SITE_URL . '/dashboard.php');
        }
    }

    public function getCurrentUser() {
        if (!$this->isLoggedIn()) return null;
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function updateProfile($user_id, $data) {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key === 'password') {
                if (!empty($value)) {
                    $fields[] = "password = ?";
                    $values[] = password_hash($value, PASSWORD_DEFAULT);
                }
            } else {
                $fields[] = "$key = ?";
                $values[] = $value;
            }
        }

        if (empty($fields)) {
            return ['success' => false, 'message' => 'No data to update'];
        }

        $values[] = $user_id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);

        return ['success' => true, 'message' => 'Profile updated successfully'];
    }
}
?>
