<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

if (!liquidation_is_accounting_user()) {
    http_response_code(403);
    die('Only Accounting/Finance can view Cash Advance monitoring.');
}

include 'header.php';
include 'navigation.php';
?>

<link rel="stylesheet" href="assets/css/liquidations.css?v=20260820">

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0">Cash Advance Monitoring</h2>
                                <p class="liquidation-subtitle">Monitor every department's cash advances, outstanding exposure, aging, and liquidation progress.</p>
                            </div>
                            <button type="button" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2" id="refreshMonitoringBtn">
                                <i class="ti ti-refresh f-18"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="monitoring-summary-grid mb-4">
            <div class="monitoring-summary-card">
                <span class="monitoring-summary-icon status-info"><i class="ti ti-wallet f-22"></i></span>
                <div><span class="monitoring-summary-label">Total Cash Advances</span><strong id="summaryTotalCount">0</strong><small id="summaryTotalAmount">&#8369;0.00</small></div>
            </div>
            <div class="monitoring-summary-card">
                <span class="monitoring-summary-icon status-danger"><i class="ti ti-alert-circle f-22"></i></span>
                <div><span class="monitoring-summary-label">Outstanding</span><strong id="summaryOutstandingCount">0</strong><small id="summaryOutstandingAmount">&#8369;0.00</small></div>
            </div>
            <div class="monitoring-summary-card">
                <span class="monitoring-summary-icon status-warning"><i class="ti ti-hourglass f-22"></i></span>
                <div><span class="monitoring-summary-label">For Liquidation</span><strong id="summaryForLiquidationCount">0</strong><small id="summaryForLiquidationAmount">&#8369;0.00</small></div>
            </div>
            <div class="monitoring-summary-card">
                <span class="monitoring-summary-icon status-success"><i class="ti ti-circle-check f-22"></i></span>
                <div><span class="monitoring-summary-label">Liquidated</span><strong id="summaryLiquidatedCount">0</strong><small id="summaryLiquidatedAmount">&#8369;0.00 actual</small></div>
            </div>
        </div>

        <div class="card liquidation-card mb-4">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Monitoring Filters</h5>
                    <p class="liquidation-toolbar-subtitle">Summary cards and records update together as filters change.</p>
                </div>
            </div>
            <div class="card-body">
                <div class="monitoring-filter-grid">
                    <div>
                        <label for="monitoringStatusFilter" class="form-label">Monitoring Status</label>
                        <select class="form-select" id="monitoringStatusFilter">
                            <option value="">All Statuses</option>
                            <option value="Awaiting Release">Awaiting Release</option>
                            <option value="For Liquidation">For Liquidation</option>
                            <option value="Liquidation In Progress">Liquidation In Progress</option>
                            <option value="Liquidation Rejected">Liquidation Rejected</option>
                            <option value="Liquidated">Liquidated</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div>
                        <label for="departmentFilter" class="form-label">Department</label>
                        <select class="form-select" id="departmentFilter"><option value="">All Departments</option></select>
                    </div>
                    <div>
                        <label for="dateFromFilter" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="dateFromFilter">
                    </div>
                    <div>
                        <label for="dateToFilter" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="dateToFilter">
                    </div>
                    <button type="button" class="btn btn-outline-secondary" id="resetMonitoringFiltersBtn" title="Reset filters">
                        <i class="ti ti-filter-off f-18"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="card liquidation-card">
            <div class="card-header">
                <div>
                    <h5 class="mb-0">Cash Advance Register</h5>
                    <p class="liquidation-toolbar-subtitle">Open a record to review its complete request and linked liquidation details.</p>
                </div>
            </div>
            <div class="card-body">
                <table class="display responsive nowrap liquidation-table" id="accountingCaTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Aging</th>
                            <th>Cash Advance No.</th>
                            <th>Amount</th>
                            <th>Monitoring Status</th>
                            <th>Linked Liquidation</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="accountingCaDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Cash Advance Details</h5>
                    <p class="liquidation-toolbar-subtitle">Request, release, and linked liquidation information.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3" id="accountingCaDetailsBody"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="accountingCaDocumentLink">
                    <i class="ti ti-file-text f-18"></i> View Document
                </a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="accountingAttachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="accountingAttachmentTitle">Attachment Preview</h5>
                    <p class="liquidation-toolbar-subtitle">Supporting document for the selected record.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body attachment-preview-body">
                <img src="" alt="Attachment preview" class="attachment-preview-image d-none" id="accountingAttachmentImage">
                <iframe src="about:blank" title="Attachment preview" class="attachment-preview-frame d-none" id="accountingAttachmentFrame"></iframe>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
    let accountingCaTable = null;
    let selectedAccountingCa = null;

    function money(value) {
        return '\u20B1' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatDate(value) {
        if (!value) return 'N/A';
        return new Date(value).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' });
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function attachmentUrl(path) {
        if (!path) return '';
        return path.startsWith('uploads/') ? '../' + path : path;
    }

    function statusBadge(status) {
        const tones = {
            'Awaiting Release': 'status-info',
            'For Liquidation': 'status-warning',
            'Liquidation In Progress': 'status-info',
            'Liquidation Rejected': 'status-danger',
            'Liquidated': 'status-success',
            'Rejected': 'status-danger'
        };
        return `<span class="liquidation-status ${tones[status] || 'status-neutral'}">${escapeHtml(status || 'N/A')}</span>`;
    }

    function rowFromAction(element) {
        let $row = $(element).closest('tr');
        if ($row.hasClass('child')) $row = $row.prev();
        return accountingCaTable.row($row).data() || {};
    }

    function showAttachment(path, title) {
        const url = attachmentUrl(path);
        const isImage = /\.(jpe?g|png)(?:[?#].*)?$/i.test(url);
        $('#accountingAttachmentTitle').text(title || 'Attachment Preview');
        $('#accountingAttachmentImage').attr('src', isImage ? url : '').toggleClass('d-none', !isImage);
        $('#accountingAttachmentFrame').attr('src', isImage ? 'about:blank' : url).toggleClass('d-none', isImage);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('accountingAttachmentModal')).show();
    }

    function detailItem(label, value, width) {
        return `<div class="${width || 'col-md-6'}"><span class="detail-label">${escapeHtml(label)}</span><span class="detail-value">${escapeHtml(value || 'N/A')}</span></div>`;
    }

    function populateDepartments(rows) {
        const departments = [...new Set(rows.map(row => row.department).filter(Boolean))].sort();
        $('#departmentFilter').html('<option value="">All Departments</option>' + departments.map(value => `<option value="${escapeHtml(value)}">${escapeHtml(value)}</option>`).join(''));
    }

    function updateSummary() {
        if (!accountingCaTable) return;
        const rows = accountingCaTable.rows({ search: 'applied' }).data().toArray();
        const totalAmount = rows.reduce((sum, row) => sum + Number(row.liquidation_amount || 0), 0);
        const outstandingStatuses = ['For Liquidation', 'Liquidation In Progress', 'Liquidation Rejected'];
        const outstanding = rows.filter(row => outstandingStatuses.includes(row.monitoring_status));
        const forLiquidation = rows.filter(row => row.monitoring_status === 'For Liquidation');
        const liquidated = rows.filter(row => row.monitoring_status === 'Liquidated');

        $('#summaryTotalCount').text(rows.length);
        $('#summaryTotalAmount').text(money(totalAmount));
        $('#summaryOutstandingCount').text(outstanding.length);
        $('#summaryOutstandingAmount').text(money(outstanding.reduce((sum, row) => sum + Number(row.outstanding_amount || 0), 0)));
        $('#summaryForLiquidationCount').text(forLiquidation.length);
        $('#summaryForLiquidationAmount').text(money(forLiquidation.reduce((sum, row) => sum + Number(row.liquidation_amount || 0), 0)));
        $('#summaryLiquidatedCount').text(liquidated.length);
        $('#summaryLiquidatedAmount').text(money(liquidated.reduce((sum, row) => sum + Number(row.linked_actual_amount || row.linked_liquidation_amount || 0), 0)) + ' actual');
    }

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        if (settings.nTable.id !== 'accountingCaTable' || !accountingCaTable) return true;
        const row = accountingCaTable.row(dataIndex).data();
        if (!row) return true;

        const status = $('#monitoringStatusFilter').val();
        const department = $('#departmentFilter').val();
        const from = $('#dateFromFilter').val();
        const to = $('#dateToFilter').val();
        const date = row.liquidation_date || '';

        return (!status || row.monitoring_status === status)
            && (!department || row.department === department)
            && (!from || date >= from)
            && (!to || date <= to);
    });

    function loadMonitoring() {
        $('#refreshMonitoringBtn').prop('disabled', true);
        $.ajax({
            url: 'data/get_accounting_cash_advance_monitoring.php',
            dataType: 'json',
            success: function(response) {
                if (response.status !== 'success') {
                    Swal.fire('Error', response.message || 'Failed to load cash advance monitoring.', 'error');
                    return;
                }
                const rows = response.data || [];
                populateDepartments(rows);
                accountingCaTable.clear().rows.add(rows).draw();
            },
            error: function(xhr) {
                Swal.fire('Error', xhr.responseJSON?.message || 'Failed to load cash advance monitoring.', 'error');
            },
            complete: function() {
                $('#refreshMonitoringBtn').prop('disabled', false);
            }
        });
    }

    $(function() {
        accountingCaTable = $('#accountingCaTable').DataTable({
            responsive: true,
            data: [],
            order: [[0, 'desc']],
            columns: [
                {
                    data: 'aging_days',
                    render: function(value, type) {
                        const days = Number(value || 0);
                        if (type !== 'display') return days;
                        const tone = days > 30 ? 'status-danger' : (days > 14 ? 'status-warning' : 'status-neutral');
                        return `<span class="liquidation-status ${tone}">${days} day${days === 1 ? '' : 's'}</span>`;
                    }
                },
                {
                    data: 'liquidation_number',
                    render: function(value, type, row) {
                        if (type !== 'display') return value;
                        return `<span class="liquidation-primary-cell">${escapeHtml(value || 'N/A')}</span><span class="liquidation-meta">${escapeHtml(row.department || 'No department')}</span>`;
                    }
                },
                { data: 'liquidation_amount', render: money },
                { data: 'monitoring_status', render: statusBadge },
                {
                    data: 'linked_liquidation_number',
                    render: function(value, type, row) {
                        if (type !== 'display') return value || '';
                        if (!value) return '<span class="liquidation-meta">Not submitted</span>';
                        return `<span class="liquidation-primary-cell">${escapeHtml(value)}</span><span class="liquidation-meta">${escapeHtml(row.linked_liquidation_status || '')}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    className: 'text-center',
                    render: function(row) {
                        let actions = `<div class="liquidation-action-group">
                            <button type="button" class="liquidation-action-btn view-accounting-ca-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                            <a href="print_liquidation.php?id=${row.liquidation_id}" target="_blank" class="liquidation-action-btn" title="View document" aria-label="View document"><i class="ti ti-file-text f-18"></i></a>`;
                        if (row.attachment_path) actions += '<button type="button" class="liquidation-action-btn success preview-ca-attachment-btn" title="Preview cash advance attachment" aria-label="Preview cash advance attachment"><i class="ti ti-paperclip f-18"></i></button>';
                        if (row.linked_attachment_path) actions += '<button type="button" class="liquidation-action-btn warning preview-liquidation-attachment-btn" title="Preview liquidation attachment" aria-label="Preview liquidation attachment"><i class="ti ti-receipt f-18"></i></button>';
                        return actions + '</div>';
                    }
                }
            ]
        });

        accountingCaTable.on('draw', updateSummary);
        loadMonitoring();
    });

    $('#monitoringStatusFilter, #departmentFilter, #dateFromFilter, #dateToFilter').on('change', function() {
        accountingCaTable.draw();
    });

    $('#resetMonitoringFiltersBtn').on('click', function() {
        $('#monitoringStatusFilter, #departmentFilter, #dateFromFilter, #dateToFilter').val('');
        accountingCaTable.draw();
    });

    $('#refreshMonitoringBtn').on('click', loadMonitoring);

    $(document).on('click', '.view-accounting-ca-btn', function() {
        const row = rowFromAction(this);
        selectedAccountingCa = row;
        const details = [
            ['Cash Advance Number', row.liquidation_number], ['Monitoring Status', row.monitoring_status],
            ['Workflow Status', row.status],
            ['Department', row.department], ['Dealer', row.dealer], ['Budget Code', row.budget_code],
            ['Budget Activity', row.budget_activity], ['Cash Advance Amount', money(row.liquidation_amount)],
            ['Request Date', formatDate(row.liquidation_date)], ['Activity Date', formatDate(row.activity_date)],
            ['Submitted', formatDate(row.submitted_at)], ['Released/Completed', formatDate(row.completed_at)],
            ['Aging', `${Number(row.aging_days || 0)} day${Number(row.aging_days || 0) === 1 ? '' : 's'}`],
            ['Created By', row.created_by_name], ['Remarks', row.remarks],
            ['Liquidation Number', row.linked_liquidation_number], ['Liquidation Status', row.linked_liquidation_status],
            ['Actual Amount', row.linked_liquidation_id ? money(row.linked_actual_amount || row.linked_liquidation_amount) : 'N/A'],
            ['Balance', row.liquidation_balance == null ? 'N/A' : money(row.liquidation_balance)],
            ['SAP Document Number', row.linked_sap_document_number], ['Liquidation Date', formatDate(row.linked_liquidation_date)],
            ['Liquidation Remarks', row.linked_liquidation_remarks]
        ];
        $('#accountingCaDetailsBody').html(details.map(item => detailItem(item[0], item[1], item[0].includes('Remarks') ? 'col-12' : 'col-md-6')).join(''));
        $('#accountingCaDocumentLink').attr('href', `print_liquidation.php?id=${row.liquidation_id}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('accountingCaDetailsModal')).show();
    });

    $(document).on('click', '.preview-ca-attachment-btn', function() {
        const row = rowFromAction(this);
        showAttachment(row.attachment_path, `Cash Advance Attachment - ${row.liquidation_number}`);
    });

    $(document).on('click', '.preview-liquidation-attachment-btn', function() {
        const row = rowFromAction(this);
        showAttachment(row.linked_attachment_path, `Liquidation Attachment - ${row.linked_liquidation_number}`);
    });

    $('#accountingAttachmentModal').on('hidden.bs.modal', function() {
        $('#accountingAttachmentImage').attr('src', '');
        $('#accountingAttachmentFrame').attr('src', 'about:blank');
    });
</script>

<?php include 'footer.php'; ?>
