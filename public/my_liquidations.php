<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

if (!liquidation_can_encode_department_request()) {
    http_response_code(403);
    die('Only Department PICs or Department Managers can view requests.');
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
                                <h2 class="mb-0">My Financial Requests</h2>
                                <p class="liquidation-subtitle">Track cash advances, liquidations, and requests for payment from submission to completion.</p>
                            </div>
                            <a href="add_liquidation" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-plus f-18"></i> Add Request
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
                            <h5 class="mb-0">Request History</h5>
                            <p class="liquidation-toolbar-subtitle">Switch views to find active, rejected, or completed requests.</p>
                        </div>
                        <ul class="nav nav-tabs liquidation-tabs" id="managerRequestTabs">
                            <li class="nav-item">
                                <button class="nav-link active manager-request-tab" type="button" data-scope="active" data-status="" data-type="">
                                    Current &amp; Rejected
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link manager-request-tab" type="button" data-scope="" data-status="Completed" data-type="Cash Advance">
                                    Released Cash Advances
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link manager-request-tab" type="button" data-scope="" data-status="Completed" data-type="Liquidation">
                                    Completed Liquidations
                                </button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link manager-request-tab" type="button" data-scope="" data-status="Completed" data-type="Request for Payment">
                                    Completed RFPs
                                </button>
                            </li>
                            
                        </ul>
                    </div>
                    <div class="card-body">
                        <table class="display responsive nowrap liquidation-table" id="liquidationsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Request No.</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Next Approver</th>
                                    <th>Remarks</th>
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

<input type="file" id="liquidationAttachmentInput" accept="application/pdf,image/jpeg,image/png" style="display:none;">

<div class="modal fade liquidation-modal" id="caLiquidationModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Create Liquidation</h5>
                    <p class="liquidation-toolbar-subtitle">Record the actual expense and attach supporting documents.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="caLiquidationForm" enctype="multipart/form-data">
                    <input type="hidden" id="modalSourceLiquidationId" name="source_liquidation_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Cash Advance No.</label>
                            <input type="text" class="form-control" id="modalCashAdvanceNumber" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CA Amount</label>
                            <input type="text" class="form-control" id="modalCaAmount" readonly>
                        </div>
                        <div class="col-md-6">
                            <label for="modalActualAmount" class="form-label required-label">Liquidation Amount</label>
                            <input type="number" class="form-control" id="modalActualAmount" name="actual_amount" step="0.01" min="0.01" required>
                        </div>
                        <div class="col-md-6">
                            <label for="modalLiquidationDate" class="form-label required-label">Liquidation Date</label>
                            <input type="date" class="form-control" id="modalLiquidationDate" name="liquidation_date" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-12">
                            <label for="modalRemarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="modalRemarks" name="remarks" rows="3" maxlength="2000"></textarea>
                        </div>
                        <div class="col-12">
                            <label for="modalAttachment" class="form-label required-label">Attachment</label>
                            <div class="upload-field">
                                <input type="file" class="form-control" id="modalAttachment" name="attachment" accept="application/pdf,image/jpeg,image/png" required>
                                <small class="supporting-text">PDF, JPG, or PNG. Max 5MB.</small>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="saveCaLiquidationBtn">
                    <i class="ti ti-file-plus f-18"></i> Create Liquidation
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="attachmentPreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="attachmentPreviewTitle">Attachment Preview</h5>
                    <p class="liquidation-toolbar-subtitle">Supporting document for the selected request.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body attachment-preview-body">
                <img src="" alt="Attachment preview" class="attachment-preview-image d-none" id="attachmentPreviewImage">
                <iframe src="about:blank" title="Attachment preview" class="attachment-preview-frame d-none" id="attachmentPreviewFrame"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Request Details</h5>
                    <p class="liquidation-toolbar-subtitle">Complete information for the selected financial request.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><span class="detail-label">Request Number</span><span class="detail-value" id="detailRequestNumber"></span></div>
                    <div class="col-md-6"><span class="detail-label">Cash Advance Number</span><span class="detail-value" id="detailCashAdvanceNumber"></span></div>
                    <div class="col-md-6"><span class="detail-label">Request Type</span><span class="detail-value" id="detailRequestType"></span></div>
                    <div class="col-md-6"><span class="detail-label">Series Prefix</span><span class="detail-value" id="detailSeriesPrefix"></span></div>
                    <div class="col-md-6"><span class="detail-label">Status</span><span class="detail-value" id="detailStatus"></span></div>
                    <div class="col-md-6"><span class="detail-label">Budget Code</span><span class="detail-value" id="detailBudgetCode"></span></div>
                    <div class="col-md-6"><span class="detail-label">Content</span><span class="detail-value" id="detailContent"></span></div>
                    <div class="col-md-6"><span class="detail-label">Amount</span><span class="detail-value" id="detailAmount"></span></div>
                    <div class="col-md-4"><span class="detail-label">Request Date</span><span class="detail-value" id="detailRequestDate"></span></div>
                    <div class="col-md-4"><span class="detail-label">Activity Date</span><span class="detail-value" id="detailActivityDate"></span></div>
                    <div class="col-md-4"><span class="detail-label">Submitted</span><span class="detail-value" id="detailSubmittedDate"></span></div>
                    <div class="col-md-6"><span class="detail-label">Next Approver</span><span class="detail-value" id="detailNextApprover"></span></div>
                    <div class="col-md-6"><span class="detail-label">Attachment</span><span class="detail-value" id="detailAttachment"></span></div>
                    <div class="col-12"><span class="detail-label">Remarks</span><span class="detail-value detail-value-wrap" id="detailRemarks"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-primary d-none align-items-center gap-2" id="detailUploadButton">
                    <i class="ti ti-upload f-18"></i> Upload Attachment
                </button>
                <a href="#" class="btn btn-outline-primary d-none align-items-center gap-2" id="detailEditLink">
                    <i class="ti ti-edit f-18"></i> Edit Request
                </a>
                <a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="detailPrintLink">
                    <i class="ti ti-file-text f-18"></i> View Document
                </a>
            </div>
        </div>
    </div>
</div>

<script>
    let currentLiquidationId = null;
    let selectedScope = 'active';
    let selectedStatus = '';
    let selectedRequestType = '';
    let selectedRequestRow = null;
    let currentSourceLiquidationId = null;
    let currentSourceType = null;
    let caLiquidationModal = null;
    const currentUserId = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
    const statusDisplay = {
        'Liquidated': 'Recommended',
        'Completed': 'Liquidated'
    };

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

    function canCreateCashAdvanceLiquidation(row) {
        return (row.request_type === 'Cash Advance' ||
            (row.request_type === 'Request for Payment' && String(row.requires_liquidation) === '1' && Number(row.created_by) === currentUserId)) &&
            row.status === 'Completed' &&
            !(row.request_type === 'Cash Advance' ? row.linked_liquidation_status : row.linked_rfp_liquidation_status);
    }

    function canEditRequest(row) {
        return Number(row.created_by) === currentUserId &&
            !row.source_cash_advance_id && !row.source_rfp_id &&
            (row.status === 'For Department Manager Approval' || row.status === 'Rejected');
    }

    function displayRequestStatus(row) {
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

        if (row.request_type === 'Cash Advance' && status === 'Completed') {
            return row.linked_liquidation_status === 'Completed' ?
                'Completed' :
                'For Liquidation';
        }

        return status;
    }

    const table = $('#liquidationsTable').DataTable({
        ajax: {
            url: 'data/get_liquidations.php',
            data: function(d) {
                d.mode = 'mine';
                d.scope = selectedScope;
                d.status = selectedStatus;
                d.request_type = selectedRequestType;
            },
            dataSrc: function(json) {
                if (json.status !== 'success') return [];
                return json.data || [];
            }
        },
        responsive: true,
        order: [[0, 'desc']],
        columns: [{
                data: 'liquidation_date',
                render: formatDate
            },
            {
                data: 'liquidation_number',
                render: function(data, type, row) {
                    if (type !== 'display') return data;
                    const requirement = row.request_type === 'Request for Payment' ? (row.requires_liquidation == null ? ' · Unclassified' : (String(row.requires_liquidation) === '1' ? ' · Requires Liquidation' : ' · Direct Payment')) : '';
                    const linked = row.linked_rfp_liquidation_number ? `<span class="liquidation-meta">Linked: ${escapeHtml(row.linked_rfp_liquidation_number)} · ${escapeHtml(row.linked_rfp_liquidation_status)}</span>` : '';
                    return `<span class="liquidation-primary-cell">${escapeHtml(data || 'N/A')}</span><span class="liquidation-meta">${escapeHtml(row.request_type || 'Liquidation')}${requirement}</span>${linked}`;
                }
            },
            {
                data: 'liquidation_amount',
                render: money
            },
            {
                data: null,
                render: function(row) {
                    const data = row.status;
                    let badge = 'status-warning';
                    if (data === 'Liquidated') badge = 'status-neutral';
                    if (data === 'Rejected') badge = 'status-danger';
                    if (data === 'Completed') badge = 'status-success';
                    if (data === 'For GM Approval' || data === 'For Department Manager Approval') badge = 'status-info';
                    return `<span class="liquidation-status ${badge}">${escapeHtml(displayRequestStatus(row))}</span>`;
                }
            },
            {
                data: 'next_approver',
                render: function(data, type) {
                    const approver = data || 'None';
                    return type === 'display' ? escapeHtml(approver) : approver;
                }
            },
            {
                data: 'remarks',
                render: function(data, type) {
                    if (type !== 'display') return data || '';
                    return `<span class="liquidation-remarks-cell" title="${escapeHtml(data || 'No remarks')}">${escapeHtml(data || 'No remarks')}</span>`;
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(row) {
                    let actions = `<div class="liquidation-action-group">
                        <button type="button" class="liquidation-action-btn view-request-details-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                        <a href="print_liquidation.php?id=${row.liquidation_id}" target="_blank" class="liquidation-action-btn" title="View document" aria-label="View document"><i class="ti ti-file-text f-18"></i></a>`;

                    if (row.attachment_path) {
                        actions += `<button type="button" class="liquidation-action-btn success view-request-attachment-btn" title="View attachment" aria-label="View attachment"><i class="ti ti-paperclip f-18"></i></button>`;
                    }

                    if (canEditRequest(row)) {
                        const editTitle = row.status === 'Rejected' ? 'Edit and resubmit' : 'Edit request';
                        actions += `<a href="add_liquidation.php?edit=${row.liquidation_id}" class="liquidation-action-btn primary" title="${editTitle}" aria-label="${editTitle}"><i class="ti ti-edit f-18"></i></a>`;
                    }

                    if (canCreateCashAdvanceLiquidation(row)) {
                        actions += `<button type="button" class="liquidation-action-btn warning create-ca-liquidation-btn" title="Create liquidation" aria-label="Create liquidation"><i class="ti ti-file-plus f-18"></i></button>`;
                    }

                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });

    function requestRowFromAction(element) {
        let $row = $(element).closest('tr');
        if ($row.hasClass('child')) $row = $row.prev();
        return table.row($row).data() || {};
    }

    function showAttachmentPreview(path, recordNumber) {
        const url = attachmentUrl(path);
        const isImage = /\.(jpe?g|png)(?:[?#].*)?$/i.test(url);
        $('#attachmentPreviewTitle').text(recordNumber ? `Attachment - ${recordNumber}` : 'Attachment Preview');
        $('#attachmentPreviewImage').attr('src', isImage ? url : '').toggleClass('d-none', !isImage);
        $('#attachmentPreviewFrame').attr('src', isImage ? 'about:blank' : url).toggleClass('d-none', isImage);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('attachmentPreviewModal')).show();
    }

    function openCashAdvanceLiquidation(row) {
        currentSourceLiquidationId = row.liquidation_id || null;
        if (!currentSourceLiquidationId) return;

        $('#modalSourceLiquidationId').val(currentSourceLiquidationId);
        $('#modalCashAdvanceNumber').val(row.liquidation_number || '');
        $('#modalCaAmount').val(money(row.liquidation_amount || 0));
        $('#modalActualAmount').val('');
        $('#modalLiquidationDate').val(new Date().toISOString().slice(0, 10));
        $('#modalRemarks').val('');
        $('#modalAttachment').val('');

        caLiquidationModal = bootstrap.Modal.getOrCreateInstance(document.getElementById('caLiquidationModal'));
        caLiquidationModal.show();
    }

    $(document).on('click', '.view-request-attachment-btn', function() {
        const row = requestRowFromAction(this);
        if (row.attachment_path) showAttachmentPreview(row.attachment_path, row.liquidation_number);
    });

    $(document).on('click', '.create-ca-liquidation-btn', function() {
        const row = requestRowFromAction(this);
        if (!canCreateCashAdvanceLiquidation(row)) {
            Swal.fire('Not available', 'This request already has a liquidation record or is not ready for liquidation.', 'info');
            return;
        }

        currentSourceType = row.request_type;
        openCashAdvanceLiquidation(row);
    });

    $(document).on('click', '.view-request-details-btn', function() {
        const row = requestRowFromAction(this);
        selectedRequestRow = row;

        $('#detailRequestNumber').text(row.liquidation_number || 'N/A');
        $('#detailCashAdvanceNumber').text(row.source_cash_advance_number || row.source_rfp_number || 'N/A');
        $('#detailRequestType').text(row.request_type || 'Liquidation');
        const rfpChoice = row.request_type === 'Request for Payment'
            ? (row.requires_liquidation == null ? 'Unclassified' : (String(row.requires_liquidation) === '1' ? 'Requires Liquidation' : 'Direct Payment / No Liquidation'))
            : '';
        $('#detailRequestType').append(rfpChoice ? ` · ${rfpChoice}` : '');
        if (row.linked_rfp_liquidation_number) $('#detailRequestType').append(document.createTextNode(` · Linked liquidation: ${row.linked_rfp_liquidation_number} (${row.linked_rfp_liquidation_status})`));
        if (row.sap_document_number) $('#detailRequestType').append(document.createTextNode(` · SAP: ${row.sap_document_number}`));
        $('#detailSeriesPrefix').text(row.series_prefix || 'N/A');
        $('#detailStatus').text(displayRequestStatus(row) || 'N/A');
        $('#detailBudgetCode').text(row.budget_code || 'N/A');
        $('#detailContent').text(row.content_name || 'N/A');
        $('#detailAmount').text(money(row.liquidation_amount));
        $('#detailRequestDate').text(formatDate(row.liquidation_date) || 'N/A');
        $('#detailActivityDate').text(formatDate(row.activity_date) || 'N/A');
        $('#detailSubmittedDate').text(formatDate(row.submitted_at) || 'N/A');
        $('#detailNextApprover').text(row.next_approver || 'None');
        $('#detailRemarks').text(row.remarks || 'No remarks provided.');
        $('#detailPrintLink').attr('href', `print_liquidation.php?id=${row.liquidation_id}`);

        const canUpload = row.status === 'For Department Manager Approval' && Number(row.created_by) === currentUserId;
        $('#detailUploadButton')
            .toggleClass('d-none', !canUpload)
            .toggleClass('d-inline-flex', canUpload)
            .data('id', canUpload ? row.liquidation_id : '');

        const canEdit = canEditRequest(row);
        $('#detailEditLink')
            .toggleClass('d-none', !canEdit)
            .toggleClass('d-inline-flex', canEdit)
            .attr('href', canEdit ? `add_liquidation.php?edit=${row.liquidation_id}` : '#')
            .html(`<i class="ti ti-edit f-18"></i> ${row.status === 'Rejected' ? 'Edit and Resubmit' : 'Edit Request'}`);

        if (row.attachment_path) {
            const $attachmentButton = $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1',
                text: 'Preview attachment'
            }).prepend('<i class="ti ti-paperclip"></i>');
            $('#detailAttachment').empty().append($attachmentButton);
            $attachmentButton.on('click', function() {
                const detailsModal = document.getElementById('requestDetailsModal');
                $(detailsModal).one('hidden.bs.modal', function() {
                    showAttachmentPreview(selectedRequestRow.attachment_path, selectedRequestRow.liquidation_number);
                });
                bootstrap.Modal.getInstance(detailsModal)?.hide();
            });
        } else {
            $('#detailAttachment').text('Not attached');
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('requestDetailsModal')).show();
    });

    $('#attachmentPreviewModal').on('hidden.bs.modal', function() {
        $('#attachmentPreviewImage').attr('src', '');
        $('#attachmentPreviewFrame').attr('src', 'about:blank');
    });

    $('#detailUploadButton').on('click', function() {
        currentLiquidationId = $(this).data('id');
        if (!currentLiquidationId) return;
        bootstrap.Modal.getInstance(document.getElementById('requestDetailsModal'))?.hide();
        $('#liquidationAttachmentInput').click();
    });

    $(document).on('click', '.manager-request-tab', function() {
        $('.manager-request-tab').removeClass('active');
        $(this).addClass('active');
        selectedScope = $(this).data('scope') || '';
        selectedStatus = $(this).data('status') || '';
        selectedRequestType = $(this).data('type') || '';
        table.ajax.reload();
    });

    $(document).on('click', '.upload-liq-btn', function() {
        currentLiquidationId = $(this).data('id');
        $('#liquidationAttachmentInput').click();
    });

    $('#liquidationAttachmentInput').on('change', function() {
        const file = this.files[0];
        if (!file || !currentLiquidationId) return;

        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Error', 'Attachment must be below 5MB.', 'error');
            this.value = '';
            return;
        }

        const formData = new FormData();
        formData.append('liquidation_id', currentLiquidationId);
        formData.append('attachment', file);

        $.ajax({
            url: 'data/upload_liquidation_file.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message || 'Upload failed.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Upload failed.', 'error');
            },
            complete: function() {
                $('#liquidationAttachmentInput').val('');
                currentLiquidationId = null;
            }
        });
    });

    $('#saveCaLiquidationBtn').on('click', function() {
        const file = $('#modalAttachment')[0].files[0];
        const actualAmount = $('#modalActualAmount').val();
        const liquidationDate = $('#modalLiquidationDate').val();
        const remarks = $('#modalRemarks').val();

        if (!currentSourceLiquidationId) return;

        if (!actualAmount || parseFloat(actualAmount) <= 0) {
            Swal.fire('Error', 'Liquidation amount is required and must be greater than zero.', 'error');
            return;
        }

        if (!liquidationDate) {
            Swal.fire('Error', 'Liquidation date is required.', 'error');
            return;
        }

        if (!file) {
            Swal.fire('Error', 'Attachment is required.', 'error');
            return;
        }

        if (file.size > 5 * 1024 * 1024) {
            Swal.fire('Error', 'Attachment must be below 5MB.', 'error');
            return;
        }

        const formData = new FormData();
        formData.append('source_liquidation_id', currentSourceLiquidationId);
        formData.append('actual_amount', actualAmount);
        formData.append('liquidation_date', liquidationDate);
        formData.append('remarks', remarks);
        formData.append('attachment', file);

        $.ajax({
            url: currentSourceType === 'Request for Payment' ? 'data/create_rfp_liquidation.php' : 'data/create_ca_liquidation.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            beforeSend: function() {
                $('#saveCaLiquidationBtn').prop('disabled', true);
            },
            success: function(res) {
                if (res.status === 'success') {
                    if (caLiquidationModal) caLiquidationModal.hide();
                    Swal.fire('Success', res.message, 'success');
                    table.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message || 'Failed to create liquidation.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Failed to create liquidation.', 'error');
            },
            complete: function() {
                $('#saveCaLiquidationBtn').prop('disabled', false);
                currentSourceLiquidationId = null;
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
