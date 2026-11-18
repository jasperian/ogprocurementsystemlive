<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';

$dealer = $_SESSION['dealer'];
$user_id = $_SESSION['user_id'];
$department = $_SESSION['department'];
$generalmanager_userid = null;
$purchasingmanager_userid = null;

// Get General Manager Approver
$stmt = $pdo->prepare("
    SELECT user_id 
    FROM users 
    WHERE role = 'Approver' 
      AND position = 'General Manager' 
      AND dealer = :dealer
    LIMIT 1
");
$stmt->execute([':dealer' => $dealer]);
$gm = $stmt->fetch(PDO::FETCH_ASSOC);

if ($gm) {
    $generalmanager_userid = $gm['user_id'];
}


// Get Noter in the same department (Purchasing)
$stmt = $pdo->prepare("
    SELECT user_id 
    FROM users 
    WHERE role = 'Noter' 
      AND department = :department 
      AND dealer = :dealer
    LIMIT 1
");
$stmt->execute([
    ':department' => $department,
    ':dealer' => $dealer
]);
$pm = $stmt->fetch(PDO::FETCH_ASSOC);

if ($pm) {
    $purchasingmanager_userid = $pm['user_id'];
}
?>


<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<!-- Select2 CSS -->
<!-- Select2 CSS -->


<!-- Select2 Bootstrap 5 Theme -->
<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<!-- jQuery (must load BEFORE Select2) -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script> -->
<!-- <link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" /> -->
<!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script> -->

<link href="assets/css/select2.min.css" rel="stylesheet" />
<script src="assets/js/select2.min.js"></script>


<!-- Select2 CSS -->
<!-- <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" /> -->

<!-- jQuery (already included in your code) -->
<!-- Select2 JS -->
<!-- <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" /> -->

<style>
    .select2-container--default .select2-selection--single {
        height: 38px;
        padding: 6px 12px;
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
    }

    @media print {
        .no-print {
            display: none !important;
        }

        nav {
            display: none;
            /* This hides the navigation entirely when printing */
        }

    }

    @page {
        size: A3 landscape;
        /* Suggest A4 in portrait */
        /* You can also use landscape, or specific dimensions like 21cm 29.7cm */
        margin: 1cm;
    }

    .desc-full,
    .desc-short {
        display: block;
        transition: all 0.3s ease;
    }

    .list-group-item {
        border-radius: 10px;
    }

    .list-group-item:hover {
        background: #f9fafb;
    }

    .item-qty-input::-webkit-inner-spin-button,
    .item-discount-input::-webkit-inner-spin-button {
        display: none;
    }

    .supplier-card {
        border: none;
        border-radius: 14px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
        transition: transform .2s ease, box-shadow .2s ease;
    }

    .supplier-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
    }

    .selectedItems li {
        font-size: 0.9rem;
    }

    .itemSelectArea {
        border: 1px dashed #ced4da;
    }

    .totalAmount {
        letter-spacing: 0.3px;
    }

    .item-pill {
        border-radius: 10px;
        border: 1px solid #e5e7eb;
        padding: 8px 10px;
        background: #fff;
    }

    .item-pill+.item-pill {
        margin-top: 6px;
    }

    .pill-icon {
        cursor: grab;
    }

    .pill-item {
        background: #f1f5f9;
        border-radius: 8px;
        padding: 6px 10px;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .pill-input {
        width: 70px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .pill-btn {
        border-radius: 8px;
        padding: 6px 8px;
    }

    .item-pill:hover {
        background: #f9fafb;
    }

    .item-card {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 12px;
        background: #fff;
    }

    .item-card+.item-card {
        margin-top: 8px;
    }

    .item-title {
        font-size: 0.95rem;
        font-weight: 500;
        color: #111827;
    }

    .item-thumb {
        width: 46px;
        height: 46px;
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        background: #f8f9fa;
    }

    .item-thumb img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .discount-input {
        width: 60px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .item-input {
        width: 80px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .item-btn {
        border-radius: 8px;
        padding: 6px 10px;
    }

    .item-card:hover {
        background: #f9fafb;
    }

    .item-row-structured {
        border-radius: 12px;
        border: 1px solid #e5e7eb;
        padding: 12px;
        background: #fff;
    }

    .item-row-structured+.item-row-structured {
        margin-top: 8px;
    }

    .item-image {
        width: 70px;
        height: 70px;
        border-radius: 10px;
        overflow: hidden;
        border: 1px solid #e5e7eb;
        background: #f8f9fa;
        flex-shrink: 0;
    }

    .item-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .item-name {
        font-size: 0.95rem;
        font-weight: 600;
        color: #111827;
    }

    .item-desc {
        font-size: 0.85rem;
        line-height: 1.3;
    }

    .control-input {
        width: 80px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .discount-input {
        width: 60px;
        border-radius: 8px;
        font-size: 0.85rem;
    }

    .control-btn {
        border-radius: 8px;
        padding: 6px 10px;
    }

    .item-row-structured:hover {
        background: #f9fafb;
    }

    .input-group-text {
        font-weight: 500;
    }

    .btn-remove {
        z-index: 5;
    }

    @media (hover: hover) {
        .btn-remove {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .position-relative:hover .btn-remove {
            opacity: 1;
        }
    }

    .item-name {
        font-weight: 600;
    }

    .item-desc {
        font-size: 0.85rem;
    }

    .input-group-text {
        background-color: #f8f9fa;
    }
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">

    <div class="pc-content">
        <div col-md-12>
            <div d-flex justify-content-between align-items-center>


            </div>
        </div>
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">

                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <img src="assets/images/<?php echo $_SESSION['dealer']; ?>.png" class="img-fluid logo-md" alt="logo" style="width: 250px;" />
                            </div>
                            <div class="page-header-title">
                                <h2 class="mb-0">Canvass Sheet</h2>
                            </div>
                            <div>
                                <img src="assets/images/logo.png" class="img-fluid logo-md" alt="logo" style="width: 150px;" />
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="btn-page text-end no-print mt-3 mr-5">
                        <button onclick="window.print()" class="btn btn-primary no-print">Print This Page</button>
                    </div>
                    <form id="allSuppliersForm" action="save_all_suppliers.php" method="post">
                        <input type="text" name="generalmanager_userid" id="generalmanager_userid" value="<?php echo $generalmanager_userid; ?>" hidden />
                        <input type="text" name="purchasingmanager_userid" id="purchasingmanager_userid" value="<?php echo $purchasingmanager_userid; ?>" hidden />

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row g-4">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <div class="col-md-4">
                                                <div class="card supplier-card h-100">
                                                    <div class="card-body supplierForm" data-form-id="<?= $i ?>">

                                                        <!-- Header -->
                                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                                            <h6 class="fw-semibold text-primary mb-0">
                                                                Supplier <?= $i ?>
                                                            </h6>
                                                        </div>

                                                        <!-- Supplier Select -->
                                                        <div class="no-print">
                                                            <div class="d-flex gap-2 align-items-center">
                                                                <select name="suppliers[<?= $i ?>][supplier_id]"
                                                                    class="form-select supplierSelect"
                                                                    required>
                                                                    <option selected disabled value="">Select Supplier</option>
                                                                </select>

                                                                <button type="button"
                                                                    class="btn btn-primary btn-sm rounded-pill addItemBtn px-3">
                                                                    ADD
                                                                </button>
                                                            </div>

                                                            <!-- Supplier Info -->
                                                            <div class="mt-2 small text-muted">
                                                                <div class="supplierDescription"></div>
                                                                <div class="supplierContact"></div>
                                                                <div class="supplierTerms"></div>
                                                            </div>

                                                            <!-- Item Picker -->
                                                            <div class="itemSelectArea d-none mt-3 p-2 rounded bg-light">
                                                                <select class="form-select form-select-sm availableItemSelect mb-3"></select>
                                                                <div class="d-flex gap-2 mt-2">
                                                                    <button type="button" class="btn btn-sm btn-success confirmAddBtn w-100">
                                                                        Add Item
                                                                    </button>
                                                                    <button type="button" class="btn btn-sm btn-outline-secondary cancelAddBtn w-100">
                                                                        Cancel
                                                                    </button>
                                                                </div>
                                                            </div>
                                                        </div>

                                                        <!-- Items -->
                                                        <ul class="list-group list-group-flush selectedItems my-3 border rounded"></ul>

                                                        <!-- Total -->
                                                        <div class="d-flex justify-content-between align-items-center border-top pt-2">
                                                            <span class="text-muted small">Total</span>
                                                            <span class="fw-bold fs-5 text-dark">
                                                                ₱<span class="totalAmount">0.00</span>
                                                            </span>
                                                        </div>

                                                        <!-- PDF Upload -->
                                                        <div class="mt-3 pdfUploadArea" style="display:none;">
                                                            <label class="form-label small fw-semibold">Proposal (PDF)</label>
                                                            <input type="file"
                                                                class="form-control form-control-sm pdfInput"
                                                                name="suppliers[<?= $i ?>][po_file]"
                                                                accept="application/pdf">

                                                            <div class="loadingSpinner text-center my-2" style="display:none;">
                                                                <div class="spinner-border spinner-border-sm text-primary"></div>
                                                                <div class="small text-muted">Loading preview…</div>
                                                            </div>

                                                            <div class="pdfContainer text-center mt-2" style="display:none;">
                                                                <img class="pdfThumbnail rounded shadow-sm"
                                                                    style="width:90px;">
                                                                <div class="small text-muted mt-1">Click to preview</div>
                                                            </div>
                                                        </div>

                                                        <!-- Discount -->
                                                        <div class="mt-3 pdfUploadArea" style="display:none;">
                                                            <label class="form-label small fw-semibold">Discounted Total</label>
                                                            <input type="text"
                                                                class="form-control form-control-sm discountedPrice"
                                                                readonly>
                                                            <small class="text-success supplier-savings d-none"></small>
                                                        </div>

                                                    </div>
                                                </div>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="mb-3 mb-0"><label class="form-label">Recommendations and Suggestions:</label><textarea class="form-control" rows="3" name="notes" placeholder="Recommendations and Suggestions:"></textarea></div>
                                </div>
                                <div class="row align-items-end gx-2"> <!-- gx-2 adds horizontal spacing -->
                                    <div class="row g-3 align-items-end">
                                        <div class="col-md-3 col-sm-4">
                                            <label for="prNumber" class="form-label fw-semibold">PR Number</label>
                                            <select class="form-select" id="prNumber" name="pr_number"
                                                data-placeholder="Select PR Number" required>
                                            </select>
                                        </div>

                                        <div class="col-md-3 col-sm-4">
                                            <input type="hidden" name="select_budgetcode_text" id="select_budgetcode_text">
                                            <label for="selectBudgetCode" class="form-label fw-semibold">Budget Code</label>
                                            <select class="form-select" id="selectBudgetCode" name="select_budgetcode"
                                                data-placeholder="Select Budget Code" required>
                                            </select>
                                        </div>

                                        <div class="col-md-3 col-sm-4">
                                            <label for="displayRemaining" class="form-label fw-semibold">Remaining Budget</label>
                                            <input type="text" id="displayRemaining" class="form-control mt-2" readonly>
                                        </div>



                                        <input type="hidden" id="hiddenBudgetCode">
                                        <input type="hidden" id="hiddenRemaining">

                                        <?php if ($_SESSION['user_id'] == 20): ?>
                                            <div class="col-md-3 col-sm-4">
                                                <label for="selectDepartment" class="form-label fw-semibold">Checker</label>
                                                <select class="form-select" id="selectDepartmentAffiliate" name="assigned_department"
                                                    data-placeholder="Select Department" required>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <div class="col-md-3 col-sm-4">
                                                <label for="selectDepartment" class="form-label fw-semibold">Checker</label>
                                                <select class="form-select" id="selectDepartment" name="assigned_department"
                                                    data-placeholder="Select Department" required>
                                                </select>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-auto" style="display:none;">
                                        <input type="checkbox" name="renovationRelated" id="renovationRelated">
                                        <label for="" class="form-label text-muted"><i>Construction / Renovation-Related</i></label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="row text-end g-3">

                                        <div class="btn-page text-end no-print mt-3 mr-5">
                                            <button type="button" id="saveAllBtn" class="btn btn-primary"><i class="ti ti-plus f-18"></i>Create Canvass</button>
                                        </div>

                                    </div>
                                </div>
                                <div class="col-12" hidden>
                                    <div class="row justify-content-center my-5 gap-5">
                                        <!-- Signatories -->
                                        <?php
                                        $signatories = ['Checked By (Hide Details)', 'Noted By', 'Approved By'];
                                        foreach ($signatories as $label):
                                        ?>
                                            <div class="text-center" style="max-width: 300px;">
                                                <p class="mb-1 fw-semibold"><?= $label ?>:</p>
                                                <div style="border-bottom: 1px solid #000; height: 20px;"></div>
                                                <p class="mb-1 small text-muted">Name</p>
                                                <p class="small text-muted">Position</p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>


                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>

        <!-- Include Choices.js CSS -->
        <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
        <style>
            .choices__inner {
                display: block;
                width: 100%;
                padding: 0.375rem 2.25rem 0.375rem 0.75rem;
                font-size: 1rem;
                font-weight: 400;
                line-height: 1.5;
                color: #212529;
                background-color: #fff;
                background-clip: padding-box;
                border: 1px solid #ced4da;
                appearance: none;
                border-radius: 0.375rem;
                transition: border-color .15s ease-in-out, box-shadow .15s ease-in-out;
            }
        </style>

        <select id="mySelect">
            <option value="" selected="false">Select an option</option>
            <option value="option1">Option 1</option>
            <option value="option2">Option 2</option>
            <option value="option3">Option 3</option>
        </select> -->

        <!-- Include Choices.js JavaScript -->
        <!-- <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
        <script>
            // Initialize Choices.js on the select element
            const mySelectElement = document.getElementById('mySelect');
            const choices = new Choices(mySelectElement, {
                searchEnabled: true, // Enable search functionality
                removeItemButton: true, // Show remove button for selected items (for multiple select)
                // Add other options as needed
            });
        </script> -->


        <!-- [ sample-page ] end -->
        <!-- [ Main Content ] end -->

    </div>
</div>


<!-- Image Lightbox Modal -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img id="modalPreviewImage" src="" class="w-100" alt="Product Image">
            </div>
        </div>
    </div>
</div>
<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Preview</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <canvas id="pdfCanvas"></canvas>
                <div class="mt-3">
                    <button id="prevPage" class="btn btn-sm btn-secondary">Previous</button>
                    <button id="nextPage" class="btn btn-sm btn-secondary">Next</button>
                    <span>Page <span id="pageNum">1</span> of <span id="pageCount">?</span></span>
                </div>
                <div class="mt-2">
                    <button id="zoomIn" class="btn btn-sm btn-primary">Zoom In</button>
                    <button id="zoomOut" class="btn btn-sm btn-primary">Zoom Out</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="cust-modal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="mb-0">Product Details</h5>
                <a href="#" class="avtar avtar-s btn-link-danger btn-pc-default ms-auto" data-bs-dismiss="modal">
                    <i class="ti ti-x f-20"></i>
                </a>
            </div>
            <div class="modal-body">
                <div class="row align-items-center">
                    <!-- Product Image -->
                    <div class="col-sm-4">
                        <div class="bg-light rounded position-relative">
                            <div class="text-center">
                                <div class="chat-avtar d-inline-flex mx-auto">
                                    <img class="img-fluid rounded product-image" alt="Product image" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="col-sm-8">

                        <h5 class="product-title"></h5>
                        <p class="text-muted product-description"></p>
                        <table class="table w-auto table-borderless">
                            <tbody class="product-details"></tbody>
                        </table>
                        <p class="text-muted product-measure"></p>
                    </div>
                    <!-- Update Price Button -->

                </div>
            </div>
        </div>
    </div>
</div>
</div>
<script>
    $(document).ready(function() {
        let supplierData = [];

        // Load suppliers once
        $.getJSON('data/get_suppliers.php', function(data) {
            supplierData = data;

            // Initialize each supplier form
            $('.supplierForm').each(function() {
                const $form = $(this);
                const formId = $form.data('form-id');

                let availableItems = [];
                let selectedItems = [];

                const $supplierSelect = $form.find('.supplierSelect');
                const $availableItemSelect = $form.find('.availableItemSelect');
                const $itemSelectArea = $form.find('.itemSelectArea');
                const $selectedItems = $form.find('.selectedItems');
                const $totalAmount = $form.find('.totalAmount');

                const $supplierDescription = $form.find('.supplierDescription');
                const $supplierContact = $form.find('.supplierContact');
                const $supplierTerms = $form.find('.supplierTerms');

                // Populate supplier select
                $supplierSelect.find('option:not(:first)').remove();
                supplierData.forEach(supplier => {
                    $supplierSelect.append(
                        `<option value="${supplier.supplier_id}">${supplier.supplier_name}</option>`
                    );
                });

                if ($supplierSelect.hasClass('select2-hidden-accessible')) {
                    $supplierSelect.select2('destroy');
                }
                $supplierSelect.select2({
                    placeholder: 'Select Supplier',
                    width: '100%'
                });

                // Supplier change
                $supplierSelect.on('change', function() {
                    const supplierId = $(this).val();
                    const supplier = supplierData.find(s => s.supplier_id == supplierId);

                    $supplierDescription.text('CONTACT PERSON: ' + (supplier.contact_person || 'No description'));
                    $supplierContact.text('CONTACT NUMBER: ' + (supplier.contact_number || 'N/A') + ' | ' + (supplier.mobile_number || 'N/A'));
                    $supplierTerms.text('TERMS OF PAYMENT: ' + (supplier.payment_terms || 'N/A'));

                    // Reset items
                    selectedItems = [];
                    availableItems = [];
                    $selectedItems.empty();
                    $itemSelectArea.addClass('d-none');

                    // Load items for this supplier
                    $.getJSON('data/get_items.php?supplier_id=' + supplierId, function(data) {
                        availableItems = data;
                    });
                });

                // Rebuild item dropdown
                function rebuildItemDropdown() {
                    $availableItemSelect.empty();
                    const filtered = availableItems.filter(item => !selectedItems.includes(item.item_id));

                    if (!filtered.length) {
                        $itemSelectArea.addClass('d-none');
                        return;
                    }

                    filtered.forEach(item => {
                        $availableItemSelect.append(
                            `<option value="${item.item_id}">${item.item_name} - ₱${item.unit_price}</option>`
                        );
                    });

                    $itemSelectArea.removeClass('d-none');

                    if ($availableItemSelect.hasClass("select2-hidden-accessible")) {
                        $availableItemSelect.select2('destroy');
                    }
                    $availableItemSelect.select2({
                        placeholder: "Search items...",
                        width: '100%'
                    });
                }

                // Show item dropdown
                $form.find('.addItemBtn').on('click', function(e) {
                    e.preventDefault();
                    if (availableItems.length === 0 || selectedItems.length >= 4) return;
                    rebuildItemDropdown();
                });

                // Confirm adding item
                $form.find('.confirmAddBtn').on('click', function() {
                    const selectedId = $availableItemSelect.val();
                    const selectedItem = availableItems.find(item => item.item_id == selectedId);
                    if (!selectedItem || selectedItems.includes(selectedItem.item_id)) return;

                    const itemIndex = selectedItems.length;
                    selectedItems.push(selectedItem.item_id);

                    const supplierId = $supplierSelect.val();
                    const imageSrc = selectedItem.product_image && selectedItem.product_image.trim() !== '' ?
                        `data/${selectedItem.product_image}` :
                        'data/uploads/items/default.jpeg';

                    const maxDescLength = 80;
                    const fullDesc = selectedItem.description;
                    const shortDesc = fullDesc.length > maxDescLength ? fullDesc.slice(0, maxDescLength) + '...' : fullDesc;
                    const needsToggle = fullDesc.length > maxDescLength;

                    $selectedItems.append(`
                    <li class="list-group-item item-row"
                        data-id="${selectedItem.item_id}"
                        data-price="${selectedItem.unit_price}"
                        data-qty="1">

                        <!-- Hidden Inputs -->
                        <input type="hidden" name="suppliers[${formId}][supplier_id]" value="${supplierId}" />
                        <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][item_id]" value="${selectedItem.item_id}" />
                        <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][qty]" value="1" class="item-qty-hidden" />
                        <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][price]" value="${selectedItem.unit_price}" />
                        <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][unit_measure]" value="${selectedItem.unit_measure}" />
                        <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][changed_price]" value="${selectedItem.unit_price}" class="item-changeprice-hidden" />


                        <div class="d-flex gap-3 align-items-start position-relative">
                                <button type="button"
                                        class="btn btn-sm btn-outline-danger btn-remove position-absolute top-0 end-0">
                                    <i class="fas fa-trash"></i>
                                </button>
                                <!-- LEFT: Image -->
                                <a href="#" class="image-lightbox" data-bs-toggle="modal" data-bs-target="#imageModal" data-img="${imageSrc}"> <img src="${imageSrc}" class="img-thumbnail" style="width:70px;height:70px;object-fit:cover;"> </a>

                                <!-- RIGHT: Content -->
                                <div class="flex-grow-1">

                                    <!-- Title -->
                                    <div class="item-name">
                                        ${selectedItem.item_name}
                                    </div>

                                    <!-- Description -->
                                    <div class="item-desc text-muted">
                                        (${selectedItem.unit_measure})-${shortDesc}
                                    </div>
                                    <button type="button"
                                            class="btn btn-sm btn-link p-0 mt-1 text-primary btn-view-details"
                                            data-bs-toggle="modal"
                                            data-bs-target="#cust-modal"
                                            data-title="${selectedItem.item_name}"
                                            data-desc="${selectedItem.description}"
                                            data-image="${imageSrc}"
                                            data-measure="${selectedItem.unit_measure}"
                                            data-details='${JSON.stringify(selectedItem.details)}'>
                                        View details
                                    </button>
                                </div>
                            </div>
                           <div class="d-flex align-items-center gap-2 mt-2 flex-wrap">
                            <!-- Qty -->
                            <div class="input-group input-group-sm" style="width:90px;">
                                <span class="input-group-text">Qty</span>
                                <input type="number"
                                    value="1"
                                    class="form-control text-center item-qty-input"
                                    data-index="${itemIndex}"
                                    style="appearance: textfield;">
                            </div>
                            <!-- Price -->
                            <div class="input-group input-group-sm" style="width:140px;">
                                <span class="input-group-text">₱</span>
                                <input type="text"
                                    class="form-control text-end"
                                    value="${parseFloat(selectedItem.unit_price).toFixed(2)}"
                                    readonly>
                            </div>
                            <!-- Change Price -->
                            <div class="input-group input-group-sm" style="width:140px;">
                                <span class="input-group-text">New ₱</span>
                                <input type="number"
                                    step="0.01"
                                    value="${parseFloat(selectedItem.unit_price).toFixed(2)}"
                                    class="form-control text-end item-changeprice-input"
                                    data-index="${itemIndex}">
                            </div>
                        </div>
                    </li>
                `);
                    // QTY - Allow empty while typing
                    $(document).on('input', '.item-qty-input', function() {
                        const $row = $(this).closest('.item-row');
                        const value = $(this).val();

                        if (value === '') {
                            $row.find('.item-qty-hidden').val('');
                            return;
                        }

                        const qty = parseInt(value);

                        if (!isNaN(qty) && qty > 0) {
                            $row.find('.item-qty-hidden').val(qty);
                            $row.attr('data-qty', qty);
                        }

                        updateTotalAmount();
                    });

                    // QTY - Validate on blur
                    $(document).on('blur', '.item-qty-input', function() {
                        const $row = $(this).closest('.item-row');
                        let value = parseInt($(this).val());

                        if (!value || value < 1) {
                            value = 1;
                            $(this).val(1);
                        }

                        $row.find('.item-qty-hidden').val(value);
                        $row.attr('data-qty', value);

                        updateTotalAmount();
                    });

                    // CHANGE PRICE
                    $(document).on('input', '.item-changeprice-input', function() {
                        let value = parseFloat($(this).val());

                        if (isNaN(value) || value < 0) value = 0;

                        const $row = $(this).closest('.item-row');

                        // Update hidden field
                        $row.find('.item-changeprice-hidden').val(value);

                        updateTotalAmount();
                    });

                    $(document).on('blur', '.item-changeprice-input', function() {
                        let value = parseFloat($(this).val());
                        if (isNaN(value) || value < 0) value = parseFloat($(this).closest('.item-row').data('price'));
                        $(this).val(value.toFixed(2));
                        $(this).closest('.item-row').find('.item-changeprice-hidden').val(value);
                        updateTotalAmount();
                    });

                    if ($selectedItems.find('li').length > 0) {
                        $form.find('.pdfUploadArea').show();
                    }

                    updateTotalAmount();
                    $itemSelectArea.addClass('d-none');
                    rebuildItemDropdown();
                });


                // Cancel add item
                $form.find('.cancelAddBtn').on('click', function() {
                    $itemSelectArea.addClass('d-none');
                });

                // Recalculate item
                function recalcItem(listItem) {
                    const price = parseFloat(listItem.attr('data-price'));
                    const qty = parseInt(listItem.find('.item-qty-input').val()) || 1;
                    let changedPrice = parseFloat(listItem.find('.item-changeprice-input').val());
                    if (isNaN(changedPrice) || changedPrice < 0) changedPrice = price;

                    const subtotal = changedPrice * qty;

                    listItem.find('.item-subtotal').text(subtotal.toFixed(2));
                    listItem.find('.item-qty-hidden').val(qty);
                    listItem.attr('data-qty', qty);

                    updateTotalAmount();
                }

                // Remove item
                $selectedItems.on('click', '.btn-remove', function() {
                    const listItem = $(this).closest('li');
                    const itemId = listItem.data('id');

                    selectedItems = selectedItems.filter(id => id != itemId);
                    listItem.remove();

                    updateTotalAmount();
                    rebuildItemDropdown();

                    if ($selectedItems.find('li').length === 0) {
                        const $pdfArea = $form.find('.pdfUploadArea');
                        $pdfArea.hide();
                        $pdfArea.find('input.pdfInput').prop('required', false).val('');
                    }
                });

                // Quantity controls
                $selectedItems.on('click', '.btn-add', function() {
                    const listItem = $(this).closest('li');
                    let $input = listItem.find('.item-qty-input');
                    $input.val(parseInt($input.val()) + 1);
                    recalcItem(listItem);
                });
                $selectedItems.on('click', '.btn-minus', function() {
                    const listItem = $(this).closest('li');
                    let $input = listItem.find('.item-qty-input');
                    let qty = parseInt($input.val());
                    if (qty > 1) {
                        $input.val(qty - 1);
                        recalcItem(listItem);
                    }
                });
                $selectedItems.on('input', '.item-qty-input', function() {
                    const listItem = $(this).closest('li');
                    let qty = parseInt($(this).val()) || 1;
                    if (qty < 1) qty = 1;
                    $(this).val(qty);
                    recalcItem(listItem);
                });
                $selectedItems.on('input', '.item-changeprice-input', function() {
                    const listItem = $(this).closest('li');
                    recalcItem(listItem);
                });

                // Update total for this form
                function updateTotalAmount() {
                    let originalTotal = 0;
                    let discountedTotal = 0;

                    $selectedItems.find('li').each(function() {
                        const price = parseFloat($(this).data('price'));
                        const qty = parseInt($(this).find('.item-qty-input').val()) || 1;
                        let changedPrice = parseFloat($(this).find('.item-changeprice-input').val());
                        if (isNaN(changedPrice) || changedPrice < 0) changedPrice = price;

                        const itemOriginal = price * qty;
                        const itemDiscounted = changedPrice * qty;

                        originalTotal += itemOriginal;
                        discountedTotal += itemDiscounted;
                    });

                    $totalAmount.text(discountedTotal.toFixed(2));
                    $form.find('.discountedPrice').val(discountedTotal.toFixed(2));

                    const savings = originalTotal - discountedTotal;
                    const $savingsText = $form.find('.supplier-savings');

                    if (savings > 0) {
                        $savingsText.removeClass('d-none').text(`You saved ₱${savings.toFixed(2)}`);
                    } else {
                        $savingsText.addClass('d-none').text('');
                    }

                    // Recheck the budget whenever supplier totals change.
                    checkBudgetLimit();
                }

                // Sortable (optional)
                $selectedItems.sortable({
                    update: function() {
                        selectedItems = $selectedItems.find('li').map(function() {
                            return $(this).data('id');
                        }).get();
                    }
                });

            }); // End supplierForm each
        }); // End getJSON suppliers
    });

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.btn-view-details');
        if (!btn) return;

        document.querySelector('#cust-modal .product-title')
            .textContent = btn.dataset.title;

        document.querySelector('#cust-modal .product-description')
            .textContent = btn.dataset.desc;

        document.querySelector('#cust-modal .product-image')
            .src = btn.dataset.image;

        document.querySelector('#cust-modal .product-measure')
            .textContent = btn.dataset.measure;

        const tbody = document.querySelector('#cust-modal .product-details');
        tbody.innerHTML = '';

        const details = JSON.parse(btn.dataset.details || '{}');

        Object.entries(details).forEach(([label, value]) => {
            tbody.innerHTML += `
            <tr>
                <td class="fw-semibold">${label}</td>
                <td>${value}</td>
            </tr>
        `;
        });
    });

    // Save All Button
    $('#saveAllBtn').on('click', function() {
        const button = $(this);
        
        // Validation for required fields
        const prNumber = $('#prNumber').val();
        const budgetCode = $('#selectBudgetCode').val();
        // const select_budgetcode_text = $('#select_budgetcode_text').val();
        const remainingBudget = $('#displayRemaining').val();
        const checker = $('#selectDepartment').val() || $('#selectDepartmentAffiliate').val();

        if (!prNumber || !budgetCode || !remainingBudget || !checker) {
            Swal.fire({
                icon: 'warning',
                title: 'Required Fields Missing',
                text: 'Please select PR Number, Budget Code, and Checker. Remaining Budget will be filled once Budget Code is selected.'
            });
            return;
        }

        // Final budget validation before submission.
        // Submission is allowed as long as at least one supplier with items
        // has a total less than or equal to the remaining budget.
        const remainingBudgetValue = parseFloat($('#hiddenRemaining').val()) || 0;
        let hasSupplierWithinBudget = false;

        $('.supplierForm').each(function() {
            const itemCount = $(this).find('.selectedItems li').length;
            const supplierTotal = parseFloat(
                $(this).find('.totalAmount').text().replace(/,/g, '')
            ) || 0;

            if (itemCount > 0 && supplierTotal <= remainingBudgetValue) {
                hasSupplierWithinBudget = true;
                return false; // Stop looping once a valid supplier is found.
            }
        });

        if (!hasSupplierWithinBudget) {
            Swal.fire({
                icon: 'error',
                title: 'Budget Limit Exceeded',
                text: 'At least one supplier must have a total within the remaining budget.'
            });
            return;
        }

        Swal.fire({
            title: 'Are you sure?',
            text: 'This will save the canvass sheet.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Yes, save it!',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33'
        }).then((result) => {
            if (result.isConfirmed) {
                const form = $('#allSuppliersForm')[0];
                const formData = new FormData(form);

                // Ensure required fields are added
                formData.append('pr_number', $('#prNumber').val());
                formData.append('dealer', $('#dealer').val());
                formData.append('select_budgetcode_text', $('#select_budgetcode_text').val());
                formData.append('generalmanager_userid', $('#generalmanager_userid').val());
                formData.append('purchasingmanager_userid', $('#purchasingmanager_userid').val());

                button.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: 'save_all_suppliers.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        try {
                            const res = typeof response === 'string' ? JSON.parse(response) : response;

                            if (res.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Saved!',
                                    text: res.message || 'Canvass sheet created.',
                                    timer: 1000,
                                    showConfirmButton: false
                                }).then(() => {
                                    // Redirect after success alert closes
                                    window.location.href = 'canvass_view?pr_number=' + encodeURIComponent($('#prNumber').val());
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.message || 'Something went wrong.'
                                });
                                console.error(res);
                            }
                        } catch (e) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Invalid Response',
                                text: 'Unexpected response from server.'
                            });
                            console.error('Parse error:', e, 'Response:', response);
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'AJAX Error',
                            text: 'Error saving data: ' + error
                        });
                        console.error(xhr.responseText);
                    },
                    complete: function() {
                        button.text('Create Canvass');
                    checkBudgetLimit();
                    }
                });
            }
        });
    });


    let selectedDepartment = '';

    $('#prNumber').select2({
        theme: 'bootstrap-5',
        ajax: {
            url: 'data/select_purchase_requisition.php',
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
            }
        }
    }).on('select2:select', function(e) {

        const data = e.params.data;

        // ✅ store department
        selectedDepartment = data.department || '';

        console.log('Selected Department:', selectedDepartment);

        // Enable budget dropdown
        $('#selectBudgetCode').prop('disabled', false);

        // Reset budget dropdown
        $('#selectBudgetCode').val(null).trigger('change');
    });

    // Budget Code dropdown
    $('#selectBudgetCode').select2({
        theme: 'bootstrap-5',
        ajax: {
            url: 'data/get_budget_code.php',
            type: 'POST',
            dataType: 'json',
            delay: 250,
            data: function(params) {

                return {
                    search: params.term || '',
                    department: selectedDepartment // send department
                };
            },
            processResults: function(data) {
                return {
                    results: data
                };
            }
        }
    });
    
    $('#selectBudgetCode').on('select2:select', function (e) {
        var data = e.params.data;
        $('#select_budgetcode_text').val(data.text);
    });

    // Allow submission when at least one supplier with items
    // is within the selected budget code's remaining balance.
    function checkBudgetLimit() {
        const remainingBudget = parseFloat($('#hiddenRemaining').val());

        let hasSupplierItems = false;
        let hasSupplierWithinBudget = false;

        $('.supplierForm').each(function() {
            const $supplierForm = $(this);
            const itemCount = $supplierForm.find('.selectedItems li').length;
            const supplierTotal = parseFloat(
                $supplierForm.find('.totalAmount').text().replace(/,/g, '')
            ) || 0;

            // Ignore supplier cards without selected items.
            if (itemCount === 0) {
                $supplierForm.find('.totalAmount')
                    .removeClass('text-danger text-success');
                return;
            }

            hasSupplierItems = true;

            if (!isNaN(remainingBudget) && supplierTotal <= remainingBudget) {
                hasSupplierWithinBudget = true;

                $supplierForm.find('.totalAmount')
                    .addClass('text-success')
                    .removeClass('text-danger');
            } else {
                $supplierForm.find('.totalAmount')
                    .addClass('text-danger')
                    .removeClass('text-success');
            }
        });

        const canSubmit =
            hasSupplierItems &&
            hasSupplierWithinBudget &&
            !isNaN(remainingBudget);

        $('#saveAllBtn')
            .prop('disabled', !canSubmit)
            .attr(
                'title',
                canSubmit
                    ? ''
                    : 'At least one supplier total must be within the remaining budget.'
            );

        if (hasSupplierItems && !hasSupplierWithinBudget) {
            $('#displayRemaining').addClass('is-invalid');
        } else {
            $('#displayRemaining').removeClass('is-invalid');
        }
    }

    // Handle budget selection
    $('#selectBudgetCode').on('select2:select', function(e) {
        let data = e.params.data;

        let budgetCode = data.budget_code;
        let remaining = parseFloat(data.remaining) || 0;

        // Store in hidden inputs
        $('#hiddenBudgetCode').val(budgetCode);
        $('#hiddenRemaining').val(remaining);

        // Display remaining nicely
        $('#displayRemaining').val("₱" + remaining.toLocaleString('en-PH', {
            minimumFractionDigits: 2
        }));

        console.log("Selected Budget Code:", budgetCode);
        console.log("Remaining Budget:", remaining);

        // Check if current total exceeds remaining budget
        checkBudgetLimit();
    });


    // ✅ Initially disable the Budget Code dropdown
    $('#selectBudgetCode').prop('disabled', true);

    $('#selectDepartment').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#selectDepartment').parent(),
        ajax: {
            url: 'data/get_select_department.php',
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

    $('#selectDepartmentAffiliate').select2({
        theme: 'bootstrap-5',
        dropdownParent: $('#selectDepartmentAffiliate').parent(),
        ajax: {
            url: 'data/get_select_department_affiliate.php',
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

    // Image modal functionality
    $(document).on('click', '.image-lightbox', function(e) {
        e.preventDefault();
        const imgSrc = $(this).data('img');
        $('#modalPreviewImage').attr('src', imgSrc);
    });

    // Toggle description functionality
    $(document).on('click', '.toggle-desc', function(e) {
        e.preventDefault();
        const $desc = $(this).closest('.description');
        $desc.find('.desc-short, .desc-full').toggleClass('d-none');
        const isExpanded = $(this).text() === 'View Less';
        $(this).text(isExpanded ? 'View More' : 'View Less');
    });


    //Checkbox
    const checkboxes = document.querySelectorAll('input[type="checkbox"][name="recommendedSupplier"]');

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            if (this.checked) {
                checkboxes.forEach(cb => {
                    if (cb !== this) cb.checked = false;
                });
            }
        });
    });
</script>
<script>
    document.querySelectorAll('.supplierForm').forEach((form, index) => {
        const pdfInput = form.querySelector('.pdfInput');
        const thumbnail = form.querySelector('.pdfThumbnail');
        const container = form.querySelector('.pdfContainer');
        const spinner = form.querySelector('.loadingSpinner');

        let fileURL = '';
        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.5;

        function renderPage(num, canvas, ctx, pageNumSpan, pageCountSpan) {
            pdfDoc.getPage(num).then(page => {
                const viewport = page.getViewport({
                    scale
                });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                page.render({
                    canvasContext: ctx,
                    viewport
                });

                // Update counters
                pageNumSpan.textContent = num;
                pageCountSpan.textContent = pdfDoc.numPages;
            });
        }

        // File upload handler
        pdfInput.addEventListener('change', e => {
            const file = e.target.files[0];
            if (!file || file.type !== "application/pdf") {
                alert("Please upload a valid PDF file.");
                spinner.style.display = "none";
                container.style.display = "none";
                return;
            }

            fileURL = URL.createObjectURL(file);
            spinner.style.display = "block";
            container.style.display = "none";

            pdfjsLib.getDocument(fileURL).promise.then(pdf => {
                pdfDoc = pdf;
                pdf.getPage(1).then(page => {
                    const viewport = page.getViewport({
                        scale: 0.5
                    });
                    const tempCanvas = document.createElement('canvas');
                    const tempCtx = tempCanvas.getContext('2d');
                    tempCanvas.height = viewport.height;
                    tempCanvas.width = viewport.width;

                    page.render({
                        canvasContext: tempCtx,
                        viewport
                    }).promise.then(() => {
                        thumbnail.src = tempCanvas.toDataURL();
                        spinner.style.display = "none";
                        container.style.display = "block";
                    });
                });
            }).catch(error => {
                console.error("Error loading PDF:", error);
                alert("Failed to load PDF.");
                spinner.style.display = "none";
            });
        });

        // Open modal with PDF when thumbnail is clicked
        container.addEventListener('click', () => {
            if (!fileURL) return;

            pdfjsLib.getDocument(fileURL).promise.then(pdf => {
                pdfDoc = pdf;
                currentPage = 1;
                scale = 1.5;

                // Inject a unique modal for each supplier
                const modalId = `pdfModal_${index}`;
                let modalEl = document.getElementById(modalId);
                if (!modalEl) {
                    document.body.insertAdjacentHTML('beforeend', `
                    <div class="modal fade" id="${modalId}" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-xl modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">PDF Preview - Supplier ${index + 1}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <canvas id="pdfCanvas_${index}"></canvas>
                                    <div class="mt-3">
                                        <button class="btn btn-sm btn-secondary prevPage">Previous</button>
                                        <button class="btn btn-sm btn-secondary nextPage">Next</button>
                                        <span>Page <span class="pageNum">1</span> of <span class="pageCount">?</span></span>
                                    </div>
                                    <div class="mt-2">
                                        <button class="btn btn-sm btn-primary zoomIn">Zoom In</button>
                                        <button class="btn btn-sm btn-primary zoomOut">Zoom Out</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `);
                    modalEl = document.getElementById(modalId);
                }

                const canvas = modalEl.querySelector(`#pdfCanvas_${index}`);
                const ctx = canvas.getContext('2d');
                const pageNumSpan = modalEl.querySelector('.pageNum');
                const pageCountSpan = modalEl.querySelector('.pageCount');

                renderPage(currentPage, canvas, ctx, pageNumSpan, pageCountSpan);

                const modal = new bootstrap.Modal(modalEl);
                modal.show();

                // Controls
                modalEl.querySelector('.zoomIn').onclick = () => {
                    scale += 0.2;
                    renderPage(currentPage, canvas, ctx, pageNumSpan, pageCountSpan);
                };
                modalEl.querySelector('.zoomOut').onclick = () => {
                    if (scale > 0.4) {
                        scale -= 0.2;
                        renderPage(currentPage, canvas, ctx, pageNumSpan, pageCountSpan);
                    }
                };
                modalEl.querySelector('.prevPage').onclick = () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPage(currentPage, canvas, ctx, pageNumSpan, pageCountSpan);
                    }
                };
                modalEl.querySelector('.nextPage').onclick = () => {
                    if (currentPage < pdfDoc.numPages) {
                        currentPage++;
                        renderPage(currentPage, canvas, ctx, pageNumSpan, pageCountSpan);
                    }
                };
            });
        });
    });
</script>


<?php
include 'footer.php';
?>