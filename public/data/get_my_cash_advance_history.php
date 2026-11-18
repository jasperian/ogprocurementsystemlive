<?php
session_start();
header('Content-Type: application/json');
include '../../includes/db.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

$dealer = trim($_SESSION['dealer'] ?? '');
$userId = (int)$_SESSION['user_id'];
$scope = trim($_GET['scope'] ?? '');
$status = trim($_GET['status'] ?? '');
$requestType = trim($_GET['request_type'] ?? '');

try {
    $hasLegacyRfpLink = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'source_rfp_id'")->fetch(PDO::FETCH_ASSOC);
    $sql = "
        SELECT
            pr_number AS ca_number,
            request_type,
            ca_amount,
            pr_department,
            budget_code,
            purchase_requisition_number,
            pr_status,
            ca_date,
            activity_date,
            content_name,
            payable_to,
            requires_liquidation,
            liquidation_exemption_reason,
            liquidation_due_date,
            remarks,
            pr_date,
            ca_attachment_file,
            po_file,
            sap_document_number,
            created_at,
            " . ($hasLegacyRfpLink
                ? "EXISTS(SELECT 1 FROM cash_advance linked_rfp WHERE linked_rfp.source_rfp_id = cash_advance.pr_id)"
                : "0") . " AS has_linked_liquidation,
            (SELECT GROUP_CONCAT(caa.budget_code ORDER BY caa.allocation_id SEPARATOR ', ')
             FROM cash_advance_allocations caa WHERE caa.cash_advance_number = cash_advance.pr_number) AS allocation_budget_codes,
            (SELECT GROUP_CONCAT(caa.department ORDER BY caa.allocation_id SEPARATOR ', ')
             FROM cash_advance_allocations caa WHERE caa.cash_advance_number = cash_advance.pr_number) AS allocation_departments
        FROM cash_advance
        WHERE created_by = :user_id
    ";
    $params = [':user_id' => $userId];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $sql .= " AND pr_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }

    if ($scope === 'active') {
        $sql .= $hasLegacyRfpLink
            ? " AND (COALESCE(pr_status, '') NOT IN ('Completed', 'Cancelled')
                OR (request_type = 'Request for Payment' AND requires_liquidation = 1 AND pr_status = 'Completed'
                    AND NOT EXISTS (SELECT 1 FROM cash_advance linked_rfp WHERE linked_rfp.source_rfp_id = cash_advance.pr_id)))"
            : " AND COALESCE(pr_status, '') NOT IN ('Completed', 'Cancelled')";
    }
    if ($status !== '') {
        $sql .= " AND pr_status = :status";
        $params[':status'] = $status;
    }
    if (in_array($requestType, ['Cash Advance', 'Request for Payment', 'Liquidation'], true)) {
        $sql .= " AND request_type = :request_type";
        $params[':request_type'] = $requestType;
    }

    $sql .= " ORDER BY COALESCE(ca_date, pr_date) DESC, pr_number DESC LIMIT 500";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$row) {
        $row['display_status'] = purchasingWorkflowStatusLabel(
            (string)($row['pr_status'] ?? ''),
            (string)($row['request_type'] ?? 'Cash Advance')
        );
        $row['phase_label'] = purchasingWorkflowPhaseLabel($row);
        $nextStatus = purchasingWorkflowNextStatus($row);
        $row['next_stage'] = $nextStatus
            ? purchasingWorkflowStatusLabel($nextStatus, (string)($row['request_type'] ?? 'Cash Advance'))
            : 'None';
        if (($row['request_type'] ?? '') === 'Request for Payment'
            && ($row['pr_status'] ?? '') === 'Completed'
            && (int)($row['requires_liquidation'] ?? 0) === 1
            && (int)($row['has_linked_liquidation'] ?? 0) === 0) {
            $row['next_stage'] = 'Create Linked Liquidation';
        }
    }
    unset($row);

    echo json_encode([
        'status' => 'success',
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to load cash advance history.',
        'data' => []
    ]);
}
