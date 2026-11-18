<?php
include '../../includes/db.php';

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$dealer     = $_GET['dealer'] ?? '';
$user       = $_GET['user'] ?? '';
$chart_type = $_GET['chart_type'] ?? 'date'; // 'date' or 'dealer'

$where = "WHERE 1=1";
$params = [];

// 🔹 Date range filter
if (!empty($start_date) && !empty($end_date)) {
    $where .= " AND pr.pr_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $start_date;
    $params[':end_date']   = $end_date;
} elseif (!empty($start_date)) {
    $where .= " AND pr.pr_date >= :start_date";
    $params[':start_date'] = $start_date;
} elseif (!empty($end_date)) {
    $where .= " AND pr.pr_date <= :end_date";
    $params[':end_date'] = $end_date;
}

// 🔹 Dealer filter
if (!empty($dealer)) {
    $where .= " AND u.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

// 🔹 User filter
if (!empty($user)) {
    $where .= " AND u.user_id = :user";
    $params[':user'] = $user;
}

// 🔹 Chart query
if ($chart_type === 'dealer') {
    $sql = "
        SELECT 
            u.dealer AS label,
            SUM(po.amount_purchased) AS total
        FROM purchase_requisition pr
        INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
        INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
        INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
        INNER JOIN users u ON ca.approver_1 = u.user_id
        $where
        GROUP BY u.dealer
        ORDER BY total DESC
    ";
} else { // Default: by date
    $sql = "
        SELECT 
            DATE(pr.pr_date) AS label,
            SUM(po.amount_purchased) AS total
        FROM purchase_requisition pr
        INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
        INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
        INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
        INNER JOIN users u ON ca.approver_1 = u.user_id
        $where
        GROUP BY DATE(pr.pr_date)
        ORDER BY pr.pr_date ASC
    ";
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 🔹 Count total PRs
$sql_total_pr = "
    SELECT COUNT(DISTINCT pr.pr_number) AS total_pr
    FROM purchase_requisition pr
    INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
    INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    INNER JOIN users u ON ca.approver_1 = u.user_id
    $where
";
$stmt_pr = $pdo->prepare($sql_total_pr);
$stmt_pr->execute($params);
$total_pr = $stmt_pr->fetch(PDO::FETCH_ASSOC)['total_pr'] ?? 0;

// 🔹 Count total Suppliers
$sql_total_suppliers = "
    SELECT COUNT(DISTINCT s.supplier_id) AS total_suppliers
    FROM purchase_requisition pr
    INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
    INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    INNER JOIN users u ON ca.approver_1 = u.user_id
    $where
";
$stmt_sup = $pdo->prepare($sql_total_suppliers);
$stmt_sup->execute($params);
$total_suppliers = $stmt_sup->fetch(PDO::FETCH_ASSOC)['total_suppliers'] ?? 0;

// 🔹 Total Amount Purchased
$sql_total_amount = "
    SELECT SUM(po.amount_purchased) AS total_amount
    FROM purchase_requisition pr
    INNER JOIN purchase_order po ON pr.pr_number = po.pr_number
    INNER JOIN suppliers s ON po.supplier_id = s.supplier_id
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    INNER JOIN users u ON ca.approver_1 = u.user_id
    $where
";
$stmt_amt = $pdo->prepare($sql_total_amount);
$stmt_amt->execute($params);
$total_amount = $stmt_amt->fetch(PDO::FETCH_ASSOC)['total_amount'] ?? 0;

// 🔹 Return JSON response
echo json_encode([
    'chartData' => $data,
    'summary' => [
        'total_pr' => $total_pr,
        'total_suppliers' => $total_suppliers,
        'total_amount' => $total_amount
    ]
]);
