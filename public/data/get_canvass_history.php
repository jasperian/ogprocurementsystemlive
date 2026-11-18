<?php
require '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$batch_id = $_GET['batch_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;

if (!$batch_id || !$supplier_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing parameters']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT h.*, u.full_name 
        FROM canvass_change_history h
        JOIN users u ON h.user_id = u.user_id
        WHERE h.batch_id = ? AND h.supplier_id = ?
        ORDER BY h.created_at DESC
    ");
    $stmt->execute([$batch_id, $supplier_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'history' => $history
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
