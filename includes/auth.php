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
}

function ownerLogin($username, $password, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $adminUser = getSetting('admin_username', DEFAULT_ADMIN_USER, $pdo);
    $adminHash = getSetting('admin_password', '', $pdo);

    if ($username === $adminUser && password_verify($password, $adminHash)) {
        $_SESSION['owner_logged_in'] = true;
        $_SESSION['owner_username'] = $username;
        $_SESSION['last_activity'] = time();
        return true;
    }

    // Emergency fallback if settings table had unhashed password during early setup
    if ($username === $adminUser && $password === DEFAULT_ADMIN_PASS) {
        $_SESSION['owner_logged_in'] = true;
        $_SESSION['owner_username'] = $username;
        $_SESSION['last_activity'] = time();
        // Update to proper hash
        updateSetting('admin_password', password_hash($password, PASSWORD_DEFAULT), $pdo);
        return true;
    }

    return false;
}

function ownerLogout() {
    unset($_SESSION['owner_logged_in']);
    unset($_SESSION['owner_username']);
    unset($_SESSION['last_activity']);
    session_destroy();
}
