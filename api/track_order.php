<?php
/**
 * API Endpoint: Track Print Order Status for Customer
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';

header('Content-Type: application/json');

$query = trim($_GET['q'] ?? '');
if (empty($query)) {
    echo json_encode(['success' => false, 'message' => 'Please provide a Token ID or Mobile Number.']);
    exit;
}

$pdo = getDbConnection();

$rawDigits = preg_replace('/[^0-9]/', '', $query);
$upperQuery = strtoupper($query);
$phoneSuffix = strlen($rawDigits) >= 10 ? substr($rawDigits, -10) : $rawDigits;

$stmt = $pdo->prepare("
    SELECT token_id, customer_name, customer_phone, print_type, sides, paper_size, copies, status, estimated_amount, created_at, files_json
    FROM print_orders
    WHERE UPPER(token_id) = :upperQ 
       OR customer_phone = :q 
       OR (LENGTH(:digits) >= 10 AND REPLACE(customer_phone, ' ', '') LIKE :likePhone)
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute([
    ':upperQ' => $upperQuery,
    ':q' => $query,
    ':digits' => $rawDigits,
    ':likePhone' => '%' . $phoneSuffix
]);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    echo json_encode(['success' => false, 'message' => 'No print orders found with the provided details.']);
    exit;
}

$formatted = [];
foreach ($orders as $o) {
    $files = json_decode($o['files_json'], true) ?: [];
    $formatted[] = [
        'token_id' => $o['token_id'],
        'customer_name' => htmlspecialchars($o['customer_name'], ENT_QUOTES, 'UTF-8'),
        'print_type' => htmlspecialchars($o['print_type'], ENT_QUOTES, 'UTF-8'),
        'sides' => htmlspecialchars($o['sides'], ENT_QUOTES, 'UTF-8'),
        'paper_size' => htmlspecialchars($o['paper_size'], ENT_QUOTES, 'UTF-8'),
        'copies' => (int)$o['copies'],
        'status' => ucfirst(htmlspecialchars($o['status'], ENT_QUOTES, 'UTF-8')),
        'file_count' => count($files),
        'estimated_amount' => formatCurrency($o['estimated_amount']),
        'created_at' => date('d M Y, h:i A', strtotime($o['created_at']))
    ];
}

echo json_encode(['success' => true, 'orders' => $formatted]);
