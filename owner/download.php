<?php
/**
 * Secure Document Download Handler for CSC Owner
 */

define('CSC_APP', true);
require_once dirname(__DIR__) . '/includes/config.php';
require_once dirname(__DIR__) . '/includes/db.php';
require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/auth.php';

requireOwnerLogin();

$file = basename($_GET['file'] ?? '');
if (empty($file)) {
    die('Invalid file request.');
}

$filePath = PRINTS_DIR . '/' . $file;
if (!file_exists($filePath)) {
    die('File not found or has been removed.');
}

$ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
$mimeMap = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'txt' => 'text/plain',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$mime = $mimeMap[$ext] ?? 'application/octet-stream';
$downloadName = $_GET['name'] ?? $file;

header('Content-Type: ' . $mime);
header('Content-Disposition: inline; filename="' . basename($downloadName) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($filePath);
exit;
