<?php
/**
 * CSC Owner Portal - Store & Stationery Inventory Manager
 * Tracks paper, inks, lamination, PVC cards, stock in/out and low-stock alerts
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? 'list';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // 1. ADD NEW INVENTORY ITEM
    if ($action === 'create') {
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'General Stationery');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'Pieces');
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 10));
        $costPrice = (float)($_POST['cost_price'] ?? 0.00);
        $sellingPrice = (float)($_POST['selling_price'] ?? 0.00);
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($itemName)) {
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
        } else {
            setFlash('danger', 'Item name is required.');
        }
        header('Location: inventory.php');
        exit;
    }

    // 2. UPDATE INVENTORY ITEM
    if ($action === 'update') {
        $id = (int)$_POST['id'];
        $itemName = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $quantity = (int)($_POST['quantity'] ?? 0);
        $unit = trim($_POST['unit'] ?? 'Pieces');
        $threshold = max(0, (int)($_POST['low_stock_threshold'] ?? 10));
        $costPrice = (float)($_POST['cost_price'] ?? 0.00);
        $sellingPrice = (float)($_POST['selling_price'] ?? 0.00);
        $notes = trim($_POST['notes'] ?? '');

        if ($id > 0 && !empty($itemName)) {
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
        }
        header('Location: inventory.php');
        exit;
    }

    // 3. QUICK STOCK ADJUSTMENT (+ / -)
    if ($action === 'quick_stock_adjust') {
        $id = (int)$_POST['id'];
        $delta = (int)$_POST['delta'];
        if ($id > 0 && $delta !== 0) {
            $stmt = $pdo->prepare("
                UPDATE inventory 
                SET quantity = MAX(0, quantity + :delta), updated_at = CURRENT_TIMESTAMP 
                WHERE id = :id
            ");
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
            setFlash('success', 'Inventory item deleted.');
        }
        header('Location: inventory.php');
        exit;
    }
}

// Fetch all inventory items
$filterLow = isset($_GET['filter']) && $_GET['filter'] === 'low_stock';
$sql = "SELECT * FROM inventory";
if ($filterLow) {
    $sql .= " WHERE quantity <= low_stock_threshold";
}
$sql .= " ORDER BY CASE WHEN quantity <= low_stock_threshold THEN 0 ELSE 1 END, category ASC, item_name ASC";

$stmt = $pdo->query($sql);
$items = $stmt->fetchAll();

// Calculate total valuation
$totalCostVal = 0.0;
$totalItemsCount = 0;
$lowCount = 0;

$allStmt = $pdo->query("SELECT quantity, cost_price, low_stock_threshold FROM inventory");
while ($r = $allStmt->fetch()) {
    $totalItemsCount++;
    $totalCostVal += ($r['quantity'] * $r['cost_price']);
    if ($r['quantity'] <= $r['low_stock_threshold']) {
        $lowCount++;
    }
}

$inventoryCategories = [
    'Paper & Sheets',
    'Photo Supplies',
    'Lamination',
    'ID Cards & PVC',
    'Printer Inks & Toners',
    'Stationery & Envelopes',
    'Hardware & Cables',
    'Other Supplies'
];

$units = ['Reams (500 sheets)', 'Sheets', 'Pouches', 'Bottles', 'Cards', 'Packets', 'Pieces', 'Boxes', 'Rolls'];

$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Inventory - <?= e(getSetting('center_name', 'Digital Solutions', $pdo)) ?></title>
    <!-- Favicon (DS Monogram) -->
    <link rel="icon" type="image/svg+xml" href="../favicon.svg">
    <link rel="alternate icon" href="../favicon.svg">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen bg-slate-900 text-slate-100 flex flex-col antialiased">

    <?php include __DIR__ . '/nav.php'; ?>

    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        
        <!-- Header -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-800">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">CSC Store Inventory</h1>
                <p class="text-slate-400 text-sm mt-1">Track paper reams, printer ink, lamination pouches, PVC cards, and stock alerts.</p>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="openInventoryModal()" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl font-semibold bg-blue-600 hover:bg-blue-500 text-white shadow-sm transition">
                    <i data-lucide="plus-circle" class="w-4 h-4"></i>
                    <span>Add New Item</span>
                </button>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-950/70 border border-emerald-800 text-emerald-300' : 'bg-red-950/70 border border-red-800 text-red-300' ?> text-sm flex items-center gap-2">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Inventory KPI Summary -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Items Tracked</span>
                    <div class="text-2xl font-black text-white mt-1"><?= $totalItemsCount ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center">
                    <i data-lucide="boxes" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Low Stock Warnings</span>
                    <div class="text-2xl font-black <?= $lowCount > 0 ? 'text-amber-400' : 'text-emerald-400' ?> mt-1">
                        <?= $lowCount ?>
                    </div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center">
                    <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                </div>
            </div>

            <div class="bg-slate-800 rounded-2xl p-5 border border-slate-700 flex items-center justify-between">
                <div>
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Estimated Stock Cost Value</span>
                    <div class="text-2xl font-black text-emerald-400 mt-1"><?= formatCurrency($totalCostVal) ?></div>
                </div>
                <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="banknote" class="w-5 h-5"></i>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-2 w-full sm:w-auto">
                <a href="inventory.php" class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= !$filterLow ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
                    All Items (<?= $totalItemsCount ?>)
                </a>
                <a href="inventory.php?filter=low_stock" class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 <?= $filterLow ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-amber-400 hover:bg-slate-700' ?>">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5"></i>
                    <span>Low Stock Only (<?= $lowCount ?>)</span>
                </a>
            </div>

            <div class="relative w-full sm:w-72">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                <input type="text" id="invSearchInput" onkeyup="filterInventoryTable()" placeholder="Search inventory..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
        </div>

        <!-- Inventory Items Table -->
        <div class="bg-slate-800 rounded-2xl border border-slate-700 overflow-hidden shadow-lg">
            <?php if (empty($items)): ?>
                <div class="p-12 text-center text-slate-400">
                    <i data-lucide="package-search" class="w-12 h-12 mx-auto mb-3 opacity-50"></i>
                    <h3 class="text-base font-bold text-slate-300">No Inventory Items Found</h3>
                    <p class="text-xs text-slate-500 mt-1">Click "Add New Item" to start tracking stationery and consumables.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm" id="inventoryTable">
                        <thead class="bg-slate-900/60 text-xs uppercase text-slate-400 border-b border-slate-700">
                            <tr>
                                <th class="px-5 py-3.5">Item & Category</th>
                                <th class="px-4 py-3.5">Quantity in Stock</th>
                                <th class="px-4 py-3.5">Quick Stock Adjust</th>
                                <th class="px-4 py-3.5">Cost Price</th>
                                <th class="px-4 py-3.5">Retail Price</th>
                                <th class="px-4 py-3.5">Status</th>
                                <th class="px-5 py-3.5 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/60">
                            <?php foreach ($items as $item): 
                                $isLow = $item['quantity'] <= $item['low_stock_threshold'];
                            ?>
                                <tr class="hover:bg-slate-700/40 transition inv-row" data-search="<?= e(strtolower($item['item_name'] . ' ' . $item['category'] . ' ' . $item['notes'])) ?>">
                                    <td class="px-5 py-4">
                                        <div class="font-bold text-white text-sm"><?= e($item['item_name']) ?></div>
                                        <div class="text-xs text-slate-400 mt-0.5"><?= e($item['category']) ?></div>
                                        <?php if (!empty($item['notes'])): ?>
                                            <div class="text-xs text-slate-500 mt-1 italic"><?= e($item['notes']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="text-base font-black <?= $isLow ? 'text-amber-400' : 'text-white' ?>">
                                            <?= $item['quantity'] ?>
                                            <span class="text-xs font-normal text-slate-400"><?= e($item['unit']) ?></span>
                                        </div>
                                        <div class="text-xs text-slate-500 mt-0.5">Alert below <?= $item['low_stock_threshold'] ?></div>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <div class="flex items-center gap-1.5">
                                            <!-- -1 -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="-1">
                                                <button type="submit" title="Deduct 1" class="w-7 h-7 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-bold transition flex items-center justify-center">
                                                    -1
                                                </button>
                                            </form>
                                            <!-- +1 -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="1">
                                                <button type="submit" title="Add 1" class="w-7 h-7 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 text-xs font-bold transition flex items-center justify-center">
                                                    +1
                                                </button>
                                            </form>
                                            <!-- +5 / +10 Quick Restock -->
                                            <form method="POST" class="inline-block">
                                                <input type="hidden" name="action" value="quick_stock_adjust">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <input type="hidden" name="delta" value="5">
                                                <button type="submit" title="Restock +5" class="px-2 h-7 rounded-lg bg-blue-900/60 hover:bg-blue-800 text-blue-300 text-xs font-bold transition flex items-center justify-center">
                                                    +5
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-300 whitespace-nowrap">
                                        <?= formatCurrency($item['cost_price']) ?>
                                    </td>
                                    <td class="px-4 py-4 text-xs text-slate-300 whitespace-nowrap">
                                        <?= formatCurrency($item['selling_price']) ?>
                                    </td>
                                    <td class="px-4 py-4 whitespace-nowrap">
                                        <?php if ($isLow): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-950/70 border border-amber-800 text-amber-300">
                                                <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-400"></i> Low Stock
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-950/70 border border-emerald-800 text-emerald-300">
                                                <i data-lucide="check" class="w-3 h-3 text-emerald-400"></i> In Stock
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-4 text-right whitespace-nowrap">
                                        <div class="flex items-center justify-end gap-2">
                                            <button onclick="editInventoryItem(<?= htmlspecialchars(json_encode($item), ENT_QUOTES, 'UTF-8') ?>)" class="p-2 rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-200 transition" title="Edit Item">
                                                <i data-lucide="pencil" class="w-4 h-4"></i>
                                            </button>
                                            <form method="POST" onsubmit="return confirm('Do you want to remove this inventory item?');" class="inline-block">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                                <button type="submit" class="p-2 rounded-lg bg-red-950/60 hover:bg-red-800 text-red-300 transition" title="Delete Item">
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

    <!-- ================= ADD / EDIT INVENTORY MODAL ================= -->
    <div id="modalInventoryContainer" class="fixed inset-0 bg-black/70 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-slate-800 rounded-3xl max-w-lg w-full max-h-[90vh] overflow-y-auto shadow-2xl border border-slate-700">
            <div class="sticky top-0 bg-slate-800 border-b border-slate-700 px-6 py-4 flex items-center justify-between z-10">
                <h3 id="modalInvHeading" class="text-lg font-bold text-white flex items-center gap-2">
                    <i data-lucide="package-plus" class="w-5 h-5 text-blue-400"></i>
                    <span>Add New Inventory Item</span>
                </h3>
                <button type="button" onclick="closeInventoryModal()" class="w-8 h-8 rounded-full bg-slate-700 hover:bg-slate-600 text-slate-300 flex items-center justify-center transition">
                    <i data-lucide="x" class="w-5 h-5"></i>
                </button>
            </div>

            <form method="POST" id="inventoryForm" class="p-6 space-y-4">
                <input type="hidden" name="action" id="invAction" value="create">
                <input type="hidden" name="id" id="invId" value="">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">
                        Item Name <span class="text-red-400">*</span>
                    </label>
                    <input type="text" name="item_name" id="invItemName" required placeholder="e.g. JK Copier A4 Paper 75 GSM"
                           class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Category</label>
                        <select name="category" id="invCategory" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($inventoryCategories as $cat): ?>
                                <option value="<?= e($cat) ?>"><?= e($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Unit of Measure</label>
                        <select name="unit" id="invUnit" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                            <?php foreach ($units as $u): ?>
                                <option value="<?= e($u) ?>"><?= e($u) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Current Stock Quantity</label>
                        <input type="number" name="quantity" id="invQuantity" value="10" min="0" required
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-amber-400 uppercase tracking-wider mb-1.5">Low Stock Alert Threshold</label>
                        <input type="number" name="low_stock_threshold" id="invThreshold" value="5" min="0" required
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Cost Price (₹ per unit)</label>
                        <input type="number" step="0.01" name="cost_price" id="invCostPrice" value="0.00"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Selling / Service Rate (₹)</label>
                        <input type="number" step="0.01" name="selling_price" id="invSellingPrice" value="0.00"
                               class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-1.5">Supplier / Storage Notes (Optional)</label>
                    <textarea name="notes" id="invNotes" rows="2" placeholder="e.g. Purchased from local wholesale dealer, rack B2..."
                              class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-white text-sm focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-slate-700">
                    <button type="button" onclick="closeInventoryModal()" class="px-5 py-2.5 rounded-xl bg-slate-700 hover:bg-slate-600 text-slate-300 text-sm font-semibold transition">
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
            document.getElementById('modalInvHeading').querySelector('span').textContent = 'Add New Inventory Item';
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
