<?php
/**
 * CSC Owner Portal - Login with 2FA OTP, Forgot Password & Security Auditing
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

ensureDirectories();
$pdo = getDbConnection();

// Redirect if already logged in
if (isOwnerLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
$success = '';
$step = $_GET['step'] ?? '';
$action = $_GET['action'] ?? '';

if (isset($_GET['reset']) && $_GET['reset'] === 'success') {
    $success = 'Password updated successfully! Please sign in with your new password.';
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['form_action'] ?? 'login_credentials';
    $deviceFp = trim(strip_tags($_POST['device_fingerprint'] ?? ''));
    if (empty($deviceFp)) {
        $deviceFp = hash('sha256', ($ipAddress . '|' . ($_SERVER['HTTP_USER_AGENT'] ?? '')));
    }

    // 1. STEP 1: VERIFY CREDENTIALS & DISPATCH 2FA OTP
    if ($postAction === 'login_credentials') {
        $rateCheck = checkLoginRateLimit($ipAddress, $pdo);
        if (!$rateCheck['allowed']) {
            $error = $rateCheck['message'];
        } else {
            $username = trim(strip_tags($_POST['username'] ?? ''));
            $password = trim($_POST['password'] ?? '');

            if (empty($username) || empty($password)) {
                $error = 'Please enter both username and password.';
            } elseif (!verifyOwnerCredentials($username, $password, $pdo)) {
                logLoginAttempt($username, 'failed', 'Invalid username or password', $deviceFp, $pdo);
                $error = 'Invalid username or password. Please try again.';
            } else {
                // If direct OTP was provided in same post (e.g. automated test/script)
                if (isset($_POST['otp']) && !empty($_POST['otp'])) {
                    $regEmail = getRegisteredOwnerEmail($pdo);
                    $otpCheck = verifyOtp($regEmail, $_POST['otp'], 'login', $pdo);
                    if ($otpCheck['success']) {
                        completeOwnerLogin($username, $deviceFp, $pdo);
                        header('Location: index.php');
                        exit;
                    } else {
                        $error = $otpCheck['message'];
                    }
                } else {
                    // Valid credentials! Dispatch 2FA OTP to registered email
                    $destEmail = getRegisteredOwnerEmail($pdo);
                    $otpResult = generateAndSendOtp($destEmail, 'login', $pdo);
                    if ($otpResult['success']) {
                        $_SESSION['pending_2fa'] = [
                            'username' => $username,
                            'email' => $destEmail,
                            'device_fingerprint' => $deviceFp,
                            'created_at' => time()
                        ];
                        logLoginAttempt($username, 'otp_dispatched', '2FA code sent to ' . maskEmailAddress($destEmail), $deviceFp, $pdo);
                        header('Location: login.php?step=2fa');
                        exit;
                    } else {
                        $error = $otpResult['message'];
                    }
                }
            }
        }
    }

    // 2. STEP 2: VERIFY 2FA OTP
    if ($postAction === 'verify_2fa') {
        if (!isset($_SESSION['pending_2fa'])) {
            header('Location: login.php');
            exit;
        }

        $pending = $_SESSION['pending_2fa'];
        $inputOtp = trim($_POST['otp'] ?? '');

        if (empty($inputOtp)) {
            $error = 'Please enter the 6-digit verification code.';
            $step = '2fa';
        } else {
            $verifyResult = verifyOtp($pending['email'], $inputOtp, 'login', $pdo);
            if ($verifyResult['success']) {
                completeOwnerLogin($pending['username'], $pending['device_fingerprint'], $pdo);
                header('Location: index.php');
                exit;
            } else {
                $error = $verifyResult['message'];
                logLoginAttempt($pending['username'], 'failed', 'Invalid 2FA OTP: ' . $verifyResult['message'], $pending['device_fingerprint'], $pdo);
                $step = '2fa';
            }
        }
    }

    // 3. RESEND 2FA OTP
    if ($postAction === 'resend_2fa') {
        if (!isset($_SESSION['pending_2fa'])) {
            header('Location: login.php');
            exit;
        }
        $pending = $_SESSION['pending_2fa'];
        $otpResult = generateAndSendOtp($pending['email'], 'login', $pdo);
        if ($otpResult['success']) {
            $success = $otpResult['message'];
        } else {
            $error = $otpResult['message'];
        }
        $step = '2fa';
    }

    // 4. CANCEL 2FA AND RETURN
    if ($postAction === 'cancel_2fa') {
        unset($_SESSION['pending_2fa']);
        header('Location: login.php');
        exit;
    }

    // 5. FORGOT PASSWORD: SEND RESET CODE
    if ($postAction === 'request_reset_otp') {
        $username = trim(strip_tags($_POST['username'] ?? ''));
        $adminUser = getSetting('admin_username', DEFAULT_ADMIN_USER, $pdo);

        if (empty($username) || $username !== $adminUser) {
            usleep(200000);
            $success = 'If an account matches that username, a password reset code has been dispatched to your registered email.';
            $action = 'reset_password';
        } else {
            $destEmail = getRegisteredOwnerEmail($pdo);
            $res = generateAndSendOtp($destEmail, 'forgot_password', $pdo);
            if ($res['success']) {
                $_SESSION['pending_reset'] = [
                    'username' => $username,
                    'email' => $destEmail,
                    'created_at' => time()
                ];
                header('Location: login.php?action=reset_password');
                exit;
            } else {
                $error = $res['message'];
                $action = 'forgot_password';
            }
        }
    }

    // 6. FORGOT PASSWORD: VERIFY OTP AND UPDATE PASSWORD
    if ($postAction === 'submit_new_password') {
        $destEmail = $_SESSION['pending_reset']['email'] ?? getRegisteredOwnerEmail($pdo);
        $inputOtp = trim($_POST['otp'] ?? '');
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (empty($inputOtp)) {
            $error = 'Please enter the 6-digit verification code.';
            $action = 'reset_password';
        } elseif (strlen($newPass) < 6) {
            $error = 'New password must be at least 6 characters long.';
            $action = 'reset_password';
        } elseif ($newPass !== $confirmPass) {
            $error = 'New password and confirmation do not match.';
            $action = 'reset_password';
        } else {
            $verifyResult = verifyOtp($destEmail, $inputOtp, 'forgot_password', $pdo);
            if ($verifyResult['success']) {
                $newHash = password_hash($newPass, PASSWORD_DEFAULT);
                updateSetting('admin_password', $newHash, $pdo);
                logLoginAttempt(getSetting('admin_username', DEFAULT_ADMIN_USER, $pdo), 'password_reset', 'Password successfully reset via OTP', $deviceFp, $pdo);
                unset($_SESSION['pending_reset']);
                header('Location: login.php?reset=success');
                exit;
            } else {
                $error = $verifyResult['message'];
                $action = 'reset_password';
            }
        }
    }
}

$centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
$registeredEmail = getRegisteredOwnerEmail($pdo);
$maskedEmail = maskEmailAddress($registeredEmail);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Security Login - <?= e($centerName) ?></title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" href="../favicon.svg">
    <script>
        if (localStorage.getItem('owner_theme') === 'light') {
            document.documentElement.classList.remove('dark');
        } else {
            document.documentElement.classList.add('dark');
        }
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class'
        };
    </script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-100 dark:bg-slate-900 flex items-center justify-center p-4 antialiased transition-colors duration-150">

    <!-- Theme Switcher Top-Right -->
    <div class="fixed top-4 right-4 z-10">
        <button type="button" onclick="toggleLoginTheme()" title="Switch Theme (Light / Dark)"
                class="p-2.5 rounded-2xl bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-600 dark:text-slate-300 shadow-md transition hover:scale-105">
            <i data-lucide="sun" class="w-4 h-4 hidden dark:inline-block text-amber-400"></i>
            <i data-lucide="moon" class="w-4 h-4 inline-block dark:hidden text-slate-600"></i>
        </button>
    </div>

    <div class="max-w-md w-full bg-white dark:bg-slate-800 rounded-3xl p-6 sm:p-8 border border-slate-200 dark:border-slate-700 shadow-xl transition-colors duration-150">
        
        <!-- Header -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-sky-500 text-white flex items-center justify-center mx-auto mb-3 shadow-lg shadow-blue-500/30 font-black text-2xl tracking-tighter border border-blue-400/40 select-none">
                DS
            </div>
            
            <?php if ($step === '2fa'): ?>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Two-Factor Security</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Enter the 6-digit verification code</p>
            <?php elseif ($action === 'forgot_password'): ?>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Reset Password</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Verify ownership via registered email</p>
            <?php elseif ($action === 'reset_password'): ?>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Set New Password</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Enter code and create a new password</p>
            <?php else: ?>
                <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Owner Portal Login</h1>
                <p class="text-slate-500 dark:text-slate-400 text-sm mt-1"><?= e($centerName) ?></p>
            <?php endif; ?>
        </div>

        <!-- System Alerts -->
        <?php if (isset($_GET['reason']) && $_GET['reason'] === 'midnight'): ?>
            <div class="mb-5 p-4 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-800 dark:text-blue-300 text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="clock" class="w-5 h-5 shrink-0 text-blue-600 dark:text-blue-400"></i>
                <span>Your daily session concluded at midnight. Please sign in to begin today's session.</span>
            </div>
        <?php elseif (isset($_GET['reason']) && $_GET['reason'] === 'revoked'): ?>
            <div class="mb-5 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="shield-x" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                <span>This session was remotely signed out via session management. Please sign in again.</span>
            </div>
        <?php elseif (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
            <div class="mb-5 p-4 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 text-amber-800 dark:text-amber-300 text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="clock" class="w-5 h-5 shrink-0 text-amber-600 dark:text-amber-400"></i>
                <span>Session timed out. Please sign in to continue.</span>
            </div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="mb-5 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/60 dark:border-emerald-800/80 dark:text-emerald-300 text-sm flex items-center gap-2">
                <i data-lucide="check-circle" class="w-5 h-5 shrink-0 text-emerald-500"></i>
                <span><?= e($success) ?></span>
            </div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="mb-5 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/60 dark:border-red-800/80 dark:text-red-300 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-red-500"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <!-- ======================================================== -->
        <!-- VIEW 1: STEP 2 - 2FA OTP VERIFICATION                   -->
        <!-- ======================================================== -->
        <?php if ($step === '2fa'): ?>
            <form method="POST" class="space-y-5 double-click-guard">
                <input type="hidden" name="form_action" value="verify_2fa">
                <input type="hidden" name="device_fingerprint" class="device-fp-field">

                <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-center space-y-1">
                    <span class="text-xs text-blue-700 dark:text-blue-300">Security code sent to your registered address:</span>
                    <div class="font-mono text-sm font-bold text-blue-950 dark:text-white"><?= e($maskedEmail) ?></div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2 text-center">
                        6-Digit Verification Code
                    </label>
                    <input type="text" name="otp" required autofocus maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="------"
                           class="w-full py-3.5 px-4 text-center text-2xl font-mono font-black tracking-[0.5em] bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500 transition">
                </div>

                <button type="submit" class="btn-submit w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-600/30 transition flex items-center justify-center gap-2">
                    <i data-lucide="shield-check" class="w-4 h-4"></i>
                    <span>Verify & Sign In</span>
                </button>
            </form>

            <div class="mt-4 flex items-center justify-between text-xs pt-4 border-t border-slate-200 dark:border-slate-700/60">
                <form method="POST" class="inline">
                    <input type="hidden" name="form_action" value="resend_2fa">
                    <button type="submit" class="text-blue-600 dark:text-blue-400 hover:underline font-semibold flex items-center gap-1">
                        <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
                        <span>Resend Code</span>
                    </button>
                </form>

                <form method="POST" class="inline">
                    <input type="hidden" name="form_action" value="cancel_2fa">
                    <button type="submit" class="text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition">
                        ← Back to Sign In
                    </button>
                </form>
            </div>

        <!-- ======================================================== -->
        <!-- VIEW 2: FORGOT PASSWORD - REQUEST RESET CODE            -->
        <!-- ======================================================== -->
        <?php elseif ($action === 'forgot_password'): ?>
            <form method="POST" class="space-y-5 double-click-guard">
                <input type="hidden" name="form_action" value="request_reset_otp">
                <input type="hidden" name="device_fingerprint" class="device-fp-field">

                <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/60 text-xs text-amber-800 dark:text-amber-300">
                    Enter your owner username below. A password reset code will be dispatched to your account's registered email address (<strong><?= e($maskedEmail) ?></strong>).
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Owner Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                        <input type="text" name="username" required autofocus placeholder="Enter your username"
                               class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 transition">
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full py-3.5 px-4 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-amber-600/30 transition flex items-center justify-center gap-2">
                    <i data-lucide="send" class="w-4 h-4"></i>
                    <span>Send Password Reset Code</span>
                </button>

                <div class="text-center pt-2">
                    <a href="login.php" class="text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition">
                        ← Back to Sign In
                    </a>
                </div>
            </form>

        <!-- ======================================================== -->
        <!-- VIEW 3: FORGOT PASSWORD - VERIFY OTP & ENTER NEW PASS   -->
        <!-- ======================================================== -->
        <?php elseif ($action === 'reset_password'): ?>
            <form method="POST" class="space-y-4 double-click-guard">
                <input type="hidden" name="form_action" value="submit_new_password">
                <input type="hidden" name="device_fingerprint" class="device-fp-field">

                <div class="p-3.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs text-blue-800 dark:text-blue-300 text-center">
                    Enter the code sent to <strong><?= e($maskedEmail) ?></strong> and create your new password:
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">6-Digit Reset Code</label>
                    <input type="text" name="otp" required autofocus maxlength="6" pattern="[0-9]{6}" inputmode="numeric" placeholder="------"
                           class="w-full py-2.5 px-4 text-center text-xl font-mono font-bold tracking-[0.4em] bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">New Password</label>
                    <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters"
                           class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">Confirm New Password</label>
                    <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat new password"
                           class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <button type="submit" class="btn-submit w-full py-3.5 px-4 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-emerald-600/30 transition flex items-center justify-center gap-2">
                    <i data-lucide="check" class="w-4 h-4"></i>
                    <span>Update Password & Log In</span>
                </button>

                <div class="text-center pt-2">
                    <a href="login.php" class="text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition">
                        ← Cancel & Return to Sign In
                    </a>
                </div>
            </form>

        <!-- ======================================================== -->
        <!-- VIEW 4: DEFAULT - USERNAME & PASSWORD SIGN IN           -->
        <!-- ======================================================== -->
        <?php else: ?>
            <form method="POST" class="space-y-5 double-click-guard">
                <input type="hidden" name="form_action" value="login_credentials">
                <input type="hidden" name="device_fingerprint" class="device-fp-field">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Username</label>
                    <div class="relative">
                        <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                        <input type="text" name="username" required autofocus placeholder="Enter your username"
                               class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                    </div>
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider">Password</label>
                        <a href="login.php?action=forgot_password" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                        <input type="password" name="password" required placeholder="Enter your password"
                               class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-600/30 transition flex items-center justify-center gap-2">
                    <i data-lucide="log-in" class="w-4 h-4"></i>
                    <span>Sign In to Owner Portal</span>
                </button>
            </form>
        <?php endif; ?>

        <!-- Security & Midnight session hint -->
        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700/60 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl space-y-1">
            <div class="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-300">
                <i data-lucide="shield-check" class="w-4 h-4 text-blue-500"></i>
                <span>Protected by 2-Factor Authentication</span>
            </div>
            <p class="leading-relaxed">Access is restricted to authorized CSC operators. Daytime sessions remain active for uninterrupted work; sessions automatically conclude at midnight for security.</p>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function toggleLoginTheme() {
            const isDark = document.documentElement.classList.contains('dark');
            if (isDark) {
                document.documentElement.classList.remove('dark');
                localStorage.setItem('owner_theme', 'light');
            } else {
                document.documentElement.classList.add('dark');
                localStorage.setItem('owner_theme', 'dark');
            }
            lucide.createIcons();
        }

        // Generate client-side device fingerprint
        function generateDeviceFingerprint() {
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                ctx.textBaseline = 'top';
                ctx.font = '14px Arial';
                ctx.fillText('CSC-DIGITAL-SOLUTIONS', 2, 2);
                const canvasData = canvas.toDataURL().slice(-40);

                const raw = [
                    navigator.userAgent,
                    navigator.language,
                    screen.width + 'x' + screen.height,
                    screen.colorDepth,
                    new Date().getTimezoneOffset(),
                    canvasData
                ].join('###');

                let hash = 0;
                for (let i = 0; i < raw.length; i++) {
                    hash = ((hash << 5) - hash) + raw.charCodeAt(i);
                    hash |= 0;
                }
                return Math.abs(hash).toString(16) + '_' + screen.width + 'x' + screen.height;
            } catch (e) {
                return 'fp_' + screen.width + 'x' + screen.height;
            }
        }

        // Set device fingerprint to all hidden fields
        const fp = generateDeviceFingerprint();
        document.querySelectorAll('.device-fp-field').forEach(input => {
            input.value = fp;
        });

        // Accidental double-click / double-touch prevention on all forms & submit buttons
        document.querySelectorAll('form.double-click-guard').forEach(form => {
            form.addEventListener('submit', function(e) {
                const btn = form.querySelector('button[type="submit"]');
                if (btn) {
                    if (btn.dataset.submitting === 'true') {
                        e.preventDefault();
                        return false;
                    }
                    btn.dataset.submitting = 'true';
                    btn.classList.add('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
                    const origHtml = btn.innerHTML;
                    btn.innerHTML = '<span class="inline-flex items-center gap-2"><svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Processing...</span>';
                    setTimeout(() => { btn.disabled = true; }, 10);

                    // Re-enable after 3 seconds in case of client-side validation preventDefault
                    setTimeout(() => {
                        btn.dataset.submitting = 'false';
                        btn.disabled = false;
                        btn.classList.remove('opacity-60', 'cursor-not-allowed', 'pointer-events-none');
                        btn.innerHTML = origHtml;
                        lucide.createIcons();
                    }, 3000);
                }
            });
        });
    </script>
</body>
</html>
