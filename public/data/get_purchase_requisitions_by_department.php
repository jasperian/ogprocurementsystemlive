<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

$department = trim($_GET['department'] ?? '');
$search = trim($_GET['search'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');

if ($department === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Department is required.', 'data' => []]);
    exit;
}

try {
    $hasBudgetCode = (bool)$pdo->query("SHOW COLUMNS FROM purchase_requisition LIKE 'budget_code'")->fetch(PDO::FETCH_ASSOC);
    $hasPrDate = (bool)$pdo->query("SHOW COLUMNS FROM purchase_requisition LIKE 'pr_date'")->fetch(PDO::FETCH_ASSOC);
    $hasPrDealer = (bool)$pdo->query("SHOW COLUMNS FROM purchase_requisition LIKE 'pr_dealer'")->fetch(PDO::FETCH_ASSOC);

    $where = [
        "UPPER(TRIM(pr_department)) = UPPER(:department)",
        "UPPER(TRIM(pr_status)) = 'FOR CANVASS'",
    ];
    $params = [':department' => $department];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG' && !$hasPrDealer) {
        throw new RuntimeException('The purchase_requisition.pr_dealer column is missing.');
    }

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $where[] = "UPPER(TRIM(pr_dealer)) = UPPER(:dealer)";
        $params[':dealer'] = $dealer;
    }

    if ($search !== '') {
        $searchColumns = ['pr_number LIKE :search'];
        if ($hasBudgetCode) {
            $searchColumns[] = "COALESCE(budget_code, '') LIKE :search";
        }
        if ($hasPrDealer) {
            $searchColumns[] = "COALESCE(pr_dealer, '') LIKE :search";
        }
        $where[] = '(' . implode(' OR ', $searchColumns) . ')';
        $params[':search'] = '%' . $search . '%';
    }

    $dealerSelect = $hasPrDealer ? 'pr_dealer' : "'' AS pr_dealer";
    $dateSelect = $hasPrDate ? 'pr_date' : "NULL AS pr_date";
    $budgetSelect = $hasBudgetCode ? 'budget_code' : "'' AS budget_code";
    $orderBy = $hasPrDate ? 'pr_date DESC, pr_number DESC' : 'pr_number DESC';

    $sql = "
        SELECT pr_number, pr_status, pr_department, {$dealerSelect}, {$dateSelect}, {$budgetSelect}
        FROM purchase_requisition
        WHERE " . implode(' AND ', $where) . "
        ORDER BY {$orderBy}
        LIMIT 100
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    error_log('Purchase requisition dropdown error: ' . $e->getMessage());
    echo json_encode([
        'status' => 'danger',
        'message' => 'Unable to load purchase requisitions. Please contact the system administrator.',
        'data' => [],
    ]);
}
