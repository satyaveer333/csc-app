<?php
/**
 * CSC Digital Seva Portal - Configuration
 * Optimized for Serverbyt Blaze Plan (StackCP / cPanel / Apache / PHP 7.4 - 8.3)
 */

if (!defined('CSC_APP')) {
    define('CSC_APP', true);
}

// Application Paths
define('APP_ROOT', dirname(__DIR__));
define('UPLOADS_DIR', APP_ROOT . '/uploads');
define('PRINTS_DIR', UPLOADS_DIR . '/prints');
define('FORMS_DIR', UPLOADS_DIR . '/forms');
define('DATA_DIR', APP_ROOT . '/data');

// -----------------------------------------------------------------------------
// Environment (.env) Loader
// -----------------------------------------------------------------------------
$envFilePath = APP_ROOT . '/.env';
if (file_exists($envFilePath) && is_readable($envFilePath)) {
    $envLines = @file($envFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (is_array($envLines)) {
        foreach ($envLines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || $line[0] === ';') {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($envKey, $envVal) = explode('=', $line, 2);
                $envKey = trim($envKey);
                $envVal = trim($envVal);

                // Strip quotes or trailing unquoted inline comments
                if (preg_match('/^([\'"])(.*)\1$/', $envVal, $m)) {
                    $envVal = $m[2];
                } else {
                    $parts = explode('#', $envVal, 2);
                    $envVal = trim($parts[0]);
                }

                // Inject into environment if not already defined at server level
                if (getenv($envKey) === false) {
                    putenv("{$envKey}={$envVal}");
                    $_ENV[$envKey] = $envVal;
                    $_SERVER[$envKey] = $envVal;
                }
            }
        }
    }
}

// -----------------------------------------------------------------------------
// Application & Environment Settings
// -----------------------------------------------------------------------------
define('APP_NAME', getenv('APP_NAME') ?: 'CSC Digital Seva Portal');
define('APP_ENV', getenv('APP_ENV') ?: 'production'); // 'local', 'development', 'staging', 'production'
$appDebug = filter_var(getenv('APP_DEBUG') ?: false, FILTER_VALIDATE_BOOLEAN);
define('APP_DEBUG', $appDebug);
define('APP_URL', getenv('APP_URL') ?: 'http://localhost:8080');

// Timezone for accurate midnight (12:00 AM) auto-logout detection
date_default_timezone_set(getenv('APP_TIMEZONE') ?: 'Asia/Kolkata');

// Session configuration - keep server session storage active across the day
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    $sessionLifetime = (int)(getenv('SESSION_LIFETIME') ?: 172800); // 48 hours default
    ini_set('session.gc_maxlifetime', $sessionLifetime > 0 ? $sessionLifetime : 172800);
    session_start();
}

// Error Reporting
if (APP_DEBUG || APP_ENV === 'local' || APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
}

// -----------------------------------------------------------------------------
// Database Configuration
// -----------------------------------------------------------------------------
// 'sqlite' (zero-setup local development) or 'mysql' (production server).
define('DB_DRIVER', getenv('DB_DRIVER') ?: 'sqlite'); // 'sqlite' or 'mysql'

// SQLite settings (Used when DB_DRIVER === 'sqlite')
$sqliteSetting = getenv('SQLITE_FILE') ?: 'data/csc_database.sqlite';
if (strpos($sqliteSetting, '/') === 0) {
    define('SQLITE_FILE', $sqliteSetting);
} else {
    define('SQLITE_FILE', APP_ROOT . '/' . ltrim($sqliteSetting, '/'));
}

// MySQL settings (Used when DB_DRIVER === 'mysql')
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_PORT', (int)(getenv('DB_PORT') ?: 3306));
define('DB_NAME', getenv('DB_NAME') ?: 'csc_db');
define('DB_USER', getenv('DB_USER') ?: 'csc_user');
define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'your_password');
define('DB_CHARSET', getenv('DB_CHARSET') ?: 'utf8mb4');

// -----------------------------------------------------------------------------
// Owner Defaults & Authorization Secrets
// -----------------------------------------------------------------------------
define('DEFAULT_ADMIN_USER', getenv('DEFAULT_ADMIN_USER') ?: 'admin');
define('DEFAULT_ADMIN_PASS', getenv('DEFAULT_ADMIN_PASS') ?: 'admin123');
define('DEFAULT_CENTER_NAME', getenv('DEFAULT_CENTER_NAME') ?: 'Digital Solutions');
define('DEFAULT_OWNER_NAME', getenv('DEFAULT_OWNER_NAME') ?: 'Digital Solutions Operator');
define('DEFAULT_OWNER_PHONE', getenv('DEFAULT_OWNER_PHONE') ?: '+91 9876543210');
define('DEFAULT_OWNER_EMAIL', getenv('DEFAULT_OWNER_EMAIL') ?: 'csc.center@example.com');
define('DEFAULT_CENTER_ADDRESS', getenv('DEFAULT_CENTER_ADDRESS') ?: 'Main Market Road, Near Panchayat Bhawan');
define('DEFAULT_VLE_ID', getenv('DEFAULT_VLE_ID') ?: 'CSC-98745612');

define('DEVELOPER_EMAIL', getenv('DEVELOPER_EMAIL') ?: 'vyasthperson@gmail.com');
define('DEFAULT_DEVELOPER_EMAIL_HASH', getenv('DEVELOPER_EMAIL_HASH') ?: (
    getenv('DEVELOPER_EMAIL') 
        ? hash('sha256', strtolower(trim(getenv('DEVELOPER_EMAIL'))))
        : '0dc6d2bc23045997492ae6458d7626550d2af7f225663466561342c5d71384f7'
));

// -----------------------------------------------------------------------------
// File Upload & Restriction Settings
// -----------------------------------------------------------------------------
$maxMb = (int)(getenv('MAX_FILE_SIZE_MB') ?: 25);
define('MAX_FILE_SIZE', ($maxMb > 0 ? $maxMb : 25) * 1024 * 1024);

$allowedExtsEnv = getenv('ALLOWED_EXTENSIONS');
if (!empty($allowedExtsEnv)) {
    define('ALLOWED_EXTENSIONS', array_map('trim', explode(',', $allowedExtsEnv)));
} else {
    define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx']);
}
