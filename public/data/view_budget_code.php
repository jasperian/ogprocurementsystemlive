<?php
include '../../includes/db.php';

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$dealer = $_GET['dealer'] ?? '';
$department = $_GET['department'] ?? '';

$sql = "
    SELECT 
        b.budget_dealer AS BudgetDealer,
        b.budget_code AS BudgetCode,
        b.budget_activity AS BudgetActivity,
        b.budget_department AS BudgetDepartment,
        b.approved_budget AS ApprovedBudget,
        SUM(IFNULL(e.expense_amount, 0)) AS ActualExpense,
        (b.approved_budget - SUM(IFNULL(e.expense_amount, 0))) AS RemainingBudget,
        ROUND(
            (SUM(IFNULL(e.expense_amount, 0)) / NULLIF(b.approved_budget, 0)) * 100, 
        2) AS BudgetUtilization,
        b.budget_id
    FROM budgets b
    LEFT JOIN expenses e 
        ON b.budget_id = e.budget_id
";

// ✅ MOVE date filter inside JOIN (important)
if (!empty($start) && !empty($end)) {
    $sql .= " AND e.expense_date BETWEEN :start AND :end";
}

$sql .= " WHERE 1=1";

// ✅ Correct Dealer filter
if (!empty($dealer)) {
    $sql .= " AND b.budget_dealer = :dealer";
}

// ✅ Add Department filter
if (!empty($department)) {
    $sql .= " AND b.budget_department = :department";
}

// ✅ Proper grouping (all non-aggregated columns)
$sql .= "
    GROUP BY 
        b.budget_id,
        b.budget_dealer,
        b.budget_code,
        b.budget_activity,
        b.budget_department,
        b.approved_budget
    ORDER BY b.budget_department ASC
";

$stmt = $pdo->prepare($sql);

// Bind params
if (!empty($start) && !empty($end)) {
    $stmt->bindParam(":start", $start);
    $stmt->bindParam(":end", $end);
}

if (!empty($dealer)) {
    $stmt->bindParam(":dealer", $dealer);
}

if (!empty($department)) {
    $stmt->bindParam(":department", $department);
}

$stmt->execute();
$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['data' => $data]);