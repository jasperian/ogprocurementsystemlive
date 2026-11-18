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

$userId = (int)$_SESSION['user_id'];
$department = trim($_SESSION['department'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');
$requestType = trim($_POST['request_type'] ?? 'Liquidation');
$requiresLiquidation = $requestType === 'Request for Payment' ? ($_POST['requires_liquidation'] ?? null) : null;
$budgetId = (int)($_POST['budget_id'] ?? 0);
$amount = trim($_POST['liquidation_amount'] ?? '');
$date = trim($_POST['liquidation_date'] ?? '');
$activityDate = trim($_POST['activity_date'] ?? '');
$contentName = strtoupper(trim($_POST['content_name'] ?? ''));
$rfpDepartmentHeadName = trim($_POST['rfp_department_head_name'] ?? '');
$remarks = trim($_POST['remarks'] ?? '');

if ($department === '' || $dealer === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Your account is missing department or dealer details.']);
    exit;
}

if (!in_array($requestType, ['Liquidation', 'Request for Payment', 'Cash Advance'], true)) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request type.']);
    exit;
}
if ($requestType === 'Request for Payment' && !in_array($requiresLiquidation, ['0', '1'], true)) {
    echo json_encode(['status' => 'danger', 'message' => 'Choose whether this RFP requires liquidation.']);
    exit;
}

if ($budgetId <= 0 || $amount === '' || $date === '' || $activityDate === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Budget code, amount, date, and activity date are required.']);
    exit;
}

if (!is_numeric($amount) || (float)$amount <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Liquidation amount must be greater than zero.']);
    exit;
}

$amountValue = (float)$amount;
if ($requestType === 'Cash Advance' && $amountValue > LIQUIDATION_CA_MAX_AMOUNT) {
    echo json_encode([
        'status' => 'danger',
        'code' => 'CA_AMOUNT_REQUIRES_RFP',
        'message' => 'Cash Advance is limited to PHP 10,000. Amounts above PHP 10,000 must be submitted as Request for Payment.'
    ]);
    exit;
}

$initialStatus = liquidation_initial_status($requestType, $amountValue, liquidation_is_department_head(), $requiresLiquidation === null ? null : (int)$requiresLiquidation);

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid liquidation date.']);
    exit;
}

$activityDateObj = DateTime::createFromFormat('Y-m-d', $activityDate);
if (!$activityDateObj || $activityDateObj->format('Y-m-d') !== $activityDate) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid activity date.']);
    exit;
}
$activityDateSql = $activityDateObj->format('Y-m-d');

if (strlen($remarks) > 2000) {
    echo json_encode(['status' => 'danger', 'message' => 'Remarks must be 2000 characters or less.']);
    exit;
}

if (strlen($contentName) > 255) {
    echo json_encode(['status' => 'danger', 'message' => 'Content must be 255 characters or less.']);
    exit;
}

if ($requestType === 'Request for Payment' && $rfpDepartmentHeadName === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Payable To is required for Request for Payment.']);
    exit;
}

if (strlen($rfpDepartmentHeadName) > 255) {
    echo json_encode(['status' => 'danger', 'message' => 'Payable To must be 255 characters or less.']);
    exit;
}

if (
    ($requestType === 'Liquidation' || ($requestType === 'Request for Payment' && $requiresLiquidation === '0'))
    && (!isset($_FILES['attachment']) || ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE)
) {
    echo json_encode(['status' => 'danger', 'message' => 'Attachment is required for this request.']);
    exit;
}

ensureBudgetUserAccessTable($pdo);

try {
    $pdo->beginTransaction();

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

    if ((float)$amount > (float)$budget['remaining_budget']) {
        $pdo->rollBack();
        echo json_encode([
            'status' => 'danger',
            'message' => 'Budget exceeded. Remaining budget is PHP ' . number_format((float)$budget['remaining_budget'], 2) . '.'
        ]);
        exit;
    }

    $approvalRoute = liquidation_get_approval_route_for_request($pdo, $dealer, $department, $userId);
    $seriesPrefix = liquidation_normalize_series_prefix($approvalRoute['series_prefix'] ?? null, $dealer);
    $liquidationNumber = liquidation_generate_number($pdo, $dealer, $requestType, $seriesPrefix);
    $departmentApproverUserId = !liquidation_is_department_head()
        ? ($approvalRoute['department_approver']['user_id'] ?? null)
        : null;
    $finalApprover = $approvalRoute['final_approver'] ?? liquidation_get_default_final_approver($pdo, $dealer);
    $finalApproverUserId = $finalApprover['user_id'] ?? null;

    $attachmentPath = null;
    if (isset($_FILES['attachment']) && ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $uploadResult = liquidation_store_attachment($_FILES['attachment'], $liquidationNumber);
        if ($uploadResult['status'] !== 'success') {
            $pdo->rollBack();
            echo json_encode($uploadResult);
            exit;
        }
        $attachmentPath = $uploadResult['path'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO liquidations
            (liquidation_number, series_prefix, workflow_version, request_type, dealer, department, budget_id, budget_code, budget_activity,
             content_name, liquidation_amount, liquidation_date, activity_date, rfp_department_head_name, requires_liquidation, department_approver_user_id, final_approver_user_id,
             remarks, attachment_path, status, created_by, submitted_at)
        VALUES
            (:liquidation_number, :series_prefix, :workflow_version, :request_type, :dealer, :department, :budget_id, :budget_code, :budget_activity,
             :content_name, :liquidation_amount, :liquidation_date, :activity_date, :rfp_department_head_name, :requires_liquidation, :department_approver_user_id, :final_approver_user_id,
             :remarks, :attachment_path, :status, :created_by, NOW())
    ");
    $stmt->execute([
        ':liquidation_number' => $liquidationNumber,
        ':series_prefix' => $seriesPrefix,
        ':workflow_version' => LIQUIDATION_CURRENT_WORKFLOW_VERSION,
        ':request_type' => $requestType,
        ':dealer' => $dealer,
        ':department' => $department,
        ':budget_id' => $budgetId,
        ':budget_code' => $budget['budget_code'],
        ':budget_activity' => $budget['budget_activity'],
        ':content_name' => $contentName !== '' ? $contentName : null,
        ':liquidation_amount' => $amount,
        ':liquidation_date' => $date,
        ':activity_date' => $activityDateSql,
        ':rfp_department_head_name' => $requestType === 'Request for Payment' ? $rfpDepartmentHeadName : null,
        ':requires_liquidation' => $requiresLiquidation,
        ':department_approver_user_id' => $departmentApproverUserId,
        ':final_approver_user_id' => $finalApproverUserId,
        ':remarks' => $remarks !== '' ? $remarks : null,
        ':attachment_path' => $attachmentPath,
        ':status' => $initialStatus,
        ':created_by' => $userId
    ]);

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => $requestType . ' submitted successfully.',
        'liquidation_number' => $liquidationNumber
    ]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    if (!empty($attachmentPath)) {
        $uploadedFile = __DIR__ . '/../../' . $attachmentPath;
        if (is_file($uploadedFile)) {
            @unlink($uploadedFile);
        }
    }

    echo json_encode(['status' => 'danger', 'message' => 'Failed to submit liquidation: ' . $e->getMessage()]);
}
