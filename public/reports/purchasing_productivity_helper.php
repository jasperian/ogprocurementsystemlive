<?php

function productivityDate(string $value): string
{
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : '';
}

function productivityFilters(array $input): array
{
    $startDate = productivityDate(trim((string)($input['start_date'] ?? '')));
    $endDate = productivityDate(trim((string)($input['end_date'] ?? '')));
    if ($startDate !== '' && $endDate !== '' && $startDate > $endDate) {
        [$startDate, $endDate] = [$endDate, $startDate];
    }

    return [
        'group_by' => ($input['group_by'] ?? 'dealer') === 'tm' ? 'tm' : 'dealer',
        'start_date' => $startDate,
        'end_date' => $endDate,
        'dealer' => trim((string)($input['dealer'] ?? '')),
        'tm' => ctype_digit((string)($input['tm'] ?? '')) ? (int)$input['tm'] : 0,
    ];
}

function fetchProductivityRows(PDO $pdo, array $filters): array
{
    // Some deployed OGPS databases predate purchase_order.created_by. Keep the
    // report usable there and enable handoff metrics only when the column exists.
    try {
        $hasPoCreatedBy = (bool)$pdo->query("SHOW COLUMNS FROM purchase_order LIKE 'created_by'")->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $hasPoCreatedBy = false;
    }

    $where = ["UPPER(TRIM(COALESCE(u.department, ''))) = 'PURCHASING'"];
    $params = [];

    if ($filters['start_date'] !== '') {
        $where[] = 'DATE(pr.pr_date) >= :start_date';
        $params[':start_date'] = $filters['start_date'];
    }
    if ($filters['end_date'] !== '') {
        $where[] = 'DATE(pr.pr_date) <= :end_date';
        $params[':end_date'] = $filters['end_date'];
    }
    if ($filters['dealer'] !== '') {
        $where[] = 'UPPER(TRIM(pr.pr_dealer)) = UPPER(TRIM(:dealer))';
        $params[':dealer'] = $filters['dealer'];
    }
    if ($filters['tm'] > 0) {
        $where[] = 'u.user_id = :tm';
        $params[':tm'] = $filters['tm'];
    }

    if ($filters['group_by'] === 'tm') {
        $labelSql = "COALESCE(NULLIF(TRIM(u.full_name), ''), u.username, 'Unassigned')";
        $secondarySql = "COALESCE(NULLIF(TRIM(pr.pr_dealer), ''), NULLIF(TRIM(u.dealer), ''), 'Unknown')";
        $groupSql = 'u.user_id, ' . $labelSql . ', ' . $secondarySql;
    } else {
        $labelSql = "COALESCE(NULLIF(TRIM(pr.pr_dealer), ''), NULLIF(TRIM(u.dealer), ''), 'Unknown')";
        $secondarySql = "'All Purchasing TMs'";
        $groupSql = $labelSql;
    }

    $crossCompletedSql = $hasPoCreatedBy
        ? "COUNT(DISTINCT CASE
                WHEN po.po_number IS NOT NULL AND TRIM(po.po_number) <> ''
                 AND po.created_by REGEXP '^[0-9]+$'
                 AND CAST(po.created_by AS UNSIGNED) <> u.user_id
                THEN pr.pr_number END)"
        : '0';

    $sql = "
        SELECT
            {$labelSql} AS group_label,
            {$secondarySql} AS secondary_label,
            COUNT(DISTINCT pr.pr_number) AS total_prs,
            COUNT(DISTINCT CASE WHEN po.po_number IS NOT NULL AND TRIM(po.po_number) <> '' THEN pr.pr_number END) AS completed_prs,
            COUNT(DISTINCT po.po_number) AS total_pos,
            COUNT(DISTINCT po.supplier_id) AS suppliers,
            {$crossCompletedSql} AS cross_completed,
            COALESCE(SUM(CASE WHEN po.amount_purchased REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN CAST(po.amount_purchased AS DECIMAL(18,2)) ELSE 0 END), 0) AS total_amount,
            ROUND(AVG(CASE
                WHEN po.po_date IS NOT NULL AND po.po_date <> '' AND pr.pr_date IS NOT NULL AND pr.pr_date <> ''
                THEN GREATEST(DATEDIFF(DATE(po.po_date), DATE(pr.pr_date)), 0)
            END), 1) AS avg_lead_days,
            ROUND(100 * COUNT(DISTINCT CASE WHEN po.po_number IS NOT NULL AND TRIM(po.po_number) <> '' THEN pr.pr_number END)
                / NULLIF(COUNT(DISTINCT pr.pr_number), 0), 1) AS completion_rate
        FROM purchase_requisition pr
        INNER JOIN canvass_approvals ca ON ca.batch_id = pr.batch_id
        INNER JOIN users u ON u.user_id = ca.approver_1
        LEFT JOIN purchase_order po ON po.pr_number = pr.pr_number
        WHERE " . implode(' AND ', $where) . "
        GROUP BY {$groupSql}
        ORDER BY total_prs DESC, group_label ASC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

