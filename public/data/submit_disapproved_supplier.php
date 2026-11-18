<?php
header('Content-Type: application/json');
include '../../includes/db.php'; // adjust path to your DB connection
session_start();

$user_id = $_SESSION['user_id'] ?? null;

try {
    // Check required fields
    if (!isset($_POST['vendorid'], $_POST['name'], $_POST['remarks'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields.'
        ]);
        exit;
    }

    $vendorid = $_POST['vendorid'];      // this corresponds to pr_number
    $remarks = $_POST['remarks'];  // rejected_remarks

    // Insert into canvass_rejected
    $sql = "INSERT INTO supplier_rejected (vendor_id, rejected_date, rejected_by, rejected_remarks)
            VALUES (:vendor_id, NOW(), :rejected_by, :remarks)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':vendor_id' => $vendorid,
        ':rejected_by' => $user_id,
        ':remarks' => $remarks
    ]);

    $stmt = $pdo->prepare("UPDATE suppliers SET is_rejected = 1 WHERE vendor_id = ?");
    $stmt->execute([$vendorid]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Disapproval recorded successfully.'
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage()
    ]);
}
