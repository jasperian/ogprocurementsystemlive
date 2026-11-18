<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit;
}

$stmt = $pdo->query("
    SELECT DISTINCT s.supplier_id, s.supplier_name
    FROM purchase_order po
    INNER JOIN purchase_requisition pr
        ON pr.pr_number = po.pr_number
    INNER JOIN suppliers s
        ON s.supplier_id = po.supplier_id
    WHERE pr.pr_status = 'Completed'
    ORDER BY s.supplier_name
");

echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
