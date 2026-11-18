<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';

liquidation_require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$liquidationId = (int)($_POST['liquidation_id'] ?? 0);
$action = trim($_POST['action'] ?? '');
$reason = trim($_POST['rejection_reason'] ?? '');
$sapDocumentNumber = trim($_POST['sap_document_number'] ?? '');
$userId = (int)$_SESSION['user_id'];
$dealer = trim($_SESSION['dealer'] ?? '');

if ($liquidationId <= 0 || !in_array($action, ['manager_approve', 'approve', 'reject', 'save_sap', 'complete', 'process_liquidation'], true)) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid liquidation action.']);
    exit;
}

if ($action === 'manager_approve' && !liquidation_is_department_head()) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Department Managers can endorse requests to final approval.']);
    exit;
}

if ($action === 'approve' && !liquidation_can_final_approve()) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only the assigned final approver can approve liquidations.']);
    exit;
}

if ($action === 'reject' && !liquidation_can_final_approve() && !liquidation_is_department_head()) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Department Managers or assigned final approvers can reject requests.']);
    exit;
}

if ($action === 'complete' && !liquidation_is_accounting_user()) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Accounting/Finance can complete or release approved requests.']);
    exit;
}

if ($action === 'process_liquidation' && !liquidation_is_department_head()) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Managers can process Cash Advance liquidations.']);
    exit;
}

if ($action === 'reject' && $reason === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Rejection reason is required.']);
    exit;
}

if ($action === 'save_sap' && $sapDocumentNumber === '') {
    echo json_encode(['status' => 'danger', 'message' => 'SAP Document Number is required.']);
    exit;
}

if ($action === 'save_sap' && strlen($sapDocumentNumber) > 255) {
    echo json_encode(['status' => 'danger', 'message' => 'SAP Document Number must be 255 characters or less.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $sql = "SELECT * FROM liquidations WHERE liquidation_id = :id FOR UPDATE";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $liquidationId]);
    $liquidation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$liquidation) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Liquidation not found.']);
        exit;
    }

    if ($dealer !== '' && strtoupper($dealer) !== 'OG' && $liquidation['dealer'] !== $dealer) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'You can only update liquidations for your dealer.']);
        exit;
    }

    $requestLabel = $liquidation['request_type'] ?? 'Liquidation';

    if ($action === 'save_sap'
        && !liquidation_can_encode_sap_for_dealer($pdo, (string)($liquidation['dealer'] ?? ''), $userId)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Only the assigned SAP Document Encoder can perform this action.']);
        exit;
    }

    if ($action === 'manager_approve') {
        if (!liquidation_department_approval_is_assigned_to_current_user($liquidation)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'This request is assigned to another Department Manager approver.']);
            exit;
        }

        if ($liquidation['status'] !== 'For Department Manager Approval') {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Only requests pending Department Manager approval can be endorsed.']);
            exit;
        }
    }

    if ($action === 'reject' && $liquidation['status'] === 'For Department Manager Approval') {
        if (!liquidation_is_department_head() || !liquidation_department_approval_is_assigned_to_current_user($liquidation)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Only the assigned Department Manager can reject this request.']);
            exit;
        }
    } elseif (in_array($action, ['approve', 'reject'], true)) {
        if ($liquidation['status'] !== 'For GM Approval') {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Only pending liquidations can be approved or rejected.']);
            exit;
        }

        $assignedFinalApproverId = (int)($liquidation['final_approver_user_id'] ?? 0);
        $isAssignedFinalApprover = $assignedFinalApproverId > 0 && $assignedFinalApproverId === $userId;
        $isLegacyGmApprover = $assignedFinalApproverId === 0 && liquidation_is_general_manager();
        if (!$isAssignedFinalApprover && !$isLegacyGmApprover) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'This request is assigned to another final approver.']);
            exit;
        }

        if ($action === 'approve' && !liquidation_requires_final_approval($liquidation)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'This request does not require final approver approval.']);
            exit;
        }
    }

    if ($action === 'save_sap' && !liquidation_requires_sap($liquidation)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => $requestLabel . ' does not require a SAP Document Number in this workflow.']);
        exit;
    }

    if ($action === 'save_sap' && $liquidation['status'] !== 'Liquidated') {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'SAP Document Number can only be encoded for Liquidated requests.']);
        exit;
    }

    if ($action === 'complete') {
        $requiredStatus = $requestLabel === 'Cash Advance' ? 'Liquidated' : 'For Accounting Approval';

        if ($liquidation['status'] !== $requiredStatus) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'This request is not ready for Accounting completion.']);
            exit;
        }

        if (!liquidation_can_complete_request($liquidation)) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'You are not the authorized Accounting approver for this request.']);
            exit;
        }

        if (liquidation_requires_sap($liquidation) && trim($liquidation['sap_document_number'] ?? '') === '') {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'SAP Document Number is required before Accounting completion.']);
            exit;
        }

        if (liquidation_requires_final_approval($liquidation)
            && empty($liquidation['approved_at'])
            && empty($liquidation['liquidation_approved_at'])) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Final approver approval is required before Accounting completion.']);
            exit;
        }
    }

    if ($action === 'process_liquidation') {
        if ($requestLabel !== 'Cash Advance' || $liquidation['status'] !== 'For Liquidation') {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Only Cash Advances marked For Liquidation can be processed.']);
            exit;
        }

        if ((int)$liquidation['created_by'] !== $userId) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'You can only process your own Cash Advance liquidation.']);
            exit;
        }

        if (empty($liquidation['attachment_path'])) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Please upload the liquidation attachment before submitting to Accounting.']);
            exit;
        }

        if (empty($liquidation['actual_amount']) || (float)$liquidation['actual_amount'] <= 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Please enter the actual amount before submitting to Accounting.']);
            exit;
        }
    }

    $isCashAdvanceLiquidationApproval = !empty($liquidation['source_cash_advance_id']);

    if ($action === 'manager_approve') {
        $nextStatus = liquidation_status_after_department_approval($liquidation);
        $update = $pdo->prepare("
            UPDATE liquidations
            SET status = :status,
                department_approved_by = :user_id,
                department_approved_at = NOW(),
                rejected_by = NULL,
                rejected_at = NULL,
                rejection_reason = NULL
            WHERE liquidation_id = :id
        ");
        $update->execute([':status' => $nextStatus, ':user_id' => $userId, ':id' => $liquidationId]);
        $message = $requestLabel . ' approved by Department Manager.';
    } elseif ($action === 'approve') {
        if (liquidation_is_current_workflow($liquidation)
            && liquidation_requires_sap($liquidation)
            && trim($liquidation['sap_document_number'] ?? '') === '') {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'SAP Document Number is required before final approval.']);
            exit;
        }

        $nextStatus = liquidation_status_after_final_approval($liquidation);
        if ($isCashAdvanceLiquidationApproval) {
            if (empty($liquidation['attachment_path']) || (float)$liquidation['actual_amount'] <= 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Actual amount and attachment are required before final approval.']);
                exit;
            }

            $update = $pdo->prepare("
                UPDATE liquidations
                SET status = :status,
                    liquidation_approved_by = :user_id,
                    liquidation_approved_at = NOW(),
                    rejected_by = NULL,
                    rejected_at = NULL,
                    rejection_reason = NULL
                WHERE liquidation_id = :id
            ");
            $update->execute([':status' => $nextStatus, ':user_id' => $userId, ':id' => $liquidationId]);
            $message = 'Cash Advance liquidation approved and moved to Accounting Manager verification.';
        } else {
            $update = $pdo->prepare("
                UPDATE liquidations
                SET status = :status,
                    approved_by = :user_id,
                    approved_at = NOW(),
                    rejected_by = NULL,
                    rejected_at = NULL,
                    rejection_reason = NULL
                WHERE liquidation_id = :id
            ");
            $update->execute([':status' => $nextStatus, ':user_id' => $userId, ':id' => $liquidationId]);
            $message = $requestLabel . ' approved and moved to Accounting completion.';
        }
    } elseif ($action === 'reject') {
        $update = $pdo->prepare("
            UPDATE liquidations
            SET status = 'Rejected',
                rejected_by = :user_id,
                rejected_at = NOW(),
                rejection_reason = :reason
            WHERE liquidation_id = :id
        ");
        $update->execute([
            ':user_id' => $userId,
            ':reason' => $reason,
            ':id' => $liquidationId
        ]);
        $message = $requestLabel . ' rejected.';
    } elseif ($action === 'save_sap') {
        $nextStatus = liquidation_status_after_sap($liquidation);
        $update = $pdo->prepare("
            UPDATE liquidations
            SET status = :status,
                sap_document_number = :sap_document_number,
                sap_document_by = :user_id,
                sap_document_at = NOW()
            WHERE liquidation_id = :id
        ");
        $update->execute([
            ':status' => $nextStatus,
            ':sap_document_number' => $sapDocumentNumber,
            ':user_id' => $userId,
            ':id' => $liquidationId
        ]);
        $message = liquidation_is_current_workflow($liquidation)
            ? 'SAP Document Number saved. Sent to the final approver.'
            : 'SAP Document Number saved. Sent to Accounting Manager for approval.';
    } elseif ($action === 'complete') {
        $update = $pdo->prepare("
            UPDATE liquidations
            SET status = 'Completed',
                completed_by = :user_id,
                completed_at = NOW()
            WHERE liquidation_id = :id
        ");
        $update->execute([':user_id' => $userId, ':id' => $liquidationId]);
        $message = $requestLabel === 'Cash Advance'
            ? 'Cash Advance released by Accounting.'
            : $requestLabel . ' completed by Accounting.';
    } else {
        $update = $pdo->prepare("
            UPDATE liquidations
            SET status = 'For GM Approval',
                liquidation_approved_by = NULL,
                liquidation_approved_at = NULL,
                rejected_by = NULL,
                rejected_at = NULL,
                rejection_reason = NULL
            WHERE liquidation_id = :id
        ");
        $update->execute([':id' => $liquidationId]);
        $message = 'Cash Advance liquidation submitted for final approval.';
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => $message]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['status' => 'danger', 'message' => 'Failed to update liquidation: ' . $e->getMessage()]);
}
