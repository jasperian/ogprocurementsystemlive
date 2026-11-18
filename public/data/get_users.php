<?php
include '../../includes/db.php';

$dealer = $_GET['dealer'] ?? '';

if (!empty($dealer)) {
    $stmt = $pdo->prepare("
        SELECT user_id, full_name 
        FROM users 
        WHERE dealer = :dealer AND is_active = 1
        AND position = 'Purchasing Staff'
        ORDER BY full_name ASC
    ");
    $stmt->execute([':dealer' => $dealer]);
} else {
    // If no dealer selected, return all users
    $stmt = $pdo->query("
        SELECT user_id, full_name 
        FROM users 
        WHERE is_active = 1 
        AND position = 'Purchasing Staff'
        ORDER BY full_name ASC
    ");
}

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json');
echo json_encode($users);
