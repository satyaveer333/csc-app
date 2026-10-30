<?php
/**
 * Authentication Module for CSC Owner / Admin
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function isOwnerLoggedIn() {
    return !empty($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true;
}

function requireOwnerLogin() {
    if (!isOwnerLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    // 30-minute idle session timeout
    $idleTimeout = 1800;
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $idleTimeout)) {
        ownerLogout();
        header('Location: login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

function ownerLogin($username, $password, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $adminUser = getSetting('admin_username', DEFAULT_ADMIN_USER, $pdo);
    $adminHash = getSetting('admin_password', '', $pdo);

    if ($username === $adminUser) {
        if (!empty($adminHash) && password_verify($password, $adminHash)) {
            $_SESSION['owner_logged_in'] = true;
            $_SESSION['owner_username'] = $username;
            $_SESSION['last_activity'] = time();
            return true;
        }

        // Only allow fallback if password hash is completely empty in settings
        if (empty($adminHash) && $password === DEFAULT_ADMIN_PASS) {
            $_SESSION['owner_logged_in'] = true;
            $_SESSION['owner_username'] = $username;
            $_SESSION['last_activity'] = time();
            updateSetting('admin_password', password_hash($password, PASSWORD_DEFAULT), $pdo);
            return true;
        }
    }

    return false;
}

function ownerLogout() {
    unset($_SESSION['owner_logged_in']);
    unset($_SESSION['owner_username']);
    unset($_SESSION['last_activity']);
    session_destroy();
}
