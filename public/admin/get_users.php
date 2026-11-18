<?php
include '../../includes/db.php';

// Setup DataTables parameters
$draw   = $_GET['draw'] ?? 1;
$start  = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';

$params = [];

// Total records
$totalRecords = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();

// Filtered records
$sqlFiltered = "SELECT COUNT(*) FROM users WHERE position != 'Administrator'";
if (!empty($search)) {
    $sqlFiltered .= " AND (
        username LIKE :search 
        OR full_name LIKE :search 
        OR email_address LIKE :search 
        OR department LIKE :search 
        OR dealer LIKE :search
    )";
    $params[':search'] = "%$search%";
}
$stmtFiltered = $pdo->prepare($sqlFiltered);
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// Fetch data
$sql = "SELECT user_id, username, full_name, email_address, department, dealer, user_pfp, user_signature
        FROM users
        WHERE position != 'Administrator'";
if (!empty($search)) {
    $sql .= " AND (
        username LIKE :search 
        OR full_name LIKE :search 
        OR email_address LIKE :search 
        OR department LIKE :search 
        OR dealer LIKE :search
    )";
}
$sql .= " ORDER BY username ASC 
          LIMIT :start, :length";

$stmt = $pdo->prepare($sql);
if (!empty($search)) {
    $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
}
$stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
$stmt->execute();

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Return JSON
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => $totalRecords,
    "recordsFiltered" => $totalFiltered,
    "data" => $data
]);
