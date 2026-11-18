<?php
header('Content-Type: application/json');
include '../../includes/db.php';

session_start();

$dealer = $_SESSION['dealer'] ?? null;

$stmt = $pdo->prepare("
    SELECT user_id, department 
    FROM users 
    WHERE department IN ('HINO', 'PFFC', 'AUTOQUIX')
      AND dealer = ?
      AND is_depthead = 1
    ORDER BY department
");

$stmt->execute([$dealer]);

$results = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['user_id'],
        "text" => $row['department']
    ];
}

echo json_encode($results);


