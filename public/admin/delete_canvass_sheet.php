<?php
require '../../init.php';
header('Content-Type: application/json');

function deleteCanvassResponse(int $status, bool $success, string $message, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    deleteCanvassResponse(405, false, 'Method not allowed.');
}

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    deleteCanvassResponse(403, false, 'Administrator access is required.');
}

$csrfToken = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['delete_canvass_csrf']) || !hash_equals($_SESSION['delete_canvass_csrf'], $csrfToken)) {
    deleteCanvassResponse(419, false, 'Your session token expired. Refresh the page and try again.');
}

$prNumber = trim($_POST['pr_number'] ?? '');
if ($prNumber === '' || mb_strlen($prNumber) > 255) {
    deleteCanvassResponse(422, false, 'Invalid PR number.');
}

$quotationFiles = [];

try {
    $pdo->beginTransaction();

    $batchStmt = $pdo->prepare('SELECT batch_id FROM canvass_sheet_batches WHERE pr_number = ? FOR UPDATE');
    $batchStmt->execute([$prNumber]);
    $batchIds = array_map('intval', $batchStmt->fetchAll(PDO::FETCH_COLUMN));
    if (!$batchIds) {
        throw new RuntimeException('The canvass sheet no longer exists.');
    }

    $prStmt = $pdo->prepare('SELECT pr_id FROM purchase_requisition WHERE pr_number = ? FOR UPDATE');
    $prStmt->execute([$prNumber]);
    if (!$prStmt->fetchColumn()) {
        throw new RuntimeException('The connected purchase requisition was not found.');
    }

    $poStmt = $pdo->prepare('SELECT COUNT(*) FROM purchase_order WHERE pr_number = ?');
    $poStmt->execute([$prNumber]);
    if ((int)$poStmt->fetchColumn() > 0) {
        throw new RuntimeException('This canvass has an existing purchase order and cannot be deleted here.');
    }

    $cashAdvanceStmt = $pdo->prepare('SELECT COUNT(*) FROM cash_advance WHERE purchase_requisition_number = ? OR pr_number = ?');
    $cashAdvanceStmt->execute([$prNumber, $prNumber]);
    if ((int)$cashAdvanceStmt->fetchColumn() > 0) {
        throw new RuntimeException('This canvass has an existing cash advance and cannot be deleted here.');
    }

    $placeholders = implode(',', array_fill(0, count($batchIds), '?'));

    $fileStmt = $pdo->prepare("SELECT quotation_file FROM canvass_quotation WHERE batch_id IN ($placeholders) OR pr_number = ?");
    $fileStmt->execute([...$batchIds, $prNumber]);
    $quotationFiles = array_values(array_filter($fileStmt->fetchAll(PDO::FETCH_COLUMN)));

    $poRequestStmt = $pdo->prepare("SELECT request_id FROM po_approval_requests WHERE pr_number = ? OR batch_id IN ($placeholders)");
    $poRequestStmt->execute([$prNumber, ...$batchIds]);
    $poRequestIds = array_map('intval', $poRequestStmt->fetchAll(PDO::FETCH_COLUMN));
    if ($poRequestIds) {
        $requestPlaceholders = implode(',', array_fill(0, count($poRequestIds), '?'));
        $pdo->prepare("DELETE FROM po_approval_signatures WHERE request_id IN ($requestPlaceholders)")->execute($poRequestIds);
        $pdo->prepare("DELETE FROM po_approval_items WHERE request_id IN ($requestPlaceholders)")->execute($poRequestIds);
        $pdo->prepare("DELETE FROM po_approval_requests WHERE request_id IN ($requestPlaceholders)")->execute($poRequestIds);
    }

    $notificationStmt = $pdo->prepare("SELECT id FROM notifications WHERE link = ? OR link = ?");
    $notificationStmt->execute([
        'canvass_view?pr_number=' . $prNumber,
        'canvass_view.php?pr_number=' . $prNumber
    ]);
    $notificationIds = array_map('intval', $notificationStmt->fetchAll(PDO::FETCH_COLUMN));
    if ($notificationIds) {
        $notificationPlaceholders = implode(',', array_fill(0, count($notificationIds), '?'));
        $pdo->prepare("DELETE FROM notification_recipients WHERE notification_id IN ($notificationPlaceholders)")->execute($notificationIds);
        $pdo->prepare("DELETE FROM notifications WHERE id IN ($notificationPlaceholders)")->execute($notificationIds);
    }

    $pdo->prepare("DELETE FROM canvass_change_history WHERE batch_id IN ($placeholders)")->execute($batchIds);

    $commentsStmt = $pdo->prepare("DELETE FROM recommendations_suggestions WHERE batch_id IN ($placeholders) OR pr_number = ?");
    $commentsStmt->execute([...$batchIds, $prNumber]);

    $quotationStmt = $pdo->prepare("DELETE FROM canvass_quotation WHERE batch_id IN ($placeholders) OR pr_number = ?");
    $quotationStmt->execute([...$batchIds, $prNumber]);

    $pdo->prepare("DELETE FROM canvass_approvals WHERE batch_id IN ($placeholders)")->execute($batchIds);
    $pdo->prepare("DELETE FROM canvass_items WHERE batch_id IN ($placeholders)")->execute($batchIds);

    $rejectedStmt = $pdo->prepare('DELETE FROM canvass_rejected WHERE pr_number = ?');
    $rejectedStmt->execute([$prNumber]);
    $draftStmt = $pdo->prepare('DELETE FROM canvass_sheet_batches_drafts WHERE pr_number = ?');
    $draftStmt->execute([$prNumber]);
    $legacyDraftStmt = $pdo->prepare('DELETE FROM canvass_drafts WHERE pr_number = ?');
    $legacyDraftStmt->execute([$prNumber]);

    $pdo->prepare("DELETE FROM canvass_sheet_batches WHERE batch_id IN ($placeholders)")->execute($batchIds);

    $resetStmt = $pdo->prepare("UPDATE purchase_requisition
        SET pr_status = 'For Canvass', batch_id = NULL, canvass_created = NULL
        WHERE pr_number = ?");
    $resetStmt->execute([$prNumber]);

    $leadTimeStmt = $pdo->prepare("UPDATE leadtime_tracking
        SET for_canvass_date = NOW(), canvass_approved_date = NULL, po_created_date = NULL
        WHERE pr_number = ?");
    $leadTimeStmt->execute([$prNumber]);

    $logStmt = $pdo->prepare('INSERT INTO logs (user_id, action) VALUES (?, ?)');
    $logStmt->execute([
        $_SESSION['user_id'],
        'Deleted canvass sheet for PR ' . $prNumber . ' and reset status to For Canvass'
    ]);

    $pdo->commit();

    $proposalDirectory = realpath(__DIR__ . '/uploads/proposals');
    if ($proposalDirectory) {
        foreach ($quotationFiles as $quotationFile) {
            $safeName = basename($quotationFile);
            $path = $proposalDirectory . DIRECTORY_SEPARATOR . $safeName;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    deleteCanvassResponse(200, true, "Canvass sheet for {$prNumber} was deleted and the PR was reset to For Canvass.", [
        'pr_number' => $prNumber,
        'deleted_batches' => count($batchIds)
    ]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    deleteCanvassResponse(409, false, $e->getMessage());
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Delete canvass failed for ' . $prNumber . ': ' . $e->getMessage());
    deleteCanvassResponse(500, false, 'Unable to delete the canvass sheet. No records were changed.');
}
