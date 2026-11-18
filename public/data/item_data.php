<?php
include '../../includes/db.php';

// FOR ITEMS.PHP
header('Content-Type: application/json');

$draw = (int)($_GET['draw'] ?? 0);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 10);
$length = $length > 0 ? min($length, 100) : 10;
$search = trim($_GET['search']['value'] ?? '');
$itemCategory = trim($_GET['item_category'] ?? '');
$subCategory = trim($_GET['sub_category'] ?? '');

$columns = [
    0 => 's.supplier_name',
    1 => 'i.item_name',
    2 => 'i.description',
    3 => 'i.item_category',
    4 => 'i.sub_category',
    5 => 'i.unit_price',
];

$orderColumnIndex = (int)($_GET['order'][0]['column'] ?? -1);
$orderDirection = strtolower($_GET['order'][0]['dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$orderColumn = $columns[$orderColumnIndex] ?? 'i.item_id';

$whereParts = [];
$params = [];

if ($search !== '') {
    $whereParts[] = "(
        i.item_name LIKE :search
        OR i.description LIKE :search
        OR i.item_category LIKE :search
        OR i.product_category LIKE :search
        OR i.sub_category LIKE :search
        OR i.unit_measure LIKE :search
        OR s.supplier_name LIKE :search
    )";
    $params[':search'] = "%{$search}%";
}

if ($itemCategory !== '') {
    $whereParts[] = 'i.item_category = :item_category';
    $params[':item_category'] = $itemCategory;
}

if ($subCategory !== '') {
    $whereParts[] = 'i.sub_category = :sub_category';
    $params[':sub_category'] = $subCategory;
}

$where = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';

$totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM items")->fetchColumn();

$stmtFiltered = $pdo->prepare("
    SELECT COUNT(*)
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    $where
");
$stmtFiltered->execute($params);
$totalFiltered = (int)$stmtFiltered->fetchColumn();

$sql = "
    SELECT
        s.supplier_name,
        i.item_name,
        i.description,
        i.unit_price,
        i.item_id,
        i.product_image,
        i.item_category,
        i.product_category,
        i.sub_category,
        i.unit_measure,
        i.created_by,
        i.created_at
    FROM items i
    LEFT JOIN suppliers s ON i.supplier_id = s.supplier_id
    $where
    ORDER BY $orderColumn $orderDirection
    LIMIT :start, :length
";
$stmt = $pdo->prepare($sql);

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => $totalFiltered,
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
]);
