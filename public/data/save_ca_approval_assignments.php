<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';
require_once __DIR__ . '/cash_advance_approval_assignments_helper.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Administrator can update Cash Advance approvers.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$sessionDealer = trim($_SESSION['dealer'] ?? 'OG');
$sessionDealer = $sessionDealer !== '' ? $sessionDealer : 'OG';
$postedDealer = trim($_POST['dealer'] ?? $sessionDealer);
$dealer = $postedDealer !== '' ? $postedDealer : 'OG';

$statuses = getCashAdvanceApprovalStatuses();
$assignments = $_POST['assignments'] ?? [];

try {
    $pdo->beginTransaction();

    $validateUserStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
    ");

    $saveStmt = $pdo->prepare("
        INSERT INTO cash_advance_approval_assignments
            (status_name, dealer, approver_user_id)
        VALUES
            (:status_name, :dealer, :approver_user_id)
        ON DUPLICATE KEY UPDATE
            approver_user_id = VALUES(approver_user_id),
            updated_at = NOW()
    ");

    foreach ($statuses as $status => $label) {
        $approverUserId = trim($assignments[$status] ?? '');
        if ($approverUserId !== '') {
            $validateUserStmt->execute([':user_id' => $approverUserId]);
            if ((int)$validateUserStmt->fetchColumn() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'One of the selected approvers is invalid or inactive.']);
                exit;
            }
        }

        $saveStmt->execute([
            ':status_name' => $status,
            ':dealer' => $dealer,
            ':approver_user_id' => $approverUserId !== '' ? $approverUserId : null,
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'message' => 'Cash Advance approvers updated successfully.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to save Cash Advance approvers.',
        'error' => $e->getMessage(),
    ]);
}
