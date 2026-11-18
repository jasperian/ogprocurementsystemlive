<?php
require '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

if (!isset($_SESSION['user_id']) || $_SESSION['position'] !== 'Purchasing Staff') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized action.']);
    exit;
}

$batch_id = $_POST['batch_id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;
$overall_discount = $_POST['overall_discount'] ?? null;

if ($batch_id === null || $supplier_id === null || $overall_discount === null) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    $overall_discount = (float) $overall_discount;
    if ($overall_discount < 0) $overall_discount = 0;

    // Check if record exists in canvass_quotation
    $stmt = $pdo->prepare("SELECT overall_discount FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
    $stmt->execute([$batch_id, $supplier_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    $old_discount = $row ? $row['overall_discount'] : 0;

    if ($row) {
        $stmt = $pdo->prepare("UPDATE canvass_quotation SET overall_discount = ? WHERE batch_id = ? AND supplier_id = ?");
        $stmt->execute([$overall_discount, $batch_id, $supplier_id]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO canvass_quotation (batch_id, supplier_id, overall_discount) VALUES (?, ?, ?)");
        $stmt->execute([$batch_id, $supplier_id, $overall_discount]);
    }

    // Log to history
    $stmt = $pdo->prepare("INSERT INTO canvass_change_history (batch_id, supplier_id, user_id, action_type, old_value, new_value) VALUES (?, ?, ?, 'overall_discount_update', ?, ?)");
    $log_msg_old = "Overall Deduction: ₱" . number_format($old_discount, 2);
    $log_msg_new = "Overall Deduction: ₱" . number_format($overall_discount, 2);
    $stmt->execute([$batch_id, $supplier_id, $_SESSION['user_id'], $log_msg_old, $log_msg_new]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Overall discount updated successfully.',
        'overall_discount' => $overall_discount
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
