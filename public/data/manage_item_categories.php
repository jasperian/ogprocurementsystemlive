<?php
header('Content-Type: application/json');
include '../../includes/db.php';
require_once '../../includes/item_category_helpers.php';

ensureItemCategoryTables($pdo);

$type = $_GET['type'] ?? 'item_categories';
$draw = (int)($_GET['draw'] ?? 0);
$start = max(0, (int)($_GET['start'] ?? 0));
$length = (int)($_GET['length'] ?? 10);
$length = $length > 0 ? min($length, 100) : 10;
$search = trim($_GET['search']['value'] ?? '');

if ($type === 'sub_categories') {
    $where = $search !== '' ? "WHERE item_category LIKE :search OR sub_category LIKE :search" : "";
    $params = $search !== '' ? [':search' => "%{$search}%"] : [];

    $totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM item_sub_categories")->fetchColumn();
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM item_sub_categories $where");
    $stmtFiltered->execute($params);

    $stmt = $pdo->prepare("
        SELECT id, item_category, sub_category, created_by, created_at
        FROM item_sub_categories
        $where
        ORDER BY item_category ASC, sub_category ASC
        LIMIT :start, :length
    ");
} else {
    $where = $search !== '' ? "WHERE category_name LIKE :search" : "";
    $params = $search !== '' ? [':search' => "%{$search}%"] : [];

    $totalRecords = (int)$pdo->query("SELECT COUNT(*) FROM item_categories")->fetchColumn();
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM item_categories $where");
    $stmtFiltered->execute($params);

    $stmt = $pdo->prepare("
        SELECT id, category_name
        FROM item_categories
        $where
        ORDER BY category_name ASC
        LIMIT :start, :length
    ");
}

foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', $start, PDO::PARAM_INT);
$stmt->bindValue(':length', $length, PDO::PARAM_INT);
$stmt->execute();

echo json_encode([
    'draw' => $draw,
    'recordsTotal' => $totalRecords,
    'recordsFiltered' => (int)$stmtFiltered->fetchColumn(),
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
]);
