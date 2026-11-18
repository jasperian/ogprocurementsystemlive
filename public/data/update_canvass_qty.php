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
$item_id = $_POST['item_id'] ?? null;
$qty = $_POST['qty'] ?? null;

if ($batch_id === null || $supplier_id === null || $item_id === null || $qty === null) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    $qty = (int) $qty;
    if ($qty < 1) $qty = 1;

    // Get the original qty
    $stmt = $pdo->prepare("SELECT qty, item_id FROM canvass_items WHERE batch_id = ? AND supplier_id = ? AND item_id = ?");
    $stmt->execute([$batch_id, $supplier_id, $item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Canvass item not found.']);
        exit;
    }

    $old_qty = $row['qty'];

    // Get item name
    $stmt = $pdo->prepare("SELECT item_name FROM items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $item_name = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        UPDATE canvass_items 
        SET qty = ?
        WHERE batch_id = ? AND supplier_id = ? AND item_id = ?
    ");
    $stmt->execute([$qty, $batch_id, $supplier_id, $item_id]);

    // Log to history
    $stmt = $pdo->prepare("INSERT INTO canvass_change_history (batch_id, supplier_id, user_id, action_type, old_value, new_value) VALUES (?, ?, ?, 'qty_update', ?, ?)");
    $log_msg_old = "Item: $item_name, Qty: " . $old_qty;
    $log_msg_new = "Item: $item_name, Qty: " . $qty;
    $stmt->execute([$batch_id, $supplier_id, $_SESSION['user_id'], $log_msg_old, $log_msg_new]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Quantity updated successfully.',
        'qty' => $qty
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
