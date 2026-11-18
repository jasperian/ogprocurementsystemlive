<?php
include '../../includes/db.php';
session_start(); // your DB connection

header('Content-Type: application/json');

$user_id = $_SESSION['user_id']; // assuming user is logged in

$currentPassword = $_POST['currentPassword'] ?? '';
$newPassword = $_POST['newPassword'] ?? '';
$confirmPassword = $_POST['confirmnewPassword'] ?? '';

if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'New password and confirmation do not match.']);
    exit;
}

// Fetch current password from DB
$stmt = $pdo->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || !password_verify($currentPassword, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
    exit;
}

// Update with new password hash
$newPasswordHash = password_hash($newPassword, PASSWORD_DEFAULT);
$update = $pdo->prepare("UPDATE users SET password = ? WHERE user_id = ?");
$update->execute([$newPasswordHash, $user_id]);

echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
