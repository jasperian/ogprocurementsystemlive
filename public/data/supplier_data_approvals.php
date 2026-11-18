<?php
include '../../includes/db.php';

// FOR SUPPLIERS_APPROVALS.PHP
session_start();

$dealer = $_SESSION['dealer'] ?? null;
$position = $_SESSION['position'] ?? null;

$draw = $_GET['draw'];
$start = $_GET['start'];
$length = $_GET['length'];
$search = $_GET['search']['value'] ?? '';

$params = [];
$where = "WHERE s.is_approved = 0";
$join = ""; // default no join

// Dealer filter
// if (!empty($dealer) && $dealer !== 'OG') {
//     $where .= " AND s.dealer = :dealer";
//     $params[':dealer'] = $dealer;
// }
// Dealer filter — OG sees all, others are filtered
if (!empty($dealer) && $dealer !== 'OG') {
    $where .= " AND s.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

// If position matches one of the roles, apply extra filters
$positionsWithFilter = [
    'Manager'        => "sa.checked_1 IS NULL",
    'VP for Accounting'        => "sa.checked_1 IS NOT NULL AND sa.checked_2 IS NULL",
    'SVP for Finance and Admin'=> "sa.checked_1 IS NOT NULL AND sa.checked_2 IS NOT NULL AND sa.checked_3 IS NULL",
];

if (array_key_exists($position, $positionsWithFilter)) {
    $join = "INNER JOIN supplier_approvals sa ON s.vendor_id = sa.vendor_id";
    $where .= " AND " . $positionsWithFilter[$position];
}

// Search
if (!empty($search)) {
    $where .= " AND (s.vendor_id LIKE :search OR s.supplier_name LIKE :search OR s.tin_number LIKE :search)";
    $params[':search'] = "%$search%";
}

// Filtered count
$stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM suppliers s $join $where");
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// Total count (still respect dealer, no search/position filters)
$countParams = [];
$countWhere = "WHERE is_approved = 0";
if (!empty($dealer) && $dealer !== 'OG') {
    $countWhere .= " AND dealer = :dealer";
    $countParams[':dealer'] = $dealer;
}
$totalRecordsStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers $countWhere");
$totalRecordsStmt->execute($countParams);
$totalRecords = $totalRecordsStmt->fetchColumn();

// Data query
$sql = "SELECT s.vendor_id, s.supplier_name, s.tin_number, s.created_by, s.dealer FROM suppliers s $join $where LIMIT :start, :length";
$stmt = $pdo->prepare($sql);

// Bind values
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, PDO::PARAM_STR);
}
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
$stmt->execute();
$data = $stmt->fetchAll();

// JSON response
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);


// session_start(); // Make sure session is started

// $dealer = $_SESSION['dealer'] ?? null;

// // Setup DataTables parameters
// $draw = $_GET['draw'];
// $start = $_GET['start'];
// $length = $_GET['length'];
// $search = $_GET['search']['value'] ?? '';

// // Count total unapproved records (with dealer filter if present)
// $where = "WHERE is_approved = 0";
// $params = [];

// if (!empty($dealer) && $dealer !== 'OG') {
//     $where .= " AND dealer = :dealer";
//     $params[':dealer'] = $dealer;
// }

// // Add search filter
// if (!empty($search)) {
//     $where .= " AND (vendor_id LIKE :search OR supplier_name LIKE :search OR tin_number LIKE :search)";
//     $params[':search'] = "%$search%";
// }

// // Count total records after filters
// $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
// $stmtFiltered->execute($params);
// $totalFiltered = $stmtFiltered->fetchColumn();

// // Count total unapproved (for recordsTotal — without search but still with dealer)
// $countParams = [];
// $countWhere = "WHERE is_approved = 0";
// if (!empty($dealer) && $dealer !== 'OG') {
//     $countWhere .= " AND dealer = :dealer";
//     $countParams[':dealer'] = $dealer;
// }
// $totalRecordsStmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers $countWhere");
// $totalRecordsStmt->execute($countParams);
// $totalRecords = $totalRecordsStmt->fetchColumn();

// // Fetch paginated data
// $sql = "SELECT vendor_id, supplier_name, tin_number FROM suppliers $where LIMIT :start, :length";
// $stmt = $pdo->prepare($sql);

// // Bind all params
// foreach ($params as $key => $value) {
//     $stmt->bindValue($key, $value, PDO::PARAM_STR);
// }
// $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
// $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
// $stmt->execute();

// $data = $stmt->fetchAll();

// // Return JSON response
// echo json_encode([
//     "draw" => intval($draw),
//     "recordsTotal" => $totalRecords,
//     "recordsFiltered" => $totalFiltered,
//     "data" => $data
// ]);

// how can I filter that if the session position is 
// P for Accounting and if checked_3 is null in table supplier_approvals
// President  = checked_4 if checked_3 is null in table supplier_approvals
// SVP for Finance and Admin  = checked_5  if checked_3 is null in table supplier_approvals
// Vice Chairman  = checked_6 if checked_3 is null in table supplier_approvals
// show the data. otherwise dont show it
// // Setup DataTables parameters
// $draw = $_GET['draw'];
// $start = $_GET['start'];
// $length = $_GET['length'];
// $search = $_GET['search']['value'] ?? '';

// // Count total unapproved records
// $totalRecords = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_approved = 0")->fetchColumn();

// // Build WHERE clause for filtering
// $where = "WHERE is_approved = 0";
// $params = [];

// if (!empty($search)) {
//     $where .= " AND (vendor_id LIKE :search OR supplier_name LIKE :search OR tin_number LIKE :search)";
//     $params[':search'] = "%$search%";
// }

// // Get filtered records count
// $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
// $stmtFiltered->execute($params);
// $totalFiltered = $stmtFiltered->fetchColumn();

// // Fetch data
// $sql = "SELECT vendor_id, supplier_name, tin_number FROM suppliers $where LIMIT :start, :length";
// $stmt = $pdo->prepare($sql);

// // Bind parameters
// if (!empty($search)) {
//     $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
// }
// $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
// $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
// $stmt->execute();

// $data = $stmt->fetchAll();

// // Return JSON response
// echo json_encode([
//     "draw" => intval($draw),
//     "recordsTotal" => $totalRecords,
//     "recordsFiltered" => $totalFiltered,
//     "data" => $data
// ]);


// This code handles server-side processing for DataTables, allowing for efficient pagination and searching of supplier data.
// <?php
// include '../../includes/db.php';

// // Setup DataTables parameters
// $draw = $_GET['draw'];
// $start = $_GET['start'];
// $length = $_GET['length'];
// $search = $_GET['search']['value'] ?? '';

// $where = "";
// $params = [];

// if (!empty($search)) {
//     $where = "WHERE vendor_id LIKE :search OR supplier_name LIKE :search OR tin_number LIKE :search";
//     $params[':search'] = "%$search%";
// }

// // When search is empty, we only want to work with the first 100 rows
// if (empty($search)) {
//     // Limit total to 100
//     $totalRecords = 100;

//     // Fetch the first 100 records
//     $sqlLimit100 = "SELECT vendor_id, supplier_name, tin_number FROM suppliers LIMIT 100";
//     $stmtLimit100 = $pdo->prepare($sqlLimit100);
//     $stmtLimit100->execute();
//     $all100 = $stmtLimit100->fetchAll();

//     // Slice the results based on start/length to simulate paging in the first 100
//     $pagedData = array_slice($all100, $start, $length);

//     // Return 100 as both total and filtered count
//     echo json_encode([
//         "draw" => intval($draw),
//         "recordsTotal" => 100,
//         "recordsFiltered" => 100,
//         "data" => $pagedData
//     ]);
// } else {
//     // If there's a search term, use full filtering and pagination
//     $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where");
//     $stmtFiltered->execute($params);
//     $totalFiltered = $stmtFiltered->fetchColumn();

//     $totalRecords = $pdo->query("SELECT COUNT(*) FROM suppliers")->fetchColumn();

//     $sql = "SELECT vendor_id, supplier_name, tin_number FROM suppliers $where LIMIT :start, :length";
//     $stmt = $pdo->prepare($sql);

//     $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
//     $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
//     $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
//     $stmt->execute();

//     $data = $stmt->fetchAll();

//     echo json_encode([
//         "draw" => intval($draw),
//         "recordsTotal" => $totalRecords,
//         "recordsFiltered" => $totalFiltered,
//         "data" => $data
//     ]);
// }
