<?php
include '../../includes/db.php';
require_once __DIR__ . '/cash_advance_approval_assignments_helper.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';
session_start();
header('Content-Type: application/json');

try {
    if (($_SESSION['department'] ?? '') !== 'PURCHASING') {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Only Purchasing can view Cash Advance monitoring.']);
        exit;
    }

    $dealer = $_SESSION['dealer'] ?? '';
    $statusFilter = trim($_GET['status'] ?? '');
    $dateFrom = trim($_GET['date_from'] ?? '');
    $dateTo = trim($_GET['date_to'] ?? '');

    $hasPoAmount = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'po_amount'")->fetch(PDO::FETCH_ASSOC);
    $hasPoFile = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'po_file'")->fetch(PDO::FETCH_ASSOC);
    $hasSapDocumentNumber = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'sap_document_number'")->fetch(PDO::FETCH_ASSOC);
    $hasGmApprovedAt = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'gm_approved_at'")->fetch(PDO::FETCH_ASSOC);
    $hasPurchaseRequisitionNumber = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'purchase_requisition_number'")->fetch(PDO::FETCH_ASSOC);
    $hasLiquidationRemarks = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'liquidation_remarks'")->fetch(PDO::FETCH_ASSOC);
    $hasRequestType = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'request_type'")->fetch(PDO::FETCH_ASSOC);
    $hasFinalApproverUserId = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'final_approver_user_id'")->fetch(PDO::FETCH_ASSOC);

    $where = [];
    $params = [];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $where[] = "ca.pr_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }

    if ($statusFilter !== '') {
        $where[] = "ca.pr_status = :status";
        $params[':status'] = $statusFilter;
    }

    if ($dateFrom !== '') {
        $where[] = "ca.ca_date >= :date_from";
        $params[':date_from'] = $dateFrom;
    }

    if ($dateTo !== '') {
        $where[] = "ca.ca_date <= :date_to";
        $params[':date_to'] = $dateTo;
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $poAmountSelect = $hasPoAmount ? 'ca.po_amount AS po_amount' : 'NULL AS po_amount';
    $poFileSelect = $hasPoFile ? 'ca.po_file AS po_file' : 'NULL AS po_file';
    $sapSelect = $hasSapDocumentNumber ? 'ca.sap_document_number AS sap_document_number' : 'NULL AS sap_document_number';
    $gmSelect = $hasGmApprovedAt ? 'ca.gm_approved_at AS gm_approved_at' : 'NULL AS gm_approved_at';
    $purchaseRequisitionSelect = $hasPurchaseRequisitionNumber ? 'ca.purchase_requisition_number AS purchase_requisition_number' : 'NULL AS purchase_requisition_number';
    $liquidationRemarksSelect = $hasLiquidationRemarks ? 'ca.liquidation_remarks AS liquidation_remarks' : 'NULL AS liquidation_remarks';
    $requestTypeSelect = $hasRequestType ? 'ca.request_type AS request_type' : "'Cash Advance' AS request_type";

    $summarySql = "
        SELECT
            ca.pr_status,
            COUNT(*) AS status_count,
            SUM(IFNULL(ca.ca_amount, 0)) AS ca_total
            " . ($hasPoAmount ? ", SUM(IFNULL(ca.po_amount, 0)) AS po_total" : ", 0 AS po_total") . "
        FROM cash_advance ca
        $whereSql
        GROUP BY ca.pr_status
    ";
    $summaryStmt = $pdo->prepare($summarySql);
    $summaryStmt->execute($params);
    $summaryRows = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

    $statusOrder = [
        'CA Request',
        'CA Release',
        'For PO',
        'For Liquidation',
        'Liquidated',
        'For GM Approval',
        'For Accounting Approval',
        'Completed',
    ];

    $summary = [];
    foreach ($statusOrder as $status) {
        $summary[$status] = [
            'status' => $status,
            'display_status' => purchasingWorkflowStatusLabel($status, $status === 'Liquidated' ? 'Liquidation' : 'Cash Advance'),
            'count' => 0,
            'ca_total' => 0,
            'po_total' => 0,
        ];
    }

    foreach ($summaryRows as $row) {
        $status = $row['pr_status'] ?: 'Unknown';
        if (!isset($summary[$status])) {
            $summary[$status] = [
                'status' => $status,
                'display_status' => purchasingWorkflowStatusLabel($status, $status === 'Liquidated' ? 'Liquidation' : 'Cash Advance'),
                'count' => 0,
                'ca_total' => 0,
                'po_total' => 0,
            ];
        }

        $summary[$status]['count'] = (int)$row['status_count'];
        $summary[$status]['ca_total'] = (float)$row['ca_total'];
        $summary[$status]['po_total'] = (float)$row['po_total'];
    }

    $totalCount = 0;
    $openCount = 0;
    $completedCount = 0;
    $totalCaAmount = 0;
    $totalPoAmount = 0;

    foreach ($summary as $row) {
        $totalCount += $row['count'];
        $totalCaAmount += $row['ca_total'];
        $totalPoAmount += $row['po_total'];
        if ($row['status'] === 'Completed') {
            $completedCount += $row['count'];
        } else {
            $openCount += $row['count'];
        }
    }

    $detailSql = "
        SELECT
            ca.pr_number AS ca_number,
            $requestTypeSelect,
            $purchaseRequisitionSelect,
            ca.pr_status,
            ca.ca_amount,
            $poAmountSelect,
            $poFileSelect,
            $sapSelect,
            $gmSelect,
            ca.ca_date,
            ca.pr_date,
            ca.pr_department,
            ca.budget_code,
            ca.remarks,
            $liquidationRemarksSelect,
            ca.pr_dealer,
            ca.created_by,
            creator.full_name AS created_by_name,
            " . ($hasFinalApproverUserId ? "ca.final_approver_user_id" : "NULL AS final_approver_user_id") . ",
            " . ($hasFinalApproverUserId ? "assigned_gm.full_name" : "NULL AS assigned_gm_name") . ",
            DATEDIFF(CURDATE(), ca.pr_date) AS aging
        FROM cash_advance ca
        LEFT JOIN users creator ON ca.created_by = creator.user_id
        " . ($hasFinalApproverUserId ? "LEFT JOIN users assigned_gm ON assigned_gm.user_id = ca.final_approver_user_id" : "") . "
        $whereSql
        ORDER BY ca.pr_date DESC, ca.pr_number DESC
    ";
    $detailStmt = $pdo->prepare($detailSql);
    $detailStmt->execute($params);
    $rows = $detailStmt->fetchAll(PDO::FETCH_ASSOC);

    $approverNameCache = [];
    $assignedApproverCache = [];
    $findAssignedApproverName = function (array $row) use ($pdo, &$assignedApproverCache): string {
        $rowDealer = trim($row['pr_dealer'] ?? 'OG');
        $rowDealer = $rowDealer !== '' ? $rowDealer : 'OG';
        if (!isset($assignedApproverCache[$rowDealer])) {
            $assignedApproverCache[$rowDealer] = getCashAdvanceAssignedApprovers($pdo, $rowDealer);
        }

        $assignmentStatus = getCashAdvanceAssignmentStatusKey($row);
        $assignment = $assignedApproverCache[$rowDealer][$assignmentStatus] ?? null;
        if (!$assignment || empty($assignment['user_id']) || empty($assignment['full_name'])) {
            return '';
        }

        return $assignment['full_name'];
    };

    $findApproverNames = function (string $role, string $rowDealer = '') use ($pdo, &$approverNameCache): string {
        $dealerKey = strtoupper(trim($rowDealer));
        $cacheKey = $role . '|' . $dealerKey;
        if (isset($approverNameCache[$cacheKey])) {
            return $approverNameCache[$cacheKey];
        }

        $where = ["is_active = 1"];
        $params = [];

        if ($dealerKey !== '' && $dealerKey !== 'OG') {
            $where[] = "(dealer = :dealer OR dealer = 'OG')";
            $params[':dealer'] = $rowDealer;
        }

        if ($role === 'Purchasing Manager') {
            $where[] = "department = 'PURCHASING'";
            $where[] = "position = 'Manager'";
        } elseif ($role === 'Purchasing Staff') {
            $where[] = "department = 'PURCHASING'";
            $where[] = "position NOT IN ('Manager', 'viewer')";
        } elseif ($role === 'Accounting Staff') {
            $where[] = "department = 'ACCOUNTING'";
            $where[] = "position NOT IN ('Manager', 'viewer')";
        } elseif ($role === 'Accounting Manager') {
            $where[] = "department = 'ACCOUNTING'";
            $where[] = "position = 'Manager'";
        } elseif ($role === 'General Manager') {
            $where[] = "position = 'General Manager'";
        } elseif ($role === 'Department Head') {
            $where[] = "(is_depthead = 1 OR position = 'Manager')";
        } else {
            $approverNameCache[$cacheKey] = $role;
            return $role;
        }

        $sql = "
            SELECT full_name
            FROM users
            WHERE " . implode(' AND ', $where) . "
            ORDER BY
                CASE WHEN dealer = :exact_dealer THEN 0 ELSE 1 END,
                full_name ASC
            LIMIT 3
        ";
        $params[':exact_dealer'] = $rowDealer;

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $names = array_filter($stmt->fetchAll(PDO::FETCH_COLUMN));

        $fallbackRole = $role === 'Accounting Staff' ? 'Accounting Officer' : $role;
        $label = $names ? implode(', ', $names) : $fallbackRole;
        $approverNameCache[$cacheKey] = $label;
        return $label;
    };

    foreach ($rows as &$row) {
        $amount = (float)($row['ca_amount'] ?? 0);
        $gmApprovedAt = $row['gm_approved_at'] ?? null;
        $rowDealer = $row['pr_dealer'] ?? '';
        $row['display_status'] = purchasingWorkflowStatusLabel(
            (string)($row['pr_status'] ?? ''),
            (string)($row['request_type'] ?? 'Cash Advance')
        );
        $row['phase_label'] = purchasingWorkflowPhaseLabel($row);
        $assignedApproverName = in_array(($row['pr_status'] ?? ''), ['For PO', 'Liquidated'], true)
            ? ''
            : $findAssignedApproverName($row);

        if ($assignedApproverName !== '') {
            $row['next_approver'] = $assignedApproverName;
            continue;
        }

        switch ($row['pr_status'] ?? '') {
            case 'CA Request':
                $row['next_approver'] = $findApproverNames('Purchasing Manager', $rowDealer);
                break;
            case 'CA Release':
                $row['next_approver'] = $findApproverNames('Accounting Staff', $rowDealer);
                break;
            case 'For PO':
                $row['next_approver'] = $row['created_by_name'] ?: 'Request Creator';
                break;
            case 'For Liquidation':
                $row['next_approver'] = $findApproverNames('Purchasing Manager', $rowDealer);
                break;
            case 'Liquidated':
                $row['next_approver'] = $findApproverNames('Accounting Staff', $rowDealer);
                break;
            case 'For GM Approval':
                $row['next_approver'] = $row['assigned_gm_name'] ?: $findApproverNames('General Manager', $rowDealer);
                break;
            case 'For SAP Document':
                $row['next_approver'] = $findApproverNames('Accounting Staff', $rowDealer);
                break;
            case 'For Accounting Approval':
                $row['next_approver'] = $findApproverNames('Accounting Staff', $rowDealer);
                break;
            case 'Completed':
            case 'Cancelled':
            case 'Rejected':
                $row['next_approver'] = 'None';
                break;
            default:
                $row['next_approver'] = 'N/A';
                break;
        }
    }
    unset($row);

    echo json_encode([
        'status' => 'success',
        'summary' => array_values($summary),
        'totals' => [
            'total_count' => $totalCount,
            'open_count' => $openCount,
            'completed_count' => $completedCount,
            'total_ca_amount' => $totalCaAmount,
            'total_po_amount' => $totalPoAmount,
        ],
        'data' => $rows,
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to load Cash Advance monitoring.',
        'error' => $e->getMessage(),
        'summary' => [],
        'totals' => [],
        'data' => [],
    ]);
}
