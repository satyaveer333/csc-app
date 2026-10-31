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

    $now = time();
    $today = date('Y-m-d');
    $loginDate = $_SESSION['login_date'] ?? $today;
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    $midnightPostponeWindow = 1800; // 30 minutes postponement window at midnight

    // Auto-logout evaluation exclusively at Midnight (calendar day rollover)
    if ($today !== $loginDate) {
        // If owner was inactive for more than 30 minutes past midnight -> Auto Logout
        if (($now - $lastActivity) > $midnightPostponeWindow) {
            ownerLogout();
            header('Location: login.php?reason=midnight');
            exit;
        }

        // Owner is actively using the portal at midnight -> Postpone by 30 minutes each time
        $_SESSION['last_activity'] = $now;
        return;
    }

    // Daytime: No auto-logout during the same calendar day
    $_SESSION['last_activity'] = $now;
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
            $_SESSION['login_date'] = date('Y-m-d');
            $_SESSION['last_activity'] = time();
            return true;
        }

        // Only allow fallback if password hash is completely empty in settings
        if (empty($adminHash) && $password === DEFAULT_ADMIN_PASS) {
            $_SESSION['owner_logged_in'] = true;
            $_SESSION['owner_username'] = $username;
            $_SESSION['login_date'] = date('Y-m-d');
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
    unset($_SESSION['login_date']);
    unset($_SESSION['last_activity']);
    session_destroy();
}
