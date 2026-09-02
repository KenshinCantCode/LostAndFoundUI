<?php
// Site Configuration
define('SITE_NAME', 'PHINMA UI Lost And Found');
define('SITE_URL', 'http://localhost/campus-lost-found');
define('BASE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/campus-lost-found');

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'lostandfoundui@gmail.com');
define('SMTP_PASSWORD', 'kenshin091306');
define('SMTP_FROM_EMAIL', 'lostandfoundui@gmail.com');
define('SMTP_FROM_NAME', 'PHINMA UI Lost And Found');

// Upload Configuration
define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('ITEMS_UPLOAD', UPLOAD_PATH . 'items/');
define('AVATARS_UPLOAD', UPLOAD_PATH . 'avatars/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// Pagination
define('ITEMS_PER_PAGE', 12);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
