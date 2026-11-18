<?php
require '../init.php';
require_once __DIR__ . '/data/cash_advance_workflow_helper.php';
$department = strtoupper(trim($_SESSION['department'] ?? ''));
$position = strtoupper(trim($_SESSION['position'] ?? ''));
$allowed = in_array($department, ['PURCHASING', 'ACCOUNTING', 'FINANCE'], true)
    || in_array($position, ['GENERAL MANAGER', 'ADMINISTRATOR'], true)
    || purchasingWorkflowIsTnescApprover($position);
if (!$allowed) {
    http_response_code(403);
    die('You cannot view Purchasing transactions.');
}
include 'header.php';https://github.com/jasperian/ogprocurementsystemlive.git
include 'navigation.php';
?>

<link rel="stylesheet" href="assets/css/liquidations.css?v=20261016">
<div class="pc-container liquidation-page"><div class="pc-content">
    <div class="page-header"><div class="page-block"><div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div class="page-header-title"><h2 class="mb-0">Purchasing Approvals</h2><p class="liquidation-subtitle">Your pending actions and Cash Advance or Request for Payment liquidations in your department.</p></div>
    </div></div></div>

    <div class="card liquidation-card mb-3"><div class="card-body p-3 p-md-4"><div class="row g-3 align-items-end">
        <div class="col-md-8"><label class="form-label" for="statusFilter">Status</label><select id="statusFilter" class="form-select"><option value="">All Visible Statuses</option></select></div>
        <div class="col-md-4 d-flex align-items-end"><button type="button" id="clearFilters" class="btn btn-outline-secondary w-100" style="min-height:44px">Clear Filter</button></div>
    </div></div></div>

    <div class="card liquidation-card"><div class="card-header"><div><h5 class="mb-0">Pending Actions &amp; Department Liquidations</h5><p class="liquidation-toolbar-subtitle">Requests assigned to you and your department's Cash Advance or Request for Payment records in For PO or For Liquidation. Available actions follow your workflow permissions.</p></div></div><div class="card-body">
        <table class="display responsive nowrap liquidation-table" id="transactionsTable" style="width:100%"><thead><tr><th>Submitted</th><th>Request</th><th>Department</th><th>Amount</th><th>Status</th><th>Created By</th><th>Actions</th></tr></thead></table>
    </div></div>
</div></div>

<div class="modal fade liquidation-modal" id="transactionDetailsModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable"><div class="modal-content">
    <div class="modal-header"><div><h5 class="modal-title">Transaction Details</h5><p class="liquidation-toolbar-subtitle mb-0">Request, budget, amount, and workflow information.</p></div><button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
    <div class="modal-body"><div class="row g-3" id="transactionDetailsBody"></div></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button><a href="#" target="_blank" class="btn btn-primary" id="transactionDocumentLink"><i class="ti ti-file-text me-1"></i>View Document</a></div>
</div></div></div>

<div class="modal fade liquidation-modal" id="liquidationModal" tabindex="-1"><div class="modal-dialog modal-dialog-centered"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title" id="liquidationModalTitle">Submit Cash Advance Liquidation</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
    <div class="modal-body">
        <input type="hidden" id="liquidationNumber">
        <div class="border rounded p-3 mb-3 bg-light" id="liquidationBudgetPanel">
            <div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted">Request Amount</span><strong id="liquidationRequestAmount">—</strong></div>
            <div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted">Budget Code</span><strong id="liquidationBudgetCode">—</strong></div>
            <div class="d-flex justify-content-between gap-3 mb-2"><span class="text-muted">Budget Name</span><strong class="text-end" id="liquidationBudgetName">—</strong></div>
            <div class="d-flex justify-content-between gap-3 pt-2 border-top"><span class="text-muted">Remaining Budget</span><strong id="liquidationRemainingBudget">Loading…</strong></div>
        </div>
        <div class="mb-3"><label class="form-label">Actual Amount</label><input type="number" min="0.01" step="0.01" class="form-control" id="actualAmount"></div><div class="mb-3"><label class="form-label">Attachment</label><input type="file" accept="application/pdf,image/jpeg,image/png" class="form-control" id="liquidationFile"></div><div><label class="form-label">Remarks</label><textarea class="form-control" maxlength="2000" id="liquidationRemarks"></textarea></div>
    </div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" id="submitLiquidation">Submit Liquidation</button></div>
</div></div></div>

<script>
const esc = value => $('<div>').text(value == null ? '' : String(value)).html();
const money = value => '\u20B1 ' + Number(value || 0).toLocaleString('en-US', {minimumFractionDigits: 2});
const dateText = value => value ? new Date(value).toLocaleDateString('en-US', {year:'numeric', month:'short', day:'2-digit'}) : 'N/A';
const dateTimeText = value => value ? new Date(value).toLocaleString('en-US', {year:'numeric', month:'short', day:'2-digit', hour:'numeric', minute:'2-digit'}) : 'N/A';
const detailItem = (label, value, fullWidth) => `<div class="${fullWidth ? 'col-12' : 'col-md-6'}"><span class="detail-label">${esc(label)}</span><span class="detail-value">${esc(value == null || value === '' ? 'N/A' : value)}</span></div>`;
let rows = [];
let liquidationSubmissionMode = 'cash_advance';
let selectedLiquidationRow = null;

function transactionAttachments(row, compact = false) {
    const files = [
        ['ca_attachment_file', 'Supporting attachment'],
        ['po_file', 'Liquidation attachment'],
        ['pr_file', 'Request file']
    ];
    const seen = new Set();
    return files.map(([field, label]) => {
        const path = String(row[field] || '').trim();
        if (!path || seen.has(path)) return '';
        seen.add(path);
        const href = esc('../' + path);
        return compact
            ? `<a href="${href}" target="_blank" rel="noopener" class="liquidation-action-btn success" title="${label}" aria-label="${label}"><i class="ti ti-paperclip f-18"></i></a>`
            : `<a href="${href}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm"><i class="ti ti-paperclip me-1"></i>${label}</a>`;
    }).join('');
}

function updateStatusFilter(requests) {
    const filter = $('#statusFilter');
    const selectedStatus = filter.val();
    const statuses = [...new Set(requests.map(row => row.display_status).filter(Boolean))].sort();

    filter.empty().append(new Option('All Visible Statuses', ''));
    statuses.forEach(status => filter.append(new Option(status, status)));
    filter.val(statuses.includes(selectedStatus) ? selectedStatus : '');
}
function updatePurchasingApprovalBadge(count) {
    $('.purchasing-approval-badge').text(count).toggleClass('d-none', count === 0);
}

const table = $('#transactionsTable').DataTable({
    ajax: {url:'data/get_purchasing_transactions.php', dataSrc: json => {
        rows = json.status === 'success' ? json.data : [];
        updateStatusFilter(rows);
        updatePurchasingApprovalBadge(rows.length);
        return rows;
    }},
    responsive: true,
    order: [[0, 'desc']],
    columns: [
        {data:'pr_date', render:dateText},
        {data:null, render:row => `<span class="liquidation-primary-cell">${esc(row.pr_number)}</span><span class="liquidation-meta">${esc(row.request_type || 'Cash Advance')}</span>${row.phase_label ? `<span class="liquidation-status status-warning mt-1">${esc(row.phase_label)}</span>` : ''}`},
        {data:'pr_department', render:value => esc(value || 'N/A')},
        {data:'ca_amount', render:(value,type) => type === 'display' ? money(value) : Number(value || 0)},
        {data:'display_status', render:value => `<span class="liquidation-status status-info">${esc(value)}</span>`},
        {data:'created_by_name', render:value => esc(value || 'N/A')},
        {data:null, orderable:false, className:'text-center', render:row => {
            let html = `<div class="liquidation-action-group"><button type="button" class="liquidation-action-btn view-transaction" title="View details" aria-label="View transaction details"><i class="ti ti-eye f-18"></i></button><a href="print_cash_advance.php?ca_number=${encodeURIComponent(row.pr_number)}" target="_blank" class="liquidation-action-btn" title="View document"><i class="ti ti-file-text f-18"></i></a>`;
            html += transactionAttachments(row, true);
            if (row.can_act) html += `<button class="liquidation-action-btn success workflow-action" title="Process request"><i class="ti ti-check f-18"></i></button>`;
            if (row.can_create_linked_liquidation) html += `<button type="button" class="liquidation-action-btn warning create-linked-rfp-liquidation" title="Create linked liquidation" aria-label="Create linked liquidation"><i class="ti ti-file-plus f-18"></i></button>`;
            return html + '</div>';
        }}
    ]
});

function actionRow(element) { let tr = $(element).closest('tr'); if (tr.hasClass('child')) tr = tr.prev(); return table.row(tr).data() || {}; }
$.fn.dataTable.ext.search.push((settings, data, index) => {
    if (settings.nTable.id !== 'transactionsTable') return true;
    const row = table.row(index).data();
    return !$('#statusFilter').val() || row.display_status === $('#statusFilter').val();
});
$('#statusFilter').on('change', () => table.draw());
$('#clearFilters').on('click', () => { $('#statusFilter').val(''); table.draw(); });

$(document).on('click', '.view-transaction', function() {
    const row = actionRow(this);
    const liquidationRequirement = row.request_type === 'Request for Payment'
        ? (String(row.requires_liquidation) === '1' ? 'Requires Liquidation' : 'Direct Payment / No Liquidation')
        : 'N/A';
    const details = [
        ['Request Number', row.pr_number],
        ['Request Type', row.request_type || 'Cash Advance'],
        ['Transaction Phase', row.phase_label],
        ['Status', row.display_status],
        ['Next Stage', row.can_create_linked_liquidation ? 'Create Linked Liquidation' : (row.next_status || 'None')],
        ['Department', row.pr_department],
        ['Dealer', row.pr_dealer],
        ['Budget Code', row.budget_code],
        ['Purchase Requisition', row.purchase_requisition_number],
        ['Payable To', row.payable_to],
        ['Liquidation Requirement', liquidationRequirement],
        ['Requested Amount', money(row.ca_amount)],
        ['Actual Amount', row.po_amount ? money(row.po_amount) : 'N/A'],
        ['Request Date', dateText(row.ca_date)],
        ['Activity Date', dateText(row.activity_date)],
        ['Content', row.content_name],
        ['SAP Document Number', row.sap_document_number],
        ['Created By', row.created_by_name],
        ['Submitted', dateTimeText(row.pr_date)],
        ['Remarks', row.remarks, true],
        ['Liquidation Remarks', row.liquidation_remarks, true]
    ];
    $('#transactionDetailsBody').html(details.map(item => detailItem(item[0], item[1], item[2])).join(''));
    const attachments = transactionAttachments(row);
    $('#transactionDetailsBody').append(`<div class="col-12"><span class="detail-label">Attachments</span><div class="d-flex flex-wrap gap-2">${attachments || '<span class="text-muted">No attachments uploaded.</span>'}</div></div>`);
    $('#transactionDocumentLink').attr('href', `print_cash_advance.php?ca_number=${encodeURIComponent(row.pr_number || '')}`);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('transactionDetailsModal')).show();
});

function updateStatus(row, sapNumber) {
    $.post('data/update_ca_status.php', {ca_number:row.pr_number, new_status:row.next_status, sap_document_number:sapNumber || ''}, response => {
        if (response.status === 'success') { Swal.fire('Success', response.message, 'success'); table.ajax.reload(null, false); }
        else Swal.fire('Error', response.message || 'Unable to update request.', 'error');
    }, 'json').fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Unable to update request.', 'error'));
}

function loadLiquidationBudget(row) {
    $('#liquidationRequestAmount').text(money(row.ca_amount));
    $('#liquidationBudgetCode').text(row.budget_code || 'N/A');
    $('#liquidationBudgetName').text('Loading…');
    $('#liquidationRemainingBudget').text('Loading…').removeClass('text-danger');
    $.getJSON('data/get_cash_advance_budget_remaining.php', {budget_id:row.budget_id, department:row.pr_department}).done(response => {
        if (response.status !== 'success' || !response.data) {
            $('#liquidationBudgetName').text('Unavailable');
            $('#liquidationRemainingBudget').text(response.message || 'Unavailable').addClass('text-danger');
            return;
        }
        $('#liquidationBudgetCode').text(response.data.budget_code || row.budget_code || 'N/A');
        $('#liquidationBudgetName').text(response.data.budget_activity || 'N/A');
        $('#liquidationRemainingBudget').text(money(response.data.remaining_budget)).toggleClass('text-danger', Number(response.data.remaining_budget) < 0);
    }).fail(xhr => {
        $('#liquidationBudgetName').text('Unavailable');
        $('#liquidationRemainingBudget').text(xhr.responseJSON?.message || 'Unable to load budget').addClass('text-danger');
    });
}

$(document).on('click', '.workflow-action', function() {
    const row = actionRow(this);
    if (row.request_type === 'Cash Advance' && row.pr_status === 'For PO') {
        liquidationSubmissionMode = 'cash_advance';
        selectedLiquidationRow = row;
        $('#liquidationModalTitle').text('Submit Cash Advance Liquidation');
        $('#liquidationNumber').val(row.pr_number); $('#actualAmount').val(row.po_amount || ''); $('#liquidationFile').val(''); $('#liquidationRemarks').val(row.liquidation_remarks || '');
        loadLiquidationBudget(row);
        bootstrap.Modal.getOrCreateInstance(document.getElementById('liquidationModal')).show(); return;
    }
    if (row.requires_sap_next) {
        Swal.fire({title:'SAP Document Number', input:'text', inputValue:row.sap_document_number || '', inputValidator:value => !value?.trim() ? 'SAP Document Number is required.' : null, showCancelButton:true, confirmButtonText:'Save and proceed'}).then(result => { if (result.isConfirmed) updateStatus(row, result.value.trim()); }); return;
    }
    Swal.fire({title:'Confirm action', text:`Move ${row.pr_number} to ${row.next_status === 'Completed' ? 'Completed' : row.next_status}?`, icon:'warning', showCancelButton:true, confirmButtonText:'Yes, proceed'}).then(result => { if (result.isConfirmed) updateStatus(row, ''); });
});

$(document).on('click', '.create-linked-rfp-liquidation', function() {
    const row = actionRow(this);
    liquidationSubmissionMode = 'linked_rfp';
    selectedLiquidationRow = row;
    $('#liquidationModalTitle').text('Create Linked RFP Liquidation');
    $('#liquidationNumber').val(row.pr_number);
    $('#actualAmount').val(row.ca_amount || '');
    $('#liquidationFile').val('');
    $('#liquidationRemarks').val('');
    loadLiquidationBudget(row);
    bootstrap.Modal.getOrCreateInstance(document.getElementById('liquidationModal')).show();
});

$('#submitLiquidation').on('click', function() {
    const number = $('#liquidationNumber').val(), amount = $('#actualAmount').val(), file = $('#liquidationFile')[0].files[0];
    if (!amount || Number(amount) <= 0 || !file) { Swal.fire('Required', 'Actual amount and attachment are required.', 'error'); return; }
    if (liquidationSubmissionMode === 'linked_rfp') {
        if (!selectedLiquidationRow?.pr_id) { Swal.fire('Error', 'RFP source was not found.', 'error'); return; }
        const form = new FormData();
        form.append('source_rfp_id', selectedLiquidationRow.pr_id);
        form.append('actual_amount', amount);
        form.append('remarks', $('#liquidationRemarks').val());
        form.append('attachment', file);
        $.ajax({url:'data/create_purchasing_rfp_liquidation.php', method:'POST', data:form, processData:false, contentType:false, dataType:'json'}).done(response => {
            if (response.status !== 'success') { Swal.fire('Error', response.message || 'Unable to create liquidation.', 'error'); return; }
            bootstrap.Modal.getInstance(document.getElementById('liquidationModal'))?.hide();
            Swal.fire('Success', response.message, 'success');
            table.ajax.reload(null, false);
        }).fail(xhr => Swal.fire('Error', xhr.responseJSON?.message || 'Unable to create liquidation.', 'error'));
        return;
    }
    const form = new FormData(); form.append('ca_number', number); form.append('po_amount', amount); form.append('liquidation_remarks', $('#liquidationRemarks').val()); form.append('ca_file', file);
    $.ajax({url:'data/upload_ca_file.php', method:'POST', data:form, processData:false, contentType:false, dataType:'json'}).done(response => {
        if (response.status !== 'success') { Swal.fire('Error', response.message, 'error'); return; }
        const row = rows.find(item => item.pr_number === number); updateStatus(row, ''); bootstrap.Modal.getInstance(document.getElementById('liquidationModal'))?.hide();
    }).fail(() => Swal.fire('Error', 'Unable to upload liquidation details.', 'error'));
});
</script>
<?php include 'footer.php'; ?>
