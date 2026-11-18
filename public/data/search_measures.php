<?php
header('Content-Type: application/json');
include '../../includes/db.php';

$search = $_POST['search'] ?? '';

$stmt = $pdo->prepare("
    SELECT id, unit_code, description 
    FROM units_of_measure
    WHERE (unit_code LIKE ? OR description LIKE ?)
    ORDER BY unit_code ASC
");
$stmt->execute(["%$search%", "%$search%"]);

$results = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $results[] = [
        "id" => $row['unit_code'],
        "text" => "(" . $row['unit_code'] . ") " . $row['description']
    ];
}

echo json_encode($results);
