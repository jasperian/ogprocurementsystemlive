<?php
include '../../includes/db.php';
session_start();

$params = [];
$where = "WHERE s.is_approved = 0";
$join = "INNER JOIN supplier_approvals sa ON s.vendor_id = sa.vendor_id";

if (!empty($dealer) && $dealer !== 'OG') {
    $where .= " AND s.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

$sql = "
SELECT
    COUNT(CASE WHEN sa.checked_1 IS NULL THEN 1 END) AS `Manager`,
    COUNT(CASE WHEN sa.checked_1 IS NOT NULL AND sa.checked_2 IS NULL THEN 1 END) AS `VP for Accounting`,
    COUNT(CASE WHEN sa.checked_2 IS NOT NULL AND sa.checked_1 IS NOT NULL AND sa.checked_3 IS NULL THEN 1 END) AS `SVP for Finance and Admin`
FROM suppliers s
$join
$where
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($result);
