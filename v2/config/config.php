<?php
/**
 * Application Configuration
 * 
 * This file contains all application settings.
 * Secrets (DB password, encryption key, cron token) are NOT stored here —
 * they are read from the gitignored `.env` file in this same folder.
 * 
 * @package Config
 */

// Prevent direct access
if (!defined('ROOT_PATH')) {
    exit('Direct access not permitted');
}

// ==================
// Load Secrets from .env (gitignored — never commit secrets)
// ==================
$_envFile = __DIR__ . '/.env';
$_env = [];
if (file_exists($_envFile)) {
    foreach (file($_envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $_line) {
        $_line = trim($_line);
        if ($_line === '' || $_line[0] === '#' || strpos($_line, '=') === false) {
            continue;
        }
        [$_key, $_value] = explode('=', $_line, 2);
        $_key = trim($_key);
        $_value = trim($_value);
        // Strip surrounding quotes if present
        $_len = strlen($_value);
        if ($_len >= 2 && (($_value[0] === '"' && $_value[$_len - 1] === '"') || ($_value[0] === "'" && $_value[$_len - 1] === "'"))) {
            $_value = substr($_value, 1, -1);
        }
        $_env[$_key] = $_value;
        putenv("{$_key}={$_value}");
        $_ENV[$_key] = $_value;
    }
}

// Read a secret from the environment, exiting with a clear message if missing
$_secret = function (string $key): string {
    $value = getenv($key);
    if ($value === false) {
        $value = $_ENV[$key] ?? '';
    }
    if ($value === '') {
        exit("Configuration error: missing {$key}. Add it to " . basename(__DIR__) . "/.env (copy .env.example first).");
    }
    return $value;
};

// ==================
// Database Settings (cPanel MySQL)
// ==================
define('DB_HOST', 'localhost');
define('DB_NAME', 'h421704_clinic');
define('DB_USER', $_env['DB_USER'] ?? 'h421704_clinic');
define('DB_PASS', $_secret('DB_PASS'));
define('DB_PORT', 3306);
define('DB_CHARSET', 'utf8mb4');

// ==================
// Application Settings
// ==================
define('APP_NAME', 'Dr. Zohrabi Nutrition Clinic');
define('APP_URL', isset($_SERVER['HTTP_HOST']) ? 'https://' . $_SERVER['HTTP_HOST'] : 'http://localhost');
define('APP_ENV', 'production'); // 'production' or 'development'

// ==================
// Security Settings
// ==================
define('SECURITY_KEY', $_env['SECURITY_KEY'] ?? 'f375a1ac763637bcc938bdee85dfb401'); // 32-char key
define('CRON_SECRET', $_env['CRON_SECRET'] ?? 'cf35a746a68dc3682dc3e320f150590b');   // Token for cron.php
define('SESSION_TIMEOUT', 1800); // 30 minutes inactivity timeout
define('REMEMBER_ME_DAYS', 30); // Remember me cookie duration
define('BCRYPT_COST', 12); // Password hashing cost factor

// ==================
// Upload Settings
// ==================
define('UPLOAD_PATH', ROOT_PATH . '/uploads');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_MIME_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// ==================
// Pagination Settings
// ==================
define('PER_PAGE', 50); // Records per page

// ==================
// Cache Settings
// ==================
define('CACHE_PATH', ROOT_PATH . '/cache');
define('CACHE_LIFETIME', 3600); // 1 hour default cache lifetime

// ==================
// Log Settings
// ==================
define('LOG_PATH', ROOT_PATH . '/logs');
define('LOG_MAX_SIZE', 5 * 1024 * 1024); // 5MB before rotation

// ==================
// Timezone
// ==================
date_default_timezone_set('Asia/Tehran');

// ==================
// Error Reporting
// ==================
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', LOG_PATH . '/error.log');
}