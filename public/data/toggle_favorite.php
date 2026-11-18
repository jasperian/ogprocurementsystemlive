<?php
require '../../init.php';

// Make sure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];
$pr_number = $_POST['pr_number'] ?? null;

if (!$pr_number) {
    echo json_encode(['success' => false, 'message' => 'Missing PR number']);
    exit;
}

try {
    // Check if favorite already exists
    $checkSql = "SELECT id FROM pr_favorites WHERE user_id = :user_id AND pr_number = :pr_number";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([':user_id' => $user_id, ':pr_number' => $pr_number]);
    $exists = $checkStmt->fetch();

    if ($exists) {
        // Remove favorite
        $deleteSql = "DELETE FROM pr_favorites WHERE user_id = :user_id AND pr_number = :pr_number";
        $deleteStmt = $pdo->prepare($deleteSql);
        $deleteStmt->execute([':user_id' => $user_id, ':pr_number' => $pr_number]);
        $is_favorite = false;
    } else {
        // Add favorite
        $insertSql = "INSERT INTO pr_favorites (user_id, pr_number, created_at) VALUES (:user_id, :pr_number, NOW())";
        $insertStmt = $pdo->prepare($insertSql);
        $insertStmt->execute([':user_id' => $user_id, ':pr_number' => $pr_number]);
        $is_favorite = true;
    }

    echo json_encode(['success' => true, 'is_favorite' => $is_favorite]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
