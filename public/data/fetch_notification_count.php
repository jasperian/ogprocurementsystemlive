<?php
include '../../includes/db.php';
session_start();

$user_id = $_SESSION['user_id'] ?? 0;

$sql = "SELECT id, title, message, type, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute([':user_id' => $user_id]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($notifications);
?>