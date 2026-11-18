<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include '../../includes/db.php';

header('Content-Type: application/json');

$budgetId = (int)($_GET['budget_id'] ?? 0);
$budgetCode = trim($_GET['budget_code'] ?? '');
$department = trim($_GET['department'] ?? '');
$dealer = trim($_GET['dealer'] ?? '');
$start = $_GET['start'] ?? '';
$end = $_GET['end'] ?? '';
$sessionDepartment = trim($_SESSION['department'] ?? '');
$sessionDealer = trim($_SESSION['dealer'] ?? '');
$canViewAllDepartments = in_array(strtoupper($sessionDepartment), ['PURCHASING', 'EO'], true);

if ($budgetId <= 0 && $budgetCode === '') {
    echo json_encode(['success' => false, 'message' => 'Missing budget code.', 'data' => []]);
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

$budgetSql = "
    SELECT budget_id, budget_code, budget_activity, budget_department, budget_dealer
    FROM budgets
    WHERE 1 = 1
";
$budgetParams = [];

if ($budgetId > 0) {
    $budgetSql .= ' AND budget_id = :budget_id';
    $budgetParams[':budget_id'] = $budgetId;
} else {
    $budgetSql .= ' AND budget_code = :budget_code';
    $budgetParams[':budget_code'] = $budgetCode;

    if ($department !== '') {
        $budgetSql .= ' AND UPPER(TRIM(budget_department)) = UPPER(TRIM(:department))';
        $budgetParams[':department'] = $department;
    }

    if ($dealer !== '') {
        $budgetSql .= ' AND UPPER(TRIM(budget_dealer)) = UPPER(TRIM(:dealer))';
        $budgetParams[':dealer'] = $dealer;
    }
}

if (!$canViewAllDepartments) {
    if ($sessionDepartment === '') {
        echo json_encode(['success' => true, 'budget' => null, 'data' => []]);
        exit;
    }

    $budgetSql .= ' AND UPPER(TRIM(budget_department)) = UPPER(TRIM(:session_department))';
    $budgetParams[':session_department'] = $sessionDepartment;
}

if ($sessionDealer !== '') {
    $budgetSql .= ' AND UPPER(TRIM(budget_dealer)) = UPPER(TRIM(:session_dealer))';
    $budgetParams[':session_dealer'] = $sessionDealer;
}

$budgetStmt = $pdo->prepare($budgetSql);
$budgetStmt->execute($budgetParams);
$budget = $budgetStmt->fetch(PDO::FETCH_ASSOC);

if (!$budget) {
    echo json_encode(['success' => false, 'message' => 'Budget code not found.', 'data' => []]);
    exit;
}

$budgetId = (int)$budget['budget_id'];

$dateFilters = [
    'po' => '',
    'ca' => '',
    'liq' => '',
];
$params = [
    ':budget_id_po' => $budgetId,
    ':budget_id_liq' => $budgetId,
    ':budget_code_ca' => $budget['budget_code'],
    ':budget_dealer_ca' => $budget['budget_dealer'],
    ':budget_department_ca' => $budget['budget_department'],
];

if ($start !== '' && $end !== '') {
    $dateFilters['po'] = ' AND DATE(po.po_date) BETWEEN :po_start AND :po_end';
    $dateFilters['ca'] = ' AND COALESCE(ca.ca_date, DATE(ca.pr_date)) BETWEEN :ca_start AND :ca_end';
    $dateFilters['liq'] = ' AND l.liquidation_date BETWEEN :liq_start AND :liq_end';
    $params[':po_start'] = $start;
    $params[':po_end'] = $end;
    $params[':ca_start'] = $start;
    $params[':ca_end'] = $end;
    $params[':liq_start'] = $start;
    $params[':liq_end'] = $end;
} elseif ($start !== '') {
    $dateFilters['po'] = ' AND DATE(po.po_date) >= :po_start';
    $dateFilters['ca'] = ' AND COALESCE(ca.ca_date, DATE(ca.pr_date)) >= :ca_start';
    $dateFilters['liq'] = ' AND l.liquidation_date >= :liq_start';
    $params[':po_start'] = $start;
    $params[':ca_start'] = $start;
    $params[':liq_start'] = $start;
} elseif ($end !== '') {
    $dateFilters['po'] = ' AND DATE(po.po_date) <= :po_end';
    $dateFilters['ca'] = ' AND COALESCE(ca.ca_date, DATE(ca.pr_date)) <= :ca_end';
    $dateFilters['liq'] = ' AND l.liquidation_date <= :liq_end';
    $params[':po_end'] = $end;
    $params[':ca_end'] = $end;
    $params[':liq_end'] = $end;
}

$sql = "
    SELECT *
    FROM (
        SELECT
            'Purchase Order' AS transaction_type,
            po.po_number AS reference_number,
            po.pr_number AS related_number,
            DATE(po.po_date) AS transaction_date,
            CAST(po.amount_purchased AS DECIMAL(15,2)) AS amount,
            '' AS status,
            po.po_dealer AS dealer,
            b.budget_department AS department,
            '' AS remarks,
            CONCAT('view_purchase_requisition?pr_number=', po.pr_number) AS detail_url
        FROM purchase_order po
        INNER JOIN canvass_sheet_batches csb ON csb.pr_number = po.pr_number
        INNER JOIN budgets b
            ON CAST(b.budget_id AS CHAR) = TRIM(csb.budget_code)
            OR b.budget_code = TRIM(csb.budget_code)
        WHERE b.budget_id = :budget_id_po
          {$dateFilters['po']}

        UNION ALL

        SELECT
            COALESCE(NULLIF(ca.request_type, ''), 'Cash Advance') AS transaction_type,
            ca.pr_number AS reference_number,
            ca.purchase_requisition_number AS related_number,
            COALESCE(ca.ca_date, DATE(ca.pr_date)) AS transaction_date,
            ca.ca_amount AS amount,
            ca.pr_status AS status,
            ca.pr_dealer AS dealer,
            ca.pr_department AS department,
            COALESCE(ca.liquidation_remarks, ca.remarks, '') AS remarks,
            CASE
                WHEN ca.request_type = 'Request for Payment' THEN CONCAT('print_cash_advance.php?ca_number=', ca.pr_number)
                ELSE CONCAT('print_cash_advance.php?ca_number=', ca.pr_number)
            END AS detail_url
        FROM cash_advance ca
        WHERE ca.budget_code = :budget_code_ca
          AND ca.pr_dealer = :budget_dealer_ca
          AND UPPER(TRIM(ca.pr_department)) = UPPER(TRIM(:budget_department_ca))
          AND COALESCE(ca.pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
          {$dateFilters['ca']}

        UNION ALL

        SELECT
            l.request_type AS transaction_type,
            l.liquidation_number AS reference_number,
            l.source_cash_advance_number AS related_number,
            l.liquidation_date AS transaction_date,
            l.liquidation_amount AS amount,
            l.status AS status,
            l.dealer AS dealer,
            l.department AS department,
            COALESCE(l.liquidation_remarks, l.remarks, '') AS remarks,
            CONCAT('print_liquidation.php?id=', l.liquidation_id) AS detail_url
        FROM liquidations l
        INNER JOIN budgets b
            ON b.budget_id = l.budget_id
            OR b.budget_code = l.budget_code
        WHERE b.budget_id = :budget_id_liq
          AND COALESCE(l.status, '') <> 'Rejected'
          {$dateFilters['liq']}
    ) transactions
    ORDER BY transaction_date DESC, reference_number DESC
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    echo json_encode([
        'success' => true,
        'budget' => $budget,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Unable to load transactions.', 'data' => []]);
}
