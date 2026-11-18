<?php

declare(strict_types=1);

const PURCHASING_WORKFLOW_THRESHOLD = 10000.00;
const PURCHASING_TNESC_SIGNATORY_CREATOR = 'ltarrayo';

function purchasingWorkflowIsTnescApprover(string $position): bool
{
    return strtoupper(trim($position)) === 'TNESC BRANCH MANAGER';
}

function purchasingWorkflowVisibleRequests(PDO $pdo, array $session): array
{
    $userId = (int)($session['user_id'] ?? 0);
    $department = strtoupper(trim((string)($session['department'] ?? '')));
    $position = strtoupper(trim((string)($session['position'] ?? '')));
    $dealer = trim((string)($session['dealer'] ?? ''));
    $isTneApprover = purchasingWorkflowIsTnescApprover($position);
    if ($userId <= 0 || (!in_array($department, ['PURCHASING', 'ACCOUNTING', 'FINANCE'], true)
        && !in_array($position, ['GENERAL MANAGER', 'ADMINISTRATOR'], true) && !$isTneApprover)) {
        return [];
    }

    $hasLegacyRfpLink = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'source_rfp_id'")->fetch(PDO::FETCH_ASSOC);
    $linkedSelect = $hasLegacyRfpLink
        ? "EXISTS(SELECT 1 FROM cash_advance linked_rfp WHERE linked_rfp.source_rfp_id = ca.pr_id)"
        : '0';
    $sql = "SELECT ca.*, u.full_name AS created_by_name, u.department AS creator_department, u.username AS creator_username,
            {$linkedSelect} AS has_linked_liquidation
        FROM cash_advance ca LEFT JOIN users u ON u.user_id = ca.created_by
        WHERE (ca.pr_status NOT IN ('Completed', 'Cancelled')";
    if ($hasLegacyRfpLink) {
        $sql .= " OR (ca.request_type = 'Request for Payment'
            AND ca.requires_liquidation = 1
            AND ca.pr_status = 'Completed'
            AND ca.created_by = :linked_rfp_creator
            AND NOT EXISTS (SELECT 1 FROM cash_advance linked_rfp WHERE linked_rfp.source_rfp_id = ca.pr_id))";
    }
    $sql .= ')';
    $params = [];
    if ($hasLegacyRfpLink) {
        $params[':linked_rfp_creator'] = $userId;
    }
    if ($isTneApprover) {
        $sql .= " AND LOWER(TRIM(u.username)) = :tnesc_creator AND ca.pr_status = 'For GM Approval'";
        $params[':tnesc_creator'] = PURCHASING_TNESC_SIGNATORY_CREATOR;
    } elseif ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $sql .= ' AND ca.pr_dealer = :dealer';
        $params[':dealer'] = $dealer;
    }
    $sql .= ' ORDER BY ca.pr_date DESC, ca.pr_id DESC LIMIT 1000';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $visible = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row['can_create_linked_liquidation'] = ($row['request_type'] ?? '') === 'Request for Payment'
            && ($row['pr_status'] ?? '') === 'Completed'
            && (int)($row['requires_liquidation'] ?? 0) === 1
            && (int)($row['created_by'] ?? 0) === $userId
            && (int)($row['has_linked_liquidation'] ?? 0) === 0;
        $row['next_status'] = purchasingWorkflowNextStatus($row);
        $row['can_act'] = $row['next_status'] !== null
            && purchasingWorkflowActionAllowed($row, $position, $department, $userId);
        if (!$row['can_act'] && !$row['can_create_linked_liquidation']
            && !purchasingWorkflowDepartmentLiquidationVisible($row, $department)) {
            continue;
        }
        $row['display_status'] = purchasingWorkflowStatusLabel((string)$row['pr_status'], (string)($row['request_type'] ?? 'Cash Advance'));
        $row['phase_label'] = purchasingWorkflowPhaseLabel($row);
        $row['requires_sap_next'] = $row['next_status'] !== null
            && purchasingWorkflowRequiresSap($row, (string)$row['pr_status'], (string)$row['next_status']);
        if ($row['can_create_linked_liquidation']) {
            $row['display_status'] = 'For Liquidation';
            $row['phase_label'] = 'RFP - Create Linked Liquidation';
        }
        $visible[] = $row;
    }
    return $visible;
}

function purchasingWorkflowResolveFinalApproverId(
    PDO $pdo,
    string $dealer,
    string $department,
    int $creatorUserId
): ?int {
    try {
        $stmt = $pdo->prepare("
            SELECT dpa.final_approver_user_id
            FROM department_pic_assignments dpa
            INNER JOIN users approver
                ON approver.user_id = dpa.final_approver_user_id
               AND approver.is_active = 1
               AND (approver.position = 'General Manager' OR approver.position LIKE '%Branch Manager%')
            WHERE dpa.pic_user_id = :creator_user_id
              AND dpa.dealer = :dealer
              AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(:department))
            LIMIT 1
        ");
        $stmt->execute([
            ':creator_user_id' => $creatorUserId,
            ':dealer' => $dealer,
            ':department' => $department,
        ]);
        $approverId = (int)$stmt->fetchColumn();
        if ($approverId > 0) {
            return $approverId;
        }

        // Use the configured dealer-wide GM when this creator has no override.
        $stmt = $pdo->prepare("
            SELECT a.approver_user_id
            FROM cash_advance_approval_assignments a
            INNER JOIN users approver
                ON approver.user_id = a.approver_user_id
               AND approver.is_active = 1
            WHERE a.status_name = 'For GM Approval'
              AND a.dealer IN (:dealer, 'OG')
            ORDER BY CASE WHEN a.dealer = :dealer_order THEN 0 ELSE 1 END
            LIMIT 1
        ");
        $stmt->execute([':dealer' => $dealer, ':dealer_order' => $dealer]);
        $approverId = (int)$stmt->fetchColumn();
        if ($approverId > 0) {
            return $approverId;
        }

        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE is_active = 1
              AND position = 'General Manager'
              AND (dealer = :dealer OR dealer = 'OG')
            ORDER BY CASE WHEN dealer = :dealer_order THEN 0 ELSE 1 END, full_name ASC
            LIMIT 1
        ");
        $stmt->execute([':dealer' => $dealer, ':dealer_order' => $dealer]);
        $approverId = (int)$stmt->fetchColumn();
        return $approverId > 0 ? $approverId : null;
    } catch (Throwable $e) {
        // Older installations may not have the routing table/column yet.
        return null;
    }
}

function purchasingWorkflowNextStatus(array $request): ?string
{
    $status = (string)($request['pr_status'] ?? '');
    $type = (string)($request['request_type'] ?? 'Cash Advance');
    $amount = (float)($request['ca_amount'] ?? 0);

    if ($status === 'For Accounting Approval') return 'Completed';

    if ($type === 'Cash Advance') {
        return match ($status) {
            'CA Request' => 'CA Release',
            'CA Release' => 'For PO',
            'For PO' => 'For Liquidation',
            'For Liquidation' => 'Liquidated',
            'Liquidated' => 'For Accounting Approval',
            'For GM Approval' => 'For Accounting Approval',
            default => null,
        };
    }

    if ($type === 'Request for Payment') {
        return match ($status) {
            'CA Request' => 'For GM Approval',
            'Liquidated' => 'For GM Approval', // Compatibility for RFPs already in the former SAP stage.
            'For GM Approval' => 'CA Release',
            'CA Release' => 'Completed',
            default => null,
        };
    }

    if ($type === 'Liquidation') {
        return match ($status) {
            'CA Request' => 'Liquidated',
            'Liquidated' => (($type === 'Liquidation' && !empty($request['source_rfp_id'])) || $amount > PURCHASING_WORKFLOW_THRESHOLD)
                ? 'For GM Approval' : 'For Accounting Approval',
            'For GM Approval' => 'For Accounting Approval',
            default => null,
        };
    }

    return null;
}

function purchasingWorkflowStatusLabel(string $status, string $requestType = 'Cash Advance'): string
{
    return match ($status) {
        'CA Request' => 'For Department Head Approval',
        'CA Release' => 'For Release',
        'For PO' => 'For Liquidation',
        'For Liquidation' => 'For Department Head Approval',
        'Liquidated' => 'For SAP Document Number',
        'For Accounting Approval' => 'For Accounting Completion',
        default => $status,
    };
}

function purchasingWorkflowPhaseLabel(array $request): ?string
{
    if (($request['request_type'] ?? 'Cash Advance') !== 'Cash Advance') {
        return null;
    }

    return match ((string)($request['pr_status'] ?? '')) {
        'For PO' => 'CA Liquidation - For Submission',
        'For Liquidation' => 'CA Liquidation - Department Head Approval',
        'Liquidated' => 'CA Liquidation - SAP Document',
        'For Accounting Approval' => 'CA Liquidation - Accounting Completion',
        'Completed' => 'CA Liquidation - Completed',
        default => null,
    };
}

function purchasingWorkflowActionAllowed(array $request, string $position, string $department, int $userId): bool
{
    $status = (string)($request['pr_status'] ?? '');
    $type = (string)($request['request_type'] ?? 'Cash Advance');
    $position = strtoupper(trim($position));
    $department = strtoupper(trim($department));
    if ($status === 'For GM Approval' && strtolower(trim((string)($request['creator_username'] ?? ''))) === PURCHASING_TNESC_SIGNATORY_CREATOR) {
        return $userId > 0 && purchasingWorkflowIsTnescApprover($position);
    }
    $isDepartmentHead = in_array($position, ['MANAGER', 'PURCHASING MANAGER'], true);
    $isAccounting = in_array($department, ['ACCOUNTING', 'FINANCE'], true);

    if ($department === 'ACCOUNTING') {
        $isAccountingManager = in_array($position, ['MANAGER', 'ACCOUNTING MANAGER'], true);
        return match ($status) {
            'Liquidated' => true,
            'CA Release' => $type === 'Request for Payment' || ($isAccountingManager && $type === 'Cash Advance'),
            'For Accounting Approval' => $isAccountingManager,
            default => false,
        };
    }

    return match ($status) {
        'CA Request' => $isDepartmentHead,
        'CA Release' => in_array($type, ['Cash Advance', 'Request for Payment'], true) && $isAccounting,
        'For PO' => $type === 'Cash Advance' && (int)($request['created_by'] ?? 0) === $userId,
        'For Liquidation' => $type === 'Cash Advance' && $isDepartmentHead,
        'Liquidated' => $isAccounting,
        'For GM Approval' => $position === 'GENERAL MANAGER'
            && ((int)($request['final_approver_user_id'] ?? 0) === 0
                || (int)$request['final_approver_user_id'] === $userId),
        'For Accounting Approval' => $isAccounting,
        default => false,
    };
}

function purchasingWorkflowDepartmentLiquidationVisible(array $request, string $department): bool
{
    $department = strtoupper(trim($department));

    // The form's department identifies the budget/request department, which
    // can differ from the department of the employee who created it.
    $creatorDepartment = trim((string)($request['creator_department'] ?? ''));
    $sharedDepartment = $creatorDepartment !== ''
        ? $creatorDepartment
        : (string)($request['pr_department'] ?? '');

    return $department !== '' && $department !== 'ACCOUNTING'
        && strtoupper(trim($sharedDepartment)) === $department
        && in_array($request['request_type'] ?? 'Cash Advance', ['Cash Advance', 'Request for Payment'], true)
        && in_array($request['pr_status'] ?? '', ['For PO', 'For Liquidation'], true);
}

function purchasingWorkflowRequiresSap(array $request, string $currentStatus, string $nextStatus): bool
{
    return ($currentStatus === 'Liquidated'
            && ($request['request_type'] ?? '') !== 'Request for Payment'
            && in_array($nextStatus, ['For GM Approval', 'For Accounting Approval'], true))
        || (($request['request_type'] ?? '') === 'Request for Payment'
            && $currentStatus === 'CA Release' && $nextStatus === 'Completed');
}
