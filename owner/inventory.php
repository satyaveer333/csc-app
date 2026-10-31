<?php
/**
 * CSC Owner Portal - Inventory Management
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Verify CSRF Token
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security validation failed (Invalid CSRF Token). Please try again.');
        header('Location: inventory.php');
        exit;
    }

    // 1. ADD NEW INVENTORY ITEM
    if ($action === 'create') {
        $itemName = trim(strip_tags($_POST['item_name'] ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? 'General Stationery'));
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $unit = trim(strip_tags($_POST['unit'] ?? 'Pieces'));
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 10));
        $costPrice = max(0.0, round((float)($_POST['cost_price'] ?? 0.00), 2));
        $sellingPrice = max(0.0, round((float)($_POST['selling_price'] ?? 0.00), 2));
        $notes = trim(strip_tags($_POST['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) $notes = mb_substr($notes, 0, 1000);

        if (empty($itemName)) {
            setFlash('danger', 'Item name is required.');
            header('Location: inventory.php');
            exit;
        }
        if (mb_strlen($itemName) < 2 || mb_strlen($itemName) > 200) {
            setFlash('danger', 'Item name must be between 2 and 200 characters.');
            header('Location: inventory.php');
            exit;
        }

        $stmt = $pdo->prepare("
            INSERT INTO inventory (item_name, category, quantity, unit, low_stock_threshold, cost_price, selling_price, notes, updated_at)
            VALUES (:item_name, :category, :quantity, :unit, :threshold, :cost_price, :selling_price, :notes, CURRENT_TIMESTAMP)
        ");
        $stmt->execute([
            ':item_name' => $itemName,
            ':category' => $category,
            ':quantity' => $quantity,
            ':unit' => $unit,
            ':threshold' => $threshold,
            ':cost_price' => $costPrice,
            ':selling_price' => $sellingPrice,
            ':notes' => $notes
        ]);
        setFlash('success', "Item '{$itemName}' added to inventory.");
        header('Location: inventory.php');
        exit;
    }

    // 2. UPDATE INVENTORY ITEM
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $itemName = trim(strip_tags($_POST['item_name'] ?? ''));
        $category = trim(strip_tags($_POST['category'] ?? 'General Stationery'));
        $quantity = max(0, (int)($_POST['quantity'] ?? 0));
        $unit = trim(strip_tags($_POST['unit'] ?? 'Pieces'));
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 10));
        $costPrice = max(0.0, round((float)($_POST['cost_price'] ?? 0.00), 2));
        $sellingPrice = max(0.0, round((float)($_POST['selling_price'] ?? 0.00), 2));
        $notes = trim(strip_tags($_POST['notes'] ?? ''));
        if (mb_strlen($notes) > 1000) $notes = mb_substr($notes, 0, 1000);

        if ($id <= 0 || empty($itemName)) {
            setFlash('danger', 'Invalid item ID or missing item name.');
            header('Location: inventory.php');
            exit;
        }
        if (mb_strlen($itemName) < 2 || mb_strlen($itemName) > 200) {
            setFlash('danger', 'Item name must be between 2 and 200 characters.');
            header('Location: inventory.php');
            exit;
        }

        $stmt = $pdo->prepare("
            UPDATE inventory SET 
                item_name = :item_name, 
                category = :category, 
                quantity = :quantity, 
                unit = :unit, 
                low_stock_threshold = :threshold, 
                cost_price = :cost_price, 
                selling_price = :selling_price, 
                notes = :notes,
                updated_at = CURRENT_TIMESTAMP
            WHERE id = :id
        ");
        $stmt->execute([
            ':id' => $id,
            ':item_name' => $itemName,
            ':category' => $category,
            ':quantity' => $quantity,
            ':unit' => $unit,
            ':threshold' => $threshold,
            ':cost_price' => $costPrice,
            ':selling_price' => $sellingPrice,
            ':notes' => $notes
        ]);
        setFlash('success', "Item '{$itemName}' updated successfully.");
        header('Location: inventory.php');
        exit;
    }

    // 3. QUICK STOCK ADJUSTMENT (+ / -)
    if ($action === 'quick_stock_adjust') {
        $id = (int)$_POST['id'];
        $delta = (int)$_POST['delta'];
        if ($id > 0 && $delta !== 0) {
            $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
            $sql = ($driver === 'sqlite')
                ? "UPDATE inventory SET quantity = MAX(0, quantity + :delta), updated_at = datetime('now') WHERE id = :id"
                : "UPDATE inventory SET quantity = GREATEST(0, CAST(quantity AS SIGNED) + :delta), updated_at = CURRENT_TIMESTAMP WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':delta' => $delta, ':id' => $id]);
            setFlash('success', 'Stock quantity adjusted.');
        }
        header('Location: inventory.php');
        exit;
    }

    // 4. DELETE INVENTORY ITEM
    if ($action === 'delete') {
        $id = (int)$_POST['id'];
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM inventory WHERE id = :id");
            $stmt->execute([':id' => $id]);
            setFlash('success', 'Inventory item removed.');
        }
        header('Location: inventory.php');
        exit;
    }
}

// Fetch Inventory items
$filterLow = (isset($_GET['filter']) && $_GET['filter'] === 'low_stock');
$sql = "SELECT * FROM inventory";
if ($filterLow) {
    $sql .= " WHERE quantity <= low_stock_threshold";
}
$sql .= " ORDER BY quantity <= low_stock_threshold DESC, item_name ASC";
$items = $pdo->query($sql)->fetchAll();

// Calculate total metrics
$allStmt = $pdo->query("SELECT quantity, cost_price, low_stock_threshold FROM inventory");
$allItems = $allStmt->fetchAll();
$totalItemsCount = count($allItems);
$lowCount = 0;
$totalCostVal = 0.0;
foreach ($allItems as $it) {
    if ($it['quantity'] <= $it['low_stock_threshold']) {
        $lowCount++;
    }
    $totalCostVal += ($it['quantity'] * (float)$it['cost_price']);
}

$inventoryCategories = [
    'Paper & Sheets',
    'Photo Supplies',
    'Lamination',
    'ID Cards',
    'Printer Inks',
    'Stationery',
    'Envelopes & Covers',
    'General Supplies'
];

$units = ['Pieces', 'Reams (500 sheets)', 'Sheets', 'Pouches', 'Cards', 'Bottles', 'Sets', 'Boxes', 'Packets'];
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSC Inventory - <?= e(getSetting('center_name', 'Digital Solutions', $pdo)) ?></title>
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

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8 space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-200 dark:border-slate-800">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900 dark:text-white tracking-tight">Center Inventory & Stock</h1>
                <p class="text-slate-500 dark:text-slate-400 text-xs sm:text-sm mt-1">Track paper reams, lamination pouches, smart cards, and printer ink consumables.</p>
            </div>
            <button onclick="openInventoryModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold bg-blue-600 hover:bg-blue-500 text-white shadow-sm transition">
                <i data-lucide="plus-circle" class="w-4 h-4"></i>
                <span>Add Stock Item</span>
            </button>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/70 dark:border-emerald-800 dark:text-emerald-300' : 'bg-red-50 border border-red-200 text-red-800 dark:bg-red-950/70 dark:border-red-800 dark:text-red-300' ?> text-xs sm:text-sm flex items-center gap-2 shadow-sm">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Inventory KPI Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Items Tracked</span>
                    <div class="text-2xl font-black text-slate-900 dark:text-white mt-1"><?= $totalItemsCount ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
                    <i data-lucide="boxes" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Low Stock Warnings</span>
                    <div class="text-2xl font-black <?= $lowCount > 0 ? 'text-amber-500' : 'text-emerald-500' ?> mt-1">
                        <?= $lowCount ?>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-50 dark:bg-amber-500/10 text-amber-500 dark:text-amber-400 flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-white dark:bg-slate-800 rounded-2xl p-5 border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 uppercase tracking-wider">Total Stock Cost Value</span>
                    <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1"><?= formatCurrency($totalCostVal) ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
                    <i data-lucide="banknote" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 w-full sm:w-auto overflow-x-auto">
                <a href="inventory.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap <?= !$filterLow ? 'bg-blue-600 text-white' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' ?>">
                    All Items (<?= $totalItemsCount ?>)
                </a>
                <a href="inventory.php?filter=low_stock" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 whitespace-nowrap <?= $filterLow ? 'bg-amber-500 text-slate-900 font-black' : 'bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 text-amber-600 dark:text-amber-400 hover:bg-slate-50 dark:hover:bg-slate-700' ?>">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    <span>Low Stock Only (<?= $lowCount ?>)</span>
                </a>
            </div>

            <div class="relative w-full sm:w-72">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                <input type="text" id="invSearchInput" onkeyup="filterInventoryTable()" placeholder="Search inventory..."
                       class="w-full pl-9 pr-4 py-2 bg-white dark:bg-slate-800 border border-slate-300 dark:border-slate-700 rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Inventory Items Table -->
        <div class="bg-white dark:bg-slate-800 rounded-2xl border border-slate-200 dark:border-slate-700 overflow-hidden shadow-sm">
            <?php if (empty($items)): ?>
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="package-search" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <h3 class="text-base font-bold text-slate-700 dark:text-slate-300">No Inventory Items Found</h3>
                    <p class="text-xs text-slate-500 mt-1">Click "Add Stock Item" to start tracking stationery and consumables.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto w-full">
                    <table class="w-full text-left text-sm" id="inventoryTable">
                        <thead class="bg-slate-100 dark:bg-slate-900/60 text-xs uppercase text-slate-600 dark:text-slate-400 border-b border-slate-200 dark:border-slate-700">
                            <tr>
                                <th class="px-5 py-3.5">Item & Category</th>
                                <th class="px-4 py-3.5">Quantity in Stock</th>
                                <th class="px-4 py-3.5">Quick Adjust</th>
                                <th class="px-4 py-3.5">Cost Price</th>
                                <th class="px-4 py-3.5">Retail Price</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 dark:divide-slate-700/60">
                            <?php foreach ($items as $item): 
                                $isLow = $item['quantity'] <= $item['low_stock_threshold'];
                            ?>
                                <tr class="hover:bg-slate-50 dark:hover:bg-slate-700/40 transition inv-row" data-search="<?= e(strtolower($item['item_name'] . ' ' . $item['category'] . ' ' . $item['notes'])) ?>">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-slate-900 dark:text-white text-sm"><?= e($item['item_name']) ?></div>
                                        <div class="text-xs text-slate-500 dark:text-slate-400 mt-0.5"><?= e($item['category']) ?></div>
                                        <?php if (!empty($item['notes'])): ?>
                                            <div class="text-xs text-slate-400 dark:text-slate-500 mt-1 italic"><?= e($item['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-base font-black <?= $isLow ? 'text-amber-500' : 'text-slate-900 dark:text-white' ?>">
                                            <?= $item['quantity'] ?>
                                            <span class="text-xs font-normal text-slate-500 dark:text-slate-400"><?= e($item['unit']) ?></span>
                                        </div>
                                        <div class="text-[11px] text-slate-400 mt-0.5">Alert &le; <?= $item['low_stock_threshold'] ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <!-- -1 -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" title="Deduct 1" class="w-7 h-7 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 text-xs font-bold transition flex items-center justify-center">
                                                    -1
                                                </button>
                                            </form>
                                            <!-- +1 -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" title="Add 1" class="w-7 h-7 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-800 dark:text-slate-200 text-xs font-bold transition flex items-center justify-center">
                                                    +1
                                                </button>
                                            </form>
                                            <!-- +5 Restock -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="5">
                                                <button type="submit" title="Quick Restock +5" class="px-2 py-1 rounded-lg bg-emerald-100 hover:bg-emerald-200 text-emerald-800 dark:bg-emerald-950 dark:hover:bg-emerald-900 dark:text-emerald-300 text-xs font-bold transition">
                                                    +5
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-600 dark:text-slate-300 whitespace-nowrap">
                                        <?= formatCurrency($item['cost_price']) ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs font-semibold text-slate-900 dark:text-white whitespace-nowrap">
                                        <?= formatCurrency($item['selling_price']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php if ($isLow): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-400 border border-amber-300 dark:border-amber-800">
                                                <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-500"></i> Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800">
                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span> Adequate
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <button type="button" onclick="editInventoryItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)"
                                                    class="p-1.5 rounded-lg bg-slate-100 hover:bg-blue-50 text-slate-600 hover:text-blue-600 dark:bg-slate-700 dark:hover:bg-blue-900/40 dark:text-slate-300 dark:hover:text-blue-300 transition" title="Edit Item">
                                                <i data-lucide="edit-3" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Do you really want to delete this inventory item?');" class="inline-block">
                                                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="p-1.5 rounded-lg bg-slate-100 hover:bg-red-50 text-slate-600 hover:text-red-600 dark:bg-slate-700 dark:hover:bg-red-950 dark:text-slate-300 dark:hover:text-red-400 transition" title="Delete Item">
                                                    <i data-lucide="trash-2" class="w-4 h-4"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <!-- Modal Form (Add / Edit Inventory Item) -->
    <div id="modalInventoryContainer" class="fixed inset-0 z-50 bg-slate-900/70 backdrop-blur-sm flex items-center justify-center p-3 sm:p-4 hidden">
        <div class="bg-white dark:bg-slate-800 rounded-3xl max-w-xl w-full p-5 sm:p-6 border border-slate-200 dark:border-slate-700 shadow-2xl max-h-[92vh] overflow-y-auto">
            
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-700 mb-5">
                <h3 id="modalInvHeading" class="text-base sm:text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <i data-lucide="package-plus" class="w-5 h-5 text-blue-500"></i>
                    <span>Add Stock Item</span>
                </h3>
                <button onclick="closeInventoryModal()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white p-1 rounded-lg">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" id="inventoryForm" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= e(getCsrfToken()) ?>">
                <input type="hidden" name="action" id="invAction" value="create">
                <input type="hidden" name="id" id="invId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">
                        Item Name <span class="text-red-500">*</span>
                    </label>
                    <input type="text" name="item_name" id="invItemName" required minlength="2" maxlength="200" placeholder="e.g. JK Copier A4 Paper 75 GSM"
                           class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Category</label>
                        <select name="category" id="invCategory" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($inventoryCategories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Unit of Measure</label>
                        <select name="unit" id="invUnit" class="w-full px-3 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Current Stock Quantity</label>
                        <input type="number" name="quantity" id="invQuantity" value="10" min="0" required
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-amber-600 dark:text-amber-400 uppercase tracking-wider mb-1.5">Low Stock Alert Threshold</label>
                        <input type="number" name="low_stock_threshold" id="invThreshold" value="5" min="0" required
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Cost Price (₹ per unit)</label>
                        <input type="number" step="0.01" min="0" name="cost_price" id="invCostPrice" value="0.00"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Selling / Service Rate (₹)</label>
                        <input type="number" step="0.01" min="0" name="selling_price" id="invSellingPrice" value="0.00"
                               class="w-full px-4 py-2.5 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1.5">Supplier / Storage Notes (Optional)</label>
                    <textarea name="notes" id="invNotes" rows="2" maxlength="1000" placeholder="e.g. Purchased from local wholesale dealer, rack B2..."
                              class="w-full px-4 py-2 bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-xl text-slate-900 dark:text-white text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-200 dark:border-slate-700">
                    <button type="button" onclick="closeInventoryModal()" class="px-5 py-2.5 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-700 dark:hover:bg-slate-600 text-slate-700 dark:text-slate-300 text-sm font-semibold transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white text-sm font-bold shadow-md transition flex items-center gap-2">
                        <i data-lucide="check" class="w-4 h-4"></i>
                        <span>Save Stock Item</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        lucide.createIcons();

        function filterInventoryTable() {
            const query = document.getElementById('invSearchInput').value.toLowerCase().trim();
            const rows = document.querySelectorAll('.inv-row');
            rows.forEach(r => {
                const text = r.getAttribute('data-search') || '';
                r.style.display = text.includes(query) ? '' : 'none';
            });
        }

        function openInventoryModal() {
            document.getElementById('invAction').value = 'create';
            document.getElementById('invId').value = '';
            document.getElementById('modalInvHeading').querySelector('span').textContent = 'Add Stock Item';
            document.getElementById('inventoryForm').reset();
            document.getElementById('modalInventoryContainer').classList.remove('hidden');
        }

        function closeInventoryModal() {
            document.getElementById('modalInventoryContainer').classList.add('hidden');
        }

        function editInventoryItem(item) {
            document.getElementById('invAction').value = 'update';
            document.getElementById('invId').value = item.id;
            document.getElementById('modalInvHeading').querySelector('span').textContent = 'Edit Item: ' + item.item_name;
            
            document.getElementById('invItemName').value = item.item_name || '';
            document.getElementById('invCategory').value = item.category || 'Paper & Sheets';
            document.getElementById('invUnit').value = item.unit || 'Pieces';
            document.getElementById('invQuantity').value = item.quantity || 0;
            document.getElementById('invThreshold').value = item.low_stock_threshold || 10;
            document.getElementById('invCostPrice').value = item.cost_price || 0.00;
            document.getElementById('invSellingPrice').value = item.selling_price || 0.00;
            document.getElementById('invNotes').value = item.notes || '';

            document.getElementById('modalInventoryContainer').classList.remove('hidden');
        }
    </script>
</body>
</html>
