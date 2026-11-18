<?php

function ensureLiquidationAccountingAssignmentsTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS liquidation_accounting_assignments (
            dealer VARCHAR(50) NOT NULL PRIMARY KEY,
            sap_encoder_user_id INT NULL,
            accounting_approver_user_id INT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_liq_accounting_sap_encoder (sap_encoder_user_id),
            INDEX idx_liq_accounting_approver (accounting_approver_user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS liquidation_accounting_assignment_users (
            assignment_id INT AUTO_INCREMENT PRIMARY KEY,
            dealer VARCHAR(50) NOT NULL,
            role_name ENUM('SAP Encoder', 'Accounting Approver', 'CA Release', 'Accounting Completion') NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_liq_accounting_assignment (dealer, role_name, user_id),
            INDEX idx_liq_accounting_assignment_user (user_id),
            INDEX idx_liq_accounting_assignment_role (dealer, role_name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    $pdo->exec("
        INSERT IGNORE INTO liquidation_accounting_assignment_users (dealer, role_name, user_id)
        SELECT dealer, 'SAP Encoder', sap_encoder_user_id
        FROM liquidation_accounting_assignments
        WHERE sap_encoder_user_id IS NOT NULL
    ");
    $pdo->exec("
        INSERT IGNORE INTO liquidation_accounting_assignment_users (dealer, role_name, user_id)
        SELECT dealer, 'Accounting Approver', accounting_approver_user_id
        FROM liquidation_accounting_assignments
        WHERE accounting_approver_user_id IS NOT NULL
    ");
}

function getLiquidationAccountingAssignedUsers(PDO $pdo, string $dealer, string $roleName): array
{
    static $cache = [];

    $dealer = trim($dealer);
    if ($dealer === '' || !in_array($roleName, ['SAP Encoder', 'Accounting Approver', 'CA Release', 'Accounting Completion'], true)) {
        return [];
    }

    $cacheKey = $dealer . '|' . $roleName;
    if (array_key_exists($cacheKey, $cache)) {
        return $cache[$cacheKey];
    }

    try {
        $stmt = $pdo->prepare("
            SELECT
                a.user_id,
                u.full_name,
                u.department,
                u.position,
                COALESCE(u.is_active, 0) AS is_active
            FROM liquidation_accounting_assignment_users a
            LEFT JOIN users u ON u.user_id = a.user_id
            WHERE a.dealer = :dealer
              AND a.role_name = :role_name
              AND u.department = 'ACCOUNTING'
              AND u.is_active = 1
            ORDER BY u.full_name ASC, a.user_id ASC
        ");
        $stmt->execute([':dealer' => $dealer, ':role_name' => $roleName]);
        $assignments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $assignments = [];
    }

    if (!$assignments && in_array($roleName, ['SAP Encoder', 'Accounting Approver'], true)) {
        try {
            $legacyColumn = $roleName === 'SAP Encoder' ? 'sap_encoder_user_id' : 'accounting_approver_user_id';
            $legacyStmt = $pdo->prepare("
                SELECT
                    a.$legacyColumn AS user_id,
                    u.full_name,
                    u.department,
                    u.position,
                    COALESCE(u.is_active, 0) AS is_active
                FROM liquidation_accounting_assignments a
                LEFT JOIN users u ON u.user_id = a.$legacyColumn
                WHERE a.dealer = :dealer
                  AND a.$legacyColumn IS NOT NULL
                  AND u.department = 'ACCOUNTING'
                  AND u.is_active = 1
                LIMIT 1
            ");
            $legacyStmt->execute([':dealer' => $dealer]);
            $legacyAssignment = $legacyStmt->fetch(PDO::FETCH_ASSOC);
            $assignments = $legacyAssignment ? [$legacyAssignment] : [];
        } catch (Throwable $e) {
            $assignments = [];
        }
    }

    $cache[$cacheKey] = $assignments;
    return $assignments;
}

function liquidation_accounting_assignee_names(array $assignments): string
{
    $names = [];
    foreach ($assignments as $assignment) {
        $name = trim((string)($assignment['full_name'] ?? ''));
        if ($name !== '') {
            $names[] = $name;
        }
    }

    return implode(', ', $names);
}

function liquidation_can_encode_sap_for_dealer(PDO $pdo, string $dealer, int $userId): bool
{
    $assignments = getLiquidationAccountingAssignedUsers($pdo, $dealer, 'SAP Encoder');
    if ($assignments) {
        foreach ($assignments as $assignment) {
            if ((int)$assignment['user_id'] === $userId && (int)($assignment['is_active'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    return liquidation_is_accounting_staff();
}

function liquidation_can_complete_for_dealer(PDO $pdo, array $request, int $userId): bool
{
    $dealer = trim((string)($request['dealer'] ?? ''));
    $roleName = ($request['request_type'] ?? '') === 'Cash Advance' ? 'CA Release' : 'Accounting Completion';
    $assignments = getLiquidationAccountingAssignedUsers($pdo, $dealer, $roleName);
    if ($assignments) {
        foreach ($assignments as $assignment) {
            if ((int)$assignment['user_id'] === $userId && (int)($assignment['is_active'] ?? 0) === 1) {
                return true;
            }
        }
        return false;
    }

    if (liquidation_requires_accounting_manager($request)) {
        return liquidation_is_accounting_manager($dealer);
    }

    return liquidation_is_accounting_user();
}
