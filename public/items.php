<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
require_once '../includes/item_category_helpers.php';

function getItemFilterOptions(PDO $pdo, string $column): array
{
    $allowedColumns = ['item_category', 'sub_category'];
    if (!in_array($column, $allowedColumns, true)) {
        return [];
    }

    $stmt = $pdo->query("
        SELECT DISTINCT $column AS value
        FROM items
        WHERE $column IS NOT NULL AND $column != ''
        ORDER BY $column ASC
    ");

    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

$itemCategoryOptions = getItemFilterOptions($pdo, 'item_category');
$subCategoryOptions = getManagedSubCategoryOptions($pdo);
$publicBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$publicBaseUrl = ($publicBasePath === '' || $publicBasePath === '.') ? '/' : $publicBasePath . '/';
?>

<style>
    @media (max-width: 768px) {
        table.dataTable td {
            white-space: normal !important;
        }
    }

    .dataTables_wrapper .dataTables_length {
        float: right;
        margin-right: 10px;
    }

    .dataTables_wrapper .dataTables_filter {
        float: left;
        margin-left: 10px;
    }


    .dataTables_info {
        margin-left: 10px;
    }

    /* Force SweetAlert2 above Bootstrap modal */
    .swal2-container {
        z-index: 20000 !important;
    }

    body {
        background: #f4f6f9;
    }

    .items-header {
        align-items: center;
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.04);
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 16px 18px;
    }

    .items-header h2 {
        font-weight: 700;
    }

    .panel-card {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    }

    .filter-toolbar {
        border-bottom: 1px solid #edf0f5;
        display: grid;
        gap: 12px;
        grid-template-columns: repeat(2, minmax(170px, 1fr)) auto;
        padding-bottom: 14px;
    }

    .filter-toolbar label {
        color: #6c757d;
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 4px;
    }

    .filter-toolbar .form-select,
    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    .table-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        margin-bottom: 12px;
    }

    .item-filter-summary {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .item-filter-summary .badge {
        font-size: 0.82rem;
        font-weight: 600;
    }

    .category-badge {
        border: 1px solid rgba(13, 110, 253, 0.12);
        font-size: 0.78rem;
        font-weight: 600;
        max-width: 180px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .edit-image-preview {
        background: #f8fafc;
        border: 1px dashed #cfd7e2;
        border-radius: 8px;
        min-height: 130px;
        padding: 12px;
    }

    .edit-image-preview img {
        max-height: 120px;
        object-fit: contain;
        width: 100%;
    }

    .item-history-table {
        font-size: 0.82rem;
    }

    .item-history-table td {
        vertical-align: top;
    }

    #itemsTable {
        border-collapse: separate !important;
        border-spacing: 0;
    }

    #itemsTable thead th {
        background: #f8fafc;
        color: #495057;
        font-size: 0.78rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    #itemsTable td {
        vertical-align: middle;
    }

    @media (max-width: 992px) {
        .filter-toolbar {
            grid-template-columns: repeat(2, minmax(160px, 1fr));
        }
    }

    @media (max-width: 768px) {
        .items-header,
        .filter-toolbar {
            align-items: stretch;
            display: flex;
            flex-direction: column;
        }

        .items-header .btn,
        .filter-toolbar .btn {
            width: 100%;
        }
    }
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="items-header mb-4">
            <div>
                <h2 class="mb-1">Item List</h2>
                <small class="text-muted">Browse items by supplier, category, sub-category, and price.</small>
            </div>
            <div class="btn-page">
                <a href="add_item" class="btn btn-primary d-inline-flex align-items-center gap-2">
                    <i class="ti ti-plus f-18"></i> Add Item
                </a>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="panel-card p-3">
                    <div class="filter-toolbar mb-3">
                        <div>
                            <label for="itemCategoryFilter">Item Category</label>
                            <select id="itemCategoryFilter" class="form-select form-select-sm item-filter">
                                <option value="">All item categories</option>
                                <?php foreach ($itemCategoryOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="subCategoryFilter">Sub Category</label>
                            <select id="subCategoryFilter" class="form-select form-select-sm item-filter">
                                <option value="">All sub-categories</option>
                                <?php foreach ($subCategoryOptions as $option): ?>
                                    <option
                                        value="<?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-category="<?= htmlspecialchars($option['item_category'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="d-flex align-items-end gap-2">
                            <button type="button" class="btn btn-outline-secondary btn-sm" id="clearItemFiltersBtn">
                                <i class="ti ti-filter-off"></i> Clear
                            </button>
                            <button type="button" class="btn btn-outline-primary btn-sm" id="refreshItemsBtn">
                                <i class="ti ti-refresh"></i> Refresh
                            </button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div>
                            <h6 class="mb-1">Products</h6>
                            <small class="text-muted">Use search for text matching and filters for exact categories.</small>
                        </div>
                        <div class="item-filter-summary">
                            <span id="activeItemFiltersLabel" class="badge bg-light-primary">All Items</span>
                            <span id="itemRecordCount" class="badge bg-light-secondary">0 records</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0" id="itemsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Supplier Name</th>
                                    <th>Item Name</th>
                                    <th>Description</th>
                                    <th>Item Category</th>
                                    <th>Sub Category</th>
                                    <th>Price</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
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
                                    <img class="img-fluid rounded" alt="Product image" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Product Info -->
                    <div class="col-sm-8">
                        <h5 class="product-title"></h5>
                        <p class="text-muted product-description"></p>
                        <div class="table-responsive">
                            <table class="table w-auto table-borderless">
                                <tbody>
                                    <!-- details dynamically inserted here -->
                                </tbody>
                            </table>
                        </div>
                        <!-- Update Price Button -->

                    </div>
                </div>
                <div class="mt-4">
                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                        <h6 class="mb-0">Edit Logs</h6>
                        <small class="text-muted">Latest item changes</small>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped item-history-table mb-0">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Edited By</th>
                                    <th>Field</th>
                                    <th>Old Value</th>
                                    <th>New Value</th>
                                </tr>
                            </thead>
                            <tbody id="itemEditLogsBody">
                                <tr>
                                    <td colspan="5" class="text-center text-muted">No edit logs loaded.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="editItemModal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <form id="editItemForm" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="mb-0">Update Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="item_id" id="editItemId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="editItemName" class="form-label">Item Name</label>
                            <input type="text" class="form-control" name="item_name" id="editItemName" required>
                        </div>
                        <div class="col-md-6">
                            <label for="editUnitPrice" class="form-label">Price</label>
                            <input type="text" class="form-control" name="unit_price" id="editUnitPrice" inputmode="decimal" required>
                        </div>
                        <div class="col-md-12">
                            <label for="editDescription" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="editDescription" rows="3"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label for="editItemCategory" class="form-label">Item Category</label>
                            <input type="text" class="form-control" name="item_category" id="editItemCategory" list="editItemCategoryOptions">
                            <datalist id="editItemCategoryOptions">
                                <?php foreach ($itemCategoryOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                        <div class="col-md-6">
                            <label for="editUnitMeasure" class="form-label">Unit Measure</label>
                            <input type="text" class="form-control" name="unit_measure" id="editUnitMeasure">
                        </div>
                        <div class="col-md-6">
                            <label for="editSubCategory" class="form-label">Sub Category</label>
                            <select class="form-select" name="sub_category" id="editSubCategory" required>
                                <option value="">Select Sub Category</option>
                                <?php foreach ($subCategoryOptions as $option): ?>
                                    <option
                                        value="<?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-category="<?= htmlspecialchars($option['item_category'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="editProductImage" class="form-label">Product Image</label>
                            <input type="file" class="form-control" name="product_image" id="editProductImage" accept="image/*">
                            <small class="text-muted">Choose a new image only if you want to replace the current one.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Current Image</label>
                            <div class="edit-image-preview d-flex align-items-center justify-content-center">
                                <img id="editImagePreview" src="data/uploads/items/default.jpeg" alt="Current product image" class="img-fluid rounded">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="ti ti-device-floppy"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts -->
<script>
    const appPublicBaseUrl = <?= json_encode($publicBaseUrl, JSON_UNESCAPED_SLASHES) ?>;

    function appPublicUrl(path) {
        return appPublicBaseUrl + String(path || '').replace(/^\/+/, '');
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function(char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            } [char];
        });
    }

    function escapeAttr(value) {
        return escapeHtml(value);
    }

    function textOrNA(value) {
        return value === null || value === undefined || value === '' ? 'N/A' : value;
    }

    function formatDateTime(value) {
        if (!value) return 'N/A';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return value;

        return date.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function truncate(str, maxLength) {
        if (!str) return '';
        str = str.toString();
        return str.length > maxLength ? str.substring(0, maxLength) + '...' : str;
    }

    function renderTruncated(data, type) {
        const value = data ?? '';
        if (type !== 'display') return value;
        return escapeHtml(truncate(value, 20));
    }

    function renderBadge(data, type, colorClass) {
        const value = data ?? '';
        if (type !== 'display') return value;
        if (!value) return '<span class="text-muted">N/A</span>';
        return `<span class="badge ${colorClass} category-badge" title="${escapeAttr(value)}">${escapeHtml(value)}</span>`;
    }

    function itemFilters() {
        return {
            item_category: $('#itemCategoryFilter').val() || '',
            sub_category: $('#subCategoryFilter').val() || ''
        };
    }

    function filterSubCategoryOptions($select, itemCategory, emptyText) {
        const selectedSubCategory = $select.val();
        let selectedSubCategoryIsAllowed = false;

        $select.find('option').each(function() {
            const $option = $(this);
            const optionValue = $option.val();
            const optionCategory = String($option.data('item-category') || '');
            const isPlaceholder = optionValue === '';
            const isAllowed = isPlaceholder || itemCategory === '' || optionCategory === itemCategory;

            $option.prop('hidden', !isAllowed);

            if (optionValue === selectedSubCategory && isAllowed) {
                selectedSubCategoryIsAllowed = true;
            }
        });

        $select.find('option[value=""]').text(emptyText);

        if (!selectedSubCategoryIsAllowed) {
            $select.val('');
        }
    }

    function syncFilterSubCategorySelect() {
        const itemCategory = $('#itemCategoryFilter').val() || '';
        filterSubCategoryOptions(
            $('#subCategoryFilter'),
            itemCategory,
            itemCategory === '' ? 'All sub-categories' : 'All matching sub-categories'
        );
    }

    function syncEditSubCategorySelect() {
        const itemCategory = $('#editItemCategory').val() || '';
        const $editSubCategory = $('#editSubCategory');

        filterSubCategoryOptions(
            $editSubCategory,
            itemCategory,
            itemCategory === '' ? 'Select Item Category First' : 'Select Sub Category'
        );

        $editSubCategory.prop('disabled', itemCategory === '');
    }

    function updateFilterSummary() {
        const filters = itemFilters();
        const active = [];

        if (filters.item_category) active.push(`Item: ${filters.item_category}`);
        if (filters.sub_category) active.push(`Sub: ${filters.sub_category}`);

        $('#activeItemFiltersLabel').text(active.length ? active.join(' | ') : 'All Items');
    }

    function updateRecordCount(settings) {
        const json = settings.json || {};
        const count = Number(json.recordsFiltered || 0);
        $('#itemRecordCount').text(`${count.toLocaleString('en-US')} record${count === 1 ? '' : 's'}`);
    }

    function loadItemEditLogs(itemId) {
        $('#itemEditLogsBody').html('<tr><td colspan="5" class="text-center text-muted">Loading edit logs...</td></tr>');

        $.ajax({
            url: appPublicUrl('data/get_item_edit_logs.php'),
            type: 'GET',
            dataType: 'json',
            data: {
                item_id: itemId
            },
            success: function(response) {
                if (response.status !== 'success') {
                    $('#itemEditLogsBody').html(`<tr><td colspan="5" class="text-center text-danger">${escapeHtml(response.message || 'Could not load edit logs.')}</td></tr>`);
                    return;
                }

                const logs = response.logs || [];
                if (!logs.length) {
                    $('#itemEditLogsBody').html('<tr><td colspan="5" class="text-center text-muted">No edit logs found.</td></tr>');
                    return;
                }

                const html = logs.map(log => `
                    <tr>
                        <td>${escapeHtml(formatDateTime(log.created_at))}</td>
                        <td>${escapeHtml(textOrNA(log.edited_by))}</td>
                        <td>${escapeHtml(textOrNA(log.field_name))}</td>
                        <td>${escapeHtml(textOrNA(log.old_value))}</td>
                        <td>${escapeHtml(textOrNA(log.new_value))}</td>
                    </tr>
                `).join('');

                $('#itemEditLogsBody').html(html);
            },
            error: function() {
                $('#itemEditLogsBody').html('<tr><td colspan="5" class="text-center text-danger">Failed to load edit logs.</td></tr>');
            }
        });
    }

    $(document).on('click', '.view-btn', function() {
        const itemid = $(this).data('itemid');
        const suppliername = textOrNA($(this).data('suppliername'));
        const itemname = textOrNA($(this).data('itemname'));
        const unitprice = $(this).data('unitprice') || 'N/A';
        const description = $(this).data('description') || 'No description available.';
        const productimage = $(this).data('productimage');
        const itemcategory = textOrNA($(this).data('itemcategory'));
        const productcategory = textOrNA($(this).data('productcategory'));
        const subcategory = textOrNA($(this).data('subcategory'));
        const unitmeasure = textOrNA($(this).data('unitmeasure'));
        const createdby = textOrNA($(this).data('createdby'));
        const createdat = textOrNA($(this).data('createdat'));

        // Save item_id globally inside modal for later use
        $('#cust-modal').data('itemid', itemid);

        // Fallback image if productimage is missing
        const imgPath = productimage && productimage !== '' ?
            productimage :
            'uploads/items/default.jpeg';

        // Format price
        let priceDisplay = 'N/A';
        if (unitprice && !isNaN(unitprice)) {
            priceDisplay = `₱${parseFloat(unitprice).toLocaleString(undefined, { 
            minimumFractionDigits: 2, 
            maximumFractionDigits: 2 
        })}`;
        }

        // Update modal content
        $('#cust-modal h5.product-title').text(itemname);
        $('#cust-modal .modal-body .chat-avtar img').attr('src', 'data/' + imgPath);
        $('#cust-modal .modal-body p.text-muted').text(description);

        // Update details in table
        const detailsHtml = `
        <tr hidden>
            <td class="text-muted py-1">Item ID</td>
            <td class="py-1 item-id">${escapeHtml(itemid)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Supplier</td>
            <td class="py-1">${escapeHtml(suppliername)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Item Category</td>
            <td class="py-1">${escapeHtml(itemcategory)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Product Category</td>
            <td class="py-1">${escapeHtml(productcategory)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Sub Category</td>
            <td class="py-1">${escapeHtml(subcategory)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Unit</td>
            <td class="py-1">${escapeHtml(unitmeasure)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Price</td>
            <td class="py-1"><h5 class="mb-0 current-price">${priceDisplay}</h5></td>
        </tr>
        <tr>
            <td class="text-muted py-1">Created By</td>
            <td class="py-1">${escapeHtml(createdby)}</td>
        </tr>
        <tr>
            <td class="text-muted py-1">Added Date</td>
            <td class="py-1">${escapeHtml(formatDateTime(createdat))}</td>
        </tr>
    `;
        $('#cust-modal .modal-body table tbody').html(detailsHtml);
        loadItemEditLogs(itemid);
    });

    $(document).on('click', '.edit-item-btn', function() {
        $('#editItemId').val($(this).data('itemid'));
        $('#editItemName').val($(this).data('itemname') || '');
        $('#editDescription').val($(this).data('description') || '');
        $('#editUnitPrice').val($(this).data('unitprice') || '');
        $('#editItemCategory').val($(this).data('itemcategory') || '');
        syncEditSubCategorySelect();
        $('#editSubCategory').val($(this).data('subcategory') || '');
        syncEditSubCategorySelect();
        $('#editUnitMeasure').val($(this).data('unitmeasure') || '');
        $('#editProductImage').val('');

        const currentImage = $(this).data('productimage') || 'uploads/items/default.jpeg';
        $('#editImagePreview').attr('src', 'data/' + currentImage);
    });

    $(document).on('change', '#editProductImage', function(event) {
        const file = event.target.files[0];
        if (!file || !file.type.startsWith('image/')) {
            return;
        }

        const reader = new FileReader();
        reader.onload = function(e) {
            $('#editImagePreview').attr('src', e.target.result);
        };
        reader.readAsDataURL(file);
    });

    function cleanMoneyInput(value) {
        return String(value || '').replace(/,/g, '').trim();
    }

    function updatePrice(productId, currentPrice) {
        if ($.fn.modal) {
            $.fn.modal.Constructor.prototype.enforceFocus = function() {};
        }

        Swal.fire({
            title: 'Update Price',
            input: 'text',
            inputLabel: 'Enter new price',
            inputValue: parseFloat(currentPrice).toFixed(2), // show plain number initially
            inputAttributes: {
                inputmode: 'decimal'
            },
            showCancelButton: true,
            confirmButtonText: 'Update',
            showLoaderOnConfirm: true,
            customClass: {
                popup: 'swal2-front'
            },
            didOpen: () => {
                const input = Swal.getInput();

                // Let user type freely (only restrict invalid chars)
                input.addEventListener('input', () => {
                    let value = input.value.replace(/,/g, '');
                    value = value.replace(/[^0-9.]/g, ''); // keep only numbers + decimal

                    // Only allow one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }

                    // Limit decimals to 2
                    if (parts[1]) {
                        parts[1] = parts[1].slice(0, 2);
                        value = parts[0] + '.' + parts[1];
                    }

                    input.value = value;
                });

                // On blur (when leaving the field), format with commas
                input.addEventListener('blur', () => {
                    let value = input.value.replace(/,/g, '');
                    if (!value) return;

                    const num = parseFloat(value);
                    if (!isNaN(num)) {
                        input.value = num.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                });
            },
            // preConfirm: (newPrice) => {
            //     if (!newPrice) {
            //         Swal.showValidationMessage('Please enter a valid number');
            //         return false;
            //     }

            //     // Remove commas before saving
            //     let cleanValue = newPrice.replace(/,/g, '');
            //     if (isNaN(cleanValue)) {
            //         Swal.showValidationMessage('Invalid number format');
            //         return false;
            //     }

            //     cleanValue = parseFloat(cleanValue).toFixed(2); // store as 1234.56

            //     return fetch('data/update_item_price.php', {
            //             method: 'POST',
            //             headers: {
            //                 'Content-Type': 'application/x-www-form-urlencoded'
            //             },
            //             body: `id=${productId}&price=${cleanValue}`
            //         })
            //         .then(response => response.json())
            //         .then(data => {
            //             if (data.status === "success") {
            //                 Swal.fire({
            //                     icon: "success",
            //                     title: "Price updated!",
            //                     showConfirmButton: false,
            //                     timer: 1000
            //                 }).then(() => {
            //                     location.reload(); // reload page after alert disappears
            //                 });
            //             } else {
            //                 Swal.fire({
            //                     icon: "error",
            //                     title: "Update failed",
            //                     text: data.message || "Something went wrong."
            //                 });
            //             }
            //         }).catch(error => {
            //             Swal.showValidationMessage(`Request failed: ${error}`);
            //         });

            // },
            preConfirm: (newPrice) => {
                if (!newPrice) {
                    Swal.showValidationMessage('Please enter a valid number');
                    return false;
                }

                // Remove commas before saving
                let cleanValue = newPrice.replace(/,/g, '');

                // Validate with regex (only numbers + optional 2 decimals)
                if (!/^\d+(\.\d{1,2})?$/.test(cleanValue)) {
                    Swal.showValidationMessage('Invalid number format (max 2 decimals)');
                    return false;
                }

                // Send as plain string (e.g. "123.95"), no float conversion
                return fetch(appPublicUrl('data/update_item_price.php'), {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${productId}&price=${encodeURIComponent(cleanValue)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "Price updated!",
                                showConfirmButton: false,
                                timer: 1000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Update failed",
                                text: data.message || "Something went wrong."
                            });
                        }
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        });
    }








    $(document).ready(function() {
        syncFilterSubCategorySelect();
        syncEditSubCategorySelect();

        const itemsTable = $('#itemsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            order: [],
            ajax: {
                url: appPublicUrl('data/item_data.php'),
                data: function(d) {
                    const filters = itemFilters();
                    d.item_category = filters.item_category;
                    d.sub_category = filters.sub_category;
                }
            },
            drawCallback: function(settings) {
                updateRecordCount(settings);
                updateFilterSummary();
            },
            columns: [{
                    data: 'supplier_name',
                    render: renderTruncated
                },
                {
                    data: 'item_name',
                    render: renderTruncated
                },
                {
                    data: 'description',
                    render: renderTruncated
                },
                {
                    data: 'item_category',
                    render: function(data, type) {
                        return renderBadge(data, type, 'bg-light-primary');
                    }
                },
                {
                    data: 'sub_category',
                    render: function(data, type) {
                        return renderBadge(data, type, 'bg-light-success');
                    }
                },
                {
                    data: 'unit_price',
                    render: function(data, type, row) {
                        if (data == null || data === '') return '';
                        return '₱' + parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        const itemId = Number(row.item_id) || 0;
                        const unitPrice = Number(row.unit_price) || 0;

                        return `
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="View">
                                <a class="avtar avtar-xs btn-link-secondary btn-pc-default btn view-btn" 
                                    data-itemid="${itemId}"
                                    data-suppliername="${escapeAttr(row.supplier_name)}"
                                    data-itemname="${escapeAttr(row.item_name)}"
                                    data-unitprice="${escapeAttr(row.unit_price)}"
                                    data-description="${escapeAttr(row.description)}"
                                    data-productimage="${escapeAttr(row.product_image)}"
                                    data-itemcategory="${escapeAttr(row.item_category)}"
                                    data-productcategory="${escapeAttr(row.product_category)}"
                                    data-subcategory="${escapeAttr(row.sub_category)}"
                                    data-unitmeasure="${escapeAttr(row.unit_measure)}"
                                    data-createdby="${escapeAttr(row.created_by)}"
                                    data-createdat="${escapeAttr(row.created_at)}"
                                    data-bs-toggle="modal" data-bs-target="#cust-modal">
                                    <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                 <a class="avtar avtar-xs btn-link-warning btn-pc-default edit-item-btn"
                                    style="cursor:pointer;"
                                    data-itemid="${itemId}"
                                    data-itemname="${escapeAttr(row.item_name)}"
                                    data-unitprice="${escapeAttr(row.unit_price)}"
                                    data-description="${escapeAttr(row.description)}"
                                    data-productimage="${escapeAttr(row.product_image)}"
                                    data-itemcategory="${escapeAttr(row.item_category)}"
                                    data-productcategory="${escapeAttr(row.product_category)}"
                                    data-subcategory="${escapeAttr(row.sub_category)}"
                                    data-unitmeasure="${escapeAttr(row.unit_measure)}"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editItemModal">
                                    <i class="ti ti-edit-circle f-18"></i>
                                </a>
                            </li>
                        </ul>
                        `;
                    }

                }
            ]
        });

        $('.item-filter').on('change', function() {
            if (this.id === 'itemCategoryFilter') {
                syncFilterSubCategorySelect();
            }
            updateFilterSummary();
            itemsTable.ajax.reload();
        });

        $('#clearItemFiltersBtn').on('click', function() {
            $('.item-filter').val('');
            syncFilterSubCategorySelect();
            updateFilterSummary();
            itemsTable.ajax.reload();
        });

        $('#editItemCategory').on('input change', syncEditSubCategorySelect);

        $('#refreshItemsBtn').on('click', function() {
            itemsTable.ajax.reload(null, false);
        });

        $('#editItemForm').on('submit', function(e) {
            e.preventDefault();

            const cleanPrice = cleanMoneyInput($('#editUnitPrice').val());
            if (!/^\d+(\.\d{1,2})?$/.test(cleanPrice)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Price',
                    text: 'Please enter a valid price with up to 2 decimal places.'
                });
                return;
            }

            $('#editUnitPrice').val(cleanPrice);
            const formData = new FormData(this);

            $.ajax({
                url: appPublicUrl('data/update_item.php'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const modalEl = document.getElementById('editItemModal');
                        const modal = bootstrap.Modal.getInstance(modalEl);
                        if (modal) modal.hide();

                        Swal.fire({
                            icon: 'success',
                            title: 'Item updated!',
                            showConfirmButton: false,
                            timer: 1000
                        });

                        itemsTable.ajax.reload(null, false);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Update failed',
                            text: response.message || 'Something went wrong.'
                        });
                    }
                },
                error: function(xhr, status, errorThrown) {
                    console.error('AJAX error while updating item:', {
                        status: status,
                        error: errorThrown,
                        responseText: xhr.responseText
                    });

                    Swal.fire({
                        icon: 'error',
                        title: 'AJAX Error',
                        text: 'Something went wrong while updating the item.'
                    });
                }
            });
        });

        updateFilterSummary();
    });
</script>


<!-- <script>
    $(document).ready(function() {
        $('#itemsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: 'data/item_data.php',
            columns: [{
                    data: 'supplier_name'
                },
                {
                    data: 'item_name'
                },
                {
                    data: 'description'
                },
                {
                    data: 'unit_price'
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="View">
                                <a class="avtar avtar-xs btn-link-secondary btn-pc-default btn view-btn" 
                                data-sap="${row.supplier_id}" 
                                data-name="${row.item_name}"
                                data-tin="${row.description}" data-bs-toggle="modal" data-bs-target="#cust-modal"><i class="ti ti-eye f-18"></i></a>
                            </li>
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                <a href="edit_supplier.php?sap_id=${row.supplier_id}" class="avtar avtar-xs btn-link-success btn-pc-default"><i class="ti ti-edit-circle f-18"></i></a>
                            </li>
                        </ul>
                        `;
                    }
                }
            ]
        });
    });
</script> -->
<!-- <button 
                    class="btn btn-sm btn-outline-primary view-btn"
                    data-sap="${row.sap_id}" 
                    data-name="${row.supplier_name}"
                    data-tin="${row.tin_number}"
                    data-bs-toggle="modal" 
                    data-bs-target="#cust-modal"
                    title="View Details">
                    <i class="ti ti-eye"></i>
                </button>
                 <a 
                href="edit_supplier.php?sap_id=${row.sap_id}" 
                class="btn btn-sm btn-outline-warning"
                title="Edit Supplier">
                <i class="ti ti-pencil"></i>
            </a> -->
<?php
include 'footer.php';
?>
