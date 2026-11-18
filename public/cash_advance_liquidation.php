<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

if (!liquidation_can_encode_department_request()) {
    http_response_code(403);
    die('Only Department PICs or Department Managers can process liquidations.');
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
                                <h2 class="mb-0">For Liquidation</h2>
                                <p class="liquidation-subtitle">Review completed Cash Advances and RFPs that require liquidation.</p>
                            </div>
                            <a href="my_liquidations" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-list-details f-18"></i> My Requests
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
                        <div>
                            <h5 class="mb-0">Requests Awaiting Liquidation</h5>
                            <p class="liquidation-toolbar-subtitle">Select a completed request to create its liquidation record.</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <table class="display responsive nowrap liquidation-table" id="caLiquidationTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th>Completed Date</th>
                                    <th>Request No.</th>
                                    <th>Department</th>
                                    <th>Amount</th>
                                    <th>Status</th>
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

<div class="modal fade liquidation-modal" id="caDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title">Request Details</h5>
                    <p class="liquidation-toolbar-subtitle">Complete information for the selected request.</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><span class="detail-label">Request Number</span><span class="detail-value" id="caDetailNumber"></span></div>
                    <div class="col-md-6"><span class="detail-label">Series Prefix</span><span class="detail-value" id="caDetailSeriesPrefix"></span></div>
                    <div class="col-md-6"><span class="detail-label">Status</span><span class="detail-value" id="caDetailStatus"></span></div>
                    <div class="col-md-6"><span class="detail-label">Department</span><span class="detail-value" id="caDetailDepartment"></span></div>
                    <div class="col-md-6"><span class="detail-label">Budget Code</span><span class="detail-value" id="caDetailBudgetCode"></span></div>
                    <div class="col-md-6"><span class="detail-label">Content</span><span class="detail-value" id="caDetailContent"></span></div>
                    <div class="col-md-4"><span class="detail-label">Amount</span><span class="detail-value" id="caDetailAmount"></span></div>
                    <div class="col-md-4"><span class="detail-label">Request Date</span><span class="detail-value" id="caDetailDate"></span></div>
                    <div class="col-md-4"><span class="detail-label">Completed Date</span><span class="detail-value" id="caDetailReleasedDate"></span></div>
                    <div class="col-md-6"><span class="detail-label">Attachment</span><span class="detail-value" id="caDetailAttachment"></span></div>
                    <div class="col-12"><span class="detail-label">Remarks</span><span class="detail-value detail-value-wrap" id="caDetailRemarks"></span></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
                <a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="caDetailPrintLink">
                    <i class="ti ti-file-text f-18"></i> View Document
                </a>
                <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="createLiquidationFromDetailsBtn">
                    <i class="ti ti-file-plus f-18"></i> Create Liquidation
                </button>
            </div>
        </div>
    </div>
</div>

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
                            <label class="form-label">Request No.</label>
                            <input type="text" class="form-control" id="modalCashAdvanceNumber" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Request Amount</label>
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

<script>
    let currentSourceLiquidationId = null;
    let currentSourceType = null;
    let caLiquidationModal = null;
    let selectedCashAdvanceRow = null;

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

    const caLiquidationTable = $('#caLiquidationTable').DataTable({
        ajax: {
            url: 'data/get_liquidations.php',
            data: function(d) {
                d.mode = 'manager_liquidation';
            },
            dataSrc: function(json) {
                if (json.status !== 'success') return [];
                return json.data || [];
            }
        },
        responsive: true,
        order: [
            [0, 'desc']
        ],
        columns: [{
                data: 'completed_at',
                render: formatDate
            },
            {
                data: 'liquidation_number',
                render: function(data, type, row) {
                    if (type !== 'display') return data;
                    return `<span class="liquidation-primary-cell">${$('<div>').text(data || 'N/A').html()}</span><span class="liquidation-meta">${row.request_type === 'Request for Payment' ? 'RFP · Requires Liquidation' : 'Cash Advance'}</span>`;
                }
            },
            {
                data: 'department'
            },
            {
                data: 'liquidation_amount',
                render: money
            },
            {
                data: 'status',
                render: function() {
                    return '<span class="liquidation-status status-warning">For Liquidation</span>';
                }
            },
            {
                data: null,
                orderable: false,
                className: 'text-center',
                render: function(row) {
                    let actions = `<div class="liquidation-action-group">
                        <button type="button" class="liquidation-action-btn view-ca-details-btn" title="View details" aria-label="View details"><i class="ti ti-eye f-18"></i></button>
                        <a href="print_liquidation.php?id=${row.liquidation_id}" target="_blank" class="liquidation-action-btn" title="View document" aria-label="View document"><i class="ti ti-file-text f-18"></i></a>`;

                    if (row.attachment_path) {
                        actions += `<button type="button" class="liquidation-action-btn success view-ca-attachment-btn" title="View attachment" aria-label="View attachment"><i class="ti ti-paperclip f-18"></i></button>`;
                    }

                    actions += `<button type="button" class="liquidation-action-btn warning create-ca-liquidation-btn" title="Create liquidation" aria-label="Create liquidation"><i class="ti ti-file-plus f-18"></i></button>`;

                    actions += '</div>';
                    return actions;
                }
            }
        ]
    });

    function caRowFromAction(element) {
        let $row = $(element).closest('tr');
        if ($row.hasClass('child')) $row = $row.prev();
        return caLiquidationTable.row($row).data() || {};
    }

    function showAttachmentPreview(path, recordNumber) {
        const url = attachmentUrl(path);
        const isImage = /\.(jpe?g|png)(?:[?#].*)?$/i.test(url);
        $('#attachmentPreviewTitle').text(recordNumber ? `Attachment - ${recordNumber}` : 'Attachment Preview');
        $('#attachmentPreviewImage').attr('src', isImage ? url : '').toggleClass('d-none', !isImage);
        $('#attachmentPreviewFrame').attr('src', isImage ? 'about:blank' : url).toggleClass('d-none', isImage);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('attachmentPreviewModal')).show();
    }

    $(document).on('click', '.view-ca-attachment-btn', function() {
        const row = caRowFromAction(this);
        if (row.attachment_path) showAttachmentPreview(row.attachment_path, row.liquidation_number);
    });

    $(document).on('click', '.create-ca-liquidation-btn', function() {
        const row = caRowFromAction(this);
        openCashAdvanceLiquidation(row);
    });

    $(document).on('click', '.view-ca-details-btn', function() {
        const row = caRowFromAction(this);
        selectedCashAdvanceRow = row;
        $('#caDetailNumber').text(row.liquidation_number || 'N/A');
        $('#caDetailSeriesPrefix').text(row.series_prefix || 'N/A');
        $('#caDetailStatus').text((row.request_type === 'Request for Payment' ? 'RFP' : 'Cash Advance') + ' · For Liquidation');
        $('#caDetailDepartment').text(row.department || 'N/A');
        $('#caDetailBudgetCode').text(row.budget_code || 'N/A');
        $('#caDetailContent').text(row.content_name || 'N/A');
        $('#caDetailAmount').text(money(row.liquidation_amount));
        $('#caDetailDate').text(formatDate(row.liquidation_date) || 'N/A');
        $('#caDetailReleasedDate').text(formatDate(row.completed_at) || 'N/A');
        $('#caDetailRemarks').text(row.remarks || 'No remarks provided.');
        $('#caDetailPrintLink').attr('href', `print_liquidation.php?id=${row.liquidation_id}`);

        if (row.attachment_path) {
            const $attachmentButton = $('<button>', {
                type: 'button',
                class: 'btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1',
                text: 'Preview attachment'
            }).prepend('<i class="ti ti-paperclip"></i>');
            $('#caDetailAttachment').empty().append($attachmentButton);
            $attachmentButton.on('click', function() {
                const detailsModal = document.getElementById('caDetailsModal');
                $(detailsModal).one('hidden.bs.modal', function() {
                    showAttachmentPreview(selectedCashAdvanceRow.attachment_path, selectedCashAdvanceRow.liquidation_number);
                });
                bootstrap.Modal.getInstance(detailsModal)?.hide();
            });
        } else {
            $('#caDetailAttachment').text('Not attached');
        }

        bootstrap.Modal.getOrCreateInstance(document.getElementById('caDetailsModal')).show();
    });

    $('#attachmentPreviewModal').on('hidden.bs.modal', function() {
        $('#attachmentPreviewImage').attr('src', '');
        $('#attachmentPreviewFrame').attr('src', 'about:blank');
    });

    function openCashAdvanceLiquidation(row) {
        currentSourceLiquidationId = row.liquidation_id || null;
        currentSourceType = row.request_type || 'Cash Advance';
        if (!currentSourceLiquidationId) return;
        $('#modalSourceLiquidationId').val(currentSourceLiquidationId);
        $('#modalCashAdvanceNumber').val(row.liquidation_number || '');
        $('#modalCaAmount').val(money(row.liquidation_amount || 0));
        $('#modalActualAmount').val('');
        $('#modalLiquidationDate').val(new Date().toISOString().slice(0, 10));
        $('#modalRemarks').val('');
        $('#modalAttachment').val('');

        caLiquidationModal = new bootstrap.Modal(document.getElementById('caLiquidationModal'));
        caLiquidationModal.show();
    }

    $('#createLiquidationFromDetailsBtn').on('click', function() {
        if (!selectedCashAdvanceRow) return;
        bootstrap.Modal.getInstance(document.getElementById('caDetailsModal'))?.hide();
        openCashAdvanceLiquidation(selectedCashAdvanceRow);
    });

    $('#saveCaLiquidationBtn').on('click', function() {
        const file = $('#modalAttachment')[0].files[0];
        const actualAmount = $('#modalActualAmount').val();
        const liquidationDate = $('#modalLiquidationDate').val();
        const remarks = $('#modalRemarks').val();
        if (!file || !currentSourceLiquidationId) return;

        if (!actualAmount || parseFloat(actualAmount) <= 0) {
            Swal.fire('Error', 'Liquidation amount is required and must be greater than zero.', 'error');
            return;
        }

        if (!liquidationDate) {
            Swal.fire('Error', 'Liquidation date is required.', 'error');
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
                    caLiquidationTable.ajax.reload(null, false);
                } else {
                    Swal.fire('Error', res.message || 'Upload failed.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Upload failed.', 'error');
            },
            complete: function() {
                $('#saveCaLiquidationBtn').prop('disabled', false);
                currentSourceLiquidationId = null;
            }
        });
    });

</script>

<?php include 'footer.php'; ?>
