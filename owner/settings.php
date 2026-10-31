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
    
    // Verify CSRF Token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security validation failed (Invalid CSRF Token). Please try again.');
        header('Location: settings.php');
        exit;
    }
    
    // 1. UPDATE CENTER PROFILE
    if ($action === 'update_profile') {
        $centerName = trim(strip_tags($_POST['center_name'] ?? ''));
        $ownerName = trim(strip_tags($_POST['owner_name'] ?? ''));
        $rawPhone = trim($_POST['owner_phone'] ?? '');
        $centerAddress = trim(strip_tags($_POST['center_address'] ?? ''));
        $vleId = trim(strip_tags($_POST['vle_id'] ?? ''));

        // Validation Checks
        if (mb_strlen($centerName) < 2 || mb_strlen($centerName) > 200) {
            setFlash('danger', 'Center name must be between 2 and 200 characters.');
            header('Location: settings.php');
            exit;
        }
        if (mb_strlen($ownerName) < 2 || mb_strlen($ownerName) > 150) {
            setFlash('danger', 'Owner / VLE name must be between 2 and 150 characters.');
            header('Location: settings.php');
            exit;
        }

        // Strict 10-digit phone number check
        $cleanPhone = preg_replace('/[^0-9]/', '', $rawPhone);
        if (strlen($cleanPhone) === 12 && substr($cleanPhone, 0, 2) === '91') {
            $cleanPhone = substr($cleanPhone, 2);
        } elseif (strlen($cleanPhone) === 11 && substr($cleanPhone, 0, 1) === '0') {
            $cleanPhone = substr($cleanPhone, 1);
        }
        if (strlen($cleanPhone) !== 10) {
            setFlash('danger', 'Phone number must be exactly 10 digits (digits only, e.g. 9876543210).');
            header('Location: settings.php');
            exit;
        }

        // Opening Hours from Weekdays Checklist + Time Selectors
        $selectedDays = $_POST['opening_days'] ?? [];
        if (!is_array($selectedDays)) {
            $selectedDays = [];
        }
        $validDayKeys = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        $filteredDays = array_values(array_intersect($validDayKeys, $selectedDays));
        $legacyHours = trim(strip_tags($_POST['opening_hours'] ?? ''));

        if (empty($filteredDays)) {
            if (!empty($legacyHours)) {
                $filteredDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
            } else {
                setFlash('danger', 'Please select at least one operating day for opening hours.');
                header('Location: settings.php');
                exit;
            }
        }

        $timeStart = trim($_POST['opening_time_start'] ?? '09:00');
        $timeEnd = trim($_POST['opening_time_end'] ?? '20:00');
        if (!preg_match('/^\d{1,2}:\d{2}$/', $timeStart)) $timeStart = '09:00';
        if (!preg_match('/^\d{1,2}:\d{2}$/', $timeEnd)) $timeEnd = '20:00';

        $startFormatted = date('g:i A', strtotime("2000-01-01 $timeStart"));
        $endFormatted = date('g:i A', strtotime("2000-01-01 $timeEnd"));

        if (!empty($legacyHours) && empty($_POST['opening_days'])) {
            $formattedOpeningHours = $legacyHours;
        } else {
            if (count($filteredDays) === 7) {
                $daysSummary = '(Everyday)';
            } elseif ($filteredDays === ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']) {
                $daysSummary = '(Mon - Sat)';
            } elseif ($filteredDays === ['Mon', 'Tue', 'Wed', 'Thu', 'Fri']) {
                $daysSummary = '(Mon - Fri)';
            } else {
                $daysSummary = '(' . implode(', ', $filteredDays) . ')';
            }
            $formattedOpeningHours = "{$startFormatted} - {$endFormatted} {$daysSummary}";
        }

        updateSetting('center_name', $centerName, $pdo);
        updateSetting('owner_name', $ownerName, $pdo);
        updateSetting('owner_phone', $cleanPhone, $pdo);
        updateSetting('center_address', $centerAddress, $pdo);
        updateSetting('vle_id', $vleId, $pdo);
        updateSetting('opening_hours', $formattedOpeningHours, $pdo);
        updateSetting('opening_days', json_encode($filteredDays), $pdo);
        updateSetting('opening_time_start', $timeStart, $pdo);
        updateSetting('opening_time_end', $timeEnd, $pdo);

        setFlash('success', 'Center profile and operating hours updated successfully.');
        header('Location: settings.php');
        exit;
    }

    // 2. UPDATE EMAIL & NOTIFICATION SETTINGS (WITH DEVELOPER CONFIRMATION)
    if ($action === 'update_email') {
        $ownerEmail = trim($_POST['owner_email'] ?? '');
        if (!filter_var($ownerEmail, FILTER_VALIDATE_EMAIL)) {
            setFlash('danger', 'Please provide a valid receiving email address.');
            header('Location: settings.php');
            exit;
        }

        $currentMethod = getSetting('mail_method', 'php_mail', $pdo);
        $newMethod = trim($_POST['mail_method'] ?? 'php_mail');
        if (!in_array($newMethod, ['php_mail', 'smtp'])) {
            $newMethod = 'php_mail';
        }

        $isMethodChanging = ($newMethod !== $currentMethod);
        $isConfiguringSmtp = ($newMethod === 'smtp');
        $developerEmailInput = strtolower(trim($_POST['developer_email'] ?? ''));

        // Developer confirmation check required if changing delivery method or configuring SMTP
        // Verified via secure SHA-256 hash stored in DB / config (zero plain text storage)
        if ($isMethodChanging || $isConfiguringSmtp) {
            $inputHash = hash('sha256', $developerEmailInput);
            $storedHash = getSetting('developer_email_hash', DEFAULT_DEVELOPER_EMAIL_HASH, $pdo);
            if (!hash_equals($storedHash, $inputHash)) {
                setFlash('danger', 'Developer Verification Failed: The email delivery method or SMTP settings can only be altered after confirmation from the authorized developer.');
                header('Location: settings.php');
                exit;
            }
        }

        updateSetting('owner_email', $ownerEmail, $pdo);
        updateSetting('mail_method', $newMethod, $pdo);
        updateSetting('smtp_host', trim(strip_tags($_POST['smtp_host'] ?? '')), $pdo);
        $smtpPort = (int)($_POST['smtp_port'] ?? 587);
        if ($smtpPort <= 0 || $smtpPort > 65535) $smtpPort = 587;
        updateSetting('smtp_port', (string)$smtpPort, $pdo);
        updateSetting('smtp_user', trim(strip_tags($_POST['smtp_user'] ?? '')), $pdo);
        if (!empty($_POST['smtp_pass'])) {
            updateSetting('smtp_pass', encryptSensitiveData(trim($_POST['smtp_pass'])), $pdo);
        }
        $smtpSecure = in_array($_POST['smtp_secure'] ?? '', ['tls', 'ssl', 'none']) ? $_POST['smtp_secure'] : 'tls';
        updateSetting('smtp_secure', $smtpSecure, $pdo);

        setFlash('success', 'Email configuration updated successfully.');
        header('Location: settings.php');
        exit;
    }

    // 3. SEND TEST EMAIL
    if ($action === 'test_email') {
        $testOrder = [
            'id' => 0,
            'token_id' => 'TEST-' . rand(1000, 9999),
            'customer_name' => 'System Check',
            'customer_phone' => '9999999999',
            'customer_email' => 'test@example.com',
            'print_type' => 'B&W',
            'sides' => 'Single Sided',
            'paper_size' => 'A4',
            'copies' => 1,
            'notes' => 'This is an automated test email confirming that your CSC email dispatcher is functioning properly.'
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
        $bwSingle = max(0.0, round((float)($_POST['rate_bw_single'] ?? 3.0), 2));
        $bwDouble = max(0.0, round((float)($_POST['rate_bw_double'] ?? 5.0), 2));
        $colSingle = max(0.0, round((float)($_POST['rate_color_single'] ?? 10.0), 2));
        $colDouble = max(0.0, round((float)($_POST['rate_color_double'] ?? 18.0), 2));
        $lamination = max(0.0, round((float)($_POST['rate_lamination'] ?? 20.0), 2));

        updateSetting('rate_bw_single', number_format($bwSingle, 2, '.', ''), $pdo);
        updateSetting('rate_bw_double', number_format($bwDouble, 2, '.', ''), $pdo);
        updateSetting('rate_color_single', number_format($colSingle, 2, '.', ''), $pdo);
        updateSetting('rate_color_double', number_format($colDouble, 2, '.', ''), $pdo);
        updateSetting('rate_lamination', number_format($lamination, 2, '.', ''), $pdo);

        setFlash('success', 'Print pricing rates updated successfully.');
        header('Location: settings.php');
        exit;
    }

    // 5. CHANGE OWNER PASSWORD
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $storedHash = getSetting('admin_password', '', $pdo);
        $isValidCurrent = !empty($storedHash) ? password_verify($currentPass, $storedHash) : ($currentPass === DEFAULT_ADMIN_PASS);

        if (!$isValidCurrent) {
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

$weekdaysList = ['Mon' => 'Monday', 'Tue' => 'Tuesday', 'Wed' => 'Wednesday', 'Thu' => 'Thursday', 'Fri' => 'Friday', 'Sat' => 'Saturday', 'Sun' => 'Sunday'];

// Determine current selected weekdays
$currentDays = [];
if (!empty($settings['opening_days'])) {
    $decoded = json_decode($settings['opening_days'], true);
    if (is_array($decoded)) {
        $currentDays = $decoded;
    }
}

if (empty($currentDays)) {
    $rawHours = $settings['opening_hours'] ?? '';
    if (stripos($rawHours, 'Everyday') !== false || stripos($rawHours, 'All Days') !== false) {
        $currentDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    } elseif (stripos($rawHours, 'Mon-Fri') !== false) {
        $currentDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri'];
    } else {
        $currentDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
    }
}

$startTime = $settings['opening_time_start'] ?? '09:00';
$endTime = $settings['opening_time_end'] ?? '20:00';

// Clean 10-digit owner phone for display
$rawStoredPhone = preg_replace('/[^0-9]/', '', $settings['owner_phone'] ?? '');
$cleanOwnerPhoneDisplay = strlen($rawStoredPhone) >= 10 ? substr($rawStoredPhone, -10) : $rawStoredPhone;
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
<body class="min-h-screen bg-slate-100 text-slate-800 dark:bg-slate-900 dark:text-slate-100 flex flex-col antialiased transition-colors duration-150">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="flex-1 max-w-5xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">
        
        <div class="pb-3 border-b border-slate-200 dark:border-slate-800">
            <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Center Settings & Configuration</h1>
            <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Configure your center profile, schedule, verified email notifications, and service rates.</p>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?> text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- 1. Center Profile & Operating Schedule -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <i data-lucide="building" class="w-5 h-5 text-blue-500"></i>
                <span>CSC Center Information & Schedule</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="update_profile">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">CSC Center Name</label>
                        <input type="text" name="center_name" required value="<?= e($settings['center_name'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Operator / VLE Name</label>
                        <input type="text" name="owner_name" required value="<?= e($settings['owner_name'] ?? '') ?>"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Mobile Number <span class="text-blue-500 font-normal">(10 Digits Only)</span> <span class="text-red-500">*</span>
                        </label>
                        <input type="tel" name="owner_phone" required value="<?= e($cleanOwnerPhoneDisplay) ?>"
                               maxlength="10" pattern="[0-9]{10}" placeholder="e.g. 9876543210"
                               oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 10)"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-mono">
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 block">Used for customer direct calls and WhatsApp notifications.</span>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">VLE ID (CSC ID)</label>
                        <input type="text" name="vle_id" value="<?= e($settings['vle_id'] ?? '') ?>" placeholder="e.g. VLE-98745612"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <!-- Operating Hours: Checklist of Weekdays + Time Selector Menu -->
                <div class="bg-slate-50 dark:bg-slate-900/60 p-4 rounded-xl border border-slate-200 dark:border-slate-700/80 space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-slate-200 dark:border-slate-800 pb-2">
                        <div>
                            <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 uppercase tracking-wider">
                                Operating Schedule (Weekdays & Timings)
                            </label>
                            <span class="text-[11px] text-slate-500 dark:text-slate-400">Select open days and business hours for your center.</span>
                        </div>
                        <div class="flex items-center gap-1.5 text-xs">
                            <span class="text-slate-400 text-[11px] mr-1 hidden sm:inline">Presets:</span>
                            <button type="button" onclick="setWeekdayPreset(['Mon','Tue','Wed','Thu','Fri','Sat'])" class="px-2.5 py-1 rounded-md bg-white border border-slate-300 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition text-[11px]">Mon-Sat</button>
                            <button type="button" onclick="setWeekdayPreset(['Mon','Tue','Wed','Thu','Fri'])" class="px-2.5 py-1 rounded-md bg-white border border-slate-300 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition text-[11px]">Mon-Fri</button>
                            <button type="button" onclick="setWeekdayPreset(['Mon','Tue','Wed','Thu','Fri','Sat','Sun'])" class="px-2.5 py-1 rounded-md bg-white border border-slate-300 hover:bg-slate-100 dark:bg-slate-800 dark:border-slate-700 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-semibold transition text-[11px]">All 7 Days</button>
                        </div>
                    </div>

                    <!-- Weekdays Checklist -->
                    <div>
                        <span class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1.5">Checklist of Working Weekdays:</span>
                        <div class="grid grid-cols-2 sm:grid-cols-4 md:grid-cols-7 gap-2">
                            <?php foreach ($weekdaysList as $code => $dayName): ?>
                                <label class="flex items-center gap-2 p-2 rounded-lg border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 hover:border-blue-400 cursor-pointer transition select-none">
                                    <input type="checkbox" name="opening_days[]" value="<?= $code ?>" 
                                           <?= in_array($code, $currentDays) ? 'checked' : '' ?>
                                           onchange="updateHoursPreview()"
                                           class="w-4 h-4 rounded text-blue-600 focus:ring-blue-500 border-slate-300 dark:border-slate-600 bg-slate-100 dark:bg-slate-900 day-checkbox">
                                    <span class="text-xs font-semibold text-slate-800 dark:text-slate-200"><?= $code ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Time Selectors & Live Preview -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Opening Time (Start)</label>
                            <input type="time" name="opening_time_start" id="openingTimeStart" value="<?= e($startTime) ?>" required onchange="updateHoursPreview()"
                                   class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div>
                            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Closing Time (End)</label>
                            <input type="time" name="opening_time_end" id="openingTimeEnd" value="<?= e($endTime) ?>" required onchange="updateHoursPreview()"
                                   class="w-full px-3 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-xs font-medium focus:ring-2 focus:ring-blue-500">
                        </div>
                        <div class="sm:col-span-1 flex flex-col justify-end">
                            <div class="p-2.5 rounded-xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 text-xs">
                                <span class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400 block mb-0.5">Customer Display Preview</span>
                                <span id="hoursPreviewText" class="font-bold text-slate-800 dark:text-white text-xs truncate block"><?= e($settings['opening_hours'] ?? '9:00 AM - 8:00 PM (Mon - Sat)') ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Center Address / Location</label>
                    <input type="text" name="center_address" value="<?= e($settings['center_address'] ?? '') ?>"
                           class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold rounded-xl text-sm shadow-sm transition">
                        Save Center Profile
                    </button>
                </div>
            </form>
        </div>

        <!-- 2. Email & Print Order Notifications (With Developer Confirmation) -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="mail" class="w-5 h-5 text-emerald-500"></i>
                    <span>Email & Print Notification Settings</span>
                </h2>
                <form method="POST" class="inline-block">
                    <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                    <input type="hidden" name="action" value="test_email">
                    <button type="submit" class="px-3.5 py-1.5 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-xs font-semibold rounded-lg text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800 flex items-center gap-1.5 transition">
                        <i data-lucide="send" class="w-3.5 h-3.5"></i>
                        <span>Send Test Email</span>
                    </button>
                </form>
            </div>

            <p class="text-xs text-slate-500 dark:text-slate-400 mb-6">
                When customers submit documents online, an email alert with the attached documents and print details is dispatched to your configured email.
            </p>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="update_email">

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                            Receiving Email Address <span class="text-red-500">*</span>
                        </label>
                        <input type="email" name="owner_email" required value="<?= e($settings['owner_email'] ?? '') ?>" placeholder="your.csc.center@gmail.com"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Email Delivery Method</label>
                        <select name="mail_method" id="mailMethodSelect" onchange="toggleSmtpFields()" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <option value="php_mail" <?= ($settings['mail_method'] ?? 'php_mail') === 'php_mail' ? 'selected' : '' ?>>Native PHP Mail (Recommended for Serverbyt Blaze)</option>
                            <option value="smtp" <?= ($settings['mail_method'] ?? '') === 'smtp' ? 'selected' : '' ?>>Custom SMTP Server</option>
                        </select>
                    </div>
                </div>

                <!-- Developer Confirmation Security Gate -->
                <div class="p-4 rounded-xl border border-amber-300 dark:border-amber-800/80 bg-amber-50 dark:bg-amber-950/40 space-y-2">
                    <div class="flex items-center gap-2 text-xs font-bold text-amber-800 dark:text-amber-300">
                        <i data-lucide="shield-check" class="w-4 h-4 text-amber-600 dark:text-amber-400"></i>
                        <span>Developer Authorization Gate (Required to Modify Delivery Method)</span>
                    </div>
                    <p class="text-xs text-amber-700 dark:text-amber-400">
                        Changing the email delivery method or updating custom SMTP requires developer confirmation. Please enter the authorized developer email address:
                    </p>
                    <div class="max-w-md">
                        <input type="email" name="developer_email" id="developerEmailInput" placeholder="Enter authorized developer email to confirm"
                               class="w-full px-3.5 py-2 text-xs rounded-xl border border-amber-300 dark:border-amber-700 bg-white dark:bg-slate-900 text-slate-900 dark:text-white placeholder-slate-400 focus:ring-2 focus:ring-amber-500">
                    </div>
                </div>

                <!-- SMTP Optional Details -->
                <div id="smtpFields" class="space-y-4 <?= ($settings['mail_method'] ?? 'php_mail') === 'smtp' ? '' : 'hidden' ?> pt-2 border-t border-slate-200 dark:border-slate-700/60">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">SMTP Host</label>
                            <input type="text" name="smtp_host" value="<?= e($settings['smtp_host'] ?? '') ?>" placeholder="e.g. mail.yourdomain.com"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">SMTP Port</label>
                            <input type="number" name="smtp_port" value="<?= e($settings['smtp_port'] ?? '587') ?>"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Encryption</label>
                            <select name="smtp_secure" class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm">
                                <option value="tls" <?= ($settings['smtp_secure'] ?? '') === 'tls' ? 'selected' : '' ?>>TLS (Port 587)</option>
                                <option value="ssl" <?= ($settings['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (Port 465)</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">SMTP Username</label>
                            <input type="text" name="smtp_user" value="<?= e($settings['smtp_user'] ?? '') ?>" placeholder="username@domain.com"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">SMTP Password</label>
                            <input type="password" name="smtp_pass" placeholder="Leave empty to keep unchanged"
                                   class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm">
                        </div>
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-sm shadow-sm transition">
                        Save Email Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- 3. Customer Print Pricing Rates -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <i data-lucide="tag" class="w-5 h-5 text-amber-500"></i>
                <span>Printing Service Rates (per page)</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="update_pricing">

                <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">B&W Single (₹)</label>
                        <input type="number" step="0.50" min="0" name="rate_bw_single" required value="<?= e($settings['rate_bw_single'] ?? '3.00') ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">B&W Both (₹)</label>
                        <input type="number" step="0.50" min="0" name="rate_bw_double" required value="<?= e($settings['rate_bw_double'] ?? '5.00') ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Color Single (₹)</label>
                        <input type="number" step="0.50" min="0" name="rate_color_single" required value="<?= e($settings['rate_color_single'] ?? '10.00') ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Color Both (₹)</label>
                        <input type="number" step="0.50" min="0" name="rate_color_double" required value="<?= e($settings['rate_color_double'] ?? '18.00') ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Lamination (₹)</label>
                        <input type="number" step="0.50" min="0" name="rate_lamination" required value="<?= e($settings['rate_lamination'] ?? '20.00') ?>"
                               class="w-full px-3 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500 font-bold">
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-500 text-white font-bold rounded-xl text-sm shadow-sm transition">
                        Save Pricing Rates
                    </button>
                </div>
            </form>
        </div>

        <!-- 4. Security & Change Password -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2 mb-4">
                <i data-lucide="lock" class="w-5 h-5 text-red-500"></i>
                <span>Change Owner Password</span>
            </h2>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" value="change_password">

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Current Password</label>
                        <input type="password" name="current_password" required placeholder="Enter current password"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">New Password</label>
                        <input type="password" name="new_password" required minlength="6" placeholder="At least 6 characters"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Confirm New Password</label>
                        <input type="password" name="confirm_password" required minlength="6" placeholder="Repeat new password"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="pt-2 text-right">
                    <button type="submit" class="px-6 py-2.5 bg-red-600 hover:bg-red-500 text-white font-bold rounded-xl text-sm shadow-sm transition">
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

        function setWeekdayPreset(days) {
            document.querySelectorAll('.day-checkbox').forEach(cb => {
                cb.checked = days.includes(cb.value);
            });
            updateHoursPreview();
        }

        function formatTime12(timeStr) {
            if (!timeStr) return '';
            const [hours, minutes] = timeStr.split(':');
            let h = parseInt(hours, 10);
            const m = minutes || '00';
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12;
            h = h ? h : 12;
            return `${h}:${m} ${ampm}`;
        }

        function updateHoursPreview() {
            const checkedBoxes = Array.from(document.querySelectorAll('.day-checkbox:checked')).map(cb => cb.value);
            const validOrder = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const orderedDays = validOrder.filter(d => checkedBoxes.includes(d));
            
            let daysSummary = '';
            if (orderedDays.length === 7) {
                daysSummary = '(Everyday)';
            } else if (orderedDays.join(',') === 'Mon,Tue,Wed,Thu,Fri,Sat') {
                daysSummary = '(Mon - Sat)';
            } else if (orderedDays.join(',') === 'Mon,Tue,Wed,Thu,Fri') {
                daysSummary = '(Mon - Fri)';
            } else if (orderedDays.length > 0) {
                daysSummary = '(' + orderedDays.join(', ') + ')';
            } else {
                daysSummary = '(No days selected)';
            }

            const startTime = document.getElementById('openingTimeStart').value;
            const endTime = document.getElementById('openingTimeEnd').value;
            const startFmt = formatTime12(startTime);
            const endFmt = formatTime12(endTime);

            const preview = (startFmt && endFmt) ? `${startFmt} - ${endFmt} ${daysSummary}` : 'Please select times';
            document.getElementById('hoursPreviewText').textContent = preview;
        }
    </script>
</body>
</html>
