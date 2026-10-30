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
<header class="bg-slate-900 text-white sticky top-0 z-30 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Brand -->
            <div class="flex items-center space-x-3">
                <a href="index.php" class="flex items-center space-x-2.5">
                    <div class="w-9 h-9 rounded-lg bg-gradient-to-br from-blue-600 to-sky-500 flex items-center justify-center font-black text-sm text-white shadow-sm border border-blue-400/30 select-none">
                        DS
                    </div>
                    <div>
                        <div class="font-bold text-base tracking-tight leading-none text-white"><?= e($centerName) ?></div>
                        <div class="text-xs text-blue-400 font-medium mt-0.5">Owner Administration Portal</div>
                    </div>
                </a>
            </div>

            <!-- Desktop Nav Items -->
            <nav class="hidden md:flex items-center space-x-1 text-sm font-medium">
                <a href="index.php" class="px-3 py-2 rounded-lg transition <?= $currentPage === 'index.php' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="home" class="w-4 h-4 text-blue-400"></i>
                    <span>Dashboard</span>
                </a>
                <a href="forms.php" class="px-3 py-2 rounded-lg transition <?= $currentPage === 'forms.php' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="file-text" class="w-4 h-4 text-emerald-400"></i>
                    <span>Govt Forms</span>
                </a>
                <a href="inventory.php" class="px-3 py-2 rounded-lg transition <?= $currentPage === 'inventory.php' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="boxes" class="w-4 h-4 text-amber-400"></i>
                    <span>CSC Inventory</span>
                    <?php if ($lowStockCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-xs font-bold bg-amber-500 text-slate-900"><?= $lowStockCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="print_orders.php" class="px-3 py-2 rounded-lg transition <?= $currentPage === 'print_orders.php' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="printer" class="w-4 h-4 text-cyan-400"></i>
                    <span>Print Orders</span>
                    <?php if ($pendingPrintCount > 0): ?>
                        <span class="px-1.5 py-0.5 rounded-full text-xs font-bold bg-cyan-400 text-slate-900"><?= $pendingPrintCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="settings.php" class="px-3 py-2 rounded-lg transition <?= $currentPage === 'settings.php' ? 'bg-slate-800 text-white font-semibold' : 'text-slate-300 hover:text-white hover:bg-slate-800' ?> flex items-center gap-1.5">
                    <i data-lucide="settings" class="w-4 h-4 text-indigo-400"></i>
                    <span>Settings & Email</span>
                </a>
            </nav>

            <!-- User / Logout -->
            <div class="flex items-center space-x-3">
                <span class="hidden sm:inline-block text-xs text-slate-400">
                    Logged as: <strong class="text-white"><?= e($_SESSION['owner_username'] ?? 'Owner') ?></strong>
                </span>
                <a href="logout.php" onclick="return confirm('Do you want to log out from Owner Portal?')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-red-900/40 text-red-300 border border-red-800/60 hover:bg-red-800 hover:text-white transition">
                    <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>

        <!-- Mobile sub-navigation bar -->
        <div class="flex md:hidden overflow-x-auto py-2 border-t border-slate-800 space-x-2 text-xs font-medium">
            <a href="index.php" class="px-3 py-1.5 rounded-md whitespace-nowrap <?= $currentPage === 'index.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>">Dashboard</a>
            <a href="forms.php" class="px-3 py-1.5 rounded-md whitespace-nowrap <?= $currentPage === 'forms.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>">Forms</a>
            <a href="inventory.php" class="px-3 py-1.5 rounded-md whitespace-nowrap <?= $currentPage === 'inventory.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>">Inventory <?= $lowStockCount > 0 ? "($lowStockCount)" : "" ?></a>
            <a href="print_orders.php" class="px-3 py-1.5 rounded-md whitespace-nowrap <?= $currentPage === 'print_orders.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>">Prints <?= $pendingPrintCount > 0 ? "($pendingPrintCount)" : "" ?></a>
            <a href="settings.php" class="px-3 py-1.5 rounded-md whitespace-nowrap <?= $currentPage === 'settings.php' ? 'bg-slate-800 text-white' : 'text-slate-300' ?>">Settings</a>
        </div>
    </div>
</header>
