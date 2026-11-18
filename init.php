<?php
// require 'config/config.php';
include 'includes/db.php';
session_start();

if (!isset($_SESSION['user_id']) && !empty($_COOKIE['rememberme'])) {
    list($selector, $validator) = explode(':', $_COOKIE['rememberme']);

    $stmt = $pdo->prepare("SELECT * FROM auth_tokens WHERE selector = ? AND expires >= NOW()");
    $stmt->execute([$selector]);
    $token = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($token && hash_equals($token['hashed_validator'], hash('sha256', $validator))) {
        $userStmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
        $userStmt->execute([$token['user_id']]);
        $user = $userStmt->fetch();

        if ($user) {
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['department'] = $user['department'];
            $_SESSION['position'] = $user['position'];
            $_SESSION['dealer'] = $user['dealer'];
        }
    } else {
        setcookie('rememberme', '', time() - 3600, '/', '', true, true);
    }
}

if (!isset($_SESSION['user_id'])) {
    header("Location: index");
    exit;
}
