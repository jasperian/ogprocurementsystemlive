<?php
include 'includes/db.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

function columnExists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE ?");
    $stmt->execute([$column]);
    return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

if (!columnExists($pdo, 'department_pic_assignments', 'series_prefix')) {
    $pdo->exec("
        ALTER TABLE department_pic_assignments
        ADD COLUMN series_prefix VARCHAR(20) NULL AFTER final_approver_user_id
    ");
}

if (!columnExists($pdo, 'liquidations', 'series_prefix')) {
    $pdo->exec("
        ALTER TABLE liquidations
        ADD COLUMN series_prefix VARCHAR(20) NULL AFTER liquidation_number
    ");
}

$routeStmt = $pdo->query("
    SELECT assignment_id, dealer
    FROM department_pic_assignments
    WHERE series_prefix IS NULL
       OR TRIM(series_prefix) = ''
");
$routeUpdateStmt = $pdo->prepare("
    UPDATE department_pic_assignments
    SET series_prefix = :series_prefix
    WHERE assignment_id = :assignment_id
");
foreach ($routeStmt->fetchAll(PDO::FETCH_ASSOC) as $route) {
    $seriesPrefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper((string)$route['dealer']));
    if ($seriesPrefix === '') {
        $seriesPrefix = 'DLR';
    }
    $routeUpdateStmt->execute([
        ':series_prefix' => $seriesPrefix,
        ':assignment_id' => (int)$route['assignment_id'],
    ]);
}

$pdo->exec("
    UPDATE liquidations
    SET series_prefix = SUBSTRING_INDEX(liquidation_number, '-', 1)
    WHERE (series_prefix IS NULL OR TRIM(series_prefix) = '')
      AND liquidation_number LIKE '%-%'
");

echo "Department PIC series prefixes are ready.\n";
