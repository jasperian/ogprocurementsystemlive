<?php
include '../../includes/db.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

$dealer = $_SESSION['dealer'] ?? '';
$params = [];

$sql = "
    SELECT s.vendor_id
    FROM supplier_profile sp
    INNER JOIN suppliers s ON sp.vendor_id = s.vendor_id
    WHERE sp.memorandum_expiration IS NOT NULL
      AND DATEDIFF(sp.memorandum_expiration, CURDATE()) <= 90
";

if (!empty($dealer) && $dealer !== 'OG') {
    $sql .= " AND s.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
