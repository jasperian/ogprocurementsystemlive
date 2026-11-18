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

function ensureItemEditLogTable(PDO $pdo): void
{
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS item_edit_logs (
            log_id INT AUTO_INCREMENT PRIMARY KEY,
            item_id INT NOT NULL,
            user_id INT NULL,
            edited_by VARCHAR(255) NULL,
            field_name VARCHAR(100) NOT NULL,
            old_value TEXT NULL,
            new_value TEXT NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_item_edit_logs_item_id (item_id),
            INDEX idx_item_edit_logs_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

function uploadItemImage(array $file): string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return '';
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        jsonResponse(['status' => 'danger', 'message' => 'Image upload failed.']);
    }

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $originalName = basename($file['name'] ?? '');
    $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowedExtensions, true)) {
        jsonResponse(['status' => 'danger', 'message' => 'Please upload a valid image file.']);
    }

    $mimeType = mime_content_type($file['tmp_name'] ?? '') ?: '';
    if (strpos($mimeType, 'image/') !== 0) {
        jsonResponse(['status' => 'danger', 'message' => 'Please upload a valid image file.']);
    }

    $uploadDir = __DIR__ . '/uploads/items/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        jsonResponse(['status' => 'danger', 'message' => 'Could not create image upload folder.']);
    }

    $safeName = preg_replace('/[^A-Za-z0-9.\-_]/', '_', $originalName);
    $fileName = time() . '_' . bin2hex(random_bytes(4)) . '_' . $safeName;
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        jsonResponse(['status' => 'danger', 'message' => 'Failed to upload image.']);
    }

    return 'uploads/items/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid request method.']);
}

$itemId = filter_var($_POST['item_id'] ?? null, FILTER_VALIDATE_INT);
$itemName = trim($_POST['item_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$unitPrice = trim(str_replace(',', '', $_POST['unit_price'] ?? ''));
$itemCategory = trim($_POST['item_category'] ?? '');
$subCategory = trim($_POST['sub_category'] ?? '');
$unitMeasure = trim($_POST['unit_measure'] ?? '');
$editedBy = trim($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'System');
$userId = (int)($_SESSION['user_id'] ?? 0);

if (!$itemId) {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid item ID.']);
}

if ($itemName === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Item name is required.']);
}

if (!preg_match('/^\d+(\.\d{1,2})?$/', $unitPrice)) {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid price format.']);
}

if ($subCategory === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Sub Category is required.']);
}

if ($itemCategory === '') {
    jsonResponse(['status' => 'danger', 'message' => 'Item Category is required.']);
}

if (!itemSubCategoryExists($pdo, $subCategory, $itemCategory)) {
    jsonResponse(['status' => 'danger', 'message' => 'Please select a valid Sub Category for the selected Item Category.']);
}

try {
    ensureItemEditLogTable($pdo);

    $stmt = $pdo->prepare("
        SELECT
            item_name,
            description,
            unit_price,
            item_category,
            sub_category,
            unit_measure,
            product_image
        FROM items
        WHERE item_id = ?
    ");
    $stmt->execute([$itemId]);
    $oldItem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$oldItem) {
        jsonResponse(['status' => 'danger', 'message' => 'Item not found.']);
    }

    $newItem = [
        'item_name' => $itemName,
        'description' => $description,
        'unit_price' => number_format((float)$unitPrice, 2, '.', ''),
        'item_category' => $itemCategory,
        'sub_category' => $subCategory,
        'unit_measure' => $unitMeasure,
    ];

    $newImagePath = uploadItemImage($_FILES['product_image'] ?? []);
    if ($newImagePath !== '') {
        $newItem['product_image'] = $newImagePath;
    }

    $setClauses = [
        'item_name = :item_name',
        'description = :description',
        'unit_price = :unit_price',
        'item_category = :item_category',
        'sub_category = :sub_category',
        'unit_measure = :unit_measure',
    ];

    if ($newImagePath !== '') {
        $setClauses[] = 'product_image = :product_image';
    }

    $stmt = $pdo->prepare("
        UPDATE items
        SET " . implode(",\n            ", $setClauses) . "
        WHERE item_id = :item_id
        LIMIT 1
    ");
    $updateParams = [
        ':item_name' => $newItem['item_name'],
        ':description' => $newItem['description'],
        ':unit_price' => $newItem['unit_price'],
        ':item_category' => $newItem['item_category'],
        ':sub_category' => $newItem['sub_category'],
        ':unit_measure' => $newItem['unit_measure'],
        ':item_id' => $itemId,
    ];

    if ($newImagePath !== '') {
        $updateParams[':product_image'] = $newImagePath;
    }

    $stmt->execute($updateParams);

    $fieldLabels = [
        'item_name' => 'Item Name',
        'description' => 'Description',
        'unit_price' => 'Price',
        'item_category' => 'Item Category',
        'sub_category' => 'Sub Category',
        'unit_measure' => 'Unit Measure',
        'product_image' => 'Product Image',
    ];

    $logStmt = $pdo->prepare("
        INSERT INTO item_edit_logs (item_id, user_id, edited_by, field_name, old_value, new_value)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    foreach ($newItem as $field => $newValue) {
        $oldValue = (string)($oldItem[$field] ?? '');
        $newValue = (string)$newValue;

        if ($field === 'unit_price') {
            $oldValue = number_format((float)$oldValue, 2, '.', '');
        }

        if ($oldValue !== $newValue) {
            $logStmt->execute([
                $itemId,
                $userId ?: null,
                $editedBy,
                $fieldLabels[$field] ?? $field,
                $oldValue,
                $newValue,
            ]);
        }
    }

    jsonResponse(['status' => 'success', 'message' => 'Item updated successfully.']);
} catch (Throwable $e) {
    jsonResponse(['status' => 'danger', 'message' => 'Database error: ' . $e->getMessage()]);
}
