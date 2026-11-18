<?php

function ensureDepartmentPicAssignmentsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS department_pic_assignments (
            assignment_id INT AUTO_INCREMENT PRIMARY KEY,
            dealer VARCHAR(50) NOT NULL,
            department VARCHAR(255) NOT NULL,
            pic_user_id INT NULL,
            department_approver_user_id INT NULL,
            final_approver_user_id INT NULL,
            series_prefix VARCHAR(20) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_department_pic_route (dealer, department, pic_user_id),
            INDEX idx_department_pic_user (pic_user_id),
            INDEX idx_department_pic_department_approver (department_approver_user_id),
            INDEX idx_department_pic_final_approver (final_approver_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function syncDepartmentPicFlags(PDO $pdo): void
{
    $pdo->exec("UPDATE users SET is_dept_pic = 0");
    $pdo->exec("
        UPDATE users u
        INNER JOIN department_pic_assignments dpa ON dpa.pic_user_id = u.user_id
        SET u.is_dept_pic = 1
        WHERE dpa.pic_user_id IS NOT NULL
    ");
}

function getDepartmentPicFinalApprover(PDO $pdo, string $dealer, string $department, int $picUserId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            dpa.final_approver_user_id,
            dpa.series_prefix,
            approver.full_name,
            approver.position,
            approver.department,
            approver.dealer
        FROM department_pic_assignments dpa
        LEFT JOIN users approver ON approver.user_id = dpa.final_approver_user_id
        WHERE dpa.pic_user_id = :pic_user_id
          AND dpa.dealer = :dealer
          AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(:department))
        LIMIT 1
    ");
    $stmt->execute([
        ':pic_user_id' => $picUserId,
        ':dealer' => $dealer,
        ':department' => $department,
    ]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    return $route ?: null;
}

function getDepartmentPicApprovalRoute(PDO $pdo, string $dealer, string $department, int $picUserId): ?array
{
    $stmt = $pdo->prepare("
        SELECT
            dpa.department_approver_user_id,
            department_approver.full_name AS department_approver_name,
            department_approver.position AS department_approver_position,
            department_approver.department AS department_approver_department,
            department_approver.dealer AS department_approver_dealer,
            dpa.final_approver_user_id,
            dpa.series_prefix,
            final_approver.full_name AS final_approver_name,
            final_approver.position AS final_approver_position,
            final_approver.department AS final_approver_department,
            final_approver.dealer AS final_approver_dealer
        FROM department_pic_assignments dpa
        LEFT JOIN users department_approver ON department_approver.user_id = dpa.department_approver_user_id
        LEFT JOIN users final_approver ON final_approver.user_id = dpa.final_approver_user_id
        WHERE dpa.pic_user_id = :pic_user_id
          AND dpa.dealer = :dealer
          AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(:department))
        LIMIT 1
    ");
    $stmt->execute([
        ':pic_user_id' => $picUserId,
        ':dealer' => $dealer,
        ':department' => $department,
    ]);
    $route = $stmt->fetch(PDO::FETCH_ASSOC);

    return $route ?: null;
}

function getDepartmentLiquidationApprovalStatuses(): array
{
    return [
        'Department PIC' => [
            'label' => 'Department PIC / Encoder',
            'description' => 'Creates Cash Advance, Liquidation, and RFP requests',
        ],
        'For Department Manager Approval' => [
            'label' => 'Department Manager Approval',
            'description' => 'Reviews requests encoded by the Department PIC',
        ],
        'For GM Approval' => [
            'label' => 'Final Approver Approval',
            'description' => 'Approves Liquidations after SAP and RFPs above PHP 10,000',
        ],
        'Liquidated' => [
            'label' => 'Accounting Processing',
            'description' => 'CA is released; Liquidation is encoded with a SAP document number',
        ],
        'For Accounting Approval' => [
            'label' => 'Accounting Completion',
            'description' => 'Accounting Manager verifies Liquidations; Accounting completes RFPs',
        ],
        'Completed' => [
            'label' => 'Completed',
            'description' => 'Request is fully completed',
        ],
    ];
}

function formatDepartmentPicUserLabel(?array $userRow): string
{
    if (!$userRow) {
        return 'Not assigned';
    }

    return trim(
        ($userRow['full_name'] ?? '') .
        ' - ' .
        (($userRow['department'] ?? '') ?: 'No Department') .
        ' / ' .
        (($userRow['position'] ?? '') ?: 'No Position') .
        ' / ' .
        (($userRow['dealer'] ?? '') ?: 'No Dealer')
    );
}

function getDepartmentLiquidationDefaultApproverLabels(PDO $pdo, string $dealer, string $department): array
{
    $labels = [
        'For Department Manager Approval' => 'No active Department Manager found',
        'For GM Approval' => 'No active final approver found',
        'Liquidated' => 'No active Accounting user found',
        'For Accounting Approval' => 'No active Accounting Manager found',
        'Completed' => 'No approver required',
    ];

    $managerStmt = $pdo->prepare("
        SELECT full_name, department, position, dealer
        FROM users
        WHERE is_active = 1
          AND dealer = :dealer
          AND UPPER(TRIM(department)) = UPPER(TRIM(:department))
          AND (COALESCE(is_depthead, 0) = 1 OR position = 'Manager')
        ORDER BY full_name ASC
    ");
    $managerStmt->execute([
        ':dealer' => $dealer,
        ':department' => $department,
    ]);
    $managerRows = $managerStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($managerRows)) {
        $labels['For Department Manager Approval'] = implode(', ', array_map('formatDepartmentPicUserLabel', $managerRows));
    }

    $gmWhere = ["is_active = 1", "(position = 'General Manager' OR position LIKE '%Branch Manager%')"];
    $gmParams = [];
    if (strtoupper($dealer) !== 'OG') {
        $gmWhere[] = "(dealer = :dealer OR dealer = 'OG')";
        $gmParams[':dealer'] = $dealer;
    }
    $gmStmt = $pdo->prepare("
        SELECT full_name, department, position, dealer
        FROM users
        WHERE " . implode(' AND ', $gmWhere) . "
        ORDER BY CASE WHEN dealer = :dealer_order THEN 0 ELSE 1 END, full_name ASC
    ");
    $gmStmt->execute(array_merge($gmParams, [':dealer_order' => $dealer]));
    $gmRows = $gmStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($gmRows)) {
        $labels['For GM Approval'] = implode(', ', array_map('formatDepartmentPicUserLabel', $gmRows));
    }

    $accountingStaffStmt = $pdo->prepare("
        SELECT full_name, department, position, dealer
        FROM users
        WHERE is_active = 1
          AND dealer = :dealer
          AND department = 'ACCOUNTING'
          AND position NOT IN ('Manager', 'viewer')
        ORDER BY department ASC, full_name ASC
    ");
    $accountingStaffStmt->execute([':dealer' => $dealer]);
    $accountingStaffRows = $accountingStaffStmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($accountingStaffRows)) {
        $labels['Liquidated'] = implode(', ', array_map('formatDepartmentPicUserLabel', $accountingStaffRows));
    }

    $accountingManagerStmt = $pdo->prepare("
        SELECT full_name, department, position, dealer
        FROM users
        WHERE is_active = 1
          AND dealer = :dealer
          AND (
              (department = 'ACCOUNTING' AND position = 'Manager')
              OR department = 'ACCOUNTING'
          )
        ORDER BY
            CASE
                WHEN department = 'ACCOUNTING' AND position = 'Manager' THEN 0
                WHEN department = 'ACCOUNTING' THEN 2
                ELSE 3
            END,
            full_name ASC
        LIMIT 1
    ");
    $accountingManagerStmt->execute([':dealer' => $dealer]);
    $accountingManagerRow = $accountingManagerStmt->fetch(PDO::FETCH_ASSOC);
    if ($accountingManagerRow) {
        $labels['For Accounting Approval'] = formatDepartmentPicUserLabel($accountingManagerRow);
    }

    return $labels;
}
