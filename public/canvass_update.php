<?php
require '../init.php';
include 'header.php';
include 'navigation.php';

$dealer = $_SESSION['dealer'];
$user_id = $_SESSION['user_id'];
$department = $_SESSION['department'];
$generalmanager_userid = null;
$purchasingmanager_userid = null;


$pr_number = $_GET['pr_number'] ?? '';
if (!$pr_number) {
    die('PR Number missing.');
}

$pr_department = $_GET['dept'] ?? '';
if (!$pr_number) {
    die('Department missing.');
}


// Get batch info
$stmt = $pdo->prepare("SELECT * FROM canvass_sheet_batches WHERE pr_number = ?");
$stmt->execute([$pr_number]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die('PR Number not found.');
}

$batch_id = $batch['batch_id'];
$notes = $batch['notes'] ?? '';
// Get all orders in this batch with item and supplier info
$stmt = $pdo->prepare("
    SELECT 
        po.*,
        s.supplier_name,
        s.contact_person,
        s.contact_number,
        s.payment_terms,
        i.item_name,
        i.description,
        i.product_image,
        i.unit_measure
    FROM canvass_items po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN items i ON po.item_id = i.item_id
    WHERE po.batch_id = ?
    ORDER BY s.supplier_name
");
$stmt->execute([$batch_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by supplier
$suppliers = [];
foreach ($orders as $order) {
    $sid = $order['supplier_id'];
    if (!isset($suppliers[$sid])) {
        $suppliers[$sid] = [
            'supplier_name' => $order['supplier_name'],
            'contact_person' => $order['contact_person'],
            'contact_number' => $order['contact_number'],
            'payment_terms' => $order['payment_terms'],
            'items' => []
        ];
    }
    $suppliers[$sid]['items'][] = $order;
}

?>


<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- jQuery (already included in your code) -->
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>


<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

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

                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-12">
                                    <div class="row">
                                        <?php foreach (array_values($suppliers) as $index => $data): ?>
                                            <?php
                                            $sid = array_keys($suppliers)[$index]; // current supplier_id
                                            $total = 0;
                                            foreach ($data['items'] as $item) {
                                                $total += $item['qty'] * $item['unit_price'];
                                            }
                                            ?>

                                            <div class="col-md-4 col-lg-4 mb-3">
                                                <div class="card shadow-sm rounded-4 supplierForm" data-form-id="<?= $index ?>">
                                                    <div class="card-body">

                                                        <!-- Supplier Select -->
                                                        <label class="fw-semibold text-primary small">Supplier <?= $index + 1 ?></label>
                                                        <select class="form-select supplierSelect"
                                                            name="suppliers[<?= $index ?>][supplier_id]"
                                                            data-selected="<?= $sid ?>">
                                                            <option value="">-- Select Supplier --</option>
                                                        </select>

                                                        <!-- Supplier Info -->
                                                        <div class="supplierDescription">
                                                            CONTACT PERSON: <?= !empty($data['contact_person']) ? htmlspecialchars($data['contact_person']) : '-' ?>
                                                        </div>
                                                        <div class="supplierContact">
                                                            CONTACT NUMBER: <?= !empty($data['contact_number']) ? htmlspecialchars($data['contact_number']) : '-' ?>
                                                        </div>
                                                        <div class="supplierTerms">
                                                            TERMS OF PAYMENT: <?= !empty($data['payment_terms']) ? htmlspecialchars($data['payment_terms']) : '-' ?>
                                                        </div>



                                                        <!-- Item List -->
                                                        <ul class="list-group list-group-flush selectedItems mb-2 rounded border mt-3">
                                                            <?php foreach ($data['items'] as $iIndex => $item): ?>
                                                                <?php
                                                                $subtotal = $item['qty'] * $item['unit_price'];
                                                                $imagePath = 'data/' . (!empty($item['product_image']) ? $item['product_image'] : 'uploads/items/default.jpeg');
                                                                ?>
                                                                <li class="list-group-item d-flex flex-wrap align-items-center justify-content-between px-2 py-2 gap-2"
                                                                    data-id="<?= $item['item_id'] ?>"
                                                                    data-price="<?= $item['unit_price'] ?>"
                                                                    data-qty="<?= $item['qty'] ?>">

                                                                    <!-- hidden fields for saving -->
                                                                    <input type="hidden" name="suppliers[<?= $index ?>][items][<?= $iIndex ?>][item_id]" value="<?= $item['item_id'] ?>" />
                                                                    <input type="hidden" name="suppliers[<?= $index ?>][items][<?= $iIndex ?>][qty]" value="<?= $item['qty'] ?>" class="item-qty-hidden" />
                                                                    <input type="hidden" name="suppliers[<?= $index ?>][items][<?= $iIndex ?>][price]" value="<?= $item['unit_price'] ?>" />
                                                                    <input type="hidden" name="suppliers[<?= $index ?>][items][<?= $iIndex ?>][unit_measure]" value="<?= $item['unit_measure'] ?>" />

                                                                    <div class="me-3 d-flex align-items-start gap-3 flex-grow-1" style="min-width: 250px;">
                                                                        <div>
                                                                            <a href="#" class="image-lightbox" data-bs-toggle="modal" data-bs-target="#imageModal" data-img="<?= $imagePath ?>">
                                                                                <img src="<?= $imagePath ?>" class="img-thumbnail" style="width: 60px; height: 60px; object-fit: cover;" />
                                                                            </a>
                                                                        </div>
                                                                        <div style="flex: 1;">
                                                                            <strong><?= htmlspecialchars($item['item_name']) ?></strong><br />
                                                                            <small class="text-muted"><?= htmlspecialchars($item['description']) ?></small><br />
                                                                            <small class="text-muted">₱<?= number_format($item['unit_price'], 2) ?> per <?= htmlspecialchars($item['unit_measure']) ?></small>
                                                                        </div>
                                                                        <div class="d-flex flex-column align-items-end text-end">
                                                                            <div class="d-flex align-items-center gap-1">
                                                                                <button type="button" class="btn btn-sm btn-outline-secondary btn-minus px-2"><i class="fas fa-circle-minus"></i></button>
                                                                                <input type="number" min="1" value="<?= $item['qty'] ?>"
                                                                                    class="form-control form-control-sm item-qty-input text-center"
                                                                                    style="width: 60px;" />
                                                                                <button type="button" class="btn btn-sm btn-outline-primary btn-add px-2"><i class="fas fa-circle-plus"></i></button>
                                                                            </div>
                                                                            <button type="button" class="btn btn-sm btn-outline-danger btn-remove mt-1 px-2"><i class="fas fa-trash-alt"></i></button>
                                                                            <small class="text-muted d-block">Subtotal: ₱<span class="item-subtotal"><?= number_format($subtotal, 2) ?></span></small>
                                                                        </div>
                                                                    </div>
                                                                </li>
                                                            <?php endforeach; ?>
                                                        </ul>

                                                        <!-- Add Item controls -->
                                                        <div class="itemSelectArea d-none mt-2">
                                                            <select class="form-select availableItemSelect"></select>
                                                            <div class="mt-2 text-end">
                                                                <button type="button" class="btn btn-sm btn-success confirmAddBtn">Add</button>
                                                                <button type="button" class="btn btn-sm btn-secondary cancelAddBtn">Cancel</button>
                                                            </div>
                                                        </div>
                                                        <button type="button" class="btn btn-sm btn-primary addItemBtn mt-2">+ Add Item</button>

                                                        <!-- Total -->
                                                        <div class="text-end fw-semibold text-dark mt-2">
                                                            Total: ₱<span class="totalAmount"><?= number_format($total, 2) ?></span>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>

                                    </div>
                                </div>
                                <div class="col-12">
                                    <div class="mb-3 mb-0"><label class="form-label">Recommendations and Suggestions:</label><textarea class="form-control" rows="3" name="notes" placeholder="Recommendations and Suggestions:"></textarea></div>
                                </div>
                                <div class="row align-items-end gx-2"> <!-- gx-2 adds horizontal spacing -->
                                    <div class="col-auto">
                                        <label class="form-label">PR Number</label>
                                        <input class="form-control" id="prNumber" name="pr_number"  value="<?php echo $pr_number; ?>" readonly />
                                    </div>
                                    <div class="col-auto">
                                        <label class="form-label">PR Number</label>
                                        <input class="form-control" id="selectDepartment" name="assigned_department" value="<?php echo $pr_department; ?>" readonly />
                                        
                                    </div>
                                    <div class="col-auto">
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

            $selectedItems.find('li').each(function() {
                const id = $(this).data('id');
                if (!selectedItems.includes(id)) {
                    selectedItems.push(id); // store old item_id in JS array
                }
            });
            updateTotalAmount(); // compute total on page load

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

                $itemSelectArea.removeClass('d-none'); // show only when ready
            }

            function updateTotalAmount() {
                let total = 0;
                $selectedItems.find('li').each(function() {
                    const price = parseFloat($(this).attr('data-price'));
                    const qty = parseInt($(this).attr('data-qty'));
                    total += price * qty;
                });
                $totalAmount.text(total.toFixed(2));
            }

            // Load supplier data
            $.getJSON('data/get_suppliers.php', function(data) {
                // Save data in a lookup map
                supplierData = {};
                data.forEach(supplier => {
                    supplierData[supplier.supplier_id] = supplier;
                });

                // Populate selects
                $('.supplierSelect').each(function() {
                    let $supplierSelect = $(this);
                    let selectedId = $supplierSelect.data('selected');

                    $supplierSelect.empty().append('<option value="">-- Select Supplier --</option>');
                    data.forEach(supplier => {
                        let isSelected = supplier.supplier_id == selectedId ? 'selected' : '';
                        $supplierSelect.append(
                            `<option value="${supplier.supplier_id}" ${isSelected}>${supplier.supplier_name}</option>`
                        );
                    });

                    // store the initial supplierId in its card so change event won't reset items
                    if (selectedId) {
                        $supplierSelect.closest('.supplierForm').data('supplierId', selectedId);
                    }

                    $supplierSelect.select2({
                        placeholder: "Select Supplier",
                        width: 'resolve'
                    });
                    // ❌ remove .trigger('change') here
                });
            });

            // On supplier change
            $supplierSelect.on('change', function() {
                let supplierId = $(this).val();
                let $card = $(this).closest('.supplierForm');
                let oldSupplierId = $card.data('supplierId'); // store the previous supplier id

                if (supplierId && supplierId === oldSupplierId) {
                    // 🔹 Supplier didn’t change, do nothing (keep old items)
                    return;
                }

                // 🔹 Supplier changed, clear items + reset
                selectedItems = [];
                $card.find('.selectedItems').empty();
                $card.find('.totalAmount').text("0.00");

                if (supplierId) {
                    // Save new supplierId in card
                    $card.data('supplierId', supplierId);

                    // Load supplier details
                    $.getJSON('data/get_supplier_details.php', {
                        id: supplierId
                    }, function(supplier) {
                        $card.find('.supplierDescription').text("CONTACT PERSON: " + (supplier.contact_person || '-'));
                        $card.find('.supplierContact').text("CONTACT NUMBER: " + (supplier.contact_number || '-'));
                        $card.find('.supplierTerms').text("TERMS OF PAYMENT: " + (supplier.payment_terms || '-'));
                    });

                    // Load supplier items
                    $.getJSON('data/get_supplier_items.php', {
                        id: supplierId
                    }, function(items) {
                        availableItems = items;

                        // hide first so no flicker
                        $itemSelectArea.addClass('d-none');

                        // rebuild dropdown
                        rebuildItemDropdown();

                        // ✅ auto-select the first item if available
                        if (items.length > 0) {
                            $availableItemSelect.val(items[0].item_id).trigger('change');
                        }
                    });

                } else {
                    // Reset if no supplier selected
                    $card.removeData('supplierId');
                    $card.find('.supplierDescription').text("CONTACT PERSON: -");
                    $card.find('.supplierContact').text("CONTACT NUMBER: -");
                    $card.find('.supplierTerms').text("TERMS OF PAYMENT: -");

                    availableItems = [];
                    rebuildItemDropdown();
                }
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
                if (!result.isConfirmed) return;

                const form = $('#allSuppliersForm')[0];
                const formData = new FormData(form);

                // Ensure required fields
                formData.append('pr_number', $('#prNumber').val());
                // formData.append('dealer', $('#dealer').val());
                // formData.append('canvass_id', $('#canvassId').val()); // if editing

                // 🔥 Debug: log all FormData entries
                console.group("FormData Payload");
                for (let [key, value] of formData.entries()) {
                    console.log(key, ":", value);
                }
                console.groupEnd();

                // 🔥 Convert to JSON-like object for easy viewing
                const obj = {};
                formData.forEach((value, key) => {
                    if (obj[key]) {
                        if (!Array.isArray(obj[key])) obj[key] = [obj[key]];
                        obj[key].push(value);
                    } else {
                        obj[key] = value;
                    }
                });
                console.log("FormData as JSON:", JSON.stringify(obj, null, 2));

                button.prop('disabled', true).text('Saving...');

                $.ajax({
                    url: 'save_all_supplierss.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    dataType: 'json', // expect JSON
                    success: function(res) {
                        console.log("Response:", res);

                        if (res.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Saved!',
                                text: res.message || 'Canvass sheet saved.',
                                timer: 1200,
                                showConfirmButton: false
                            }).then(() => {
                                window.location.href =
                                    'canvass_view?pr_number=' + encodeURIComponent($('#prNumber').val());
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.message || 'Something went wrong.'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'AJAX Error',
                            text: 'Error saving data: ' + error
                        });
                    },
                    complete: function() {
                        button.prop('disabled', false).text('Save All');
                    }
                });
            });
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

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-description').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const descId = this.getAttribute('data-id');
                const descElem = document.getElementById(descId);

                if (descElem.classList.contains('collapsed-desc')) {
                    descElem.classList.remove('collapsed-desc');
                    descElem.classList.add('expanded-desc');
                    this.textContent = 'View less';
                } else {
                    descElem.classList.remove('expanded-desc');
                    descElem.classList.add('collapsed-desc');
                    this.textContent = 'View more';
                }
            });
        });
    });
</script>

<?php
include 'footer.php';
?>