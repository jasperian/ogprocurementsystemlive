<?php 
include '../../includes/db.php';

if (isset($_GET['vendor_id'])) {
    $vendor_id = $_GET['vendor_id'];

    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE vendor_id = ?");
    $stmt->execute([$vendor_id]);
    $vendor = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($vendor) {
        echo json_encode(['status' => 'success', 'data' => $vendor]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Vendor not found']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No vendor_id provided']);
}

?>