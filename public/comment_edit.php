<?php
session_start();
require '../includes/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $comment_id = $_POST['comment_id'];
    $comments = trim($_POST['comments']);
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("
        UPDATE recommendations_suggestions 
        SET comments = :comments, updated_at = NOW() 
        WHERE id = :id AND user_id = :user_id
    ");
    $stmt->execute([
        ':comments' => $comments,
        ':id' => $comment_id,
        ':user_id' => $user_id
    ]);

    echo $stmt->rowCount() > 0 ? "success" : "error";
}