<?php
header('Content-Type: application/json');
session_start();
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/budget_access_helper.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized.']);
    exit;
}
if (!budgetAccessCanManage($_SESSION)) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Administrators or Accounting users can manage budget access.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request method.']);
    exit;
}

ensureBudgetUserAccessTable($pdo);
$action = trim($_POST['action'] ?? '');
$canManageAllDealers = budgetAccessCanManageAllDealers($_SESSION);
$sessionDealer = trim($_SESSION['dealer'] ?? '');

if ($sessionDealer === '') {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Your account has no assigned dealer.']);
    exit;
}

try {
    if ($action === 'revoke') {
        $accessId = (int)($_POST['access_id'] ?? 0);
        if ($accessId <= 0) {
            throw new InvalidArgumentException('Invalid access grant.');
        }
        if ($canManageAllDealers) {
            $stmt = $pdo->prepare('DELETE FROM budget_user_access WHERE access_id = :access_id');
            $stmt->execute([':access_id' => $accessId]);
        } else {
            $stmt = $pdo->prepare("
                DELETE bua
                FROM budget_user_access bua
                INNER JOIN budgets b ON b.budget_id = bua.budget_id
                WHERE bua.access_id = :access_id
                  AND UPPER(TRIM(b.budget_dealer)) = UPPER(TRIM(:dealer))
            ");
            $stmt->execute([':access_id' => $accessId, ':dealer' => $sessionDealer]);
            if ($stmt->rowCount() === 0) {
                http_response_code(403);
                echo json_encode(['status' => 'danger', 'message' => 'You can only revoke access for your assigned dealer.']);
                exit;
            }
        }
        echo json_encode(['status' => 'success', 'message' => 'Budget access revoked.']);
        exit;
    }

    if ($action !== 'grant') {
        throw new InvalidArgumentException('Invalid action.');
    }

    $userId = (int)($_POST['user_id'] ?? 0);
    $budgetId = (int)($_POST['budget_id'] ?? 0);
    if ($userId <= 0 || $budgetId <= 0) {
        throw new InvalidArgumentException('PIC and budget code are required.');
    }

    $picStmt = $pdo->prepare("
        SELECT u.user_id, u.department, u.dealer
        FROM users u
        INNER JOIN department_pic_assignments dpa
            ON dpa.pic_user_id = u.user_id
           AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(u.department))
           AND UPPER(TRIM(dpa.dealer)) = UPPER(TRIM(u.dealer))
        WHERE u.user_id = :user_id AND u.is_active = 1
        LIMIT 1
    ");
    $picStmt->execute([':user_id' => $userId]);
    $pic = $picStmt->fetch(PDO::FETCH_ASSOC);
    if (!$pic) {
        http_response_code(422);
        echo json_encode(['status' => 'danger', 'message' => 'Selected user is not an active assigned Department PIC.']);
        exit;
    }

    $budgetStmt = $pdo->prepare('SELECT budget_id, budget_department, budget_dealer FROM budgets WHERE budget_id = :budget_id');
    $budgetStmt->execute([':budget_id' => $budgetId]);
    $budget = $budgetStmt->fetch(PDO::FETCH_ASSOC);
    if (!$budget) {
        throw new InvalidArgumentException('Selected budget code does not exist.');
    }
    if (strcasecmp(trim($pic['dealer']), trim($budget['budget_dealer'])) !== 0) {
        http_response_code(422);
        echo json_encode(['status' => 'danger', 'message' => 'PIC and budget code must belong to the same dealer.']);
        exit;
    }
    if (!$canManageAllDealers && strcasecmp(trim($budget['budget_dealer']), $sessionDealer) !== 0) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'You can only grant access for your assigned dealer.']);
        exit;
    }
    if (strcasecmp(trim($pic['department']), trim($budget['budget_department'])) === 0) {
        http_response_code(422);
        echo json_encode(['status' => 'danger', 'message' => 'A grant is unnecessary for the PIC’s own department budget.']);
        exit;
    }

    $save = $pdo->prepare("
        INSERT INTO budget_user_access (budget_id, user_id, granted_by)
        VALUES (:budget_id, :user_id, :granted_by)
        ON DUPLICATE KEY UPDATE granted_by = VALUES(granted_by), updated_at = NOW()
    ");
    $save->execute([
        ':budget_id' => $budgetId,
        ':user_id' => $userId,
        ':granted_by' => (int)$_SESSION['user_id'],
    ]);
    echo json_encode(['status' => 'success', 'message' => 'Budget access granted.']);
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'danger', 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'danger', 'message' => 'Failed to update budget access.']);
}
