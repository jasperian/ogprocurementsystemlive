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
// $where .= " AND (
//     (ca.canvass_type = 1 AND ca.checked_4 IS NOT NULL)
//     OR (ca.canvass_type = 2 AND ca.checked_7 IS NOT NULL)
//     OR (ca.canvass_type = 3 AND ca.checked_9 IS NOT NULL)
// )";

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
    LEFT JOIN purchase_requisition pr ON csb.pr_number = pr.pr_number
    WHERE u.dealer = :dealer
      AND pr.pr_status = 'Rejected'
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
    LEFT JOIN purchase_requisition pr ON csb.pr_number = pr.pr_number
    WHERE $where
      AND pr.pr_status = 'Rejected'
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
        u.dealer,
        pr.pr_status,
        pr.pr_department
    FROM canvass_sheet_batches csb
    LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
    LEFT JOIN users u ON ca.approver_1 = u.user_id
    LEFT JOIN purchase_requisition pr ON csb.pr_number = pr.pr_number
    WHERE $where
      AND pr.pr_status = 'Rejected'
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
