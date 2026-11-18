<?php
session_start();
include '../../includes/db.php';

$start = $_GET['start'] ?? '';
$end   = $_GET['end'] ?? '';

$user_id = $_SESSION['user_id'] ?? null;

$department = $_SESSION['department'] ?? '';
$dealer     = strtoupper($_SESSION['dealer'] ?? '');

$params = [
    ':dealer' => $dealer
];

$sql = "SELECT 
        b.budget_dealer AS BudgetDealer,
        b.budget_code AS BudgetCode,
        b.budget_activity AS BudgetActivity,
        b.budget_department AS BudgetDepartment,
        b.approved_budget AS ApprovedBudget,
        SUM(IFNULL(e.expense_amount, 0)) AS ActualExpense,
        (b.approved_budget - SUM(IFNULL(e.expense_amount, 0))) AS RemainingBudget,
        ROUND(
            (SUM(IFNULL(e.expense_amount, 0)) / NULLIF(b.approved_budget, 0)) * 100,
            2
        ) AS BudgetUtilization,
        b.budget_id
    FROM budgets b
    LEFT JOIN expenses e 
        ON b.budget_id = e.budget_id
    WHERE b.budget_dealer = :dealer
";

// AFF user department mapping
$aff_departments = [
    97 => 'TSURE',
    98 => 'AUTOQUIX',
    99 => 'HINO',
    100 => 'PFFC',
    101 => 'DIVINE'
];

// Department filter
if (array_key_exists((int)$user_id, $aff_departments)) {
    $sql .= " AND b.budget_department = :aff_department";
    $params[':aff_department'] = $aff_departments[(int)$user_id];
} else {
    $sql .= " AND b.budget_department = :department";
    $params[':department'] = $department;
}

// DATE FILTER expenses only
if (!empty($start) && !empty($end)) {
    $sql .= " AND e.expense_date BETWEEN :start AND :end";
    $params[':start'] = $start;
    $params[':end']   = $end;
}

// GROUP BY
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
$stmt->execute($params);

$data = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode(['data' => $data]);