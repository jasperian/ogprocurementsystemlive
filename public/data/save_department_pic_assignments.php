<?php
header('Content-Type: application/json');
session_start();
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/department_pic_assignments_helper.php';
require_once __DIR__ . '/liquidation_accounting_assignments_helper.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Only Administrator can update Department PIC assignments.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request.']);
    exit;
}

$dealer = trim($_POST['dealer'] ?? '');
$assignments = $_POST['assignments'] ?? [];
$accountingAssignment = $_POST['accounting'] ?? [];

if ($dealer === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Dealer is required.']);
    exit;
}

try {
    ensureDepartmentPicAssignmentsTable($pdo);
    ensureLiquidationAccountingAssignmentsTable($pdo);
    $pdo->beginTransaction();

    $sapEncoderUserIds = array_values(array_unique(array_filter(
        array_map('intval', (array)($accountingAssignment['sap_encoder_user_ids'] ?? [])),
        static fn(int $userId): bool => $userId > 0
    )));
    $accountingApproverUserIds = array_values(array_unique(array_filter(
        array_map('intval', (array)($accountingAssignment['accounting_approver_user_ids'] ?? [])),
        static fn(int $userId): bool => $userId > 0
    )));

    $validateSapEncoderStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
          AND dealer = :dealer
          AND department = 'ACCOUNTING'
          AND position <> 'viewer'
    ");
    foreach ($sapEncoderUserIds as $sapEncoderUserId) {
        $validateSapEncoderStmt->execute([':user_id' => $sapEncoderUserId, ':dealer' => $dealer]);
        if ((int)$validateSapEncoderStmt->fetchColumn() === 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'SAP Document Encoder must be an active Accounting user for ' . $dealer . '.']);
            exit;
        }
    }

    $validateAccountingApproverStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
          AND dealer = :dealer
          AND department IN ('ACCOUNTING', 'FINANCE')
          AND position <> 'viewer'
    ");
    foreach ($accountingApproverUserIds as $accountingApproverUserId) {
        $validateAccountingApproverStmt->execute([':user_id' => $accountingApproverUserId, ':dealer' => $dealer]);
        if ((int)$validateAccountingApproverStmt->fetchColumn() === 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Accounting Approver must be an active Accounting or Finance user for ' . $dealer . '.']);
            exit;
        }
    }

    $deleteAccountingUsersStmt = $pdo->prepare("
        DELETE FROM liquidation_accounting_assignment_users
        WHERE dealer = :dealer
    ");
    $deleteAccountingUsersStmt->execute([':dealer' => $dealer]);

    $saveAccountingUserStmt = $pdo->prepare("
        INSERT INTO liquidation_accounting_assignment_users (dealer, role_name, user_id)
        VALUES (:dealer, :role_name, :user_id)
    ");
    foreach ($sapEncoderUserIds as $sapEncoderUserId) {
        $saveAccountingUserStmt->execute([
            ':dealer' => $dealer,
            ':role_name' => 'SAP Encoder',
            ':user_id' => $sapEncoderUserId,
        ]);
    }
    foreach ($accountingApproverUserIds as $accountingApproverUserId) {
        $saveAccountingUserStmt->execute([
            ':dealer' => $dealer,
            ':role_name' => 'Accounting Approver',
            ':user_id' => $accountingApproverUserId,
        ]);
    }

    $saveAccountingAssignmentStmt = $pdo->prepare("
        INSERT INTO liquidation_accounting_assignments
            (dealer, sap_encoder_user_id, accounting_approver_user_id)
        VALUES
            (:dealer, :sap_encoder_user_id, :accounting_approver_user_id)
        ON DUPLICATE KEY UPDATE
            sap_encoder_user_id = VALUES(sap_encoder_user_id),
            accounting_approver_user_id = VALUES(accounting_approver_user_id),
            updated_at = NOW()
    ");
    $saveAccountingAssignmentStmt->execute([
        ':dealer' => $dealer,
        ':sap_encoder_user_id' => $sapEncoderUserIds[0] ?? null,
        ':accounting_approver_user_id' => $accountingApproverUserIds[0] ?? null,
    ]);

    $validateDepartmentStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM (
            SELECT department
            FROM users
            WHERE dealer = :dealer_users
              AND TRIM(COALESCE(department, '')) <> ''
            UNION
            SELECT budget_department AS department
            FROM budgets
            WHERE budget_dealer = :dealer_budgets
              AND TRIM(COALESCE(budget_department, '')) <> ''
        ) departments
        WHERE UPPER(TRIM(department)) = UPPER(TRIM(:department))
    ");

    $validateUserStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
          AND dealer = :dealer
          AND UPPER(TRIM(department)) = UPPER(TRIM(:department))
    ");

    $validateDepartmentApproverStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
          AND dealer = :dealer
          AND (COALESCE(is_depthead, 0) = 1 OR position = 'Manager')
    ");

    $validateFinalApproverStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM users
        WHERE user_id = :user_id
          AND is_active = 1
          AND (dealer = :dealer OR dealer = 'OG')
          AND (
              position = 'General Manager'
              OR position LIKE '%Branch Manager%'
          )
    ");

    $saveStmt = $pdo->prepare("
        INSERT INTO department_pic_assignments
            (dealer, department, pic_user_id, department_approver_user_id, final_approver_user_id, series_prefix)
        VALUES
            (:dealer, :department, :pic_user_id, :department_approver_user_id, :final_approver_user_id, :series_prefix)
        ON DUPLICATE KEY UPDATE
            department_approver_user_id = VALUES(department_approver_user_id),
            final_approver_user_id = VALUES(final_approver_user_id),
            series_prefix = VALUES(series_prefix),
            updated_at = NOW()
    ");

    $deleteDealerStmt = $pdo->prepare("DELETE FROM department_pic_assignments WHERE dealer = :dealer");
    $deleteDealerStmt->execute([':dealer' => $dealer]);

    $seenRoutes = [];

    foreach ($assignments as $department => $routes) {
        $department = trim((string)$department);

        if ($department === '') {
            continue;
        }

        $validateDepartmentStmt->execute([
            ':dealer_users' => $dealer,
            ':dealer_budgets' => $dealer,
            ':department' => $department,
        ]);
        if ((int)$validateDepartmentStmt->fetchColumn() === 0) {
            $pdo->rollBack();
            echo json_encode(['status' => 'danger', 'message' => 'Invalid department selected: ' . $department]);
            exit;
        }

        if (!is_array($routes)) {
            $routes = [['pic_user_id' => $routes, 'department_approver_user_id' => '', 'final_approver_user_id' => '', 'series_prefix' => '']];
        }

        foreach ($routes as $route) {
            if (!is_array($route)) {
                continue;
            }

            $picUserId = trim((string)($route['pic_user_id'] ?? ''));
            $departmentApproverUserId = trim((string)($route['department_approver_user_id'] ?? ''));
            $finalApproverUserId = trim((string)($route['final_approver_user_id'] ?? ''));
            $seriesPrefix = strtoupper(trim((string)($route['series_prefix'] ?? '')));
            $seriesPrefix = preg_replace('/[^A-Za-z0-9]/', '', $seriesPrefix);
            if ($seriesPrefix === '') {
                $seriesPrefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper($dealer));
            }

            if ($picUserId === '' && $departmentApproverUserId === '' && $finalApproverUserId === '') {
                continue;
            }

            if ($seriesPrefix === '') {
                $seriesPrefix = 'DLR';
            }

            if (strlen($seriesPrefix) > 20) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Series prefix must be 20 letters/numbers or less.']);
                exit;
            }

            if ($picUserId === '' || $departmentApproverUserId === '' || $finalApproverUserId === '') {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Each PIC route must have a Department PIC, Department Manager approver, and final approver.']);
                exit;
            }

            $validateUserStmt->execute([
                ':user_id' => $picUserId,
                ':dealer' => $dealer,
                ':department' => $department,
            ]);
            if ((int)$validateUserStmt->fetchColumn() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Selected PIC must be an active user in ' . $department . ' / ' . $dealer . '.']);
                exit;
            }

            $validateDepartmentApproverStmt->execute([
                ':user_id' => $departmentApproverUserId,
                ':dealer' => $dealer,
            ]);
            if ((int)$validateDepartmentApproverStmt->fetchColumn() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Department Manager approver must be an active manager for ' . $dealer . '.']);
                exit;
            }

            $validateFinalApproverStmt->execute([
                ':user_id' => $finalApproverUserId,
                ':dealer' => $dealer,
            ]);
            if ((int)$validateFinalApproverStmt->fetchColumn() === 0) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'Final approver must be an active General Manager or Branch Manager for ' . $dealer . '.']);
                exit;
            }

            $routeKey = strtoupper($department) . '|' . $picUserId;
            if (isset($seenRoutes[$routeKey])) {
                $pdo->rollBack();
                echo json_encode(['status' => 'danger', 'message' => 'A Department PIC can only have one final approver per department.']);
                exit;
            }
            $seenRoutes[$routeKey] = true;

            $saveStmt->execute([
                ':dealer' => $dealer,
                ':department' => $department,
                ':pic_user_id' => $picUserId,
                ':department_approver_user_id' => $departmentApproverUserId,
                ':final_approver_user_id' => $finalApproverUserId,
                ':series_prefix' => $seriesPrefix,
            ]);
        }
    }

    syncDepartmentPicFlags($pdo);
    $pdo->commit();

    echo json_encode(['status' => 'success', 'message' => 'Department PIC and Accounting assignments updated successfully.']);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to save Department PIC assignments.',
        'error' => $e->getMessage(),
    ]);
}
