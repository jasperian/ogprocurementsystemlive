<?php

/**
 * Inserts a notification into the database.
 *
 * @param PDO $pdo The PDO connection object.
 * @param int $user_id The recipient user ID.
 * @param string $title The notification title.
 * @param string $message The notification message.
 * @param string $type The notification type (info, success, warning, error).
 * @param string $link The optional link for the notification.
 * @return int|bool The notification ID on success, or false on failure.
 */
function create_notification($pdo, $user_id, $title, $message, $type = 'info', $link = null) {
    try {
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $title, $message, $type, $link]);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        error_log("Notification Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Notifies multiple users for a single event.
 *
 * @param PDO $pdo The PDO connection object.
 * @param array $user_ids Array of recipient user IDs.
 * @param string $title The notification title.
 * @param string $message The notification message.
 * @param string $type The notification type.
 * @param string $link The optional link.
 */
function notify_multiple($pdo, $user_ids, $title, $message, $type = 'info', $link = null) {
    // Standardise user_ids to array and remove duplicates
    $user_ids = array_unique((array)$user_ids);
    
    foreach ($user_ids as $uid) {
        if (!empty($uid)) {
            create_notification($pdo, $uid, $title, $message, $type, $link);
        }
    }
}
?>
