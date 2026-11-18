<?php

header('Content-Type: application/json');
include '../../includes/db.php';
session_start();

$search = $_POST['search'] ?? '';

$stmt = $pdo->prepare("SELECT id, category_name FROM supplier_category WHERE category_name LIKE ?");
$stmt->execute(["%$search%"]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['category_name'],
        "text" => $row['category_name']
    ];
}

echo json_encode($results);
