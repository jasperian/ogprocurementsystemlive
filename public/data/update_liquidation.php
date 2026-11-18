<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';
require_once __DIR__ . '/budget_access_helper.php';

liquidation_require_department_encoder();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$liquidationId = (int)($_POST['liquidation_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$department = trim($_SESSION['department'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');
$requestType = trim($_POST['request_type'] ?? '');
$requiresLiquidation = $requestType === 'Request for Payment' ? ($_POST['requires_liquidation'] ?? null) : null;
$budgetId = (int)($_POST['budget_id'] ?? 0);
$amount = trim($_POST['liquidation_amount'] ?? '');
$date = trim($_POST['liquidation_date'] ?? '');
$activityDate = trim($_POST['activity_date'] ?? '');
$contentName = strtoupper(trim($_POST['content_name'] ?? ''));
$rfpDepartmentHeadName = trim($_POST['rfp_department_head_name'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($liquidationId <= 0 || $department === '' || $dealer === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request details.']);
    exit;
}

if ($budgetId <= 0 || $amount === '' || $date === '' || $activityDate === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Budget code, amount, date, and activity date are required.']);
    exit;
}

if (!is_numeric($amount) || (float)$amount <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Amount must be greater than zero.']);
    exit;
}

$amountValue = (float)$amount;
if ($requestType === 'Cash Advance' && $amountValue > LIQUIDATION_CA_MAX_AMOUNT) {
    echo json_encode([
        'status' => 'danger',
        'code' => 'CA_AMOUNT_REQUIRES_RFP',
        'message' => 'Cash Advance is limited to PHP 10,000. Reduce the amount before saving.'
    ]);
    exit;
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
$activityDateObj = DateTime::createFromFormat('Y-m-d', $activityDate);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request date.']);
    exit;
}
if (!$activityDateObj || $activityDateObj->format('Y-m-d') !== $activityDate) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid activity date.']);
    exit;
}

if (strlen($contentName) > 255 || strlen($remarks) > 2000 || strlen($rfpDepartmentHeadName) > 255) {
    echo json_encode(['status' => 'danger', 'message' => 'One or more fields exceed the allowed length.']);
    exit;
}

if ($requestType === 'Request for Payment' && $rfpDepartmentHeadName === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Payable To is required for Request for Payment.']);
    exit;
}
if ($requestType === 'Request for Payment' && !in_array($requiresLiquidation, ['0', '1'], true)) {
    echo json_encode(['status' => 'danger', 'message' => 'Choose whether this RFP requires liquidation.']);
    exit;
}

ensureBudgetUserAccessTable($pdo);
$newAttachmentPath = null;
$oldAttachmentPath = null;

try {
    $pdo->beginTransaction();

    $requestStmt = $pdo->prepare("
        SELECT *
        FROM liquidations
        WHERE liquidation_id = :liquidation_id
        FOR UPDATE
    ");
    $requestStmt->execute([':liquidation_id' => $liquidationId]);
    $request = $requestStmt->fetch(PDO::FETCH_ASSOC);

    if (!$request
        || (int)$request['created_by'] !== $userId
        || (string)$request['dealer'] !== $dealer
        || strtoupper(trim((string)$request['department'])) !== strtoupper($department)) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'You can only edit requests that you created.']);
        exit;
    }

    if (!empty($request['source_cash_advance_id']) || !empty($request['source_rfp_id'])) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Cash Advance liquidation records must be maintained from the liquidation action.']);
        exit;
    }

    if (!in_array($request['status'], ['For Department Manager Approval', 'Rejected'], true)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'This request can no longer be edited because Department Manager approval has already been completed.']);
        exit;
    }

    if ($requestType !== $request['request_type']) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'The request type cannot be changed while editing.']);
        exit;
    }

    $oldAttachmentPath = $request['attachment_path'] ?? null;
    $hasNewAttachment = isset($_FILES['attachment'])
        && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
    if (($requestType === 'Liquidation' || ($requestType === 'Request for Payment' && $requiresLiquidation === '0')) && !$hasNewAttachment && empty($oldAttachmentPath)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Attachment is required for a Liquidation.']);
        exit;
    }

    $allowedBudget = budgetAccessGetAllowedBudget($pdo, $budgetId, $userId, $department, $dealer);
    if (!$allowedBudget) {
        $pdo->rollBack();
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget is not allowed for your department/dealer.']);
        exit;
    }

    $budget = liquidation_get_budget_monitoring(
        $pdo,
        $budgetId,
        $allowedBudget['budget_department'],
        $allowedBudget['budget_dealer'],
        true
    );
    if (!$budget) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Selected budget could not be loaded.']);
        exit;
    }

    $availableBudget = (float)$budget['remaining_budget'];
    if ($request['status'] !== 'Rejected' && (int)$request['budget_id'] === $budgetId) {
        $availableBudget += (float)$request['liquidation_amount'];
    }
    if ($amountValue > $availableBudget) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'danger',
            'message' => 'Budget exceeded. Available budget for this edit is PHP ' . number_format($availableBudget, 2) . '.'
        ]);
        exit;
    }

    if ($hasNewAttachment) {
        $uploadResult = liquidation_store_attachment($_FILES['attachment'], $request['liquidation_number']);
        if ($uploadResult['status'] !== 'success') {
            $pdo->rollBack();
            echo json_encode($uploadResult);
            exit;
        }
        $newAttachmentPath = $uploadResult['path'];
    }

    $approvalRoute = liquidation_get_approval_route_for_request($pdo, $dealer, $department, $userId);
    $creatorSkipsDepartmentApproval = $request['status'] === 'Rejected' && liquidation_is_department_head();
    $nextStatus = $request['status'] === 'For Department Manager Approval'
        ? 'For Department Manager Approval'
        : liquidation_initial_status($requestType, $amountValue, $creatorSkipsDepartmentApproval, $requiresLiquidation === null ? null : (int)$requiresLiquidation);
    $departmentApproverUserId = !$creatorSkipsDepartmentApproval
        ? ($approvalRoute['department_approver']['user_id'] ?? null)
        : null;
    $finalApprover = $approvalRoute['final_approver'] ?? liquidation_get_default_final_approver($pdo, $dealer);
    $finalApproverUserId = $finalApprover['user_id'] ?? null;

    $update = $pdo->prepare("
        UPDATE liquidations
        SET budget_id = :budget_id,
            budget_code = :budget_code,
            budget_activity = :budget_activity,
            content_name = :content_name,
            liquidation_amount = :liquidation_amount,
            liquidation_date = :liquidation_date,
            activity_date = :activity_date,
            rfp_department_head_name = :rfp_department_head_name,
            requires_liquidation = :requires_liquidation,
            remarks = :remarks,
            attachment_path = :attachment_path,
            status = :status,
            department_approver_user_id = :department_approver_user_id,
            final_approver_user_id = :final_approver_user_id,
            submitted_at = NOW(),
            department_approved_by = NULL,
            department_approved_at = NULL,
            approved_by = NULL,
            approved_at = NULL,
            liquidation_approved_by = NULL,
            liquidation_approved_at = NULL,
            sap_document_number = NULL,
            sap_document_by = NULL,
            sap_document_at = NULL,
            rejected_by = NULL,
            rejected_at = NULL,
            rejection_reason = NULL,
            completed_by = NULL,
            completed_at = NULL
        WHERE liquidation_id = :liquidation_id
    ");
    $update->execute([
        ':budget_id' => $budgetId,
        ':budget_code' => $budget['budget_code'],
        ':budget_activity' => $budget['budget_activity'],
        ':content_name' => $contentName !== '' ? $contentName : null,
        ':liquidation_amount' => $amountValue,
        ':liquidation_date' => $dateObj->format('Y-m-d'),
        ':activity_date' => $activityDateObj->format('Y-m-d'),
        ':rfp_department_head_name' => $requestType === 'Request for Payment' ? $rfpDepartmentHeadName : null,
        ':requires_liquidation' => $requiresLiquidation,
        ':remarks' => $remarks !== '' ? $remarks : null,
        ':attachment_path' => $newAttachmentPath ?: $oldAttachmentPath,
        ':status' => $nextStatus,
        ':department_approver_user_id' => $departmentApproverUserId,
        ':final_approver_user_id' => $finalApproverUserId,
        ':liquidation_id' => $liquidationId,
    ]);

    $wasRejected = $request['status'] === 'Rejected';
    $pdo->commit();

    if ($newAttachmentPath && $oldAttachmentPath && $newAttachmentPath !== $oldAttachmentPath) {
        $oldFile = __DIR__ . '/../../' . $oldAttachmentPath;
        if (is_file($oldFile)) {
            @unlink($oldFile);
        }
    }

    echo json_encode([
        'status' => 'success',
        'message' => $requestType . ($wasRejected ? ' resubmitted successfully.' : ' updated successfully.'),
        'liquidation_number' => $request['liquidation_number'],
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    if ($newAttachmentPath) {
        $newFile = __DIR__ . '/../../' . $newAttachmentPath;
        if (is_file($newFile)) {
            @unlink($newFile);
        }
    }

    echo json_encode(['status' => 'danger', 'message' => 'Failed to update request: ' . $e->getMessage()]);
}
