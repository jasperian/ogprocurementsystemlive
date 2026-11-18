<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'draw' => intval($_GET['draw'] ?? 0),
        'recordsTotal' => 0,
        'recordsFiltered' => 0,
        'data' => []
    ]);
    exit;
}

$draw = intval($_GET['draw'] ?? 0);
$start = max(0, intval($_GET['start'] ?? 0));
$length = intval($_GET['length'] ?? 10);
$search = trim($_GET['search']['value'] ?? '');
$dealer = trim($_GET['dealer'] ?? '');
$supplierId = intval($_GET['supplier_id'] ?? 0);

if ($length < 1) {
    $length = 10;
}

$columns = [
    'po.po_dealer',
    'po.po_number',
    'po.pr_number',
    's.supplier_name',
    'i.item_name',
    'ci.qty',
    'i.unit_measure',
    'effective_unit_price',
    'line_total',
    'po.po_date'
];

$from = "
    FROM purchase_order po
    INNER JOIN purchase_requisition pr
        ON pr.pr_number = po.pr_number
    INNER JOIN canvass_sheet_batches csb
        ON csb.pr_number = pr.pr_number
    INNER JOIN canvass_items ci
        ON ci.batch_id = csb.batch_id
        AND ci.supplier_id = po.supplier_id
    INNER JOIN items i
        ON i.item_id = ci.item_id
    LEFT JOIN suppliers s
        ON s.supplier_id = po.supplier_id
";

$where = " WHERE pr.pr_status = 'Completed' ";
$params = [];

if ($dealer !== '') {
    $where .= " AND po.po_dealer = :dealer";
    $params[':dealer'] = $dealer;
}

if ($supplierId > 0) {
    $where .= " AND po.supplier_id = :supplier_id";
    $params[':supplier_id'] = $supplierId;
}

if ($search !== '') {
    $where .= " AND (
        po.po_dealer LIKE :search
        OR po.po_number LIKE :search
        OR po.pr_number LIKE :search
        OR s.supplier_name LIKE :search
        OR i.item_name LIKE :search
        OR i.description LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

$totalSql = "
    SELECT COUNT(*)
    {$from}
    WHERE pr.pr_status = 'Completed'
";
$recordsTotal = (int) $pdo->query($totalSql)->fetchColumn();

$filteredSql = "SELECT COUNT(*) {$from} {$where}";
$stmt = $pdo->prepare($filteredSql);
foreach ($params as $key => $value) {
    $type = $key === ':supplier_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $type);
}
$stmt->execute();
$recordsFiltered = (int) $stmt->fetchColumn();

$orderColumnIndex = intval($_GET['order'][0]['column'] ?? 9);
$orderColumn = $columns[$orderColumnIndex] ?? 'po.po_date';
$orderDirection = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';

$dataSql = "
    SELECT
        po.po_id,
        po.po_dealer,
        po.po_number,
        po.pr_number,
        po.po_date,
        po.po_file,
        pr.pr_department,
        s.supplier_id,
        COALESCE(s.supplier_name, 'N/A') AS supplier_name,
        i.item_id,
        i.item_name,
        i.description,
        i.unit_measure,
        ci.qty,
        ci.unit_price,
        ci.discounted_unit_price,
        COALESCE(NULLIF(ci.discounted_unit_price, 0), ci.unit_price) AS effective_unit_price,
        (ci.qty * COALESCE(NULLIF(ci.discounted_unit_price, 0), ci.unit_price)) AS line_total
    {$from}
    {$where}
    ORDER BY {$orderColumn} {$orderDirection}, po.po_id DESC, i.item_name ASC
    LIMIT :start, :length
";

$stmt = $pdo->prepare($dataSql);
foreach ($params as $key => $value) {
    $type = $key === ':supplier_id' ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key, $value, $type);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $recordsTotal,
    'recordsFiltered' => $recordsFiltered,
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
]);
