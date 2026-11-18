<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
session_start();
header('Content-Type: application/json');

include '../../includes/db.php';
require_once '../../includes/item_category_helpers.php';

function jsonResponse(array $response): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($response);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid request method.']);
}

$itemCategory = trim($_POST['item_category'] ?? '');
$subCategory = trim($_POST['sub_category'] ?? '');
$createdBy = trim($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System');

if ($itemCategory === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Item Category is required.']);
}

if ($subCategory === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Sub Category is required.']);
}

try {
    ensureItemCategoryTables($pdo);

    if (!itemCategoryExists($pdo, $itemCategory)) {
        jsonResponse(['status' => 'danger', 'message' => 'Please select a valid Item Category.']);
    }

    if (itemSubCategoryExists($pdo, $subCategory, $itemCategory)) {
        jsonResponse(['status' => 'danger', 'message' => 'This Sub Category already exists for the selected Item Category.']);
    }

    $stmt = $pdo->prepare("
        INSERT INTO item_sub_categories (item_category, sub_category, created_by)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$itemCategory, $subCategory, $createdBy]);

    jsonResponse(['status' => 'success', 'message' => 'Sub Category added successfully.']);
} catch (Throwable $e) {
    jsonResponse(['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()]);
}
