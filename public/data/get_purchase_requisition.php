<?php
include '../../includes/db.php';
session_start();

// FOR ITEMS.PHP
$dealer = $_SESSION['dealer'] ?? null;

// Setup DataTables parameters
$draw = $_GET['draw'];
$start = $_GET['start'];
$length = $_GET['length'];
$search = $_GET['search']['value'] ?? '';

// Count total records (with dealer filter if applicable)
if (!empty($dealer) && $dealer !== 'OG') {
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM purchase_requisition WHERE pr_dealer = :dealer");
    $stmtTotal->execute([':dealer' => $dealer]);
    $totalRecords = $stmtTotal->fetchColumn();
} else {
    $totalRecords = $pdo->query("SELECT COUNT(*) FROM purchase_requisition")->fetchColumn();
}

// Prepare filtered query
$where = " WHERE 1=1 ";
$params = [];

if (!empty($dealer) && $dealer !== 'OG') {
    $where .= " AND pr_dealer = :dealer";
    $params[':dealer'] = $dealer;
}

if (!empty($search)) {
    $where .= " AND (pr_number LIKE :search OR pr_status LIKE :search OR pr_department LIKE :search)";
    $params[':search'] = "%$search%";
}

// Get filtered records count
$stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM purchase_requisition $where");
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// Fetch data with aging calculation and order by aging desc
$sql = "
    SELECT 
        pr_number, 
        pr_status, 
        pr_department, 
        pr_date, 
        canvass_created,
        pr_file,
        DATEDIFF(CURDATE(), pr_date) AS aging
    FROM purchase_requisition
    $where
    ORDER BY aging DESC
    LIMIT :start, :length
";

$stmt = $pdo->prepare($sql);

// Bind values
if (!empty($dealer) && $dealer !== 'OG') {
    $stmt->bindValue(':dealer', $dealer, PDO::PARAM_STR);
}
if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return response
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
