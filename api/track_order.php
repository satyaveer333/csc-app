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

$stmt = $pdo->prepare("
    SELECT token_id, customer_name, customer_phone, print_type, sides, paper_size, copies, status, estimated_amount, created_at, files_json
    FROM print_orders
    WHERE token_id = :q OR customer_phone = :q
    ORDER BY id DESC
    LIMIT 5
");
$stmt->execute([':q' => $query]);
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
        'customer_name' => $o['customer_name'],
        'print_type' => $o['print_type'],
        'sides' => $o['sides'],
        'paper_size' => $o['paper_size'],
        'copies' => $o['copies'],
        'status' => ucfirst($o['status']),
        'file_count' => count($files),
        'estimated_amount' => formatCurrency($o['estimated_amount']),
        'created_at' => date('d M Y, h:i A', strtotime($o['created_at']))
    ];
}

echo json_encode(['success' => true, 'orders' => $formatted]);
