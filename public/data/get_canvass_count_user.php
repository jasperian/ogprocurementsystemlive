<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

ini_set('display_errors', 1);
error_reporting(E_ALL);

$dealer  = $_SESSION['dealer'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;

$params = [
    ':user_id' => $user_id
];

$where = "WHERE pr.pr_status = 'For Approval'";
$join  = "LEFT JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id";

if (!empty($dealer) && $dealer !== 'OG') {
    $where .= " AND pr.pr_dealer = :dealer";
    $params[':dealer'] = $dealer;
}

$sql = "
SELECT COUNT(*) AS total_pending
FROM purchase_requisition pr
$join
$where
AND (
    (ca.approver_1 = :user_id AND ca.checked_1 IS NULL)
 OR (ca.approver_2 = :user_id AND ca.checked_1 IS NOT NULL AND ca.checked_2 IS NULL)
 OR (ca.approver_3 = :user_id AND ca.checked_2 IS NOT NULL AND ca.checked_3 IS NULL)
 OR (ca.approver_4 = :user_id AND ca.checked_3 IS NOT NULL AND ca.checked_4 IS NULL)
)
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'total' => $result['total_pending'] ?? 0
]);
?>