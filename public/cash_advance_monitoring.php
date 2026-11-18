<?php
require '../init.php';

if (($_SESSION['department'] ?? '') !== 'PURCHASING') {
    http_response_code(403);
    die('Only Purchasing can view Cash Advance monitoring.');
}

include 'header.php';
include 'navigation.php';
?>

<link rel="stylesheet" href="assets/css/liquidations.css?v=20261001">
<style>
    .liquidation-action-group {
        display: inline-flex;
        flex-wrap: nowrap;
        gap: 6px;
        list-style: none;
        padding: 0;
        white-space: nowrap;
    }

    .liquidation-action-group .list-inline-item { margin: 0; }

    .ca-monitor-table-card .card-body { min-width: 0; }
    .ca-monitor-table-card .dataTables_wrapper { width: 100%; }
    .ca-monitor-table-card .dataTables_scrollBody { overflow-x: auto !important; }

    .ca-monitor-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 14px;
    }

    .ca-monitor-card {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        background: #fff;
        box-shadow: 0 8px 24px rgba(16, 24, 40, 0.05);
        padding: 18px;
        min-height: 118px;
    }

    .ca-monitor-card .label {
        font-size: 12px;
        color: #667085;
        margin-bottom: 8px;
    }

    .ca-monitor-card .value {
        font-size: 24px;
        font-weight: 700;
        line-height: 1.1;
    }

    .ca-monitor-card .amount {
        font-size: 12px;
        color: #667085;
        margin-top: 8px;
    }

    .ca-monitor-toolbar {
        display: grid;
        grid-template-columns: minmax(180px, 1fr) repeat(2, minmax(150px, 180px)) auto;
        gap: 10px;
        align-items: end;
        padding: 20px 22px;
    }

    .ca-monitor-table-card .dataTables_scrollHead th { white-space: nowrap; }
    .ca-monitor-table-card .liquidation-action-group { justify-content: flex-start; }
    .ca-monitor-table-card .liquidation-action-btn { flex: 0 0 36px; }

    @media (max-width: 768px) {
        .ca-monitor-toolbar {
            grid-template-columns: 1fr;
            padding: 18px;
        }

        .ca-monitor-toolbar .btn { width: 100%; }

        table.dataTable td {
            white-space: normal !important;
        }
    }

</style>

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0">Purchasing Transaction Monitoring</h2>
                                <p class="liquidation-subtitle">Track Cash Advances, RFPs, and Liquidations through the current workflow.</p>
                            </div>
                            <a href="add_cash_advance" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-plus f-18"></i> Add Request
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12">
                <div class="card liquidation-card">
                    <div class="card-header">
                        <div>
                            <h5 class="mb-0">Monitoring Filters</h5>
                            <p class="liquidation-toolbar-subtitle">Filter the register by workflow status or request date.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="ca-monitor-toolbar">
                            <div>
                                <label for="statusFilter" class="form-label">Status</label>
                                <select class="form-select" id="statusFilter">
                                    <option value="">All Statuses</option>
                                    <option value="CA Request">For Department Head Approval</option>
                                    <option value="CA Release">For Release</option>
                                    <option value="For PO">For Liquidation</option>
                                    <option value="For Liquidation">For Department Head Approval (Liquidation)</option>
                                    <option value="Liquidated">For SAP Document Number</option>
                                    <option value="For GM Approval">For GM Approval</option>
                                    <option value="For Accounting Approval">For Accounting Completion</option>
                                    <option value="Completed">Completed</option>
                                </select>
                            </div>
                            <div>
                                <label for="dateFrom" class="form-label">Request Date From</label>
                                <input type="date" class="form-control" id="dateFrom">
                            </div>
                            <div>
                                <label for="dateTo" class="form-label">Request Date To</label>
                                <input type="date" class="form-control" id="dateTo">
                            </div>
                            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center justify-content-center gap-2" id="resetFiltersBtn" title="Clear filters">
                                <i class="ti ti-filter-off f-18"></i> Clear
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="ca-monitor-summary" id="totalSummary">
                    <div class="ca-monitor-card">
                        <div class="label">Total Requests</div>
                        <div class="value" id="totalCount">0</div>
                        <div class="amount" id="totalCaAmount">PHP 0.00</div>
                    </div>
                    <div class="ca-monitor-card">
                        <div class="label">Open Requests</div>
                        <div class="value" id="openCount">0</div>
                        <div class="amount">Not yet completed</div>
                    </div>
                    <div class="ca-monitor-card">
                        <div class="label">Completed Requests</div>
                        <div class="value" id="completedCount">0</div>
                        <div class="amount">Closed requests</div>
                    </div>
                    <div class="ca-monitor-card">
                        <div class="label">Total Actual Amount</div>
                        <div class="value" id="totalPoAmount">PHP 0.00</div>
                        <div class="amount">Submitted CA liquidation totals</div>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="ca-monitor-summary" id="statusSummary"></div>
            </div>

            <div class="col-12">
                <div class="card liquidation-card ca-monitor-table-card">
                    <div class="card-header">
                        <div>
                            <h5 class="mb-0">Purchasing Transaction Register</h5>
                            <p class="liquidation-toolbar-subtitle">Open a record for details or use the document actions.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="display nowrap liquidation-table" id="caMonitoringTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Aging</th>
                                    <th>Request Type</th>
                                    <th>Request No.</th>
                                    <th>Status</th>
                                    <th>Next Approver</th>
                                    <th>Request Amount</th>
                                    <th>Request Date</th>
                                    <th>Department</th>
                                    <th>Created By</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="caDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Purchasing Transaction Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="caDetailsBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    const statusTone = {
        'CA Request': 'info',
        'CA Release': 'primary',
        'For PO': 'warning',
        'For Liquidation': 'secondary',
        'Liquidated': 'secondary',
        'For GM Approval': 'danger',
        'For Accounting Approval': 'primary',
        'Completed': 'success'
    };

    let caMonitoringTable = null;
    const liquidationVisibleStatuses = [
        'For PO',
        'For Liquidation',
        'Liquidated',
        'For GM Approval',
        'For SAP Document',
        'For Accounting Approval',
        'Completed'
    ];

    function money(value) {
        return 'PHP ' + parseFloat(value || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function formatDate(value) {
        if (!value) return 'N/A';
        return new Date(value).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
    }

    function displayStatus(status, requestType) {
        const labels = {
            'CA Request': 'For Department Head Approval',
            'CA Release': 'For Release',
            'For PO': 'For Liquidation',
            'For Liquidation': 'For Department Head Approval (Liquidation)',
            'For Accounting Approval': 'For Accounting Completion'
        };
        if (status === 'Liquidated') return 'For SAP Document Number';
        return labels[status] || status;
    }

    function statusBadge(status, requestType) {
        const tone = statusTone[status] || 'primary';
        const classes = { primary: 'status-info', info: 'status-info', warning: 'status-warning', danger: 'status-danger', success: 'status-success', secondary: 'status-neutral' };
        return `<span class="liquidation-status ${classes[tone] || 'status-neutral'}">${escapeHtml(displayStatus(status || 'N/A', requestType))}</span>`;
    }

    function requestTypeBadge(value, phaseLabel) {
        const type = value || 'Cash Advance';
        const tone = type === 'Request for Payment' ? 'status-success' : 'status-info';
        return `<span class="liquidation-status ${tone}">${escapeHtml(type)}</span>${phaseLabel ? `<span class="liquidation-status status-warning mt-1">${escapeHtml(phaseLabel)}</span>` : ''}`;
    }

    function caLiquidationFormUrl(caNumber) {
        return 'print_cash_advance_liquidation.php?ca_number=' + encodeURIComponent(caNumber || '');
    }

    function attachmentUrl(path) {
        if (!path) return '';
        if (path.startsWith('uploads/')) {
            return '../' + path;
        }
        if (path.startsWith('../') || path.startsWith('data/')) {
            return path;
        }
        return 'data/' + path;
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
                <div class="border rounded p-2 h-100">
                    <div class="text-muted small">${escapeHtml(label)}</div>
                    <div class="fw-semibold">${escapeHtml(value || 'N/A')}</div>
                </div>
            </div>`;
    }

    function currentFilters() {
        return {
            status: $('#statusFilter').val(),
            date_from: $('#dateFrom').val(),
            date_to: $('#dateTo').val()
        };
    }

    function renderStatusSummary(summary) {
        const html = (summary || []).map(row => {
            const tone = statusTone[row.status] || 'primary';
            return `
                <div class="ca-monitor-card">
                    <div class="label">${escapeHtml(row.display_status || row.status)}</div>
                    <div class="value text-${tone}">${row.count}</div>
                    <div class="amount">${money(row.ca_total)}</div>
                </div>`;
        }).join('');

        $('#statusSummary').html(html);
    }

    function updateTotals(totals) {
        $('#totalCount').text(totals.total_count || 0);
        $('#openCount').text(totals.open_count || 0);
        $('#completedCount').text(totals.completed_count || 0);
        $('#totalCaAmount').text(money(totals.total_ca_amount || 0));
        $('#totalPoAmount').text(money(totals.total_po_amount || 0));
    }

    function loadMonitoring() {
        $.ajax({
            url: 'data/get_cash_advance_monitoring.php',
            data: currentFilters(),
            dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') {
                    Swal.fire('Error', response.message || 'Failed to load Cash Advance monitoring.', 'error');
                    return;
                }

                updateTotals(response.totals || {});
                renderStatusSummary(response.summary || []);

                const rows = response.data || [];
                const visibleRows = $('#statusFilter').val() === ''
                    ? rows.filter(row => row.pr_status !== 'Completed')
                    : rows;
                caMonitoringTable.clear();
                caMonitoringTable.rows.add(visibleRows);
                caMonitoringTable.draw();
            },
            error: function() {
                Swal.fire('Error', 'Failed to load Cash Advance monitoring.', 'error');
            }
        });
    }

    $(function() {
        caMonitoringTable = $('#caMonitoringTable').DataTable({
            scrollX: true,
            autoWidth: false,
            data: [],
            order: [
                [6, 'desc']
            ],
            columns: [
                {
                    data: 'aging',
                    render: function(value, type) {
                        const days = parseInt(value || 0, 10);
                        if (type !== 'display') return days;
                        const tone = days > 30 ? 'status-danger' : (days > 14 ? 'status-warning' : 'status-neutral');
                        return `<span class="liquidation-status ${tone}">${days} day${days === 1 ? '' : 's'}</span>`;
                    }
                },
                {
                    data: 'request_type',
                    render: function(value, type, row) { return type === 'display' ? requestTypeBadge(value, row.phase_label) : value; }
                },
                {
                    data: 'ca_number',
                    render: function(value, type) {
                        return type === 'display' ? `<span class="liquidation-primary-cell">${escapeHtml(value || 'N/A')}</span>` : value;
                    }
                },
                {
                    data: 'pr_status',
                    render: function(value, type, row) { return statusBadge(value, row.request_type); }
                },
                {
                    data: 'next_approver',
                    render: function(value) {
                        return value || 'N/A';
                    }
                },
                
                {
                    data: 'ca_amount',
                    render: money
                },
                {
                    data: 'ca_date',
                    render: formatDate
                },
                {
                    data: 'pr_department'
                },
                {
                    data: 'created_by_name',
                    render: function(value) {
                        return value || 'N/A';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(row) {
                        const caNumber = row.ca_number || '';
                        return `<div class="liquidation-action-group">
                            <button type="button" class="liquidation-action-btn view-ca-details-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                            ${row.purchase_requisition_number ? `
                            <a href="view_purchase_requisition?pr_number=${encodeURIComponent(row.purchase_requisition_number)}" target="_blank" rel="noopener" class="liquidation-action-btn" title="View purchase requisition" aria-label="View purchase requisition"><i class="ti ti-external-link f-18"></i></a>` : ''}
                            <a href="print_cash_advance.php?ca_number=${encodeURIComponent(caNumber)}" target="_blank" rel="noopener" class="liquidation-action-btn" title="View request form" aria-label="View request form"><i class="ti ti-file-text f-18"></i></a>
                            ${liquidationVisibleStatuses.includes(row.pr_status) ? `
                            <a href="${caLiquidationFormUrl(caNumber)}" target="_blank" rel="noopener" class="liquidation-action-btn warning" title="View liquidation form" aria-label="View liquidation form"><i class="ti ti-receipt f-18"></i></a>` : ''}
                            ${row.po_file ? `
                            <a href="${escapeHtml(attachmentUrl(row.po_file))}" target="_blank" rel="noopener" class="liquidation-action-btn" title="View liquidation attachment" aria-label="View liquidation attachment"><i class="ti ti-file-invoice f-18"></i></a>` : ''}
                        </div>`;
                    }
                }
            ]
        });

        $(document).on('click', '.view-ca-details-btn', function() {
            const row = caMonitoringTable.row($(this).closest('tr')).data() || {};
            const details = [
                ['Request Number', row.ca_number],
                ['Request Type', row.request_type || 'Cash Advance'],
                ['Transaction Phase', row.phase_label],
                ['Purchase Requisition', row.purchase_requisition_number],
                ['Status', displayStatus(row.pr_status, row.request_type)],
                ['Next Approver', row.next_approver],
                ['Aging', (parseInt(row.aging || 0, 10)) + ' day' + (parseInt(row.aging || 0, 10) === 1 ? '' : 's')],
                ['Request Amount', money(row.ca_amount)],
                ['Actual Amount', row.po_amount ? money(row.po_amount) : 'N/A'],
                ['Liquidation Attachment', row.po_file || 'N/A'],
                ['SAP Document No.', row.sap_document_number],
                ['Request Date', formatDate(row.ca_date)],
                ['Department', row.pr_department],
                ['Budget Code', row.budget_code],
                ['Dealer', row.pr_dealer],
                ['Created By', row.created_by_name],
                ['Created Date', formatDate(row.pr_date)],
                ['Remarks', row.remarks],
                ['Liquidation Remarks', row.liquidation_remarks]
            ];

            $('#caDetailsBody').html(details.map(function(item) {
                return detailItem(item[0], item[1]);
            }).join(''));

            new bootstrap.Modal(document.getElementById('caDetailsModal')).show();
        });

        $('#statusFilter, #dateFrom, #dateTo').on('change', loadMonitoring);
        $('#resetFiltersBtn').on('click', function() {
            $('#statusFilter').val('');
            $('#dateFrom').val('');
            $('#dateTo').val('');
            loadMonitoring();
        });

        loadMonitoring();
    });
</script>

<?php include 'footer.php'; ?>

