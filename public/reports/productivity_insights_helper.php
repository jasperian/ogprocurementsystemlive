<?php

function insightFilters(array $input): array
{
    $date = static fn($value) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$value) ? (string)$value : '';
    $from = $date($input['start_date'] ?? '');
    $to = $date($input['end_date'] ?? '');
    if ($from !== '' && $to !== '' && $from > $to) [$from, $to] = [$to, $from];
    return ['start_date' => $from, 'end_date' => $to, 'dealer' => trim((string)($input['dealer'] ?? ''))];
}

function fetchProductivityInsights(PDO $pdo, array $filters): array
{
    $where = ["UPPER(TRIM(COALESCE(u.department, ''))) = 'PURCHASING'"];
    $params = [];
    if ($filters['start_date'] !== '') { $where[] = 'DATE(pr.pr_date) >= :start_date'; $params[':start_date'] = $filters['start_date']; }
    if ($filters['end_date'] !== '') { $where[] = 'DATE(pr.pr_date) <= :end_date'; $params[':end_date'] = $filters['end_date']; }
    if ($filters['dealer'] !== '') { $where[] = 'UPPER(TRIM(pr.pr_dealer)) = UPPER(TRIM(:dealer))'; $params[':dealer'] = $filters['dealer']; }

    $sql = "SELECT pr.pr_number, pr.pr_date, pr.pr_status, pr.pr_dealer,
                   u.user_id, u.full_name,
                   COALESCE(NULLIF(TRIM(pr_creator.full_name), ''), NULLIF(TRIM(pr.created_by), ''), 'Unknown') AS pr_creator,
                   MIN(NULLIF(po.po_date, '')) AS first_po_date,
                   COUNT(DISTINCT NULLIF(po.po_number, '')) AS po_count,
                   GROUP_CONCAT(DISTINCT COALESCE(NULLIF(TRIM(po_creator.full_name), ''), NULLIF(TRIM(po.created_by), '')) ORDER BY COALESCE(po_creator.full_name, po.created_by) SEPARATOR ', ') AS completed_by,
                   MAX(CASE WHEN po.po_number IS NOT NULL AND po.po_number <> '' AND po_creator.user_id IS NOT NULL AND po_creator.user_id <> u.user_id THEN 1 ELSE 0 END) AS cross_completed,
                   COALESCE(SUM(CASE WHEN po.amount_purchased REGEXP '^-?[0-9]+(\\.[0-9]+)?$' THEN CAST(po.amount_purchased AS DECIMAL(18,2)) ELSE 0 END), 0) AS amount,
                   EXISTS(SELECT 1 FROM canvass_rejected cr WHERE cr.pr_number = pr.pr_number) AS was_rejected
            FROM purchase_requisition pr
            INNER JOIN canvass_approvals ca ON ca.batch_id = pr.batch_id
            INNER JOIN users u ON u.user_id = ca.approver_1
            LEFT JOIN users pr_creator ON
                pr.created_by REGEXP '^[0-9]+$' AND pr_creator.user_id = CAST(pr.created_by AS UNSIGNED)
            LEFT JOIN purchase_order po ON po.pr_number = pr.pr_number
            LEFT JOIN users po_creator ON
                po.created_by REGEXP '^[0-9]+$' AND po_creator.user_id = CAST(po.created_by AS UNSIGNED)
            WHERE " . implode(' AND ', $where) . "
            GROUP BY pr.pr_id, pr.pr_number, pr.pr_date, pr.pr_status, pr.pr_dealer, pr.created_by, pr_creator.full_name, u.user_id, u.full_name
            ORDER BY pr.pr_date DESC";
    $stmt = $pdo->prepare($sql); $stmt->execute($params); $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = new DateTimeImmutable('today');
    $teams = []; $aging = ['0_3' => 0, '4_7' => 0, '8_14' => 0, '15_plus' => 0];
    $summary = ['total_prs' => count($records), 'completed' => 0, 'open' => 0, 'overdue' => 0, 'rejected' => 0, 'total_pos' => 0, 'total_amount' => 0.0];
    foreach ($records as &$record) {
        $prDate = DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$record['pr_date'], 0, 10));
        $poDate = $record['first_po_date'] ? DateTimeImmutable::createFromFormat('!Y-m-d', substr((string)$record['first_po_date'], 0, 10)) : false;
        $completed = $poDate !== false;
        $days = $prDate ? max(0, (int)$prDate->diff($completed ? $poDate : $today)->format('%r%a')) : 0;
        $record['age_days'] = $days; $record['completed'] = $completed; $record['overdue'] = !$completed && $days > 14;
        $summary['completed'] += $completed ? 1 : 0; $summary['open'] += $completed ? 0 : 1;
        $summary['overdue'] += $record['overdue'] ? 1 : 0; $summary['rejected'] += (int)$record['was_rejected'];
        $summary['total_pos'] += (int)$record['po_count']; $summary['total_amount'] += (float)$record['amount'];
        if (!$completed) $aging[$days <= 3 ? '0_3' : ($days <= 7 ? '4_7' : ($days <= 14 ? '8_14' : '15_plus'))]++;

        $id = (int)$record['user_id'];
        if (!isset($teams[$id])) $teams[$id] = ['user_id'=>$id, 'tm'=>$record['full_name'], 'dealers'=>[], 'total_prs'=>0, 'completed'=>0, 'open'=>0, 'overdue'=>0, 'rejected'=>0, 'cross_completed'=>0, 'on_time'=>0, 'lead_total'=>0, 'total_pos'=>0, 'amount'=>0.0];
        $t =& $teams[$id]; $t['dealers'][$record['pr_dealer'] ?: 'Unknown'] = true; $t['total_prs']++;
        $t['completed'] += $completed ? 1 : 0; $t['open'] += $completed ? 0 : 1; $t['overdue'] += $record['overdue'] ? 1 : 0;
        $t['rejected'] += (int)$record['was_rejected']; $t['cross_completed'] += (int)$record['cross_completed']; $t['total_pos'] += (int)$record['po_count']; $t['amount'] += (float)$record['amount'];
        if ($completed) { $t['lead_total'] += $days; $t['on_time'] += $days <= 14 ? 1 : 0; }
        unset($t);
    }
    unset($record);

    $maxCompleted = max(1, ...array_map(static fn($t) => $t['completed'], $teams ?: [['completed'=>1]]));
    foreach ($teams as &$t) {
        $t['dealer'] = implode(', ', array_keys($t['dealers'])); unset($t['dealers']);
        $t['completion_rate'] = $t['total_prs'] ? round(100*$t['completed']/$t['total_prs'], 1) : 0;
        $t['on_time_rate'] = $t['completed'] ? round(100*$t['on_time']/$t['completed'], 1) : 0;
        $t['quality_rate'] = $t['total_prs'] ? round(100*($t['total_prs']-$t['rejected'])/$t['total_prs'], 1) : 100;
        $t['avg_lead_days'] = $t['completed'] ? round($t['lead_total']/$t['completed'], 1) : null;
        $throughput = 100*$t['completed']/$maxCompleted;
        $t['score'] = round(.35*$t['completion_rate'] + .30*$t['on_time_rate'] + .15*$t['quality_rate'] + .20*$throughput, 1);
    }
    unset($t); usort($teams, static fn($a,$b) => $b['score'] <=> $a['score']);
    $summary['completion_rate'] = $summary['total_prs'] ? round(100*$summary['completed']/$summary['total_prs'], 1) : 0;
    return ['summary'=>$summary, 'aging'=>$aging, 'teams'=>array_values($teams), 'overdue'=>array_values(array_filter($records, static fn($r)=>$r['overdue'])), 'activity'=>$records];
}

