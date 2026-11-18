<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Pusher\Pusher;

$options = [
    'cluster' => 'ap1',
    'useTLS' => true
];

$pusher = new Pusher(
    '7f506782df003022bff5',
    (is_file(__DIR__ . '/../../config/pusher-local.php') ? require __DIR__ . '/../../config/pusher-local.php' : getenv('PUSHER_APP_SECRET')),
    '2032452',
    $options
);


?>