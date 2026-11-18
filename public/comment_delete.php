<?php
session_start();
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = $_POST['comment_id'];
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("DELETE FROM recommendations_suggestions WHERE id = :id AND user_id = :user_id");
    $stmt->execute([
        ':id' => $comment_id,
        ':user_id' => $user_id
    ]);

    echo $stmt->rowCount() > 0 ? "success" : "error";
}