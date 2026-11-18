<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/cash_advance_number_helper.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$sourceId = (int)($_POST['source_rfp_id'] ?? 0);
$amount = (float)($_POST['actual_amount'] ?? 0);
$remarks = trim((string)($_POST['remarks'] ?? ''));
if ($userId <= 0 || $sourceId <= 0 || $amount <= 0 || empty($_FILES['attachment'])) {
    echo json_encode(['status' => 'danger', 'message' => 'RFP, actual amount, and attachment are required.']); exit;
}

$file = $_FILES['attachment'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ($file['size'] ?? 0) > 5 * 1024 * 1024) {
    echo json_encode(['status' => 'danger', 'message' => 'A valid attachment below 5MB is required.']); exit;
}
$allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!isset($allowed[$mime])) {
    echo json_encode(['status' => 'danger', 'message' => 'Attachment must be PDF, JPG, or PNG.']); exit;
}

$storedPath = null;
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare("SELECT * FROM cash_advance WHERE pr_id=? FOR UPDATE");
    $stmt->execute([$sourceId]);
    $source = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$source || $source['request_type'] !== 'Request for Payment' || (int)$source['requires_liquidation'] !== 1 || $source['pr_status'] !== 'Completed' || (int)$source['created_by'] !== $userId) {
        throw new RuntimeException('Only the creator can liquidate a completed RFP requiring liquidation.');
    }
    $dupe = $pdo->prepare("SELECT pr_number FROM cash_advance WHERE source_rfp_id=? LIMIT 1");
    $dupe->execute([$sourceId]);
    if ($dupe->fetchColumn()) throw new RuntimeException('This RFP already has a linked liquidation.');

    $number = generateCashAdvanceNumber($pdo, (string)$source['pr_dealer'], 'Liquidation');
    $dir = __DIR__ . '/../../uploads/liquidations/';
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) throw new RuntimeException('Unable to create upload directory.');
    $name = preg_replace('/[^A-Za-z0-9_-]/', '_', $number) . '_' . time() . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) throw new RuntimeException('Unable to store attachment.');
    $storedPath = 'uploads/liquidations/' . $name;

    $finalApproverId = purchasingWorkflowResolveFinalApproverId($pdo, (string)$source['pr_dealer'], (string)($_SESSION['department'] ?? $source['pr_department']), $userId);
    $insert = $pdo->prepare("INSERT INTO cash_advance
        (pr_number,request_type,ca_amount,ca_date,remarks,pr_status,pr_department,budget_code,budget_id,pr_date,pr_file,pr_dealer,created_by,ca_attachment_file,source_rfp_id,source_rfp_number,final_approver_user_id)
        VALUES (?,'Liquidation',?,?,?,'CA Request',?,?,?,NOW(),?,?,?,?,?,?,?)");
    $insert->execute([$number,$amount,date('Y-m-d'),$remarks ?: null,$source['pr_department'],$source['budget_code'],$source['budget_id'],$source['pr_file'],$source['pr_dealer'],$userId,$storedPath,$sourceId,$source['pr_number'],$finalApproverId]);
    $pdo->commit();
    echo json_encode(['status'=>'success','message'=>'Linked liquidation ' . $number . ' created successfully.','liquidation_number'=>$number]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    if ($storedPath && is_file(__DIR__ . '/../../' . $storedPath)) unlink(__DIR__ . '/../../' . $storedPath);
    echo json_encode(['status'=>'danger','message'=>$e->getMessage()]);
}
