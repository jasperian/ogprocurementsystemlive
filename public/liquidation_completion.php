<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

if (!liquidation_is_accounting_user()) {
    http_response_code(403);
    die('Only Accounting/Finance can complete liquidations.');
}

include 'header.php';
include 'navigation.php';
?>

<link rel="stylesheet" href="assets/css/liquidations.css?v=20260822">

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0">Accounting Completion</h2>
                                <p class="liquidation-subtitle">Process SAP document number, release cash advances, and review financial requests.</p>
                            </div>
                            <a href="accounting_cash_advance_monitoring" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-chart-bar f-18"></i> Monitoring
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card liquidation-card">
                    <div class="card-header">
                        <div class="mb-3">
                            <h5 class="mb-0">Accounting Queue</h5>
                            <p class="liquidation-toolbar-subtitle">Switch views to process pending records or review transactions.</p>
                        </div>
                        <ul class="nav nav-tabs liquidation-tabs" id="completionTabs">
                            <li class="nav-item">
                                <button class="nav-link active completion-tab" type="button" data-status="Liquidated" data-type="">
                                    Pending SAP / CA Release
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link completion-tab" type="button" data-status="For Accounting Approval" data-type="">
                                    Pending Accounting Completion
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link completion-tab" type="button" data-status="Completed" data-type="Cash Advance">
                                    Released Cash Advances
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link completion-tab" type="button" data-status="Completed" data-type="Liquidation">
                                    Completed Liquidations
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link completion-tab" type="button" data-status="Completed" data-type="Request for Payment">
                                    Completed RFPs
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <table class="display responsive nowrap liquidation-table" id="completionTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Processed Date</th>
                                    <th>Request No.</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>SAP Doc No.</th>
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

<div class="modal fade liquidation-modal" id="completionDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Request Details</h5>
                    <p class="liquidation-toolbar-subtitle">Complete accounting and request information.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><div class="row g-3" id="completionDetailsBody"></div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="completionDocumentLink"><i class="ti ti-file-text f-18"></i> View Document</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="completionAttachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title" id="completionAttachmentTitle">Attachment Preview</h5><p class="liquidation-toolbar-subtitle">Supporting document for the selected request.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body attachment-preview-body">
                <img src="" alt="Attachment preview" class="attachment-preview-image d-none" id="completionAttachmentImage">
                <iframe src="about:blank" title="Attachment preview" class="attachment-preview-frame d-none" id="completionAttachmentFrame"></iframe>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
    let selectedStatus = 'Liquidated';
    let selectedRequestType = '';
    const isAccountingOrFinanceUser = <?= liquidation_is_accounting_user() ? 'true' : 'false' ?>;
    const statusDisplay = {
        'Liquidated': 'Recommended',
        'Completed': 'Liquidated'
    };

    function updateAccountingPendingCount(count) {
        const $badge = $('.js-liquidation-accounting-pending');
        $badge.text(count);
        $badge.toggleClass('d-none', count <= 0);
    }

    function refreshAccountingPendingCount() {
        $.ajax({
            url: 'data/get_liquidations.php',
            type: 'GET',
            dataType: 'json',
            data: {
                mode: 'accounting'
            },
            success: function(json) {
                const rows = json.status === 'success' ? (json.data || []) : [];
                updateAccountingPendingCount(rows.length);
            }
        });
    }

    function money(data) {
        return '\u20B1 ' + parseFloat(data || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2
        });
    }

    function formatDate(data) {
        if (!data) return '';
        return new Date(data).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: '2-digit'
        });
    }

    function attachmentUrl(path) {
        if (!path) return '';
        return path.startsWith('uploads/') ? '../' + path : path;
    }

    function escapeHtml(value) {
        return $('<div>').text(value == null ? '' : String(value)).html();
    }

    function detailItem(label, value, width) {
        return `<div class="${width || 'col-md-6'}"><span class="detail-label">${escapeHtml(label)}</span><span class="detail-value">${escapeHtml(value || 'N/A')}</span></div>`;
    }

    function displayStatus(row) {
        if (row.request_type === 'Cash Advance') {
            if (row.status === 'Liquidated') return 'For Release';
            if (row.status === 'Completed') return 'Released';
        }

        const requestType = row.request_type || 'Liquidation';
        if (Number(row.workflow_version || 1) >= 2 && requestType === 'Request for Payment') {
            if (row.status === 'For Accounting Approval') return 'For Accounting Completion';
            if (row.status === 'Completed') return 'Completed';
        }
        const usesLiquidationLabels = requestType === 'Liquidation' || requestType === 'Request for Payment';
        if (
            isAccountingOrFinanceUser &&
            usesLiquidationLabels &&
            row.status === 'Liquidated'
        ) {
            return 'For SAP Document Number';
        }

        if (usesLiquidationLabels && statusDisplay[row.status]) {
            return statusDisplay[row.status];
        }
        if (row.status === 'Liquidated') return 'For SAP Document';
        return row.status || '';
    }

    const completionTable = $('#completionTable').DataTable({
        ajax: {
            url: 'data/get_liquidations.php',
            data: function(d) {
                d.mode = 'accounting';
                d.status = selectedStatus;
                d.request_type = selectedRequestType;
            },
            dataSrc: function(json) {
                const rows = json.status === 'success' ? (json.data || []) : [];
                return rows;
            }
        },
        responsive: true,
        order: [
            [0, 'desc']
        ],
        columns: [{
                data: null,
                render: function(row) {
                    return formatDate(row.status === 'Completed'
                        ? row.completed_at
                        : (row.approved_at || row.liquidation_approved_at || row.department_approved_at || row.submitted_at));
                }
            },
            {
                data: 'liquidation_number',
                render: function(data, type, row) {
                    if (type !== 'display') return data;
                    const requestType = row.source_cash_advance_number ? 'Cash Advance Liquidation' : (row.request_type || 'Liquidation');
                    return `<span class="liquidation-primary-cell">${escapeHtml(data || 'N/A')}</span><span class="liquidation-meta">${escapeHtml(requestType)} &bull; ${escapeHtml(row.department || 'No department')}</span>`;
                }
            },
            {
                data: 'liquidation_amount',
                render: function(data, type, row) {
                    if (type !== 'display') return Number(data || 0);
                    const actual = row.actual_amount ? `<span class="liquidation-meta">Actual: ${money(row.actual_amount)}</span>` : '';
                    return `<span class="liquidation-primary-cell">${money(data)}</span>${actual}`;
                }
            },
            {
                data: null,
                render: function(row) {
                    let badge = row.status === 'Completed' ? 'status-success' : 'status-warning';
                    if (row.status === 'For Accounting Approval') badge = 'status-info';
                    return `<span class="liquidation-status ${badge}">${escapeHtml(displayStatus(row))}</span>`;
                }
            },
            {
                data: 'sap_document_number',
                render: function(data, type, row) {
                    if (Number(row.workflow_version || 1) >= 2 && row.request_type === 'Request for Payment') {
                        return '';
                    }
                    return escapeHtml(data || 'N/A');
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(row) {
                    let buttons = `<div class="liquidation-action-group">
                        <button type="button" class="liquidation-action-btn view-completion-details-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                        <a href="print_liquidation.php?id=${row.liquidation_id}" target="_blank" class="liquidation-action-btn" title="View document" aria-label="View document"><i class="ti ti-file-text f-18"></i></a>`;

                    if (row.attachment_path) {
                        buttons += `<button type="button" class="liquidation-action-btn success view-completion-attachment-btn" title="Preview attachment" aria-label="Preview attachment"><i class="ti ti-paperclip f-18"></i></button>`;
                    }

                    if (row.status === 'Liquidated') {
                        if (row.request_type === 'Cash Advance') {
                            if (row.can_accounting_complete) {
                                buttons += `<button type="button" class="liquidation-action-btn success complete-liq-btn" data-id="${row.liquidation_id}" title="Release cash advance" aria-label="Release cash advance"><i class="ti ti-checks f-18"></i></button>`;
                            }
                        } else if (row.requires_sap && row.can_encode_sap) {
                            buttons += `<button type="button" class="liquidation-action-btn warning sap-liq-btn" data-id="${row.liquidation_id}" title="Save SAP document number" aria-label="Save SAP document number"><i class="ti ti-file-invoice f-18"></i></button>`;
                        }
                    }

                    if (row.status === 'For Accounting Approval' && row.can_accounting_complete) {
                        buttons += `<button type="button" class="liquidation-action-btn success complete-liq-btn" data-id="${row.liquidation_id}" title="Accounting manager approval" aria-label="Accounting manager approval"><i class="ti ti-checks f-18"></i></button>`;
                    }

                    buttons += '</div>';
                    return buttons;
                }
            }
        ]
    });

    function completionRowFromAction(element) {
        let $row = $(element).closest('tr');
        if ($row.hasClass('child')) $row = $row.prev();
        return completionTable.row($row).data() || {};
    }

    function showCompletionAttachment(row) {
        const url = attachmentUrl(row.attachment_path);
        const isImage = /\.(jpe?g|png)(?:[?#].*)?$/i.test(url);
        $('#completionAttachmentTitle').text(`Attachment - ${row.liquidation_number || 'Request'}`);
        $('#completionAttachmentImage').attr('src', isImage ? url : '').toggleClass('d-none', !isImage);
        $('#completionAttachmentFrame').attr('src', isImage ? 'about:blank' : url).toggleClass('d-none', isImage);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('completionAttachmentModal')).show();
    }

    $(document).on('click', '.view-completion-details-btn', function() {
        const row = completionRowFromAction(this);
        const requestType = row.source_cash_advance_number ? 'Cash Advance Liquidation' : (row.request_type || 'Liquidation');
        const details = [
            ['Request Number', row.liquidation_number], ['Request Type', requestType],
            ['Series Prefix', row.series_prefix], ['Source Cash Advance', row.source_cash_advance_number], ['Status', displayStatus(row)],
            ['Department', row.department], ['Dealer', row.dealer], ['Budget Code', row.budget_code],
            ['Budget Activity', row.budget_activity], ['Content', row.content_name],
            ['Requested Amount', money(row.liquidation_amount)],
            ['Actual Amount', row.actual_amount ? money(row.actual_amount) : 'N/A'],
            ['Request Date', formatDate(row.liquidation_date)], ['Activity Date', formatDate(row.activity_date)],
            ['Submitted By', row.created_by_name], ['Submitted', formatDate(row.submitted_at)],
            ['Processed', formatDate(row.status === 'Completed' ? row.completed_at : (row.approved_at || row.liquidation_approved_at || row.department_approved_at || row.submitted_at))],
            ['Next Approver', row.next_approver],
            ['Remarks', row.remarks]
        ];
        if (row.requires_sap || row.sap_document_number) {
            details.splice(details.length - 2, 0, ['SAP Document Number', row.sap_document_number]);
        }
        $('#completionDetailsBody').html(details.map(item => detailItem(item[0], item[1], item[0] === 'Remarks' ? 'col-12' : 'col-md-6')).join(''));
        $('#completionDocumentLink').attr('href', `print_liquidation.php?id=${row.liquidation_id}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('completionDetailsModal')).show();
    });

    $(document).on('click', '.view-completion-attachment-btn', function() {
        const row = completionRowFromAction(this);
        if (row.attachment_path) showCompletionAttachment(row);
    });

    $('#completionAttachmentModal').on('hidden.bs.modal', function() {
        $('#completionAttachmentImage').attr('src', '');
        $('#completionAttachmentFrame').attr('src', 'about:blank');
    });

    $(document).on('click', '.completion-tab', function() {
        $('.completion-tab').removeClass('active');
        $(this).addClass('active');
        selectedStatus = $(this).data('status') || '';
        selectedRequestType = $(this).data('type') || '';
        completionTable.ajax.reload();
    });

    function updateLiquidationStatus(payload, successCallback) {
        $.ajax({
            url: 'data/update_liquidation_status.php',
            type: 'POST',
            dataType: 'json',
            data: payload,
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    completionTable.ajax.reload(null, false);
                    refreshAccountingPendingCount();
                    if (typeof successCallback === 'function') successCallback(res);
                } else {
                    Swal.fire('Error', res.message || 'Failed to update liquidation.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update liquidation.', 'error');
            }
        });
    }

    $(document).on('click', '.sap-liq-btn', function() {
        const id = $(this).data('id');
        const row = completionRowFromAction(this);
        Swal.fire({
            title: 'SAP Document Number',
            input: 'text',
            inputValue: row.sap_document_number || '',
            inputPlaceholder: 'Enter SAP Document Number',
            inputValidator: (value) => {
                if (!value || !value.trim()) return 'SAP Document Number is required.';
                if (value.trim().length > 255) return 'SAP Document Number must be 255 characters or less.';
                return null;
            },
            showCancelButton: true,
            confirmButtonText: 'Save SAP'
        }).then((result) => {
            if (!result.isConfirmed) return;

            updateLiquidationStatus({
                liquidation_id: id,
                action: 'save_sap',
                sap_document_number: result.value.trim()
            });
        });
    });

    $(document).on('click', '.complete-liq-btn', function() {
        const id = $(this).data('id');
        const row = completionRowFromAction(this);
        const isCashAdvanceRelease = row.request_type === 'Cash Advance' && row.status === 'Liquidated';
        const isAccountingManagerStep = row.request_type === 'Liquidation';
        const confirmText = isCashAdvanceRelease
            ? 'This will release the Cash Advance.'
            : (isAccountingManagerStep
                ? 'Accounting Manager verification will mark this request as completed.'
                : 'This will mark the Request for Payment as completed.');

        Swal.fire({
            title: isCashAdvanceRelease ? 'Release Cash Advance?' : (isAccountingManagerStep ? 'Accounting Manager verification?' : 'Complete RFP?'),
            text: confirmText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: isCashAdvanceRelease ? 'Yes, release' : 'Yes, complete'
        }).then((result) => {
            if (!result.isConfirmed) return;

            updateLiquidationStatus({
                liquidation_id: id,
                action: 'complete'
            });
        });
    });

    $(document).ready(refreshAccountingPendingCount);
</script>

<?php include 'footer.php'; ?>
