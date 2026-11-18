<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>
<script src="https://js.pusher.com/8.2/pusher.min.js"></script>
  
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
                        <div class="page-header-title">
                            <h2 class="mb-0">Dashboard</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end -->
        <!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">
                        <table class="table-stripped display responsive nowrap table-striped p-1" id="suppliersTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 5%;">Favorite</th>
                                    <th style="width: 20.127118644067796%;">Aging</th>
                                    <th style="width: 20.127118644067796%;">Dealer</th>
                                    <th style="width: 20.127118644067796%;">Department</th>
                                    <th style="width: 34.32203389830508%; ">PR Status</th>
                                    <th style="width: 34.32203389830508%; ">Next Approver</th>
                                    <th style="width: 4.872881355932203%;">PR Number</th>
                                    <th style="width: 20.127118644067796%;">Canvass Created</th>
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
<!-- [ Main Content ] end -->
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


<style>
    .favorite-btn {
        background: none !important;
        border: none !important;
        padding: 5px !important;
        cursor: pointer;
        z-index: 10;
        position: relative;
    }
    
    .favorite-btn:hover {
        opacity: 0.7;
    }
    
    .favorite-btn i {
        font-size: 1.2rem;
        transition: color 0.2s ease;
    }
</style>

<script>
    console.log('Dashboard script initializing...');
    
    $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: 'data/get_purchase_requisition_department.php',
        order: [[1, 'asc']],
        columns: [
            {
                data: 'is_favorite',
                orderable: false,
                render: function(data, type, row) {
                    console.log('Rendering favorite column:', {data: data, pr_number: row.pr_number});
                    
                    const isFavorite = data == 1;
                    const iconClass = isFavorite ? 'fa-solid fa-star' : 'fa-regular fa-star';
                    const color = isFavorite ? '#FFD700' : '#888888';
                    
                    const html = `
                        <button class="btn btn-link favorite-btn p-0" data-pr-number="${row.pr_number}" title="${isFavorite ? 'Remove Favorite' : 'Add Favorite'}" style="min-width: 30px;">
                            <i class="${iconClass}" style="color: ${color}; font-size: 1.2rem; display: inline-block;"></i>
                        </button>
                    `;
                    
                    console.log('Rendered HTML:', html);
                    return html;
                }
            },
            {
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
                data: 'pr_dealer'
            },
            {
                data: 'pr_department'
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
                        case 'Success':
                            badgeClass = 'success';
                            badgeLabel = 'Success';
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
                data: 'next_approver',
            },
            {
                data: 'pr_number'
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
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    const isDisabled = row.pr_status === 'For Canvass';

                    // Option 1: Disable the button
                   

                    // Option 2: Show the active link
                    return `
            <ul class="list-inline me-auto mb-0">
                <li class="list-inline-item" title="View Canvass">
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

    console.log('DataTable initialized');
    console.log('Favorite button handler attached');

    // Handle favorite button click
    $(document).on('click', '.favorite-btn', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        const btn = $(this);
        const prNumber = btn.data('pr-number');
        
        console.log('Star clicked for PR:', prNumber);
        
        if (!prNumber) {
            console.error('No PR number found');
            alert('Error: Could not identify PR number');
            return false;
        }
        
        // Show loading state
        const icon = btn.find('i');
        const originalIcon = icon.attr('class');
        icon.addClass('spin');

        console.log('Sending request to toggle_favorite.php...');
        
        $.ajax({
            type: 'POST',
            url: 'data/toggle_favorite.php',
            data: {
                pr_number: prNumber
            },
            dataType: 'json',
            timeout: 5000,
            success: function(response) {
                console.log('Response received:', response);
                if (response.success) {
                    console.log('Favorite toggled successfully');
                    console.log('About to reload table...');
                    // Reload table to show updated state and reorder
                    $('#suppliersTable').DataTable().ajax.reload(function() {
                        console.log('Table reloaded successfully');
                    }, false);
                } else {
                    console.error('Server error:', response.message);
                    alert('Error: ' + (response.message || 'Failed to update favorite'));
                    // Restore icon
                    icon.attr('class', originalIcon);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('AJAX Error:', textStatus, errorThrown);
                console.error('Response:', jqXHR.responseText);
                alert('Error: Could not connect to server (' + textStatus + ')');
                // Restore icon
                icon.attr('class', originalIcon);
            }
        });
        
        return false;
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
<?php
include 'footer.php';
?>