<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

$isFinalApproval = liquidation_can_final_approve();
$isManagerApproval = liquidation_is_department_head() && !$isFinalApproval;

if (!$isManagerApproval && !$isFinalApproval) {
    http_response_code(403);
    die('Only Department Managers or assigned final approvers can approve requests.');
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
                                <h2 class="mb-0"><?= $isFinalApproval ? 'Final Request Approval' : 'Department Request Approval' ?></h2>
                                <p class="liquidation-subtitle">Review request details, supporting files, and budget information before making a decision.</p>
                            </div>
                            <span class="liquidation-status status-info"><i class="ti ti-shield-check me-1"></i><?= $isFinalApproval ? 'Final Approver' : 'Department Manager' ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card liquidation-card">
                    <div class="card-header">
                        <div>
                            <h5 class="mb-0"><?= $isFinalApproval ? 'Pending Final Approval' : 'Pending Department Manager Approval' ?></h5>
                            <p class="liquidation-toolbar-subtitle">Only requests awaiting your approval stage are shown.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="display responsive nowrap liquidation-table" id="approvalTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Submitted</th>
                                    <th>Request No.</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Next Approver</th>
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

<div class="modal fade liquidation-modal" id="approvalDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title">Approval Details</h5><p class="liquidation-toolbar-subtitle">Complete request, budget, and approval information.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body"><div class="row g-3" id="approvalDetailsBody"></div></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="approvalDocumentLink"><i class="ti ti-file-text f-18"></i> View Document</a>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="approvalAttachmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div><h5 class="modal-title" id="approvalAttachmentTitle">Attachment Preview</h5><p class="liquidation-toolbar-subtitle">Supporting document for the selected request.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body attachment-preview-body">
                <img src="" alt="Attachment preview" class="attachment-preview-image d-none" id="approvalAttachmentImage">
                <iframe src="about:blank" title="Attachment preview" class="attachment-preview-frame d-none" id="approvalAttachmentFrame"></iframe>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
        </div>
    </div>
</div>

<script>
    const approvalMode = '<?= $isFinalApproval ? 'approval' : 'manager_approval' ?>';
    const approvalAction = '<?= $isFinalApproval ? 'approve' : 'manager_approve' ?>';
    const approvalStageLabel = '<?= $isFinalApproval ? 'Final Approver' : 'Department Manager' ?>';
    const statusDisplay = {
        'Liquidated': 'Recommended',
        'Completed': 'Liquidated'
    };

    function updateGmPendingCount(count) {
        const $badge = $('.js-liquidation-gm-pending');
        $badge.text(count);
        $badge.toggleClass('d-none', count <= 0);
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

    function isCashAdvanceLiquidation(row) {
        return !!row.source_cash_advance_number;
    }

    function displayLiquidationStatus(row) {
        const status = row.status || '';
        if (row.request_type === 'Cash Advance' && status === 'Liquidated') {
            return 'For Release';
        }

        const requestType = row.request_type || 'Liquidation';
        if (Number(row.workflow_version || 1) >= 2 && requestType === 'Request for Payment') {
            if (status === 'For Accounting Approval') return 'For Accounting Completion';
            if (status === 'Completed') return 'Completed';
        }
        if ((requestType === 'Liquidation' || requestType === 'Request for Payment') && statusDisplay[status]) {
            return statusDisplay[status];
        }

        return status;
    }

    const approvalTable = $('#approvalTable').DataTable({
        ajax: {
            url: 'data/get_liquidations.php?mode=' + approvalMode,
            dataSrc: function(json) {
                const rows = json.status === 'success' ? (json.data || []) : [];
                updateGmPendingCount(rows.length);
                return rows;
            }
        },
        responsive: true,
        order: [
            [0, 'desc']
        ],
        columns: [{
                data: 'submitted_at',
                render: formatDate
            },
            {
                data: 'liquidation_number',
                render: function(data, type, row) {
                    if (type !== 'display') return data;
                    const requestType = isCashAdvanceLiquidation(row) ? 'Cash Advance Liquidation' : (row.request_type || 'Liquidation');
                    return `<span class="liquidation-primary-cell">${escapeHtml(data || 'N/A')}</span><span class="liquidation-meta">${escapeHtml(requestType)}</span>`;
                }
            },
            {
                data: 'department',
                render: function(data) {
                    return escapeHtml(data || 'N/A');
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
                    const data = row.status;
                    let badge = 'status-warning';
                    if (data === 'Rejected') badge = 'status-danger';
                    if (data === 'Completed') badge = 'status-success';
                    return `<span class="liquidation-status ${badge}">${escapeHtml(displayLiquidationStatus(row))}</span>`;
                }
            },
            {
                data: 'next_approver',
                render: function(data) {
                    return escapeHtml(data || 'None');
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(row) {
                    let buttons = `<div class="liquidation-action-group">
                        <button type="button" class="liquidation-action-btn view-approval-details-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                        <a href="print_liquidation.php?id=${row.liquidation_id}" target="_blank" class="liquidation-action-btn" title="View document" aria-label="View document"><i class="ti ti-file-text f-18"></i></a>`;

                    if (row.attachment_path) {
                        buttons += `<button type="button" class="liquidation-action-btn success view-approval-attachment-btn" title="Preview attachment" aria-label="Preview attachment"><i class="ti ti-paperclip f-18"></i></button>`;
                    }

                    if (row.status === 'For GM Approval' || row.status === 'For Department Manager Approval') {
                        buttons += `<button type="button" class="liquidation-action-btn success approve-liq-btn" data-id="${row.liquidation_id}" title="Approve request" aria-label="Approve request"><i class="ti ti-check f-18"></i></button>
                            <button type="button" class="liquidation-action-btn danger reject-liq-btn" data-id="${row.liquidation_id}" title="Reject request" aria-label="Reject request"><i class="ti ti-x f-18"></i></button>`;
                    }

                    buttons += '</div>';
                    return buttons;
                }
            }
        ]
    });

    function approvalRowFromAction(element) {
        let $row = $(element).closest('tr');
        if ($row.hasClass('child')) $row = $row.prev();
        return approvalTable.row($row).data() || {};
    }

    function showApprovalAttachment(row) {
        const url = attachmentUrl(row.attachment_path);
        const isImage = /\.(jpe?g|png)(?:[?#].*)?$/i.test(url);
        $('#approvalAttachmentTitle').text(`Attachment - ${row.liquidation_number || 'Request'}`);
        $('#approvalAttachmentImage').attr('src', isImage ? url : '').toggleClass('d-none', !isImage);
        $('#approvalAttachmentFrame').attr('src', isImage ? 'about:blank' : url).toggleClass('d-none', isImage);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('approvalAttachmentModal')).show();
    }

    $(document).on('click', '.view-approval-details-btn', function() {
        const row = approvalRowFromAction(this);
        const requestType = isCashAdvanceLiquidation(row) ? 'Cash Advance Liquidation' : (row.request_type || 'Liquidation');
        const details = [
            ['Request Number', row.liquidation_number], ['Request Type', requestType],
            ['Series Prefix', row.series_prefix], ['Source Cash Advance', row.source_cash_advance_number], ['Status', displayLiquidationStatus(row)],
            ['Department', row.department], ['Dealer', row.dealer], ['Budget Code', row.budget_code],
            ['Budget Activity', row.budget_activity], ['Content', row.content_name],
            ['Requested Amount', money(row.liquidation_amount)],
            ['Actual Amount', row.actual_amount ? money(row.actual_amount) : 'N/A'],
            ['Request Date', formatDate(row.liquidation_date)], ['Activity Date', formatDate(row.activity_date)],
            ['Created By', row.created_by_name], ['Submitted', formatDate(row.submitted_at)],
            ['Next Approver', row.next_approver], ['Remarks', row.remarks]
        ];
        $('#approvalDetailsBody').html(details.map(item => detailItem(item[0], item[1], item[0] === 'Remarks' ? 'col-12' : 'col-md-6')).join(''));
        $('#approvalDocumentLink').attr('href', `print_liquidation.php?id=${row.liquidation_id}`);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('approvalDetailsModal')).show();
    });

    $(document).on('click', '.view-approval-attachment-btn', function() {
        const row = approvalRowFromAction(this);
        if (row.attachment_path) showApprovalAttachment(row);
    });

    $('#approvalAttachmentModal').on('hidden.bs.modal', function() {
        $('#approvalAttachmentImage').attr('src', '');
        $('#approvalAttachmentFrame').attr('src', 'about:blank');
    });

    function updateLiquidation(id, action, reason) {
        $.ajax({
            url: 'data/update_liquidation_status.php',
            type: 'POST',
            dataType: 'json',
            data: {
                liquidation_id: id,
                action: action,
                rejection_reason: reason || ''
            },
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    approvalTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message || 'Failed to update liquidation.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to update liquidation.', 'error');
            }
        });
    }

    $(document).on('click', '.approve-liq-btn', function() {
        const id = $(this).data('id');
        const row = approvalRowFromAction(this);
        const isCaLiquidation = isCashAdvanceLiquidation(row);
        const isCurrentWorkflow = Number(row.workflow_version || 1) >= 2;
        let confirmationText;
        if (approvalAction === 'manager_approve' && isCurrentWorkflow) {
            if (row.request_type === 'Cash Advance') {
                confirmationText = 'This will send the Cash Advance directly to Accounting for release.';
            } else if (row.request_type === 'Liquidation') {
                confirmationText = 'This will send the Liquidation to Accounting for SAP Document encoding.';
            } else if (Number(row.liquidation_amount || 0) > 10000) {
                confirmationText = 'This will endorse the Request for Payment to the assigned final approver.';
            } else {
                confirmationText = 'This will send the Request for Payment directly to Accounting for completion.';
            }
        } else if (approvalAction === 'approve' && isCurrentWorkflow) {
            confirmationText = row.request_type === 'Liquidation'
                ? 'This will send the Liquidation to Accounting Manager verification.'
                : 'This will send the Request for Payment to Accounting for completion.';
        } else {
            confirmationText = approvalAction === 'manager_approve'
                ? 'This will endorse the request to final approval.'
                : (isCaLiquidation
                    ? 'This will move the Cash Advance liquidation to Accounting completion.'
                    : 'This will move the liquidation to Liquidated for Accounting completion.');
        }
        Swal.fire({
            title: isCaLiquidation ? 'Approve Cash Advance liquidation?' : 'Approve request?',
            text: confirmationText,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Yes, approve'
        }).then((result) => {
            if (result.isConfirmed) updateLiquidation(id, approvalAction, '');
        });
    });

    $(document).on('click', '.reject-liq-btn', function() {
        const id = $(this).data('id');
        Swal.fire({
            title: 'Reject request',
            input: 'textarea',
            inputLabel: 'Reason',
            inputPlaceholder: 'Enter rejection reason',
            inputValidator: (value) => {
                if (!value || !value.trim()) return 'Rejection reason is required.';
                return null;
            },
            showCancelButton: true,
            confirmButtonText: 'Reject'
        }).then((result) => {
            if (result.isConfirmed) updateLiquidation(id, 'reject', result.value);
        });
    });
</script>

<?php include 'footer.php'; ?>
