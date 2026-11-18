<?php
require '../init.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
require_once '../includes/item_category_helpers.php';

$subCategoryOptions = getManagedSubCategoryOptions($pdo);
?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<style>
    body {
        background: #f4f6f9;
    }

    /* Target Select2 placeholder text */
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        font-size: 0.85rem;
        /* Smaller than normal */
        color: #bec8d0;
        /* Bootstrap's secondary text */
    }

    .item-form-header,
    .panel-card {
        background: #fff;
        border: 1px solid #edf0f5;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    }

    .item-form-header {
        align-items: center;
        display: flex;
        gap: 16px;
        justify-content: space-between;
        padding: 16px 18px;
    }

    .item-form-header h2 {
        font-weight: 700;
    }

    .section-title {
        border-left: 4px solid #0d6efd;
        color: #495057;
        font-size: 1rem;
        font-weight: 700;
        margin-bottom: 16px;
        padding-left: 10px;
    }

    .form-label {
        color: #495057;
        font-size: 0.82rem;
        font-weight: 600;
    }

    .form-control,
    .form-select,
    .select2-container--bootstrap-5 .select2-selection {
        border-radius: 8px !important;
    }

    .upload-panel {
        align-items: center;
        background: #f8fafc;
        border: 1px dashed #cfd7e2;
        border-radius: 10px;
        display: flex;
        flex-direction: column;
        gap: 10px;
        justify-content: center;
        min-height: 230px;
        padding: 16px;
        text-align: center;
    }

    .upload-panel .upload-icon {
        align-items: center;
        background: #e7f1ff;
        border-radius: 10px;
        color: #0d6efd;
        display: inline-flex;
        height: 42px;
        justify-content: center;
        width: 42px;
    }

    #imagePreview {
        border-radius: 10px;
        max-height: 180px;
        object-fit: contain;
        width: 100%;
    }

    .form-actions {
        background: #fff;
        border-top: 1px solid #edf0f5;
        bottom: 0;
        display: flex;
        gap: 8px;
        justify-content: flex-end;
        margin: 18px -16px -16px;
        padding: 14px 16px 0;
        position: sticky;
    }

    @media (max-width: 768px) {
        .item-form-header,
        .form-actions {
            align-items: stretch;
            flex-direction: column;
        }

        .item-form-header .btn,
        .form-actions .btn {
            width: 100%;
        }
    }
</style>
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <div class="item-form-header mb-4">
            <div>
                <h2 class="mb-1">Add New Item</h2>
                <small class="text-muted">Create a supplier item with category, unit, price, and optional image.</small>
            </div>
            <a href="items" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                <i class="ti ti-arrow-left"></i> Item List
            </a>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="panel-card p-3">
                        <form id="addItemForm">
                            <div class="row g-4">
                                <div class="col-lg-7">
                                    <h6 class="section-title">Product Details</h6>
                                    <div class="mb-3">
                                        <label for="supplierSelect" class="form-label">Supplier Name</label>
                                        <select class="form-select w-100 form-select-lg" id="supplierSelect" name="supplier_name" data-placeholder="Select Supplier Name" required></select>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Product Name</label>
                                        <input type="text" class="form-control" name="product_name" placeholder="Enter Product Name" required />
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Product Description</label>
                                        <textarea class="form-control" name="product_description" rows="4" placeholder="Enter Product Description" required></textarea>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="categorySelect" class="form-label">Item Category</label>
                                                <select class="form-select w-100 form-select-lg" id="categorySelect" name="item_category" data-placeholder="Select Item Category" required></select>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="subCategorySelect" class="form-label">Sub Category</label>
                                                <select class="form-select w-100 form-select-lg" id="subCategorySelect" name="sub_category" required disabled>
                                                    <option value="">Select Item Category First</option>
                                                    <?php foreach ($subCategoryOptions as $option): ?>
                                                        <option
                                                            value="<?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>"
                                                            data-item-category="<?= htmlspecialchars($option['item_category'], ENT_QUOTES, 'UTF-8') ?>">
                                                            <?= htmlspecialchars($option['sub_category'], ENT_QUOTES, 'UTF-8') ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-lg-5">
                                    <h6 class="section-title">Pricing And Media</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6 col-lg-12">
                                            <label for="measuresSelect" class="form-label">Unit of Measures</label>
                                            <select class="form-select w-100 form-select-lg" id="measuresSelect" name="unit_measures" data-placeholder="Select Unit of Measures" required></select>
                                        </div>
                                        <div class="col-md-6 col-lg-12">
                                            <label class="form-label">Price</label>
                                            <input type="text" class="form-control" name="price" inputmode="decimal" placeholder="Enter Product Price" required />
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <label class="form-label">Product Image</label>
                                        <div class="upload-panel">
                                            <span class="upload-icon"><i class="ti ti-photo-up f-22"></i></span>
                                            <div>
                                                <div class="fw-semibold">Upload product image</div>
                                                <small class="text-muted">PNG, JPG, or WEBP. Click preview to enlarge.</small>
                                            </div>
                                            <input type="file" class="form-control" id="productImage" name="product_image" accept="image/*">
                                            <img id="imagePreview" src="#" alt="Preview" class="img-thumbnail d-none" data-bs-toggle="modal" data-bs-target="#imageModal">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div id="responseMsg" class="mt-2"></div>
                            <div class="form-actions">
                                <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='items'">Cancel</button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="ti ti-device-floppy"></i> Add New Product
                                </button>
                            </div>
                        </form>
                </div>

            </div>
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<!-- Modal Lightbox -->
<div class="modal fade" id="imageModal" tabindex="-1" aria-labelledby="imageModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-body p-0">
                <img src="#" id="modalImage" class="w-100" alt="Product Image">
            </div>
        </div>
    </div>
</div>
<script>
    document.getElementById('productImage').addEventListener('change', function(event) {
        const file = event.target.files[0];
        const preview = document.getElementById('imagePreview');
        const modalImage = document.getElementById('modalImage');

        if (file && file.type.startsWith('image/')) {
            const reader = new FileReader();

            reader.onload = function(e) {
                preview.src = e.target.result;
                modalImage.src = e.target.result;
                preview.classList.remove('d-none');
            };

            reader.readAsDataURL(file);
        } else {
            preview.classList.add('d-none');
            modalImage.src = '#';
        }
    });
</script>
<script>
    const appPublicBaseUrl = (() => {
        const path = window.location.pathname;
        const publicIndex = path.indexOf('/public/');

        if (publicIndex !== -1) {
            return path.substring(0, publicIndex + '/public/'.length);
        }

        return path.substring(0, path.lastIndexOf('/') + 1);
    })();

    function appPublicUrl(path) {
        return appPublicBaseUrl + String(path || '').replace(/^\/+/, '');
    }

    $(document).ready(function() {
        // Initialize Select2 for Supplier Name
        $('#supplierSelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#supplierSelect').parent(), // Helps with modals
            ajax: {
                url: appPublicUrl('data/search_suppliers.php'),
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

        $('#measuresSelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#measuresSelect').parent(),
            ajax: {
                url: appPublicUrl('data/search_measures.php'),
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


        $('#categorySelect').select2({
            theme: 'bootstrap-5',
            dropdownParent: $('#categorySelect').parent(),
            ajax: {
                url: appPublicUrl('data/search_category.php'),
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

        function syncSubCategorySelect() {
            const selectedCategory = $('#categorySelect').val() || '';
            const $subCategorySelect = $('#subCategorySelect');
            const currentSubCategory = $subCategorySelect.val();
            let currentSubCategoryIsAllowed = false;

            $subCategorySelect.find('option').each(function() {
                const $option = $(this);
                const optionValue = $option.val();
                const optionCategory = String($option.data('item-category') || '');
                const isPlaceholder = optionValue === '';
                const isAllowed = isPlaceholder || optionCategory === selectedCategory;

                $option.prop('hidden', !isAllowed);

                if (optionValue === currentSubCategory && isAllowed) {
                    currentSubCategoryIsAllowed = true;
                }
            });

            $subCategorySelect
                .prop('disabled', selectedCategory === '')
                .find('option[value=""]')
                .text(selectedCategory === '' ? 'Select Item Category First' : 'Select Sub Category');

            if (selectedCategory === '' || !currentSubCategoryIsAllowed) {
                $subCategorySelect.val('');
            }
        }

        $('#categorySelect').on('change select2:select select2:clear', syncSubCategorySelect);
        syncSubCategorySelect();

        // AJAX Form Submission
        $('#addItemForm').submit(function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            $.ajax({
                url: appPublicUrl('data/submit_item.php'),
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: response.message,
                            timer: 2000,
                            showConfirmButton: false
                        });

                        // Reset the form
                        $('#addItemForm')[0].reset();

                        // Reset Select2 dropdowns
                        $('#supplierSelect').val(null).trigger('change');
                        $('#categorySelect').val(null).trigger('change');
                        $('#measuresSelect').val(null).trigger('change');
                        syncSubCategorySelect();

                        // Hide image preview
                        $('#imagePreview').attr('src', '#').addClass('d-none');
                        $('#modalImage').attr('src', '#');

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Something went wrong.'
                        });
                    }
                },
                error: function(xhr, status, errorThrown) {
                    console.error('AJAX error while submitting item:', {
                        status: status,
                        error: errorThrown,
                        responseText: xhr.responseText
                    });

                    Swal.fire({
                        icon: 'error',
                        title: 'AJAX Error',
                        text: 'Something went wrong while submitting the item.'
                    });
                }
            });
        });

    });
</script>


<?php
include 'footer.php';
?>
