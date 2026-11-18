<?php
require '../../init.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$department = trim($_SESSION['department'] ?? '');
$markAll = ($_POST['all'] ?? '') === '1';
$updateId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$markAll && !$updateId) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid system update.']);
    exit;
}

try {
    $sql = "SELECT su.update_id
        FROM system_updates su
        WHERE su.is_active = 1
          AND su.published_at <= NOW()
          AND (su.expires_at IS NULL OR su.expires_at > NOW())
          AND (
              NOT EXISTS (SELECT 1 FROM system_update_departments all_sud WHERE all_sud.update_id = su.update_id)
              OR EXISTS (
                  SELECT 1 FROM system_update_departments own_sud
                  WHERE own_sud.update_id = su.update_id
                    AND UPPER(TRIM(own_sud.department)) = UPPER(TRIM(:department))
              )
          )";
    $params = [':department' => $department];
    if (!$markAll) {
        $sql .= ' AND su.update_id = :update_id';
        $params[':update_id'] = $updateId;
    }

    $visibleStmt = $pdo->prepare($sql);
    $visibleStmt->execute($params);
    $ids = $visibleStmt->fetchAll(PDO::FETCH_COLUMN);
    $readStmt = $pdo->prepare('INSERT INTO system_update_reads (update_id, user_id, read_at) VALUES (?, ?, NOW()) ON DUPLICATE KEY UPDATE read_at = read_at');
    foreach ($ids as $id) {
        $readStmt->execute([(int)$id, $userId]);
    }

    echo json_encode(['success' => true, 'marked' => count($ids)]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update read status.']);
}
