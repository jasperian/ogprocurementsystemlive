<?php
require '../../init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$batch_id = $_POST['batch_id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;

if (!$batch_id || !$supplier_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required parameters.']);
    exit;
}

try {
    $stmt = $pdo->prepare("UPDATE canvass_sheet_batches SET selected_supplier_id = ?, updated_at = NOW(), updated_by = ? WHERE batch_id = ?");
    $stmt->execute([$supplier_id, $_SESSION['full_name'] ?? 'System', $batch_id]);

    echo json_encode(['status' => 'success', 'message' => 'Supplier selected successfully.']);
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
