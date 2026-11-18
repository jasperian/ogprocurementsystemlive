<?php

header('Content-Type: application/json');
include '../../includes/db.php';
session_start();

$dealer = $_SESSION['dealer'] ?? '';
$search = $_POST['search'] ?? '';

$stmt = $pdo->prepare("SELECT pr_id, pr_number, pr_department FROM purchase_requisition WHERE pr_number LIKE ? AND pr_status = 'For Canvass' AND pr_dealer = ?");
$stmt->execute(["%$search%", $dealer]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['pr_number'],
        "text" => $row['pr_number'],
        "department" => $row['pr_department']
    ];
}

echo json_encode($results);
