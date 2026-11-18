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
$discount = $_POST['discount'] ?? null;

if ($batch_id === null || $supplier_id === null || $item_id === null || $discount === null) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    $discount = (float) $discount;

    // Get the original unit price
    $stmt = $pdo->prepare("SELECT unit_price, discounted_unit_price, item_id FROM canvass_items WHERE batch_id = ? AND supplier_id = ? AND item_id = ?");
    $stmt->execute([$batch_id, $supplier_id, $item_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo json_encode(['status' => 'error', 'message' => 'Canvass item not found.']);
        exit;
    }

    $price = $row['unit_price'];
    $old_discounted_unit_price = $row['discounted_unit_price'];

    // Get item name
    $stmt = $pdo->prepare("SELECT item_name FROM items WHERE item_id = ?");
    $stmt->execute([$item_id]);
    $item_name = $stmt->fetchColumn();

    $discounted_unit_price = $price - ($price * ($discount / 100));
    $discounted_unit_price = round($discounted_unit_price, 2);

    $stmt = $pdo->prepare("
        UPDATE canvass_items 
        SET discounted_unit_price = ? 
        WHERE batch_id = ? AND supplier_id = ? AND item_id = ?
    ");
    $stmt->execute([$discounted_unit_price, $batch_id, $supplier_id, $item_id]);

    // Log to history
    $stmt = $pdo->prepare("INSERT INTO canvass_change_history (batch_id, supplier_id, user_id, action_type, old_value, new_value) VALUES (?, ?, ?, 'discount_update', ?, ?)");
    $log_msg_old = "Item: $item_name, Price: ₱" . number_format($old_discounted_unit_price, 2);
    $log_msg_new = "Item: $item_name, Price: ₱" . number_format($discounted_unit_price, 2);
    $stmt->execute([$batch_id, $supplier_id, $_SESSION['user_id'], $log_msg_old, $log_msg_new]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Discount updated successfully.',
        'discounted_unit_price' => $discounted_unit_price
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
