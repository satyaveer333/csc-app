<?php
/**
 * CSC Owner Portal - Settings, Center Profile & Email Setup
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. UPDATE CENTER PROFILE
    if ($action === 'update_profile') {
        updateSetting('center_name', trim($_POST['center_name'] ?? ''), $pdo);
        updateSetting('owner_name', trim($_POST['owner_name'] ?? ''), $pdo);
        updateSetting('owner_phone', trim($_POST['owner_phone'] ?? ''), $pdo);
        updateSetting('center_address', trim($_POST['center_address'] ?? ''), $pdo);
        updateSetting('vle_id', trim($_POST['vle_id'] ?? ''), $pdo);
        updateSetting('opening_hours', trim($_POST['opening_hours'] ?? ''), $pdo);
        setFlash('success', 'Center profile updated successfully.');
        header('Location: settings.php');
        exit;
    }

    // 2. UPDATE EMAIL & NOTIFICATION SETTINGS
    if ($action === 'update_email') {
        updateSetting('owner_email', trim($_POST['owner_email'] ?? ''), $pdo);
        updateSetting('mail_method', trim($_POST['mail_method'] ?? 'php_mail'), $pdo);
        updateSetting('smtp_host', trim($_POST['smtp_host'] ?? ''), $pdo);
        updateSetting('smtp_port', trim($_POST['smtp_port'] ?? '587'), $pdo);
        updateSetting('smtp_user', trim($_POST['smtp_user'] ?? ''), $pdo);
        if (!empty($_POST['smtp_pass'])) {
            updateSetting('smtp_pass', trim($_POST['smtp_pass']), $pdo);
        }
        updateSetting('smtp_secure', trim($_POST['smtp_secure'] ?? 'tls'), $pdo);
        setFlash('success', 'Email configuration updated.');
        header('Location: settings.php');
        exit;
    }

    // 3. SEND TEST EMAIL
    if ($action === 'test_email') {
        $testOrder = [
            'id' => 0,
            'token_id' => 'TEST-' . rand(1000, 9999),
            'customer_name' => 'Serverbyt System Check',
            'customer_phone' => '+91 9999999999',
            'customer_email' => 'test@example.com',
            'print_type' => 'B&W',
            'sides' => 'Single Sided',
            'paper_size' => 'A4',
            'copies' => 1,
            'notes' => 'This is an automated test email confirming that your Serverbyt Blaze Plan mail dispatcher is functioning properly.'
        ];
        $sent = sendPrintOrderEmail($testOrder, [], $pdo);
        if ($sent) {
            setFlash('success', 'Test email dispatched successfully! Please check your inbox.');
        } else {
            setFlash('danger', 'Could not send test email. Please check your PHP mail / SMTP server configuration.');
        }
        header('Location: settings.php');
        exit;
    }

    // 4. UPDATE PRINT PRICING
    if ($action === 'update_pricing') {
        updateSetting('rate_bw_single', number_format((float)$_POST['rate_bw_single'], 2, '.', ''), $pdo);
        updateSetting('rate_bw_double', number_format((float)$_POST['rate_bw_double'], 2, '.', ''), $pdo);
        updateSetting('rate_color_single', number_format((float)$_POST['rate_color_single'], 2, '.', ''), $pdo);
        updateSetting('rate_color_double', number_format((float)$_POST['rate_color_double'], 2, '.', ''), $pdo);
        updateSetting('rate_lamination', number_format((float)$_POST['rate_lamination'], 2, '.', ''), $pdo);
        setFlash('success', 'Print rates updated.');
        header('Location: settings.php');
        exit;
    }

    // 5. CHANGE OWNER PASSWORD
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $storedHash = getSetting('admin_password', '', $pdo);
        if (!password_verify($currentPass, $storedHash) && $currentPass !== DEFAULT_ADMIN_PASS) {
            setFlash('danger', 'Current password is incorrect.');
        } elseif (strlen($newPass) < 6) {
            setFlash('danger', 'New password must be at least 6 characters long.');
        } elseif ($newPass !== $confirmPass) {
            setFlash('danger', 'New password and confirmation do not match.');
        } else {
            $newHash = password_hash($newPass, PASSWORD_DEFAULT);
            updateSetting('admin_password', $newHash, $pdo);
            setFlash('success', 'Owner password updated successfully!');
        }
        header('Location: settings.php');
        exit;
    }
}

$settings = getSettings($pdo);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Center Settings - <?= e($settings['center_name'] ?? 'Digital Solutions') ?></title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" href="../favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 flex flex-col antialiased">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-8">
        
        <div class="pb-4 border-b border-slate-800">
            <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Center Settings & Configuration</h1>
            <p class="text-slate-400 text-sm mt-1">Configure your center branding, email alerts, print pricing rates, and credentials.</p>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-950/70 border border-emerald-800 text-emerald-300' : 'bg-red-950/70 border border-red-800 text-red-300' ?> text-sm flex items-center gap-2">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- 1. Center Profile -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 shadow-md">
            <h2 class="text-lg font-bold text-white flex items-center gap-2 mb-4">
                <i data-lucide="building" class="w-5 h-5 text-blue-400"></i>
                <span>CSC Center Information</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_profile">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">CSC Center Name</label>
                        <input type="text" name="center_name" required value="<?= e($settings['center_name'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Operator / VLE Name</label>
                        <input type="text" name="owner_name" required value="<?= e($settings['owner_name'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">WhatsApp / Phone Number</label>
                        <input type="text" name="owner_phone" required value="<?= e($settings['owner_phone'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">VLE ID (CSC ID)</label>
                        <input type="text" name="vle_id" value="<?= e($settings['vle_id'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Opening Hours</label>
                        <input type="text" name="opening_hours" value="<?= e($settings['opening_hours'] ?? '9:00 AM - 8:00 PM') ?>"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Center Address / Location</label>
                    <input type="text" name="center_address" value="<?= e($settings['center_address'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm transition">
                        Save Center Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Email & Print Order Notifications -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 shadow-md">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="mail" class="w-5 h-5 text-emerald-400"></i>
                    <span>Email & Print Notification Settings</span>
                </h2>
                <form method="POST" class="inline-block">
                    <input type="hidden" name="action" value="test_email">
                    <button type="submit" class="px-3.5 py-1.5 bg-slate-700 hover:bg-slate-600 text-xs font-semibold rounded-lg text-emerald-300 border border-emerald-800 flex items-center gap-1.5 transition">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        <span>Send Test Email</span>
                    </button>
                </form>
            </div>

            <p class="text-xs text-slate-400 mb-6">
                When customers submit documents online, an email alert with the attached documents and print details is dispatched to your configured email.
            </p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_email">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                            Receiving Email Address <span class="text-red-400">*</span>
                        </label>
                        <input type="email" name="owner_email" required value="<?= e($settings['owner_email'] ?? '') ?>" placeholder="your.csc.center@gmail.com"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Email Delivery Method</label>
                        <select name="mail_method" id="mailMethodSelect" onchange="toggleSmtpFields()" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="php_mail" <?= ($settings['mail_method'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' ?>>Native PHP Mail (Recommended for Serverbyt Blaze)</option>
                            <option value="smtp" <?= ($settings['mail_method'] ?? '') === 'smtp' ? 'selected' : '' ?>>Custom SMTP Server</option>
                        </select>
                    </div>
                </div>

                <!-- SMTP Optional Details -->
                <div id="smtpFields" class="space-y-4 <?= ($settings['mail_method'] ?? 'php_mail') === 'smtp' ? '' : 'hidden' ?> pt-2 border-t border-slate-700/60">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="e.g. mail.yourdomain.com"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Encryption</label>
                            <select name="smtp_secure" class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                                <option value="tls" <?= ($settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                                <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">SMTP Username</label>
                            <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>" placeholder="username@domain.com"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">SMTP Password</label>
                            <input type="password" name="smtp_pass" placeholder="Leave empty to keep unchanged"
                                   class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm">
                        </div>
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm transition">
                        Save Email Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- 3. Customer Print Pricing Rates -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 shadow-md">
            <h2 class="text-lg font-bold text-white flex items-center gap-2 mb-4">
                <i data-lucide="tag" class="w-5 h-5 text-amber-400"></i>
                <span>Printing Service Rates (per page)</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_pricing">

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">B&W Single (₹)</label>
                        <input type="number" step="0.50" name="rate_bw_single" required value="<?= e($settings['rate_bw_single'] ?? '3.00') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">B&W Both (₹)</label>
                        <input type="number" step="0.50" name="rate_bw_double" required value="<?= e($settings['rate_bw_double'] ?? '5.00') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Color Single (₹)</label>
                        <input type="number" step="0.50" name="rate_color_single" required value="<?= e($settings['rate_color_single'] ?? '10.00') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Color Both (₹)</label>
                        <input type="number" step="0.50" name="rate_color_double" required value="<?= e($settings['rate_color_double'] ?? '18.00') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Lamination (₹)</label>
                        <input type="number" step="0.50" name="rate_lamination" required value="<?= e($settings['rate_lamination'] ?? '20.00') ?>"
                               class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm transition">
                        Save Pricing Rates
                    </button>
                </div>
            </form>
        </div>

        <!-- 4. Security & Change Password -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 p-6 shadow-md">
            <h2 class="text-lg font-bold text-white flex items-center gap-2 mb-4">
                <i data-lucide="lock" class="w-5 h-5 text-red-400"></i>
                <span>Change Owner Password</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="change_password">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required placeholder="Enter current password"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">New Password</label>
                        <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat new password"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-sm transition">
                        Update Password
                    </button>
                </div>
            </form>
        </div>

    </main>

    <script>
        lucide.createIcons();

        function toggleSmtpFields() {
            const val = document.getElementById('mailMethodSelect').value;
            const fields = document.getElementById('smtpFields');
            fields.classList.toggle('hidden', val !== 'smtp');
        }
    </script>
</body>
</html>
