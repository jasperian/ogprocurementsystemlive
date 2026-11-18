<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->query("
    SELECT DISTINCT po.po_dealer
    FROM purchase_order po
    INNER JOIN purchase_requisition pr
        ON pr.pr_number = po.pr_number
    WHERE pr.pr_status = 'Completed'
      AND po.po_dealer IS NOT NULL
      AND po.po_dealer <> ''
    ORDER BY po.po_dealer
");

echo json_encode($stmt->fetchAll(PDO::FETCH_COLUMN));
