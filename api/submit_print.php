<?php
/**
 * API Endpoint: Submit Document Print Request
 * Handles multi-file upload, database persistence, and email dispatch to Owner
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/mailer.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

ensureDirectories();
$pdo = getDbConnection();

// Validate Customer Inputs
$customerName = trim($_POST['customer_name'] ?? '');
$customerPhone = trim($_POST['customer_phone'] ?? '');
$customerEmail = trim($_POST['customer_email'] ?? '');
$printType = trim($_POST['print_type'] ?? 'B&W');
$sides = trim($_POST['sides'] ?? 'Single Sided');
$paperSize = trim($_POST['paper_size'] ?? 'A4');
$copies = max(1, intval($_POST['copies'] ?? 1));
$notes = trim($_POST['notes'] ?? '');

if (empty($customerName) || empty($customerPhone)) {
    echo json_encode(['success' => false, 'message' => 'Please provide your Full Name and Mobile Number.']);
    exit;
}

// Check if files were uploaded
if (empty($_FILES['documents']) || !is_array($_FILES['documents']['name'])) {
    echo json_encode(['success' => false, 'message' => 'Please select at least one document to print.']);
    exit;
}

$uploadedFiles = [];
$totalFiles = count($_FILES['documents']['name']);
$allowedExts = ALLOWED_EXTENSIONS;

for ($i = 0; $i < $totalFiles; $i++) {
    $fileName = $_FILES['documents']['name'][$i];
    $fileTmp  = $_FILES['documents']['tmp_name'][$i];
    $fileSize = $_FILES['documents']['size'][$i];
    $fileErr  = $_FILES['documents']['error'][$i];

    if ($fileErr === UPLOAD_ERR_NO_FILE || empty($fileName)) {
        continue;
    }

    if ($fileErr !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => "Error uploading file '{$fileName}'. Error code: {$fileErr}"]);
        exit;
    }

    if ($fileSize > MAX_FILE_SIZE) {
        echo json_encode(['success' => false, 'message' => "File '{$fileName}' exceeds the maximum allowed size (25MB)."]);
        exit;
    }

    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        echo json_encode(['success' => false, 'message' => "File format '.{$ext}' is not supported. Allowed formats: " . implode(', ', $allowedExts)]);
        exit;
    }

    // Generate safe unique filename
    $safeName = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $targetPath = PRINTS_DIR . '/' . $safeName;

    if (!move_uploaded_file($fileTmp, $targetPath)) {
        echo json_encode(['success' => false, 'message' => "Could not save uploaded document '{$fileName}'. Please check permissions."]);
        exit;
    }

    $uploadedFiles[] = [
        'original_name' => $fileName,
        'stored_name' => $safeName,
        'size' => $fileSize,
        'extension' => $ext,
        'uploaded_at' => date('Y-m-d H:i:s')
    ];
}

if (empty($uploadedFiles)) {
    echo json_encode(['success' => false, 'message' => 'No valid files were uploaded. Please try again.']);
    exit;
}

// Calculate Estimated Price
$rateBwSingle = (float)getSetting('rate_bw_single', '3.00', $pdo);
$rateBwDouble = (float)getSetting('rate_bw_double', '5.00', $pdo);
$rateColorSingle = (float)getSetting('rate_color_single', '10.00', $pdo);
$rateColorDouble = (float)getSetting('rate_color_double', '18.00', $pdo);

$perFileBaseRate = $rateBwSingle;
if ($printType === 'Color') {
    $perFileBaseRate = ($sides === 'Both Sided (Duplex)') ? $rateColorDouble : $rateColorSingle;
} else {
    $perFileBaseRate = ($sides === 'Both Sided (Duplex)') ? $rateBwDouble : $rateBwSingle;
}
$estimatedAmount = count($uploadedFiles) * $perFileBaseRate * $copies;

$tokenId = generateTokenId();

// Insert into Database
try {
    $stmt = $pdo->prepare("
        INSERT INTO print_orders (
            token_id, customer_name, customer_phone, customer_email, 
            print_type, sides, paper_size, copies, notes, 
            files_json, estimated_amount, status, email_sent, created_at
        ) VALUES (
            :token_id, :customer_name, :customer_phone, :customer_email, 
            :print_type, :sides, :paper_size, :copies, :notes, 
            :files_json, :estimated_amount, 'pending', 0, CURRENT_TIMESTAMP
        )
    ");

    $stmt->execute([
        ':token_id' => $tokenId,
        ':customer_name' => $customerName,
        ':customer_phone' => $customerPhone,
        ':customer_email' => $customerEmail,
        ':print_type' => $printType,
        ':sides' => $sides,
        ':paper_size' => $paperSize,
        ':copies' => $copies,
        ':notes' => $notes,
        ':files_json' => json_encode($uploadedFiles),
        ':estimated_amount' => $estimatedAmount
    ]);

    $orderId = $pdo->lastInsertId();

    // Prepare order array for email
    $orderData = [
        'id' => $orderId,
        'token_id' => $tokenId,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'customer_email' => $customerEmail,
        'print_type' => $printType,
        'sides' => $sides,
        'paper_size' => $paperSize,
        'copies' => $copies,
        'notes' => $notes,
        'estimated_amount' => $estimatedAmount
    ];

    // Dispatch email to CSC Owner
    $emailSent = sendPrintOrderEmail($orderData, $uploadedFiles, $pdo);

    echo json_encode([
        'success' => true,
        'message' => 'Document submitted successfully!',
        'token_id' => $tokenId,
        'customer_name' => $customerName,
        'customer_phone' => $customerPhone,
        'file_count' => count($uploadedFiles),
        'estimated_amount' => number_format($estimatedAmount, 2),
        'email_sent' => $emailSent
    ]);
} catch (Exception $e) {
    error_log("Order Submission Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to save order. Error: ' . $e->getMessage()]);
}
