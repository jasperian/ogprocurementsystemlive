<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$caNumber = trim($_POST['ca_number'] ?? '');
$newStatus = trim($_POST['new_status'] ?? '');
$sapDocumentNumber = trim($_POST['sap_document_number'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);
$position = (string)($_SESSION['position'] ?? '');
$department = (string)($_SESSION['department'] ?? '');

if ($caNumber === '' || $newStatus === '' || $userId <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Request number, status, and authenticated user are required.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT ca.*, (SELECT u.username FROM users u WHERE u.user_id = ca.created_by) AS creator_username FROM cash_advance ca WHERE ca.pr_number = :number FOR UPDATE');
    $stmt->execute([':number' => $caNumber]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Request not found.']);
        exit;
    }

    $expectedStatus = purchasingWorkflowNextStatus($request);
    if ($expectedStatus === null || $newStatus !== $expectedStatus) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Invalid status transition for this request type.']);
        exit;
    }

    if (!purchasingWorkflowActionAllowed($request, $position, $department, $userId)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'You are not authorized to perform this workflow action.']);
        exit;
    }

    $currentStatus = (string)$request['pr_status'];
    $requestType = (string)($request['request_type'] ?? 'Cash Advance');
    $requiresSap = purchasingWorkflowRequiresSap($request, $currentStatus, $newStatus);

    if ($requestType === 'Cash Advance' && $currentStatus === 'For PO'
        && (empty($request['po_file']) || (float)($request['po_amount'] ?? 0) <= 0)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Actual amount and liquidation attachment are required before submitting the liquidation.']);
        exit;
    }

    if ($requiresSap && ($sapDocumentNumber === '' || strlen($sapDocumentNumber) > 255)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'A valid SAP Document Number is required.']);
        exit;
    }

    $sets = ['pr_status = :status'];
    $params = [':status' => $newStatus, ':number' => $caNumber];

    if ($newStatus === 'Completed') {
        $sets[] = 'completed_by = :audit_user';
        $sets[] = 'completed_at = NOW()';
        if ($requestType === 'Request for Payment' && $currentStatus === 'CA Release') {
            $sets[] = 'for_po_by = :audit_user';
            $sets[] = 'for_po_at = NOW()';
        }
        $params[':audit_user'] = (string)$userId;
    } elseif ($currentStatus === 'CA Request') {
        $sets[] = 'ca_released_by = :audit_user';
        $sets[] = 'ca_released_at = NOW()';
        $params[':audit_user'] = (string)$userId;
    } elseif ($currentStatus === 'CA Release') {
        $sets[] = 'for_po_by = :audit_user';
        $sets[] = 'for_po_at = NOW()';
        $params[':audit_user'] = (string)$userId;
    } elseif ($currentStatus === 'For PO') {
        $sets[] = 'for_liquidation_by = :audit_user';
        $sets[] = 'for_liquidation_at = NOW()';
        $params[':audit_user'] = (string)$userId;
    } elseif ($currentStatus === 'For Liquidation') {
        $sets[] = 'liquidated_by = :audit_user';
        $sets[] = 'liquidated_at = NOW()';
        $params[':audit_user'] = (string)$userId;
    } elseif ($currentStatus === 'For GM Approval') {
        $sets[] = 'gm_approved_by = :audit_user';
        $sets[] = 'gm_approved_at = NOW()';
        $params[':audit_user'] = (string)$userId;
    }

    if ($requiresSap) {
        $sets[] = 'sap_document_number = :sap_document_number';
        $sets[] = 'sap_document_by = :sap_user';
        $sets[] = 'sap_document_at = NOW()';
        $params[':sap_document_number'] = $sapDocumentNumber;
        $params[':sap_user'] = (string)$userId;
    }

    $update = $pdo->prepare('UPDATE cash_advance SET ' . implode(', ', $sets) . ' WHERE pr_number = :number');
    $update->execute($params);

    if ($newStatus === 'Completed' && trim((string)($request['purchase_requisition_number'] ?? '')) !== '') {
        $prUpdate = $pdo->prepare("UPDATE purchase_requisition SET pr_status = 'Completed' WHERE pr_number = :pr_number AND COALESCE(pr_status, '') NOT IN ('Rejected', 'Cancelled')");
        $prUpdate->execute([':pr_number' => $request['purchase_requisition_number']]);
    }

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Request moved to ' . purchasingWorkflowStatusLabel($newStatus, $requestType) . '.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'danger', 'message' => 'Failed to update request: ' . $e->getMessage()]);
}
