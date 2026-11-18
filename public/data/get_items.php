<?php
include '../../includes/db.php';

if (!isset($_GET['supplier_id']) || !is_numeric($_GET['supplier_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid supplier ID']);
    exit;
}

$vendor_id = $_GET['supplier_id'];

try {
    $stmt = $pdo->prepare("SELECT item_id, item_name, unit_price, description, product_image, unit_measure FROM items WHERE supplier_id = ? ORDER BY item_name ASC");
    $stmt->execute([$vendor_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header('Content-Type: application/json');
    echo json_encode($items);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
