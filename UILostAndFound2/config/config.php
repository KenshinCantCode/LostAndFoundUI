<?php

$serverProtocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
if ($scriptDirectory === '/' || $scriptDirectory === '\\') {
    $scriptDirectory = '';
}

define('SITE_NAME', 'Lost and Found');
define('SITE_URL', $serverProtocol . $serverHost . $scriptDirectory);
define('BASE_PATH', realpath(__DIR__ . '/..'));

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'lostandfoundui@gmail.com');
define('SMTP_PASSWORD', 'kenshin091306');
define('SMTP_FROM_EMAIL', 'lostandfoundui@gmail.com');
define('SMTP_FROM_NAME', 'PHINMA UI Lost And Found');

define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('ITEMS_UPLOAD', UPLOAD_PATH . 'items/');
define('AVATARS_UPLOAD', UPLOAD_PATH . 'avatars/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('ITEMS_PER_PAGE', 12);

// Start session
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
