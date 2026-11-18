<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once "../../includes/db.php";

$response = ["status" => "error", "data" => []];

$budget_id = $_GET['budget_id'] ?? "";

if ($budget_id) {
    try {
        $stmt = $pdo->prepare("
            SELECT ba.amount, ba.remarks, ba.created_at, ba.file_path, u.full_name AS created_by
            FROM budget_adjustments ba
            LEFT JOIN users u ON ba.created_by = u.user_id
            WHERE ba.budget_id = :id
            ORDER BY ba.created_at DESC
        ");
        $stmt->execute([':id' => $budget_id]);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response = ["status" => "success", "data" => $data];

    } catch (PDOException $e) {
        $response["message"] = "Database error: " . $e->getMessage();
    }
} else {
    $response["message"] = "Invalid budget ID";
}

echo json_encode($response);
