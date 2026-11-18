<?php
include '../../includes/db.php';
session_start();

$user_id    = $_SESSION['user_id'] ?? null;
$department = $_SESSION['department'] ?? '';
$dealer     = $_SESSION['dealer'] ?? '';

$draw   = $_GET['draw'] ?? 1;
$start  = (int)($_GET['start'] ?? 0);
$length = (int)($_GET['length'] ?? 10);
$search = $_GET['search']['value'] ?? '';

$columns = [
    'is_favorite',
    'pr_date',
    'pr_dealer',
    'pr_department',
    'pr_status',
    'next_approver',
    'pr_number',
    'canvass_created'
];

// ✅ Ordering
$orderColumnIndex = $_GET['order'][0]['column'] ?? 1;
$orderDirection   = $_GET['order'][0]['dir'] ?? 'asc';

$orderColumn    = $columns[$orderColumnIndex] ?? 'pr_date';
$orderDirection = strtolower($orderDirection) === 'desc' ? 'DESC' : 'ASC';

// ✅ Base WHERE
$where  = "WHERE pr.pr_status NOT IN ('Rejected', 'Completed', 'Cancelled')";
$params = [];

if (!(strtolower($dealer) === 'og' && strtolower($department) === 'eo')) {
    if (!empty($dealer) && strtolower($dealer) !== 'og') {
        $where .= " AND pr.pr_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }
    if (strtolower($department) !== 'purchasing') {
        $where .= " AND pr.pr_department = :department";
        $params[':department'] = $department;
    }
}

// ✅ COUNT QUERY (MATCHES MAIN QUERY)
$sqlTotal = "
    SELECT COUNT(DISTINCT pr.pr_number)
    FROM purchase_requisition pr
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    $where
";

$stmtTotal = $pdo->prepare($sqlTotal);
$stmtTotal->execute($params);
$totalRecords = $stmtTotal->fetchColumn();

// ✅ SEARCH
if (!empty($search)) {
    $where .= " AND (
        pr.pr_number LIKE :search OR
        pr.pr_status LIKE :search OR
        pr.pr_department LIKE :search
    )";
    $params[':search'] = "%$search%";
}

// ✅ FILTERED COUNT
$sqlFiltered = "
    SELECT COUNT(DISTINCT pr.pr_number)
    FROM purchase_requisition pr
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    $where
";

$stmtFiltered = $pdo->prepare($sqlFiltered);
$stmtFiltered->execute($params);
$totalFiltered = $stmtFiltered->fetchColumn();

// ✅ MAIN DATA QUERY
$sql = "
    SELECT 
        pr.pr_number,
        pr.pr_status,
        pr.pr_department,
        pr.pr_date,
        pr.canvass_created,
        pr.pr_file,
        pr.pr_dealer,
        DATEDIFF(CURDATE(), pr.pr_date) AS aging,
        COALESCE(u.full_name, 'Completed') AS next_approver,
        IF(pf.id IS NOT NULL, 1, 0) AS is_favorite
    FROM purchase_requisition pr
    INNER JOIN canvass_approvals ca ON pr.batch_id = ca.batch_id
    LEFT JOIN users u ON u.user_id = (
        CASE
            WHEN ca.checked_1 IS NULL THEN ca.approver_1
            WHEN ca.checked_2 IS NULL THEN ca.approver_2
            WHEN ca.checked_3 IS NULL THEN ca.approver_3
            WHEN ca.checked_4 IS NULL THEN ca.approver_4
            WHEN ca.checked_5 IS NULL THEN ca.approver_5
            WHEN ca.checked_6 IS NULL THEN ca.approver_6
            WHEN ca.checked_7 IS NULL THEN ca.approver_7
            WHEN ca.checked_8 IS NULL THEN ca.approver_8
            WHEN ca.checked_9 IS NULL THEN ca.approver_9
            ELSE NULL
        END
    )
    LEFT JOIN pr_favorites pf 
        ON pf.pr_number = pr.pr_number 
        AND pf.user_id = :user_id
    $where
    GROUP BY pr.pr_number
    ORDER BY is_favorite DESC, $orderColumn $orderDirection
";

// ✅ FIXED LIMIT (NO BINDING)
if ($length > 0) {
    $sql .= " LIMIT $start, $length";
}

$stmt = $pdo->prepare($sql);

// ✅ Safe user_id binding
$user_id_safe = $user_id ?? 0;
$stmt->bindValue(':user_id', $user_id_safe, PDO::PARAM_INT);

// ✅ Bind filters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ✅ RESPONSE
echo json_encode([
    "draw"            => intval($draw),
    "recordsTotal"    => intval($totalRecords),
    "recordsFiltered" => intval($totalFiltered),
    "data"            => $data
]);
?>