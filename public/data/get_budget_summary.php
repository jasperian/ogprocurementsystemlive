<?php
include '../../includes/db.php';

// Summarized data grouped by department
$sql = "
    SELECT 
        b.budget_dealer AS BudgetDealer,
        b.budget_code AS BudgetCode,
        b.budget_activity AS BudgetActivity,
        b.budget_department AS BudgetDepartment,
        b.approved_budget AS ApprovedBudget,
        SUM(IFNULL(e.expense_amount, 0)) AS ActualExpense,
        b.approved_budget - SUM(IFNULL(e.expense_amount, 0)) AS RemainingBudget,
        ROUND((SUM(IFNULL(e.expense_amount, 0)) / SUM(b.approved_budget)) * 100, 2) AS BudgetUtilization
    FROM budgets b
    LEFT JOIN expenses e ON b.budget_id = e.budget_id
    GROUP BY b.budget_department
    ORDER BY b.budget_department ASC
";

$stmt = $pdo->query($sql);
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['data' => $data]);
