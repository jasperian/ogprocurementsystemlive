<?php
include '../../includes/db.php';
session_start();

$dealer = $_SESSION['dealer'] ?? null;
$params = [];

$sql = "
SELECT 
    vendor_id, 
    supplier_name, 
    tin_number,
    accreditation_date, 
    DATEDIFF(DATE_ADD(accreditation_date, INTERVAL 2 YEAR), CURDATE()) AS days_remaining 
FROM suppliers s
WHERE DATEDIFF(DATE_ADD(accreditation_date, INTERVAL 2 YEAR), CURDATE()) <= 90
";

if (!empty($dealer) && $dealer !== 'OG') {
    $sql .= " AND s.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetchAll(PDO::FETCH_ASSOC); // fetch all

echo json_encode($result);