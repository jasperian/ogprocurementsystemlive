<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include '../../includes/db.php';

header('Content-Type: application/json');

$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$department = trim($_GET['department'] ?? ($_GET['dealer'] ?? ''));
$sessionDepartment = trim($_SESSION['department'] ?? '');
$canViewAllDepartments = in_array(strtoupper($sessionDepartment), ['PURCHASING', 'EO'], true);

if (!$canViewAllDepartments && $sessionDepartment === '') {
    echo json_encode([
        'can_view_all' => false,
        'session_department' => '',
        'data' => [],
    ]);
    exit;
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
    $start = '';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $end)) {
    $end = '';
}

if ($start !== '' && $end !== '' && $start > $end) {
    [$start, $end] = [$end, $start];
}

if (!$canViewAllDepartments) {
    $department = $sessionDepartment;
}

$purchaseOrderDateFilter = '';
$cashAdvanceDateFilter = '';
$liquidationDateFilter = '';
$params = [];

if ($start !== '' && $end !== '') {
    $purchaseOrderDateFilter = ' AND DATE(po.po_date) BETWEEN :expense_start AND :expense_end';
    $cashAdvanceDateFilter = ' AND COALESCE(ca_date, DATE(pr_date)) BETWEEN :cash_start AND :cash_end';
    $liquidationDateFilter = ' AND l.liquidation_date BETWEEN :liquidation_start AND :liquidation_end';
    $params[':expense_start'] = $start;
    $params[':expense_end'] = $end;
    $params[':cash_start'] = $start;
    $params[':cash_end'] = $end;
    $params[':liquidation_start'] = $start;
    $params[':liquidation_end'] = $end;
} elseif ($start !== '') {
    $purchaseOrderDateFilter = ' AND DATE(po.po_date) >= :expense_start';
    $cashAdvanceDateFilter = ' AND COALESCE(ca_date, DATE(pr_date)) >= :cash_start';
    $liquidationDateFilter = ' AND l.liquidation_date >= :liquidation_start';
    $params[':expense_start'] = $start;
    $params[':cash_start'] = $start;
    $params[':liquidation_start'] = $start;
} elseif ($end !== '') {
    $purchaseOrderDateFilter = ' AND DATE(po.po_date) <= :expense_end';
    $cashAdvanceDateFilter = ' AND COALESCE(ca_date, DATE(pr_date)) <= :cash_end';
    $liquidationDateFilter = ' AND l.liquidation_date <= :liquidation_end';
    $params[':expense_end'] = $end;
    $params[':cash_end'] = $end;
    $params[':liquidation_end'] = $end;
}

$sql = "
    SELECT
        b.budget_department AS BudgetDepartment,
        SUM(b.approved_budget) AS TotalApprovedBudget,
        SUM(IFNULL(e.total_expense, 0)) AS TotalActualExpense,
        SUM(IFNULL(lr.total_liquidation, 0)) AS TotalLiquidationExpense,
        SUM(IFNULL(lr.total_rfp, 0)) AS TotalRFP,
        SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0)) AS TotalCashAdvance,
        (
            SUM(IFNULL(e.total_expense, 0))
            + SUM(IFNULL(lr.total_liquidation, 0))
            + SUM(IFNULL(lr.total_rfp, 0))
            + SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
        ) AS TotalCommittedAmount,
        (
            SUM(b.approved_budget)
            - SUM(IFNULL(e.total_expense, 0))
            - SUM(IFNULL(lr.total_liquidation, 0))
            - SUM(IFNULL(lr.total_rfp, 0))
            - SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
        ) AS TotalRemainingBudget,
        ROUND(
            (
                (
                    SUM(IFNULL(e.total_expense, 0))
                    + SUM(IFNULL(lr.total_liquidation, 0))
                    + SUM(IFNULL(lr.total_rfp, 0))
                    + SUM(IFNULL(ca.total_cash_advance, 0) + IFNULL(lr.total_cash_advance, 0))
                )
                / NULLIF(SUM(b.approved_budget), 0)
            ) * 100,
            2
        ) AS BudgetUtilization
    FROM budgets b
    LEFT JOIN (
        SELECT
            budget_lookup.budget_id,
            SUM(po.amount_purchased) AS total_expense
        FROM purchase_order po
        INNER JOIN canvass_sheet_batches csb ON csb.pr_number = po.pr_number
        INNER JOIN budgets budget_lookup
            ON CAST(budget_lookup.budget_id AS CHAR) = TRIM(csb.budget_code)
            OR budget_lookup.budget_code = TRIM(csb.budget_code)
        WHERE csb.budget_code IS NOT NULL
          AND TRIM(csb.budget_code) <> ''
          $purchaseOrderDateFilter
        GROUP BY budget_lookup.budget_id
    ) e ON b.budget_id = e.budget_id
    LEFT JOIN (
        SELECT
            budget_code,
            pr_dealer,
            pr_department,
            SUM(ca_amount) AS total_cash_advance
        FROM cash_advance
        WHERE COALESCE(pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
          $cashAdvanceDateFilter
        GROUP BY budget_code, pr_dealer, pr_department
    ) ca ON b.budget_code = ca.budget_code
        AND b.budget_dealer = ca.pr_dealer
        AND UPPER(TRIM(b.budget_department)) = UPPER(TRIM(ca.pr_department))
    LEFT JOIN (
        SELECT
            budget_lookup.budget_id,
            SUM(CASE WHEN l.request_type = 'Liquidation' THEN l.liquidation_amount ELSE 0 END) AS total_liquidation,
            SUM(CASE WHEN l.request_type = 'Request for Payment' THEN l.liquidation_amount ELSE 0 END) AS total_rfp,
            SUM(CASE WHEN l.request_type = 'Cash Advance' THEN l.liquidation_amount ELSE 0 END) AS total_cash_advance
        FROM liquidations l
        INNER JOIN budgets budget_lookup
            ON budget_lookup.budget_id = l.budget_id
            OR budget_lookup.budget_code = l.budget_code
        WHERE COALESCE(l.status, '') <> 'Rejected'
          $liquidationDateFilter
        GROUP BY budget_lookup.budget_id
    ) lr ON b.budget_id = lr.budget_id
    WHERE b.budget_department IS NOT NULL
      AND b.budget_department <> ''
";

if ($department !== '') {
    $sql .= ' AND UPPER(TRIM(b.budget_department)) = UPPER(TRIM(:department))';
    $params[':department'] = $department;
}

$sql .= "
    GROUP BY b.budget_department
    ORDER BY b.budget_department ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);

echo json_encode([
    'can_view_all' => $canViewAllDepartments,
    'session_department' => $sessionDepartment,
    'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
]);
