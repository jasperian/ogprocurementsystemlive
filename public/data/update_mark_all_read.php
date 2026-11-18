<?php
include '../../includes/db.php';
session_start();

$user_id = (int)($_SESSION['user_id'] ?? 0);
$isAjax = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
    || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;

if ($user_id <= 0) {
    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }

    header("Location: login");
    exit;
}

try {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE notifications
        SET is_read = 1
        WHERE user_id = ?
    ")->execute([$user_id]);

    $pdo->prepare("
        UPDATE notification_recipients
        SET is_read = 1
        WHERE user_id = ?
    ")->execute([$user_id]);

    $pdo->commit();

    if ($isAjax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => true]);
        exit;
    }

    header("Location: dashboard");
    exit;
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Mark all notifications read error: ' . $e->getMessage());

    if ($isAjax) {
        header('Content-Type: application/json');
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to mark notifications as read']);
        exit;
    }

    header("Location: dashboard");
    exit;
}
