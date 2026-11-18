<?php
require '../init.php';
require_once __DIR__ . '/data/cash_advance_number_helper.php';

$requestDepartment = trim($_SESSION['department'] ?? '');
$requestDealer = trim($_SESSION['dealer'] ?? '');
$departmentSql = "
    SELECT department FROM (
        SELECT TRIM(department) AS department FROM users WHERE TRIM(COALESCE(department, '')) <> ''
        UNION
        SELECT TRIM(budget_department) AS department FROM budgets WHERE TRIM(COALESCE(budget_department, '')) <> ''
    ) available_departments
";
$departmentParams = [];
if ($requestDealer !== '' && strtoupper($requestDealer) !== 'OG') {
    $departmentSql = "
        SELECT department FROM (
            SELECT TRIM(department) AS department FROM users
            WHERE TRIM(COALESCE(department, '')) <> '' AND UPPER(TRIM(dealer)) = UPPER(:user_dealer)
            UNION
            SELECT TRIM(budget_department) AS department FROM budgets
            WHERE TRIM(COALESCE(budget_department, '')) <> '' AND UPPER(TRIM(budget_dealer)) = UPPER(:budget_dealer)
        ) available_departments
    ";
    $departmentParams = [':user_dealer' => $requestDealer, ':budget_dealer' => $requestDealer];
}
$departmentSql .= ' ORDER BY department';
$departmentStmt = $pdo->prepare($departmentSql);
$departmentStmt->execute($departmentParams);
$availableDepartments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN);
if ($requestDepartment !== '' && !in_array($requestDepartment, $availableDepartments, true)) {
    $availableDepartments[] = $requestDepartment;
    natcasesort($availableDepartments);
}

ini_set('display_errors', 1);
error_reporting(E_ALL);
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);

$generatedCaNumber = generateCashAdvanceNumber($pdo, $_SESSION['dealer'] ?? '', 'Cash Advance');
$generatedRfpNumber = generateCashAdvanceNumber($pdo, $_SESSION['dealer'] ?? '', 'Request for Payment');
$generatedLiquidationNumber = generateCashAdvanceNumber($pdo, $_SESSION['dealer'] ?? '', 'Liquidation');

include 'header.php';
include 'navigation.php';
?>
<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet" />
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>
<link rel="stylesheet" href="assets/css/liquidations.css?v=20260824">


<style>
    /* Target Select2 placeholder text */
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        font-size: 0.85rem;
        /* Smaller than normal */
        color: #bec8d0;
        /* Bootstrap's secondary text */
    }

    .request-subtitle { margin: 6px 0 0; color: #667085; font-size: 0.9rem; }
    .request-form-card { border: 1px solid #e6e9ef; border-radius: 8px; box-shadow: 0 10px 30px rgba(16, 24, 40, 0.06); overflow: hidden; }
    .request-form-card .card-body { padding: 1.5rem; }
    .profile-form-section + .profile-form-section { border-top: 1px solid #eef1f5; margin-top: 1.5rem; padding-top: 1.5rem; }
    .section-heading { display: flex; align-items: flex-start; gap: 0.8rem; margin-bottom: 1.25rem; }
    .section-icon { width: 40px; height: 40px; border-radius: 8px; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 40px; background: #eef6ff; color: #1677ff; }
    .section-heading h5 { margin: 0 0 3px; font-size: 1rem; }
    .section-heading p { margin: 0; color: #667085; font-size: 0.86rem; }
    .request-form-card .form-control,
    .request-form-card .form-select,
    .request-form-card .select2-container--bootstrap-5 .select2-selection { border-radius: 8px; }
    .request-form-card .form-control:focus,
    .request-form-card .form-select:focus { border-color: #84adff; box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.12); }
    .request-form-card .form-control:disabled,
    .request-form-card .form-control[readonly],
    .request-form-card .form-select:disabled { color: #475467; background-color: #f8fafc; opacity: 1; }
    .allocation-row { border: 1px solid #e6e9ef !important; border-radius: 8px !important; background: #fbfcfe; }
    .allocation-row strong { color: #344054; }
    .allocation-total { padding: 0.8rem 1rem; border-radius: 8px; background: #f8fafc; color: #344054; }
    .form-actions { border-top: 1px solid #eef1f5; margin-top: 1.5rem; padding-top: 1.25rem; }
    .cash-request-type-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .allocation-row { padding: 18px !important; }
    .allocation-total { margin-top: 14px !important; }
    .liquidation-page .select2-container--bootstrap-5 .select2-selection { border-color: #d8dde6; border-radius: 8px; min-height: 44px; }
    .liquidation-page .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered { line-height: 30px; }
    @media (max-width: 767.98px) { .cash-request-type-grid { grid-template-columns: 1fr; } }
</style>
<!-- [ Main Content ] start -->
<div class="pc-container liquidation-page">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="page-header-title">
                                <h2 class="mb-0">Add Financial Request</h2>
                                <p class="liquidation-subtitle">Create a cash advance, request for payment, or liquidation.</p>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="my_cash_advance_requests" class="btn btn-outline-primary d-inline-flex align-items-center gap-2"><i class="ti ti-list-check f-18"></i> My Requests</a>
                                <a href="cash_advance_process_flow" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="ti ti-route f-18"></i> Process Flow</a>
                                <a href="add_cash_advance_tutorial" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="ti ti-help-circle f-18"></i> Tutorial</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card liquidation-card">
                    <div class="card-body">
                        <form id="addItemForm" enctype="multipart/form-data">
                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-receipt f-22"></i></span>
                                    <div><h5>Request Type</h5><p>Choose the transaction you want to submit for approval.</p></div>
                                </div>
                                <div class="request-type-grid cash-request-type-grid" role="group" aria-label="Request type">
                                    <input type="radio" class="btn-check" name="request_type" id="typeCashAdvance" value="Cash Advance" checked>
                                    <label class="request-type-option" for="typeCashAdvance">
                                        <i class="ti ti-wallet f-22"></i>
                                        <span><strong>Cash Advance</strong><small>Request funds before an activity or purchase.</small></span>
                                    </label>
                                    <input type="radio" class="btn-check" name="request_type" id="typeLiquidation" value="Liquidation">
                                    <label class="request-type-option" for="typeLiquidation">
                                        <i class="ti ti-receipt f-22"></i>
                                        <span><strong>Liquidation</strong><small>Record actual expenses with the required supporting documents.</small></span>
                                    </label>
                                    <input type="radio" class="btn-check" name="request_type" id="typeRfp" value="Request for Payment">
                                    <label class="request-type-option" for="typeRfp">
                                        <i class="ti ti-cash f-22"></i>
                                        <span><strong>Request for Payment</strong><small>Submit a direct payment request against a department budget.</small></span>
                                    </label>
                                </div>
                            </div>

                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-calendar-dollar f-20"></i></span>
                                    <div><h5>Request Details</h5><p>Enter the request number, schedule, payee, and amount.</p></div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <label for="caNumber" class="form-label required-label" id="requestNumberLabel">Cash Advance Number</label>
                                        <input class="form-control" id="caNumber" name="ca_number" value="<?= htmlspecialchars($generatedCaNumber) ?>" data-ca-number="<?= htmlspecialchars($generatedCaNumber) ?>" data-rfp-number="<?= htmlspecialchars($generatedRfpNumber) ?>" data-liquidation-number="<?= htmlspecialchars($generatedLiquidationNumber) ?>" readonly required>
                                    </div>
                                    <div class="col-lg-4">
                                        <label for="caDate" class="form-label required-label" id="requestDateLabel">Cash Advance Date</label>
                                        <input class="form-control" id="caDate" name="ca_date" type="date" value="<?= date('Y-m-d') ?>" required>
                                    </div>
                                    <div class="col-lg-4" id="requestDepartmentField">
                                        <label for="pr_department" class="form-label required-label">Department</label>
                                        <select class="form-select" name="pr_department" id="pr_department" required>
                                            <option value="" disabled>Select Department</option>
                                            <?php foreach ($availableDepartments as $departmentOption): ?>
                                                <option value="<?= htmlspecialchars($departmentOption, ENT_QUOTES, 'UTF-8') ?>" <?= strcasecmp($departmentOption, $requestDepartment) === 0 ? 'selected' : '' ?>><?= htmlspecialchars($departmentOption, ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-lg-4 d-none" id="payableToField">
                                        <label class="form-label required-label" for="payable_to">Payable To</label>
                                        <input class="form-control" id="payable_to" name="payable_to" maxlength="255" placeholder="Enter payee name">
                                    </div>
                                    <div class="col-12 d-none" id="rfpLiquidationRequirementField">
                                        <label class="form-label required-label">Payment Type</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <label class="form-check"><input class="form-check-input" type="radio" name="requires_liquidation" value="0" checked> <span class="form-check-label">Direct Payment / No Liquidation</span></label>
                                            <label class="form-check"><input class="form-check-input" type="radio" name="requires_liquidation" value="1"> <span class="form-check-label">Requires Liquidation</span></label>
                                        </div>
                                    </div>
                                    <div class="col-lg-4 d-none" id="activityDateField">
                                        <label for="activity_date" class="form-label required-label">Activity Date</label>
                                        <input type="date" class="form-control" id="activity_date" name="activity_date" value="<?= date('Y-m-d') ?>">
                                    </div>
                                    <div class="col-lg-4 d-none" id="contentNameField">
                                        <label for="content_name" class="form-label">Content</label>
                                        <input type="text" class="form-control" id="content_name" name="content_name" maxlength="255" placeholder="Enter content name">
                                    </div>
                                    <div class="col-lg-4" id="singleBudgetField">
                                        <label class="form-label required-label" for="budget_code">Budget Code</label>
                                        <select class="form-select" name="budget_id" id="budget_code" required disabled><option value="" selected>Loading accessible budget codes...</option></select>
                                        <small id="remainingBudgetInfo" class="supporting-text" style="display: none;"></small>
                                    </div>
                                    <div class="col-lg-4" id="singleAmountField">
                                        <label for="caAmount" class="form-label required-label" id="requestAmountLabel">Cash Advance Amount</label>
                                        <input class="form-control" id="caAmount" name="ca_amount" type="number" min="0.01" step="0.01" placeholder="0.00" required>
                                        <small class="supporting-text">All RFPs require General Manager approval. Cash Advances are limited to PHP 10,000.</small>
                                    </div>
                                    <div class="col-lg-4" id="singlePrField">
                                        <label class="form-label" for="purchase_requisition_number">Purchase Requisition</label>
                                        <select class="form-select" name="purchase_requisition_number" id="purchase_requisition_number" disabled><option value="" selected>Select department first</option></select>
                                        <small class="supporting-text">Optional</small>
                                    </div>
                                </div>
                            </div>

                            <div id="rfpAllocationsField" class="liquidation-form-section d-none">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-chart-pie f-20"></i></span>
                                    <div class="flex-grow-1"><h5>Budget Information</h5><p>Select a department, then its budget code and optional purchase requisition.</p></div>
                                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="addAllocationRow" aria-hidden="true" tabindex="-1"><i class="ti ti-plus f-18"></i> Add Budget</button>
                                </div>
                                <div id="allocationRows"></div>
                                <div class="budget-summary allocation-total text-end fw-semibold">Total RFP Amount: <span id="allocationTotal">₱0.00</span></div>
                            </div>

                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-paperclip f-20"></i></span>
                                    <div><h5>Supporting Information</h5><p>Add notes and attach supporting documents for the approver.</p></div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-7">
                                        <label class="form-label" for="remarks">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="4" maxlength="1000" placeholder="Add context or notes for the approver"></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label" for="caFile">Attachment</label>
                                        <div class="upload-field">
                                            <input class="form-control" id="caFile" name="ca_file" type="file" accept="application/pdf,image/jpeg,image/png">
                                            <small class="supporting-text" id="attachmentHelp">Optional. PDF, JPG, or PNG. Max 5MB.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="liquidation-form-actions">
                                <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='items'">Cancel</button>
                                <button type="submit" id="submitCashAdvance" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2"><i class="ti ti-send f-18"></i> Submit Request</button>
                            </div>
                        </form>
                        <div id="responseMsg"></div>
                    </div>
                </div>

            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<script>
    $(function() {
        let remainingBudget = null;
        let budgetExceededAlertShown = false;
        let allocationBudgetExceededAlertKey = '';
        let caLimitPromptOpen = false;
        let caLimitPromptTimer = null;
        let lastPromptedCaAmount = null;

        function formatCurrency(amount) {
            return '₱' + Number(amount || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function getSwalIcon(status) {
            return status === 'success' ? 'success' : 'error';
        }

        function selectedRequestType() {
            return $('input[name="request_type"]:checked').val() || 'Cash Advance';
        }

        function promptCashAdvanceToRfp() {
            if (caLimitPromptOpen) return;
            caLimitPromptOpen = true;
            Swal.fire({
                title: 'Use Request for Payment',
                text: 'Cash Advance is limited to PHP 10,000. Amounts above PHP 10,000 must be submitted as Request for Payment.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonText: 'Switch to RFP',
                cancelButtonText: 'Edit amount'
            }).then(result => {
                if (result.isConfirmed) {
                    $('#typeRfp').prop('checked', true).trigger('change');
                    $('#payable_to').trigger('focus');
                } else {
                    $('#caAmount').trigger('focus');
                }
            }).finally(() => { caLimitPromptOpen = false; });
        }

        function scheduleCashAdvanceLimitPrompt() {
            clearTimeout(caLimitPromptTimer);
            const amount = Number($('#caAmount').val() || 0);
            if (selectedRequestType() !== 'Cash Advance' || amount <= 10000) {
                lastPromptedCaAmount = null;
                return;
            }
            caLimitPromptTimer = setTimeout(() => {
                if (lastPromptedCaAmount === amount) return;
                lastPromptedCaAmount = amount;
                promptCashAdvanceToRfp();
            }, 350);
        }

        function updateRequestTypeLabels() {
            const requestType = selectedRequestType();
            const isRfp = requestType === 'Request for Payment';
            const isLiquidation = requestType === 'Liquidation';
            const shortLabel = isRfp ? 'RFP' : (isLiquidation ? 'Liquidation' : 'Cash Advance');
            $('#requestNumberLabel').text(shortLabel + ' Number');
            $('#requestDateLabel').text(shortLabel + ' Date');
            $('#requestAmountLabel').text(shortLabel + ' Amount');
            $('#caAmount').attr('placeholder', 'Enter ' + shortLabel + ' Amount');
            $('#caNumber').val(isRfp ? $('#caNumber').data('rfp-number') : (isLiquidation ? $('#caNumber').data('liquidation-number') : $('#caNumber').data('ca-number')));
            $('#payableToField, #rfpAllocationsField, #rfpLiquidationRequirementField').toggleClass('d-none', !isRfp);
            $('#activityDateField, #contentNameField').toggleClass('d-none', !isLiquidation);
            $('#activity_date').prop('required', isLiquidation).prop('disabled', !isLiquidation);
            $('#content_name').prop('disabled', !isLiquidation);
            $('#caFile').prop('required', isLiquidation);
            $('#attachmentHelp').text(isLiquidation ? 'Required. PDF, JPG, or PNG. Max 5MB.' : 'Optional. PDF, JPG, or PNG. Max 5MB.');
            $('#singleBudgetField, #singleAmountField, #singlePrField').toggleClass('d-none', isRfp);
            $('#requestDepartmentField').toggleClass('d-none', isRfp);
            $('#payable_to').prop('required', isRfp).prop('disabled', !isRfp);
            $('input[name="requires_liquidation"]').prop('disabled', !isRfp);
            $('#budget_code, #purchase_requisition_number').prop('disabled', isRfp);
            $('#pr_department').prop('disabled', isRfp);
            $('#allocationRows :input').prop('disabled', !isRfp);
            $('#caAmount').prop('readonly', isRfp);
            if (isRfp && !$('#allocationRows .allocation-row').length) {
                addAllocationRow();
            }
            if (!isRfp) {
                $('#caAmount').prop('readonly', false);
                loadAccessibleBudgetCodes();
                loadPurchaseRequisitionsByDepartment($('#pr_department').val());
            }
        }

        function allocationOptions(sourceSelector, placeholder) {
            let html = `<option value="">${placeholder}</option>`;
            $(sourceSelector).find('option').each(function() {
                const value = $(this).attr('value');
                if (value) html += $('<option>').val(value).text($(this).text()).prop('outerHTML');
            });
            return html;
        }

        function updateAllocationTotal() {
            let total = 0;
            $('.allocation-amount').each(function() { total += parseFloat($(this).val()) || 0; });
            $('#caAmount').val(total > 0 ? total.toFixed(2) : '');
            $('#allocationTotal').text(formatCurrency(total));
        }

        function validateAllocationBudgets(showAlert) {
            const totalsByBudget = {};
            let exceeded = null;

            $('.allocation-row').each(function() {
                const budgetId = String($(this).find('.allocation-budget').val() || '');
                const amount = parseFloat($(this).find('.allocation-amount').val()) || 0;
                if (budgetId && amount > 0) totalsByBudget[budgetId] = (totalsByBudget[budgetId] || 0) + amount;
            });

            $('.allocation-row').each(function() {
                const $row = $(this);
                const $amount = $row.find('.allocation-amount');
                const budgetId = String($row.find('.allocation-budget').val() || '');
                const remaining = $row.data('remaining');
                const allocated = totalsByBudget[budgetId] || 0;
                const isExceeded = budgetId && remaining !== undefined && allocated > Number(remaining);

                $amount.toggleClass('is-invalid', !!isExceeded);
                $row.find('.allocation-amount-feedback')
                    .toggle(!!isExceeded)
                    .text(isExceeded ? 'Allocated amount exceeds the remaining budget of ' + formatCurrency(remaining) + '.' : '');

                if (isExceeded && !exceeded) {
                    exceeded = { budgetId: budgetId, allocated: allocated, remaining: Number(remaining) };
                }
            });

            if (exceeded) {
                $('#submitCashAdvance').prop('disabled', true);
                const alertKey = exceeded.budgetId + ':' + exceeded.allocated + ':' + exceeded.remaining;
                if (showAlert && allocationBudgetExceededAlertKey !== alertKey) {
                    allocationBudgetExceededAlertKey = alertKey;
                    Swal.fire({
                        icon: 'error',
                        title: 'Budget exceeded',
                        text: 'The total allocated amount of ' + formatCurrency(exceeded.allocated) +
                            ' cannot be higher than the remaining budget of ' + formatCurrency(exceeded.remaining) + '.'
                    });
                }
                return false;
            }

            allocationBudgetExceededAlertKey = '';
            if (selectedRequestType() === 'Request for Payment') $('#submitCashAdvance').prop('disabled', false);
            return true;
        }

        function addAllocationRow() {
            const index = $('#allocationRows .allocation-row').length;
            const row = $(`
                <div class="allocation-row border rounded p-3 mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Budget Details</strong>
                        <button type="button" class="btn btn-sm btn-outline-danger remove-allocation d-none" title="Remove" aria-hidden="true" tabindex="-1"><i class="ti ti-trash"></i></button>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Department</label>
                        <select class="form-select allocation-department" name="allocations[${index}][department]" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Budget Code</label>
                        <select class="form-select allocation-budget" name="allocations[${index}][budget_id]" required></select>
                        <div class="form-text allocation-budget-info"></div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Purchase Requisition <span class="text-muted">(Optional)</span></label>
                        <select class="form-select allocation-pr" name="allocations[${index}][purchase_requisition_number]"></select>
                    </div>
                    <div>
                        <label class="form-label">Amount</label>
                        <input type="number" min="0.01" step="0.01" class="form-control allocation-amount" name="allocations[${index}][amount]" required>
                        <div class="allocation-amount-feedback invalid-feedback"></div>
                    </div>
                </div>`);
            row.find('.allocation-department').html(allocationOptions('#pr_department', 'Select Department'));
            row.find('.allocation-budget').html('<option value="">Select Department first</option>').prop('disabled', true);
            row.find('.allocation-pr').html('<option value="">Select Department first</option>').prop('disabled', true);
            $('#allocationRows').append(row);
            row.find('select').select2({ theme: 'bootstrap-5', width: '100%', allowClear: true });
            renumberAllocations();
        }

        function renumberAllocations() {
            $('#allocationRows .allocation-row').each(function(index) {
                $(this).find('.allocation-number').text(index + 1);
                $(this).find('.allocation-department').attr('name', `allocations[${index}][department]`);
                $(this).find('.allocation-budget').attr('name', `allocations[${index}][budget_id]`);
                $(this).find('.allocation-pr').attr('name', `allocations[${index}][purchase_requisition_number]`);
                $(this).find('.allocation-amount').attr('name', `allocations[${index}][amount]`);
            });
            $('.remove-allocation').prop('disabled', $('#allocationRows .allocation-row').length === 1);
        }

        function refreshAllocationOptions(selector, sourceSelector, placeholder) {
            $(selector).each(function() {
                const value = $(this).val();
                $(this).html(allocationOptions(sourceSelector, placeholder)).val(value).trigger('change.select2');
            });
        }

        $('#addAllocationRow').on('click', addAllocationRow);
        $('#allocationRows').on('click', '.remove-allocation', function() {
            $(this).closest('.allocation-row').find('select').select2('destroy');
            $(this).closest('.allocation-row').remove();
            renumberAllocations();
            updateAllocationTotal();
            validateAllocationBudgets(false);
        }).on('input', '.allocation-amount', function() {
              updateAllocationTotal();
              validateAllocationBudgets(false);
          })
          .on('change blur', '.allocation-amount', function() {
              validateAllocationBudgets(true);
          })
          .on('change', '.allocation-department', function() {
              const $row = $(this).closest('.allocation-row');
              const department = $(this).val();
              const $budget = $row.find('.allocation-budget');
              const $pr = $row.find('.allocation-pr');
              $row.removeData('remaining').find('.allocation-budget-info').text('');
              validateAllocationBudgets(false);
              $budget.html('<option value="">Loading budget codes...</option>').prop('disabled', true).trigger('change.select2');
              $pr.html('<option value="">Loading purchase requisitions...</option>').prop('disabled', true).trigger('change.select2');
              if (!department) return;

              $.getJSON('data/get_my_department_budgets.php', { department: department }).done(function(res) {
                  $budget.empty().append('<option value="">Select Budget Code</option>');
                  if (res.status === 'success' && Array.isArray(res.data)) {
                      res.data.forEach(function(item) {
                          $('<option>').val(item.budget_id).text(item.budget_code + (item.budget_activity ? ' - ' + item.budget_activity : '')).appendTo($budget);
                      });
                  }
                  $budget.prop('disabled', false).trigger('change.select2');
              }).fail(function() {
                  $budget.html('<option value="">Failed to load budget codes</option>').prop('disabled', true).trigger('change.select2');
              });

              $.getJSON('data/get_purchase_requisitions_by_department.php', { department: department }).done(function(res) {
                  $pr.empty().append('<option value="">No Purchase Requisition selected</option>');
                  if (res.status === 'success' && Array.isArray(res.data)) {
                      res.data.forEach(function(item) {
                          $('<option>').val(item.pr_number).text(item.pr_number + (item.budget_code ? ' - ' + item.budget_code : '')).appendTo($pr);
                      });
                  } else if (res.message) {
                      $pr.empty().append($('<option>').val('').text(res.message));
                  }
                  $pr.prop('disabled', false).trigger('change.select2');
              }).fail(function() {
                  $pr.html('<option value="">Failed to load purchase requisitions</option>').prop('disabled', true).trigger('change.select2');
              });
          })
          .on('change', '.allocation-budget', function() {
              const $row = $(this).closest('.allocation-row');
              const budgetId = $(this).val();
              $row.removeData('remaining');
              $row.find('.allocation-budget-info').text(budgetId ? 'Loading remaining budget...' : '');
              validateAllocationBudgets(false);
              if (!budgetId) return;
              $.getJSON('data/get_cash_advance_budget_remaining.php', {
                  budget_id: budgetId,
                  department: $row.find('.allocation-department').val()
              })
                .done(function(res) {
                    if (res.status === 'success' && res.data) {
                        $row.data('remaining', parseFloat(res.data.remaining_budget));
                        $row.find('.allocation-budget-info').text('Remaining Budget: ' + formatCurrency(res.data.remaining_budget));
                        validateAllocationBudgets(true);
                    } else {
                        $row.find('.allocation-budget-info').addClass('text-danger').text(res.message || 'Unable to load budget.');
                    }
                }).fail(function() { $row.find('.allocation-budget-info').addClass('text-danger').text('Unable to load budget.'); });
          });

        $('#budget_code, #purchase_requisition_number').select2({
            theme: 'bootstrap-5',
            width: '100%',
            allowClear: true
        });

        function resetRemainingBudgetInfo(message, isError) {
            remainingBudget = null;
            budgetExceededAlertShown = false;
            $('#remainingBudgetInfo')
                .toggle(!!message)
                .toggleClass('text-danger', !!isError)
                .toggleClass('text-muted', !isError)
                .text(message || '');
            $('#caAmount').removeClass('is-invalid');
            $('#submitCashAdvance').prop('disabled', false);
        }

        function validateCashAdvanceAmount(showAlert) {
            const amount = parseFloat($('#caAmount').val());

            if (remainingBudget === null || Number.isNaN(amount) || amount <= 0) {
                $('#caAmount').removeClass('is-invalid');
                $('#submitCashAdvance').prop('disabled', false);
                budgetExceededAlertShown = false;
                return true;
            }

            if (amount > remainingBudget) {
                $('#caAmount').addClass('is-invalid');
                $('#submitCashAdvance').prop('disabled', true);

                if (showAlert && !budgetExceededAlertShown) {
                    budgetExceededAlertShown = true;
                    Swal.fire({
                        icon: 'error',
                        title: 'Budget exceeded',
                        text: 'Request amount cannot be higher than the remaining budget of ' + formatCurrency(remainingBudget) + '.'
                    });
                }

                return false;
            }

            $('#caAmount').removeClass('is-invalid');
            $('#submitCashAdvance').prop('disabled', false);
            budgetExceededAlertShown = false;
            return true;
        }

        function resetBudgetCodeSelect(placeholderText) {
            const $budget = $('#budget_code');
            $budget.empty().append(`<option value="" selected>${placeholderText}</option>`);
            $budget.prop('disabled', true);
            $budget.trigger('change.select2');
            resetRemainingBudgetInfo('', false);
        }

        function resetPurchaseRequisitionSelect(placeholderText) {
            const $pr = $('#purchase_requisition_number');
            $pr.empty().append(`<option value="" selected>${placeholderText}</option>`);
            $pr.prop('disabled', true);
            $pr.trigger('change.select2');
        }

        function loadAccessibleBudgetCodes() {
            if (!$('#pr_department').val()) {
                resetBudgetCodeSelect('Your account has no department');
                return;
            }

            const $budget = $('#budget_code');
            resetBudgetCodeSelect('Loading budget codes...');

            $.ajax({
                url: 'data/get_my_department_budgets.php',
                type: 'GET',
                data: { department: $('#pr_department').val() },
                dataType: 'json',
                success: function(res) {
                    $budget.empty();
                    $budget.append('<option value="" selected disabled>Select Budget Code</option>');

                    if (res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                        res.data.forEach(function(item) {
                            const ownerDepartment = item.access_source === 'granted' && item.budget_department
                                ? ' - ' + item.budget_department
                                : '';
                            const activityName = item.budget_activity ? ' - ' + item.budget_activity : '';
                            $('<option>')
                                .val(item.budget_id)
                                .text(item.budget_code + ownerDepartment + activityName)
                                .appendTo($budget);
                        });
                        $budget.prop('disabled', selectedRequestType() === 'Request for Payment');
                        $budget.trigger('change.select2');
                        refreshAllocationOptions('.allocation-budget', '#budget_code', 'Select Budget Code');
                    } else {
                        $budget.empty().append('<option value="" selected>No budget code available for this department</option>');
                        $budget.prop('disabled', true);
                        resetRemainingBudgetInfo('', false);
                        $budget.trigger('change.select2');
                    }
                },
                error: function() {
                    resetBudgetCodeSelect('Failed to load budget codes');
                }
            });
        }

        function loadPurchaseRequisitionsByDepartment(department) {
            if (!department) {
                resetPurchaseRequisitionSelect('Select department first');
                return;
            }

            const $pr = $('#purchase_requisition_number');
            resetPurchaseRequisitionSelect('Loading purchase requisitions...');

            $.ajax({
                url: 'data/get_purchase_requisitions_by_department.php',
                type: 'GET',
                data: {
                    department: department
                },
                dataType: 'json',
                success: function(res) {
                    $pr.empty();
                    $pr.append('<option value="" selected>No Purchase Requisition selected</option>');

                    if (res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                        res.data.forEach(function(item) {
                            const budgetCode = item.budget_code ? ' - ' + item.budget_code : '';
                            const dealer = item.pr_dealer ? ' - ' + item.pr_dealer : '';
                            const status = item.pr_status ? ' (' + item.pr_status + ')' : '';
                            $('<option>')
                                .val(item.pr_number)
                                .text(item.pr_number + dealer + budgetCode + status)
                                .appendTo($pr);
                        });
                        $pr.prop('disabled', selectedRequestType() === 'Request for Payment');
                    } else {
                        const emptyMessage = res.message || 'No matching active PR for this department - optional';
                        $pr.empty().append($('<option>').val('').prop('selected', true).text(emptyMessage));
                        $pr.prop('disabled', selectedRequestType() === 'Request for Payment');
                    }

                    $pr.trigger('change.select2');
                    refreshAllocationOptions('.allocation-pr', '#purchase_requisition_number', 'No Purchase Requisition selected');
                },
                error: function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'Failed to load purchase requisitions';
                    resetPurchaseRequisitionSelect(message);
                }
            });
        }

        function loadRemainingBudget() {
            const department = $('#pr_department').val();
            const budgetId = $('#budget_code').val();

            if (!department || !budgetId) {
                resetRemainingBudgetInfo('', false);
                return;
            }

            resetRemainingBudgetInfo('Loading remaining budget...', false);

            $.ajax({
                url: 'data/get_cash_advance_budget_remaining.php',
                type: 'GET',
                data: {
                    budget_id: budgetId,
                    department: department
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success' && res.data) {
                        remainingBudget = parseFloat(res.data.remaining_budget);
                        $('#remainingBudgetInfo')
                            .show()
                            .removeClass('text-danger')
                            .addClass('text-muted')
                            .text(
                                'Remaining Budget: ' + formatCurrency(remainingBudget) +
                                ' (Actual: ' + formatCurrency(res.data.actual_expenses) +
                                ', Pending: ' + formatCurrency(res.data.pending_expenses) + ')'
                            );
                        validateCashAdvanceAmount(false);
                    } else {
                        resetRemainingBudgetInfo(res.message || 'Failed to load remaining budget.', true);
                    }
                },
                error: function() {
                    resetRemainingBudgetInfo('Failed to load remaining budget.', true);
                }
            });
        }

        $('input[name="request_type"]').on('change', updateRequestTypeLabels);

        $('#pr_department').on('change', function() {
            $('.allocation-row').removeData('remaining').find('.allocation-budget-info').text('');
            $('.allocation-budget, .allocation-pr').val('').trigger('change');
            loadAccessibleBudgetCodes();
            loadPurchaseRequisitionsByDepartment($(this).val());
        });

        $('#budget_code').on('change', function() {
            loadRemainingBudget();
        });

        $('#caAmount').on('input', function() {
            validateCashAdvanceAmount(true);
            scheduleCashAdvanceLimitPrompt();
        });

        $('#addItemForm').submit(function(e) {
            e.preventDefault();

            if (selectedRequestType() === 'Cash Advance' && Number($('#caAmount').val() || 0) > 10000) {
                promptCashAdvanceToRfp();
                return;
            }

            if (selectedRequestType() === 'Request for Payment') {
                if (!validateAllocationBudgets(true)) return;
                let allocationValid = $('#allocationRows .allocation-row').length > 0;
                $('.allocation-row').each(function() {
                    const amount = parseFloat($(this).find('.allocation-amount').val()) || 0;
                    const remaining = $(this).data('remaining');
                    if (!$(this).find('.allocation-department').val() || !$(this).find('.allocation-budget').val() || amount <= 0 || (remaining !== undefined && amount > remaining)) {
                        allocationValid = false;
                    }
                });
                if (!allocationValid) {
                    Swal.fire({ icon: 'error', title: 'Invalid allocations', text: 'Choose a budget and valid amount for every allocation. Each amount must be within its remaining budget.' });
                    return;
                }
                updateAllocationTotal();
            }

            if (!validateCashAdvanceAmount(true)) {
                return;
            }

            let formData = new FormData(this);

            $.ajax({
                url: 'data/create_cash_advance.php',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                dataType: 'json',

                beforeSend: function() {
                    $('#submitCashAdvance').prop('disabled', true).text('Saving...');
                },

                success: function(res) {

                    $('#submitCashAdvance').prop('disabled', false).text('Submit Request');

                    if (res.code === 'CA_AMOUNT_REQUIRES_RFP') {
                        promptCashAdvanceToRfp();
                        return;
                    }

                    Swal.fire({
                        icon: getSwalIcon(res.status),
                        title: res.status === 'success' ? 'Success' : 'Error',
                        text: res.message
                    });

                    if (res.status === 'success') {
                        $('#addItemForm')[0].reset();
                        $('#allocationRows').empty();
                        if (res.next_numbers) {
                            $('#caNumber')
                                .data('ca-number', res.next_numbers.cash_advance || $('#caNumber').data('ca-number'))
                                .data('rfp-number', res.next_numbers.request_for_payment || $('#caNumber').data('rfp-number'))
                                .data('liquidation-number', res.next_numbers.liquidation || $('#caNumber').data('liquidation-number'));
                        } else if (res.next_ca_number) {
                            $('#caNumber').val(res.next_ca_number);
                        }
                        updateRequestTypeLabels();
                        $('#caDate').val(new Date().toISOString().slice(0, 10));
                        loadAccessibleBudgetCodes();
                        loadPurchaseRequisitionsByDepartment($('#pr_department').val());
                    }

                },

                error: function() {
                    $('#submitCashAdvance').prop('disabled', false).text('Submit Request');

                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: 'Please try again'
                    });
                }

            });

        });

        updateRequestTypeLabels();
        loadAccessibleBudgetCodes();
        loadPurchaseRequisitionsByDepartment($('#pr_department').val());

    });
</script>





<?php
include 'footer.php';
?>
