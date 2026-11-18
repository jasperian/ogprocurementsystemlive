<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';

$response = ['status' => 'danger', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$ca_number = trim($_POST['ca_number'] ?? '');
$po_amount = trim($_POST['po_amount'] ?? '');
$liquidation_remarks = trim($_POST['liquidation_remarks'] ?? '');

if (!$ca_number || !isset($_FILES['ca_file'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Missing CA number or file.']);
    exit;
}

if (strlen($liquidation_remarks) > 2000) {
    echo json_encode(['status' => 'danger', 'message' => 'Liquidation remarks must be 2000 characters or less.']);
    exit;
}

$file = $_FILES['ca_file'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'danger', 'message' => 'Upload failed. Please choose a valid file.']);
    exit;
}

# Validate File Size (5MB)
if ($file['size'] > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'danger', 'message' => 'File must be below 5MB']);
    exit;
}

# Validate MIME and Extension
$mime = mime_content_type($file['tmp_name']);
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png'
];

if (!array_key_exists($mime, $allowedMimes)) {
    echo json_encode(['status' => 'danger', 'message' => 'Only PDF, JPG, and PNG files allowed']);
    exit;
}

$extension = $allowedMimes[$mime];

# Upload Directory
$uploadDir = __DIR__ . '/../../uploads/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

# Generate Safe Filename
$fileName = 'CA_UPD_' . $ca_number . '_' . time() . '.' . $extension;
$destPath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    echo json_encode(['status' => 'danger', 'message' => 'Upload failed']);
    exit;
}

# Path to store in database
$dbFilePath = 'uploads/' . $fileName;

try {
    // Check current status to decide which column to update
    $stmt = $pdo->prepare("SELECT pr_status FROM cash_advance WHERE pr_number = ?");
    $stmt->execute([$ca_number]);
    $ca = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ca) {
        echo json_encode(['status' => 'danger', 'message' => 'Cash Advance not found.']);
        exit;
    }

    $column = in_array($ca['pr_status'], ['For PO', 'For Liquidation', 'Liquidated', 'For GM Approval', 'For SAP Document', 'For Accounting Approval'], true) ? 'po_file' : 'pr_file';

    if ($column === 'po_file' && $ca['pr_status'] === 'For PO') {
        if ($po_amount === '' || !is_numeric($po_amount) || (float)$po_amount <= 0) {
            echo json_encode(['status' => 'danger', 'message' => 'Actual liquidation amount is required and must be greater than zero.']);
            exit;
        }
    }

    $hasLiquidationRemarks = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'liquidation_remarks'")->fetch(PDO::FETCH_ASSOC);

    // Update the record with the new file path
    if ($column === 'po_file' && $po_amount !== '' && is_numeric($po_amount)) {
        if ($hasLiquidationRemarks) {
            $stmt = $pdo->prepare("UPDATE cash_advance SET $column = ?, po_amount = ?, liquidation_remarks = ? WHERE pr_number = ?");
            $stmt->execute([$dbFilePath, $po_amount, $liquidation_remarks !== '' ? $liquidation_remarks : null, $ca_number]);
        } else {
            $stmt = $pdo->prepare("UPDATE cash_advance SET $column = ?, po_amount = ? WHERE pr_number = ?");
            $stmt->execute([$dbFilePath, $po_amount, $ca_number]);
        }
    } else {
        $stmt = $pdo->prepare("UPDATE cash_advance SET $column = ? WHERE pr_number = ?");
        $stmt->execute([$dbFilePath, $ca_number]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'File uploaded successfully as ' . ($column === 'po_file' ? 'Liquidation Attachment' : 'Request File'),
        'file_path' => $dbFilePath
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
