<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';


$dealer = $_SESSION['dealer'];
$department = $_SESSION['department'];
$publicBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$publicBaseUrl = ($publicBasePath === '' || $publicBasePath === '.') ? '/' : $publicBasePath . '/';

// $generalmanager_userid = null;
// $purchasingmanager_userid = null;

// // Get General Manager Approver
// $stmt = $pdo->prepare("
//     SELECT user_id 
//     FROM users 
//     WHERE role = 'Approver' 
//       AND position = 'General Manager' 
//       AND dealer = :dealer
//     LIMIT 1
// ");
// $stmt->execute([':dealer' => $dealer]);
// $gm = $stmt->fetch(PDO::FETCH_ASSOC);

// if ($gm) {
//     $generalmanager_userid = $gm['user_id'];
// }

// // Get Noter in the same department (Purchasing)
// $stmt = $pdo->prepare("
//     SELECT user_id 
//     FROM users 
//     WHERE role = 'Noter' 
//       AND department = :department 
//       AND dealer = :dealer
//     LIMIT 1
// ");
// $stmt->execute([
//     ':department' => $department,
//     ':dealer' => $dealer
// ]);
// $pm = $stmt->fetch(PDO::FETCH_ASSOC);

// if ($pm) {
//     $purchasingmanager_userid = $pm['user_id'];
// }

?>
<!-- [ Main Content ] start -->
<style>
    .supplier-page .page-header-title h2 {
        font-weight: 700;
        letter-spacing: 0;
    }

    .supplier-subtitle {
        color: #667085;
        margin: 6px 0 0;
    }

    .supplier-shell {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(16, 24, 40, 0.06);
        overflow: hidden;
    }

    .supplier-shell .card-body {
        padding: 0;
    }

    .supplier-form-section {
        padding: 26px 28px;
        border-bottom: 1px solid #eef1f5;
    }

    .supplier-form-section:last-of-type {
        border-bottom: 0;
    }

    .section-heading {
        display: flex;
        align-items: flex-start;
        gap: 14px;
        margin-bottom: 22px;
    }

    .section-icon {
        width: 38px;
        height: 38px;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: #eef6ff;
        color: #1677ff;
        flex: 0 0 auto;
    }

    .section-heading h5 {
        margin: 0;
        font-weight: 700;
    }

    .section-heading p {
        margin: 3px 0 0;
        color: #667085;
        font-size: 13px;
    }

    .supplier-form-section .form-label {
        color: #344054;
        font-weight: 600;
        margin-bottom: 7px;
    }

    .required-mark::after {
        content: " *";
        color: #dc3545;
    }

    .supplier-form-section .form-control,
    .supplier-form-section .form-select,
    .select2-container--bootstrap-5 .select2-selection {
        border-color: #d8dde6;
        border-radius: 8px;
        min-height: 44px;
    }

    .supplier-form-section .form-control:focus,
    .supplier-form-section .form-select:focus,
    .select2-container--bootstrap-5.select2-container--focus .select2-selection {
        border-color: #1677ff;
        box-shadow: 0 0 0 0.2rem rgba(22, 119, 255, 0.12);
    }

    .select2-container {
        width: 100% !important;
    }

    .supporting-text {
        color: #667085;
        font-size: 12px;
        margin-top: 6px;
    }

    .memo-upload-box {
        border: 1px dashed #cbd5e1;
        border-radius: 8px;
        background: #f8fafc;
        padding: 16px;
    }

    .document-summary {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        color: #475467;
        display: flex;
        font-size: 13px;
        justify-content: space-between;
        margin-bottom: 18px;
        padding: 12px 14px;
    }

    .upload-item {
        display: flex;
    }

    .upload-card {
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        padding: 15px;
        width: 100%;
        background: #fff;
        transition: border-color 0.2s ease, box-shadow 0.2s ease;
    }

    .upload-card:hover {
        border-color: #9cc8ff;
        box-shadow: 0 8px 20px rgba(16, 24, 40, 0.05);
    }

    .upload-card.is-uploaded {
        border-color: #22c55e;
        background: #f8fffb;
    }

    .upload-trigger {
        min-height: 42px;
        white-space: normal;
    }

    .file-name {
        color: #475467;
        display: block;
        font-size: 12px;
        margin-top: 8px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pdf-container {
        cursor: pointer;
    }

    .pdf-thumbnail {
        max-width: 100%;
        height: 132px;
        object-fit: contain;
        border: 1px solid #d0d5dd;
        border-radius: 6px;
        background: #fff;
    }

    .form-actions {
        align-items: center;
        background: #fff;
        border-top: 1px solid #eef1f5;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        padding: 20px 28px;
    }

    #pdfCanvas {
        max-width: 100%;
    }

    @media (max-width: 767.98px) {
        .supplier-form-section,
        .form-actions {
            padding: 20px 18px;
        }

        .section-heading {
            gap: 10px;
        }

        .form-actions {
            align-items: stretch;
            flex-direction: column-reverse;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<!-- Select2 CSS -->
<!-- Select2 CSS -->
<link href="https://unpkg.com/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://unpkg.com/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<!-- Select2 Bootstrap 5 Theme -->
<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />



<div class="pc-container supplier-page">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0">Add New Supplier</h2>
                            </div>
                            <a href="suppliers" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-arrow-left f-18"></i> Back to Suppliers
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card supplier-shell">
                    <div class="card-body">
                        <form id="vendorForm" enctype="multipart/form-data">
                            <div class="supplier-form-section">
                                <div class="section-heading">
                                    <span class="section-icon"><i class="ti ti-building-store f-20"></i></span>
                                    <div>
                                        <h5>Supplier Information</h5>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label required-mark" for="businessType">Business Type</label>
                                        <select class="form-select" name="businessType" id="businessType" required>
                                            <option value="" selected hidden>Select business type</option>
                                            <option value="Small Business">Small Business</option>
                                            <option value="Sole Proprietorship">Sole Proprietorship</option>
                                            <option value="Micro">Micro</option>
                                            <option value="Corporate">Corporate</option>
                                        </select>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label required-mark" for="productCategory">Business Category</label>
                                        <select class="form-select" name="productCategory" id="productCategory" data-placeholder="Select business category" required></select>
                                    </div>

                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label required-mark" for="tinNumber">TIN Number</label>
                                        <input type="text"
                                            class="form-control"
                                            name="tinNumber"
                                            id="tinNumber"
                                            placeholder="000-000-000-0000"
                                            minlength="16"
                                            maxlength="16"
                                            required />
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-mark" for="supplierName">Vendor Name</label>
                                        <input type="text" class="form-control" name="vendorName" id="supplierName" placeholder="Registered supplier name" required />
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label required-mark">Address</label>
                                        <input type="text" class="form-control" name="vendorAddress" placeholder="Complete business address" required />
                                    </div>
                                </div>
                            </div>

                            <div class="supplier-form-section">
                                <div class="section-heading">
                                    <span class="section-icon"><i class="ti ti-address-book f-20"></i></span>
                                    <div>
                                        <h5>Contact and Payment</h5>
                                    </div>
                                </div>

                                <div class="row g-3">
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label required-mark">Contact Person</label>
                                        <input type="text" class="form-control" name="contactPerson" placeholder="Full name" required />
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label required-mark">Designation</label>
                                        <input type="text" class="form-control" name="contactDesignation" placeholder="Position or title" required />
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label" for="contactNumber">Contact Number</label>
                                        <input type="text" class="form-control" name="contactNumber" id="contactNumber" placeholder="11-digit number" maxlength="11" />
                                    </div>
                                    <div class="col-lg-3 col-md-6">
                                        <label class="form-label" for="mobileNumber">Mobile Number</label>
                                        <input type="text" class="form-control" name="mobileNumber" id="mobileNumber" placeholder="11-digit number" maxlength="11" />
                                    </div>
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label">Email</label>
                                        <input type="email" class="form-control" name="email" placeholder="name@company.com" />
                                    </div>
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label required-mark" for="termsPayment">Terms of Payment</label>
                                        <select name="termsPayment" id="termsPayment" class="form-select" required>
                                            <option value="" selected hidden>Select terms</option>
                                            <option value="60 Days">60 Days</option>
                                            <option value="30 Days">30 Days</option>
                                            <option value="15 Days">15 Days</option>
                                            <option value="7 Days">7 Days</option>
                                            <option value="50% DP / 50% Upon Comp">50% DP / 50% Upon Comp.</option>
                                            <option value="Cash Basis">Cash Basis</option>
                                            <option value="COD (Cash on delivery)">COD (Cash on Delivery)</option>
                                        </select>
                                    </div>
                                    <div class="col-lg-4 col-md-6">
                                        <label class="form-label required-mark" for="vatStatus">VAT Status</label>
                                        <select name="vatStatus" id="vatStatus" class="form-select" required>
                                            <option value="" selected hidden>Select VAT status</option>
                                            <option value="With VAT">With VAT</option>
                                            <option value="Without VAT">Without VAT</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            <div class="supplier-form-section">
                                <div class="section-heading">
                                    <span class="section-icon"><i class="ti ti-file-certificate f-20"></i></span>
                                    <div>
                                        <h5>Compliance Documents</h5>
                                        <p>Upload PDFs required for the selected business type.</p>
                                    </div>
                                </div>

                                <div class="memo-upload-box mb-4">
                                    <div class="row g-3 align-items-end">
                                        <div class="col-lg-8">
                                            <label class="form-label" for="memorandumAssociation">Memorandum of Association</label>
                                            <input type="file"
                                                class="form-control"
                                                name="doc_memo"
                                                id="memorandumAssociation"
                                                accept="application/pdf" />
                                            <div class="supporting-text">PDF only, maximum 10 MB. Add an expiration date when a file is uploaded.</div>
                                        </div>
                                        <div class="col-lg-4">
                                            <label class="form-label" for="memorandumExpiration">Memorandum Expiration</label>
                                            <input type="date"
                                                class="form-control"
                                                name="memorandumExpiration"
                                                id="memorandumExpiration" />
                                        </div>
                                    </div>
                                </div>

                                <div class="document-summary">
                                    <span id="documentSummaryText">Select a business type to show required document uploads.</span>
                                    <span class="badge bg-light text-dark border" id="documentCountBadge">0 required</span>
                                </div>

                                <!-- Dynamic Upload Fields -->
                                <div id="documentFields" class="row g-3"></div>
                            </div>

                            <!-- Template for Upload Field -->
                            <template id="uploadFieldTemplate">
                                <div class="col-xl-3 col-lg-4 col-md-6 upload-item">
                                    <div class="upload-card">
                                        <label class="form-label doc-label"></label>
                                        <label class="btn btn-outline-primary w-100 upload-trigger">
                                            <i class="ti ti-upload me-2"></i> Upload <span class="doc-label-text"></span>
                                            <input type="file" class="d-none doc-input" accept="application/pdf" />
                                        </label>
                                        <span class="file-name">No file selected</span>
                                        <div class="supporting-text">PDF only, max 10 MB</div>

                                        <div class="spinner-border text-primary mt-3 d-none loading-spinner" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>

                                        <div class="mt-3 text-center d-none pdf-container">
                                            <img class="pdf-thumbnail" src="" alt="PDF Thumbnail" />
                                            <div class="supporting-text">Click preview to open viewer</div>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div class="form-actions">
                                <button class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" type="button" onclick="window.location.href='suppliers'">
                                    <i class="ti ti-x f-18"></i> Cancel
                                </button>
                                <button type="submit" id="submitSupplierBtn" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                                    <i class="ti ti-device-floppy f-18"></i> Submit Supplier
                                </button>
                            </div>

                            <div id="responseMessage" class="mt-3"></div>
                        </form>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="ti ti-file-type-pdf me-2"></i>PDF Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <canvas id="pdfCanvas"></canvas>
                <p class="text-muted mt-3 mb-0">Page <span id="pageNum">1</span> of <span id="pageCount">1</span></p>
            </div>
            <div class="modal-footer justify-content-between">
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" id="prevPage" type="button"><i class="ti ti-chevron-left me-1"></i>Previous</button>
                    <button class="btn btn-outline-secondary" id="nextPage" type="button">Next<i class="ti ti-chevron-right ms-1"></i></button>
                </div>
                <div class="btn-group">
                    <button class="btn btn-outline-secondary" id="zoomOut" type="button"><i class="ti ti-zoom-out"></i></button>
                    <button class="btn btn-outline-secondary" id="zoomIn" type="button"><i class="ti ti-zoom-in"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- PDF Viewer Modal -->
<!-- <div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3 d-flex justify-content-center align-items-center gap-2 flex-wrap">
                    <button class="btn btn-secondary" id="prevPage">Previous</button>
                    <span>Page <span id="pageNum">1</span> of <span id="pageCount">?</span></span>
                    <button class="btn btn-secondary" id="nextPage">Next</button>
                    <button class="btn btn-secondary" id="zoomOut">-</button>
                    <button class="btn btn-secondary" id="zoomIn">+</button>
                </div>
                <canvas id="pdfCanvas" style="max-width: 100%; border: 1px solid #ccc;"></canvas>
            </div>
        </div>
    </div>
</div> -->

<script>
    const appPublicBaseUrl = <?= json_encode($publicBaseUrl, JSON_UNESCAPED_SLASHES) ?>;

    function appPublicUrl(path) {
        return appPublicBaseUrl + String(path || '').replace(/^\/+/, '');
    }

    const supplierUploadKey = (
        window.crypto && typeof window.crypto.randomUUID === 'function'
            ? window.crypto.randomUUID().replace(/-/g, '')
            : String(Date.now()) + String(Math.random()).replace(/\D/g, '')
    ).slice(0, 32);

    // MOBILE NUMBER VALIDATION
    document.getElementById('mobileNumber').addEventListener('input', function(e) {
        // Remove non-digit characters
        this.value = this.value.replace(/\D/g, '').substring(0, 11);
    });
    // CONTACT NUMBER VALIDATION
    document.getElementById('contactNumber').addEventListener('input', function(e) {
        // Remove non-digit characters
        this.value = this.value.replace(/\D/g, '').substring(0, 11);
    });
    // TIN NUMBER VALIDATION
    document.getElementById('tinNumber').addEventListener('input', function(e) {
        let input = e.target.value.replace(/\D/g, ''); // Remove all non-digits
        input = input.substring(0, 13); // Limit to 13 digits only

        // Format: 000-000-000-0000
        let formatted = '';
        if (input.length > 0) formatted = input.substring(0, 3);
        if (input.length > 3) formatted += '-' + input.substring(3, 6);
        if (input.length > 6) formatted += '-' + input.substring(6, 9);
        if (input.length > 9) formatted += '-' + input.substring(9, 13);

        e.target.value = formatted;
    });

    function showSupplierFormError(title, text, focusElement = null) {
        Swal.fire({
            icon: 'error',
            title: title,
            text: text
        });

        if (focusElement) {
            focusElement.focus();
        }
    }

    function validateSupplierFormBeforeSubmit() {
        const tinInput = document.getElementById('tinNumber');
        const contactNumberInput = document.getElementById('contactNumber');
        const mobileNumberInput = document.getElementById('mobileNumber');
        const emailInput = document.querySelector('input[name="email"]');
        const businessTypeInput = document.getElementById('businessType');
        const memorandumInput = document.getElementById('memorandumAssociation');
        const memorandumExpirationInput = document.getElementById('memorandumExpiration');
        const selectedBusinessType = businessTypeInput.value;

        if (!/^\d{3}-\d{3}-\d{3}-\d{4}$/.test(tinInput.value)) {
            showSupplierFormError('Invalid TIN', 'TIN number must be in the format 000-000-000-0000.', tinInput);
            return false;
        }

        if (contactNumberInput.value && !/^\d{11}$/.test(contactNumberInput.value)) {
            showSupplierFormError('Invalid Contact Number', 'Contact number must contain exactly 11 digits.', contactNumberInput);
            return false;
        }

        if (mobileNumberInput.value && !/^\d{11}$/.test(mobileNumberInput.value)) {
            showSupplierFormError('Invalid Mobile Number', 'Mobile number must contain exactly 11 digits.', mobileNumberInput);
            return false;
        }

        if (emailInput.value && !emailInput.checkValidity()) {
            showSupplierFormError('Invalid Email', 'Please enter a valid email address.', emailInput);
            return false;
        }

        const hasMemorandumFile = memorandumInput.files && memorandumInput.files.length > 0;
        if (hasMemorandumFile) {
            const memorandumFile = memorandumInput.files[0];
            const memorandumExtension = memorandumFile.name.split('.').pop().toLowerCase();

            if (memorandumFile.type !== 'application/pdf' && memorandumExtension !== 'pdf') {
                showSupplierFormError('Invalid Memorandum', 'Memorandum of Association must be a PDF file.', memorandumInput);
                return false;
            }

            if (memorandumFile.size > 10485760) {
                showSupplierFormError('Invalid Memorandum', 'Memorandum of Association must be 10 MB or smaller.', memorandumInput);
                return false;
            }
        }

        if (hasMemorandumFile && !memorandumExpirationInput.value) {
            showSupplierFormError('Missing Expiration', 'Please enter the Memorandum of Association expiration date.', memorandumExpirationInput);
            return false;
        }

        if (!hasMemorandumFile && memorandumExpirationInput.value) {
            showSupplierFormError('Missing Memorandum', 'Please upload the Memorandum of Association PDF.', memorandumInput);
            return false;
        }

        if (!documentRequirements[selectedBusinessType]) {
            showSupplierFormError('Missing Business Type', 'Please select a valid business type.', businessTypeInput);
            return false;
        }

        for (const doc of documentRequirements[selectedBusinessType]) {
            const fieldName = documentUploadFieldMap[documentFieldMap[doc]];
            const input = document.querySelector(`input[name="${fieldName}"]`);
            if (!input || !input.files || input.files.length === 0) {
                showSupplierFormError('Missing Document', `Please upload ${doc}.`, input);
                return false;
            }
        }

        return true;
    }


    function selectedSupplierDocuments() {
        const documents = [];
        const memorandumInput = document.getElementById('memorandumAssociation');

        if (memorandumInput.files && memorandumInput.files.length > 0) {
            documents.push({
                field: 'memorandum_association',
                label: 'Memorandum of Association',
                file: memorandumInput.files[0]
            });
        }

        document.querySelectorAll('.doc-input').forEach((input) => {
            if (input.files && input.files.length > 0) {
                documents.push({
                    field: input.dataset.profileField || '',
                    label: input.dataset.docLabel || 'Document',
                    file: input.files[0]
                });
            }
        });

        return documents;
    }

    function arrayBufferToBase64(buffer) {
        const bytes = new Uint8Array(buffer);
        let binary = '';
        const batchSize = 0x8000;

        for (let i = 0; i < bytes.length; i += batchSize) {
            const batch = bytes.subarray(i, i + batchSize);
            binary += String.fromCharCode.apply(null, batch);
        }

        return btoa(binary);
    }

    function readBlobAsArrayBuffer(blob) {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            reader.onload = () => resolve(reader.result);
            reader.onerror = () => reject(new Error('Could not read the selected PDF.'));
            reader.readAsArrayBuffer(blob);
        });
    }

    async function uploadSupplierDocument(documentInfo, index, total) {
        const chunkSize = 96 * 1024;
        const totalChunks = Math.ceil(documentInfo.file.size / chunkSize);
        let response = null;

        for (let chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
            const start = chunkIndex * chunkSize;
            const end = Math.min(start + chunkSize, documentInfo.file.size);
            const buffer = await readBlobAsArrayBuffer(documentInfo.file.slice(start, end));

            $('#submitSupplierBtn').html(
                `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Uploading ${index + 1}/${total}: ${documentInfo.label} (${chunkIndex + 1}/${totalChunks})`
            );

            response = await $.ajax({
                type: 'POST',
                url: appPublicUrl('document_chunk.php'),
                data: {
                    supplier_upload_key: supplierUploadKey,
                    document_field: documentInfo.field,
                    original_name: documentInfo.file.name,
                    chunk_index: chunkIndex,
                    total_chunks: totalChunks,
                    file_size: documentInfo.file.size,
                    chunk_data: arrayBufferToBase64(buffer)
                },
                dataType: 'json'
            });

            if (!response || (response.status !== 'success' && response.status !== 'partial')) {
                throw new Error(response && response.message ? response.message : `Could not upload ${documentInfo.label}.`);
            }
        }

        return response;
    }

    $('#vendorForm').submit(async function(e) {
        e.preventDefault(); // Prevent normal form submission

        const tinInput = document.getElementById('tinNumber');

        // ✅ Validate TIN length (must be 16 characters including hyphens)
        if (tinInput.value.length !== 16) {
            Swal.fire({
                icon: 'error',
                title: 'Invalid TIN',
                text: 'TIN number must be in the format 000-000-000-0000'
            });
            tinInput.focus();
            return; // Stop execution, prevent AJAX submission
        }

        if (!validateSupplierFormBeforeSubmit()) {
            return;
        }

        const submitButton = $('#submitSupplierBtn');

        submitButton.prop('disabled', true).html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Submitting...');

        try {
            const documents = selectedSupplierDocuments();
            for (let i = 0; i < documents.length; i++) {
                const uploadResponse = await uploadSupplierDocument(documents[i], i, documents.length);
                if (!uploadResponse || uploadResponse.status !== 'success') {
                    throw new Error(uploadResponse && uploadResponse.message ? uploadResponse.message : `Could not upload ${documents[i].label}.`);
                }
            }

            submitButton.html('<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Saving supplier...');
            const formData = $(this).serializeArray();
            formData.push({ name: 'supplier_upload_key', value: supplierUploadKey });

            const response = await $.ajax({
                type: 'POST',
                url: appPublicUrl('supplier_submit.php'),
                data: $.param(formData),
                dataType: 'json'
            });

            if (!response || response.status !== 'success') {
                throw new Error(response && response.message ? response.message : 'Something went wrong.');
            }

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: response.message,
                timer: 2000,
                showConfirmButton: false
            });

            $('#vendorForm')[0].reset();
            $('#productCategory').val(null).trigger('change');
            $('#documentFields').empty();
            updateDocumentSummary();
        } catch (requestError) {
            const xhr = requestError && requestError.status ? requestError : null;
            const responseText = xhr ? String(xhr.responseText || '') : '';
            const responseJson = xhr && xhr.responseJSON ? xhr.responseJSON : null;
            const isForbidden = xhr && (xhr.status === 403 || (responseText.includes('<h1') && responseText.includes('403')));

            console.error('Supplier submission failed:', requestError);

            Swal.fire({
                icon: 'error',
                title: isForbidden ? 'Hosting Blocked an Upload' : 'Supplier Not Saved',
                text: isForbidden
                    ? 'The hosting firewall blocked one of the document upload chunks. Try a smaller PDF; if it still fails, ask hosting support to whitelist document_chunk.php.'
                    : (responseJson && responseJson.message ? responseJson.message : (requestError.message || 'An error occurred. Please try again.'))
            });
        } finally {
            submitButton.prop('disabled', false).html('<i class="ti ti-device-floppy f-18"></i> Submit Supplier');
        }
    });


    // $('#vendorForm').submit(function(e) {
    //     e.preventDefault(); // Prevent normal form submission

    //     const tinInput = document.getElementById('tinNumber');
    //     if (tinInput.value.length !== 16) {
    //         e.preventDefault();
    //         alert('TIN number must be in the format 000-000-000-0000 (16 characters including hyphens).');
    //         tinInput.focus();
    //     }

    //     const form = $('#vendorForm')[0];
    //     const formData = new FormData(form); // Use FormData to include file

    //     $.ajax({
    //         type: 'POST',
    //         url: 'data/submit_supplier.php',
    //         data: formData,
    //         processData: false, // Don't process the data
    //         contentType: false, // Don't set content type (jQuery will use multipart/form-data)
    //         dataType: 'json',
    //         success: function(response) {
    //             $('#responseMessage').html(
    //                 `<div class="alert alert-${response.status}">${response.message}</div>`
    //             );
    //             if (response.status === 'success') {
    //                 $('#vendorForm')[0].reset();
    //                 $('#pdfPreview').hide(); // Hide PDF preview if any
    //             }
    //         },
    //         error: function() {
    //             $('#responseMessage').html(
    //                 `<div class="alert alert-danger">An error occurred. Please try again.</div>`
    //             );
    //         }
    //     });
    // });

    // $('#businessType').select2({
    //     theme: 'bootstrap-5',
    //     dropdownParent: $('#businessType').parent(),
    //     ajax: {
    //         url: 'data/select_business_type.php',
    //         type: 'POST',
    //         dataType: 'json',
    //         delay: 250,
    //         data: function(params) {
    //             return {
    //                 search: params.term
    //             };
    //         },
    //         processResults: function(data) {
    //             return {
    //                 results: data
    //             };
    //         },
    //         cache: true
    //     }
    // });

    $('#productCategory').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#productCategory').parent(),
        ajax: {
            url: appPublicUrl('data/select_supplier_category.php'),
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {
                return {
                    search: params.term
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            },
            cache: true
        }
    });
</script>


<script>
    // const documentRequirements = {
    //     "Small Business": ["DTI", "Brgy. Permit", "BIR"],
    //     "Sole proprietorship": ["DTI", "Brgy. Permit", "BIR or 2303", "Mayor’s Permit"],
    //     "Micro": ["Brgy. Permit", "Mayor’s permit", "BIR"],
    //     "Corporate": [
    //         "Company Profile", "List of Products and Services", "Business Permit",
    //         "Certificate of Registration (2303)", "Audited Financial Statement",
    //         "Organizational Chart", "Certificate (Authority to Sell / Distributor)",
    //         "GIS (General Information Sheet)", "Mayor's Permit", "SEC certificate"
    //     ]
    // };

    // const documentFieldMap = {
    //     "DTI": "dti",
    //     "Brgy. Permit": "brgy_permit",
    //     "BIR": "bir_cert",
    //     "BIR or 2303": "bir_cert",
    //     "Mayor’s Permit": "mayors_permit",
    //     "Mayor’s permit": "mayors_permit",
    //     "Company Profile": "company_profile",
    //     "List of Products and Services": "product_services",
    //     "Business Permit": "business_permit",
    //     "Certificate of Registration (2303)": "bir_cert",
    //     "Audited Financial Statement": "fin_statement",
    //     "Organizational Chart": "org_chart",
    //     "Certificate (Authority to Sell / Distributor)": "authorize_distributor",
    //     "GIS (General Information Sheet)": "gen_info",
    //     "Mayor's Permit": "mayors_permit",
    //     "SEC certificate": "sec_cert"
    // };

    const documentRequirements = {
    "Small Business": ["DTI", "Brgy. Permit", "Certificate of Registration (2303)"],
    "Sole Proprietorship": ["DTI", "Brgy. Permit", "Certificate of Registration (2303)", "Mayor's Permit"],
    "Micro": ["Brgy. Permit", "Mayor's Permit", "Certificate of Registration (2303)"],
    "Corporate": [
        "Company Profile", "List of Products and Services", "Business Permit",
        "Certificate of Registration (2303)", "Audited Financial Statement",
        "Organizational Chart", "Certificate (Authority to Sell / Distributor)",
        "GIS (General Information Sheet)", "Mayor's Permit", "SEC Certificate"
    ]
};

const documentFieldMap = {
    "DTI": "dti",
    "Brgy. Permit": "brgy_permit",
    "Mayor's Permit": "mayors_permit",
    "Company Profile": "company_profile",
    "List of Products and Services": "product_services",
    "Business Permit": "business_permit",
    "Certificate of Registration (2303)": "bir_cert",
    "Audited Financial Statement": "fin_statement",
    "Organizational Chart": "org_chart",
    "Certificate (Authority to Sell / Distributor)": "authorize_distributor",
    "GIS (General Information Sheet)": "gen_info",
    "SEC Certificate": "sec_cert"
};

const documentUploadFieldMap = {
    company_profile: "doc_company_profile",
    product_services: "doc_product_services",
    business_permit: "doc_business_permit",
    bir_cert: "doc_bir",
    fin_statement: "doc_financial",
    org_chart: "doc_org_chart",
    authorize_distributor: "doc_auth_dist",
    gen_info: "doc_gis",
    mayors_permit: "doc_mayors",
    sec_cert: "doc_sec",
    brgy_permit: "doc_brgy",
    dti: "doc_dti"
};


    const businessTypeSelect = document.getElementById("businessType");
    const documentFields = document.getElementById("documentFields");
    const template = document.getElementById("uploadFieldTemplate").content;
    const documentSummaryText = document.getElementById("documentSummaryText");
    const documentCountBadge = document.getElementById("documentCountBadge");

    // PDF.js globals
    let pdfDoc = null;
    let currentPage = 1;
    let scale = 1.5;
    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas.getContext('2d');
    const pageNumSpan = document.getElementById('pageNum');
    const pageCountSpan = document.getElementById('pageCount');

    function updateDocumentSummary(selectedType = "") {
        const docs = documentRequirements[selectedType] || [];
        documentCountBadge.textContent = `${docs.length} required`;

        if (!selectedType) {
            documentSummaryText.textContent = "Select a business type to show required document uploads.";
            return;
        }

        documentSummaryText.textContent = docs.length ?
            `${selectedType} suppliers require ${docs.length} document upload${docs.length === 1 ? "" : "s"}.` :
            "No document requirements found for the selected business type.";
    }

    businessTypeSelect.addEventListener("change", () => {
        const selected = businessTypeSelect.value;
        documentFields.innerHTML = "";
        updateDocumentSummary(selected);

        if (documentRequirements[selected]) {
            documentRequirements[selected].forEach((doc) => {
                const dbFieldName = documentFieldMap[doc];
                const clone = document.importNode(template, true);
                const uploadCard = clone.querySelector(".upload-card");
                const label = clone.querySelector(".doc-label");
                const span = clone.querySelector(".doc-label-text");
                const input = clone.querySelector(".doc-input");
                const fileName = clone.querySelector(".file-name");
                const spinner = clone.querySelector(".loading-spinner");
                const container = clone.querySelector(".pdf-container");
                const thumbnail = clone.querySelector(".pdf-thumbnail");
                let itemFileURL = '';

                label.textContent = doc;
                span.textContent = doc;
                input.name = documentUploadFieldMap[dbFieldName] || dbFieldName;
                input.dataset.profileField = dbFieldName;
                input.dataset.docLabel = doc;

                input.addEventListener('change', (e) => {
                    const file = e.target.files[0];
                    const allowedTypes = ['application/pdf'];

                    if (!file) {
                        spinner.classList.add('d-none');
                        container.classList.add('d-none');
                        thumbnail.src = "";
                        fileName.textContent = "No file selected";
                        uploadCard.classList.remove('is-uploaded');
                        if (itemFileURL) {
                            URL.revokeObjectURL(itemFileURL);
                            itemFileURL = '';
                        }
                        return;
                    }

                    const fileExtension = file.name.split('.').pop().toLowerCase();
                    if (!file || (!allowedTypes.includes(file.type) && fileExtension !== 'pdf')) {
                        alert("Please upload a valid PDF file.");
                        e.target.value = "";
                        spinner.classList.add('d-none');
                        container.classList.add('d-none');
                        fileName.textContent = "No file selected";
                        uploadCard.classList.remove('is-uploaded');
                        return;
                    }

                    if (file.size > 10485760) {
                        alert("PDF file must be 10 MB or smaller.");
                        e.target.value = "";
                        spinner.classList.add('d-none');
                        container.classList.add('d-none');
                        fileName.textContent = "No file selected";
                        uploadCard.classList.remove('is-uploaded');
                        return;
                    }

                    if (itemFileURL) {
                        URL.revokeObjectURL(itemFileURL);
                    }

                    itemFileURL = URL.createObjectURL(file);
                    fileName.textContent = file.name;
                    spinner.classList.remove('d-none');
                    container.classList.add('d-none');

                    pdfjsLib.getDocument(itemFileURL).promise.then(pdf => {
                        pdf.getPage(1).then(page => {
                            const tempCanvas = document.createElement('canvas');
                            const tempCtx = tempCanvas.getContext('2d');
                            const viewport = page.getViewport({
                                scale: 0.5
                            });

                            tempCanvas.width = viewport.width;
                            tempCanvas.height = viewport.height;

                            page.render({
                                canvasContext: tempCtx,
                                viewport: viewport
                            }).promise.then(() => {
                                thumbnail.src = tempCanvas.toDataURL();
                                spinner.classList.add('d-none');
                                container.classList.remove('d-none');
                                uploadCard.classList.add('is-uploaded');
                            });
                        });
                    }).catch(err => {
                        console.error("PDF load error:", err);
                        alert("Failed to load PDF.");
                        spinner.classList.add('d-none');
                        uploadCard.classList.remove('is-uploaded');
                    });
                });

                container.addEventListener('click', () => {
                    if (!itemFileURL) return;
                    pdfjsLib.getDocument(itemFileURL).promise.then(pdf => {
                        pdfDoc = pdf;
                        currentPage = 1;
                        scale = 1.5;
                        renderPage(currentPage);
                        const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
                        modal.show();
                    });
                });

                documentFields.appendChild(clone);
            });
        }
    });

    // Viewer controls
    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.width = viewport.width;
            canvas.height = viewport.height;

            page.render({
                canvasContext: ctx,
                viewport: viewport
            });

            pageNumSpan.textContent = num;
            pageCountSpan.textContent = pdfDoc.numPages;
        });
    }

    document.getElementById('zoomIn').addEventListener('click', () => {
        scale += 0.2;
        renderPage(currentPage);
    });
    document.getElementById('zoomOut').addEventListener('click', () => {
        if (scale > 0.4) {
            scale -= 0.2;
            renderPage(currentPage);
        }
    });
    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderPage(currentPage);
        }
    });
    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage < pdfDoc.numPages) {
            currentPage++;
            renderPage(currentPage);
        }
    });
</script>




<?php
include 'footer.php';
?>
