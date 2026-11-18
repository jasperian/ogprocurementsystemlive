<?php
require '../../init.php';
require __DIR__ . '/purchasing_productivity_helper.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $filters = productivityFilters($_GET);
    $rows = fetchProductivityRows($pdo, $filters);
    $totals = [
        'total_prs' => 0,
        'completed_prs' => 0,
        'total_pos' => 0,
        'total_amount' => 0.0,
    ];
    foreach ($rows as $row) {
        $totals['total_prs'] += (int)$row['total_prs'];
        $totals['completed_prs'] += (int)$row['completed_prs'];
        $totals['total_pos'] += (int)$row['total_pos'];
        $totals['total_amount'] += (float)$row['total_amount'];
    }
    $totals['completion_rate'] = $totals['total_prs'] > 0
        ? round(($totals['completed_prs'] / $totals['total_prs']) * 100, 1)
        : 0;

    echo json_encode(['success' => true, 'filters' => $filters, 'totals' => $totals, 'rows' => $rows]);
} catch (Throwable $e) {
    error_log('Purchasing productivity report error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load the productivity report.']);
}

