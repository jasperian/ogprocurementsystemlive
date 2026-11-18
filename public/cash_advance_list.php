<?php
require '../init.php';
include 'header.php';
include 'navigation.php';

$status = $_GET['status'] ?? 'CA Request';
$statusLabels = [
    'CA Request' => 'For Department Head Approval',
    'CA Release' => 'For Release',
    'For PO' => 'For Liquidation',
    'For Liquidation' => 'For Department Head Approval',
    'Liquidated' => 'For SAP Document Number',
    'For Accounting Approval' => 'For Accounting Completion',
];
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
<link rel="stylesheet" href="assets/css/liquidations.css?v=20260928">
<style>
    #caTable th:last-child,
    #caTable td:last-child { min-width: 230px; white-space: nowrap; }
</style>

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0">Purchasing Requests - <?= htmlspecialchars($statusLabels[$status] ?? $status) ?></h2>
                                <p class="liquidation-subtitle">Review cash advance requests, supporting documents, amounts, and workflow progress.</p>
                            </div>
                            <?php if ($_SESSION['department'] === 'PURCHASING') { ?>
                                <div class="btn-page">
                                    <a href="add_cash_advance" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                        <i class="ti ti-plus f-18"></i> Add Cash Advance
                                    </a>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card liquidation-card">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Cash Advance Register</h5>
                    <p class="liquidation-toolbar-subtitle">Open a record for full details or use the available document and workflow actions.</p>
                </div>
            </div>
            <div class="card-body">
                        <!-- Hidden global file input for CA uploads -->
                        <input type="file" id="globalCaFileInput" accept="application/pdf,image/jpeg,image/png" style="display:none;">
                        
                        <table class="display responsive nowrap liquidation-table" id="caTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 10%;">Aging</th>
                                    <th style="width: 12%;">Request Type</th>
                                    <th style="width: 15%;">Request No.</th>
                                    <th style="width: 15%;">PR No.</th>
                                    <th style="width: 15%;">Amount</th>
                                    <th style="width: 12%;">Actual Amount</th>
                                    <th style="width: 12%;">CA Date</th>
                                    <th style="width: 16%;">Department</th>
                                    <th style="width: 10%;">Status</th>
                                    <th style="width: 10%;">Actions</th>
                                </tr>
                            </thead>
                        </table>
            </div>
        </div>
    </div>
</div>

<!-- Cash Advance liquidation details modal -->
<div class="modal fade liquidation-modal" id="poDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Liquidation Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="poDetailsForm" enctype="multipart/form-data">
                    <input type="hidden" id="poModalCaNumber" name="ca_number">
                    <div class="mb-3">
                        <label for="poModalAmount" class="form-label">Actual Amount</label>
                        <input type="number" class="form-control" id="poModalAmount" name="po_amount" step="0.01" min="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label for="poModalFile" class="form-label">Liquidation Attachment (PDF/JPG/PNG)</label>
                        <input type="file" class="form-control" id="poModalFile" name="ca_file" accept="application/pdf,image/jpeg,image/png" required>
                    </div>
                    <div class="mb-3">
                        <label for="poModalLiquidationRemarks" class="form-label">Liquidation Remarks</label>
                        <textarea class="form-control" id="poModalLiquidationRemarks" name="liquidation_remarks" rows="3" maxlength="2000"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="savePoDetailsBtn">Save Liquidation Details</button>
            </div>
        </div>
    </div>
</div>

<!-- PDF Viewer Modal -->
<div class="modal fade liquidation-modal" id="pdfModal" tabindex="-1">
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
                    <button class="btn btn-outline-secondary" id="prevPage">Previous</button>
                    <button class="btn btn-outline-secondary" id="nextPage">Next</button>
                </div>
                <div>
                    <button class="btn btn-outline-secondary" id="zoomOut" title="Zoom out" aria-label="Zoom out"><i class="ti ti-zoom-out f-18"></i></button>
                    <button class="btn btn-outline-secondary" id="zoomIn" title="Zoom in" aria-label="Zoom in"><i class="ti ti-zoom-in f-18"></i></button>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="caDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Cash Advance Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="caDetailsBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const workflowUser = {
        id: <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        position: <?= json_encode(strtoupper(trim($_SESSION['position'] ?? ''))) ?>,
        department: <?= json_encode(strtoupper(trim($_SESSION['department'] ?? ''))) ?>
    };

    function workflowStatusLabel(status, requestType) {
        const labels = {
            'CA Request': 'For Department Head Approval',
            'CA Release': 'For Release',
            'For PO': 'For Liquidation',
            'For Liquidation': 'For Department Head Approval',
            'For Accounting Approval': 'For Accounting Completion'
        };
        if (status === 'Liquidated') return 'For SAP Document Number';
        return labels[status] || status;
    }

    function workflowNextStatus(row) {
        const status = row.pr_status;
        const type = row.request_type || 'Cash Advance';
        const amount = parseFloat(row.ca_amount || 0);
        if (status === 'For Accounting Approval') return 'Completed';
        if (type === 'Cash Advance') {
            return ({'CA Request':'CA Release','CA Release':'For PO','For PO':'For Liquidation','For Liquidation':'Liquidated','Liquidated':'For Accounting Approval','For GM Approval':'For Accounting Approval'})[status] || null;
        }
        if (type === 'Request for Payment') return ({'CA Request':'For GM Approval','Liquidated':'For GM Approval','For GM Approval':'CA Release','CA Release':'Completed'})[status] || null;
        return ({'CA Request':'Liquidated','Liquidated':(type === 'Liquidation' && row.source_rfp_id) || amount > 10000 ? 'For GM Approval' : 'For Accounting Approval','For GM Approval':'For Accounting Approval'})[status] || null;
    }

    function workflowCanAct(row) {
        const status = row.pr_status;
        const type = row.request_type || 'Cash Advance';
        const isHead = ['MANAGER', 'PURCHASING MANAGER'].includes(workflowUser.position);
        const isAccounting = ['ACCOUNTING', 'FINANCE'].includes(workflowUser.department);
        if (status === 'CA Request') return isHead;
        if (status === 'CA Release') return ['Cash Advance', 'Request for Payment'].includes(type) && isAccounting;
        if (status === 'For PO') return type === 'Cash Advance' && Number(row.created_by) === workflowUser.id;
        if (status === 'For Liquidation') return type === 'Cash Advance' && isHead;
        if (status === 'Liquidated') return isAccounting;
        if (status === 'For GM Approval') {
            const assignedGm = Number(row.final_approver_user_id || 0);
            return workflowUser.position === 'GENERAL MANAGER'
                && (assignedGm === 0 || assignedGm === workflowUser.id);
        }
        if (status === 'For Accounting Approval') return isAccounting;
        return false;
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

    function detailItem(label, value) {
        return `
            <div class="col-md-6">
                <span class="detail-label">${escapeHtml(label)}</span>
                <span class="detail-value">${escapeHtml(value || 'N/A')}</span>
            </div>`;
    }

    function money(value) {
        return value ? '\u20B1 ' + parseFloat(value).toLocaleString('en-US', {
            minimumFractionDigits: 2
        }) : 'N/A';
    }

    function formatDate(value, longFormat) {
        if (!value) return 'N/A';
        return new Date(value).toLocaleDateString('en-US', longFormat ? {
            year: 'numeric',
            month: 'long',
            day: '2-digit'
        } : {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
    }

    function truncateText(value, limit) {
        const text = value || '';
        return text.length > limit ? text.substring(0, limit) + '...' : text;
    }

    function requestTypeBadge(value, phaseLabel) {
        const type = value || 'Cash Advance';
        const badgeClass = type === 'Request for Payment' ? 'status-success' : 'status-info';
        return `<span class="liquidation-status ${badgeClass}">${escapeHtml(type)}</span>${phaseLabel ? `<span class="liquidation-status status-warning mt-1">${escapeHtml(phaseLabel)}</span>` : ''}`;
    }

    function getCaTableRowData(element) {
        const table = $('#caTable').DataTable();
        let $row = $(element).closest('tr');

        if ($row.hasClass('child')) {
            $row = $row.prev();
        }

        return table.row($row).data() || {};
    }

    const liquidationVisibleStatuses = [
        'For PO',
        'For Liquidation',
        'Liquidated',
        'For GM Approval',
        'For SAP Document',
        'For Accounting Approval',
        'Completed'
    ];

    $('#caTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: 'data/get_cash_advance_list.php?status=<?= urlencode($status) ?>',
        order: [
            [0, 'desc']
        ],
        columns: [{
                data: 'pr_date',
                render: function(data, type) {
                    if (!data) return '';
                    const createdDate = new Date(data);
                    const today = new Date();
                    const diffTime = today - createdDate;
                    const diffDays = Math.floor(diffTime / (1000 * 60 * 60 * 24));
                    if (type !== 'display') return diffDays;
                    const tone = diffDays > 30 ? 'status-danger' : (diffDays > 14 ? 'status-warning' : 'status-neutral');
                    return `<span class="liquidation-status ${tone}">${diffDays} day${diffDays !== 1 ? 's' : ''}</span>`;
                }
            },
            {
                data: 'request_type',
                render: function(value, type, row) { return type === 'display' ? requestTypeBadge(value, row.phase_label) : value; }
            },
            {
                data: 'ca_number',
                render: function(data, type) {
                    return type === 'display' ? `<span class="liquidation-primary-cell">${escapeHtml(data || 'N/A')}</span>` : data;
                }
            },
            {
                data: 'purchase_requisition_number',
                defaultContent: null,
                render: function(data, type) {
                    if (type !== 'display') return data || '';
                    return data ? `<span class="liquidation-primary-cell">${escapeHtml(data)}</span>` : '<span class="liquidation-meta">Not linked</span>';
                }
            },
            {
                data: 'ca_amount',
                render: function(data, type, row) {
                    return data ? '₱ ' + parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2
                    }) : '0.00';
                }
            },
            {
                data: 'po_amount',
                render: function(data) {
                    return data ? '\u20B1 ' + parseFloat(data).toLocaleString('en-US', {
                        minimumFractionDigits: 2
                    }) : 'N/A';
                }
            },
            {
                data: 'ca_date',
                render: function(data) {
                    if (!data) return 'N/A';
                    return new Date(data).toLocaleDateString('en-US', {
                        year: 'numeric',
                        month: 'short',
                        day: '2-digit'
                    });
                }
            },
            {
                data: 'pr_department'
            },
            {
                data: 'pr_status',
                render: function(data) {
                    let badgeClass = 'status-neutral';
                    if (data === 'Completed') badgeClass = 'status-success';
                    else if (data === 'CA Request' || data === 'For Accounting Approval') badgeClass = 'status-info';
                    else if (data === 'For PO' || data === 'For SAP Document') badgeClass = 'status-warning';
                    else if (data === 'For GM Approval') badgeClass = 'status-danger';
                    return `<span class="liquidation-status ${badgeClass}">${escapeHtml(workflowStatusLabel(data || 'N/A', row.request_type))}</span>`;
                }
            },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    const currentStatus = row.pr_status;
                    let nextStatus = workflowNextStatus(row);

                    let actionButtons = `<ul class="list-inline me-auto mb-0 liquidation-action-group">
                            <li class="list-inline-item" title="View Details">
                                <a href="javascript:void(0);"
                                    class="liquidation-action-btn success view-ca-details-btn" aria-label="View details">
                                    <i class="ti ti-eye f-18"></i>
                                </a>
                            </li>
                            ${row.purchase_requisition_number ? `
                            <li class="list-inline-item" title="View Purchase Requisition">
                                <a href="view_purchase_requisition?pr_number=${encodeURIComponent(row.purchase_requisition_number)}"
                                    target="_blank"
                                    class="liquidation-action-btn" aria-label="View purchase requisition">
                                    <i class="ti ti-external-link f-18"></i>
                                </a>
                            </li>` : ''}
                            <li class="list-inline-item" title="Request Form">
                                <a href="print_cash_advance.php?ca_number=${encodeURIComponent(row.ca_number)}" 
                                    target="_blank"
                                    class="liquidation-action-btn" aria-label="View request form">
                                    <i class="ti ti-file-text f-18"></i>
                                </a>
                            </li>
                            ${liquidationVisibleStatuses.includes(currentStatus) ? `
                            <li class="list-inline-item" title="Liquidation Form">
                                <a href="print_cash_advance_liquidation.php?ca_number=${encodeURIComponent(row.ca_number)}" 
                                    target="_blank"
                                    class="liquidation-action-btn warning" aria-label="View liquidation form">
                                    <i class="ti ti-file-invoice f-18"></i>
                                </a>
                            </li>` : ''}
                            ${row.ca_attachment_file ? `
                            <li class="list-inline-item" title="View Attachment">
                                <a href="javascript:void(0);" 
                                    class="liquidation-action-btn success view-pdf-btn" aria-label="View attachment"
                                    data-filepath="${row.ca_attachment_file}">
                                    <i class="ti ti-paperclip f-18"></i>
                                </a>
                            </li>` : ''}`;

                    if (currentStatus === 'For PO') {
                        actionButtons += `
                            <li class="list-inline-item" title="Liquidation Details">
                                <a href="javascript:void(0);" 
                                    class="liquidation-action-btn warning po-details-btn" aria-label="Edit liquidation details"
                                    data-ca-number="${row.ca_number}"
                                    data-po-amount="${row.po_amount || ''}">
                                    <i class="ti ti-receipt-2 f-18"></i>
                                </a>
                            </li>`;
                    }

                    if (currentStatus !== 'Completed' && currentStatus !== 'For PO') {
                        actionButtons += `
                            <li class="list-inline-item" title="Upload/Change File">
                                <a href="javascript:void(0);" 
                                    class="liquidation-action-btn upload-ca-btn" aria-label="Upload or change file"
                                    data-ca-number="${row.ca_number}">
                                    <i class="ti ti-upload f-18"></i>
                                </a>
                            </li>`;
                    }

                    if (row.po_file) {
                        actionButtons += `
                            <li class="list-inline-item" title="View Liquidation Attachment">
                                <a href="javascript:void(0);" 
                                    class="liquidation-action-btn view-pdf-btn" aria-label="View purchase order"
                                    data-filepath="${row.po_file}">
                                    <i class="fa-regular fa-file f-18"></i>
                                </a>
                            </li>`;
                    }

                    if (nextStatus && workflowCanAct(row)) {
                        let btnText = "Approve";
                        if (currentStatus === 'CA Request') btnText = "Department Head Approve";
                        if (currentStatus === 'CA Release') btnText = row.request_type === 'Request for Payment' ? "Enter SAP & Complete" : "Release";
                        if (currentStatus === 'For PO') btnText = "Submit Liquidation";
                        if (currentStatus === 'For Liquidation') btnText = "Department Head Approve";
                        if (currentStatus === 'Liquidated') btnText = "Save SAP";
                        if (currentStatus === 'For GM Approval') btnText = "GM Approve";
                        if (currentStatus === 'For Accounting Approval') btnText = "Accounting Complete";

                        actionButtons += `
                            <li class="list-inline-item" title="${btnText}">
                                <a href="javascript:void(0);" 
                                    class="liquidation-action-btn update-status-btn" aria-label="${btnText}"
                                    data-ca-number="${row.ca_number}"
                                    data-next-status="${nextStatus}">
                                    <i class="ti ti-check f-18"></i>
                                </a>
                            </li>`;
                    }
                    actionButtons += `</ul>`;
                    return actionButtons;
                }
            }
        ]
    });

    $(document).on('click', '.view-ca-details-btn', function() {
        const row = getCaTableRowData(this);
        const details = [
            ['Request Number', row.ca_number],
            ['Request Type', row.request_type || 'Cash Advance'],
            ['Transaction Phase', row.phase_label],
            ['Purchase Requisition', row.purchase_requisition_number],
            ['Status', workflowStatusLabel(row.pr_status, row.request_type)],
            ['Aging', row.pr_date ? (Math.floor((new Date() - new Date(row.pr_date)) / (1000 * 60 * 60 * 24)) + ' day(s)') : 'N/A'],
            ['Request Amount', money(row.ca_amount)],
            ['Actual Amount', money(row.po_amount)],
            ['SAP Document No.', row.sap_document_number],
            ['CA Date', formatDate(row.ca_date, false)],
            ['Department', row.pr_department],
            ['Budget Code', row.budget_code],
            ['Created Date', formatDate(row.pr_date, true)],
            ['Remarks', row.remarks],
            ['Attachment', row.ca_attachment_file],
            ['Liquidation Remarks', row.liquidation_remarks]
        ];

        $('#caDetailsBody').html(details.map(function(item) {
            return detailItem(item[0], item[1]);
        }).join(''));

        new bootstrap.Modal(document.getElementById('caDetailsModal')).show();
    });

    $(document).on('click', '.update-status-btn', function() {
        const caNumber = $(this).data('ca-number');
        const nextStatus = $(this).data('next-status');
        const rowData = getCaTableRowData(this);
        const currentStatus = rowData.pr_status;
        const amount = parseFloat(rowData.ca_amount || 0);

        if (rowData.request_type === 'Cash Advance' && currentStatus === 'For PO' && (!rowData.po_file || rowData.po_file === '')) {
            Swal.fire({
                icon: 'error',
                title: 'Liquidation attachment required',
                text: 'Please upload the liquidation attachment before submitting.'
            });
            return;
        }
        if (rowData.request_type === 'Cash Advance' && currentStatus === 'For PO' && (!rowData.po_amount || parseFloat(rowData.po_amount) <= 0)) {
            Swal.fire({
                icon: 'error',
                title: 'Actual amount required',
                text: 'Please save the actual liquidation amount first.'
            });
            return;
        }

        let confirmText = `Move this request to ${workflowStatusLabel(nextStatus, rowData.request_type)}?`;
        if (currentStatus === 'CA Request') {
            confirmText = 'Approve this request as Department Head?';
        } else if (currentStatus === 'For Accounting Approval' && nextStatus === 'Completed') {
            confirmText = 'Complete this request in Accounting?';
        } else if (currentStatus === 'For PO' && nextStatus === 'For Liquidation') {
            confirmText = 'Submit this Cash Advance liquidation for Department Head approval?';
        }

        if (currentStatus === 'Liquidated') {
            Swal.fire({
                title: 'SAP Document Number',
                input: 'text',
                inputValue: rowData.sap_document_number || '',
                inputPlaceholder: 'Enter SAP Document Number',
                inputAttributes: {
                    maxlength: 255
                },
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Save and proceed',
                inputValidator: (value) => {
                    if (!value || !value.trim()) {
                        return 'SAP Document Number is required.';
                    }
                    return null;
                }
            }).then((result) => {
                if (!result.isConfirmed) return;

                $.ajax({
                    url: 'data/update_ca_status.php',
                    type: 'POST',
                    data: {
                        ca_number: caNumber,
                        new_status: nextStatus,
                        sap_document_number: result.value.trim()
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Success', response.message, 'success');
                            $('#caTable').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to update status.', 'error');
                    }
                });
            });
            return;
        }

        Swal.fire({
            title: 'Confirm Action',
            text: confirmText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, proceed!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'data/update_ca_status.php',
                    type: 'POST',
                    data: {
                        ca_number: caNumber,
                        new_status: nextStatus
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire('Success', response.message, 'success');
                            $('#caTable').DataTable().ajax.reload();
                        } else {
                            Swal.fire('Error', response.message, 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Failed to update status.', 'error');
                    }
                });
            }
        });
    });

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
            page.render({
                canvasContext: ctx,
                viewport: viewport
            });
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

    $(document).on('click', '.view-pdf-btn', function() {
        let filePath = $(this).data('filepath');
        if (!filePath) {
            Swal.fire('Error', 'No file attached to this request.', 'error');
            return;
        }

        // The new script saves it as 'uploads/CA_...', which lives outside the public directory
        if (filePath.startsWith('uploads/')) {
            filePath = '../' + filePath;
        } else if (!filePath.startsWith('../') && !filePath.startsWith('data/')) {
            // Fallback for older formats if any
            filePath = 'data/' + filePath;
        }

        const fileExt = filePath.split('.').pop().toLowerCase();
        if (fileExt === 'pdf') {
            pdfjsLib.getDocument(filePath).promise.then(pdf => {
                pdfDoc = pdf;
                currentPage = 1;
                renderPage(currentPage);
                new bootstrap.Modal(document.getElementById('pdfModal')).show();
            }).catch(error => {
                console.error('PDF load error:', error);
                alert('Failed to load PDF.');
            });
        } else if (['jpg', 'jpeg', 'png'].includes(fileExt)) {
            Swal.fire({
                imageUrl: filePath,
                imageAlt: 'Cash Advance File',
                width: 'auto',
                showCloseButton: true,
                showConfirmButton: false
            });
        } else {
            Swal.fire('Error', 'Unsupported file format.', 'error');
        }
    });

    let poDetailsModal = null;
    $(document).on('click', '.po-details-btn', function() {
        const row = getCaTableRowData(this);
        const caNumber = $(this).data('ca-number') || row.ca_number;
        const poAmount = $(this).data('po-amount') || row.po_amount;
        $('#poModalCaNumber').val(caNumber);
        $('#poModalAmount').val(poAmount || '');
        $('#poModalFile').val('');
        $('#poModalLiquidationRemarks').val(row.liquidation_remarks || '');
        poDetailsModal = new bootstrap.Modal(document.getElementById('poDetailsModal'));
        poDetailsModal.show();
    });

    $('#savePoDetailsBtn').on('click', function() {
        const caNumber = $('#poModalCaNumber').val();
        const poAmount = $('#poModalAmount').val();
        const poFile = $('#poModalFile')[0].files[0];
        const liquidationRemarks = $('#poModalLiquidationRemarks').val();

        if (!caNumber || !poAmount || parseFloat(poAmount) <= 0 || !poFile) {
            Swal.fire('Error', 'Actual amount and liquidation attachment are required.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('ca_number', caNumber);
        formData.append('po_amount', poAmount);
        formData.append('liquidation_remarks', liquidationRemarks);
        formData.append('ca_file', poFile);

        Swal.fire({
            title: 'Saving Liquidation Details...',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'data/upload_ca_file.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.status === 'success') {
                    if (poDetailsModal) poDetailsModal.hide();
                    Swal.fire('Success', response.message, 'success');
                    $('#caTable').DataTable().ajax.reload(null, false);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Failed to save PO details.', 'error');
            }
        });
    });

    // CA File Upload Logic
    let currentCaForUpload = null;
    $(document).on('click', '.upload-ca-btn', function() {
        currentCaForUpload = $(this).data('ca-number');
        $('#globalCaFileInput').click();
    });

    $('#globalCaFileInput').on('change', function() {
        const file = this.files[0];
        if (!file || !currentCaForUpload) return;

        const formData = new FormData();
        formData.append('ca_file', file);
        formData.append('ca_number', currentCaForUpload);

        Swal.fire({
            title: 'Uploading...',
            text: 'Please wait while we upload the file.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: 'data/upload_ca_file.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.status === 'success') {
                    Swal.fire('Success', response.message, 'success');
                    $('#caTable').DataTable().ajax.reload(null, false);
                } else {
                    Swal.fire('Error', response.message, 'error');
                }
                $('#globalCaFileInput').val('');
                currentCaForUpload = null;
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Failed to upload file.', 'error');
                $('#globalCaFileInput').val('');
                currentCaForUpload = null;
            }
        });
    });
</script>

<?php include 'footer.php'; ?>

