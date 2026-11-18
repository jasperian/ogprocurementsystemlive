<?php
session_start();
header('Content-Type: application/json');
include '../../includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized', 'data' => []]);
    exit;
}

$department = trim($_GET['department'] ?? '');
$dealer = $_SESSION['dealer'] ?? '';

if ($department === '') {
    echo json_encode(['status' => 'danger', 'message' => 'Department is required.', 'data' => []]);
    exit;
}

try {
    if (!empty($dealer) && $dealer !== 'OG') {
        $stmt = $pdo->prepare("
            SELECT budget_code, budget_activity
            FROM budgets
            WHERE budget_department = :department
              AND budget_dealer = :dealer
            ORDER BY budget_code ASC
        ");
        $stmt->execute([
            ':department' => $department,
            ':dealer' => $dealer
        ]);
    } else {
        $stmt = $pdo->prepare("
            SELECT budget_code, budget_activity
            FROM budgets
            WHERE budget_department = :department
            ORDER BY budget_code ASC
        ");
        $stmt->execute([
            ':department' => $department
        ]);
    }

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'data' => $rows
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Failed to load budget codes.',
        'data' => []
    ]);
}

