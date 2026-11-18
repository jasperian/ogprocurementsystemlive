<?php
header('Content-Type: application/json');
include '../../includes/db.php';

function jsonResponse(array $response): void
{
    echo json_encode($response);
    exit;
}

function ensureItemEditLogTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_edit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            user_id INT NULL,
            edited_by VARCHAR(255) NULL,
            field_name VARCHAR(100) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item_edit_logs_item_id (item_id),
            INDEX idx_item_edit_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

$itemId = filter_var($_GET['item_id'] ?? null, FILTER_VALIDATE_INT);
if (!$itemId) {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid item ID.', 'logs' => []]);
}

try {
    ensureItemEditLogTable($pdo);

    $stmt = $pdo->prepare("
        SELECT
            log_id,
            item_id,
            user_id,
            edited_by,
            field_name,
            old_value,
            new_value,
            created_at
        FROM item_edit_logs
        WHERE item_id = ?
        ORDER BY created_at DESC, log_id DESC
        LIMIT 50
    ");
    $stmt->execute([$itemId]);

    jsonResponse([
        'status' => 'success',
        'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
    ]);
} catch (Throwable $e) {
    jsonResponse(['status' => 'danger', 'message' => $e->getMessage(), 'logs' => []]);
}
