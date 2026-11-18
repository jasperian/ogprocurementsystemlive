<?php
session_start();
require 'vendor/autoload.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Unauthorized');
}

$options = [
    'cluster' => 'ap1',
    'useTLS'  => true
];
$pusher = new Pusher\Pusher(
    '7f506782df003022bff5', // APP_KEY
    (is_file(__DIR__ . '/../config/pusher-local.php') ? require __DIR__ . '/../config/pusher-local.php' : getenv('PUSHER_APP_SECRET')), // APP_SECRET
    '2032452',             // APP_ID
    $options
);

// Authorize subscription for the user's private channel
echo $pusher->authorizeChannel($_POST['channel_name'], $_POST['socket_id']);
