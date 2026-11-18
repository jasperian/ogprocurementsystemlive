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

$liquidationId = (int)($_POST['liquidation_id'] ?? 0);
$actualAmount = trim($_POST['actual_amount'] ?? '');
$submitForApproval = (int)($_POST['submit_for_approval'] ?? 0) === 1;
$userId = (int)$_SESSION['user_id'];

if ($liquidationId <= 0 || !isset($_FILES['attachment'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Liquidation and file are required.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT liquidation_id, liquidation_number, request_type, status, created_by
        FROM liquidations
        WHERE liquidation_id = ?
    ");
    $stmt->execute([$liquidationId]);
    $liquidation = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$liquidation) {
        echo json_encode(['status' => 'danger', 'message' => 'Liquidation not found.']);
        exit;
    }

    if ((int)$liquidation['created_by'] !== $userId) {
        echo json_encode(['status' => 'danger', 'message' => 'You can only upload files for your own liquidation.']);
        exit;
    }

    $canUploadForApproval = $liquidation['status'] === 'For Department Manager Approval';
    $canUploadForCashAdvanceLiquidation = $liquidation['request_type'] === 'Cash Advance'
        && $liquidation['status'] === 'For Liquidation';

    if (!$canUploadForApproval && !$canUploadForCashAdvanceLiquidation) {
        echo json_encode(['status' => 'danger', 'message' => 'Files can only be changed before Department Manager approval or during Cash Advance liquidation.']);
        exit;
    }

    $submitForApproval = $submitForApproval && $canUploadForCashAdvanceLiquidation;

    if ($canUploadForCashAdvanceLiquidation) {
        if ($actualAmount === '' || !is_numeric($actualAmount) || (float)$actualAmount <= 0) {
            echo json_encode(['status' => 'danger', 'message' => 'Actual amount is required and must be greater than zero.']);
            exit;
        }
    }

    $uploadResult = liquidation_store_attachment($_FILES['attachment'], $liquidation['liquidation_number']);
    if ($uploadResult['status'] !== 'success') {
        echo json_encode($uploadResult);
        exit;
    }

    if ($canUploadForCashAdvanceLiquidation) {
        $update = $pdo->prepare("UPDATE liquidations SET attachment_path = ?, actual_amount = ? WHERE liquidation_id = ?");
        $update->execute([$uploadResult['path'], $actualAmount, $liquidationId]);

        if ($submitForApproval) {
            $submit = $pdo->prepare("
                UPDATE liquidations
                SET status = 'For GM Approval',
                    liquidation_approved_by = NULL,
                    liquidation_approved_at = NULL,
                    rejected_by = NULL,
                    rejected_at = NULL,
                    rejection_reason = NULL
                WHERE liquidation_id = ?
            ");
            $submit->execute([$liquidationId]);
        }
    } else {
        $update = $pdo->prepare("UPDATE liquidations SET attachment_path = ? WHERE liquidation_id = ?");
        $update->execute([$uploadResult['path'], $liquidationId]);
    }

    echo json_encode([
        'status' => 'success',
        'message' => $submitForApproval
            ? 'Cash Advance liquidation submitted for GM approval.'
            : 'Attachment uploaded successfully.',
        'file_path' => $uploadResult['path']
    ]);
} catch (Exception $e) {
    echo json_encode(['status' => 'danger', 'message' => 'Failed to upload attachment: ' . $e->getMessage()]);
}
