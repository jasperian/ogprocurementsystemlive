<?php

header('Content-Type: application/json');
include '../../includes/db.php';
session_start();

$search = $_POST['search'] ?? '';

$stmt = $pdo->prepare("SELECT id, business_type FROM supplier_type WHERE business_type LIKE ?");
$stmt->execute(["%$search%"]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['business_type'],
        "text" => $row['business_type']
    ];
}

echo json_encode($results);
