<?php
require_once '../../includes/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$dealer  = $_SESSION['dealer'] ?? null;
$user_id = $_SESSION['user_id'];
$query   = $_GET['q'] ?? '';

try {
    $stmt = $pdo->prepare("
        SELECT username, full_name, user_pfp 
        FROM users 
        WHERE (username LIKE ? OR full_name LIKE ?) 
        AND is_active = 1 
        AND (dealer = ? OR dealer = 'OG')
        AND user_id != ?
        LIMIT 10
    ");

    $searchTerm = "%$query%";
    $stmt->execute([$searchTerm, $searchTerm, $dealer, $user_id]);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($users);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
?>