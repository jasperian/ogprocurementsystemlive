<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const MAX_STAGE_PDF_BYTES = 10485760;

function stageJsonResponse(array $response): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($response);
    exit;
}

function stageDocumentLabels(): array
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

function stageDetectMimeType(string $tmpName): string
{
    if (function_exists('finfo_open')) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo !== false) {
            $mime = finfo_file($finfo, $tmpName);
            finfo_close($finfo);
            return (string) $mime;
        }
    }

    return function_exists('mime_content_type') ? (string) mime_content_type($tmpName) : '';
}

function stageValidatePdf(string $field, array $file): ?string
{
    $labels = stageDocumentLabels();
    $label = $labels[$field] ?? $field;
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) {
        return "Please upload a valid PDF for $label.";
    }

    if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > MAX_STAGE_PDF_BYTES) {
        return "$label must be a PDF file up to 10 MB.";
    }

    if (strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION)) !== 'pdf') {
        return "$label must be a PDF file.";
    }

    if (!in_array(stageDetectMimeType($file['tmp_name']), ['application/pdf', 'application/x-pdf'], true)) {
        return "$label must be a valid PDF file.";
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 4) : '';
    if ($handle) {
        fclose($handle);
    }

    return $signature === '%PDF' ? null : "$label must be a valid PDF file.";
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    stageJsonResponse(['status' => 'danger', 'message' => 'Invalid request method.']);
}

if (empty($_SESSION['user_id'])) {
    stageJsonResponse(['status' => 'danger', 'message' => 'Your session has expired. Please log in again.']);
}

$field = preg_replace('/[^a-z_]/', '', strtolower((string) ($_POST['document_field'] ?? '')));
$uploadKey = preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['supplier_upload_key'] ?? '')));
$labels = stageDocumentLabels();

if (!isset($labels[$field]) || strlen($uploadKey) < 16) {
    stageJsonResponse(['status' => 'danger', 'message' => 'Invalid upload request.']);
}

$file = $_FILES['doc_file'] ?? null;
if (!is_array($file)) {
    stageJsonResponse(['status' => 'danger', 'message' => 'No document was uploaded.']);
}

$error = stageValidatePdf($field, $file);
if ($error !== null) {
    stageJsonResponse(['status' => 'danger', 'message' => $error]);
}

$userId = (int) $_SESSION['user_id'];
$stageFolder = 'uploads/supplier_staging/' . $userId . '_' . $uploadKey . '/';
$stageBase = __DIR__ . '/' . $stageFolder;

if (!is_dir($stageBase) && !mkdir($stageBase, 0775, true)) {
    stageJsonResponse(['status' => 'danger', 'message' => 'Upload folder could not be created.']);
}

if (!is_writable($stageBase)) {
    stageJsonResponse(['status' => 'danger', 'message' => 'Upload folder is not writable.']);
}

$existingPath = $_SESSION['supplier_document_uploads'][$uploadKey][$field] ?? null;
if ($existingPath) {
    $existingAbsolute = __DIR__ . '/' . ltrim((string) $existingPath, '/\\');
    if (is_file($existingAbsolute)) {
        @unlink($existingAbsolute);
    }
}

$fileName = date('YmdHis') . '_' . $field . '_' . bin2hex(random_bytes(8)) . '.pdf';
$destPath = $stageBase . $fileName;

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    stageJsonResponse(['status' => 'danger', 'message' => 'Document could not be uploaded.']);
}

$_SESSION['supplier_document_uploads'][$uploadKey][$field] = $stageFolder . $fileName;

stageJsonResponse([
    'status' => 'success',
    'message' => $labels[$field] . ' uploaded.',
    'field' => $field,
]);
