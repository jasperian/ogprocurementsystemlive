<?php
include '../../includes/db.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Your session has expired. Please log in again.']);
    exit;
}

$requestedUserId = isset($_POST['user_id']) ? base64_decode((string)$_POST['user_id'], true) : (string)$_SESSION['user_id'];
$requestedUserId = $requestedUserId !== false ? (int)$requestedUserId : 0;
$sessionUserId = (int)$_SESSION['user_id'];
$isAdmin = ($_SESSION['position'] ?? '') === 'Administrator';

if ($requestedUserId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid profile request.']);
    exit;
}

if ($requestedUserId !== $sessionUserId && !$isAdmin) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'You can only update your own profile.']);
    exit;
}

$email = trim($_POST['email_address'] ?? '');
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address.']);
    exit;
}

$updates = ['email_address = :email_address'];
$params = [
    ':email_address' => $email !== '' ? $email : null,
    ':user_id' => $requestedUserId,
];
$enforceOneTimeSignature = false;
$signatureTargetPath = null;

if (isset($_FILES['user_signature']) && $_FILES['user_signature']['error'] !== UPLOAD_ERR_NO_FILE) {
    if (!$isAdmin) {
        $signatureCheck = $pdo->prepare('SELECT user_signature FROM users WHERE user_id = :user_id');
        $signatureCheck->execute([':user_id' => $requestedUserId]);
        $existingSignature = $signatureCheck->fetchColumn();

        if (is_string($existingSignature) && trim($existingSignature) !== '') {
            http_response_code(409);
            echo json_encode([
                'status' => 'error',
                'message' => 'A signature is already saved. Please contact the administrator to change your current signature.',
            ]);
            exit;
        }

        $enforceOneTimeSignature = true;
    }

    if ($_FILES['user_signature']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to upload the signature.']);
        exit;
    }

    if ($_FILES['user_signature']['size'] > 5 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Signature image must be 5 MB or smaller.']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $signatureMimeType = $finfo->file($_FILES['user_signature']['tmp_name']);
    if ($signatureMimeType !== 'image/png') {
        echo json_encode(['status' => 'error', 'message' => 'The processed signature must be a PNG image.']);
        exit;
    }

    $signatureSize = @getimagesize($_FILES['user_signature']['tmp_name']);
    if ($signatureSize === false || $signatureSize[0] < 1 || $signatureSize[1] < 1 || $signatureSize[0] > 2400 || $signatureSize[1] > 2400) {
        echo json_encode(['status' => 'error', 'message' => 'The processed signature has invalid dimensions.']);
        exit;
    }

    $signatureDir = dirname(__DIR__) . '/assets/images/signature/';
    if (!is_dir($signatureDir) && !mkdir($signatureDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Signature directory is not available.']);
        exit;
    }

    $signatureFileName = $requestedUserId . '_' . bin2hex(random_bytes(8)) . '.png';
    $signatureTargetPath = $signatureDir . $signatureFileName;
    if (!move_uploaded_file($_FILES['user_signature']['tmp_name'], $signatureTargetPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to save the signature.']);
        exit;
    }

    $updates[] = 'user_signature = :user_signature';
    $params[':user_signature'] = 'assets/images/signature/' . $signatureFileName;
}

if (isset($_FILES['user_pfp']) && $_FILES['user_pfp']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['user_pfp']['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to upload the profile photo.']);
        exit;
    }

    if ($_FILES['user_pfp']['size'] > 2 * 1024 * 1024) {
        echo json_encode(['status' => 'error', 'message' => 'Profile photo must be 2 MB or smaller.']);
        exit;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($_FILES['user_pfp']['tmp_name']);
    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    if (!isset($allowedTypes[$mimeType])) {
        echo json_encode(['status' => 'error', 'message' => 'Profile photo must be a JPG, PNG, or WebP image.']);
        exit;
    }

    $uploadDir = dirname(__DIR__) . '/assets/images/pfp/';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0777, true)) {
        echo json_encode(['status' => 'error', 'message' => 'Profile photo directory is not available.']);
        exit;
    }

    $fileName = $requestedUserId . '_' . bin2hex(random_bytes(8)) . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . $fileName;

    if (!move_uploaded_file($_FILES['user_pfp']['tmp_name'], $targetPath)) {
        echo json_encode(['status' => 'error', 'message' => 'Unable to save the profile photo.']);
        exit;
    }

    $updates[] = 'user_pfp = :user_pfp';
    $params[':user_pfp'] = 'assets/images/pfp/' . $fileName;
}

$signatureCondition = $enforceOneTimeSignature
    ? " AND (user_signature IS NULL OR TRIM(user_signature) = '')"
    : '';
$stmt = $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE user_id = :user_id' . $signatureCondition);
$stmt->execute($params);

if ($enforceOneTimeSignature && $stmt->rowCount() === 0) {
    if (is_string($signatureTargetPath) && is_file($signatureTargetPath)) {
        @unlink($signatureTargetPath);
    }
    http_response_code(409);
    echo json_encode([
        'status' => 'error',
        'message' => 'A signature is already saved. Please contact the administrator to change your current signature.',
    ]);
    exit;
}

echo json_encode(['status' => 'success', 'message' => 'Profile settings updated successfully.']);
