<?php
require '../init.php';
include 'header.php';
include 'navigation.php';
?>
<link rel="stylesheet" href="assets/css/liquidations.css?v=20260928">

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header"><div class="page-block"><div class="row align-items-center"><div class="col-md-12">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="page-header-title">
                    <h2 class="mb-0">My Financial Requests</h2>
                    <p class="liquidation-subtitle">Track your Cash Advances, Requests for Payment, and Liquidations from submission to completion.</p>
                </div>
                <a href="add_cash_advance" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="ti ti-plus f-18"></i> Add Request</a>
            </div>
        </div></div></div></div>

        <div class="card liquidation-card">
            <div class="card-header">
                <div class="mb-3"><h5 class="mb-0">Request History</h5><p class="liquidation-toolbar-subtitle">Switch views to review active or completed requests.</p></div>
                <ul class="nav nav-tabs liquidation-tabs" id="requestTabs">
                    <li class="nav-item"><button class="nav-link active request-tab" data-scope="active" data-status="" data-type="">Current</button></li>
                    <li class="nav-item"><button class="nav-link request-tab" data-scope="" data-status="" data-type="Cash Advance">Cash Advances</button></li>
                    <li class="nav-item"><button class="nav-link request-tab" data-scope="" data-status="" data-type="Request for Payment">RFPs</button></li>
                    <li class="nav-item"><button class="nav-link request-tab" data-scope="" data-status="" data-type="Liquidation">Liquidations</button></li>
                    <li class="nav-item"><button class="nav-link request-tab" data-scope="" data-status="Completed" data-type="">Completed</button></li>
                </ul>
            </div>
            <div class="card-body">
                <table class="display responsive nowrap liquidation-table" id="myRequestsTable" style="width:100%">
                    <thead><tr><th>Date</th><th>Request No.</th><th>Type</th><th>Department</th><th>Budget</th><th>Amount</th><th>Status</th><th>Actions</th></tr></thead>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade liquidation-modal" id="requestDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title">Request Details</h5><p class="liquidation-toolbar-subtitle">Current information and workflow status.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><div class="row g-3" id="requestDetailsGrid"></div></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><a href="#" target="_blank" class="btn btn-primary d-inline-flex align-items-center gap-2" id="detailsPrintLink"><i class="ti ti-file-text f-18"></i> View Document</a></div>
    </div></div>
</div>

<div class="modal fade liquidation-modal" id="editRequestModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><div><h5 class="modal-title">Edit Request</h5><p class="liquidation-toolbar-subtitle">Editing is available only before Department Head approval.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body"><form id="editRequestForm" enctype="multipart/form-data">
            <input type="hidden" name="ca_number" id="editNumber">
            <div class="row g-3">
                <div class="col-md-6"><label class="form-label required-label">Amount</label><input type="number" min="0.01" step="0.01" class="form-control" name="ca_amount" id="editAmount" required></div>
                <div class="col-md-6"><label class="form-label required-label">Request Date</label><input type="date" class="form-control" name="ca_date" id="editDate" required></div>
                <div class="col-md-6 d-none" id="editPayableGroup"><label class="form-label required-label">Payable To</label><input type="text" maxlength="255" class="form-control" name="payable_to" id="editPayable"></div>
                <div class="col-12 d-none" id="editRfpLiquidationGroup"><label class="form-label required-label">Payment Type</label><div class="d-flex flex-wrap gap-3"><label class="form-check"><input class="form-check-input" type="radio" name="requires_liquidation" value="0"> <span class="form-check-label">Direct Payment / No Liquidation</span></label><label class="form-check"><input class="form-check-input" type="radio" name="requires_liquidation" value="1"> <span class="form-check-label">Requires Liquidation</span></label></div></div>
                <div class="col-md-6 d-none" id="editActivityGroup"><label class="form-label required-label">Activity Date</label><input type="date" class="form-control" name="activity_date" id="editActivityDate"></div>
                <div class="col-md-6 d-none" id="editContentGroup"><label class="form-label">Content</label><input type="text" maxlength="255" class="form-control" name="content_name" id="editContent"></div>
                <div class="col-12"><label class="form-label">Remarks</label><textarea class="form-control" rows="4" maxlength="1000" name="remarks" id="editRemarks"></textarea></div>
                <div class="col-12"><label class="form-label">Replace Attachment</label><div class="upload-field"><input type="file" class="form-control" name="ca_file" id="editFile" accept="application/pdf,image/jpeg,image/png"><small class="supporting-text">Leave empty to retain the current attachment. Max 5MB.</small></div></div>
            </div>
        </form></div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="saveEditButton"><i class="ti ti-device-floppy f-18"></i> Save Changes</button></div>
    </div></div>
</div>

<script>
let selectedScope = 'active';
let selectedStatus = '';
let selectedType = '';

function esc(value) { return $('<div>').text(value == null ? '' : String(value)).html(); }
function money(value) { return '\u20B1 ' + Number(value || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function dateText(value) { if (!value) return ''; const d = new Date(value); return Number.isNaN(d.getTime()) ? value : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: '2-digit' }); }
function fileUrl(path) { return path && path.startsWith('uploads/') ? '../' + path : path; }
function workflowStatus(status, requestType) {
    const labels = {'CA Request':'For Department Head Approval','CA Release':'For Release','For PO':'For Liquidation','For Liquidation':'For Department Head Approval (Liquidation)','Liquidated':'For SAP Document Number','For Accounting Approval':'For Accounting Completion'};
    return labels[status] || status;
}
function statusBadge(status, requestType, displayStatus) {
    let style = 'status-warning';
    if (status === 'Completed') style = 'status-success';
    else if (status === 'Rejected' || status === 'Cancelled') style = 'status-danger';
    else if (status === 'For GM Approval' || status === 'For Accounting Approval') style = 'status-info';
    return `<span class="liquidation-status ${style}">${esc(displayStatus || workflowStatus(status, requestType) || 'Pending')}</span>`;
}

const table = $('#myRequestsTable').DataTable({
    ajax: { url: 'data/get_my_cash_advance_history.php', data: d => { d.scope = selectedScope; d.status = selectedStatus; d.request_type = selectedType; }, dataSrc: json => json.status === 'success' ? (json.data || []) : [] },
    responsive: true,
    order: [[0, 'desc']],
    columns: [
        { data: 'ca_date', render: (v,t,r) => dateText(v || r.pr_date) },
        { data: 'ca_number', render: (v,t,r) => t === 'display' ? `<span class="liquidation-primary-cell">${esc(v)}</span><span class="liquidation-meta">${esc(r.purchase_requisition_number || 'No linked PR')}</span>` : v },
        { data: 'request_type', render: (v,t,r) => t === 'display' ? `<span class="liquidation-primary-cell">${esc(v || 'Cash Advance')}</span>${r.phase_label ? `<span class="liquidation-status status-warning mt-1">${esc(r.phase_label)}</span>` : ''}` : (v || 'Cash Advance') },
        { data: 'pr_department', render: (v,t,r) => esc(r.allocation_departments || v || '') },
        { data: 'budget_code', render: (v,t,r) => esc(r.allocation_budget_codes || v || '') },
        { data: 'ca_amount', render: money },
        { data: 'pr_status', render: (v,t,r) => statusBadge(v, r.request_type, r.display_status) },
        { data: null, orderable: false, className: 'text-center', render: row => {
            let actions = `<div class="liquidation-action-group"><button class="liquidation-action-btn view-details" title="View details"><i class="ti ti-eye f-18"></i></button><a href="print_cash_advance.php?ca_number=${encodeURIComponent(row.ca_number)}" target="_blank" class="liquidation-action-btn" title="View document"><i class="ti ti-file-text f-18"></i></a>`;
            if (row.ca_attachment_file) actions += `<a href="${esc(fileUrl(row.ca_attachment_file))}" target="_blank" class="liquidation-action-btn success" title="View attachment"><i class="ti ti-paperclip f-18"></i></a>`;
            if (row.pr_status === 'CA Request') actions += `<button class="liquidation-action-btn warning edit-request" title="Edit request"><i class="ti ti-edit f-18"></i></button>`;
            if (row.request_type === 'Cash Advance' && row.pr_status === 'For PO') actions += `<a href="purchasing_transactions" class="liquidation-action-btn warning" title="Submit Cash Advance liquidation"><i class="ti ti-receipt f-18"></i></a>`;
            if (row.request_type === 'Request for Payment' && row.pr_status === 'Completed' && String(row.requires_liquidation) === '1' && String(row.has_linked_liquidation) !== '1') actions += `<a href="cash_advance_liquidation" class="liquidation-action-btn warning" title="Create linked liquidation"><i class="ti ti-file-plus f-18"></i></a>`;
            return actions + '</div>';
        }}
    ]
});

$('.request-tab').on('click', function() { $('.request-tab').removeClass('active'); $(this).addClass('active'); selectedScope = $(this).data('scope') || ''; selectedStatus = $(this).data('status') || ''; selectedType = $(this).data('type') || ''; table.ajax.reload(); });

$(document).on('click', '.view-details', function() {
    let $tr = $(this).closest('tr'); if ($tr.hasClass('child')) $tr = $tr.prev();
    const row = table.row($tr).data() || {};
    const liquidationRequirement = row.request_type === 'Request for Payment' ? (String(row.requires_liquidation) === '0' ? 'Direct Payment / No Liquidation' : (row.requires_liquidation == null ? 'Unclassified' : 'Requires Liquidation')) : '';
    const values = [['Request Number', row.ca_number], ['Request Type', row.request_type], ['Transaction Phase', row.phase_label], ['Status', row.display_status || workflowStatus(row.pr_status, row.request_type)], ['Next Stage', row.next_stage], ['Department', row.allocation_departments || row.pr_department], ['Budget Code', row.allocation_budget_codes || row.budget_code], ['Purchase Requisition', row.purchase_requisition_number], ['Amount', money(row.ca_amount)], ['Request Date', dateText(row.ca_date || row.pr_date)], ['Activity Date', dateText(row.activity_date)], ['Payable To', row.payable_to], ['Payment Type', liquidationRequirement], ['Content', row.content_name], ['SAP Document Number', row.sap_document_number], ['Remarks', row.remarks]];
    $('#requestDetailsGrid').html(values.filter(v => v[1]).map(v => `<div class="${v[0] === 'Remarks' ? 'col-12' : 'col-md-6'}"><span class="detail-label">${esc(v[0])}</span><span class="detail-value ${v[0] === 'Remarks' ? 'detail-value-wrap' : ''}">${esc(v[1])}</span></div>`).join(''));
    $('#detailsPrintLink').attr('href', 'print_cash_advance.php?ca_number=' + encodeURIComponent(row.ca_number || ''));
    bootstrap.Modal.getOrCreateInstance(document.getElementById('requestDetailsModal')).show();
});

$(document).on('click', '.edit-request', function() {
    let $tr = $(this).closest('tr'); if ($tr.hasClass('child')) $tr = $tr.prev();
    const row = table.row($tr).data() || {};
    if (row.pr_status !== 'CA Request') return;
    const isRfp = row.request_type === 'Request for Payment';
    const isLiquidation = row.request_type === 'Liquidation';
    $('#editNumber').val(row.ca_number || '');
    $('#editAmount').val(row.ca_amount || '');
    $('#editDate').val((row.ca_date || '').substring(0, 10));
    $('#editPayable').val(row.payable_to || '').prop('required', isRfp);
    $(`#editRequestForm input[name="requires_liquidation"][value="${String(row.requires_liquidation ?? 0)}"]`).prop('checked', true);
    $('#editActivityDate').val((row.activity_date || '').substring(0, 10)).prop('required', isLiquidation);
    $('#editContent').val(row.content_name || '');
    $('#editRemarks').val(row.remarks || '');
    $('#editFile').val('');
    $('#editPayableGroup').toggleClass('d-none', !isRfp);
    $('#editRfpLiquidationGroup').toggleClass('d-none', !isRfp);
    $('#editActivityGroup, #editContentGroup').toggleClass('d-none', !isLiquidation);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('editRequestModal')).show();
});

$('#saveEditButton').on('click', function() {
    const form = document.getElementById('editRequestForm');
    if (!form.reportValidity()) return;
    const editingRowType = $('#editPayableGroup').is(':visible') ? 'Request for Payment' : ($('#editActivityGroup').is(':visible') ? 'Liquidation' : 'Cash Advance');
    if (editingRowType === 'Cash Advance' && Number($('#editAmount').val() || 0) > 10000) {
        Swal.fire('Use Request for Payment', 'Cash Advance is limited to PHP 10,000. Create an RFP for a higher amount.', 'info');
        return;
    }
    const data = new FormData(form);
    $.ajax({
        url: 'data/update_my_cash_advance_request.php', type: 'POST', data: data, processData: false, contentType: false, dataType: 'json',
        beforeSend: () => $('#saveEditButton').prop('disabled', true),
        success: res => {
            if (res.status === 'success') {
                bootstrap.Modal.getInstance(document.getElementById('editRequestModal'))?.hide();
                Swal.fire('Success', res.message, 'success');
                table.ajax.reload(null, false);
            } else Swal.fire('Error', res.message || 'Update failed.', 'error');
        },
        error: xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Update failed.', 'error'),
        complete: () => $('#saveEditButton').prop('disabled', false)
    });
});
</script>
<?php include 'footer.php'; ?>
