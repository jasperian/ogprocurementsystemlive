<?php
session_start();
require '../../includes/db.php';
include '../includes/pusher.php';

$pr_number = $_POST['pr_number'];
$user_id = $_POST['user_id'];
$comments = trim($_POST['comments']);
$user_pfp = $_POST['user_pfp'] ?? '';

if (!empty($comments)) {
    $stmt = $pdo->prepare("INSERT INTO recommendations_suggestions (pr_number, user_id, comments, created_date)
                           VALUES (?, ?, ?, NOW())");
    $stmt->execute([$pr_number, $user_id, $comments]);
    echo "success";
} else {
    http_response_code(400);
    echo "Comment cannot be empty.";
}


$options = [
    'cluster' => 'ap1',
    'useTLS'  => true
];

$pusher = new Pusher\Pusher(
    '7f506782df003022bff5', // APP_KEY
    (is_file(__DIR__ . '/../../config/pusher-local.php') ? require __DIR__ . '/../../config/pusher-local.php' : getenv('PUSHER_APP_SECRET')), // APP_SECRET
    '2032452',             // APP_ID
    $options
);
// Get comment from POST
$pr_number  = $_POST['pr_number'] ?? '';

// Example user data (replace with session/db data)
$user = [
    'user_id'      => $_SESSION['user_id'] ?? '',
    'full_name'    => $_SESSION['full_name'] ?? '',
    'department'   => $_SESSION['department'] ?? '',
    'user_pfp'     => ($user_pfp) ?? '',
    'created_date' => date('M d, Y h:i A'),
    'message'      => htmlspecialchars($comments)
];
$data = $user;
// $data = [
//     'user' => 'Guest',
//     'message' => $comment
// ];

// $pusher->trigger('comments-channel', 'new-comment', $data);
$pusher->trigger("comments-" . $pr_number, 'new-comment', $user);