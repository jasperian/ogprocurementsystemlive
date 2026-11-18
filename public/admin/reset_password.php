<?php
include '../../includes/db.php';

if (!isset($_POST['key'])) {
    http_response_code(400);
    echo 'Invalid request';
    exit;
}

$user_id = base64_decode($_POST['key']); // Decode Base64 ID
$new_password = password_hash('ogps2025', PASSWORD_DEFAULT); // Hash the password
$now = date('Y-m-d H:i:s'); // Current timestamp

$stmt = $pdo->prepare("
    UPDATE users 
    SET password = :password, last_password_change = :last_change
    WHERE user_id = :user_id
");
$stmt->execute([
    ':password' => $new_password,
    ':last_change' => $now,
    ':user_id' => $user_id
]);

if ($stmt->rowCount() > 0) {
    echo 'success';
} else {
    http_response_code(500);
    echo 'Failed to reset password';
}
