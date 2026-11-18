<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/budget_remaining_helper.php';
require_once __DIR__ . '/budget_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
    exit;
}

$department = trim($_GET['department'] ?? ($_SESSION['department'] ?? ''));
$budgetId = (int)($_GET['budget_id'] ?? 0);
$dealer = trim($_SESSION['dealer'] ?? '');

if ($department === '' || $budgetId <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Department and budget code are required.']);
    exit;
}

try {
    ensureBudgetUserAccessTable($pdo);
    $allowedBudget = budgetAccessGetAllowedBudget(
        $pdo,
        $budgetId,
        (int)$_SESSION['user_id'],
        $department,
        $dealer
    );

    if (!$allowedBudget) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget code is not accessible to your account.']);
        exit;
    }

    $budget = getUnifiedBudgetRemainingById(
        $pdo,
        $budgetId,
        $allowedBudget['budget_department'],
        $allowedBudget['budget_dealer']
    );

    if ($budget === null) {
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget code is invalid for the chosen department.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'data' => $budget
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Failed to load remaining budget.']);
}
