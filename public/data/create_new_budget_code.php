<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

$dealer = $_SESSION['dealer'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$budget_code = $_POST['budget_code'] ?? '';
$budget_department = $_POST['budget_department'] ?? '';
$budget_activity = $_POST['budget_activity'] ?? '';
$approved_budget = $_POST['approved_budget'] ?? '';

if ($budget_code && $budget_department && $budget_activity && $approved_budget) {
    // Check if budget code already exists
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM budgets WHERE budget_code = ?");
    $checkStmt->execute([$budget_code]);
    if ($checkStmt->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Budget code already exists.']);
    } else {
        $stmt = $pdo->prepare("INSERT INTO budgets (budget_code, budget_department, budget_activity, approved_budget, budget_dealer, created_by) VALUES (?, ?, ?, ?, ?, ?)");
        if ($stmt->execute([$budget_code, $budget_department, $budget_activity, $approved_budget, $dealer, $user_id])) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Database insert failed.']);
        }
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Incomplete data.']);
}
