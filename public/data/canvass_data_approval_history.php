<?php
include '../../includes/db.php';

session_start();
// Session variables
$department = $_SESSION['department'] ?? '';
$dealer = strtoupper($_SESSION['dealer'] ?? '');
$role = strtolower($_SESSION['role'] ?? '');

$draw = $_GET['draw'] ?? 1;
$start = $_GET['start'] ?? 0;
$length = $_GET['length'] ?? 10;
$search = $_GET['search']['value'] ?? '';

$allowedDealers = ['TNE', 'TMR', 'TSJ', 'TBK', 'TOT'];

$where = "1=1";
$params = [];

// ✅ Dealer-based filtering (users.dealer)
if (in_array($dealer, $allowedDealers)) {
    $where .= " AND u.dealer = :dealer";
    $params[':dealer'] = $dealer;
}

// ✅ Role-based visibility
$visibilityConditions = [];
// (optional role-based filtering here if needed)

if (!empty($visibilityConditions)) {
    $where .= " AND (" . implode(" OR ", $visibilityConditions) . ")";
}

// ✅ Filter out specific checked_X conditions based on canvass_type
$where .= " AND (
    (ca.canvass_type = 1 AND ca.checked_4 IS NOT NULL)
    OR (ca.canvass_type = 2 AND ca.checked_7 IS NOT NULL)
    OR (ca.canvass_type = 3 AND ca.checked_9 IS NOT NULL)
)";

// ✅ Search filter
if (!empty($search)) {
    $where .= " AND csb.pr_number LIKE :search";
    $params[':search'] = "%$search%";
}

// ✅ Total records (only dealer filter)
$totalSQL = "
    SELECT COUNT(DISTINCT csb.batch_id)
    FROM canvass_sheet_batches csb
    LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
    LEFT JOIN users u ON ca.approver_1 = u.user_id
    WHERE u.dealer = :dealer
";
$totalStmt = $pdo->prepare($totalSQL);
$totalStmt->execute([':dealer' => $dealer]);
$totalRecords = $totalStmt->fetchColumn();

// ✅ Filtered records
$filteredSQL = "
    SELECT COUNT(DISTINCT csb.batch_id)
    FROM canvass_sheet_batches csb
    LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
    LEFT JOIN users u ON ca.approver_1 = u.user_id
    WHERE $where
";
$filteredStmt = $pdo->prepare($filteredSQL);
$filteredStmt->execute($params);
$filteredRecords = $filteredStmt->fetchColumn();

// ✅ Paginated data
$dataSQL = "
    SELECT 
        csb.batch_id, 
        csb.pr_number, 
        csb.created_by,
        csb.created_at,
        u.full_name AS approver_name, 
        u.department AS assigned_department,
        u.dealer
    FROM canvass_sheet_batches csb
    LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
    LEFT JOIN users u ON ca.approver_1 = u.user_id
    WHERE $where
    GROUP BY csb.batch_id
    ORDER BY csb.batch_id DESC
    LIMIT :start, :length
";

$dataStmt = $pdo->prepare($dataSQL);
foreach ($params as $key => $val) {
    $dataStmt->bindValue($key, $val);
}
$dataStmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
$dataStmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
$dataStmt->execute();

$data = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ JSON response
header('Content-Type: application/json');
echo json_encode([
    "draw" => intval($draw),
    "recordsTotal" => intval($totalRecords),
    "recordsFiltered" => intval($filteredRecords),
    "data" => $data
]);


// $department = $_SESSION['department'] ?? '';
// $draw = $_GET['draw'] ?? 1;
// $start = $_GET['start'] ?? 0;
// $length = $_GET['length'] ?? 10;
// $search = $_GET['search']['value'] ?? '';

// $params = [':department' => "%$department%"];

// // 1. Get total records
// $totalSQL = "
//     SELECT COUNT(DISTINCT csb.batch_id)
//     FROM canvass_sheet_batches csb
//     LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
//     LEFT JOIN users u ON ca.prepared_by = u.user_id
//     WHERE ca.assigned_department LIKE :department
// ";
// $stmt = $pdo->prepare($totalSQL);
// $stmt->execute($params);
// $totalRecords = $stmt->fetchColumn();

// // 2. Add search if needed
// $searchSQL = '';
// if (!empty($search)) {
//     $searchSQL = " AND csb.pr_number LIKE :search";
//     $params[':search'] = "%$search%";
// }

// // 3. Get filtered records count
// $filteredSQL = $totalSQL . $searchSQL;
// $stmt = $pdo->prepare($filteredSQL);
// $stmt->execute($params);
// $filteredRecords = $stmt->fetchColumn();

// // 4. Get paginated data
// $dataSQL = "
//     SELECT 
//         csb.batch_id, 
//         csb.pr_number, 
//         csb.created_by, 
//         u.full_name AS approver_name, 
//         ca.assigned_department
//     FROM canvass_sheet_batches csb
//     LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
//     LEFT JOIN users u ON ca.prepared_by = u.user_id
//     WHERE ca.assigned_department LIKE :department
//     $searchSQL
//     GROUP BY csb.batch_id
//     ORDER BY csb.batch_id DESC
//     LIMIT :start, :length
// ";
// $stmt = $pdo->prepare($dataSQL);
// foreach ($params as $key => $val) {
//     $stmt->bindValue($key, $val);
// }
// $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
// $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
// $stmt->execute();
// $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // 5. Return JSON
// header('Content-Type: application/json');
// echo json_encode([
//     "draw" => intval($draw),
//     "recordsTotal" => intval($totalRecords),
//     "recordsFiltered" => intval($filteredRecords),
//     "data" => $data
// ]);

// $department = $_SESSION['department'] ?? '';

// // Get draw, start, length, search
// $draw = $_GET['draw'];
// $start = $_GET['start'];
// $length = $_GET['length'];
// $search = $_GET['search']['value'];

// // Total records
// $totalRecords = $pdo->query("SELECT COUNT(*) FROM canvass_sheet_batches")->fetchColumn();

// // Filtered records
// if (!empty($search)) {
//     $stmt = $pdo->prepare("SELECT COUNT(*) FROM canvass_sheet_batches WHERE pr_number LIKE ?");
//     $stmt->execute(["%$search%"]);
//     $filteredRecords = $stmt->fetchColumn();

//     $stmt = $pdo->prepare("SELECT batch_id, pr_number, created_by FROM canvass_sheet_batches WHERE pr_number LIKE ? LIMIT ?, ?");
//     $stmt->bindValue(1, "%$search%", PDO::PARAM_STR);
//     $stmt->bindValue(2, (int)$start, PDO::PARAM_INT);
//     $stmt->bindValue(3, (int)$length, PDO::PARAM_INT);
//     $stmt->execute();
// } else {
//     $filteredRecords = $totalRecords;

//     $stmt = $pdo->prepare("SELECT batch_id, pr_number, created_by FROM canvass_sheet_batches LIMIT ?, ?");
//     $stmt->bindValue(1, (int)$start, PDO::PARAM_INT);
//     $stmt->bindValue(2, (int)$length, PDO::PARAM_INT);
//     $stmt->execute();
// }

// $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// // Output JSON
// echo json_encode([
//     "draw" => intval($draw),
//     "recordsTotal" => intval($totalRecords),
//     "recordsFiltered" => intval($filteredRecords),
//     "data" => $data
// ]);
