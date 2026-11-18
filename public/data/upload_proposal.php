<?php
require '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Check if user is Purchasing Staff
if (($_SESSION['position'] ?? '') !== 'Purchasing Staff') {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized access']);
    exit;
}

$batch_id = $_POST['batch_id'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;
$pr_number = $_POST['pr_number'] ?? null;

if (!$batch_id || !$supplier_id || !$pr_number || !isset($_FILES['proposal_file'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit;
}

$file = $_FILES['proposal_file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['status' => 'error', 'message' => 'File upload failed']);
    exit;
}

$allowed_extensions = ['pdf', 'jpg', 'jpeg', 'png'];
$file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['status' => 'error', 'message' => 'Only PDF, JPG, JPEG, and PNG files are allowed']);
    exit;
}

$upload_dir = 'uploads/proposals/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$new_filename = 'proposal_' . $supplier_id . '_' . $batch_id . '_' . time() . '.' . $file_ext;
$upload_path = $upload_dir . $new_filename;

if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    try {
        // Check if a quotation already exists
        $stmt = $pdo->prepare("SELECT id FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
        $stmt->execute([$batch_id, $supplier_id]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Get old filename for history
            $stmt_old = $pdo->prepare("SELECT quotation_file FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
            $stmt_old->execute([$batch_id, $supplier_id]);
            $old_filename = $stmt_old->fetchColumn();

            $stmt = $pdo->prepare("UPDATE canvass_quotation SET quotation_file = ?, pr_number = ? WHERE batch_id = ? AND supplier_id = ?");
            $stmt->execute([$new_filename, $pr_number, $batch_id, $supplier_id]);

            // Log change
            $stmt_log = $pdo->prepare("INSERT INTO canvass_change_history (batch_id, supplier_id, user_id, action_type, old_value, new_value) VALUES (?, ?, ?, 'proposal_upload', ?, ?)");
            $stmt_log->execute([$batch_id, $supplier_id, $_SESSION['user_id'], "Old Proposal: $old_filename", "New Proposal: $new_filename"]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO canvass_quotation (batch_id, supplier_id, pr_number, quotation_file) VALUES (?, ?, ?, ?)");
            $stmt->execute([$batch_id, $supplier_id, $pr_number, $new_filename]);

            // Log new upload
            $stmt_log = $pdo->prepare("INSERT INTO canvass_change_history (batch_id, supplier_id, user_id, action_type, old_value, new_value) VALUES (?, ?, ?, 'proposal_upload', ?, ?)");
            $stmt_log->execute([$batch_id, $supplier_id, $_SESSION['user_id'], "N/A", "Initial Proposal: $new_filename"]);
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Proposal uploaded successfully',
            'filename' => $new_filename,
            'is_image' => in_array($file_ext, ['jpg', 'jpeg', 'png'])
        ]);
    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Failed to move uploaded file']);
}
