<?php
include '../../includes/db.php';
session_start();

// Get user_id from query or session
$user_id = isset($_GET['user_id']) ? base64_decode($_GET['user_id']) : $_SESSION['user_id'] ?? null;

if (!$user_id) {
    http_response_code(400);
    echo json_encode(['error' => 'No user ID provided']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT user_id, username, full_name, email_address, department_id, role, 
           user_signature, position, user_pfp, department, dealer, is_depthead, 
           last_password_change
    FROM users
    WHERE user_id = :user_id
");
$stmt->execute([':user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

echo json_encode($user);
