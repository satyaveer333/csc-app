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
if (isset($_GET['timeout'])) {
    $error = 'Your session has expired due to 30 minutes of inactivity. Please log in again.';
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim(strip_tags($_POST['username'] ?? ''));
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
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-2xl bg-gradient-to-br from-blue-600 to-sky-500 text-white flex items-center justify-center mx-auto mb-3 shadow-lg shadow-blue-500/30 font-black text-2xl tracking-tighter border border-blue-400/40 select-none">
                DS
            </div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white tracking-tight">Owner Portal Login</h1>
            <p class="text-slate-500 dark:text-slate-400 text-sm mt-1"><?= e($centerName) ?></p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/60 dark:border-red-800/80 dark:text-red-300 text-sm flex items-center gap-2">
                <i data-lucide="alert-circle" class="w-5 h-5 shrink-0 text-red-500"></i>
                <span><?= e($error) ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Username</label>
                <div class="relative">
                    <i data-lucide="user" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                    <input type="text" name="username" required autofocus placeholder="Enter your username"
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2">Password</label>
                <div class="relative">
                    <i data-lucide="lock" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3.5"></i>
                    <input type="password" name="password" required placeholder="Enter your password"
                           class="w-full pl-10 pr-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:outline-none focus:ring-2 focus:ring-blue-500 transition">
                </div>
            </div>

            <button type="submit" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm shadow-lg shadow-blue-600/30 transition flex items-center justify-center gap-2">
                <i data-lucide="log-in" class="w-4 h-4"></i>
                <span>Sign In to Owner Portal</span>
            </button>
        </form>

        <!-- Initial setup hints -->
        <div class="mt-8 pt-6 border-t border-slate-200 dark:border-slate-700/60 text-xs text-slate-500 dark:text-slate-400 bg-slate-50 dark:bg-slate-900/50 p-4 rounded-xl">
            <div class="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-300 mb-1">
                <i data-lucide="shield-alert" class="w-4 h-4 text-blue-500"></i>
                <span>Secure Center Administration</span>
            </div>
            <p>Access is restricted to authorized CSC operators. All login activities are monitored and sessions expire after 30 minutes of inactivity.</p>
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
    </script>
</body>
</html>
