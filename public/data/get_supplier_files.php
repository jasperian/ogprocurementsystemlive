<?php
header('Content-Type: application/json');
include '../../includes/db.php';

$vendorId = $_GET['vendor_id'] ?? '';

if (!$vendorId) {
    echo json_encode(['status' => 'error', 'message' => 'Missing vendor_id']);
    exit;
}

// JOIN suppliers to get business_type
$stmt = $pdo->prepare("
    SELECT 
        sp.*, 
        s.business_type 
    FROM 
        supplier_profile AS sp
    INNER JOIN 
        suppliers AS s ON sp.vendor_id = s.vendor_id
    WHERE 
        sp.vendor_id = ?
");

$stmt->execute([$vendorId]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if ($data) {
    echo json_encode([
        'status' => 'success',
        'files' => $data,
        'business_type' => $data['business_type']
    ]);
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'No records found for this vendor.'
    ]);
}
