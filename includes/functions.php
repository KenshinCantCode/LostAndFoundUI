<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

// Sanitize input
function sanitize($input)
{
    return htmlspecialchars(strip_tags(trim($input)));
}

// Validate email
function validateEmail($email)
{
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Generate CSRF token
function generateCSRFToken()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify CSRF token
function verifyCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Upload image
function uploadImage($file, $destination)
{
    $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = MAX_FILE_SIZE;

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The image exceeds the server upload limit.',
            UPLOAD_ERR_FORM_SIZE => 'The image is too large.',
            UPLOAD_ERR_PARTIAL => 'The image upload was incomplete. Please try again.',
            UPLOAD_ERR_NO_FILE => 'Please choose an image to upload.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing its temporary upload folder.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not save the uploaded image.',
            UPLOAD_ERR_EXTENSION => 'The server stopped the image upload.',
        ];
        $message = $uploadErrors[$file['error'] ?? -1] ?? 'Image upload failed. Please try again.';
        return ['success' => false, 'message' => $message];
    }

    if (!isset($file['tmp_name'], $file['name'], $file['type'], $file['size'])) {
        return ['success' => false, 'message' => 'The uploaded image data is incomplete. Please try again.'];
    }

    if (!in_array($file['type'], $allowed)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WEBP'];
    }

    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large. Max size: 5MB'];
    }

    if (!is_dir($destination) && !@mkdir($destination, 0755, true) && !is_dir($destination)) {
        error_log('Failed to create image upload directory: ' . $destination);
        return ['success' => false, 'message' => 'The server could not prepare image storage. Please contact the administrator.'];
    }

    if (!is_writable($destination)) {
        error_log('Image upload directory is not writable: ' . $destination);
        return ['success' => false, 'message' => 'The server cannot save images right now. Please contact the administrator.'];
    }

    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'item_' . uniqid() . '_' . time() . '.' . $extension;
    $filepath = $destination . $filename;

    if (@move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }

    error_log('Failed to move uploaded image to: ' . $filepath);
    return ['success' => false, 'message' => 'The server could not save the uploaded image. Please try again.'];
}

// Create notification
function createNotification($user_id, $title, $message, $type = 'system', $link = null)
{
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $title, $message, $type, $link]);
}

// Get unread notifications count
function getUnreadCount($user_id)
{
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Format date
function formatDate($date)
{
    return date('M d, Y', strtotime($date));
}

// Format datetime
function formatDateTime($datetime)
{
    return date('M d, Y h:i A', strtotime($datetime));
}

// Time ago
function timeAgo($datetime)
{
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    if ($diff->y > 0)
        return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0)
        return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0)
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0)
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0)
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

// Redirect
function redirect($url)
{
    header("Location: $url");
    exit();
}

// Flash messages
function setFlash($type, $message)
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash()
{
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// Get categories
function getCategories()
{
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->query("SELECT * FROM categories WHERE is_active = 1 ORDER BY name");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get category by ID
function getCategoryById($id)
{
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Get item by ID
function getItemById($id)
{
    $database = new Database();
    $conn = $database->getConnection();
    $stmt = $conn->prepare("
        SELECT i.*, c.name as category_name, c.icon as category_icon, 
               u.full_name as reporter_name, u.email as reporter_email
        FROM items i 
        LEFT JOIN categories c ON i.category_id = c.id
        LEFT JOIN users u ON i.user_id = u.id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

// Log activity
function logActivity($user_id, $action, $description = null)
{
    $database = new Database();
    $conn = $database->getConnection();
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip]);
}
?>