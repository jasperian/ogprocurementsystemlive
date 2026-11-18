<?php
require '../init.php';
include 'header.php';
include 'navigation.php';
require_once '../includes/item_category_helpers.php';

ensureItemCategoryTables($pdo);
$itemCategoryOptions = getManagedItemCategories($pdo);
$publicBasePath = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
$publicBaseUrl = ($publicBasePath === '' || $publicBasePath === '.') ? '/' : $publicBasePath . '/';
?>

<style>
    body {
        background: #f4f6f9;
    }

    .categories-header,
    .panel-card {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    }

    .categories-header {
        align-items: center;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 16px 18px;
    }

    .section-title {
        border-left: 4px solid #0d6efd;
        color: #495057;
        font-size: 1rem;
        font-weight: 700;
        padding-left: 10px;
    }

    .category-form {
        border-bottom: 1px solid #edf0f5;
        margin-bottom: 14px;
        padding-bottom: 14px;
    }

    .dataTables_wrapper .dataTables_filter input,
    .dataTables_wrapper .dataTables_length select {
        border: 1px solid #dee2e6;
        border-radius: 8px;
    }

    @media (max-width: 768px) {
        .categories-header {
            align-items: stretch;
            flex-direction: column;
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="categories-header mb-4">
            <div>
                <h2 class="mb-1">Category Management</h2>
                <small class="text-muted">Manage item categories and approved sub-categories.</small>
            </div>
            <a href="items" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="ti ti-arrow-left"></i> Item List
            </a>
        </div>

        <div class="row g-4">
            <div class="col-lg-5">
                <div class="panel-card p-3 h-100">
                    <h6 class="section-title mb-3">Item Categories</h6>
                    <form id="itemCategoryForm" class="category-form">
                        <label for="categoryName" class="form-label">New Item Category</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="categoryName" name="category_name" placeholder="e.g. Office Supplies" required>
                            <button class="btn btn-primary" type="submit">
                                <i class="ti ti-plus"></i> Add
                            </button>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0" id="itemCategoryTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Item Category</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="panel-card p-3 h-100">
                    <h6 class="section-title mb-3">Sub Categories</h6>
                    <form id="subCategoryForm" class="category-form">
                        <div class="row g-2">
                            <div class="col-md-5">
                                <label for="subCategoryItemCategory" class="form-label">Item Category</label>
                                <select class="form-select" id="subCategoryItemCategory" name="item_category" required>
                                    <option value="">Select Item Category</option>
                                    <?php foreach ($itemCategoryOptions as $option): ?>
                                        <option value="<?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($option, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-5">
                                <label for="subCategory" class="form-label">Sub Category</label>
                                <input type="text" class="form-control" id="subCategory" name="sub_category" placeholder="e.g. Office Furniture" required>
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100" type="submit">
                                    <i class="ti ti-plus"></i> Add
                                </button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered align-middle mb-0" id="subCategoryTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Item Category</th>
                                    <th>Sub Category</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

    function formatDateTime(value) {
        if (!value) return '-';
        const date = new Date(String(value).replace(' ', 'T'));
        if (Number.isNaN(date.getTime())) return escapeHtml(value);

        return date.toLocaleString('en-PH', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function ajaxErrorMessage(xhr, url) {
        const statusText = xhr.status ? `Request failed (${xhr.status}).` : 'Request failed.';
        const failedUrl = url ? ` URL: ${url}` : '';

        if (String(xhr.responseText || '').trim().startsWith('<!DOCTYPE html')) {
            return `${statusText} The server returned an HTML error page instead of JSON.${failedUrl}`;
        }

        return xhr.responseText || `${statusText} Please try again.${failedUrl}`;
    }

    $(document).ready(function() {
        const itemCategoryTable = $('#itemCategoryTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: appPublicUrl('data/manage_item_categories.php?type=item_categories'),
            columns: [{
                    data: 'id'
                },
                {
                    data: 'category_name',
                    render: data => escapeHtml(data)
                }
            ],
            order: []
        });

        const subCategoryTable = $('#subCategoryTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: appPublicUrl('data/manage_item_categories.php?type=sub_categories'),
            columns: [{
                    data: 'id'
                },
                {
                    data: 'item_category',
                    render: data => escapeHtml(data || '-')
                },
                {
                    data: 'sub_category',
                    render: data => escapeHtml(data)
                },
                {
                    data: 'created_by',
                    render: data => escapeHtml(data || '-')
                },
                {
                    data: 'created_at',
                    render: data => formatDateTime(data)
                }
            ],
            order: []
        });

        function handleFormSubmit($form, url, onSuccess) {
            $.ajax({
                url,
                type: 'POST',
                data: $form.serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Saved',
                            text: response.message,
                            showConfirmButton: false,
                            timer: 1200
                        });
                        $form[0].reset();
                        onSuccess();
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Unable to save',
                            text: response.message || 'Something went wrong.'
                        });
                    }
                },
                error: function(xhr) {
                    console.error('Category request failed:', {
                        url,
                        status: xhr.status,
                        responseText: xhr.responseText
                    });

                    Swal.fire({
                        icon: 'error',
                        title: 'AJAX Error',
                        text: ajaxErrorMessage(xhr, url)
                    });
                }
            });
        }

        $('#itemCategoryForm').on('submit', function(e) {
            e.preventDefault();
            handleFormSubmit($(this), appPublicUrl('data/add_item_category_master.php'), function() {
                itemCategoryTable.ajax.reload(null, false);
                window.location.reload();
            });
        });

        $('#subCategoryForm').on('submit', function(e) {
            e.preventDefault();
            handleFormSubmit($(this), appPublicUrl('data/add_product_sub_category.php'), function() {
                subCategoryTable.ajax.reload(null, false);
            });
        });
    });
</script>

<?php
include 'footer.php';
?>
