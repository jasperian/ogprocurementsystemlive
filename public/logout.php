<?php
session_start();
require '../includes/db.php';

if (!empty($_COOKIE['rememberme'])) {
    list($selector,) = explode(':', $_COOKIE['rememberme']);
    $stmt = $pdo->prepare("DELETE FROM auth_tokens WHERE selector = ?");
    $stmt->execute([$selector]);
    setcookie('rememberme', '', time() - 3600, '/', '', true, true);
}

$_SESSION = [];
session_destroy();
header("Location: index");
exit;
?>
