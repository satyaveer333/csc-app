<?php
/**
 * CSC Owner Portal - Dashboard
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

// Fetch summary metrics
$totalForms = (int)$pdo->query("SELECT COUNT(*) FROM forms WHERE status = 'active'")->fetchColumn();
$totalPrints = (int)$pdo->query("SELECT COUNT(*) FROM print_orders")->fetchColumn();
$pendingPrints = (int)$pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'pending'")->fetchColumn();
$lowStockItems = (int)$pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= low_stock_threshold")->fetchColumn();

// Fetch latest 6 print orders
$stmt = $pdo->query("SELECT * FROM print_orders ORDER BY id DESC LIMIT 6");
$recentPrints = $stmt->fetchAll();

// Fetch low stock items
$stmt = $pdo->query("SELECT * FROM inventory WHERE quantity <= low_stock_threshold ORDER BY quantity ASC LIMIT 5");
$criticalInventory = $stmt->fetchAll();

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Dashboard - <?= e(getSetting('center_name', 'Digital Solutions', $pdo)) ?></title>
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

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6 sm:space-y-8">
        
        <!-- Welcome Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-3 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">Center Overview</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Manage CSC citizen services, online print requests, and consumable inventory.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="forms.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-emerald-600 hover:bg-emerald-500 text-white shadow-sm transition">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Manage Forms</span>
                </a>
                <a href="inventory.php" class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs sm:text-sm font-semibold bg-blue-600 hover:bg-blue-500 text-white shadow-sm transition">
                    <i data-lucide="boxes" class="w-4 h-4"></i>
                    <span>Manage Inventory</span>
                </a>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?> text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Metric KPI Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">
            <!-- Active Forms -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Active Services</span>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1"><?= $totalForms ?></div>
                    <a href="forms.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline mt-2 inline-flex items-center gap-1 font-medium">
                        Manage Forms <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
                <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <i data-lucide="file-text" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Pending Print Requests -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Pending Prints</span>
                    <div class="text-2xl sm:text-3xl font-black text-cyan-600 dark:text-cyan-400 mt-1"><?= $pendingPrints ?></div>
                    <a href="print_orders.php" class="text-xs text-cyan-600 dark:text-cyan-400 hover:underline mt-2 inline-flex items-center gap-1 font-medium">
                        View Queue <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
                <div class="w-12 h-12 rounded-xl bg-cyan-50 dark:bg-cyan-500/10 text-cyan-600 dark:text-cyan-400 flex items-center justify-center">
                    <i data-lucide="printer" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Total Print Orders -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Submissions</span>
                    <div class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white mt-1"><?= $totalPrints ?></div>
                    <span class="text-xs text-slate-400 dark:text-slate-500 mt-2 inline-block">Online Orders</span>
                </div>
                <div class="w-12 h-12 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <i data-lucide="inbox" class="w-6 h-6"></i>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Low Stock Items</span>
                    <div class="text-2xl sm:text-3xl font-black <?= $lowStockItems > 0 ? 'text-amber-500' : 'text-slate-600 dark:text-slate-300' ?> mt-1"><?= $lowStockItems ?></div>
                    <a href="inventory.php" class="text-xs text-amber-600 dark:text-amber-400 hover:underline mt-2 inline-flex items-center gap-1 font-medium">
                        Inventory Alert <i data-lucide="arrow-right" class="w-3 h-3"></i>
                    </a>
                </div>
                <div class="w-12 h-12 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-6 h-6"></i>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 sm:gap-8">
            
            <!-- Left 2 Cols: Recent Print Orders -->
            <div class="lg:col-span-2 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="printer" class="w-5 h-5 text-cyan-500"></i>
                        <span>Recent Online Print Orders</span>
                    </h2>
                    <a href="print_orders.php" class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-semibold">View All Orders &rarr;</a>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
                    <?php if (empty($recentPrints)): ?>
                        <div class="p-8 text-center text-slate-400">
                            <i data-lucide="file-x" class="w-10 h-10 mx-auto mb-2 opacity-50"></i>
                            <p class="text-sm font-medium text-slate-600 dark:text-slate-400">No print orders received yet.</p>
                            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1">Customer submissions will appear here instantly.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto w-full">
                            <table class="w-full text-left text-sm">
                                <thead class="bg-slate-100 dark:bg-slate-900/60 text-xs uppercase text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                                    <tr>
                                        <th class="px-5 py-3">Token / Customer</th>
                                        <th class="px-4 py-3">Specs</th>
                                        <th class="px-4 py-3">Documents</th>
                                        <th class="px-4 py-3">Status</th>
                                        <th class="px-4 py-3 text-right">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                                    <?php foreach ($recentPrints as $p): 
                                        $files = json_decode($p['files_json'], true) ?: [];
                                    ?>
                                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition">
                                            <td class="px-5 py-3.5 whitespace-nowrap">
                                                <div class="font-bold text-slate-900 dark:text-white"><?= e($p['token_id']) ?></div>
                                                <div class="text-xs text-slate-500 dark:text-slate-400 flex items-center gap-1.5 mt-0.5">
                                                    <span><?= e($p['customer_name']) ?></span>
                                                    <span>•</span>
                                                    <span><?= e($p['customer_phone']) ?></span>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-200 font-medium"><?= e($p['print_type']) ?></span>
                                                <div class="text-slate-500 dark:text-slate-400 mt-1"><?= e($p['paper_size']) ?> • <?= e($p['copies']) ?> copy</div>
                                            </td>
                                            <td class="px-4 py-3.5 text-xs whitespace-nowrap">
                                                <span class="font-semibold text-blue-600 dark:text-blue-400"><?= count($files) ?> file(s)</span>
                                                <div class="text-slate-400 truncate max-w-[140px] text-xs">
                                                    <?= !empty($files[0]['original_name']) ? e($files[0]['original_name']) : 'Document' ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-3.5 whitespace-nowrap">
                                                <?php
                                                $statusClasses = [
                                                    'pending' => 'bg-amber-100 text-amber-800 border-amber-300 dark:bg-amber-950/60 dark:text-amber-300 dark:border-amber-800',
                                                    'processing' => 'bg-blue-100 text-blue-800 border-blue-300 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800',
                                                    'ready' => 'bg-emerald-100 text-emerald-800 border-emerald-300 dark:bg-emerald-950/60 dark:text-emerald-300 dark:border-emerald-800',
                                                    'delivered' => 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600',
                                                ];
                                                $cls = $statusClasses[$p['status']] ?? 'bg-slate-100 text-slate-700 border-slate-300 dark:bg-slate-700 dark:text-slate-300 dark:border-slate-600';
                                                ?>
                                                <span class="px-2.5 py-1 rounded-full text-xs font-semibold border <?= $cls ?>">
                                                    <?= ucfirst(e($p['status'])) ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                                <a href="print_orders.php?order=<?= $p['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-white transition">
                                                    <span>Open</span>
                                                    <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right 1 Col: Low Stock Alerts -->
            <div class="space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <i data-lucide="alert-triangle" class="w-5 h-5 text-amber-500"></i>
                        <span>Stock Alerts</span>
                    </h2>
                    <a href="inventory.php" class="text-xs text-amber-600 dark:text-amber-400 hover:underline font-semibold">Inventory Hub &rarr;</a>
                </div>

                <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 p-5 space-y-4 shadow-sm">
                    <?php if (empty($criticalInventory)): ?>
                        <div class="text-center py-6 text-slate-400">
                            <i data-lucide="check-circle" class="w-10 h-10 mx-auto mb-2 text-emerald-500 opacity-80"></i>
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-200">All Stock Levels Healthy</p>
                            <p class="text-xs text-slate-500 mt-1">No items currently below threshold.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($criticalInventory as $item): ?>
                                <div class="p-3 bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80 rounded-xl flex items-center justify-between gap-3">
                                    <div>
                                        <h4 class="font-bold text-slate-900 dark:text-white text-sm"><?= e($item['item_name']) ?></h4>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                                            Current: <strong class="text-amber-600 dark:text-amber-400 font-bold"><?= $item['quantity'] ?></strong> / <?= $item['unit'] ?> (Alert &le; <?= $item['low_stock_threshold'] ?>)
                                        </div>
                                    </div>
                                    <form method="POST" action="inventory.php">
                                        <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                        <input type="hidden" name="action" value="quick_stock_adjust">
                                        <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                        <input type="hidden" name="delta" value="5">
                                        <button type="submit" class="px-2.5 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1 shadow-sm">
                                            <i data-lucide="plus" class="w-3.5 h-3.5"></i> +5
                                        </button>
                                    </form>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="pt-3 border-t border-slate-200 dark:border-slate-700">
                        <a href="inventory.php" class="w-full py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 text-xs font-semibold transition flex items-center justify-center gap-2">
                            <i data-lucide="boxes" class="w-4 h-4"></i> View Complete Inventory
                        </a>
                    </div>
                </div>
            </div>

        </div>

    </main>

    <footer class="bg-white dark:bg-slate-950 border-t border-slate-200 dark:border-slate-800 text-center py-4 text-xs text-slate-500 transition-colors duration-150">
        <?= e(getSetting('center_name', 'Digital Solutions', $pdo)) ?> &bull; Owner Administration Console
    </footer>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
