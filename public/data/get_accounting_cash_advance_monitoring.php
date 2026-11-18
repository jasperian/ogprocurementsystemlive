<?php
session_start();
header('Content-Type: application/json');

include __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/liquidation_helpers.php';

liquidation_require_login();

if (!liquidation_is_accounting_user()) {
    http_response_code(403);
    echo json_encode([
        'status' => 'danger',
        'message' => 'Only Accounting/Finance can view cash advance monitoring.',
        'data' => [],
    ]);
    exit;
}

$dealer = trim($_SESSION['dealer'] ?? '');

try {
    $where = ["ca.request_type = 'Cash Advance'"];
    $params = [];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $where[] = 'ca.dealer = :dealer';
        $params[':dealer'] = $dealer;
    }

    $stmt = $pdo->prepare("
        SELECT
            ca.liquidation_id,
            ca.liquidation_number,
            ca.dealer,
            ca.department,
            ca.budget_code,
            ca.budget_activity,
            ca.liquidation_amount,
            ca.liquidation_date,
            ca.activity_date,
            ca.remarks,
            ca.attachment_path,
            ca.status,
            ca.submitted_at,
            ca.completed_at,
            creator.full_name AS created_by_name,
            linked.liquidation_id AS linked_liquidation_id,
            linked.liquidation_number AS linked_liquidation_number,
            linked.status AS linked_liquidation_status,
            linked.liquidation_amount AS linked_liquidation_amount,
            linked.actual_amount AS linked_actual_amount,
            linked.liquidation_date AS linked_liquidation_date,
            linked.remarks AS linked_liquidation_remarks,
            linked.attachment_path AS linked_attachment_path,
            linked.sap_document_number AS linked_sap_document_number,
            linked.completed_at AS linked_completed_at,
            DATEDIFF(CURDATE(), DATE(COALESCE(ca.completed_at, ca.submitted_at, ca.created_at))) AS aging_days,
            CASE
                WHEN linked.liquidation_id IS NOT NULL AND linked.status = 'Completed' THEN 'Liquidated'
                WHEN linked.liquidation_id IS NOT NULL AND linked.status = 'Rejected' THEN 'Liquidation Rejected'
                WHEN linked.liquidation_id IS NOT NULL THEN 'Liquidation In Progress'
                WHEN ca.status = 'Completed' THEN 'For Liquidation'
                WHEN ca.status = 'Rejected' THEN 'Rejected'
                ELSE 'Awaiting Release'
            END AS monitoring_status,
            CASE
                WHEN linked.liquidation_id IS NOT NULL AND linked.status = 'Completed' THEN 0
                ELSE ca.liquidation_amount
            END AS outstanding_amount,
            CASE
                WHEN linked.liquidation_id IS NULL THEN NULL
                ELSE ca.liquidation_amount - COALESCE(linked.actual_amount, linked.liquidation_amount, 0)
            END AS liquidation_balance
        FROM liquidations ca
        LEFT JOIN users creator ON creator.user_id = ca.created_by
        LEFT JOIN liquidations linked
            ON linked.liquidation_id = (
                SELECT child.liquidation_id
                FROM liquidations child
                WHERE child.source_cash_advance_id = ca.liquidation_id
                ORDER BY child.liquidation_id DESC
                LIMIT 1
            )
        WHERE " . implode(' AND ', $where) . "
        ORDER BY ca.submitted_at DESC, ca.liquidation_id DESC
        LIMIT 2000
    ");
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to load cash advance monitoring.',
        'data' => [],
    ]);
}
