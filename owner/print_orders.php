<?php
/**
 * CSC Owner Portal - Customer Print Orders Hub
 * Manage incoming print orders, download documents, change status, and send WhatsApp alerts
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();
ensureDirectories();
$pdo = getDbConnection();

$action = $_POST['action'] ?? '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Update order status
    if ($action === 'update_status') {
        $orderId = (int)$_POST['order_id'];
        $newStatus = trim($_POST['status'] ?? 'pending');
        
        $valid = ['pending', 'processing', 'ready', 'delivered', 'cancelled'];
        if (in_array($newStatus, $valid)) {
            $stmt = $pdo->prepare("UPDATE print_orders SET status = :st WHERE id = :id");
            $stmt->execute([':st' => $newStatus, ':id' => $orderId]);
            setFlash('success', "Order status updated to " . ucfirst($newStatus));
        }
        header('Location: print_orders.php' . (isset($_GET['status']) ? '?status=' . urlencode($_GET['status']) : ''));
        exit;
    }

    // Delete order
    if ($action === 'delete_order') {
        $orderId = (int)$_POST['order_id'];
        // Fetch files to delete from disk
        $stmt = $pdo->prepare("SELECT files_json FROM print_orders WHERE id = :id");
        $stmt->execute([':id' => $orderId]);
        $row = $stmt->fetch();
        if ($row) {
            $files = json_decode($row['files_json'], true) ?: [];
            foreach ($files as $f) {
                $target = PRINTS_DIR . '/' . basename($f['stored_name']);
                if (file_exists($target)) {
                    @unlink($target);
                }
            }
            $pdo->prepare("DELETE FROM print_orders WHERE id = :id")->execute([':id' => $orderId]);
            setFlash('success', 'Print order and attached files deleted successfully.');
        }
        header('Location: print_orders.php');
        exit;
    }
}

// Filter by status tab
$selectedStatus = $_GET['status'] ?? 'all';
$searchQuery = trim($_GET['q'] ?? '');

$sql = "SELECT * FROM print_orders";
$params = [];
$where = [];

if ($selectedStatus !== 'all') {
    $where[] = "status = :status";
    $params[':status'] = $selectedStatus;
}
if (!empty($searchQuery)) {
    $where[] = "(token_id LIKE :q OR customer_name LIKE :q OR customer_phone LIKE :q)";
    $params[':q'] = "%$searchQuery%";
}
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}
$sql .= " ORDER BY id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Counts for tabs
$counts = [
    'all' => (int)$pdo->query("SELECT COUNT(*) FROM print_orders")->fetchColumn(),
    'pending' => (int)$pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'pending'")->fetchColumn(),
    'processing' => (int)$pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'processing'")->fetchColumn(),
    'ready' => (int)$pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'ready'")->fetchColumn(),
    'delivered' => (int)$pdo->query("SELECT COUNT(*) FROM print_orders WHERE status = 'delivered'")->fetchColumn(),
];

$centerName = getSetting('center_name', DEFAULT_CENTER_NAME, $pdo);
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Print Orders - <?= e($centerName) ?></title>
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
                <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Customer Document Print Requests</h1>
                <p class="text-slate-400 text-sm mt-1">Download citizen uploaded documents, manage print status, and notify customers.</p>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="p-4 rounded-xl <?= $flash['type'] === 'success' ? 'bg-emerald-950/70 border border-emerald-800 text-emerald-300' : 'bg-red-950/70 border border-red-800 text-red-300' ?> text-sm flex items-center gap-2">
                <i data-lucide="<?= $flash['type'] === 'success' ? 'check-circle' : 'alert-circle' ?>" class="w-5 h-5 shrink-0"></i>
                <span><?= e($flash['message']) ?></span>
            </div>
        <?php endif; ?>

        <!-- Filter Tabs & Search -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs font-bold">
                <a href="print_orders.php" class="px-3.5 py-2 rounded-xl transition whitespace-nowrap <?= $selectedStatus === 'all' ? 'bg-blue-600 text-white' : 'bg-slate-800 text-slate-300 hover:bg-slate-700' ?>">
                    All Orders (<?= $counts['all'] ?>)
                </a>
                <a href="print_orders.php?status=pending" class="px-3.5 py-2 rounded-xl transition whitespace-nowrap <?= $selectedStatus === 'pending' ? 'bg-amber-500 text-slate-900' : 'bg-slate-800 text-amber-400 hover:bg-slate-700' ?>">
                    Pending (<?= $counts['pending'] ?>)
                </a>
                <a href="print_orders.php?status=processing" class="px-3.5 py-2 rounded-xl transition whitespace-nowrap <?= $selectedStatus === 'processing' ? 'bg-blue-500 text-white' : 'bg-slate-800 text-blue-400 hover:bg-slate-700' ?>">
                    Printing (<?= $counts['processing'] ?>)
                </a>
                <a href="print_orders.php?status=ready" class="px-3.5 py-2 rounded-xl transition whitespace-nowrap <?= $selectedStatus === 'ready' ? 'bg-emerald-600 text-white' : 'bg-slate-800 text-emerald-400 hover:bg-slate-700' ?>">
                    Ready for Pickup (<?= $counts['ready'] ?>)
                </a>
                <a href="print_orders.php?status=delivered" class="px-3.5 py-2 rounded-xl transition whitespace-nowrap <?= $selectedStatus === 'delivered' ? 'bg-slate-600 text-white' : 'bg-slate-800 text-slate-400 hover:bg-slate-700' ?>">
                    Delivered (<?= $counts['delivered'] ?>)
                </a>
            </div>

            <form method="GET" class="relative w-full md:w-80">
                <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3.5 top-3"></i>
                <input type="text" name="q" value="<?= e($searchQuery) ?>" placeholder="Search Token ID, Name, Phone..."
                       class="w-full pl-9 pr-4 py-2 bg-slate-800 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <?php if ($selectedStatus !== 'all'): ?>
                    <input type="hidden" name="status" value="<?= e($selectedStatus) ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- Orders Grid / Cards -->
        <?php if (empty($orders)): ?>
            <div class="bg-slate-800 rounded-2xl p-12 text-center border border-slate-700 shadow-md">
                <i data-lucide="inbox" class="w-12 h-12 text-slate-500 mx-auto mb-3"></i>
                <h3 class="text-base font-bold text-slate-300">No Orders Found</h3>
                <p class="text-xs text-slate-500 mt-1">There are no orders matching this filter or search query.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($orders as $order): 
                    $files = json_decode($order['files_json'], true) ?: [];
                    $status = $order['status'];
                    $rawPhone = preg_replace('/[^0-9]/', '', $order['customer_phone']);
                    $cleanPhone = (strlen($rawPhone) === 10) ? '91' . $rawPhone : $rawPhone;
                    
                    $waText = urlencode("Namaste " . $order['customer_name'] . "! Your document print order (" . $order['token_id'] . ") is PRINTED and READY for pickup at " . $centerName . ". Please visit to collect your prints.");
                ?>
                    <div class="bg-slate-800 rounded-2xl border border-slate-700 p-5 sm:p-6 shadow-md hover:border-slate-600 transition space-y-4">
                        
                        <!-- Top Order Bar -->
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-700/60 pb-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-blue-500/10 text-blue-400 flex items-center justify-center font-bold">
                                    <i data-lucide="printer" class="w-5 h-5"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="font-extrabold text-white text-lg tracking-wide"><?= e($order['token_id']) ?></h3>
                                        <?php if ($order['email_sent']): ?>
                                            <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-400 bg-emerald-950/60 border border-emerald-800 px-2 py-0.5 rounded-full" title="Owner email notified">
                                                <i data-lucide="mail-check" class="w-3 h-3"></i> Email Dispatched
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">Submitted on <?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></p>
                                </div>
                            </div>

                            <!-- Status Dropdown / Action -->
                            <div class="flex items-center gap-2">
                                <form method="POST" class="flex items-center gap-2">
                                    <input type="hidden" name="action" value="update_status">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <select name="status" onchange="this.form.submit()" class="text-xs font-bold px-3 py-1.5 rounded-xl bg-slate-900 border border-slate-700 text-white focus:ring-2 focus:ring-blue-500 cursor-pointer">
                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>⏳ Pending</option>
                                        <option value="processing" <?= $status === 'processing' ? 'selected' : '' ?>>🖨️ Printing</option>
                                        <option value="ready" <?= $status === 'ready' ? 'selected' : '' ?>>✅ Ready for Pickup</option>
                                        <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>📦 Delivered / Closed</option>
                                        <option value="cancelled" <?= $status === 'cancelled' ? 'selected' : '' ?>>❌ Cancelled</option>
                                    </select>
                                </form>

                                <!-- WhatsApp Customer -->
                                <a href="https://wa.me/<?= $cleanPhone ?>?text=<?= $waText ?>" target="_blank" class="px-3 py-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition flex items-center gap-1.5 shadow-sm" title="Notify Customer via WhatsApp">
                                    <i data-lucide="message-circle" class="w-4 h-4"></i>
                                    <span class="hidden sm:inline">WhatsApp</span>
                                </a>

                                <!-- Delete -->
                                <form method="POST" onsubmit="return confirm('Do you want to permanently delete this order and its uploaded documents?');" class="inline-block">
                                    <input type="hidden" name="action" value="delete_order">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    <button type="submit" class="p-1.5 rounded-xl bg-slate-700 hover:bg-red-800 text-slate-400 hover:text-white transition" title="Delete Order">
                                        <i data-lucide="trash-2" class="w-4 h-4"></i>
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- Customer & Print Details -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4 text-xs bg-slate-900/60 p-4 rounded-xl border border-slate-700/60">
                            <div>
                                <span class="text-slate-400 font-semibold uppercase">Customer Details</span>
                                <div class="font-bold text-white text-sm mt-0.5"><?= e($order['customer_name']) ?></div>
                                <div class="text-blue-400 mt-0.5">
                                    <a href="tel:<?= e($order['customer_phone']) ?>" class="hover:underline"><?= e($order['customer_phone']) ?></a>
                                </div>
                                <?php if (!empty($order['customer_email'])): ?>
                                    <div class="text-slate-400 truncate"><?= e($order['customer_email']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div>
                                <span class="text-slate-400 font-semibold uppercase">Print Specifications</span>
                                <div class="font-bold text-white mt-0.5"><?= e($order['print_type']) ?> (<?= e($order['sides']) ?>)</div>
                                <div class="text-slate-300 mt-0.5"><?= e($order['paper_size']) ?> • <?= e($order['copies']) ?> Copy(ies)</div>
                            </div>

                            <div>
                                <span class="text-slate-400 font-semibold uppercase">Estimated Charges</span>
                                <div class="font-black text-emerald-400 text-base mt-0.5"><?= formatCurrency($order['estimated_amount']) ?></div>
                                <div class="text-slate-400 mt-0.5"><?= count($files) ?> Document(s) attached</div>
                            </div>

                            <div>
                                <span class="text-slate-400 font-semibold uppercase">Customer Notes</span>
                                <div class="text-slate-300 mt-0.5 italic">
                                    <?= !empty($order['notes']) ? nl2br(e($order['notes'])) : 'No special instructions.' ?>
                                </div>
                            </div>
                        </div>

                        <!-- Uploaded Files List with Direct Download -->
                        <div>
                            <span class="text-xs font-bold text-slate-300 uppercase tracking-wider mb-2 block">
                                Attached Documents to Print (<?= count($files) ?>)
                            </span>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php foreach ($files as $f): 
                                    $ext = strtolower(pathinfo($f['original_name'], PATHINFO_EXTENSION));
                                    $sizeKb = round(($f['size'] ?? 0) / 1024, 1);
                                    $downloadUrl = "download.php?file=" . urlencode($f['stored_name']) . "&name=" . urlencode($f['original_name']);
                                ?>
                                    <div class="flex items-center justify-between p-3 rounded-xl bg-slate-900 border border-slate-700/80 hover:border-blue-500/50 transition">
                                        <div class="flex items-center gap-2.5 truncate mr-2">
                                            <div class="w-8 h-8 rounded-lg bg-blue-500/10 text-blue-400 flex items-center justify-center shrink-0">
                                                <i data-lucide="file" class="w-4 h-4"></i>
                                            </div>
                                            <div class="truncate">
                                                <div class="text-xs font-bold text-white truncate" title="<?= e($f['original_name']) ?>"><?= e($f['original_name']) ?></div>
                                                <div class="text-[11px] text-slate-400"><?= $sizeKb ?> KB • <?= strtoupper($ext) ?></div>
                                            </div>
                                        </div>
                                        <a href="<?= $downloadUrl ?>" target="_blank" class="px-2.5 py-1.5 rounded-lg bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold flex items-center gap-1 transition shrink-0" title="Download & Print Document">
                                            <i data-lucide="download" class="w-3.5 h-3.5"></i>
                                            <span>Open</span>
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>
