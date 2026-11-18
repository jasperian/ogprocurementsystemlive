<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>


<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<style>
    /* Target Select2 placeholder text */
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        font-size: 0.85rem;
        /* Smaller than normal */
        color: #bec8d0;
        /* Bootstrap's secondary text */
    }
</style>
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Add Purchase Requisition</h2>
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
                        <form id="addItemForm">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="supplierSelect" class="form-label">PR Number</label>
                                        <input class="form-control" id="prNumber" name="pr_number" placeholder="Enter PR Number" maxlength="11" minlength="10" required>
                                    </div>
                                    <div class="mb-3">
                                        <!-- Spinner -->
                                        <div id="loadingSpinner" style="display: none; text-align: center; margin: 15px 0;">
                                            <div class="spinner-border text-primary" role="status">
                                                <span class="visually-hidden">Loading...</span>
                                            </div>
                                            <p>Loading preview...</p>
                                        </div>

                                        <!-- PDF Thumbnail Preview -->
                                        <div id="pdfContainer" style="display: none; cursor: pointer; text-align: center;">
                                            <img id="pdfThumbnail" src="" alt="PDF Thumbnail" style="max-width: 100%; border: 1px solid #ccc; width: 350px;" />
                                        </div>

                                        <p><span class="text-danger">*</span> Only PDF File</p>

                                        <!-- Upload Button -->
                                        <label class="btn btn-outline-secondary" for="pdfInput">
                                            <i class="ti ti-upload me-2"></i> Upload Purchase Requisition
                                        </label>

                                        <!-- Hidden File Input -->
                                        <input type="file" id="pdfInput" name="vendorPdf" accept="application/pdf" class="d-none" />
                                    </div>

                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3"><label class="form-label">Department</label>
                                        <div class="col-sm-auto">
                                            <select class="form-select" name="pr_department" id="pr_department">
                                                <option value="" selected disabled>Select Department</option>
                                            <?php if ($_SESSION['user_id'] != 84): ?>
                                                <option value="CRD">CRD</option>
                                                <option value="HR">HR</option>
                                                <option value="ADMIN">ADMIN</option>
                                                <option value="ACCOUNTING">ACCOUNTING</option>
                                                <option value="FINANCE">FINANCE</option>
                                                <option value="MIS">MIS</option>
                                                <option value="VSD">VSD</option>
                                                <option value="PURCHASING">PURCHASING</option>
                                                <option value="PARTS">PARTS</option>
                                                <option value="AFTER SALES">AFTER SALES</option>
                                                <option value="VSPA">VSPA</option>
                                            <?php else: ?>
                                                <option value="AutoQuix">AutoQuix</option>
                                                <option value="HINO">HINO</option>
                                                <option value="PFFC">PFFC</option>
                                                <option value="TSURE">TSURE</option>
                                                <option value="DIVINE">DIVINE</option>
                                            <?php endif; ?>
                                             </select>
                                        </div>
                                    </div>

                                    <div class="text-end btn-page mt-4">
                                        <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='items'">Cancel</button>
                                        <button type="submit" class="btn btn-primary">Add Purchase Requisition</button>
                                    </div>
                                </div>
                            </div>
                            <div id="responseMsg" class="mt-2"></div>
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


<script>
    $(document).ready(function() {
        // AJAX Form Submission
        $('#addItemForm').submit(function(e) {
            e.preventDefault();

            const formData = new FormData(this); // includes file and text data

            $.ajax({
                url: 'data/create_purchase_requisition.php',
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
                            showConfirmButton: false,
                            timer: 1000
                        });

                        $('#addItemForm')[0].reset();
                        $('#supplierSelect').val(null).trigger('change');
                        $('#pdfThumbnail').hide();
                        $('#pdfContainer').hide();

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: response.message || 'Something went wrong.'
                        });
                    }
                },
                error: function() {
                    $('#loadingSpinner').hide();

                    Swal.fire({
                        icon: 'error',
                        title: 'AJAX Error',
                        text: 'Something went wrong while submitting the form.'
                    });
                }
            });
        });

    });
</script>

<script>
    const pdfInput = document.getElementById('pdfInput');
    const thumbnail = document.getElementById('pdfThumbnail');
    const container = document.getElementById('pdfContainer');
    const spinner = document.getElementById('loadingSpinner');

    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas.getContext('2d');
    const pageNumSpan = document.getElementById('pageNum');
    const pageCountSpan = document.getElementById('pageCount');

    let fileURL = '';
    let pdfDoc = null;
    let currentPage = 1;
    let scale = 1.5;

    // Render the selected page
    function renderPage(num) {
        pdfDoc.getPage(num).then(function(page) {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            page.render({
                canvasContext: ctx,
                viewport: viewport
            });

            // Update page counters
            pageNumSpan.textContent = num;
            pageCountSpan.textContent = pdfDoc.numPages;
        });
    }

    // Load and show thumbnail on upload
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
        }).catch(error => {
            console.error("Error loading PDF:", error);
            alert("Failed to load PDF.");
            spinner.style.display = "none";
        });
    });

    // Open modal and show full PDF when thumbnail is clicked
    container.addEventListener('click', function() {
        if (!fileURL) return;

        pdfjsLib.getDocument(fileURL).promise.then(pdf => {
            pdfDoc = pdf;
            currentPage = 1;
            scale = 1.5;
            renderPage(currentPage);
            const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
            modal.show();
        });
    });

    // Zoom controls
    document.getElementById('zoomIn').addEventListener('click', function() {
        scale += 0.2;
        renderPage(currentPage);
    });

    document.getElementById('zoomOut').addEventListener('click', function() {
        if (scale > 0.4) {
            scale -= 0.2;
            renderPage(currentPage);
        }
    });

    // Navigation controls
    document.getElementById('prevPage').addEventListener('click', function() {
        if (currentPage > 1) {
            currentPage--;
            renderPage(currentPage);
        }
    });

    document.getElementById('nextPage').addEventListener('click', function() {
        if (currentPage < pdfDoc.numPages) {
            currentPage++;
            renderPage(currentPage);
        }
    });
</script>





<?php
include 'footer.php';
?>