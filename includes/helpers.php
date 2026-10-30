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

function getSettings($pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    static $settingsCache = null;
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
    $stmt = $pdo->prepare("
        INSERT INTO settings (setting_key, setting_value, updated_at) 
        VALUES (:k, :v, CURRENT_TIMESTAMP)
        ON DUPLICATE KEY UPDATE setting_value = :v, updated_at = CURRENT_TIMESTAMP
    ");
    // SQLite syntax vs MySQL
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("
            INSERT INTO settings (setting_key, setting_value, updated_at) 
            VALUES (:k, :v, datetime('now'))
            ON CONFLICT(setting_key) DO UPDATE SET setting_value = :v, updated_at = datetime('now')
        ");
    }
    return $stmt->execute([':k' => $key, ':v' => $value]);
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
