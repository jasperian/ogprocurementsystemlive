<?php
include '../../includes/db.php';

header('Content-Type: application/json');

if (!isset($_GET['budget_code'])) {
    echo json_encode([]);
    exit;
}

$budget_code = $_GET['budget_code'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            e.expense_id,
            e.budget_id,
            e.expense_amount,
            e.expense_date,
            e.budget_activity,
            e.remarks,
            e.pr_number,
            e.po_number,
            b.budget_code AS budget_code
        FROM expenses e
        INNER JOIN budgets b ON e.budget_id = b.budget_id
        WHERE b.budget_code = ?
        ORDER BY e.expense_date DESC
    ");
    $stmt->execute([$budget_code]);
    $expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($expenses);
} catch (Exception $e) {
    echo json_encode([]);
}

