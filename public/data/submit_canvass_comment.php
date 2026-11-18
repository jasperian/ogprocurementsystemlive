<?php
session_start();
require_once '../../includes/db.php';
require_once '../../includes/notification_functions.php'; // Notification helpers
require_once '../includes/pusher.php'; // Use the common pusher config

$pr_number = $_POST['pr_number'] ?? '';
$user_id = $_SESSION['user_id'] ?? ''; // ALWAYS use session user_id for security and reliability
$comments = trim($_POST['comments'] ?? '');
$parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;

if (empty($pr_number) || empty($user_id) || empty($comments)) {
    http_response_code(400);
    echo "Missing required fields or unauthorized.";
    exit;
}

try {
    $stmt = $pdo->prepare("INSERT INTO recommendations_suggestions (pr_number, user_id, comments, parent_id, created_date)
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$pr_number, $user_id, $comments, $parent_id]);
    $new_id = $pdo->lastInsertId();

    // Fetch user details for Pusher payload and notification message
    $stmt_user = $pdo->prepare("SELECT full_name, department, user_pfp FROM users WHERE user_id = ?");
    $stmt_user->execute([$user_id]);
    $user_info = $stmt_user->fetch(PDO::FETCH_ASSOC);
    $full_name = $user_info['full_name'] ?? 'Someone';

    // --- NOTIFICATIONS LOGIC ---
    $notified_user_ids = [];

    // 1. Reply Notification
    if ($parent_id) {
        $stmt_parent = $pdo->prepare("SELECT user_id FROM recommendations_suggestions WHERE id = ?");
        $stmt_parent->execute([$parent_id]);
        $parent_author_id = $stmt_parent->fetchColumn();

        if ($parent_author_id && $parent_author_id != $user_id) {
            create_notification(
                $pdo,
                $parent_author_id,
                "New Reply",
                "$full_name replied to your comment on PR #$pr_number",
                'info',
                "canvass_view.php?pr_number=$pr_number#comment-$new_id"
            );
            $notified_user_ids[] = $parent_author_id;
        }
    }

    // 2. Mention Notification (Full Name Match)
    // Fetch all active users to match against full names
    $stmt_all_users = $pdo->query("SELECT user_id, full_name FROM users WHERE is_active = 1");
    $all_users = $stmt_all_users->fetchAll(PDO::FETCH_ASSOC);

    foreach ($all_users as $target_user) {
        $mention_tag = '@' . $target_user['full_name'];
        // Case-insensitive search for @FullName in comments
        if (stripos($comments, $mention_tag) !== false) {
            $mentioned_user_id = $target_user['user_id'];
            if ($mentioned_user_id != $user_id && !in_array($mentioned_user_id, $notified_user_ids)) {
                create_notification(
                    $pdo,
                    $mentioned_user_id,
                    "You were mentioned",
                    "$full_name mentioned you in a comment on PR #$pr_number",
                    'info',
                    "canvass_view.php?pr_number=$pr_number#comment-$new_id"
                );
                $notified_user_ids[] = $mentioned_user_id;
            }
        }
    }
    // --- END NOTIFICATIONS LOGIC ---

    $pusher_data = [
        'id'            => $new_id,
        'pr_number'     => $pr_number,
        'user_id'       => $user_id,
        'full_name'     => $full_name,
        'department'    => $user_info['department'] ?? 'N/A',
        'user_pfp'      => $user_info['user_pfp'] ?? 'assets/images/user/avatar-1.jpg',
        'created_date'  => date('M d, Y h:i A'),
        'message'       => $comments,
        'parent_id'     => $parent_id
    ];

    // $pusher is already initialized in ../includes/pusher.php
    $pusher->trigger("comments-" . $pr_number, 'new-comment', $pusher_data);

    echo "success";
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database error: " . $e->getMessage();
}
