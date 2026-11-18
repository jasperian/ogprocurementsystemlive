<?php
$response = [];
include '../../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorNumber = $_POST['vendorNumber'];
    $today = date('Y-m-d');

    $stmt = $pdo->prepare("UPDATE suppliers SET is_approved = '1', is_active = '1', accreditation_date = ? WHERE vendor_id = ?");
    $stmt->execute([$today, $vendorNumber]);

    $response = [
        'status' => 'success',
        'message' => 'Supplier added successfully!'
    ];
} else {
    $response = [
        'status' => 'error',
        'message' => 'Invalid request'
    ];
}

header('Content-Type: application/json');
echo json_encode($response);
