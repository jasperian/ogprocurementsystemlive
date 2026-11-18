<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/budget_remaining_helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$number = trim($_POST['ca_number'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$userId || $number === '') {
    http_response_code(400);
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT * FROM cash_advance WHERE pr_number = ? AND created_by = ? FOR UPDATE');
    $stmt->execute([$number, $userId]);
    $request = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$request) {
        throw new RuntimeException('Request not found or you are not its creator.');
    }
    if (($request['pr_status'] ?? '') !== 'CA Request') {
        throw new RuntimeException('This request can no longer be edited because Purchasing has already approved it.');
    }

    $amount = (float)($_POST['ca_amount'] ?? 0);
    $date = trim($_POST['ca_date'] ?? '');
    $remarks = trim($_POST['remarks'] ?? '');
    $payableTo = trim($_POST['payable_to'] ?? '');
    $requiresLiquidation = ($_POST['requires_liquidation'] ?? '1') === '0' ? 0 : 1;
    $liquidationDueDate = trim($_POST['liquidation_due_date'] ?? '');
    $liquidationExemptionReason = trim($_POST['liquidation_exemption_reason'] ?? '');
    $activityDate = trim($_POST['activity_date'] ?? '');
    $contentName = trim($_POST['content_name'] ?? '');
    $type = (string)($request['request_type'] ?? 'Cash Advance');

    $dateParts = explode('-', $date);
    if ($amount <= 0 || count($dateParts) !== 3 || !checkdate((int)($dateParts[1] ?? 0), (int)($dateParts[2] ?? 0), (int)($dateParts[0] ?? 0))) {
        throw new RuntimeException('A valid amount and request date are required.');
    }
    if (strlen($remarks) > 1000 || strlen($contentName) > 255 || strlen($payableTo) > 255) {
        throw new RuntimeException('One or more fields exceed the allowed length.');
    }
    if ($type === 'Request for Payment' && $payableTo === '') {
        throw new RuntimeException('Payable To is required for an RFP.');
    }
    if ($type === 'Cash Advance' && $amount > 10000) {
        throw new RuntimeException('Cash Advance is limited to PHP 10,000. Amounts above PHP 10,000 must be submitted as Request for Payment.');
    }
    if ($type === 'Liquidation') {
        $activityParts = explode('-', $activityDate);
        if (count($activityParts) !== 3 || !checkdate((int)($activityParts[1] ?? 0), (int)($activityParts[2] ?? 0), (int)($activityParts[0] ?? 0))) {
            throw new RuntimeException('A valid activity date is required for Liquidation.');
        }
    }

    $budgetId = (int)($request['budget_id'] ?? 0);
    $oldAmount = (float)$request['ca_amount'];
    if ($type === 'Request for Payment') {
        $allocationStmt = $pdo->prepare('SELECT * FROM cash_advance_allocations WHERE cash_advance_number = ? ORDER BY allocation_id LIMIT 1');
        $allocationStmt->execute([$number]);
        $allocation = $allocationStmt->fetch(PDO::FETCH_ASSOC);
        if ($allocation) {
            $budgetId = (int)$allocation['budget_id'];
            $oldAmount = (float)$allocation['allocation_amount'];
        }
    }

    $budgetStmt = $pdo->prepare('SELECT budget_department, budget_dealer FROM budgets WHERE budget_id = ?');
    $budgetStmt->execute([$budgetId]);
    $budgetOwner = $budgetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$budgetOwner) throw new RuntimeException('The request budget is no longer available.');
    $budget = getUnifiedBudgetRemainingById($pdo, $budgetId, $budgetOwner['budget_department'], $budgetOwner['budget_dealer']);
    if (!$budget || $amount > ((float)$budget['remaining_budget'] + $oldAmount)) {
        throw new RuntimeException('Budget exceeded. The edited amount is higher than the available budget.');
    }

    $attachmentPath = $request['ca_attachment_file'] ?? null;
    if (isset($_FILES['ca_file']) && ($_FILES['ca_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['ca_file'];
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 5 * 1024 * 1024) throw new RuntimeException('Attachment upload failed or exceeds 5MB.');
        $mime = mime_content_type($file['tmp_name']);
        if (!isset($allowed[$mime])) throw new RuntimeException('Only PDF, JPG, and PNG attachments are allowed.');
        $fileName = 'CA_ATT_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $number) . '_' . time() . '.' . $allowed[$mime];
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
        if (!move_uploaded_file($file['tmp_name'], $uploadDir . $fileName)) throw new RuntimeException('Failed to save attachment.');
        $attachmentPath = 'uploads/' . $fileName;
    }

    $update = $pdo->prepare('UPDATE cash_advance SET ca_amount=?, ca_date=?, activity_date=?, content_name=?, payable_to=?, requires_liquidation=?, liquidation_exemption_reason=?, liquidation_due_date=?, remarks=?, ca_attachment_file=? WHERE pr_number=?');
    $update->execute([$amount, $date, $type === 'Liquidation' ? $activityDate : null, $type === 'Liquidation' && $contentName !== '' ? $contentName : null, $type === 'Request for Payment' ? $payableTo : null, $type === 'Request for Payment' ? 0 : null, null, null, $remarks !== '' ? $remarks : null, $attachmentPath, $number]);
    if ($type === 'Request for Payment') {
        $pdo->prepare('UPDATE cash_advance_allocations SET allocation_amount=? WHERE cash_advance_number=?')->execute([$amount, $number]);
    }
    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Request updated successfully.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['status' => 'danger', 'message' => $e->getMessage()]);
}
