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

$categoryName = trim($_POST['category_name'] ?? '');
if ($categoryName === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Item Category is required.']);
}

try {
    ensureItemCategoryTables($pdo);

    if (itemCategoryExists($pdo, $categoryName)) {
        jsonResponse(['status' => 'danger', 'message' => 'This Item Category already exists.']);
    }

    $stmt = $pdo->prepare("INSERT INTO item_categories (category_name) VALUES (?)");
    $stmt->execute([$categoryName]);

    jsonResponse(['status' => 'success', 'message' => 'Item Category added successfully.']);
} catch (Throwable $e) {
    jsonResponse(['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()]);
}
