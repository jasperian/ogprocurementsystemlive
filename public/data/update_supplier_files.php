<?php
include '../../includes/db.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_id'])) {
    $vendorId = $_POST['vendor_id'];
    $uploadDir = "../uploads/vendors/$vendorId/";

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $allowed = ['application/pdf'];
    $updates = [];
    $placeholders = [];

    foreach ($_FILES as $field => $file) {
        if ($file['error'] === 0) {
            if (in_array(mime_content_type($file['tmp_name']), $allowed)) {
                $filename = $field . "_" . time() . ".pdf";
                $targetPath = $uploadDir . $filename;

                if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                    $updates[] = "$field = ?";
                    $placeholders[] = "uploads/vendors/$vendorId/$filename";
                }
            }
        }
    }

    if (!empty($updates)) {
        $sql = "UPDATE supplier_profile SET " . implode(", ", $updates) . " WHERE vendor_id = ?";
        $stmt = $pdo->prepare($sql);
        $placeholders[] = $vendorId;

        if ($stmt->execute($placeholders)) {
            $response = ['status' => 'success', 'message' => 'Files updated successfully'];
        } else {
            $response = ['status' => 'error', 'message' => 'Database update failed'];
        }
    } else {
        $response = ['status' => 'error', 'message' => 'No valid files uploaded'];
    }
}

echo json_encode($response);
