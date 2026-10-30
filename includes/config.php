<?php
/**
 * CSC Digital Seva Portal - Configuration
 * Optimized for Serverbyt Blaze Plan (StackCP / cPanel / Apache / PHP 7.4 - 8.3)
 */

if (!defined('CSC_APP')) {
    define('CSC_APP', true);
}

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    session_start();
}

// Environment & Error Handling
// In production, display_errors should be 0, but log errors.
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Application Paths
define('APP_ROOT', dirname(__DIR__));
define('UPLOADS_DIR', APP_ROOT . '/uploads');
define('PRINTS_DIR', UPLOADS_DIR . '/prints');
define('FORMS_DIR', UPLOADS_DIR . '/forms');
define('DATA_DIR', APP_ROOT . '/data');

// Database Configuration
// By default, uses SQLite so it works INSTANTLY with 0 setup on Serverbyt.
// To use MySQL on Serverbyt Blaze, simply set DB_DRIVER to 'mysql' and fill credentials.
define('DB_DRIVER', 'sqlite'); // 'sqlite' or 'mysql'

// SQLite settings
define('SQLITE_FILE', DATA_DIR . '/csc_database.sqlite');

// MySQL settings (Optional - for when you create a MySQL database in Serverbyt StackCP)
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'csc_db');
define('DB_USER', 'csc_user');
define('DB_PASS', 'your_password');
define('DB_CHARSET', 'utf8mb4');

// Default App Settings (can be overridden in Admin Settings)
define('DEFAULT_ADMIN_USER', 'admin');
define('DEFAULT_ADMIN_PASS', 'admin123'); // Prompt to change in admin panel
define('DEFAULT_CENTER_NAME', 'Digital Solutions');
define('DEFAULT_OWNER_NAME', 'Digital Solutions Operator');
define('DEFAULT_OWNER_PHONE', '+91 9876543210');
define('DEFAULT_OWNER_EMAIL', 'csc.center@example.com');
define('DEFAULT_CENTER_ADDRESS', 'Main Market Road, Near Panchayat Bhawan');

// Max file upload size (in bytes) - e.g. 25MB total
define('MAX_FILE_SIZE', 25 * 1024 * 1024);
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'txt', 'xls', 'xlsx']);
