<?php
include '../../includes/db.php';

$search     = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$dealer     = $_GET['dealer'] ?? '';
$user       = $_GET['user'] ?? '';

$where = "WHERE 1=1";
$params = [];

if (!empty($search)) {
    $where .= " AND (
        pr.pr_number LIKE :search OR
        s.supplier_name LIKE :search OR
        po.po_number LIKE :search OR
        u.full_name LIKE :search
    )";
    $params[':search'] = "%$search%";
}

if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND DATE(pr.pr_date) BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date'] = $end_date;
}

if (!empty($dealer)) {
    $where .= " AND u.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

if (!empty($user)) {
    $where .= " AND u.user_id = :user";
    $params[':user'] = $user;
}

$sql = "
    SELECT 
        pr.pr_number, 
        pr.pr_date, 
        s.supplier_name, 
        s.payment_terms,
        po.po_number, 
        po.po_date,
        po.amount_purchased,
        u.full_name,
        u.dealer
    FROM purchase_requisition pr
    INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
    INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    INNER JOIN users u ON ca.approver_1 = u.user_id
    $where
    GROUP BY pr.pr_number
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// if (!$rows) {
//     die("No data found.");
// }
if (!$rows) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'No data found.']);
    exit;
}

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=purchase_export.csv');

$output = fopen('php://output', 'w');
fputcsv($output, array_keys($rows[0]));

foreach ($rows as $row) {
    fputcsv($output, $row);
}

fclose($output);
exit;
