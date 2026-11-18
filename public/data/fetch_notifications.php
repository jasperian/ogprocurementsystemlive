<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

$user_id = (int)($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized', 'notifications' => [], 'unread_count' => 0]);
    exit;
}

$sql = "
    SELECT DISTINCT
        n.id,
        n.title,
        n.message,
        n.type,
        n.link,
        COALESCE(nr.is_read, n.is_read, 0) AS is_read,
        n.created_at
    FROM notifications n
    LEFT JOIN notification_recipients nr
        ON nr.notification_id = n.id
        AND nr.user_id = :user_id
    WHERE nr.user_id = :user_id
        OR n.user_id = :user_id
    ORDER BY n.created_at DESC
    LIMIT 20
";

$countSql = "
    SELECT COUNT(*)
    FROM notifications n
    LEFT JOIN notification_recipients nr
        ON nr.notification_id = n.id
        AND nr.user_id = :user_id
    WHERE (nr.user_id = :user_id OR n.user_id = :user_id)
        AND COALESCE(nr.is_read, n.is_read, 0) = 0
";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':user_id' => $user_id]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute([':user_id' => $user_id]);
    $unreadCount = (int)$countStmt->fetchColumn();

    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'unread_count' => $unreadCount,
    ]);
} catch (PDOException $e) {
    error_log('Fetch notifications error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to fetch notifications', 'notifications' => [], 'unread_count' => 0]);
}
