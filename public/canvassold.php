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

<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="assets/css/select2.min.css" rel="stylesheet" />
<script src="assets/js/select2.min.js"></script>
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
                                    <div class="row">
                                        <?php for ($i = 1; $i <= 3; $i++): ?>
                                            <div class="col-md-4">
                                                <div class="card mb-4">
                                                    <div class="card-body supplierForm" data-form-id="<?= $i ?>">
                                                        <h6 class="fw-bold text-primary mb-2">SUPPLIER <?= $i ?></h6>

                                                        <div class="no-print">

                                                            <div class="d-flex justify-content-around align-items-center gap-2">
                                                                <!-- Dropdown -->
                                                                <select name="suppliers[<?= $i ?>][supplier_id]" class="form-select supplierSelect flex-grow-1" required style="min-width: 200px; max-width: 325px;">
                                                                    <option selected disabled value="">Select Supplier</option>
                                                                </select>

                                                                <!-- Button -->
                                                                <button type="button" class="btn btn-light-primary btn-sm rounded-pill addItemBtn px-3">
                                                                    Add Item
                                                                </button>
                                                            </div>
                                                            <div class="supplierDescription text-muted small"></div>
                                                            <div class="supplierContact text-muted small"></div>
                                                            <div class="supplierTerms text-muted small mb-2"></div>
                                                            <div class="itemSelectArea d-none mt-2 no-print">
                                                                <select class="form-select form-select-sm availableItemSelect mb-2"></select>
                                                                <button type="button" class="btn btn-sm btn-success confirmAddBtn">Add</button>
                                                                <button type="button" class="btn btn-sm btn-secondary cancelAddBtn">Cancel</button>
                                                            </div>
                                                        </div>
                                                        <!-- Selected Items List -->
                                                        <ul class="list-group list-group-flush selectedItems mb-2 rounded border"></ul>
                                                        <!-- Total -->
                                                        <div class="text-end fw-semibold text-dark">
                                                            Total: ₱<span class="totalAmount">0.00</span>
                                                        </div>
                                                        <div class="text-center text-dark" hidden>
                                                            <input type="checkbox" name="recommendedSupplier" id="recommendedSupplier">
                                                            <label for="" class="text-muted form-label"><span class="recommendedSupplier"><i>Recommended Supplier</i></span></label>
                                                        </div>
                                                        <div class="col-md-12 mt-2 pdfUploadArea" style="display:none;">
                                                            <label>Upload Proposal (PDF Only)</label>
                                                            <input type="file" class="form-control pdfInput"
                                                                name="suppliers[<?= $i ?>][po_file]"
                                                                accept="application/pdf">

                                                            <!-- PDF Preview -->
                                                            <div class="loadingSpinner" style="display:none; text-align:center; margin:15px 0;">
                                                                <div class="spinner-border text-primary" role="status"></div>
                                                                <p>Loading preview...</p>
                                                            </div>
                                                            <div class="pdfContainer" style="display:none; cursor:pointer; text-align:center;">
                                                                <img class="pdfThumbnail" src="" alt="PDF Thumbnail"
                                                                    style="max-width:100%; border:1px solid #ccc; width:100px;">
                                                            </div>
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
                                    <div class="col-auto">
                                        <select class="form-select" id="prNumber" name="pr_number" data-placeholder="Select PR Number" required style="min-width: 200px; max-width: 325px;"></select>
                                    </div>
                                    <?php if ($_SESSION['user_id'] == 84): ?>
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
                                    <div class="col-auto" style="display:none;">
                                        <input type="checkbox" name="renovationRelated" id="renovationRelated">
                                        <label for="" class="form-label text-muted"><i>Construction / Renovation-Related (1M Above)</i></label>
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

<script>
    $(document).ready(function() {
        $('.supplierForm').each(function() {
            const $form = $(this);
            const formId = $form.data('form-id');

            let availableItems = [];
            let selectedItems = [];
            let supplierData = [];

            const $supplierSelect = $form.find('.supplierSelect');
            const $availableItemSelect = $form.find('.availableItemSelect');
            const $itemSelectArea = $form.find('.itemSelectArea');
            const $selectedItems = $form.find('.selectedItems');
            const $totalAmount = $form.find('.totalAmount');

            const $supplierDescription = $form.find('.supplierDescription');
            const $supplierContact = $form.find('.supplierContact');
            const $supplierTerms = $form.find('.supplierTerms');

            // function rebuildItemDropdown() {
            //     $availableItemSelect.empty();
            //     const filtered = availableItems.filter(item => !selectedItems.includes(item.item_id));
            //     if (filtered.length === 0) {
            //         $itemSelectArea.addClass('d-none');
            //         return;
            //     }

            //     filtered.forEach(item => {
            //         const label = `${item.item_name} - ₱${item.unit_price}`;
            //         $availableItemSelect.append(`<option value="${item.item_id}">${label}</option>`);
            //     });

            //     $itemSelectArea.removeClass('d-none');
            // }
            function rebuildItemDropdown() {
                $availableItemSelect.empty();
                const filtered = availableItems.filter(item => !selectedItems.includes(item.item_id));

                if (filtered.length === 0) {
                    $itemSelectArea.addClass('d-none');
                    return;
                }

                filtered.forEach(item => {
                    const label = `${item.item_name} - ₱${item.unit_price}`;
                    $availableItemSelect.append(`<option value="${item.item_id}">${label}</option>`);
                });

                $itemSelectArea.removeClass('d-none');

                // Initialize or refresh Select2
                $availableItemSelect.select2({
                    placeholder: "Search items...",
                    allowClear: true,
                    width: '100%'
                });
            }

            // function updateTotalAmount() {
            //     let total = 0;
            //     $selectedItems.find('li').each(function() {
            //         const price = parseFloat($(this).attr('data-price'));
            //         const qty = parseInt($(this).attr('data-qty'));
            //         total += price * qty;
            //     });
            //     $totalAmount.text(total.toFixed(2));
            // }
            
            function updateTotalAmount() {
                let total = 0;
                $selectedItems.find('li').each(function() {
                    const price = parseFloat($(this).attr('data-price'));
                    const qty = parseInt($(this).attr('data-qty'));
                    total += price * qty;
                });

                // Update the total display
                $totalAmount.text(total.toFixed(2));

                // Toggle the checkbox visibility
                if (total >= 1000000) {
                    $("#renovationRelated").closest(".col-auto").show(); // show parent div
                } else {
                    $("#renovationRelated").closest(".col-auto").hide(); // hide parent div
                    $("#renovationRelated").prop("checked", false); // uncheck when hidden (optional)
                }
            }

            // Load supplier data
            $.getJSON('data/get_suppliers.php', function(data) {
                supplierData = data;
                $supplierSelect.find('option:not(:first)').remove();

                data.forEach(supplier => {
                    $supplierSelect.append(`<option value="${supplier.supplier_id}">${supplier.supplier_name}</option>`);
                });

                $supplierSelect.select2({
                    placeholder: "Select Supplier",
                    width: 'resolve'
                });
            });

            // On supplier change
            $supplierSelect.on('change', function() {
                const supplierId = $(this).val();
                const supplier = supplierData.find(s => s.supplier_id == supplierId);

                $supplierDescription.text('CONTACT PERSON: ' + (supplier.contact_person || 'No description'));
                $supplierContact.text('CONTACT NUMBER: ' + (supplier.contact_number || 'N/A') + ' | ' + (supplier.mobile_number || 'N/A'));
                $supplierTerms.text('TERMS OF PAYMENT: ' + (supplier.payment_terms || 'N/A'));

                selectedItems = [];
                availableItems = [];
                $selectedItems.empty();
                $itemSelectArea.addClass('d-none');

                $.getJSON('data/get_items.php?supplier_id=' + supplierId, function(data) {
                    availableItems = data;
                });
            });

            // Add item
            $form.find('.addItemBtn').click(function(e) {
                e.preventDefault();
                if (availableItems.length === 0 || selectedItems.length >= 4) return;
                rebuildItemDropdown();
            });

            // Confirm item selection
            $form.find('.confirmAddBtn').click(function() {
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
                <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between px-2 py-2 gap-2"
                    data-id="${selectedItem.item_id}" data-price="${selectedItem.unit_price}" data-qty="1">
                    <input type="hidden" name="suppliers[${formId}][supplier_id]" value="${supplierId}" />
                    <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][item_id]" value="${selectedItem.item_id}" />
                    <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][qty]" value="1" class="item-qty-hidden" />
                    <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][price]" value="${selectedItem.unit_price}" />
                    <input type="hidden" name="suppliers[${formId}][items][${itemIndex}][unit_measure]" value="${selectedItem.unit_measure}" />
                    
                    <div class="me-3 d-flex align-items-start gap-3 flex-grow-1" style="min-width: 250px;">
                        <div>
                            <a href="#" class="image-lightbox" data-bs-toggle="modal" data-bs-target="#imageModal" data-img="${imageSrc}">
                                <img src="${imageSrc}" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" />
                            </a>
                        </div>
                        <div style="flex: 1;">
                            <strong>${selectedItem.item_name}</strong><br />
                            <small class="text-muted">${shortDesc}${needsToggle ? `<a href="#" class="toggle-desc ms-1">View More</a>` : ''}</small><br />
                            <small class="text-muted">₱${parseFloat(selectedItem.unit_price).toFixed(2)} per ${selectedItem.unit_measure}</small>
                        </div>
                        <div class="d-flex flex-column align-items-end text-end">
                            <div class="d-flex align-items-center gap-1">
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-minus px-2"><i class="fas fa-circle-minus"></i></button>
                                <input type="number" min="1" value="1" 
                                    class="form-control form-control-sm item-qty-input text-center" 
                                    style="width: 60px;" />
                                <button type="button" class="btn btn-sm btn-outline-primary btn-add px-2"><i class="fas fa-circle-plus"></i></button>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove mt-1 px-2"><i class="fas fa-trash-alt"></i></button>
                            <small class="text-muted d-block">Subtotal: ₱<span class="item-subtotal">${parseFloat(selectedItem.unit_price).toFixed(2)}</span></small>
                        </div>
                    </div>
                </li>
            `);
            
             if ($selectedItems.find('li').length > 0) {
                    $form.find('.pdfUploadArea').show();
                }

                updateTotalAmount();
                $itemSelectArea.addClass('d-none');
                rebuildItemDropdown();
            });

            // Cancel adding item
            $form.find('.cancelAddBtn').click(function() {
                $itemSelectArea.addClass('d-none');
            });

            // Remove item
            $selectedItems.on('click', '.btn-remove', function() {
                const listItem = $(this).closest('li');
                const itemId = listItem.data('id');
                selectedItems = selectedItems.filter(id => id != itemId);
                listItem.remove();
                updateTotalAmount();
                rebuildItemDropdown();

                $(this).closest('li').remove();

                // ✅ Hide PDF upload if no items left
                // If no items left, hide PDF area and clear file input
                if ($selectedItems.find('li').length === 0) {
                    const $pdfArea = $form.find('.pdfUploadArea');
                    const $pdfInput = $pdfArea.find('.pdfInput');

                    // Hide
                    $pdfArea.hide();

                    // Remove required
                    $pdfInput.prop('required', false);

                    // ✅ Clear file input
                    $pdfInput.val('');

                    // Also hide preview if shown
                    $pdfArea.find('.pdfContainer').hide();
                    $pdfArea.find('.pdfThumbnail').attr('src', '');
                }
            });

            // Increase qty


            $selectedItems.on('click', '.btn-add', function() {
                const listItem = $(this).closest('li');
                let $input = listItem.find('.item-qty-input');
                let qty = parseInt($input.val()) + 1;
                const price = parseFloat(listItem.attr('data-price'));

                $input.val(qty);
                listItem.attr('data-qty', qty);
                listItem.find('.item-subtotal').text((qty * price).toFixed(2));
                listItem.find('.item-qty-hidden').val(qty);
                updateTotalAmount();
            });

            // Decrease qty
            $selectedItems.on('click', '.btn-minus', function() {
                const listItem = $(this).closest('li');
                let $input = listItem.find('.item-qty-input');
                let qty = parseInt($input.val());
                if (qty > 1) {
                    qty--;
                    const price = parseFloat(listItem.attr('data-price'));
                    $input.val(qty);
                    listItem.attr('data-qty', qty);
                    listItem.find('.item-subtotal').text((qty * price).toFixed(2));
                    listItem.find('.item-qty-hidden').val(qty);
                    updateTotalAmount();
                }
            });

            $selectedItems.on('input', '.item-qty-input', function() {
                const listItem = $(this).closest('li');
                let qty = parseInt($(this).val()) || 1;
                if (qty < 1) qty = 1; // minimum safeguard
                const price = parseFloat(listItem.attr('data-price'));
                
                $(this).val(qty);
                listItem.attr('data-qty', qty);
                listItem.find('.item-subtotal').text((qty * price).toFixed(2));
                listItem.find('.item-qty-hidden').val(qty);
                updateTotalAmount();
            });

            // Sortable list (optional)
            $selectedItems.sortable({
                update: function() {
                    selectedItems = $selectedItems.find('li').map(function() {
                        return $(this).data('id');
                    }).get();
                }
            });
        });

        // Save All Button
        $('#saveAllBtn').on('click', function() {
            const button = $(this);

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
                            button.prop('disabled', false).text('Save All');
                        }
                    });
                }
            });
        });


        $('#prNumber').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#prNumber').parent(),
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
                },
                cache: true
            }
        });

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