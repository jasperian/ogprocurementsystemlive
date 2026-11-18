<?php
include '../../includes/db.php';

header('Content-Type: application/json');

$filterStart = trim($_GET['filter_start'] ?? '');
$filterEnd = trim($_GET['filter_end'] ?? '');
$department = trim($_GET['department'] ?? '');
$isDataTableRequest = isset($_GET['draw']);

$params = [];
$expenseDateFilter = '';
if ($filterStart !== '' && $filterEnd !== '') {
    $expenseDateFilter = 'WHERE e.expense_date BETWEEN :expense_start AND :expense_end';
    $params[':expense_start'] = $filterStart;
    $params[':expense_end'] = $filterEnd;
}

$expenseJoin = "
    LEFT JOIN (
        SELECT e.budget_id, SUM(COALESCE(e.expense_amount, 0)) AS total_expense
        FROM expenses e
        $expenseDateFilter
        GROUP BY e.budget_id
    ) expense_totals ON expense_totals.budget_id = b.budget_id
";

$where = ["b.budget_dealer = 'TNE'"];
if ($department !== '') {
    $where[] = 'b.budget_department = :department';
    $params[':department'] = $department;
}

if ($isDataTableRequest) {
    $draw = max(0, (int)($_GET['draw'] ?? 0));
    $offset = max(0, (int)($_GET['start'] ?? 0));
    $length = (int)($_GET['length'] ?? 10);
    $length = $length === -1 ? 100 : max(10, min($length, 100));
    $search = trim($_GET['search']['value'] ?? '');
    $filteredWhere = $where;
    $filteredParams = $params;

    if ($search !== '') {
        $filteredWhere[] = '(
            b.budget_dealer LIKE :search
            OR b.budget_department LIKE :search
            OR b.budget_code LIKE :search
            OR b.budget_activity LIKE :search
        )';
        $filteredParams[':search'] = '%' . $search . '%';
    }

    $countParams = [];
    if ($department !== '') {
        $countParams[':department'] = $department;
    }

    $countStmt = $pdo->prepare(
        'SELECT COUNT(*) FROM budgets b WHERE ' . implode(' AND ', $where)
    );
    $countStmt->execute($countParams);
    $recordsTotal = (int)$countStmt->fetchColumn();

    if ($search === '') {
        $recordsFiltered = $recordsTotal;
    } else {
        $filteredCountStmt = $pdo->prepare(
            'SELECT COUNT(*) FROM budgets b WHERE ' . implode(' AND ', $filteredWhere)
        );
        $filteredCountStmt->execute($countParams + [':search' => '%' . $search . '%']);
        $recordsFiltered = (int)$filteredCountStmt->fetchColumn();
    }

    $orderColumns = [
        0 => 'b.budget_dealer',
        1 => 'b.budget_department',
        2 => 'b.budget_code',
        3 => 'b.approved_budget',
        4 => 'ActualExpense',
        5 => 'RemainingBudget',
        6 => 'BudgetUtilization',
    ];
    $orderIndex = (int)($_GET['order'][0]['column'] ?? 1);
    $orderColumn = $orderColumns[$orderIndex] ?? 'b.budget_department';
    $orderDirection = strtolower($_GET['order'][0]['dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

    $sql = "
        SELECT
            b.budget_dealer AS BudgetDealer,
            b.budget_code AS BudgetCode,
            b.budget_activity AS BudgetActivity,
            b.budget_department AS BudgetDepartment,
            b.approved_budget AS ApprovedBudget,
            COALESCE(expense_totals.total_expense, 0) AS ActualExpense,
            b.approved_budget - COALESCE(expense_totals.total_expense, 0) AS RemainingBudget,
            ROUND(
                COALESCE(expense_totals.total_expense, 0) / NULLIF(b.approved_budget, 0) * 100,
                2
            ) AS BudgetUtilization,
            b.budget_id
        FROM budgets b
        $expenseJoin
        WHERE " . implode(' AND ', $filteredWhere) . "
        ORDER BY $orderColumn $orderDirection
        LIMIT :offset, :length
    ";

    $stmt = $pdo->prepare($sql);
    foreach ($filteredParams as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':length', $length, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode([
        'draw' => $draw,
        'recordsTotal' => $recordsTotal,
        'recordsFiltered' => $recordsFiltered,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
    exit;
}

$chartLimit = max(5, min((int)($_GET['limit'] ?? 30), 50));
$sql = "
    SELECT
        b.budget_code AS BudgetCode,
        b.approved_budget AS ApprovedBudget,
        COALESCE(expense_totals.total_expense, 0) AS ActualExpense,
        b.approved_budget - COALESCE(expense_totals.total_expense, 0) AS RemainingBudget,
        ROUND(
            COALESCE(expense_totals.total_expense, 0) / NULLIF(b.approved_budget, 0) * 100,
            2
        ) AS BudgetUtilization
    FROM budgets b
    $expenseJoin
    WHERE " . implode(' AND ', $where) . "
    ORDER BY b.approved_budget DESC, b.budget_code ASC
    LIMIT :chart_limit
";

$stmt = $pdo->prepare($sql);
foreach ($params as $name => $value) {
    $stmt->bindValue($name, $value);
}
$stmt->bindValue(':chart_limit', $chartLimit, PDO::PARAM_INT);
$stmt->execute();

echo json_encode(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
