<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

class Auth {
    private $db;

    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }

    public function register($username, $email, $password, $full_name, $role = 'student') {
        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $this->db->prepare("INSERT INTO users (username, email, password, full_name, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hashed_password, $full_name, $role]);

        if ($stmt->rowCount()) {
            $_SESSION['user_id'] = $this->db->lastInsertId();
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $role;
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;

            logActivity($_SESSION['user_id'], 'register', 'New user registered');
            return ['success' => true, 'message' => 'Registration successful'];
        }
        return ['success' => false, 'message' => 'Registration failed'];
    }

    public function login($username, $password) {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {
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
