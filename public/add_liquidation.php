<?php
require '../init.php';
require_once __DIR__ . '/data/liquidation_helpers.php';

if (!liquidation_can_encode_department_request()) {
    http_response_code(403);
    die('Only Department PICs or Department Managers can add requests.');
}

$editRequest = null;
$editId = (int)($_GET['edit'] ?? 0);
if ($editId > 0) {
    $editStmt = $pdo->prepare("
        SELECT *
        FROM liquidations
        WHERE liquidation_id = :liquidation_id
          AND created_by = :created_by
          AND dealer = :dealer
          AND UPPER(TRIM(department)) = UPPER(TRIM(:department))
        LIMIT 1
    ");
    $editStmt->execute([
        ':liquidation_id' => $editId,
        ':created_by' => (int)($_SESSION['user_id'] ?? 0),
        ':dealer' => trim($_SESSION['dealer'] ?? ''),
        ':department' => trim($_SESSION['department'] ?? ''),
    ]);
    $editRequest = $editStmt->fetch(PDO::FETCH_ASSOC) ?: null;

    if (!$editRequest
        || !in_array($editRequest['status'] ?? '', ['For Department Manager Approval', 'Rejected'], true)
        || !empty($editRequest['source_cash_advance_id']) || !empty($editRequest['source_rfp_id'])) {
        http_response_code(403);
        die('This request can no longer be edited.');
    }
}

$departmentHeadName = '';
$departmentHeadStmt = $pdo->prepare("
    SELECT full_name
    FROM users
    WHERE is_active = 1
      AND dealer = :dealer
      AND UPPER(TRIM(department)) = UPPER(TRIM(:department))
      AND (COALESCE(is_depthead, 0) = 1 OR position = 'Manager')
    ORDER BY COALESCE(is_depthead, 0) DESC, full_name ASC
    LIMIT 1
");
$departmentHeadStmt->execute([
    ':dealer' => trim($_SESSION['dealer'] ?? ''),
    ':department' => trim($_SESSION['department'] ?? ''),
]);
$departmentHeadName = (string)$departmentHeadStmt->fetchColumn();
$requestedType = $editRequest['request_type'] ?? trim($_GET['request_type'] ?? 'Cash Advance');
$allowedRequestTypes = ['Cash Advance', 'Liquidation', 'Request for Payment'];
$initialRequestType = in_array($requestedType, $allowedRequestTypes, true) ? $requestedType : 'Cash Advance';

include 'header.php';
include 'navigation.php';
?>

<link rel="stylesheet" href="assets/css/liquidations.css?v=20260824">

<div class="pc-container liquidation-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                            <div class="page-header-title">
                                <h2 class="mb-0"><?= $editRequest ? 'Edit Financial Request' : 'Add Financial Request' ?></h2>
                                <p class="liquidation-subtitle"><?= $editRequest ? 'Update your request before Department Manager approval, then submit it again.' : 'Create a cash advance, liquidation, or request for payment against your department budget.' ?></p>
                            </div>
                            <a href="my_liquidations" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2">
                                <i class="ti ti-arrow-left f-18"></i> Back to Requests
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-sm-12">
                <div class="card liquidation-card">
                    <div class="card-body">
                        <form id="liquidationForm" enctype="multipart/form-data">
                            <?php if ($editRequest) { ?>
                                <input type="hidden" name="liquidation_id" value="<?= (int)$editRequest['liquidation_id'] ?>">
                                <div class="alert <?= ($editRequest['status'] ?? '') === 'Rejected' ? 'alert-danger' : 'alert-info' ?> mb-4">
                                    <strong><?= htmlspecialchars($editRequest['liquidation_number']) ?></strong>
                                    <?php if (($editRequest['status'] ?? '') === 'Rejected') { ?>
                                        was rejected<?= !empty($editRequest['rejection_reason']) ? ': ' . htmlspecialchars($editRequest['rejection_reason']) . '.' : '.' ?> Edit the request and resubmit it for approval.
                                    <?php } else { ?>
                                        is awaiting Department Manager approval and can still be edited.
                                    <?php } ?>
                                </div>
                            <?php } ?>
                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-receipt f-22"></i></span>
                                    <div>
                                        <h5>Request Type</h5>
                                        <p>Choose the transaction you want to submit for approval.</p>
                                    </div>
                                </div>
                                <div class="request-type-grid" role="group" aria-label="Request type">
                                        <input type="radio" class="btn-check" name="request_type" id="typeCashAdvance" value="Cash Advance" <?= $initialRequestType === 'Cash Advance' ? 'checked' : '' ?> <?= $editRequest && $initialRequestType !== 'Cash Advance' ? 'disabled' : '' ?>>
                                        <label class="request-type-option" for="typeCashAdvance">
                                            <i class="ti ti-wallet f-22"></i>
                                            <span><strong>Cash Advance</strong><small>Request funds before an activity or purchase.</small></span>
                                        </label>
                                        <input type="radio" class="btn-check" name="request_type" id="typeLiquidation" value="Liquidation" <?= $initialRequestType === 'Liquidation' ? 'checked' : '' ?> <?= $editRequest && $initialRequestType !== 'Liquidation' ? 'disabled' : '' ?>>
                                        <label class="request-type-option" for="typeLiquidation">
                                            <i class="ti ti-receipt f-22"></i>
                                            <span><strong>Liquidation</strong><small>Record actual expenses with supporting documents.</small></span>
                                        </label>
                                        <input type="radio" class="btn-check" name="request_type" id="typePayment" value="Request for Payment" <?= $initialRequestType === 'Request for Payment' ? 'checked' : '' ?> <?= $editRequest && $initialRequestType !== 'Request for Payment' ? 'disabled' : '' ?>>
                                        <label class="request-type-option" for="typePayment">
                                            <i class="ti ti-cash f-22"></i>
                                            <span><strong>Request for Payment</strong><small>Submit a direct payment request to a payee.</small></span>
                                        </label>
                                </div>
                            </div>

                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-chart-pie f-20"></i></span>
                                    <div>
                                        <h5>Budget Information</h5>
                                        <p>Select a budget code and review the funds currently available.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-lg-4">
                                        <label for="budgetSearch" class="form-label required-label">Budget Code</label>
                                        <div class="budget-combobox" id="budgetCombobox">
                                            <div class="input-group budget-search-group">
                                                <span class="input-group-text"><i class="ti ti-search f-18"></i></span>
                                                <input type="search" class="form-control" id="budgetSearch" placeholder="Search and select a budget code" autocomplete="off" role="combobox" aria-autocomplete="list" aria-controls="budgetOptionsDropdown" aria-expanded="false" disabled>
                                            </div>
                                            <div class="budget-options-dropdown d-none" id="budgetOptionsDropdown" role="listbox"></div>
                                            <select class="d-none" id="budget_id" name="budget_id" tabindex="-1" aria-hidden="true">
                                                <option value="">Loading budget codes...</option>
                                            </select>
                                        </div>
                                        <small class="supporting-text" id="budgetSearchResult">Budget codes are loading.</small>
                                    </div>
                                    <div class="col-lg-4">
                                        <label for="budget_activity" class="form-label">Budget Activity</label>
                                        <input type="text" class="form-control" id="budget_activity" value="" readonly>
                                    </div>
                                    <div class="col-lg-4">
                                        <label for="content_name" class="form-label">Content</label>
                                        <input type="text" class="form-control" id="content_name" name="content_name" maxlength="255" placeholder="Enter content name" value="<?= htmlspecialchars($editRequest['content_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-12">
                                        <div class="budget-summary">
                                            <div class="row g-3">
                                                <div class="col-md-4">
                                                    <label for="approved_budget" class="form-label">Approved Budget</label>
                                                    <input type="text" class="form-control" id="approved_budget" value="" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="committed_budget" class="form-label">Pending Transactions</label>
                                                    <input type="text" class="form-control" id="committed_budget" value="" readonly>
                                                </div>
                                                <div class="col-md-4">
                                                    <label for="remaining_budget" class="form-label">Remaining Budget</label>
                                                    <input type="text" class="form-control" id="remaining_budget" value="" readonly>
                                                    <div id="budgetWarning" class="invalid-feedback"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="liquidation-form-section">
                                <div class="liquidation-section-heading">
                                    <span class="liquidation-section-icon"><i class="ti ti-calendar-dollar f-20"></i></span>
                                    <div>
                                        <h5>Request Details</h5>
                                        <p>Enter the requested amount, schedule, payee, and supporting notes.</p>
                                    </div>
                                </div>
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="liquidation_amount" class="form-label required-label">Amount</label>
                                        <input type="number" step="0.01" min="0.01" class="form-control" id="liquidation_amount" name="liquidation_amount" placeholder="0.00" value="<?= htmlspecialchars($editRequest['liquidation_amount'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="liquidation_date" class="form-label required-label">Request Date</label>
                                        <input type="date" class="form-control" id="liquidation_date" name="liquidation_date" value="<?= htmlspecialchars($editRequest['liquidation_date'] ?? date('Y-m-d')) ?>" required>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="activity_date" class="form-label required-label">Activity Date</label>
                                        <input type="date" class="form-control" id="activity_date" name="activity_date" value="<?= htmlspecialchars($editRequest['activity_date'] ?? date('Y-m-d')) ?>" required>
                                    </div>
                                    <div class="col-md-6 d-none" id="rfpDepartmentHeadGroup">
                                        <label for="rfp_department_head_name" class="form-label required-label">Payable To</label>
                                        <input type="text" class="form-control" id="rfp_department_head_name" name="rfp_department_head_name" value="<?= htmlspecialchars($editRequest['rfp_department_head_name'] ?? $departmentHeadName, ENT_QUOTES, 'UTF-8') ?>" maxlength="255">
                                    </div>
                                    <div class="col-12 d-none" id="rfpLiquidationGroup">
                                        <label class="form-label required-label">Liquidation Requirement</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <label><input type="radio" name="requires_liquidation" value="1" <?= !$editRequest || (string)($editRequest['requires_liquidation'] ?? '') === '1' ? 'checked' : '' ?>> Requires Liquidation</label>
                                            <label><input type="radio" name="requires_liquidation" value="0" <?= $editRequest && (string)($editRequest['requires_liquidation'] ?? '') === '0' ? 'checked' : '' ?>> Direct Payment / No Liquidation</label>
                                        </div>
                                    </div>
                                    <div class="col-md-7">
                                        <label for="remarks" class="form-label">Remarks</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="4" maxlength="2000" placeholder="Add context or notes for the approver"><?= htmlspecialchars($editRequest['remarks'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                    </div>
                                    <div class="col-md-5">
                                        <label for="attachment" class="form-label">Attachment</label>
                                        <div class="upload-field">
                                            <input type="file" class="form-control" id="attachment" name="attachment" accept="application/pdf,image/jpeg,image/png">
                                            <small class="supporting-text" id="attachmentHelp">Required for Liquidation. PDF, JPG, or PNG. Max 5MB.</small>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="liquidation-form-actions">
                                <button type="button" class="btn btn-outline-secondary" onclick="window.location.href='my_liquidations'">Cancel</button>
                                <button type="submit" id="submitLiquidation" class="btn btn-primary d-inline-flex align-items-center justify-content-center gap-2">
                                    <i class="ti ti-send f-18"></i>
                                    <?= $editRequest && ($editRequest['status'] ?? '') === 'Rejected' ? 'Resubmit Request' : ($editRequest ? 'Save Changes' : 'Submit Request') ?>
                                </button>
                            </div>
                        </form>
                        <div id="responseMsg"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    let budgetMonitoring = null;
    let departmentBudgets = [];
    let caLimitPromptTimer = null;
    let caLimitPromptOpen = false;
    let lastPromptedCaAmount = null;
    const isEditMode = <?= $editRequest ? 'true' : 'false' ?>;
    const editRequestId = <?= (int)($editRequest['liquidation_id'] ?? 0) ?>;
    const editBudgetId = <?= (int)($editRequest['budget_id'] ?? 0) ?>;
    const hasExistingAttachment = <?= !empty($editRequest['attachment_path']) ? 'true' : 'false' ?>;

    function updateRequestTypeRules() {
        const requestType = $('input[name="request_type"]:checked').val();
        const isLiquidation = requestType === 'Liquidation';
        const isRequestForPayment = requestType === 'Request for Payment';
        const directPayment = isRequestForPayment && $('input[name="requires_liquidation"]:checked').val() === '0';
        $('#attachment').prop('required', (isLiquidation || directPayment) && !hasExistingAttachment);
        $('#rfpDepartmentHeadGroup').toggleClass('d-none', !isRequestForPayment);
        $('#rfpLiquidationGroup').toggleClass('d-none', !isRequestForPayment);
        $('input[name="requires_liquidation"]').prop('disabled', !isRequestForPayment);
        $('#rfp_department_head_name').prop('required', isRequestForPayment);
        let helpText = 'Optional for Request for Payment. PDF, JPG, or PNG. Max 5MB.';
        if (isLiquidation || directPayment) {
            helpText = hasExistingAttachment
                ? 'A supporting file is already attached. Choose another file only to replace it.'
                : 'Required for Liquidation. PDF, JPG, or PNG. Max 5MB.';
        } else if (requestType === 'Cash Advance') {
            helpText = 'Optional for Cash Advance. PDF, JPG, or PNG. Max 5MB.';
        }
        if (hasExistingAttachment && !isLiquidation) {
            helpText = 'A file is already attached. Choose another file only to replace it.';
        }
        $('#attachmentHelp').text(helpText);
    }

    function promptCashAdvanceToRfp() {
        if (isEditMode) {
            return Swal.fire('Amount not allowed', 'Cash Advance is limited to PHP 10,000. Reduce the amount before saving.', 'error');
        }
        if (caLimitPromptOpen) return Promise.resolve();
        caLimitPromptOpen = true;

        return Swal.fire({
            title: 'Use Request for Payment',
            text: 'Cash Advance is limited to PHP 10,000. Amounts above PHP 10,000 must be submitted as Request for Payment.',
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Switch to RFP',
            cancelButtonText: 'Edit amount'
        }).then((result) => {
            if (result.isConfirmed) {
                $('#typePayment').prop('checked', true).trigger('change');
                $('#rfp_department_head_name').trigger('focus');
            } else {
                $('#liquidation_amount').trigger('focus');
            }
        }).finally(() => {
            caLimitPromptOpen = false;
        });
    }

    function scheduleCashAdvanceLimitPrompt() {
        clearTimeout(caLimitPromptTimer);

        const requestType = $('input[name="request_type"]:checked').val();
        const amount = Number($('#liquidation_amount').val() || 0);
        if (requestType !== 'Cash Advance' || amount <= 10000) {
            lastPromptedCaAmount = null;
            return;
        }

        caLimitPromptTimer = setTimeout(function() {
            if (lastPromptedCaAmount === amount) return;
            lastPromptedCaAmount = amount;
            promptCashAdvanceToRfp();
        }, 350);
    }

    function formatCurrency(amount) {
        return '\u20B1 ' + Number(amount || 0).toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function resetBudgetMonitoring() {
        budgetMonitoring = null;
        $('#budget_activity, #approved_budget, #committed_budget, #remaining_budget').val('');
        $('#remaining_budget, #liquidation_amount').removeClass('is-invalid');
        $('#budgetWarning').text('');
        $('#submitLiquidation').prop('disabled', true);
    }

    function validateLiquidationAmount(showAlert) {
        const amount = parseFloat($('#liquidation_amount').val());

        if (!budgetMonitoring || Number.isNaN(amount) || amount <= 0) {
            $('#liquidation_amount, #remaining_budget').removeClass('is-invalid');
            $('#budgetWarning').text('');
            $('#submitLiquidation').prop('disabled', !budgetMonitoring);
            return !!budgetMonitoring;
        }

        if (amount > Number(budgetMonitoring.remaining_budget)) {
            $('#liquidation_amount, #remaining_budget').addClass('is-invalid');
            $('#budgetWarning').text('Amount exceeds the remaining budget.');
            $('#submitLiquidation').prop('disabled', true);

            if (showAlert) {
                Swal.fire(
                    'Budget exceeded',
                    'Liquidation amount cannot exceed the remaining budget of ' + formatCurrency(budgetMonitoring.remaining_budget) + '.',
                    'error'
                );
            }
            return false;
        }

        $('#liquidation_amount, #remaining_budget').removeClass('is-invalid');
        $('#budgetWarning').text('');
        $('#submitLiquidation').prop('disabled', false);
        return true;
    }

    function loadBudgetMonitoring() {
        const budgetId = $('#budget_id').val();
        resetBudgetMonitoring();

        if (!budgetId) return;

        $('#budget_activity').val('Loading...');

        $.ajax({
            url: 'data/get_liquidation_budget_monitoring.php',
            type: 'GET',
            data: {
                budget_id: budgetId,
                editing_id: editRequestId
            },
            dataType: 'json',
            success: function(res) {
                if (res.status !== 'success' || !res.data) {
                    resetBudgetMonitoring();
                    Swal.fire('Error', res.message || 'Failed to load budget monitoring.', 'error');
                    return;
                }

                budgetMonitoring = res.data;
                const commitments = Number(res.data.pending_expenses || 0);

                $('#budget_activity').val(res.data.budget_activity || 'N/A');
                $('#approved_budget').val(formatCurrency(res.data.approved_budget));
                $('#committed_budget').val(formatCurrency(commitments));
                $('#remaining_budget').val(formatCurrency(res.data.remaining_budget));
                validateLiquidationAmount(false);
            },
            error: function() {
                resetBudgetMonitoring();
                Swal.fire('Error', 'Failed to load budget monitoring.', 'error');
            }
        });
    }

    function loadDepartmentBudgets() {
        const $budget = $('#budget_id');
        const $search = $('#budgetSearch');
        resetBudgetMonitoring();
        departmentBudgets = [];
        $search.val('').prop('disabled', true);
        $('#budgetSearchResult').text('Budget codes are loading.');
        $budget.html('<option value="" selected disabled>Loading budget codes...</option>');

        $.ajax({
            url: 'data/get_my_department_budgets.php',
            type: 'GET',
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success' && Array.isArray(res.data) && res.data.length > 0) {
                    departmentBudgets = res.data;
                    $search.prop('disabled', false);
                    renderBudgetOptions('');
                    if (editBudgetId) {
                        const selectedBudget = departmentBudgets.find(function(item) {
                            return String(item.budget_id) === String(editBudgetId);
                        });
                        if (selectedBudget) {
                            const selectedOwner = selectedBudget.access_source === 'granted' && selectedBudget.budget_department
                                ? ' - ' + selectedBudget.budget_department
                                : '';
                            const selectedText = selectedBudget.budget_code + selectedOwner + (selectedBudget.budget_activity ? ' - ' + selectedBudget.budget_activity : '');
                            $budget.val(String(editBudgetId));
                            $search.val(selectedText);
                            $('#budgetSearchResult').text('Selected budget code: ' + selectedBudget.budget_code);
                            loadBudgetMonitoring();
                        }
                    }
                } else {
                    $budget.html('<option value="" selected disabled>No budgets found for your department</option>');
                    $('#budgetSearchResult').text('No budget codes are available for your department.');
                }
            },
            error: function() {
                $budget.html('<option value="" selected disabled>Failed to load budget codes</option>');
                $('#budgetSearchResult').text('Budget codes could not be loaded.');
            }
        });
    }

    function renderBudgetOptions(searchTerm) {
        const $budget = $('#budget_id');
        const $dropdown = $('#budgetOptionsDropdown');
        const selectedId = String($budget.val() || '');
        const query = String(searchTerm || '').trim().toLowerCase();
        const matches = departmentBudgets.filter(function(item) {
            const searchableText = `${item.budget_code || ''} ${item.budget_department || ''} ${item.budget_activity || ''}`.toLowerCase();
            return !query || searchableText.includes(query);
        });
        const selectedIsVisible = matches.some(function(item) {
            return String(item.budget_id) === selectedId;
        });

        $budget.empty();
        $budget.append(new Option(matches.length ? 'Select Budget Code' : 'No matching budget codes', '', !selectedIsVisible, !selectedIsVisible));
        $budget.find('option:first').prop('disabled', true);

        matches.forEach(function(item) {
            const owner = item.access_source === 'granted' && item.budget_department
                ? ' - ' + item.budget_department
                : '';
            const text = item.budget_code + owner + (item.budget_activity ? ' - ' + item.budget_activity : '');
            $budget.append(new Option(text, item.budget_id, false, selectedIsVisible && String(item.budget_id) === selectedId));
        });

        $dropdown.empty();
        if (matches.length === 0) {
            $dropdown.append($('<div>', {
                class: 'budget-option-empty',
                text: 'No matching budget codes'
            }));
        } else {
            matches.forEach(function(item) {
                const $option = $('<button>', {
                    type: 'button',
                    class: 'budget-option',
                    'data-id': item.budget_id,
                    role: 'option'
                });
                $option.append($('<strong>', { text: item.budget_code || 'Budget Code' }));
                if (item.access_source === 'granted' && item.budget_department) {
                    $option.append($('<span>', { class: 'budget-option-department', text: item.budget_department }));
                }
                $option.append($('<small>', { text: item.budget_activity || 'No activity description' }));
                $dropdown.append($option);
            });
        }

        if (selectedId && !selectedIsVisible) {
            resetBudgetMonitoring();
        }

        $('#budgetSearchResult').text(matches.length === 1 ? '1 budget code found.' : `${matches.length} budget codes found.`);
    }

    $('#liquidationForm').on('submit', function(e) {
        e.preventDefault();

        if (!validateLiquidationAmount(true)) {
            return;
        }

        const requestType = $('input[name="request_type"]:checked').val();
        const amount = Number($('#liquidation_amount').val() || 0);

        if (requestType === 'Cash Advance' && amount > 10000) {
            promptCashAdvanceToRfp();
            return;
        }

        const formData = new FormData(this);
        const file = $('#attachment')[0].files[0];

        if (!$('#activity_date').val()) {
            Swal.fire('Activity date required', 'Please select the activity date.', 'error');
            return;
        }

        if (requestType === 'Request for Payment' && !$('#rfp_department_head_name').val().trim()) {
            Swal.fire('Payee required', 'Please enter the person or entity payable for this request.', 'error');
            return;
        }

        if ((requestType === 'Liquidation' || (requestType === 'Request for Payment' && $('input[name="requires_liquidation"]:checked').val() === '0')) && !file && !hasExistingAttachment) {
            Swal.fire('Attachment required', 'Please attach a supporting file for the Liquidation.', 'error');
            return;
        }

        if (file && file.size > 5 * 1024 * 1024) {
            Swal.fire('Error', 'Attachment must be below 5MB.', 'error');
            return;
        }

        Swal.fire({
            title: 'Submitting...',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        $.ajax({
            url: isEditMode ? 'data/update_liquidation.php' : 'data/create_liquidation.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                Swal.close();
                if (res.status === 'success') {
                    Swal.fire('Success', res.message, 'success').then(() => {
                        window.location.href = 'my_liquidations';
                    });
                } else {
                    if (res.code === 'CA_AMOUNT_REQUIRES_RFP') {
                        promptCashAdvanceToRfp();
                    } else {
                        Swal.fire('Error', res.message || 'Failed to submit liquidation.', 'error');
                    }
                }
            },
            error: function() {
                Swal.close();
                Swal.fire('Error', 'Failed to submit liquidation.', 'error');
            }
        });
    });

    $('#budget_id').on('change', loadBudgetMonitoring);
    $('#budgetSearch').on('input', function() {
        const selectedId = String($('#budget_id').val() || '');
        const selectedBudget = departmentBudgets.find(function(item) {
            return String(item.budget_id) === selectedId;
        });
        const selectedOwner = selectedBudget && selectedBudget.access_source === 'granted' && selectedBudget.budget_department
            ? ' - ' + selectedBudget.budget_department
            : '';
        const selectedText = selectedBudget ? selectedBudget.budget_code + selectedOwner + (selectedBudget.budget_activity ? ' - ' + selectedBudget.budget_activity : '') : '';

        if (selectedId && this.value !== selectedText) {
            $('#budget_id').val('');
            resetBudgetMonitoring();
        }

        renderBudgetOptions(this.value);
        $('#budgetOptionsDropdown').removeClass('d-none');
        $(this).attr('aria-expanded', 'true');
    }).on('focus', function() {
        this.select();
        renderBudgetOptions(this.value);
        $('#budgetOptionsDropdown').removeClass('d-none');
        $(this).attr('aria-expanded', 'true');
    });

    $(document).on('click', '.budget-option', function() {
        const budgetId = String($(this).data('id'));
        const selectedBudget = departmentBudgets.find(function(item) {
            return String(item.budget_id) === budgetId;
        });
        if (!selectedBudget) return;

        const selectedOwner = selectedBudget.access_source === 'granted' && selectedBudget.budget_department
            ? ' - ' + selectedBudget.budget_department
            : '';
        const selectedText = selectedBudget.budget_code + selectedOwner + (selectedBudget.budget_activity ? ' - ' + selectedBudget.budget_activity : '');
        $('#budgetSearch').val(selectedText).attr('aria-expanded', 'false');
        $('#budgetOptionsDropdown').addClass('d-none');
        $('#budgetSearchResult').text('Selected budget code: ' + selectedBudget.budget_code);
        $('#budget_id').val(budgetId).trigger('change');
    });

    $(document).on('click', function(event) {
        if (!$(event.target).closest('#budgetCombobox').length) {
            $('#budgetOptionsDropdown').addClass('d-none');
            $('#budgetSearch').attr('aria-expanded', 'false');
        }
    });

    $('#budgetSearch').on('keydown', function(event) {
        if (event.key === 'Escape') {
            $('#budgetOptionsDropdown').addClass('d-none');
            $(this).attr('aria-expanded', 'false').blur();
        }
    });
    $('input[name="request_type"]').on('change', function() {
        updateRequestTypeRules();
        scheduleCashAdvanceLimitPrompt();
    });
    $('input[name="requires_liquidation"]').on('change', updateRequestTypeRules);
    $('#liquidation_amount').on('input', function() {
        validateLiquidationAmount(false);
        scheduleCashAdvanceLimitPrompt();
    });

    $(document).ready(function() {
        updateRequestTypeRules();
        loadDepartmentBudgets();
    });
</script>

<?php include 'footer.php'; ?>
