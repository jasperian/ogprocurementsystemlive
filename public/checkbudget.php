<?php
require '../init.php';

$requiredColumns = [
    'budgets' => ['budget_id', 'budget_code', 'budget_department', 'budget_dealer'],
    'purchase_order' => ['po_number', 'pr_number', 'po_date', 'amount_purchased', 'po_dealer'],
    'canvass_sheet_batches' => ['pr_number', 'budget_code'],
    'cash_advance' => ['pr_number', 'request_type', 'purchase_requisition_number', 'pr_status', 'pr_department', 'budget_code', 'pr_dealer', 'ca_amount', 'ca_date', 'pr_date', 'remarks', 'liquidation_remarks'],
    'liquidations' => ['liquidation_id', 'liquidation_number', 'request_type', 'dealer', 'department', 'budget_id', 'budget_code', 'liquidation_amount', 'liquidation_date', 'status', 'source_cash_advance_number', 'remarks', 'liquidation_remarks'],
];

function h($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function fetchRows(PDO $pdo, string $sql, array $params = []): array
{
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC), 'error' => ''];
    } catch (Throwable $e) {
        return ['ok' => false, 'rows' => [], 'error' => $e->getMessage()];
    }
}

function hasTable(PDO $pdo, string $table): bool
{
    $result = fetchRows($pdo, "
        SELECT COUNT(*) AS found
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = :table
    ", [':table' => $table]);

    return $result['ok'] && (int)($result['rows'][0]['found'] ?? 0) > 0;
}

function hasColumns(PDO $pdo, string $table, array $columns): bool
{
    if (!hasTable($pdo, $table)) {
        return false;
    }

    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $result = fetchRows($pdo, "
        SELECT COUNT(*) AS found
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = ?
          AND COLUMN_NAME IN ($placeholders)
    ", array_merge([$table], $columns));

    return $result['ok'] && (int)($result['rows'][0]['found'] ?? 0) === count($columns);
}

function renderRows(array $result, array $columns, string $emptyMessage = 'No rows found.'): void
{
    if (!$result['ok']) {
        echo '<div class="alert error">' . h($result['error']) . '</div>';
        return;
    }

    if (!$result['rows']) {
        echo '<div class="alert ok">' . h($emptyMessage) . '</div>';
        return;
    }

    echo '<div class="table-wrap"><table><thead><tr>';
    foreach ($columns as $column) {
        echo '<th>' . h($column) . '</th>';
    }
    echo '</tr></thead><tbody>';

    foreach ($result['rows'] as $row) {
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<td>' . h($row[$column] ?? '') . '</td>';
        }
        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

$databaseName = $pdo->query('SELECT DATABASE()')->fetchColumn();

$columnRows = fetchRows($pdo, "
    SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE, IS_NULLABLE
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
    ORDER BY TABLE_NAME, ORDINAL_POSITION
");

$tableStatus = [];
foreach ($requiredColumns as $table => $columns) {
    $existing = [];
    foreach ($columnRows['rows'] as $row) {
        if ($row['TABLE_NAME'] === $table && in_array($row['COLUMN_NAME'], $columns, true)) {
            $existing[] = $row['COLUMN_NAME'];
        }
    }

    $missing = array_values(array_diff($columns, $existing));
    $tableStatus[] = [
        'table_name' => $table,
        'expected_columns' => count($columns),
        'found_columns' => count($existing),
        'missing_columns' => $missing ? implode(', ', $missing) : '-',
        'status' => $missing ? 'MISSING COLUMNS' : 'OK',
    ];
}

$budgetIdentity = hasColumns($pdo, 'budgets', $requiredColumns['budgets'])
    ? fetchRows($pdo, "
        SELECT
            COUNT(*) AS total_budgets,
            SUM(CASE WHEN budget_id IS NULL OR budget_id = 0 THEN 1 ELSE 0 END) AS missing_budget_id,
            SUM(CASE WHEN budget_code IS NULL OR TRIM(budget_code) = '' THEN 1 ELSE 0 END) AS missing_budget_code,
            SUM(CASE WHEN budget_department IS NULL OR TRIM(budget_department) = '' THEN 1 ELSE 0 END) AS missing_budget_department,
            SUM(CASE WHEN budget_dealer IS NULL OR TRIM(budget_dealer) = '' THEN 1 ELSE 0 END) AS missing_budget_dealer
        FROM budgets
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets table or one of its required columns is missing.'];

$duplicates = hasColumns($pdo, 'budgets', $requiredColumns['budgets'])
    ? fetchRows($pdo, "
        SELECT budget_code, budget_department, budget_dealer, COUNT(*) AS duplicate_count
        FROM budgets
        WHERE budget_code IS NOT NULL
          AND TRIM(budget_code) <> ''
        GROUP BY budget_code, budget_department, budget_dealer
        HAVING COUNT(*) > 1
        ORDER BY duplicate_count DESC, budget_code
        LIMIT 50
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets table or one of its required columns is missing.'];

$canvassMismatches = hasColumns($pdo, 'budgets', $requiredColumns['budgets']) && hasColumns($pdo, 'canvass_sheet_batches', $requiredColumns['canvass_sheet_batches'])
    ? fetchRows($pdo, "
        SELECT csb.budget_code, COUNT(*) AS unmatched_canvass_rows
        FROM canvass_sheet_batches csb
        LEFT JOIN budgets b
            ON CAST(b.budget_id AS CHAR) = TRIM(csb.budget_code)
            OR b.budget_code = TRIM(csb.budget_code)
        WHERE csb.budget_code IS NOT NULL
          AND TRIM(csb.budget_code) <> ''
          AND b.budget_id IS NULL
        GROUP BY csb.budget_code
        ORDER BY unmatched_canvass_rows DESC, csb.budget_code
        LIMIT 50
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets or canvass_sheet_batches required columns are missing.'];

$cashAdvanceMismatches = hasColumns($pdo, 'budgets', $requiredColumns['budgets']) && hasColumns($pdo, 'cash_advance', ['budget_code', 'pr_dealer', 'pr_department'])
    ? fetchRows($pdo, "
        SELECT ca.budget_code, ca.pr_dealer, ca.pr_department, COUNT(*) AS unmatched_cash_advance_rows
        FROM cash_advance ca
        LEFT JOIN budgets b
            ON b.budget_code = ca.budget_code
            AND b.budget_dealer = ca.pr_dealer
            AND UPPER(TRIM(b.budget_department)) = UPPER(TRIM(ca.pr_department))
        WHERE ca.budget_code IS NOT NULL
          AND TRIM(ca.budget_code) <> ''
          AND b.budget_id IS NULL
        GROUP BY ca.budget_code, ca.pr_dealer, ca.pr_department
        ORDER BY unmatched_cash_advance_rows DESC, ca.budget_code
        LIMIT 50
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets or cash_advance required columns are missing.'];

$liquidationMismatches = hasColumns($pdo, 'budgets', $requiredColumns['budgets']) && hasColumns($pdo, 'liquidations', ['budget_id', 'budget_code', 'dealer', 'department'])
    ? fetchRows($pdo, "
        SELECT l.budget_id, l.budget_code, l.dealer, l.department, COUNT(*) AS unmatched_liquidation_rows
        FROM liquidations l
        LEFT JOIN budgets b
            ON b.budget_id = l.budget_id
            OR b.budget_code = l.budget_code
        WHERE b.budget_id IS NULL
        GROUP BY l.budget_id, l.budget_code, l.dealer, l.department
        ORDER BY unmatched_liquidation_rows DESC, l.budget_code
        LIMIT 50
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets or liquidations required columns are missing.'];

$sampleBudgets = hasColumns($pdo, 'budgets', ['budget_id', 'budget_code', 'budget_activity', 'budget_department', 'budget_dealer', 'approved_budget'])
    ? fetchRows($pdo, "
        SELECT budget_id, budget_code, budget_activity, budget_department, budget_dealer, approved_budget
        FROM budgets
        WHERE budget_code IS NOT NULL
          AND TRIM(budget_code) <> ''
        ORDER BY budget_department, budget_code
        LIMIT 20
    ")
    : ['ok' => false, 'rows' => [], 'error' => 'The budgets sample columns are missing.'];
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Budget Transaction Diagnostics</title>
    <style>
        body { background: #f4f6f9; color: #212529; font-family: Arial, sans-serif; margin: 0; padding: 24px; }
        h1 { margin: 0 0 6px; font-size: 24px; }
        h2 { border-left: 4px solid #0d6efd; font-size: 17px; margin: 24px 0 12px; padding-left: 10px; }
        .muted { color: #6c757d; margin-bottom: 18px; }
        .card { background: #fff; border-radius: 8px; box-shadow: 0 4px 16px rgba(0,0,0,.06); margin-bottom: 18px; padding: 18px; }
        .alert { border-radius: 6px; padding: 10px 12px; }
        .ok { background: #d1e7dd; color: #0f5132; }
        .error { background: #f8d7da; color: #842029; }
        .table-wrap { overflow-x: auto; }
        table { border-collapse: collapse; min-width: 720px; width: 100%; }
        th, td { border: 1px solid #dee2e6; font-size: 13px; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f8f9fa; font-weight: 700; }
        .status-ok { color: #198754; font-weight: 700; }
        .status-bad { color: #dc3545; font-weight: 700; }
        code { background: #f1f3f5; border-radius: 4px; padding: 2px 5px; }
    </style>
</head>
<body>
    <h1>Budget Transaction Diagnostics</h1>
    <div class="muted">Connected database: <code><?= h($databaseName) ?></code>. This page is read-only.</div>

    <div class="card">
        <h2>Required Columns</h2>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Expected</th>
                        <th>Found</th>
                        <th>Missing Columns</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tableStatus as $row): ?>
                        <tr>
                            <td><?= h($row['table_name']) ?></td>
                            <td><?= h($row['expected_columns']) ?></td>
                            <td><?= h($row['found_columns']) ?></td>
                            <td><?= h($row['missing_columns']) ?></td>
                            <td class="<?= $row['status'] === 'OK' ? 'status-ok' : 'status-bad' ?>"><?= h($row['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <h2>Budget Identity Blanks</h2>
        <?php renderRows($budgetIdentity, ['total_budgets', 'missing_budget_id', 'missing_budget_code', 'missing_budget_department', 'missing_budget_dealer']); ?>
    </div>

    <div class="card">
        <h2>Duplicate Budget Code + Department + Dealer</h2>
        <?php renderRows($duplicates, ['budget_code', 'budget_department', 'budget_dealer', 'duplicate_count'], 'No duplicates found.'); ?>
    </div>

    <div class="card">
        <h2>Canvass Rows Not Matching Budgets</h2>
        <?php renderRows($canvassMismatches, ['budget_code', 'unmatched_canvass_rows'], 'No mismatches found.'); ?>
    </div>

    <div class="card">
        <h2>Cash Advance Rows Not Matching Budgets</h2>
        <?php renderRows($cashAdvanceMismatches, ['budget_code', 'pr_dealer', 'pr_department', 'unmatched_cash_advance_rows'], 'No mismatches found.'); ?>
    </div>

    <div class="card">
        <h2>Liquidation Rows Not Matching Budgets</h2>
        <?php renderRows($liquidationMismatches, ['budget_id', 'budget_code', 'dealer', 'department', 'unmatched_liquidation_rows'], 'No mismatches found.'); ?>
    </div>

    <div class="card">
        <h2>Sample Budget Rows</h2>
        <?php renderRows($sampleBudgets, ['budget_id', 'budget_code', 'budget_activity', 'budget_department', 'budget_dealer', 'approved_budget']); ?>
    </div>
</body>
</html>
