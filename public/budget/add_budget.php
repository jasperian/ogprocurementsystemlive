<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once "../../includes/db.php";

$response = ["status" => "error", "message" => "Invalid request"];

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $budget_id = $_POST["budget_id"] ?? "";
    $amount    = $_POST["amount"] ?? "";
    $remarks   = $_POST["remarks"] ?? "";
    $user_id   = $_SESSION['user_id'] ?? null;

    if (!$budget_id || !$amount || !$remarks) {
        echo json_encode([
            "status" => "error",
            "message" => "All fields are required."
        ]);
        exit;
    }

    /* ================= FILE UPLOAD ================= */
    $filePath = null;

    if (!empty($_FILES['file']['name'])) {

        $uploadDir = "../../uploads/budget_adjustments/";
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time() . "_" . basename($_FILES['file']['name']);
        $targetFile = $uploadDir . $fileName;

        $allowedTypes = ['pdf', 'jpg', 'jpeg', 'png'];
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($fileExt, $allowedTypes)) {
            echo json_encode([
                "status" => "error",
                "message" => "Invalid file type."
            ]);
            exit;
        }

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $targetFile)) {
            echo json_encode([
                "status" => "error",
                "message" => "File upload failed."
            ]);
            exit;
        }

        // Save relative path to DB
        $filePath = "uploads/budget_adjustments/" . $fileName;
    }
    /* ================================================= */

    try {
        $pdo->beginTransaction();

        // Update main budget
        $update = "
            UPDATE budgets
            SET approved_budget = approved_budget + :amount
            WHERE budget_id = :id
        ";
        $stmt = $pdo->prepare($update);
        $stmt->execute([
            ":amount" => $amount,
            ":id" => $budget_id
        ]);

        // Insert adjustment log
        $log = "
            INSERT INTO budget_adjustments
            (budget_id, amount, remarks, file_path, created_by, created_at)
            VALUES (:id, :amount, :remarks, :file_path, :created_by, NOW())
        ";

        $stmt = $pdo->prepare($log);
        $stmt->execute([
            ":id" => $budget_id,
            ":amount" => $amount,
            ":remarks" => $remarks,
            ":file_path" => $filePath,
            ":created_by" => $user_id
        ]);

        $pdo->commit();

        echo json_encode(["status" => "success"]);

    } catch (PDOException $e) {
        $pdo->rollBack();
        echo json_encode([
            "status" => "error",
            "message" => "Database error: " . $e->getMessage()
        ]);
    }
}
