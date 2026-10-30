<?php
/**
 * CSC Owner Portal - Login
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

ensureDirectories();
$pdo = getDbConnection();

// Redirect if already logged in
if (isOwnerLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (ownerLogin($username, $password, $pdo)) {
            header('Location: index.php');
            exit;
        } else {
            $error = 'Invalid username or password. Please try again.';
        }
    }
}

$centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Login - <?= e($centerName) ?></title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" href="../favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-900 flex items-center justify-center p-4 antialiased">

    <div class="max-w-md w-full bg-slate-800 rounded-3xl p-8 border border-slate-700 shadow-2xl">
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-sky-500 text-white flex items-center justify-center mx-auto mb-3 shadow-lg shadow-blue-500/30 font-black text-2xl tracking-tighter border border-blue-400/40 select-none">
                DS
            </div>
            <h1 class="text-2xl font-bold text-white tracking-tight">Owner Portal Login</h1>
            <p class="text-slate-400 text-sm mt-1"><?= e($centerName) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-950/60 border border-red-800/80 text-red-300 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-red-400"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                    <input type="text" name="username" required autofocus placeholder="e.g. admin"
                           class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                    <input type="password" name="password" required placeholder="Enter owner password"
                           class="w-full pl-10 pr-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-600/30 transition flex items-center justify-center gap-2">
                <i data-lucide="log-in" class="w-4 h-4"></i>
                <span>Sign In to Owner Portal</span>
            </button>
        </form>

        <!-- Initial setup hints -->
        <div class="mt-8 pt-6 border-t border-slate-700/60 text-xs text-slate-400 bg-slate-900/50 p-4 rounded-xl">
            <div class="flex items-center gap-1.5 font-semibold text-slate-300 mb-1">
                <i data-lucide="key" class="w-3.5 h-3.5 text-amber-400"></i>
                <span>Default Credentials:</span>
            </div>
            <p>Username: <code class="text-amber-300 bg-slate-800 px-1 py-0.5 rounded">admin</code> | Password: <code class="text-amber-300 bg-slate-800 px-1 py-0.5 rounded">admin123</code></p>
            <p class="text-slate-500 mt-1">You can change your password anytime in Owner Settings.</p>
        </div>
    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
