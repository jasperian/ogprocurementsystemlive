<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';

$dealer = $_SESSION['dealer'] ?? '';
$status = 'Completed';
$response = ['status' => 'danger', 'message' => 'Something went wrong.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pr_number = $_POST['pr_number'] ?? '';
    $po_numbers = $_POST['po_number'] ?? [];         // Array
    $po_dates = $_POST['po_date'] ?? [];             // Array
    $supplier_ids = $_POST['supplier_id'] ?? [];     // Array
    $amounts = $_POST['amount_purchased'] ?? [];     // Array
    $po_files = $_FILES['po_file'] ?? null;          // Array of uploaded PDFs

    // Validate inputs
    if (empty($pr_number) || !is_array($po_numbers) || count($po_numbers) === 0) {
        echo json_encode(['status' => 'danger', 'message' => 'Invalid data submitted.']);
        exit;
    }

    $errors = [];
    $successCount = 0;

    // Prepare upload directory
    $uploadDir = __DIR__ . '/../../uploads/po/';
    if (!file_exists($uploadDir)) mkdir($uploadDir, 0777, true);

    for ($i = 0; $i < count($po_numbers); $i++) {
        $po_number = trim($po_numbers[$i]);
        $po_date = $po_dates[$i] ?? '';
        $supplier_id = $supplier_ids[$i] ?? '';
        $po_amount = $amounts[$i] ?? '';

        // Validate mandatory fields
        if (empty($po_number) || empty($po_date) || empty($supplier_id) || empty($po_amount)) {
            $errors[] = "Missing required fields for PO at row " . ($i + 1);
            continue;
        }

        // Validate PDF file
        if (!isset($po_files['tmp_name'][$i]) || $po_files['error'][$i] !== 0) {
            $errors[] = "PDF file missing or invalid for PO: $po_number";
            continue;
        }

        // Check duplicate PO
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_number = ?");
        $stmt->execute([$po_number]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "PO number $po_number already exists.";
            continue;
        }

        // Handle file upload
        $fileTmpPath = $po_files['tmp_name'][$i];
        $fileName = basename($po_files['name'][$i]);
        $uniqueFileName = time() . '_' . preg_replace('/[^A-Za-z0-9_\.-]/', '_', $fileName);
        $destPath = $uploadDir . $uniqueFileName;
        $dbFilePath = 'uploads/po/' . $uniqueFileName; // Save relative path in DB

        if (!move_uploaded_file($fileTmpPath, $destPath)) {
            $errors[] = "Failed to upload PDF for PO: $po_number";
            continue;
        }

        // Insert PO record
        try {
            $stmt = $pdo->prepare("
                INSERT INTO purchase_order 
                (pr_number, po_number, amount_purchased, supplier_id, po_file, po_dealer, po_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$pr_number, $po_number, $po_amount, $supplier_id, $dbFilePath, $dealer, $po_date]);
            $successCount++;

            $stmt = $pdo->prepare("UPDATE leadtime_tracking SET po_created_date = NOW() WHERE pr_number = ?");
            $stmt->execute([$pr_number]);

            $stmt = $pdo->prepare("UPDATE purchase_requisition SET pr_status = 'Completed' WHERE pr_number = ?");
            $stmt->execute([$pr_number]);

        } catch (Exception $e) {
            $errors[] = 'DB Error for PO ' . $po_number . ': ' . $e->getMessage();
        }
    }

    // Final response
    if ($successCount > 0) {
        $response['status'] = 'success';
        $response['message'] = "$successCount Purchase Order(s) saved successfully!";
        if (!empty($errors)) {
            $response['message'] .= "<br>However, some errors occurred:<br>" . implode('<br>', $errors);
        }
    } else {
        $response['status'] = 'danger';
        $response['message'] = "No Purchase Orders saved.<br>" . implode('<br>', $errors);
    }
}

echo json_encode($response);
