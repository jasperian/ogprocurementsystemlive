<?php
include '../../includes/db.php';

$id = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT item_id, item_name, description, unit_price, unit_measure, product_image 
                       FROM items 
                       WHERE supplier_id = ?");
$stmt->execute([$id]);
echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
