<?php

require_once __DIR__ . '/load-env.php';
$environmentFile = loadEnvironmentFile(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

$privateConfig = [];
$externalConfigPath = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'private-config.php';
$localConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'private.php';
$databaseConfigPath = __DIR__ . DIRECTORY_SEPARATOR . 'database.local.php';
$requestHost = strtolower(preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost'));
$isLocalDevelopment = in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true)
    || preg_match('/\.localhost$/', $requestHost);

if ($isLocalDevelopment && is_file($databaseConfigPath)) {
    $databaseConfig = require $databaseConfigPath;
    if (!is_array($databaseConfig)) {
        throw new RuntimeException('Local database configuration must return an array.');
    }
    $privateConfig = [
        'DB_HOST' => $databaseConfig['host'] ?? '',
        'DB_NAME' => $databaseConfig['name'] ?? '',
        'DB_USER' => $databaseConfig['username'] ?? '',
        'DB_PASSWORD' => $databaseConfig['password'] ?? '',
    ];
}

if ($isLocalDevelopment && is_file($localConfigPath)) {
    $localPrivateConfig = require $localConfigPath;
    if (!is_array($localPrivateConfig)) {
        throw new RuntimeException('Private configuration must return an array.');
    }
    $privateConfig = array_merge($privateConfig, $localPrivateConfig);
}

if (is_file($externalConfigPath)) {
    $externalPrivateConfig = require $externalConfigPath;
    if (!is_array($externalPrivateConfig)) {
        throw new RuntimeException('Private configuration must return an array.');
    }
    $privateConfig = array_merge($privateConfig, $externalPrivateConfig);
}

$setting = function ($name, $default = '') use (&$privateConfig, &$environmentFile) {
    $environmentValue = getenv($name);
    if ($environmentValue !== false) {
        return $environmentValue;
    }
    if (array_key_exists($name, $privateConfig)) {
        return $privateConfig[$name];
    }
    return $environmentFile[$name] ?? $default;
};

date_default_timezone_set('Asia/Manila');

$serverProtocol = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443) ? 'https://' : 'http://';
$serverHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$scriptFolder = basename($scriptDirectory);
if (in_array($scriptFolder, ['admin', 'api'], true)) {
    $scriptDirectory = dirname($scriptDirectory);
}
if ($scriptDirectory === '/' || $scriptDirectory === '\\' || $scriptDirectory === '.') {
    $scriptDirectory = '';
}

define('SITE_NAME', 'Lost and Found');
define('SITE_URL', $serverProtocol . $serverHost . $scriptDirectory);
define('BASE_PATH', realpath(__DIR__ . '/..'));

define('DB_HOST', $setting('DB_HOST'));
define('DB_NAME', $setting('DB_NAME'));
define('DB_USER', $setting('DB_USER'));
define('DB_PASSWORD', $setting('DB_PASSWORD'));

define('SMTP_HOST', $setting('SMTP_HOST', 'smtp.gmail.com'));
define('SMTP_PORT', (int) $setting('SMTP_PORT', 587));
define('SMTP_ENCRYPTION', strtolower($setting('SMTP_ENCRYPTION', 'tls')));
define('SMTP_USERNAME', $setting('SMTP_USERNAME'));
define('SMTP_PASSWORD', $setting('SMTP_PASSWORD'));
define('SMTP_FROM_EMAIL', $setting('SMTP_FROM_EMAIL', SMTP_USERNAME));
define('SMTP_FROM_NAME', $setting('SMTP_FROM_NAME', 'Lost and Found'));

define('UPLOAD_PATH', BASE_PATH . '/uploads/');
define('ITEMS_UPLOAD', UPLOAD_PATH . 'items/');
define('AVATARS_UPLOAD', UPLOAD_PATH . 'avatars/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('ITEMS_PER_PAGE', 12);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
