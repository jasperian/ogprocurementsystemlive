<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$department = strtoupper(trim($_SESSION['department'] ?? ''));
$position = strtoupper(trim($_SESSION['position'] ?? ''));
$dealer = trim($_SESSION['dealer'] ?? '');
$allowed = in_array($department, ['PURCHASING', 'ACCOUNTING', 'FINANCE'], true)
    || in_array($position, ['GENERAL MANAGER', 'ADMINISTRATOR'], true);

if ($userId <= 0 || !$allowed) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'You cannot view Purchasing transactions.', 'data' => []]);
    exit;
}

try {
    $visibleRows = purchasingWorkflowVisibleRequests($pdo, $_SESSION);
    echo json_encode(['status' => 'success', 'data' => $visibleRows]);
} catch (Throwable $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Failed to load Purchasing transactions: ' . $e->getMessage(), 'data' => []]);
}
