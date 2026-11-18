<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
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
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="page-header-title">
                                <h2 class="mb-0">Purchase Requisition</h2>
                            </div>
                            <div class="btn-page">
                                <a href="add_purchase_requisition" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                    <i class="ti ti-plus f-18"></i> Add Purchase Requisition
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">
                        <table class="table-stripped display responsive nowrap table-striped p-1" id="suppliersTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 20.127118644067796%;">Aging</th>
                                    <th style="width: 4.872881355932203%;">PR Number</th>
                                    <th style="width: 34.32203389830508%; ">PR Status</th>
                                    <th style="width: 20.127118644067796%;">Canvass Created</th>
                                    <th style="width: 20.127118644067796%;">Department</th>
                                    <th style="width: 20.127118644067796%;">Action</th>
                                    <!-- <th class="text-end" style="width: 7.203389830508475%;">PRICE</th>
                                    <th class="text-end" style="width: 5.508474576271186%;">QTY</th>
                                    <th style="width: 12.182203389830509%;">STATUS</th>
                                    <th class="text-center" style="width: 15.783898305084746%;">ACTIONS</th> -->
                                </tr>
                            </thead>
                            <!-- Data will be populated here by DataTables -->
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
                <a href="#" class="avtar avtar-s btn-link-danger btn-pc-default ms-auto" data-bs-dismiss="modal"><i class="ti ti-x f-20"></i></a>
            </div>
            <div class="modal-body">
                <div class="row align-items-center">
                    <div class="col-sm-4">
                        <div class="bg-light rounded position-relative">
                            <div class="text-center">
                                <div class="chat-avtar d-inline-flex mx-auto"><img class="img-fluid rounded" src="assets/images/application/img-prod-4.jpg" alt="User image" /></div>
                            </div>
                            <div class="position-absolute end-0 top-0 p-3"><span class="badge bg-success">In Stock</span></div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <h5>Canon EOS 1500D 24.1 Digital</h5>
                        <p class="text-muted">
                            Image Enlargement: After shooting, you can enlarge photographs of the objects for clear zoomed view. Change In Aspect Ratio: Boldly crop the subject and save it with a composition that has impact.
                        </p>
                        <div class="table-responsive">
                            <table class="table w-auto table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-1">Categories</td>
                                        <td class="py-1">Electronics, Camera</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Qty</td>
                                        <td class="py-1">21</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Price</td>
                                        <td class="py-1">
                                            <h5 class="mb-0">$399</h5>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <canvas id="pdfCanvas"></canvas>
                <p>Page <span id="pageNum">1</span> of <span id="pageCount">1</span></p>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button class="btn btn-secondary" id="prevPage">Previous</button>
                    <button class="btn btn-secondary" id="nextPage">Next</button>
                </div>
                <div>
                    <button class="btn btn-secondary" id="zoomOut">-</button>
                    <button class="btn btn-secondary" id="zoomIn">+</button>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- Scripts -->
<script>
    $(document).on('click', '.view-btn', function() {
        const sapId = $(this).data('sap');
        const name = $(this).data('name');
        const tin = $(this).data('tin');

        // You can update your modal fields here
        $('#cust-modal h5').text(name);
        $('#cust-modal .modal-body').html(`
            <p><strong>SAP ID:</strong> ${sapId}</p>
            <p><strong>Vendor Name:</strong> ${name}</p>
            <p><strong>TIN Number:</strong> ${tin}</p>
        `);
    });
</script>

<script>
    $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: 'data/get_purchase_requisition.php',
        order: [
            [0, 'desc']
        ],
        columns: [{
                data: 'pr_date',
                render: function(data) {
                    if (!data) return ''; // Handle empty/null values

                    const createdDate = new Date(data);
                    const today = new Date();
                    const diffTime = today - createdDate;
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24)); // Convert ms to days

                    return diffDays + ' day' + (diffDays !== 1 ? 's' : '');
                }
            },
            {
                data: 'pr_number'
            },
            {
                data: 'pr_status',
                render: function(data, type, row) {
                    // Map status to Bootstrap badge
                    let badgeClass = '';
                    let badgeLabel = '';

                    switch (data) {
                        case 'For Canvass':
                            badgeClass = 'danger';
                            badgeLabel = 'For Canvass';
                            break;
                        case 'For Approval':
                            badgeClass = 'primary';
                            badgeLabel = 'For Approval';
                            break;
                        case 'For PO':
                            badgeClass = 'warning';
                            badgeLabel = 'For PO';
                            break;
                        case 'Completed':
                            badgeClass = 'success';
                            badgeLabel = 'Completed';
                            break;
                        default:
                            badgeClass = 'dark';
                            badgeLabel = data;
                    }

                    return `<span class="badge fs-6 bg-light-${badgeClass}">${badgeLabel}</span>`;
                }
            },
            {
                data: 'canvass_created',
                render: function(data) {
                    if (!data || data === '0000-00-00') return 'N/A'; // Handle empty or invalid dates
                    return new Date(data).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'long',
                        day: '2-digit'
                    });
                }
            },

            {
                data: 'pr_department'
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    const isDisabled = row.pr_status === 'For Canvass';

                    // Option 1: Disable the button
                    // if (isDisabled) {
                    //     return `
                    //     <ul class="list-inline me-auto mb-0">
                    //         <li class="list-inline-item" title="View">
                    //             <a href="javascript:void(0);" 
                    //             class="avtar avtar-xs btn-link-secondary btn-pc-default disabled" 
                    //             style="pointer-events: none; opacity: 0.6;">
                    //             <i class="ti ti-eye f-18"></i>
                    //             </a>
                    //         </li>
                    //     </ul>
                    // `;
                    // }

                    // Option 2: Show the active link
                    return `
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item" title="View Purchase Requisition">
                                <a href="view_purchase_requisition?pr_number=${row.pr_number}" 
                                class="avtar avtar-xs btn-link-success btn-pc-default">
                                <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                            
                        </ul>
                        
                    `;
                }
            }

        ]
    });
</script>
<script>
    let pdfDoc = null,
        currentPage = 1,
        scale = 1.5,
        canvas = document.getElementById('pdfCanvas'),
        ctx = canvas.getContext('2d'),
        pageNumSpan = document.getElementById('pageNum'),
        pageCountSpan = document.getElementById('pageCount');

    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            const renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            page.render(renderContext);

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

    function viewUploadedPDF(filePath) {
        pdfjsLib.getDocument(filePath).promise.then(pdf => {
            pdfDoc = pdf;
            currentPage = 1;
            renderPage(currentPage);

            const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
            modal.show();
        }).catch(error => {
            console.error('PDF load error:', error);
            alert('Failed to load PDF.');
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.view-pdf-btn').forEach(button => {
            button.addEventListener('click', function() {
                const prNumber = this.getAttribute('data-pr-number');

                // Adjust this path to match where your PDFs are stored
                const pdfPath = `data/${prNumber}`;

                viewUploadedPDF(pdfPath);
            });
        });
    });
</script>
<!-- <button 
                    class="btn btn-sm btn-outline-primary view-btn"
                    data-sap="${row.vendor_id}" 
                    data-name="${row.supplier_name}"
                    data-tin="${row.tin_number}"
                    data-bs-toggle="modal" 
                    data-bs-target="#cust-modal"
                    title="View Details">
                    <i class="ti ti-eye"></i>
                </button>
                 <a 
                href="edit_supplier.php?vendor_id=${row.vendor_id}" 
                class="btn btn-sm btn-outline-warning"
                title="Edit Supplier">
                <i class="ti ti-pencil"></i>
            </a> -->
<?php
include 'footer.php';
?>