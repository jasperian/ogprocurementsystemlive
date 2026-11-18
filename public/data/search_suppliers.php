<?php
include '../../includes/db.php';

$search = $_POST['search'] ?? '';
$stmt = $pdo->prepare("SELECT supplier_id, supplier_name FROM suppliers WHERE supplier_name LIKE ? AND is_approved = 1 AND is_active = 1");
$stmt->execute(["%$search%"]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['supplier_id'],
        "text" => $row['supplier_name']
    ];
}

echo json_encode($results);
