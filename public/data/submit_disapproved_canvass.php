<?php
header('Content-Type: application/json');
include '../../includes/db.php'; // adjust path to your DB connection
session_start();

$user_id = $_SESSION['user_id'] ?? null;

try {
    // Check required fields
    if (!isset($_POST['prnumber'], $_POST['name'], $_POST['remarks'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields.'
        ]);
        exit;
    }

    $prnumber = $_POST['prnumber'];      // this corresponds to pr_number
    $remarks = $_POST['remarks'];  // rejected_remarks

    // Insert into canvass_rejected
    $sql = "INSERT INTO canvass_rejected (pr_number, rejected_date, rejected_by, rejected_remarks)
            VALUES (:pr_number, NOW(), :rejected_by, :remarks)";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':pr_number' => $prnumber,
        ':rejected_by' => $user_id,
        ':remarks' => $remarks
    ]);

    $stmt = $pdo->prepare("UPDATE purchase_requisition SET pr_status = 'Rejected' WHERE pr_number = ?");
    $stmt->execute([$prnumber]);

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
