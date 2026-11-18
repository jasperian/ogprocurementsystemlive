<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
session_start();
header('Content-Type: application/json');

include '../../includes/db.php';
require_once '../../includes/item_category_helpers.php';
$fullname = $_SESSION['full_name'] ?? '';
// FOR ADD_ITEM.PHP

function jsonResponse(array $response): void {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($response);
    exit;
}


$response = ['status' => 'danger', 'message' => 'Something went wrong.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $supplier_id = $_POST['supplier_name'] ?? '';
    $product_name = $_POST['product_name'] ?? '';
    $product_description = $_POST['product_description'] ?? '';
    $price = $_POST['price'] ?? '';
    $unit_measures = $_POST['unit_measures'] ?? '';
    $item_category = trim($_POST['item_category'] ?? '');
    $sub_category = trim($_POST['sub_category'] ?? '');

    $uploadDir = 'uploads/items/';
    $imagePath = '';

    if ($sub_category === '') {
        jsonResponse(['status' => 'danger', 'message' => 'Sub Category is required.']);
    }

    if ($item_category === '') {
        jsonResponse(['status' => 'danger', 'message' => 'Item Category is required.']);
    }

    if (!itemSubCategoryExists($pdo, $sub_category, $item_category)) {
        jsonResponse(['status' => 'danger', 'message' => 'Please select a valid Sub Category for the selected Item Category.']);
    }

    // Ensure upload directory exists
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    // Handle image upload
    if (isset($_FILES['product_image']) && $_FILES['product_image']['error'] === UPLOAD_ERR_OK) {
        $imageName = basename($_FILES['product_image']['name']);
        $imageName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $imageName); // sanitize filename
        $targetPath = $uploadDir . time() . '_' . $imageName;

        if (move_uploaded_file($_FILES['product_image']['tmp_name'], $targetPath)) {
            $imagePath = $targetPath;
        } else {
            jsonResponse(['status' => 'danger', 'message' => 'Failed to upload image.']);
        }
    }

    try {
        // Insert into DB
        $stmt = $pdo->prepare("
            INSERT INTO items (
                supplier_id,
                item_name,
                description,
                unit_price,
                product_image,
                item_category,
                sub_category,
                unit_measure,
                created_at,
                created_by
            )
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
        ");
        $stmt->execute([
            $supplier_id,
            $product_name,
            $product_description,
            $price,
            $imagePath,
            $item_category,
            $sub_category,
            $unit_measures,
            $fullname
        ]);

        $response = ['status' => 'success', 'message' => 'Product added successfully!'];
    } catch (Exception $e) {
        $response['message'] = 'Database error: ' . $e->getMessage();
    }
}

jsonResponse($response);
