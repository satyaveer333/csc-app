<?php
/**
 * Helper Functions for CSC Digital Seva Portal
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

function ensureDirectories() {
    $dirs = [DATA_DIR, UPLOADS_DIR, PRINTS_DIR, FORMS_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        @chmod($dir, 0777);
    }
}

function getSettings($pdo = null, $invalidate = false) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    static $settingsCache = null;
    if ($invalidate) {
        $settingsCache = null;
    }
    if ($settingsCache !== null) {
        return $settingsCache;
    }

    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settingsCache = [];
    while ($row = $stmt->fetch()) {
        $settingsCache[$row['setting_key']] = $row['setting_value'];
    }
    return $settingsCache;
}

function getSetting($key, $default = '', $pdo = null) {
    $settings = getSettings($pdo);
    return isset($settings[$key]) ? $settings[$key] : $default;
}

function updateSetting($key, $value, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (:k, :v, datetime('now'))
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = excluded.setting_value, updated_at = datetime('now')
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (:k, :v, CURRENT_TIMESTAMP)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = CURRENT_TIMESTAMP
        ");
    }
    $result = $stmt->execute([':k' => $key, ':v' => $value]);
    getSettings($pdo, true); // Invalidate cache so updated value is immediately available
    return $result;
}

function e($string) {
    return htmlspecialchars((string)$string, ENT_QUOTES, 'UTF-8');
}

function generateTokenId() {
    $prefix = 'CSC-PRN';
    $random = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
    $timestamp = date('d');
    return "{$prefix}-{$timestamp}-{$random}";
}

function formatCurrency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

function formatDate($date, $format = 'd M, Y') {
    if (empty($date) || $date === '0000-00-00') {
        return 'No Deadline / Always Open';
    }
    $time = strtotime($date);
    return $time ? date($format, $time) : $date;
}

function getCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Application Secret Key Derivation for Sensitive Data Encryption at Rest
 */
function getAppSecretKey() {
    static $key = null;
    if ($key !== null) {
        return $key;
    }
    $envKey = getenv('APP_SECRET_KEY');
    if (!empty($envKey)) {
        $key = hash('sha256', $envKey, true);
    } else {
        $key = hash('sha256', (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '_csc_portal_secret_salt_2026', true);
    }
    return $key;
}

/**
 * Encrypt sensitive credentials (e.g. SMTP password) before storing in DB.
 * Stored as an authenticated ciphertext string in format ENC:<base64(iv . hmac . ciphertext)>.
 */
function encryptSensitiveData($plaintext) {
    if ($plaintext === '' || $plaintext === null) {
        return '';
    }
    if (!extension_loaded('openssl')) {
        return (string)$plaintext;
    }
    $key = getAppSecretKey();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt((string)$plaintext, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    $hmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    return 'ENC:' . base64_encode($iv . $hmac . $cipher);
}

/**
 * Decrypt sensitive credentials retrieved from DB.
 */
function decryptSensitiveData($ciphertext) {
    if (empty($ciphertext) || !is_string($ciphertext)) {
        return '';
    }
    if (strpos($ciphertext, 'ENC:') !== 0) {
        return $ciphertext; // Return as-is if unencrypted or legacy
    }
    if (!extension_loaded('openssl')) {
        return '';
    }
    $raw = base64_decode(substr($ciphertext, 4), true);
    if ($raw === false || strlen($raw) < 48) {
        return '';
    }
    $iv = substr($raw, 0, 16);
    $hmac = substr($raw, 16, 32);
    $cipher = substr($raw, 48);
    $key = getAppSecretKey();
    $expectedHmac = hash_hmac('sha256', $iv . $cipher, $key, true);
    if (!hash_equals($expectedHmac, $hmac)) {
        return ''; // Data has been tampered with or invalid key
    }
    $decrypted = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? $decrypted : '';
}
