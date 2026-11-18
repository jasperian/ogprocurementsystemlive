<?php
session_start();
header('Content-Type: application/json');
include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';

liquidation_require_login();

$mode = $_GET['mode'] ?? 'mine';
$status = trim($_GET['status'] ?? '');
$requestType = trim($_GET['request_type'] ?? '');
$scope = trim($_GET['scope'] ?? '');
$dealer = trim($_SESSION['dealer'] ?? '');
$department = trim($_SESSION['department'] ?? '');
$userId = (int)($_SESSION['user_id'] ?? 0);

try {
    $where = [];
    $params = [];

    if ($mode === 'manager_approval') {
        if (!liquidation_is_department_head()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only Department Managers can view department approval requests.', 'data' => []]);
            exit;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }

        if ($department !== '') {
            $where[] = '(l.department_approver_user_id = :department_approver_user_id
                OR (l.department_approver_user_id IS NULL AND UPPER(TRIM(l.department)) = UPPER(TRIM(:department))))';
            $params[':department'] = $department;
        } else {
            $where[] = 'l.department_approver_user_id = :department_approver_user_id';
        }
        $params[':department_approver_user_id'] = $userId;

        if ($status === '') {
            $where[] = "l.status = 'For Department Manager Approval'";
        }
    } elseif ($mode === 'approval') {
        if (!liquidation_can_final_approve()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only assigned final approvers can view approval requests.', 'data' => []]);
            exit;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }

        if ($status === '') {
            $where[] = "l.status = 'For GM Approval'";
        }

        if (liquidation_is_general_manager()) {
            $where[] = "(l.final_approver_user_id = :final_approver_user_id OR l.final_approver_user_id IS NULL)";
        } else {
            $where[] = "l.final_approver_user_id = :final_approver_user_id";
        }
        $params[':final_approver_user_id'] = $userId;
    } elseif ($mode === 'secretary_gm_monitoring') {
        if (!liquidation_is_secretary()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only Secretaries can view GM approval monitoring.', 'data' => []]);
            exit;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }

    } elseif ($mode === 'accounting') {
        if (!liquidation_is_accounting_user()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only Accounting can view liquidation completion.', 'data' => []]);
            exit;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }

        if ($status === '') {
            $where[] = "l.status IN ('Liquidated', 'For Accounting Approval')";
        }
    } elseif ($mode === 'manager_liquidation') {
        if (!liquidation_can_encode_department_request()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only Department PICs or Department Managers can view Cash Advances for liquidation.', 'data' => []]);
            exit;
        }

        $where[] = "(l.request_type = 'Cash Advance' OR (l.request_type = 'Request for Payment' AND l.requires_liquidation = 1 AND l.created_by = :rfp_creator))";
        $params[':rfp_creator'] = $userId;
        $where[] = "l.status = 'Completed'";
        $where[] = "NOT EXISTS (
            SELECT 1
            FROM liquidations child
            WHERE child.source_cash_advance_id = l.liquidation_id OR child.source_rfp_id = l.liquidation_id
        )";

        if ($department !== '') {
            $where[] = 'UPPER(TRIM(l.department)) = UPPER(TRIM(:department))';
            $params[':department'] = $department;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }
    } else {
        if (!liquidation_can_encode_department_request()) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'Only Department PICs or Department Managers can view liquidations.', 'data' => []]);
            exit;
        }

        if ($department !== '') {
            $where[] = 'UPPER(TRIM(l.department)) = UPPER(TRIM(:department))';
            $params[':department'] = $department;
        }

        if (!liquidation_is_department_head()) {
            $where[] = 'l.created_by = :created_by';
            $params[':created_by'] = $userId;
        }

        if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
            $where[] = 'l.dealer = :dealer';
            $params[':dealer'] = $dealer;
        }
    }

    if ($status !== '') {
        if (!in_array($status, ['For Department Manager Approval', 'For GM Approval', 'For Liquidation', 'Liquidated', 'For Accounting Approval', 'Rejected', 'Completed'], true)) {
            echo json_encode(['status' => 'danger', 'message' => 'Invalid status filter.', 'data' => []]);
            exit;
        }
        $where[] = 'l.status = :status';
        $params[':status'] = $status;
    }

    if ($scope !== '') {
        if ($scope !== 'active') {
            echo json_encode(['status' => 'danger', 'message' => 'Invalid request scope.', 'data' => []]);
            exit;
        }
        $where[] = "l.status <> 'Completed'";
    }

    if ($requestType !== '') {
        if (!in_array($requestType, ['Liquidation', 'Request for Payment', 'Cash Advance'], true)) {
            echo json_encode(['status' => 'danger', 'message' => 'Invalid request type filter.', 'data' => []]);
            exit;
        }
        $where[] = 'l.request_type = :request_type';
        $params[':request_type'] = $requestType;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $stmt = $pdo->prepare("
        SELECT
            l.*,
            (SELECT child.liquidation_number FROM liquidations child WHERE child.source_rfp_id = l.liquidation_id ORDER BY child.liquidation_id DESC LIMIT 1) AS linked_rfp_liquidation_number,
            (SELECT child.status FROM liquidations child WHERE child.source_rfp_id = l.liquidation_id ORDER BY child.liquidation_id DESC LIMIT 1) AS linked_rfp_liquidation_status,
            creator.full_name AS created_by_name,
            creator.position AS created_by_position,
            COALESCE(creator.is_depthead, 0) AS created_by_is_depthead,
            approver.full_name AS approved_by_name,
            liquidation_approver.full_name AS liquidation_approved_by_name,
            rejector.full_name AS rejected_by_name,
            completer.full_name AS completed_by_name,
            sap_user.full_name AS sap_document_by_name,
            department_assigned_approver.full_name AS department_approver_name,
            department_assigned_approver.position AS department_approver_position,
            final_approver.full_name AS final_approver_name,
            final_approver.position AS final_approver_position,
            (
                SELECT child.status
                FROM liquidations child
                WHERE child.source_cash_advance_id = l.liquidation_id
                ORDER BY child.liquidation_id DESC
                LIMIT 1
            ) AS linked_liquidation_status,
            CASE
                WHEN l.status = 'For Department Manager Approval' THEN COALESCE((
                    SELECT assigned.full_name
                    FROM users assigned
                    WHERE assigned.user_id = l.department_approver_user_id
                      AND assigned.is_active = 1
                    LIMIT 1
                ), (
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM users u
                    WHERE (u.is_depthead = 1 OR u.position = 'Manager')
                      AND UPPER(TRIM(u.department)) = UPPER(TRIM(l.department))
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                ), 'Department Manager not assigned')
                WHEN l.status = 'For GM Approval' THEN COALESCE((
                    SELECT assigned.full_name
                    FROM users assigned
                    WHERE assigned.user_id = l.final_approver_user_id
                      AND assigned.is_active = 1
                    LIMIT 1
                ), (
                    SELECT u.full_name
                    FROM users u
                    WHERE u.position = 'General Manager'
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                    ORDER BY u.full_name ASC
                    LIMIT 1
                ), 'Final approver not assigned')
                WHEN l.status = 'Liquidated' AND l.request_type = 'Cash Advance' THEN COALESCE((
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM users u
                    WHERE u.department = 'ACCOUNTING'
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                ), 'Accounting not assigned')
                WHEN l.status = 'Liquidated' THEN COALESCE((
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM users u
                    WHERE u.department = 'ACCOUNTING'
                      AND u.position NOT IN ('Manager', 'viewer')
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                ), 'Accounting Staff not assigned')
                WHEN l.status = 'For Accounting Approval'
                 AND COALESCE(l.workflow_version, 1) >= 2
                 AND l.request_type = 'Request for Payment' THEN COALESCE((
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM users u
                    WHERE u.department = 'ACCOUNTING'
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                ), 'Accounting not assigned')
                WHEN l.status = 'For Accounting Approval' THEN COALESCE((
                    SELECT u.full_name
                    FROM users u
                    WHERE u.department = 'ACCOUNTING'
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                    ORDER BY
                        CASE
                            WHEN u.department = 'ACCOUNTING' AND u.position = 'Manager' THEN 0
                            ELSE 1
                        END,
                        u.full_name ASC
                    LIMIT 1
                ), 'Accounting Manager not assigned')
                WHEN l.status = 'For Liquidation' THEN COALESCE((
                    SELECT GROUP_CONCAT(u.full_name ORDER BY u.full_name SEPARATOR ', ')
                    FROM users u
                    WHERE (u.is_depthead = 1 OR u.position = 'Manager')
                      AND UPPER(TRIM(u.department)) = UPPER(TRIM(l.department))
                      AND u.dealer = l.dealer
                      AND u.is_active = 1
                ), 'Manager not assigned')
                WHEN l.status = 'Completed' THEN 'None'
                WHEN l.status = 'Rejected' THEN 'None'
                ELSE 'None'
            END AS next_approver
        FROM liquidations l
        LEFT JOIN users creator ON creator.user_id = l.created_by
        LEFT JOIN users approver ON approver.user_id = l.approved_by
        LEFT JOIN users liquidation_approver ON liquidation_approver.user_id = l.liquidation_approved_by
        LEFT JOIN users rejector ON rejector.user_id = l.rejected_by
        LEFT JOIN users completer ON completer.user_id = l.completed_by
        LEFT JOIN users sap_user ON sap_user.user_id = l.sap_document_by
        LEFT JOIN users department_assigned_approver ON department_assigned_approver.user_id = l.department_approver_user_id
        LEFT JOIN users final_approver ON final_approver.user_id = l.final_approver_user_id
        $whereSql
        ORDER BY l.submitted_at DESC, l.liquidation_id DESC
        LIMIT 1000
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['requires_sap'] = liquidation_requires_sap($row);
        $row['requires_final_approval'] = liquidation_requires_final_approval($row);
        $dealer = (string)($row['dealer'] ?? '');
        $sapEncoders = getLiquidationAccountingAssignedUsers($pdo, $dealer, 'SAP Encoder');
        $accountingRole = ($row['request_type'] ?? '') === 'Cash Advance' ? 'CA Release' : 'Accounting Completion';
        $accountingApprovers = getLiquidationAccountingAssignedUsers($pdo, $dealer, $accountingRole);
        $isCompletionStatus = (($row['request_type'] ?? '') === 'Cash Advance' && ($row['status'] ?? '') === 'Liquidated')
            || (($row['request_type'] ?? '') !== 'Cash Advance' && ($row['status'] ?? '') === 'For Accounting Approval');
        $row['can_accounting_complete'] = $isCompletionStatus && liquidation_can_complete_request($row);
        $row['can_encode_sap'] = ($row['status'] ?? '') === 'Liquidated'
            && $row['requires_sap']
            && liquidation_can_encode_sap_for_dealer($pdo, (string)($row['dealer'] ?? ''), $userId);

        if (($row['status'] ?? '') === 'Liquidated'
            && ($row['requires_sap'] ?? false)
            && $sapEncoders) {
            $row['next_approver'] = liquidation_accounting_assignee_names($sapEncoders);
        } elseif ($isCompletionStatus && $accountingApprovers) {
            $row['next_approver'] = liquidation_accounting_assignee_names($accountingApprovers);
        }
    }
    unset($row);

    echo json_encode([
        'status' => 'success',
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to load liquidations: ' . $e->getMessage(),
        'data' => []
    ]);
}
