<?php
header('Content-Type: application/json');
include '../../includes/db.php';

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $id = $_POST['id'] ?? null;
        $price = $_POST['price'] ?? null;

        // Validate input
        if (!$id || !$price || !preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid input'
            ]);
            exit;
        }

        // ✅ Format price safely (exact string, 2 decimals)
        if (function_exists('bcadd')) {
            $price = bcadd($price, '0', 2); // keeps 2 decimals, no float rounding
        } else {
            $price = number_format((float)$price, 2, '.', '');
        }

        // Update query
        $stmt = $pdo->prepare("UPDATE items SET unit_price = :price WHERE item_id = :id");
        $stmt->execute([
            ':price' => $price,
            ':id' => $id
        ]);

        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'Price updated successfully',
                'price'   => $price // already formatted
            ]);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'No record updated (invalid ID?)'
            ]);
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid request method'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

