<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';
require_once __DIR__ . '/budget_access_helper.php';

liquidation_require_department_encoder();

$budgetId = (int)($_GET['budget_id'] ?? 0);
$editingId = (int)($_GET['editing_id'] ?? 0);
$department = trim($_SESSION['department'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');

if ($budgetId <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Budget code is required.']);
    exit;
}

try {
    ensureBudgetUserAccessTable($pdo);
    $allowedBudget = budgetAccessGetAllowedBudget(
        $pdo,
        $budgetId,
        (int)$_SESSION['user_id'],
        $department,
        $dealer
    );

    if (!$allowedBudget) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget is not allowed for your department/dealer.']);
        exit;
    }

    $monitoring = liquidation_get_budget_monitoring(
        $pdo,
        $budgetId,
        $allowedBudget['budget_department'],
        $allowedBudget['budget_dealer']
    );
    if (!$monitoring) {
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget could not be loaded.']);
        exit;
    }

    if ($editingId > 0) {
        $editStmt = $pdo->prepare("
            SELECT budget_id, liquidation_amount, status, created_by, dealer, department, source_cash_advance_id, request_type, requires_liquidation
            FROM liquidations
            WHERE liquidation_id = :liquidation_id
            LIMIT 1
        ");
        $editStmt->execute([':liquidation_id' => $editingId]);
        $editRequest = $editStmt->fetch(PDO::FETCH_ASSOC);

        if (!$editRequest
            || (int)$editRequest['created_by'] !== (int)$_SESSION['user_id']
            || (string)$editRequest['dealer'] !== $dealer
            || strtoupper(trim((string)$editRequest['department'])) !== strtoupper($department)
            || !in_array($editRequest['status'], ['For Department Manager Approval', 'Rejected'], true)
            || !empty($editRequest['source_cash_advance_id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'This request can no longer be edited.']);
            exit;
        }

        if ($editRequest['status'] !== 'Rejected' && (int)$editRequest['budget_id'] === $budgetId) {
            $originalAmount = (float)$editRequest['liquidation_amount'];
            $monitoring['remaining_budget'] = (float)$monitoring['remaining_budget'] + $originalAmount;
            $monitoring['pending_expenses'] = max(0, (float)$monitoring['pending_expenses'] - $originalAmount);
            $monitoring['total_expenses'] = max(0, (float)$monitoring['total_expenses'] - $originalAmount);
            $monitoring['committed_expenses'] = $monitoring['pending_expenses'];
            $monitoring['committed_liquidations'] = max(0, (float)$monitoring['committed_liquidations'] - $originalAmount);
            $monitoring['budget_utilization'] = (float)$monitoring['approved_budget'] > 0
                ? ((float)$monitoring['total_expenses'] / (float)$monitoring['approved_budget']) * 100
                : 0.0;
        }
    }

    echo json_encode(['status' => 'success', 'data' => $monitoring]);
} catch (Exception $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Failed to load budget monitoring.']);
}
