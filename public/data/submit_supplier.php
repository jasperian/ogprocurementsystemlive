<?php
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ob_start();
header('Content-Type: application/json');
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

include __DIR__ . '/../../includes/db.php';
require __DIR__ . '/../includes/pusher.php';
require_once __DIR__ . '/../includes/mailer.php';

const MAX_SUPPLIER_PDF_BYTES = 10485760; // 10 MB

function jsonResponse(array $response): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    echo json_encode($response);
    exit;
}

function cleanInput(?string $value): string
{
    return trim((string) $value);
}

function supplierDocumentMap(): array
{
    return [
        'Small Business' => ['dti', 'brgy_permit', 'bir_cert'],
        'Sole Proprietorship' => ['dti', 'brgy_permit', 'bir_cert', 'mayors_permit'],
        'Micro' => ['brgy_permit', 'mayors_permit', 'bir_cert'],
        'Corporate' => [
            'company_profile',
            'product_services',
            'business_permit',
            'bir_cert',
            'fin_statement',
            'org_chart',
            'authorize_distributor',
            'gen_info',
            'mayors_permit',
            'sec_cert',
        ],
    ];
}

function supplierDocumentLabels(): array
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

function allDocumentFields(): array
{
    return array_keys(supplierDocumentLabels());
}

function supplierUploadFieldAliases(): array
{
    return [
        'company_profile' => ['company_profile', 'doc_company_profile'],
        'product_services' => ['product_services', 'doc_product_services'],
        'business_permit' => ['business_permit', 'doc_business_permit'],
        'fin_statement' => ['fin_statement', 'doc_financial'],
        'org_chart' => ['org_chart', 'doc_org_chart'],
        'authorize_distributor' => ['authorize_distributor', 'doc_auth_dist'],
        'gen_info' => ['gen_info', 'doc_gis'],
        'mayors_permit' => ['mayors_permit', 'doc_mayors'],
        'sec_cert' => ['sec_cert', 'doc_sec'],
        'bir_cert' => ['bir_cert', 'doc_bir'],
        'brgy_permit' => ['brgy_permit', 'doc_brgy'],
        'dti' => ['dti', 'doc_dti'],
        'memorandum_association' => ['memorandum_association', 'doc_memo'],
    ];
}

function requiredDocumentFields(string $businessType): array
{
    $map = supplierDocumentMap();
    return $map[$businessType] ?? [];
}

function validateSupplierPayload(array $post): array
{
    $allowedBusinessTypes = array_keys(supplierDocumentMap());
    $allowedTerms = [
        '60 Days',
        '30 Days',
        '15 Days',
        '7 Days',
        '50% DP / 50% Upon Comp',
        'Cash Basis',
        'COD (Cash on delivery)',
    ];
    $allowedVatStatuses = ['With VAT', 'Without VAT'];

    $payload = [
        'businessType' => cleanInput($post['businessType'] ?? ''),
        'productCategory' => cleanInput($post['productCategory'] ?? ''),
        'vendorName' => cleanInput($post['vendorName'] ?? ''),
        'tinNumber' => cleanInput($post['tinNumber'] ?? ''),
        'vendorAddress' => cleanInput($post['vendorAddress'] ?? ''),
        'contactPerson' => cleanInput($post['contactPerson'] ?? ''),
        'contactDesignation' => cleanInput($post['contactDesignation'] ?? ''),
        'contactNumber' => preg_replace('/\D/', '', cleanInput($post['contactNumber'] ?? '')),
        'email' => cleanInput($post['email'] ?? ''),
        'mobileNumber' => preg_replace('/\D/', '', cleanInput($post['mobileNumber'] ?? '')),
        'termsPayment' => cleanInput($post['termsPayment'] ?? ''),
        'vatStatus' => cleanInput($post['vatStatus'] ?? ''),
        'memorandumExpiration' => cleanInput($post['memorandumExpiration'] ?? ''),
    ];

    $requiredFields = [
        'businessType',
        'productCategory',
        'vendorName',
        'tinNumber',
        'vendorAddress',
        'contactPerson',
        'contactDesignation',
        'termsPayment',
        'vatStatus',
    ];

    foreach ($requiredFields as $field) {
        if ($payload[$field] === '') {
            return [null, 'Please complete all required supplier fields.'];
        }
    }

    if (!in_array($payload['businessType'], $allowedBusinessTypes, true)) {
        return [null, 'Please select a valid business type.'];
    }

    if (!in_array($payload['termsPayment'], $allowedTerms, true)) {
        return [null, 'Please select a valid terms of payment.'];
    }

    if (!in_array($payload['vatStatus'], $allowedVatStatuses, true)) {
        return [null, 'Please select a valid VAT status.'];
    }

    if (!preg_match('/^\d{3}-\d{3}-\d{3}-\d{4}$/', $payload['tinNumber'])) {
        return [null, 'TIN number must be in the format 000-000-000-0000.'];
    }

    if ($payload['contactNumber'] !== '' && !preg_match('/^\d{11}$/', $payload['contactNumber'])) {
        return [null, 'Contact number must contain exactly 11 digits.'];
    }

    if ($payload['mobileNumber'] !== '' && !preg_match('/^\d{11}$/', $payload['mobileNumber'])) {
        return [null, 'Mobile number must contain exactly 11 digits.'];
    }

    if ($payload['email'] !== '' && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
        return [null, 'Please enter a valid email address.'];
    }

    if ($payload['memorandumExpiration'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $payload['memorandumExpiration']);
        $errors = DateTime::getLastErrors();
        $hasDateErrors = $errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0);
        if (!$date || $hasDateErrors || $date->format('Y-m-d') !== $payload['memorandumExpiration']) {
            return [null, 'Please enter a valid Memorandum of Association expiration date.'];
        }
    }

    return [$payload, null];
}

function hasUploadedFile(string $field): bool
{
    return uploadedFile($field) !== null || stagedUploadedPath($field) !== null;
}

function uploadedFile(string $field): ?array
{
    $aliases = supplierUploadFieldAliases()[$field] ?? [$field];

    foreach ($aliases as $alias) {
        if (isset($_FILES[$alias]) && is_array($_FILES[$alias]) && ($_FILES[$alias]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            return $_FILES[$alias];
        }
    }

    return null;
}

function supplierUploadKey(): string
{
    return preg_replace('/[^a-f0-9]/', '', strtolower((string) ($_POST['supplier_upload_key'] ?? '')));
}

function stagedUploadedPath(string $field): ?string
{
    $uploadKey = supplierUploadKey();
    if ($uploadKey === '') {
        return null;
    }

    $paths = $_SESSION['supplier_document_uploads'][$uploadKey] ?? [];
    $path = $paths[$field] ?? null;
    if (!$path || !is_string($path)) {
        return null;
    }

    $absolutePath = __DIR__ . '/../' . ltrim($path, '/\\');
    return is_file($absolutePath) ? $path : null;
}

function moveStagedPdf(string $field, string $stagedRelativePath, string $uploadBase, string $uploadRelative): string
{
    $sourcePath = __DIR__ . '/../' . ltrim($stagedRelativePath, '/\\');
    if (!is_file($sourcePath)) {
        throw new RuntimeException("Staged uploaded file for $field was not found.");
    }

    $random = bin2hex(random_bytes(8));
    $fileName = date('YmdHis') . '_' . $field . '_' . $random . '.pdf';
    $destPath = $uploadBase . $fileName;

    if (!rename($sourcePath, $destPath)) {
        if (!copy($sourcePath, $destPath) || !unlink($sourcePath)) {
            throw new RuntimeException("Failed to move staged uploaded file for $field.");
        }
    }

    return $uploadRelative . $fileName;
}

function detectMimeType(string $tmpName): string
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

function validatePdfUpload(string $field, array $file): ?string
{
    $labels = supplierDocumentLabels();
    $label = $labels[$field] ?? $field;
    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

    if ($error !== UPLOAD_ERR_OK) {
        return "Please upload a valid PDF for $label.";
    }

    if (!is_uploaded_file($file['tmp_name'] ?? '')) {
        return "Please upload a valid PDF for $label.";
    }

    if ((int) ($file['size'] ?? 0) <= 0 || (int) ($file['size'] ?? 0) > MAX_SUPPLIER_PDF_BYTES) {
        return "$label must be a PDF file up to 10 MB.";
    }

    $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if ($extension !== 'pdf') {
        return "$label must be a PDF file.";
    }

    $mime = detectMimeType($file['tmp_name']);
    $allowedMimeTypes = ['application/pdf', 'application/x-pdf'];
    if (!in_array($mime, $allowedMimeTypes, true)) {
        return "$label must be a valid PDF file.";
    }

    $handle = fopen($file['tmp_name'], 'rb');
    $signature = $handle ? fread($handle, 4) : '';
    if ($handle) {
        fclose($handle);
    }

    if ($signature !== '%PDF') {
        return "$label must be a valid PDF file.";
    }

    return null;
}

function saveUploadedPdf(string $field, array $file, string $uploadBase, string $uploadRelative): string
{
    $random = bin2hex(random_bytes(8));
    $fileName = date('YmdHis') . '_' . $field . '_' . $random . '.pdf';
    $destPath = $uploadBase . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        throw new RuntimeException("Failed to move uploaded file for $field.");
    }

    return $uploadRelative . $fileName;
}

function cleanupUploadedFiles(array $paths): void
{
    foreach ($paths as $path) {
        $absolutePath = __DIR__ . '/../' . ltrim((string) $path, '/\\');
        if ($path && is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }
}

function queueMail(PDO $pdo, ?int $userId, ?string $to, ?string $toName, string $subject, string $body, string $altBody = ''): bool
{
    if (!$to) {
        return false;
    }

    $stmt = $pdo->prepare("
        INSERT INTO email_queue (sender_id, recipient_email, recipient_name, subject, body, alt_body)
        VALUES (?, ?, ?, ?, ?, ?)
    ");

    return $stmt->execute([
        $userId,
        $to,
        $toName ?: $to,
        $subject,
        $body,
        $altBody ?: strip_tags($body),
    ]);
}

function buildApprovalEmail(string $subject, string $fullname, string $vendorName, string $vendorID): string
{
    $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
    $safeFullname = htmlspecialchars($fullname, ENT_QUOTES, 'UTF-8');
    $safeVendorName = htmlspecialchars($vendorName, ENT_QUOTES, 'UTF-8');
    $safeVendorID = urlencode($vendorID);

    return '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>' . $safeSubject . '</title>
    </head>
    <body style="margin:0; padding:0; background-color:#f4f6f8; font-family:Segoe UI, Arial, sans-serif; color:#2c3e50;">
        <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="100%">
            <tr>
                <td align="center" style="padding:20px 0;">
                    <table role="presentation" border="0" cellpadding="0" cellspacing="0" width="600" style="background-color:#ffffff; border:1px solid #e0e0e0; border-radius:6px;">
                        <tr><td bgcolor="#004080" style="height:5px; line-height:5px; font-size:0;"></td></tr>
                        <tr>
                            <td align="center" style="padding:20px; border-bottom:1px solid #f0f0f0;">
                                <h1 style="font-size:20px; font-weight:600; color:#004080; margin:0;">OGPS Supplier Verification</h1>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:24px 28px; font-size:15px; line-height:1.6; color:#333333;">
                                <p style="margin:0 0 15px;">Amazing day Ma`am/Sir,</p>
                                <p style="margin:0 0 15px;">A new supplier has been added by <strong>' . $safeFullname . '</strong> and is pending your approval in OGPS.</p>
                                <table role="presentation" border="0" cellpadding="8" cellspacing="0" width="100%" style="background-color:#fafbfc; border:1px solid #e6e9ec; border-radius:4px; margin:15px 0;">
                                    <tr><td style="font-weight:600; color:#555;">Supplier Name</td><td style="text-align:right;">' . $safeVendorName . '</td></tr>
                                    <tr><td style="font-weight:600; color:#555;">Submitted By</td><td style="text-align:right;">' . $safeFullname . '</td></tr>
                                </table>
                                <p style="margin:0 0 15px;">Please log in to the system to review and verify the supplier at your earliest convenience.</p>
                                <table role="presentation" border="0" cellpadding="0" cellspacing="0" style="margin-top:15px;">
                                    <tr>
                                        <td bgcolor="#004080" style="border-radius:4px;">
                                            <a href="supplier_verification?vendor_id=' . $safeVendorID . '" style="display:inline-block; padding:10px 20px; color:#ffffff; font-size:14px; font-weight:600; text-decoration:none;">Review &amp; Approve Supplier</a>
                                        </td>
                                    </tr>
                                </table>
                                <p style="margin:25px 0 0;">Best regards,<br><strong>' . $safeFullname . '</strong></p>
                            </td>
                        </tr>
                        <tr><td align="center" style="padding:14px 20px; background-color:#f4f6f8; font-size:12px; color:#888888;">This is an automated message. Please do not reply.</td></tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>';
}

$requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';
if ($requestMethod !== 'POST') {
    jsonResponse(['status' => 'danger', 'message' => 'Invalid request method.']);
}

if (empty($_SESSION['user_id'])) {
    jsonResponse(['status' => 'danger', 'message' => 'Your session has expired. Please log in again.']);
}

[$payload, $validationError] = validateSupplierPayload($_POST);
if ($validationError !== null) {
    jsonResponse(['status' => 'danger', 'message' => $validationError]);
}

$fullname = cleanInput($_SESSION['full_name'] ?? '');
$dealer = cleanInput($_SESSION['dealer'] ?? '');
$department = cleanInput($_SESSION['department'] ?? '');
$user_id = (int) ($_SESSION['user_id'] ?? 0);
$date = date('Y-m-d H:i:s');
$uploadedPaths = [];
$pusherPayload = null;
$pusherChannel = null;

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_category WHERE category_name = ?");
    $stmt->execute([$payload['productCategory']]);
    if ((int) $stmt->fetchColumn() === 0) {
        jsonResponse(['status' => 'danger', 'message' => 'Please select a valid business category.']);
    }

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE tin_number = ?");
    $stmt->execute([$payload['tinNumber']]);
    if ((int) $stmt->fetchColumn() > 0) {
        jsonResponse(['status' => 'danger', 'message' => 'A supplier with this TIN number already exists.']);
    }

    $requiredDocs = requiredDocumentFields($payload['businessType']);
    $docLabels = supplierDocumentLabels();
    $hasMemorandumFile = hasUploadedFile('memorandum_association');

    if ($hasMemorandumFile && $payload['memorandumExpiration'] === '') {
        jsonResponse(['status' => 'danger', 'message' => 'Please enter the Memorandum of Association expiration date.']);
    }

    if (!$hasMemorandumFile && $payload['memorandumExpiration'] !== '') {
        jsonResponse(['status' => 'danger', 'message' => 'Please upload the Memorandum of Association PDF.']);
    }

    foreach ($requiredDocs as $field) {
        if (!hasUploadedFile($field)) {
            jsonResponse(['status' => 'danger', 'message' => 'Please upload ' . $docLabels[$field] . '.']);
        }
    }

    foreach (allDocumentFields() as $field) {
        $uploadedFile = uploadedFile($field);
        if ($uploadedFile !== null) {
            $error = validatePdfUpload($field, $uploadedFile);
            if ($error !== null) {
                jsonResponse(['status' => 'danger', 'message' => $error]);
            }
        }
    }

    $folderName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $payload['vendorName']);
    $folderName = trim($folderName, '_') ?: 'supplier';
    $uploadBase = __DIR__ . '/../uploads/' . $folderName . '/';
    $uploadRelative = 'uploads/' . $folderName . '/';

    if (!is_dir($uploadBase) && !mkdir($uploadBase, 0775, true)) {
        throw new RuntimeException('Supplier upload directory could not be created.');
    }

    if (!is_writable($uploadBase)) {
        throw new RuntimeException('Supplier upload directory is not writable.');
    }

    $filePaths = array_fill_keys(allDocumentFields(), null);
    foreach (allDocumentFields() as $field) {
        $uploadedFile = uploadedFile($field);
        if ($uploadedFile !== null) {
            $filePaths[$field] = saveUploadedPdf($field, $uploadedFile, $uploadBase, $uploadRelative);
            $uploadedPaths[] = $filePaths[$field];
            continue;
        }

        $stagedPath = stagedUploadedPath($field);
        if ($stagedPath !== null) {
            $filePaths[$field] = moveStagedPdf($field, $stagedPath, $uploadBase, $uploadRelative);
            $uploadedPaths[] = $filePaths[$field];
        }
    }

    $stmt = $pdo->prepare("
        SELECT user_id, email_address, full_name
        FROM users
        WHERE department = :department
          AND dealer = :dealer
          AND position = 'Manager'
          AND is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':department' => $department, ':dealer' => $dealer]);
    $manager = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO suppliers (
            business_type, product_category, supplier_name, tin_number, street, email,
            contact_designation, contact_number, mobile_number, contact_person,
            payment_terms, vat_status, created_by, dealer
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $payload['businessType'],
        $payload['productCategory'],
        $payload['vendorName'],
        $payload['tinNumber'],
        $payload['vendorAddress'],
        $payload['email'],
        $payload['contactDesignation'],
        $payload['contactNumber'],
        $payload['mobileNumber'],
        $payload['contactPerson'],
        $payload['termsPayment'],
        $payload['vatStatus'],
        $fullname,
        $dealer,
    ]);

    $lastId = $pdo->lastInsertId();
    $vendorID = 'VN' . str_pad((string) $lastId, 6, '0', STR_PAD_LEFT);

    $stmt = $pdo->prepare("UPDATE suppliers SET vendor_id = ? WHERE supplier_id = ?");
    $stmt->execute([$vendorID, $lastId]);

    $stmt = $pdo->prepare("
        INSERT INTO supplier_profile (
            vendor_id, company_profile, product_services, business_permit,
            fin_statement, org_chart, authorize_distributor, gen_info,
            mayors_permit, sec_cert, bir_cert, brgy_permit, dti,
            memorandum_association, memorandum_expiration
        ) VALUES (
            :vendor_id, :company_profile, :product_services, :business_permit,
            :fin_statement, :org_chart, :authorize_distributor, :gen_info,
            :mayors_permit, :sec_cert, :bir_cert, :brgy_permit, :dti,
            :memorandum_association, :memorandum_expiration
        )
    ");
    $stmt->execute([
        ':vendor_id' => $vendorID,
        ':company_profile' => $filePaths['company_profile'],
        ':product_services' => $filePaths['product_services'],
        ':business_permit' => $filePaths['business_permit'],
        ':fin_statement' => $filePaths['fin_statement'],
        ':org_chart' => $filePaths['org_chart'],
        ':authorize_distributor' => $filePaths['authorize_distributor'],
        ':gen_info' => $filePaths['gen_info'],
        ':mayors_permit' => $filePaths['mayors_permit'],
        ':sec_cert' => $filePaths['sec_cert'],
        ':bir_cert' => $filePaths['bir_cert'],
        ':brgy_permit' => $filePaths['brgy_permit'],
        ':dti' => $filePaths['dti'],
        ':memorandum_association' => $filePaths['memorandum_association'],
        ':memorandum_expiration' => $payload['memorandumExpiration'] ?: null,
    ]);

    $approvalInitialized = false;
    if ($manager) {
        $stmt = $pdo->prepare("
            INSERT INTO supplier_approvals (vendor_id, approver_1, approver_2, approver_3, assigned_dealer)
            VALUES (:vendor_id, :approver_1, :approver_2, :approver_3, :assigned_dealer)
        ");
        $stmt->execute([
            ':vendor_id' => $vendorID,
            ':approver_1' => $manager['user_id'],
            ':approver_2' => '4',
            ':approver_3' => '3',
            ':assigned_dealer' => $dealer,
        ]);
        $approvalInitialized = true;

        $link = 'supplier_verification?vendor_id=' . urlencode($vendorID) . '&vendor_name=' . urlencode($payload['vendorName']) . '&dealer=' . urlencode($dealer);
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, is_read, link, created_at)
            VALUES (:user_id, :title, :message, 'info', 0, :link, :created_at)
        ");
        $stmt->execute([
            ':user_id' => $manager['user_id'],
            ':title' => $fullname . ' Added a New Supplier',
            ':message' => 'Supplier Name: ' . $payload['vendorName'],
            ':link' => $link,
            ':created_at' => $date,
        ]);

        $emailSubject = 'OGPS: New Supplier Awaiting Your Approval';
        $emailBody = buildApprovalEmail($emailSubject, $fullname, $payload['vendorName'], $vendorID);
        queueMail($pdo, $user_id, $manager['email_address'] ?? null, $manager['full_name'] ?? null, $emailSubject, $emailBody);

        $pusherChannel = 'private-user-' . $manager['user_id'];
        $pusherPayload = [
            'notifications' => [[
                'user_id' => $manager['user_id'],
                'title' => 'A new supplier added by ' . $fullname . ' awaits your approval.',
                'message' => 'Supplier Name: ' . $payload['vendorName'],
                'type' => 'info',
                'is_read' => 0,
                'link' => $link,
                'created_at' => $date,
            ]],
        ];
    }

    $pdo->commit();
    $uploadKey = supplierUploadKey();
    if ($uploadKey !== '') {
        unset($_SESSION['supplier_document_uploads'][$uploadKey]);
    }

    if (isset($pusher, $pusherPayload, $pusherChannel)) {
        try {
            $pusher->trigger($pusherChannel, 'my-event', $pusherPayload);
        } catch (Exception $e) {
            error_log('Pusher error while creating supplier: ' . $e->getMessage());
        }
    }

    $message = $approvalInitialized
        ? "Vendor added successfully as $vendorID. Files uploaded and approval process initialized."
        : "Vendor added successfully as $vendorID. Files uploaded, but no matching manager was found for approval notification.";

    jsonResponse(['status' => 'success', 'message' => $message]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    cleanupUploadedFiles($uploadedPaths);
    error_log('Supplier submission failed: ' . $e->getMessage());
    jsonResponse(['status' => 'danger', 'message' => 'Supplier could not be saved. Please check the details and try again.']);
}
