<?php
require '../init.php';
require_once __DIR__ . '/includes/po_approval_helpers.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);
$pr_number = $_GET['pr_number'] ?? '';
if (!$pr_number) {
    die('PR Number missing.');
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
$budgetSummary = null;
$budgetSummaryError = '';

$stmt = $pdo->prepare("
    SELECT pr.pr_department, pr.pr_dealer, csb.budget_code
    FROM purchase_requisition pr
    LEFT JOIN canvass_sheet_batches csb ON csb.pr_number = pr.pr_number
    WHERE pr.pr_number = ?
    LIMIT 1
");
$stmt->execute([$pr_number]);
$budgetContext = $stmt->fetch(PDO::FETCH_ASSOC);
if ($budgetContext && !empty($budgetContext['budget_code']) && !empty($budgetContext['pr_department'])) {
    try {
        $budgetSummary = po_approval_get_budget_summary(
            $pdo,
            $pr_number,
            (string)$budgetContext['budget_code'],
            (string)($budgetContext['pr_dealer'] ?? ($_SESSION['dealer'] ?? ''))
        );
    } catch (Exception $e) {
        $budgetSummaryError = $e->getMessage();
    }
}
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

$stmt = $pdo->prepare("SELECT r.*, u.full_name, u.user_pfp, u.department 
                       FROM recommendations_suggestions r
                       JOIN users u ON r.user_id = u.user_id
                       WHERE r.pr_number = ?
                       ORDER BY r.created_date ASC");
$stmt->execute([$pr_number]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("
    SELECT 
        s.supplier_id,
        s.vendor_id,
        s.supplier_name
    FROM canvass_items po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN items i ON po.item_id = i.item_id
    JOIN purchase_requisition pr ON po.batch_id = pr.batch_id
    WHERE pr.pr_number = ?
    ORDER BY s.supplier_name
");
$stmt->execute([$pr_number]);
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);


include 'header.php';
include 'navigation.php';
?>
<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
<link href="assets/css/select2.min.css" rel="stylesheet" />
<script src="assets/js/select2.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<style>
    @media print {
        .no-print {
            display: none !important;
        }

        /* You might also hide other non-essential elements */
        .header-buttons,
        .sidebar,
        .navbar,
        .breadcrumb,


        footer {
            display: none;
        }

        /* And adjust content to fill the space */
        main {
            width: 100%;
            margin: 0;
            padding: 0;
        }
    }

    @page {
        size: A3 landscape;
        /* Suggest A4 in portrait */
        /* You can also use landscape, or specific dimensions like 21cm 29.7cm */
        margin: 1cm;
    }

    .po-budget-summary {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 12px;
        margin: 14px 0 18px;
    }

    .po-budget-metric {
        min-width: 0;
        padding: 12px 14px;
        border: 1px solid #e5eaf1;
        border-radius: 8px;
        background: #f8fafc;
    }

    .po-budget-label {
        display: block;
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .po-budget-value {
        display: block;
        margin-top: 5px;
        color: #172033;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .po-budget-metric.is-remaining {
        border-color: #b7e4cc;
        background: #effaf4;
    }

    .po-budget-metric.is-over-budget {
        border-color: #fecaca;
        background: #fff1f2;
    }

    .po-budget-alert {
        display: none;
        margin: 12px 0 0;
        padding: 10px 12px;
        border: 1px solid #fecaca;
        border-radius: 8px;
        color: #991b1b;
        background: #fff1f2;
        font-size: .86rem;
        font-weight: 600;
    }

    .po-budget-alert.is-visible {
        display: block;
    }

    @media (max-width: 991.98px) {
        .po-budget-summary {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .po-budget-summary {
            grid-template-columns: 1fr;
        }
    }
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Add Purchase Order</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow">
                    <div class="card-body">
                        <form id="purchaseOrderForm" method="POST" enctype="multipart/form-data">
                            <div class="form-row">
                                <!-- PR Number (Read-Only) -->
                                <div class="form-group">
                                    <label for="pr_number">PR Number</label>
                                    <input type="text" id="pr_number" name="pr_number" class="form-control" value="<?php echo $pr_number; ?>" readonly>
                                </div>
                            </div>

                            <?php if ($budgetSummary): ?>
                                <?php
                                $approvedBudget = (float)$budgetSummary['approved_budget'];
                                $totalBudgetExpenses = (float)$budgetSummary['total_expenses'];
                                $remainingBudget = (float)$budgetSummary['remaining_budget'];
                                ?>
                                <div class="po-budget-summary">
                                    <div class="po-budget-metric">
                                        <span class="po-budget-label">Budget Code</span>
                                        <strong class="po-budget-value"><?= htmlspecialchars($budgetSummary['budget_code']) ?></strong>
                                    </div>
                                    <div class="po-budget-metric">
                                        <span class="po-budget-label">Approved</span>
                                        <strong class="po-budget-value">PHP <?= number_format($approvedBudget, 2) ?></strong>
                                    </div>
                                    <div class="po-budget-metric">
                                        <span class="po-budget-label">Used/Pending</span>
                                        <strong class="po-budget-value">PHP <?= number_format($totalBudgetExpenses, 2) ?></strong>
                                    </div>
                                    <div class="po-budget-metric <?= $remainingBudget < 0 ? 'is-over-budget' : 'is-remaining' ?>">
                                        <span class="po-budget-label">Remaining</span>
                                        <strong class="po-budget-value">PHP <?= number_format($remainingBudget, 2) ?></strong>
                                    </div>
                                </div>
                                <div id="poBudgetAlert" class="po-budget-alert" role="alert"></div>
                            <?php elseif ($budgetSummaryError !== ''): ?>
                                <div class="alert alert-warning"><?= htmlspecialchars($budgetSummaryError) ?></div>
                            <?php endif; ?>

                            <hr>
                            <h4>Purchase Orders</h4>
                            <div id="po-container">
                                <div class="po-entry border p-3 mb-2">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label>PO Number</label>
                                            <input type="text" name="po_number[]" class="form-control" placeholder="Enter PO Number" maxlength="11" minlength="10" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label>PO Date</label>
                                            <input type="date" name="po_date[]" class="form-control" required>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <label>Supplier Name</label>
                                            <select name="supplier_id[]" class="form-control" required>
                                                <option value="">Select Supplier</option>
                                                <?php foreach ($suppliers as $supplier): ?>
                                                    <option value="<?= htmlspecialchars($supplier['supplier_id']) ?>">
                                                        <?= htmlspecialchars($supplier['supplier_name']) ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mt-2">
                                            <label>Amount Purchased</label>
                                            <input type="number" step="0.01" name="amount_purchased[]" class="form-control po-amount-input" placeholder="Enter Amount" required>
                                        </div>
                                        <div class="col-md-12 mt-2">
                                            <label>Upload Purchase Order (PDF Only)</label>
                                            <input type="file" class="form-control pdf-input" name="po_file[]" accept="application/pdf" required>

                                            <!-- PDF Preview -->
                                            <div class="loading-spinner" style="display:none; text-align:center; margin:15px 0;">
                                                <div class="spinner-border text-primary" role="status"></div>
                                                <p>Loading preview...</p>
                                            </div>
                                            <div class="pdf-container" style="display:none; cursor:pointer; text-align:center;">
                                                <img class="pdf-thumbnail" src="" alt="PDF Thumbnail" style="max-width:100%; border:1px solid #ccc; width:100px;">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-danger btn-sm mt-2 remove-po">Remove</button>
                                </div>
                            </div>

                            <button type="button" id="add-po" class="btn btn-secondary mt-2">+ Add Another PO</button>
                            <hr>
                            <button type="submit" id="savePurchaseOrderBtn" class="btn btn-primary">Save Purchase Orders</button>
                        </form>

                    </div>
                </div>

            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-labelledby="pdfModalLabel" aria-hidden="true">
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
</div>

<?php
include 'footer.php';
?>
<script>
    const poRemainingBudget = <?= $budgetSummary ? json_encode((float)$budgetSummary['remaining_budget']) : 'null' ?>;

    function formatPoCurrency(amount) {
        return 'PHP ' + Number(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function getPoEnteredTotal() {
        return Array.from(document.querySelectorAll('input[name="amount_purchased[]"]'))
            .reduce((total, input) => total + (parseFloat(input.value) || 0), 0);
    }

    function updatePoBudgetGuard() {
        const saveButton = document.getElementById('savePurchaseOrderBtn');
        const alertBox = document.getElementById('poBudgetAlert');

        if (!saveButton || poRemainingBudget === null) {
            return true;
        }

        const total = getPoEnteredTotal();
        const isOverBudget = total > Number(poRemainingBudget);
        saveButton.disabled = isOverBudget;

        if (alertBox) {
            if (isOverBudget) {
                alertBox.textContent = 'PO total ' + formatPoCurrency(total) + ' is higher than the remaining budget of ' + formatPoCurrency(poRemainingBudget) + '.';
                alertBox.classList.add('is-visible');
            } else {
                alertBox.textContent = '';
                alertBox.classList.remove('is-visible');
            }
        }

        return !isOverBudget;
    }

    $(document).ready(function() {
        // AJAX Form Submission
        $('#purchaseOrderForm').submit(function(e) {
            e.preventDefault();

            if (!updatePoBudgetGuard()) {
                Swal.fire({
                    icon: 'error',
                    title: 'Budget exceeded',
                    text: 'PO total cannot be higher than the remaining budget.'
                });
                return;
            }

            const formData = new FormData(this); // includes file and text data

            $.ajax({
                url: 'data/create_purchase_order.php',
                type: 'POST',
                data: formData,
                contentType: false, // required for FormData
                processData: false, // required for FormData
                dataType: 'json',
                beforeSend: function() {
                    $('#loadingSpinner').show();
                },
                success: function(response) {
                    $('#loadingSpinner').hide();

                    if (response.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: response.message,
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload(); // Refresh after showing the message
                        });

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'An unexpected error occurred.'
                        });
                    }
                },
                error: function() {
                    $('#loadingSpinner').hide();

                    Swal.fire({
                        icon: 'error',
                        title: 'AJAX Error',
                        text: 'Something went wrong while creating the purchase order.'
                    });
                }
            });
        });

    });



    // Function to reload comments without refresh
    // function loadComments() {
    //     const prNumber = $('input[name="pr_number"]').val();
    //     $.ajax({
    //         url: 'load_comments.php',
    //         method: 'GET',
    //         data: {
    //             pr_number: prNumber
    //         },
    //         success: function(data) {
    //             $('#commentContainer').html(data);
    //         }
    //     });
    // }
</script>
<script>
    // Render PDF inside the modal
    function renderPage(pdfDoc, pageNum, scale) {
        const canvas = document.getElementById('pdfCanvas');
        const ctx = canvas.getContext('2d');
        const pageNumSpan = document.getElementById('pageNum');
        const pageCountSpan = document.getElementById('pageCount');

        pdfDoc.getPage(pageNum).then(page => {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.height = viewport.height;
            canvas.width = viewport.width;
            page.render({
                canvasContext: ctx,
                viewport
            });
            pageNumSpan.textContent = pageNum;
            pageCountSpan.textContent = pdfDoc.numPages;
        });
    }

    // Initialize PDF upload/preview per PO entry
    function initializePDFHandlers(poEntry) {
        const pdfInput = poEntry.querySelector('.pdf-input');
        const thumbnail = poEntry.querySelector('.pdf-thumbnail');
        const container = poEntry.querySelector('.pdf-container');
        const spinner = poEntry.querySelector('.loading-spinner');

        let fileURL = '';
        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.5;

        // PDF file input change handler
        pdfInput.addEventListener('change', function(e) {
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
            }).catch(err => {
                console.error("Error loading PDF:", err);
                alert("Failed to load PDF.");
                spinner.style.display = "none";
            });
        });

        // Thumbnail click opens modal PDF viewer
        container.addEventListener('click', function() {
            if (!fileURL) return;

            pdfjsLib.getDocument(fileURL).promise.then(pdf => {
                pdfDoc = pdf;
                currentPage = 1;
                scale = 1.5;
                renderPage(pdfDoc, currentPage, scale);

                const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
                modal.show();

                // Modal controls
                document.getElementById('zoomIn').onclick = () => {
                    scale += 0.2;
                    renderPage(pdfDoc, currentPage, scale);
                };
                document.getElementById('zoomOut').onclick = () => {
                    if (scale > 0.4) {
                        scale -= 0.2;
                        renderPage(pdfDoc, currentPage, scale);
                    }
                };
                document.getElementById('prevPage').onclick = () => {
                    if (currentPage > 1) {
                        currentPage--;
                        renderPage(pdfDoc, currentPage, scale);
                    }
                };
                document.getElementById('nextPage').onclick = () => {
                    if (currentPage < pdfDoc.numPages) {
                        currentPage++;
                        renderPage(pdfDoc, currentPage, scale);
                    }
                };
            });
        });
    }

    // Remove PO entry
    function attachRemoveButtons() {
        document.querySelectorAll('.remove-po').forEach(btn => {
            btn.onclick = function() {
                if (document.querySelectorAll('.po-entry').length > 1) {
                    btn.closest('.po-entry').remove();
                    updatePoBudgetGuard();
                } else {
                    alert('You must have at least one PO entry.');
                }
            };
        });
    }

    // Add new PO entry
    function addNewPO() {
        const container = document.getElementById('po-container');
        const poEntry = container.querySelector('.po-entry');
        const clone = poEntry.cloneNode(true); // Clone structure

        // Reset all input fields
        clone.querySelectorAll('input, select').forEach(el => {
            el.value = '';
        });

        // Reset PDF preview
        clone.querySelector('.pdf-thumbnail').src = '';
        clone.querySelector('.pdf-container').style.display = 'none';
        clone.querySelector('.loading-spinner').style.display = 'none';

        // Replace file input with fresh copy to remove old events
        const oldPdfInput = clone.querySelector('.pdf-input');
        const newPdfInput = oldPdfInput.cloneNode(true);
        oldPdfInput.parentNode.replaceChild(newPdfInput, oldPdfInput);

        // Replace remove button with fresh copy
        const oldRemoveBtn = clone.querySelector('.remove-po');
        const newRemoveBtn = oldRemoveBtn.cloneNode(true);
        oldRemoveBtn.parentNode.replaceChild(newRemoveBtn, oldRemoveBtn);

        container.appendChild(clone);

        // Reinitialize events
        attachRemoveButtons();
        initializePDFHandlers(clone);
        updatePoBudgetGuard();
    }

    // ---- Initialization ----
    document.addEventListener('DOMContentLoaded', function() {
        // Init first PO entry
        document.querySelectorAll('.po-entry').forEach(poEntry => initializePDFHandlers(poEntry));
        attachRemoveButtons();

        // Bind add-po button once
        const addPoBtn = document.getElementById('add-po');
        addPoBtn.replaceWith(addPoBtn.cloneNode(true)); // Remove any duplicate bindings
        document.getElementById('add-po').addEventListener('click', addNewPO);
        document.getElementById('po-container').addEventListener('input', function(e) {
            if (e.target.matches('.po-amount-input, input[name="amount_purchased[]"]')) {
                updatePoBudgetGuard();
            }
        });
        updatePoBudgetGuard();
    });
</script>
