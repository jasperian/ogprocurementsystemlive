<?php
require '../../init.php';
require __DIR__ . '/../reports/purchasing_productivity_helper.php';

$filters = productivityFilters($_GET);
$rows = fetchProductivityRows($pdo, $filters);
$scope = $filters['group_by'] === 'tm' ? 'by_tm' : 'by_dealer';
$filename = 'purchasing_productivity_' . $scope . '_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');

$output = fopen('php://output', 'w');
fwrite($output, "\xEF\xBB\xBF");
fputcsv($output, ['Purchasing Productivity Report']);
fputcsv($output, ['Grouped by', $filters['group_by'] === 'tm' ? 'TM' : 'Dealer']);
fputcsv($output, ['Period', ($filters['start_date'] ?: 'All') . ' to ' . ($filters['end_date'] ?: 'All')]);
fputcsv($output, []);
fputcsv($output, [
    $filters['group_by'] === 'tm' ? 'TM' : 'Dealer',
    $filters['group_by'] === 'tm' ? 'Dealer' : 'Scope',
    'Total PRs', 'Completed PRs', 'Completion Rate', 'Total POs',
    'Suppliers', 'Cross-completed PRs', 'Purchase Amount', 'Average PR-to-PO Days'
]);
foreach ($rows as $row) {
    fputcsv($output, [
        $row['group_label'], $row['secondary_label'], $row['total_prs'], $row['completed_prs'],
        $row['completion_rate'] . '%', $row['total_pos'], $row['suppliers'], $row['cross_completed'],
        number_format((float)$row['total_amount'], 2, '.', ''), $row['avg_lead_days'] ?? ''
    ]);
}
fclose($output);
exit;

