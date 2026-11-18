<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';

liquidation_require_department_encoder();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$sourceId = (int)($_POST['source_liquidation_id'] ?? 0);
$actualAmount = trim($_POST['actual_amount'] ?? '');
$date = trim($_POST['liquidation_date'] ?? date('Y-m-d'));
$remarks = trim($_POST['remarks'] ?? '');
$userId = (int)$_SESSION['user_id'];
$dealer = trim($_SESSION['dealer'] ?? '');
$department = trim($_SESSION['department'] ?? '');
$attachmentPath = null;
$initialStatus = liquidation_initial_status('Liquidation', (float)$actualAmount, liquidation_is_department_head());

if ($sourceId <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Cash Advance source is required.']);
    exit;
}

if ($actualAmount === '' || !is_numeric($actualAmount) || (float)$actualAmount <= 0) {
    echo json_encode(['status' => 'danger', 'message' => 'Actual amount is required and must be greater than zero.']);
    exit;
}

$dateObj = DateTime::createFromFormat('Y-m-d', $date);
if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid liquidation date.']);
    exit;
}

if (strlen($remarks) > 2000) {
    echo json_encode(['status' => 'danger', 'message' => 'Remarks must be 2000 characters or less.']);
    exit;
}

if (!isset($_FILES['attachment']) || ($_FILES['attachment']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    echo json_encode(['status' => 'danger', 'message' => 'Attachment is required.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT *
        FROM liquidations
        WHERE liquidation_id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $sourceId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$source) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Released Cash Advance not found.']);
        exit;
    }

    if ($source['request_type'] !== 'Cash Advance' || $source['status'] !== 'Completed') {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'Only released Cash Advances can be liquidated.']);
        exit;
    }

    if ($department !== '' && strtoupper(trim($source['department'])) !== strtoupper($department)) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'You can only create liquidations for your department.']);
        exit;
    }

    if ($dealer !== '' && strtoupper($dealer) !== 'OG' && $source['dealer'] !== $dealer) {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'You can only create liquidations for your dealer.']);
        exit;
    }

    $dupe = $pdo->prepare("
        SELECT liquidation_number
        FROM liquidations
        WHERE source_cash_advance_id = :source_id
        LIMIT 1
    ");
    $dupe->execute([':source_id' => $sourceId]);
    $existingNumber = (string)$dupe->fetchColumn();
    if ($existingNumber !== '') {
        $pdo->rollBack();
        echo json_encode(['status' => 'danger', 'message' => 'This Cash Advance already has liquidation ' . $existingNumber . '.']);
        exit;
    }

    $approvalRoute = liquidation_get_approval_route_for_request($pdo, $source['dealer'], $source['department'], $userId);
    $seriesPrefix = liquidation_normalize_series_prefix($approvalRoute['series_prefix'] ?? ($source['series_prefix'] ?? null), $source['dealer']);
    $liquidationNumber = liquidation_generate_number($pdo, $source['dealer'], 'Liquidation', $seriesPrefix);
    $departmentApproverUserId = !liquidation_is_department_head()
        ? ($approvalRoute['department_approver']['user_id'] ?? null)
        : null;
    $finalApprover = $approvalRoute['final_approver'] ?? liquidation_get_default_final_approver($pdo, $source['dealer']);
    $finalApproverUserId = $finalApprover['user_id'] ?? null;
    $uploadResult = liquidation_store_attachment($_FILES['attachment'], $liquidationNumber);
    if ($uploadResult['status'] !== 'success') {
        $pdo->rollBack();
        echo json_encode($uploadResult);
        exit;
    }
    $attachmentPath = $uploadResult['path'];

    $insert = $pdo->prepare("
        INSERT INTO liquidations
            (liquidation_number, series_prefix, workflow_version, request_type, dealer, department, budget_id, budget_code, budget_activity,
             content_name, liquidation_amount, actual_amount, liquidation_date, remarks, attachment_path, status, created_by,
             submitted_at, source_cash_advance_id, source_cash_advance_number, department_approver_user_id, final_approver_user_id)
        VALUES
            (:liquidation_number, :series_prefix, :workflow_version, 'Liquidation', :dealer, :department, :budget_id, :budget_code, :budget_activity,
             :content_name, :liquidation_amount, :actual_amount, :liquidation_date, :remarks, :attachment_path, :status, :created_by,
             NOW(), :source_cash_advance_id, :source_cash_advance_number, :department_approver_user_id, :final_approver_user_id)
    ");
    $insert->execute([
        ':liquidation_number' => $liquidationNumber,
        ':series_prefix' => $seriesPrefix,
        ':workflow_version' => LIQUIDATION_CURRENT_WORKFLOW_VERSION,
        ':dealer' => $source['dealer'],
        ':department' => $source['department'],
        ':budget_id' => $source['budget_id'],
        ':budget_code' => $source['budget_code'],
        ':budget_activity' => $source['budget_activity'],
        ':content_name' => $source['content_name'] ?? null,
        ':liquidation_amount' => $actualAmount,
        ':actual_amount' => $actualAmount,
        ':liquidation_date' => $date,
        ':remarks' => $remarks !== '' ? $remarks : null,
        ':attachment_path' => $attachmentPath,
        ':status' => $initialStatus,
        ':created_by' => $userId,
        ':source_cash_advance_id' => $sourceId,
        ':source_cash_advance_number' => $source['liquidation_number'],
        ':department_approver_user_id' => $departmentApproverUserId,
        ':final_approver_user_id' => $finalApproverUserId
    ]);

    $pdo->commit();
    echo json_encode([
        'status' => 'success',
        'message' => 'Liquidation ' . $liquidationNumber . ' submitted successfully.',
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

    echo json_encode(['status' => 'danger', 'message' => 'Failed to create liquidation: ' . $e->getMessage()]);
}
