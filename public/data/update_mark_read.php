<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? 0;
$notif_id = $_POST['id'] ?? 0;

if ($notif_id > 0 && $user_id > 0) {
    $stmtCheck = $pdo->prepare("
        SELECT COUNT(*) 
        FROM notification_recipients 
        WHERE notification_id = :id AND user_id = :user_id
    ");
    $stmtCheck->execute([':id' => $notif_id, ':user_id' => $user_id]);
    $isRecipient = $stmtCheck->fetchColumn();

    if ($isRecipient > 0) {
        // Recipient-based notification
        $stmt = $pdo->prepare("
            UPDATE notification_recipients 
            SET is_read = 1 
            WHERE notification_id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
    } else {
        // Owner-based notification
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1 
            WHERE id = :id AND user_id = :user_id
        ");
        $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
    }

    echo json_encode(['success' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'message' => 'Invalid notification']);



// $user_id = $_SESSION['user_id'] ?? 0;
// $notif_id = $_POST['id'] ?? 0;

// if ($notif_id > 0 && $user_id > 0) {
//     // 1️⃣ Check if this notification exists in the recipients table
//     $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM notification_recipients 
//                                 WHERE notification_id = :id AND user_id = :user_id");
//     $stmtCheck->execute([':id' => $notif_id, ':user_id' => $user_id]);
//     $isRecipient = $stmtCheck->fetchColumn();

//     if ($isRecipient > 0) {
//         // ✅ Case 1: Notification is from notification_recipients
//         $stmt = $pdo->prepare("UPDATE notification_recipients 
//                                SET is_read = 1 
//                                WHERE notification_id = :id AND user_id = :user_id");
//         $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);

//         // ✅ ALSO update notifications table
//         $stmt = $pdo->prepare("UPDATE notifications 
//                                SET is_read = 1 
//                                WHERE id = :id");
//         $stmt->execute([':id' => $notif_id]);
//     } else {
//         // ✅ Case 2: Notification belongs directly to user in notifications table
//         $stmt = $pdo->prepare("UPDATE notifications 
//                                SET is_read = 1 
//                                WHERE id = :id AND user_id = :user_id");
//         $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
//     }

//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
// }


// session_start();

// $user_id = $_SESSION['user_id'] ?? 0;
// $notif_id = $_POST['id'] ?? 0;

// if ($notif_id > 0 && $user_id > 0) {
//     $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id");
//     $stmt->execute([':id' => $notif_id, ':user_id' => $user_id]);
//     echo json_encode(['success' => true]);
// } else {
//     echo json_encode(['success' => false]);
// }
?>
