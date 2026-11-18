<?php
include '../../includes/db.php';
session_start();

$params = [];
$where = "WHERE pr.pr_status = 'For Approval'"; // always initialize
$join = "LEFT JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id";

if (!empty($dealer) && $dealer !== 'OG') {
    $where .= " AND pr.dealer = :dealer"; // fixed alias
    $params[':dealer'] = $dealer;
}

$sql = "
SELECT
    COUNT(CASE WHEN ca.approver_5 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_5 IS NULL THEN 1 END) AS `VP for Accounting`,
    COUNT(CASE WHEN ca.approver_6 IS NOT NULL AND ca.checked_5 IS NOT NULL AND ca.checked_6 IS NULL THEN 1 END) AS `SVP for Finance and Admin`,
    COUNT(CASE WHEN ca.approver_7 IS NOT NULL AND ca.checked_6 IS NOT NULL AND ca.checked_7 IS NULL THEN 1 END) AS `President`,
    COUNT(CASE WHEN ca.approver_8 IS NOT NULL AND ca.checked_4 IS NOT NULL AND ca.checked_8 IS NULL THEN 1 END) AS `Vice Chairman`,
    COUNT(CASE WHEN ca.approver_8 IS NOT NULL AND ca.checked_7 IS NOT NULL AND ca.checked_8 IS NULL THEN 1 END) AS `Chairman`
FROM purchase_requisition pr
$join
$where
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode($result);
