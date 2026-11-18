<?php
session_start();
header('Content-Type: application/json');
include '../../includes/db.php';
require_once __DIR__ . '/budget_remaining_helper.php';

$search = $_POST['search'] ?? '';
$department = trim($_POST['department'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');

$dealerFilter = ($dealer !== '' && $dealer !== 'OG') ? ' AND b.budget_dealer = ?' : '';
$params = ["%$search%"];

if ($department !== '') {
    $sql = "
        SELECT b.budget_id, b.budget_code, b.budget_activity
        FROM budgets b
        WHERE (b.budget_code LIKE ? OR b.budget_activity LIKE ?)
          AND b.budget_department = ?
          $dealerFilter
        GROUP BY b.budget_id, b.budget_code, b.budget_activity
        ORDER BY b.budget_code ASC
    ";
    $params[] = "%$search%";
    $params[] = $department;
} else {
    $sql = "
        SELECT b.budget_id, b.budget_code, b.budget_activity
        FROM budgets b
        WHERE (b.budget_code LIKE ? OR b.budget_activity LIKE ?)
          $dealerFilter
        ORDER BY b.budget_code ASC
    ";
    $params[] = "%$search%";
}

if ($dealerFilter !== '') {
    $params[] = $dealer;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

$results = [];

while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $approvedBudget = 0.0;
    $actualExpense = 0.0;
    $activeCashAdvance = 0.0;
    $pendingExpense = 0.0;
    $totalExpense = 0.0;
    $remainingValue = 0.0;

    if ($department !== '') {
        $budget = getUnifiedBudgetRemaining($pdo, $row['budget_code'], $department, $dealer);

        if ($budget !== null) {
            $approvedBudget = (float)$budget['approved_budget'];
            $actualExpense = (float)$budget['actual_expense'];
            $activeCashAdvance = (float)$budget['active_cash_advance'];
            $pendingExpense = (float)$budget['pending_expenses'];
            $totalExpense = (float)$budget['total_expenses'];
            $remainingValue = (float)$budget['remaining_budget'];
        }
    }

    $results[] = [
        'id' => $row['budget_id'],
        'text' => $row['budget_code'],
        'budget_code' => $row['budget_code'],
        'budget_activity' => $row['budget_activity'] ?? '',
        'remaining' => $remainingValue,
        'approved_budget' => $approvedBudget,
        'actual_expense' => $actualExpense,
        'active_cash_advance' => $activeCashAdvance,
        'pending_expenses' => $pendingExpense,
        'total_expenses' => $totalExpense
    ];
}

echo json_encode($results);
