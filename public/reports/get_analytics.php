<?php
include '../../includes/db.php';

// Setup DataTables parameters
$draw   = $_GET['draw'] ?? 1;
$start  = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 1000; // ✅ default small limit for performance
$search = $_GET['search']['value'] ?? '';

$start_date = $_GET['start_date'] ?? '';
$end_date   = $_GET['end_date'] ?? '';
$dealer     = $_GET['dealer'] ?? '';
$user       = $_GET['user'] ?? '';

$where = "WHERE 1=1";
$params = [];

// 🔹 Search filter
if (!empty($search)) {
    $where .= " AND (
        pr.pr_number LIKE :search OR
        s.supplier_name LIKE :search OR
        po.po_number LIKE :search OR
        u.full_name LIKE :search
    )";
    $params[':search'] = "%$search%";
}

// 🔹 Date range filter (pr_date)
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

// ------------------------------------------------------
// ⚡ Single query using SQL_CALC_FOUND_ROWS
$sql = "
    SELECT SQL_CALC_FOUND_ROWS
        DISTINCT pr.pr_number, 
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
    ORDER BY pr.pr_date DESC
    LIMIT :start, :length
";

$stmt = $pdo->prepare($sql);

// Bind dynamic params
foreach ($params as $key => $val) {
    $stmt->bindValue($key, $val);
}
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ------------------------------------------------------
// ⚡ Get filtered count and total count in one go
$totalFiltered = $pdo->query("SELECT FOUND_ROWS()")->fetchColumn();

// ⚡ Get total record count (cached, simplified)
$totalRecords = $pdo->query("
    SELECT COUNT(*) 
    FROM purchase_requisition
")->fetchColumn();

// ------------------------------------------------------
// Return JSON response
echo json_encode([
    "draw"             => intval($draw),
    "recordsTotal"     => intval($totalRecords),
    "recordsFiltered"  => intval($totalFiltered),
    "data"             => $data,
    "filtered_from"    => $start_date ?: null,
    "filtered_to"      => $end_date ?: null
]);
