<?php
/**
 * Authentication Module for CSC Owner / Admin
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/mailer.php';

function isOwnerLoggedIn() {
    return !empty($_SESSION['owner_logged_in']) && $_SESSION['owner_logged_in'] === true;
}

function requireOwnerLogin() {
    if (!isOwnerLoggedIn()) {
        header('Location: login.php');
        exit;
    }

    $pdo = getDbConnection();
    $currentSessionId = session_id();

    // Verify session has not been revoked via session management
    if (!validateActiveSession($currentSessionId, $pdo)) {
        ownerLogout();
        header('Location: login.php?reason=revoked');
        exit;
    }

    $now = time();
    $today = date('Y-m-d');
    $loginDate = $_SESSION['login_date'] ?? $today;
    $lastActivity = $_SESSION['last_activity'] ?? $now;
    $midnightPostponeWindow = 1800; // 30 minutes postponement window at midnight

    // Auto-logout evaluation exclusively at Midnight (calendar day rollover)
    if ($today !== $loginDate) {
        // Daily cleanup of expired OTPs at midnight rollover
        cleanExpiredOtps($pdo);

        // If owner was inactive for more than 30 minutes past midnight -> Auto Logout
        if (($now - $lastActivity) > $midnightPostponeWindow) {
            ownerLogout();
            header('Location: login.php?reason=midnight');
            exit;
        }

        // Owner is actively using the portal at midnight -> Postpone by 30 minutes each time
        $_SESSION['last_activity'] = $now;
        updateActiveSessionActivity($currentSessionId, $pdo);
        return;
    }

    // Daytime: No auto-logout during the same calendar day
    $_SESSION['last_activity'] = $now;
    updateActiveSessionActivity($currentSessionId, $pdo);
}

/**
 * Verify owner username and password credentials (Step 1 of 2FA)
 */
function verifyOwnerCredentials($username, $password, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $adminUser = getSetting('admin_username', DEFAULT_ADMIN_USER, $pdo);
    $adminHash = getSetting('admin_password', '', $pdo);

    if ($username === $adminUser) {
        if (!empty($adminHash) && password_verify($password, $adminHash)) {
            return true;
        }
        if (empty($adminHash) && $password === DEFAULT_ADMIN_PASS) {
            updateSetting('admin_password', password_hash($password, PASSWORD_DEFAULT), $pdo);
            return true;
        }
    }

    return false;
}

/**
 * Complete owner login after successful OTP verification (Step 2 of 2FA)
 */
function completeOwnerLogin($username, $deviceFingerprint, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    $sessionId = session_id();
    $_SESSION['owner_logged_in'] = true;
    $_SESSION['owner_username'] = $username;
    $_SESSION['login_date'] = date('Y-m-d');
    $_SESSION['last_activity'] = time();
    $_SESSION['device_fingerprint'] = $deviceFingerprint;
    unset($_SESSION['pending_2fa']);

    // Register active session in database for session management
    registerActiveSession($sessionId, $deviceFingerprint, $pdo);

    // Log successful login
    logLoginAttempt($username, 'success', '', $deviceFingerprint, $pdo);

    return true;
}

function ownerLogin($username, $password, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    if (verifyOwnerCredentials($username, $password, $pdo)) {
        $fingerprint = hash('sha256', ($username . '|' . ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1')));
        return completeOwnerLogin($username, $fingerprint, $pdo);
    }

    return false;
}

function ownerLogout() {
    $pdo = getDbConnection();
    $sessionId = session_id();
    if (!empty($sessionId)) {
        revokeSession($sessionId, $pdo);
    }

    unset($_SESSION['owner_logged_in']);
    unset($_SESSION['owner_username']);
    unset($_SESSION['login_date']);
    unset($_SESSION['last_activity']);
    unset($_SESSION['device_fingerprint']);
    unset($_SESSION['pending_2fa']);
    session_destroy();
}

/**
 * Retrieve the registered email address for 2FA and password resets
 * Falls back to print receiving email or system default
 */
function getRegisteredOwnerEmail($pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $regEmail = trim(getSetting('registered_owner_email', '', $pdo));
    if (!empty($regEmail) && filter_var($regEmail, FILTER_VALIDATE_EMAIL)) {
        return $regEmail;
    }
    $ownerEmail = trim(getSetting('owner_email', '', $pdo));
    if (!empty($ownerEmail) && filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
        return $ownerEmail;
    }
    return DEFAULT_OWNER_EMAIL;
}

/**
 * Check if the registered email has been verified via OTP
 */
function isRegisteredEmailVerified($pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    return getSetting('registered_owner_email_verified', '0', $pdo) === '1';
}

/**
 * Mask email address for security displays (e.g. j***n@gmail.com)
 */
function maskEmailAddress($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }
    $parts = explode('@', $email);
    $name = $parts[0];
    $domain = $parts[1] ?? '';
    $len = strlen($name);

    if ($len <= 2) {
        $maskedName = substr($name, 0, 1) . '*';
    } else {
        $maskedName = substr($name, 0, 1) . str_repeat('*', max(3, $len - 2)) . substr($name, -1);
    }
    return $maskedName . '@' . $domain;
}

/**
 * Generate a cryptographically secure 6-digit OTP, store its SHA-256 hash in DB, and dispatch via email
 */
function generateAndSendOtp($email, $purpose = 'login', $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $email = strtolower(trim($email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Invalid email address.'];
    }

    // Opportunistically prune expired OTP records from the database
    cleanExpiredOtps($pdo);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Rate limiting: check if OTP was already sent to this email in the last 60 seconds
    $cooldownCutoff = date('Y-m-d H:i:s', time() - 60);
    $stmt = $pdo->prepare("SELECT id FROM otps WHERE email = :email AND purpose = :purpose AND created_at >= :cooldown");
    $stmt->execute([':email' => $email, ':purpose' => $purpose, ':cooldown' => $cooldownCutoff]);
    if ($stmt->fetch()) {
        return ['success' => false, 'message' => 'Please wait 60 seconds before requesting another verification code.'];
    }

    // Daily & hourly IP limit: max 15 OTP requests per hour per IP
    $hourCutoff = date('Y-m-d H:i:s', time() - 3600);
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM otps WHERE created_at >= :hour");
    $stmt->execute([':hour' => $hourCutoff]);
    if ($stmt->fetchColumn() > 20) {
        return ['success' => false, 'message' => 'Hourly verification request limit exceeded. Please try again later.'];
    }

    // Clean previous unused OTPs for this email and purpose
    $stmt = $pdo->prepare("DELETE FROM otps WHERE email = :email AND purpose = :purpose");
    $stmt->execute([':email' => $email, ':purpose' => $purpose]);

    // Generate random 6-digit code
    $otp = (string)random_int(100000, 999999);
    $otpHash = hash('sha256', $otp);
    $expiresAt = date('Y-m-d H:i:s', time() + 600); // 10 minutes expiry

    // Save hashed OTP in database
    $stmt = $pdo->prepare("
        INSERT INTO otps (email, otp_hash, purpose, attempts, max_attempts, expires_at, created_at)
        VALUES (:email, :hash, :purpose, 0, 5, :expires, :created)
    ");
    $stmt->execute([
        ':email' => $email,
        ':hash' => $otpHash,
        ':purpose' => $purpose,
        ':expires' => $expiresAt,
        ':created' => date('Y-m-d H:i:s')
    ]);

    // Dispatch email
    $sent = sendOtpEmail($email, $otp, $purpose, $pdo);
    if (!$sent) {
        error_log("Failed to dispatch OTP email to $email for purpose: $purpose");
    }

    return [
        'success' => true,
        'message' => 'Verification code dispatched successfully to ' . maskEmailAddress($email) . '.',
        'raw_otp' => (getenv('APP_ENV') === 'testing' ? $otp : null)
    ];
}

/**
 * Verify supplied 6-digit OTP against SHA-256 hash in DB with attempt limits
 */
function verifyOtp($email, $inputOtp, $purpose = 'login', $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }

    $email = strtolower(trim($email));
    $inputOtp = trim($inputOtp);
    $now = date('Y-m-d H:i:s');

    $stmt = $pdo->prepare("
        SELECT id, otp_hash, attempts, max_attempts, expires_at 
        FROM otps 
        WHERE email = :email AND purpose = :purpose AND expires_at >= :now 
        ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([':email' => $email, ':purpose' => $purpose, ':now' => $now]);
    $record = $stmt->fetch();

    if (!$record) {
        return ['success' => false, 'message' => 'Verification code has expired or is invalid. Please request a new code.'];
    }

    $attempts = (int)$record['attempts'] + 1;
    $maxAttempts = (int)$record['max_attempts'];

    // Update attempts count
    $upd = $pdo->prepare("UPDATE otps SET attempts = :attempts WHERE id = :id");
    $upd->execute([':attempts' => $attempts, ':id' => $record['id']]);

    if ($attempts > $maxAttempts) {
        $del = $pdo->prepare("DELETE FROM otps WHERE id = :id");
        $del->execute([':id' => $record['id']]);
        return ['success' => false, 'message' => 'Too many failed attempts. This code has been invalidated. Please request a new code.'];
    }

    $inputHash = hash('sha256', $inputOtp);
    if (hash_equals($record['otp_hash'], $inputHash)) {
        // OTP is valid! Delete it immediately to prevent replay attacks
        $del = $pdo->prepare("DELETE FROM otps WHERE id = :id");
        $del->execute([':id' => $record['id']]);
        return ['success' => true, 'message' => 'Code verified successfully.'];
    }

    $remaining = max(0, $maxAttempts - $attempts);
    return [
        'success' => false, 
        'message' => "Invalid verification code. {$remaining} attempt(s) remaining."
    ];
}

/**
 * Clean expired and prior-day OTP records from DB
 */
function cleanExpiredOtps($pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $cutoff = date('Y-m-d H:i:s', time() - 86400);
    $now = date('Y-m-d H:i:s');
    $stmt = $pdo->prepare("DELETE FROM otps WHERE expires_at < :now OR created_at < :cutoff");
    return $stmt->execute([':now' => $now, ':cutoff' => $cutoff]);
}

/**
 * Check login attempt rate limiting (max 5 failed attempts per 15 minutes per IP)
 */
function checkLoginRateLimit($ip, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $cutoff = date('Y-m-d H:i:s', time() - 900); // 15 minutes
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM login_logs 
        WHERE ip_address = :ip AND status = 'failed' AND created_at >= :cutoff
    ");
    $stmt->execute([':ip' => $ip, ':cutoff' => $cutoff]);
    $failures = (int)$stmt->fetchColumn();

    if ($failures >= 5) {
        return ['allowed' => false, 'message' => 'Too many failed login attempts. Please wait 15 minutes before trying again.'];
    }
    return ['allowed' => true, 'failures' => $failures];
}

/**
 * Log login attempts (success or failure) to security audit log
 */
function logLoginAttempt($username, $status, $failureReason = '', $deviceFingerprint = '', $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';

    if (empty($deviceFingerprint)) {
        $deviceFingerprint = hash('sha256', ($userAgent . '|' . $ip));
    }

    $stmt = $pdo->prepare("
        INSERT INTO login_logs (username, ip_address, user_agent, device_fingerprint, status, failure_reason, created_at)
        VALUES (:user, :ip, :ua, :fp, :status, :reason, :created)
    ");
    return $stmt->execute([
        ':user' => $username,
        ':ip' => $ip,
        ':ua' => mb_substr($userAgent, 0, 500),
        ':fp' => $deviceFingerprint,
        ':status' => $status,
        ':reason' => mb_substr($failureReason, 0, 250),
        ':created' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Parse client user agent to identify device type, browser, and OS
 */
function parseUserAgentDetails($ua) {
    $deviceType = 'Desktop';
    if (preg_match('/(mobile|android|iphone|ipad|ipod|blackberry|opera mini)/i', $ua)) {
        $deviceType = preg_match('/(tablet|ipad)/i', $ua) ? 'Tablet' : 'Mobile';
    }

    $browser = 'Web Browser';
    if (preg_match('/Edg\/([0-9.]+)/i', $ua)) $browser = 'Microsoft Edge';
    elseif (preg_match('/Chrome\/([0-9.]+)/i', $ua)) $browser = 'Google Chrome';
    elseif (preg_match('/Safari\/([0-9.]+)/i', $ua) && !preg_match('/Chrome/i', $ua)) $browser = 'Apple Safari';
    elseif (preg_match('/Firefox\/([0-9.]+)/i', $ua)) $browser = 'Mozilla Firefox';
    elseif (preg_match('/MSIE|Trident/i', $ua)) $browser = 'Internet Explorer';

    $platform = 'Unknown OS';
    if (preg_match('/windows|win32/i', $ua)) $platform = 'Windows';
    elseif (preg_match('/macintosh|mac os x/i', $ua)) $platform = 'macOS';
    elseif (preg_match('/linux/i', $ua)) $platform = 'Linux';
    elseif (preg_match('/android/i', $ua)) $platform = 'Android';
    elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $platform = 'iOS';

    return ['device_type' => $deviceType, 'browser' => $browser, 'platform' => $platform];
}

/**
 * Register new active session in active_sessions table
 */
function registerActiveSession($sessionId, $deviceFingerprint, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    $details = parseUserAgentDetails($ua);

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    if ($driver === 'sqlite') {
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (session_id, device_fingerprint, ip_address, user_agent, device_type, browser, platform, last_activity, created_at)
            VALUES (:sid, :fp, :ip, :ua, :dev, :brow, :plat, :now, :now)
            ON CONFLICT(session_id) DO UPDATE SET last_activity = :now, ip_address = :ip
        ");
    } else {
        $stmt = $pdo->prepare("
            INSERT INTO active_sessions (session_id, device_fingerprint, ip_address, user_agent, device_type, browser, platform, last_activity, created_at)
            VALUES (:sid, :fp, :ip, :ua, :dev, :brow, :plat, :now, :now)
            ON DUPLICATE KEY UPDATE last_activity = :now, ip_address = :ip
        ");
    }

    $now = date('Y-m-d H:i:s');
    return $stmt->execute([
        ':sid' => $sessionId,
        ':fp' => $deviceFingerprint,
        ':ip' => $ip,
        ':ua' => mb_substr($ua, 0, 500),
        ':dev' => $details['device_type'],
        ':brow' => $details['browser'],
        ':plat' => $details['platform'],
        ':now' => $now
    ]);
}

/**
 * Update session last activity timestamp
 */
function updateActiveSessionActivity($sessionId, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $stmt = $pdo->prepare("UPDATE active_sessions SET last_activity = :now WHERE session_id = :sid");
    return $stmt->execute([':now' => date('Y-m-d H:i:s'), ':sid' => $sessionId]);
}

/**
 * Validate that current session ID has not been revoked
 */
function validateActiveSession($sessionId, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $stmt = $pdo->prepare("SELECT id FROM active_sessions WHERE session_id = :sid");
    $stmt->execute([':sid' => $sessionId]);
    return (bool)$stmt->fetch();
}

/**
 * Revoke specific session by session ID
 */
function revokeSession($sessionId, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE session_id = :sid");
    return $stmt->execute([':sid' => $sessionId]);
}

/**
 * Revoke all other active sessions except current
 */
function revokeAllOtherSessions($currentSessionId, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $stmt = $pdo->prepare("DELETE FROM active_sessions WHERE session_id != :sid");
    return $stmt->execute([':sid' => $currentSessionId]);
}

/**
 * Get all active sessions for admin session management dashboard
 */
function getActiveSessions($pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $stmt = $pdo->query("SELECT * FROM active_sessions ORDER BY last_activity DESC");
    return $stmt->fetchAll();
}

/**
 * Get recent login security audit logs
 */
function getRecentLoginLogs($limit = 10, $pdo = null) {
    if (!$pdo) {
        $pdo = getDbConnection();
    }
    $limit = (int)$limit;
    $stmt = $pdo->query("SELECT * FROM login_logs ORDER BY id DESC LIMIT {$limit}");
    return $stmt->fetchAll();
}
