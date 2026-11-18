<?php
include '../../includes/db.php';

// FOR SUPPLIER.PHP

// Setup DataTables parameters
$draw = $_GET['draw'];
$start = $_GET['start'];
$length = $_GET['length'];
$search = $_GET['search']['value'] ?? '';

// Count total records
$totalRecords = $pdo->query("SELECT COUNT(*) FROM suppliers WHERE is_approved = 1 AND is_rejected = 0")->fetchColumn();

// Prepare filtered query
$where = "WHERE is_approved = 1";
$params = [];

if (!empty($search)) {
    $where .= " AND (vendor_id LIKE :search OR supplier_name LIKE :search OR tin_number LIKE :search)";
    $params[':search'] = "%$search%";
}

// Get filtered records count
$stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM suppliers $where AND is_rejected = 0");
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// Fetch data
$sql = "SELECT vendor_id, supplier_name, tin_number FROM suppliers $where AND is_rejected = 0 LIMIT :start, :length";
$stmt = $pdo->prepare($sql);

// Bind values
if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
$stmt->execute();

$data = $stmt->fetchAll();

// Return response
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);

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
