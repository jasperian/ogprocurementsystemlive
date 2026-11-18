<?php

function getCashAdvanceApprovalStatuses(): array
{
    return [
        'CA Request' => 'CA Request Approval',
        'CA Release' => 'CA Release / For PO',
        'For PO' => 'For PO Processing',
        'For Liquidation' => 'Liquidation Approval',
        'Liquidated' => 'SAP Document Encoding',
        'For GM Approval' => 'GM Approval',
        'For SAP Document' => 'SAP Document Encoding',
        'For Accounting Approval' => 'Accounting Manager Approval',
    ];
}

function getCashAdvanceAssignedApprovers(PDO $pdo, string $dealer = ''): array
{
    $dealer = trim($dealer) !== '' ? trim($dealer) : 'OG';
    try {
        $tableStmt = $pdo->query("SHOW TABLES LIKE 'cash_advance_approval_assignments'");
        if (!$tableStmt || !$tableStmt->fetch(PDO::FETCH_NUM)) {
            return [];
        }
    } catch (Throwable $e) {
        return [];
    }

    $stmt = $pdo->prepare("
        SELECT
            a.status_name,
            a.dealer,
            a.approver_user_id,
            u.full_name
        FROM cash_advance_approval_assignments a
        LEFT JOIN users u ON a.approver_user_id = u.user_id
        WHERE a.dealer IN (:dealer, 'OG')
        ORDER BY CASE WHEN a.dealer = :dealer_order THEN 0 ELSE 1 END
    ");
    $stmt->execute([
        ':dealer' => $dealer,
        ':dealer_order' => $dealer,
    ]);

    $assignments = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $status = $row['status_name'] ?? '';
        if ($status === '' || isset($assignments[$status])) {
            continue;
        }

        $assignments[$status] = [
            'user_id' => $row['approver_user_id'] ?? null,
            'full_name' => $row['full_name'] ?? null,
            'dealer' => $row['dealer'] ?? null,
        ];
    }

    return $assignments;
}

function getCashAdvanceAssignmentStatusKey(array $row): string
{
    $status = $row['pr_status'] ?? '';
    $amount = (float)($row['ca_amount'] ?? 0);
    $gmApprovedAt = $row['gm_approved_at'] ?? null;

    if ($status === 'Liquidated' && $amount > 10000 && empty($gmApprovedAt)) {
        return 'For GM Approval';
    }

    return $status;
}
