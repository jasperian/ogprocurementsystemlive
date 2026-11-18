<?php
session_start();
header('Content-Type: application/json');
include '../../includes/db.php';
require_once __DIR__ . '/budget_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

$department = trim($_GET['department'] ?? ($_SESSION['department'] ?? ''));
$dealer = trim($_SESSION['dealer'] ?? '');
$search = trim($_GET['search'] ?? '');

if ($department === '') {
    echo json_encode(['status' => 'danger', 'message' => 'No department found in session.', 'data' => []]);
    exit;
}

try {
    ensureBudgetUserAccessTable($pdo);
    echo json_encode([
        'status' => 'success',
        'data' => budgetAccessGetUserBudgets(
            $pdo,
            (int)$_SESSION['user_id'],
            $department,
            $dealer,
            $search
        )
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to fetch department budgets.',
        'data' => []
    ]);
}
