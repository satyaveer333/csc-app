<?php
/**
 * Owner Portal Navigation & Header Component
 */
if (!defined('CSC_APP')) {
    exit;
}

$currentPage = basename($_SERVER['PHP_SELF']);

// Check for low stock count for badge
$lowStockCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= low_stock_threshold");
    $lowStockCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

// Check for pending print orders
$pendingPrintCount = 0;
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'pending'");
    $pendingPrintCount = (int)$stmt->fetchColumn();
} catch (Exception $e) {}

$centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
?>
<header class="bg-white dark:bg-slate-900 text-slate-900 dark:text-white border-b border-slate-200 dark:border-slate-800 sticky top-0 z-30 shadow-sm transition-colors duration-150">
    <div class="max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16 gap-2">
            <!-- Brand -->
            <div class="flex items-center space-x-3 shrink-0">
                <a href="index.php" class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-blue-600 to-sky-500 flex items-center justify-center font-black text-sm text-white shadow-sm border border-blue-400/30 select-none">
                        DS
                    </div>
                    <div class="hidden sm:block">
                        <div class="font-bold text-sm sm:text-base tracking-tight leading-none text-slate-900 dark:text-white truncate max-w-[180px] md:max-w-xs"><?= e($centerName) ?></div>
                        <div class="text-[11px] text-blue-600 dark:text-blue-400 font-medium mt-0.5">Owner Portal</div>
                    </div>
                </a>
            </div>

            <!-- Desktop Nav Items -->
            <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                <a href="index.php" class="px-3 py-2 rounded-xl transition <?= $currentPage === 'index.php' ? 'bg-blue-50 text-blue-700 dark:bg-slate-800 dark:text-white font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="home" class="w-4 h-4 text-blue-500"></i>
                    <span>Dashboard</span>
                </a>
                <a href="forms.php" class="px-3 py-2 rounded-xl transition <?= $currentPage === 'forms.php' ? 'bg-emerald-50 text-emerald-700 dark:bg-slate-800 dark:text-white font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="file-text" class="w-4 h-4 text-emerald-500"></i>
                    <span>Govt Forms</span>
                </a>
                <a href="inventory.php" class="px-3 py-2 rounded-xl transition <?= $currentPage === 'inventory.php' ? 'bg-amber-50 text-amber-700 dark:bg-slate-800 dark:text-white font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="boxes" class="w-4 h-4 text-amber-500"></i>
                    <span>Inventory</span>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-500 text-slate-900"><?= $lowStockCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="print_orders.php" class="px-3 py-2 rounded-xl transition <?= $currentPage === 'print_orders.php' ? 'bg-cyan-50 text-cyan-700 dark:bg-slate-800 dark:text-white font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="printer" class="w-4 h-4 text-cyan-500"></i>
                    <span>Print Orders</span>
                    <?php if ($pendingPrintCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-[10px] font-bold bg-cyan-400 text-slate-900"><?= $pendingPrintCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="px-3 py-2 rounded-xl transition <?= $currentPage === 'settings.php' ? 'bg-indigo-50 text-indigo-700 dark:bg-slate-800 dark:text-white font-semibold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="settings" class="w-4 h-4 text-indigo-500"></i>
                    <span>Settings</span>
                </a>
            </nav>

            <!-- Actions: Light/Dark Mode + User + Logout -->
            <div class="flex items-center space-x-2 sm:space-x-3 shrink-0">
                <!-- Theme Toggle Button -->
                <button type="button" onclick="toggleOwnerTheme()" id="themeToggleBtn" title="Switch Theme (Light / Dark)"
                        class="p-2 rounded-xl text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-300 dark:hover:text-white dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-700 transition flex items-center justify-center">
                    <i data-lucide="sun" class="w-4 h-4 hidden dark:inline-block text-amber-400"></i>
                    <i data-lucide="moon" class="w-4 h-4 inline-block dark:hidden text-slate-600"></i>
                </button>

                <span class="hidden lg:inline-block text-xs text-slate-500 dark:text-slate-400">
                    <strong class="text-slate-800 dark:text-white"><?= e($_SESSION['owner_username'] ?? 'Owner') ?></strong>
                </span>

                <a href="logout.php" onclick="return confirm('Do you want to log out from Owner Portal?')" 
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-xs font-semibold bg-red-100 text-red-700 hover:bg-red-200 dark:bg-red-950/60 dark:text-red-300 dark:border dark:border-red-800/60 dark:hover:bg-red-900 transition">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span class="hidden sm:inline">Logout</span>
                </a>
            </div>
        </div>

        <!-- Mobile sub-navigation bar -->
        <div class="flex md:hidden overflow-x-auto py-2 border-t border-slate-200 dark:border-slate-800 space-x-1.5 text-xs font-medium no-scrollbar">
            <a href="index.php" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $currentPage === 'index.php' ? 'bg-blue-600 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">Dashboard</a>
            <a href="forms.php" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $currentPage === 'forms.php' ? 'bg-emerald-600 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">Forms</a>
            <a href="inventory.php" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $currentPage === 'inventory.php' ? 'bg-amber-600 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">Inventory <?= $lowStockCount > 0 ? "($lowStockCount)" : "" ?></a>
            <a href="print_orders.php" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $currentPage === 'print_orders.php' ? 'bg-cyan-600 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">Prints <?= $pendingPrintCount > 0 ? "($pendingPrintCount)" : "" ?></a>
            <a href="settings.php" class="px-2.5 py-1.5 rounded-lg whitespace-nowrap <?= $currentPage === 'settings.php' ? 'bg-indigo-600 text-white font-semibold' : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800' ?>">Settings</a>
        </div>
    </div>
</header>

<script>
    function toggleOwnerTheme() {
        const isDark = document.documentElement.classList.contains('dark');
        if (isDark) {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('owner_theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            localStorage.setItem('owner_theme', 'dark');
        }
        if (window.lucide) {
            lucide.createIcons();
        }
    }
</script>
