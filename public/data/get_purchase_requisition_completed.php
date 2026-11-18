<?php
include '../../includes/db.php';
session_start();

$department = $_SESSION['department'] ?? '';
$dealer = $_SESSION['dealer'] ?? '';

// DataTables parameters
$draw = $_GET['draw'];
$start = $_GET['start'];
$length = $_GET['length'];
$search = $_GET['search']['value'] ?? '';

// Base query conditions
$where = " WHERE pr_status = 'Completed' ";
$params = [];

// Special case: If dealer = OG and department = EO -> show all
if (!(strtolower($dealer) === 'og' && strtolower($department) === 'eo')) {

    if (!empty($dealer) && strtolower($dealer) !== 'og') {
        $where .= " AND pr_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }

    if (strtolower($department) !== 'purchasing') {
        $where .= " AND pr_department = :department";
        $params[':department'] = $department;
    }
}

// Count total records
$sqlTotal = "SELECT COUNT(*) 
             FROM purchase_requisition
             $where";

$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();

// Search filter
if (!empty($search)) {
    $where .= " AND (pr_number LIKE :search 
                OR pr_status LIKE :search 
                OR pr_department LIKE :search)";
    $params[':search'] = "%$search%";
}

// Count filtered records
$sqlFiltered = "SELECT COUNT(*) 
                FROM purchase_requisition
                $where";

$stmtFiltered = $pdo->prepare($sqlFiltered);
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// Fetch paginated data
$sql = "SELECT 
        pr_number, 
        pr_status, 
        pr_department, 
        pr_date, 
        canvass_created,
        pr_file,
        pr_dealer,
        DATEDIFF(CURDATE(), pr_date) AS aging
    FROM purchase_requisition
    $where
    ORDER BY aging DESC
    LIMIT :start, :length";

$stmt = $pdo->prepare($sql);

// Bind parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}

$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data" => $data
]);