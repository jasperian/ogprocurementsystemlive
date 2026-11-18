<?php

/** RFP completion does not settle a required liquidation; its completed child does. */
function budgetPendingRfpConditionSql(): string
{
    return "(l.request_type = 'Request for Payment' AND l.status <> 'Rejected'
        AND ((COALESCE(l.requires_liquidation, 0) <> 1 AND l.status <> 'Completed')
            OR (l.requires_liquidation = 1 AND NOT EXISTS (
                SELECT 1 FROM liquidations rfp_child
                WHERE rfp_child.source_rfp_id = l.liquidation_id
                  AND rfp_child.status = 'Completed'
            ))))";
}

/**
 * Report projections use the same settlement rules as the live budget balance.
 * The surrounding query owns date and budget filters. Settlement is checked
 * across all dates so a settled advance never becomes pending in an old period.
 */
function budgetReportLiquidationTotalsSql(): string
{
    return "
        SUM(CASE WHEN l.request_type = 'Liquidation'
            AND l.status = 'Completed'
            THEN CASE WHEN l.source_cash_advance_id IS NOT NULL OR l.source_rfp_id IS NOT NULL
                THEN COALESCE(l.actual_amount, l.liquidation_amount)
                ELSE l.liquidation_amount END ELSE 0 END) AS total_liquidation,
        SUM(CASE WHEN l.request_type = 'Request for Payment' AND l.status = 'Completed'
            AND COALESCE(l.requires_liquidation, 0) <> 1
            THEN l.liquidation_amount ELSE 0 END) AS total_rfp,
        SUM(CASE WHEN l.request_type = 'Cash Advance' AND l.status <> 'Rejected'
            AND NOT EXISTS (SELECT 1 FROM liquidations completed_child
                WHERE completed_child.source_cash_advance_id = l.liquidation_id
                  AND completed_child.status = 'Completed')
            THEN l.liquidation_amount ELSE 0 END) AS total_cash_advance,
        SUM(CASE WHEN " . budgetPendingRfpConditionSql() . "
                OR (l.status NOT IN ('Completed', 'Rejected')
                    AND l.request_type = 'Liquidation' AND l.source_cash_advance_id IS NULL
                    AND l.source_rfp_id IS NULL)
            THEN l.liquidation_amount ELSE 0 END) AS pending_requests
    ";
}

/**
 * Returns the live, non-duplicating budget balance used by transactional pages.
 *
 * Actual expenses are purchase orders and completed Liquidation/RFP records.
 * Pending expenses are active Cash Advances and non-rejected requests that have
 * not completed. A Cash Advance from `liquidations` is replaced by its completed
 * linked liquidation instead of counting both records.
 */
function budgetRemainingColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;

    if (!array_key_exists($key, $cache)) {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
        $stmt->execute([$column]);
        $cache[$key] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    }

    return $cache[$key];
}

function getUnifiedBudgetRemaining(
    PDO $pdo,
    string $budgetCode,
    string $department,
    string $dealer,
    bool $lockBudget = false,
    ?int $budgetId = null
): ?array {
    $budgetSql = "
        SELECT
            budget_id,
            budget_code,
            budget_activity,
            approved_budget,
            budget_department,
            budget_dealer
        FROM budgets
        WHERE UPPER(TRIM(budget_department)) = UPPER(TRIM(:department))
    ";
    $budgetParams = [':department' => $department];

    if ($budgetId !== null && $budgetId > 0) {
        $budgetSql .= " AND budget_id = :budget_id";
        $budgetParams[':budget_id'] = $budgetId;
    } else {
        $budgetSql .= " AND budget_code = :budget_code";
        $budgetParams[':budget_code'] = $budgetCode;
    }

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $budgetSql .= " AND UPPER(TRIM(budget_dealer)) = UPPER(TRIM(:dealer))";
        $budgetParams[':dealer'] = $dealer;
    }

    if ($lockBudget) {
        $budgetSql .= " FOR UPDATE";
    }

    $budgetStmt = $pdo->prepare($budgetSql);
    $budgetStmt->execute($budgetParams);
    $budgets = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($budgets) === 0) {
        return null;
    }

    $approvedBudget = 0.0;
    $budgetIds = [];
    foreach ($budgets as $budget) {
        $approvedBudget += (float)$budget['approved_budget'];
        $budgetIds[] = (int)$budget['budget_id'];
    }

    $resolvedBudgetCode = (string)$budgets[0]['budget_code'];
    $resolvedDepartment = (string)$budgets[0]['budget_department'];
    $calculationDealer = $dealer;

    // A budget-id lookup must stay inside that budget's dealer even for OG users.
    if ($budgetId !== null && $budgetId > 0) {
        $calculationDealer = (string)$budgets[0]['budget_dealer'];
    }

    $budgetIdPlaceholders = [];
    $budgetIdParams = [];
    foreach ($budgetIds as $index => $resolvedBudgetId) {
        $placeholder = ':budget_id_' . $index;
        $budgetIdPlaceholders[] = $placeholder;
        $budgetIdParams[$placeholder] = $resolvedBudgetId;
    }

    $cashAdvanceHasBudgetId = budgetRemainingColumnExists($pdo, 'cash_advance', 'budget_id');
    $cashAdvanceBudgetFilter = "budget_code = :budget_code AND UPPER(TRIM(pr_department)) = UPPER(TRIM(:department))";
    if ($cashAdvanceHasBudgetId) {
        $cashAdvanceBudgetFilter = "
            budget_id IN (" . implode(',', $budgetIdPlaceholders) . ")
            OR (
                budget_id IS NULL
                AND budget_code = :budget_code
                AND UPPER(TRIM(pr_department)) = UPPER(TRIM(:department))
            )
        ";
    }

    $poParams = $budgetIdParams + [
        ':po_budget_code' => $resolvedBudgetCode,
        ':po_department' => $resolvedDepartment
    ];
    $poSql = "
        SELECT IFNULL(SUM(po.amount_purchased), 0)
        FROM purchase_order po
        INNER JOIN canvass_sheet_batches csb ON csb.pr_number = po.pr_number
        INNER JOIN purchase_requisition pr ON pr.pr_number = po.pr_number
        WHERE (
            TRIM(csb.budget_code) = :po_budget_code
            OR CAST(csb.budget_code AS UNSIGNED) IN (" . implode(',', $budgetIdPlaceholders) . ")
        )
          AND UPPER(TRIM(pr.pr_department)) = UPPER(TRIM(:po_department))
    ";

    if ($calculationDealer !== '' && strtoupper($calculationDealer) !== 'OG') {
        $poSql .= " AND UPPER(TRIM(pr.pr_dealer)) = UPPER(TRIM(:po_dealer))";
        $poParams[':po_dealer'] = $calculationDealer;
    }

    $poStmt = $pdo->prepare($poSql);
    $poStmt->execute($poParams);
    $poExpenses = (float)$poStmt->fetchColumn();

    $hasAllocationsTable = (bool)$pdo->query("SHOW TABLES LIKE 'cash_advance_allocations'")->fetchColumn();
    $allocationExclusion = $hasAllocationsTable
        ? " AND NOT EXISTS (SELECT 1 FROM cash_advance_allocations caa_parent WHERE caa_parent.cash_advance_number = cash_advance.pr_number)"
        : '';

    $cashAdvanceSql = "
        SELECT IFNULL(SUM(ca_amount), 0)
        FROM cash_advance
        WHERE ({$cashAdvanceBudgetFilter})
          AND COALESCE(pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
          {$allocationExclusion}
    ";
    $cashAdvanceParams = $budgetIdParams + [
        ':budget_code' => $resolvedBudgetCode,
        ':department' => $resolvedDepartment
    ];

    if ($calculationDealer !== '' && strtoupper($calculationDealer) !== 'OG') {
        $cashAdvanceSql .= " AND UPPER(TRIM(pr_dealer)) = UPPER(TRIM(:dealer))";
        $cashAdvanceParams[':dealer'] = $calculationDealer;
    }

    $cashAdvanceStmt = $pdo->prepare($cashAdvanceSql);
    $cashAdvanceStmt->execute($cashAdvanceParams);
    $activeCashAdvance = (float)$cashAdvanceStmt->fetchColumn();

    if ($hasAllocationsTable) {
        $allocationSql = "
            SELECT IFNULL(SUM(caa.allocation_amount), 0)
            FROM cash_advance_allocations caa
            INNER JOIN cash_advance ca ON ca.pr_number = caa.cash_advance_number
            WHERE caa.budget_id IN (" . implode(',', $budgetIdPlaceholders) . ")
              AND COALESCE(ca.pr_status, '') NOT IN ('Completed', 'Rejected', 'Cancelled')
        ";
        $allocationParams = $budgetIdParams;
        if ($calculationDealer !== '' && strtoupper($calculationDealer) !== 'OG') {
            $allocationSql .= " AND UPPER(TRIM(ca.pr_dealer)) = UPPER(TRIM(:allocation_dealer))";
            $allocationParams[':allocation_dealer'] = $calculationDealer;
        }
        $allocationStmt = $pdo->prepare($allocationSql);
        $allocationStmt->execute($allocationParams);
        $activeCashAdvance += (float)$allocationStmt->fetchColumn();
    }

    $liquidationParams = $budgetIdParams + [
        ':liquidation_budget_code' => $resolvedBudgetCode
    ];
    $liquidationSql = "
        SELECT
            IFNULL(SUM(CASE
                WHEN l.request_type = 'Liquidation'
                  AND l.source_cash_advance_id IS NULL
                 AND l.status = 'Completed'
                THEN COALESCE(l.actual_amount, l.liquidation_amount) ELSE 0 END), 0) AS completed_liquidations,
            IFNULL(SUM(CASE
                WHEN l.request_type = 'Request for Payment'
                 AND COALESCE(l.requires_liquidation, 0) <> 1
                 AND l.status = 'Completed'
                THEN l.liquidation_amount ELSE 0 END), 0) AS completed_rfp,
            IFNULL(SUM(CASE
                WHEN l.request_type = 'Liquidation'
                 AND l.source_cash_advance_id IS NOT NULL
                 AND l.status = 'Completed'
                THEN COALESCE(l.actual_amount, l.liquidation_amount) ELSE 0 END), 0) AS completed_ca_liquidations,
            IFNULL(SUM(CASE
                WHEN l.request_type = 'Liquidation'
                  AND l.source_cash_advance_id IS NULL AND l.source_rfp_id IS NULL
                 AND l.status NOT IN ('Completed', 'Rejected')
                THEN l.liquidation_amount ELSE 0 END), 0) AS pending_liquidations,
            IFNULL(SUM(CASE
                WHEN " . budgetPendingRfpConditionSql() . "
                THEN l.liquidation_amount ELSE 0 END), 0) AS pending_rfp,
            IFNULL(SUM(CASE
                WHEN l.request_type = 'Cash Advance'
                 AND l.status <> 'Rejected'
                 AND NOT EXISTS (
                     SELECT 1
                     FROM liquidations completed_child
                     WHERE completed_child.source_cash_advance_id = l.liquidation_id
                       AND completed_child.status = 'Completed'
                 )
                THEN l.liquidation_amount ELSE 0 END), 0) AS reserved_cash_advances
        FROM liquidations l
        WHERE (
            l.budget_id IN (" . implode(',', $budgetIdPlaceholders) . ")
            OR (l.budget_id IS NULL AND l.budget_code = :liquidation_budget_code)
        )
          AND COALESCE(l.status, '') <> 'Rejected'
    ";

    if ($calculationDealer !== '' && strtoupper($calculationDealer) !== 'OG') {
        $liquidationSql .= " AND UPPER(TRIM(l.dealer)) = UPPER(TRIM(:liquidation_dealer))";
        $liquidationParams[':liquidation_dealer'] = $calculationDealer;
    }

    $liquidationStmt = $pdo->prepare($liquidationSql);
    $liquidationStmt->execute($liquidationParams);
    $liquidationSummary = $liquidationStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    $completedLiquidations = (float)($liquidationSummary['completed_liquidations'] ?? 0);
    $completedRfp = (float)($liquidationSummary['completed_rfp'] ?? 0);
    $completedCashAdvanceLiquidations = (float)($liquidationSummary['completed_ca_liquidations'] ?? 0);
    $pendingLiquidations = (float)($liquidationSummary['pending_liquidations'] ?? 0);
    $pendingRfp = (float)($liquidationSummary['pending_rfp'] ?? 0);
    $reservedLiquidationCashAdvances = (float)($liquidationSummary['reserved_cash_advances'] ?? 0);

    $actualExpenses = $poExpenses
        + $completedLiquidations
        + $completedRfp
        + $completedCashAdvanceLiquidations;
    $pendingLiquidationRequests = $pendingLiquidations
        + $pendingRfp
        + $reservedLiquidationCashAdvances;
    $pendingExpenses = $activeCashAdvance + $pendingLiquidationRequests;
    $totalExpenses = $actualExpenses + $pendingExpenses;
    $remainingBudget = $approvedBudget - $totalExpenses;
    $budgetUtilization = $approvedBudget > 0 ? ($totalExpenses / $approvedBudget) * 100 : 0.0;

    return [
        'budget_id' => count($budgets) === 1 ? (int)$budgets[0]['budget_id'] : null,
        'budget_code' => $resolvedBudgetCode,
        'budget_activity' => (string)($budgets[0]['budget_activity'] ?? ''),
        'approved_budget' => $approvedBudget,
        'po_expenses' => $poExpenses,
        'completed_liquidations' => $completedLiquidations,
        'completed_rfp' => $completedRfp,
        'completed_cash_advance_liquidations' => $completedCashAdvanceLiquidations,
        'active_cash_advance' => $activeCashAdvance,
        'pending_liquidations' => $pendingLiquidations,
        'pending_rfp' => $pendingRfp,
        'reserved_liquidation_cash_advances' => $reservedLiquidationCashAdvances,
        'actual_expenses' => $actualExpenses,
        'pending_expenses' => $pendingExpenses,
        'total_expenses' => $totalExpenses,
        'remaining_budget' => $remainingBudget,
        'budget_utilization' => $budgetUtilization,

        // Compatibility aliases for existing API consumers during migration.
        'actual_expense' => $actualExpenses,
        'committed_expenses' => $pendingExpenses,
        'committed_liquidations' => $pendingLiquidationRequests
    ];
}

function getUnifiedBudgetRemainingById(
    PDO $pdo,
    int $budgetId,
    string $department,
    string $dealer,
    bool $lockBudget = false
): ?array {
    return getUnifiedBudgetRemaining($pdo, '', $department, $dealer, $lockBudget, $budgetId);
}

/**
 * Backward-compatible name. All callers now receive the unified calculation.
 */
function getCashAdvanceBudgetRemaining(
    PDO $pdo,
    string $budgetCode,
    string $department,
    string $dealer,
    bool $unusedUsePurchaseOrderExpenses = true,
    bool $unusedIncludeLiquidationRequests = true
): ?array {
    return getUnifiedBudgetRemaining($pdo, $budgetCode, $department, $dealer);
}
