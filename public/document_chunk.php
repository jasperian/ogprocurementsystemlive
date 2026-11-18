<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const MAX_CHUNKED_PDF_BYTES = 10485760;

function chunkJsonResponse(array $response): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($response);
    exit;
}

function chunkDocumentLabels(): array
{
    return [
        'company_profile' => 'Company Profile',
        'product_services' => 'List of Products and Services',
        'business_permit' => 'Business Permit',
        'fin_statement' => 'Audited Financial Statement',
        'org_chart' => 'Organizational Chart',
        'authorize_distributor' => 'Certificate (Authority to Sell / Distributor)',
        'gen_info' => 'GIS (General Information Sheet)',
        'mayors_permit' => "Mayor's Permit",
        'sec_cert' => 'SEC Certificate',
        'bir_cert' => 'Certificate of Registration (2303)',
        'brgy_permit' => 'Brgy. Permit',
        'dti' => 'DTI',
        'memorandum_association' => 'Memorandum of Association',
    ];
}

function chunkDetectMimeType(string $path): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);
            return (string) $mime;
        }
    }

    return function_exists('mime_content_type') ? (string) mime_content_type($path) : '';
}

function validateAssembledPdf(string $field, string $path, string $originalName, int $expectedSize): ?string
{
    $labels = chunkDocumentLabels();
    $label = $labels[$field] ?? $field;

    if (!is_file($path)) {
        return "$label was not uploaded completely.";
    }

    $actualSize = filesize($path);
    if ($actualSize === false || $actualSize <= 0 || $actualSize > MAX_CHUNKED_PDF_BYTES || $actualSize !== $expectedSize) {
        return "$label must be a PDF file up to 10 MB.";
    }

    if (strtolower(pathinfo($originalName, PATHINFO_EXTENSION)) !== 'pdf') {
        return "$label must be a PDF file.";
    }

    $handle = fopen($path, 'rb');
    $signature = $handle ? fread($handle, 4) : '';
    if ($handle) {
        fclose($handle);
    }

    if ($signature !== '%PDF') {
        return "$label must be a valid PDF file.";
    }

    $mime = chunkDetectMimeType($path);
    if ($mime !== '' && !in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
        return "$label must be a valid PDF file.";
    }

    return null;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Invalid request method.']);
}

if (empty($_SESSION['user_id'])) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Your session has expired. Please log in again.']);
}

$field = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['document_field'] ?? '')));
$uploadKey = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['supplier_upload_key'] ?? '')));
$originalName = (string) ($_POST['original_name'] ?? '');
$chunkIndex = filter_input(INPUT_POST, 'chunk_index', FILTER_VALIDATE_INT);
$totalChunks = filter_input(INPUT_POST, 'total_chunks', FILTER_VALIDATE_INT);
$fileSize = filter_input(INPUT_POST, 'file_size', FILTER_VALIDATE_INT);
$chunkData = (string) ($_POST['chunk_data'] ?? '');
$labels = chunkDocumentLabels();

if (!isset($labels[$field]) || strlen($uploadKey) < 16) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Invalid upload request.']);
}

if (!is_int($chunkIndex) || !is_int($totalChunks) || !is_int($fileSize) || $chunkIndex < 0 || $totalChunks < 1 || $chunkIndex >= $totalChunks) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Invalid upload chunk.']);
}

if ($fileSize <= 0 || $fileSize > MAX_CHUNKED_PDF_BYTES || $totalChunks > 120) {
    chunkJsonResponse(['status' => 'danger', 'message' => $labels[$field] . ' must be a PDF file up to 10 MB.']);
}

if ($chunkData === '') {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Upload chunk was empty.']);
}

$decodedChunk = base64_decode($chunkData, true);
if ($decodedChunk === false || $decodedChunk === '') {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Upload chunk could not be read.']);
}

$userId = (int) $_SESSION['user_id'];
$stageFolder = 'uploads/supplier_staging/' . $userId . '_' . $uploadKey . '/';
$stageBase = __DIR__ . '/' . $stageFolder;

if (!is_dir($stageBase) && !mkdir($stageBase, 0775, true)) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Upload folder could not be created.']);
}

if (!is_writable($stageBase)) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Upload folder is not writable.']);
}

$partPath = $stageBase . $field . '.part';
if ($chunkIndex === 0) {
    $existingPath = $_SESSION['supplier_document_uploads'][$uploadKey][$field] ?? null;
    if ($existingPath) {
        $existingAbsolute = __DIR__ . '/' . ltrim((string) $existingPath, '/\\');
        if (is_file($existingAbsolute)) {
            @unlink($existingAbsolute);
        }
    }

    if (is_file($partPath)) {
        @unlink($partPath);
    }
}

if (file_put_contents($partPath, $decodedChunk, FILE_APPEND | LOCK_EX) === false) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Upload chunk could not be saved.']);
}

if (filesize($partPath) > MAX_CHUNKED_PDF_BYTES) {
    @unlink($partPath);
    chunkJsonResponse(['status' => 'danger', 'message' => $labels[$field] . ' must be a PDF file up to 10 MB.']);
}

if ($chunkIndex < $totalChunks - 1) {
    chunkJsonResponse([
        'status' => 'partial',
        'message' => $labels[$field] . ' upload in progress.',
        'field' => $field,
        'chunk' => $chunkIndex + 1,
        'total_chunks' => $totalChunks,
    ]);
}

$error = validateAssembledPdf($field, $partPath, $originalName, $fileSize);
if ($error !== null) {
    @unlink($partPath);
    chunkJsonResponse(['status' => 'danger', 'message' => $error]);
}

$fileName = date('YmdHis') . '_' . $field . '_' . bin2hex(random_bytes(8)) . '.pdf';
$finalPath = $stageBase . $fileName;

if (!rename($partPath, $finalPath)) {
    chunkJsonResponse(['status' => 'danger', 'message' => 'Document could not be finalized.']);
}

$_SESSION['supplier_document_uploads'][$uploadKey][$field] = $stageFolder . $fileName;

chunkJsonResponse([
    'status' => 'success',
    'message' => $labels[$field] . ' uploaded.',
    'field' => $field,
]);
