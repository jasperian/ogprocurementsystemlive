<?php
require '../init.php';

include 'header.php';
include 'navigation.php';

$sessionDepartment = trim($_SESSION['department'] ?? '');
$sessionDealer = trim($_SESSION['dealer'] ?? '');
$canViewAllDepartments = true;

$departmentSql = "
    SELECT DISTINCT budget_department
    FROM budgets
    WHERE budget_department IS NOT NULL
      AND budget_department <> ''
";
$departmentParams = [];

if ($sessionDealer !== '') {
    $departmentSql .= " AND UPPER(TRIM(budget_dealer)) = UPPER(TRIM(:session_dealer))";
    $departmentParams[':session_dealer'] = $sessionDealer;
}

$departmentSql .= " ORDER BY budget_department";
$departmentStmt = $pdo->prepare($departmentSql);
$departmentStmt->execute($departmentParams);
$departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    body {
        background: #f4f6f9;
        font-family: 'Inter', 'Segoe UI', sans-serif;
        color: #212529;
    }

    .dashboard-header {
        background: #0d6efd;
        color: #fff;
        padding: 18px 22px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(13, 110, 253, 0.12);
        display: flex;
        align-items: end;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .dashboard-title h3 {
        color: #fff;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .filter-inline {
        display: flex;
        align-items: end;
        gap: 10px;
        flex-wrap: wrap;
    }

    .filter-inline label {
        color: rgba(255, 255, 255, 0.95);
        font-size: 0.8rem;
        margin-bottom: 2px;
    }

    .filter-inline input,
    .filter-inline select {
        min-width: 170px;
    }

    .metric-card,
    .table-card {
        background: #fff;
        border: none;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.05);
    }

    .metric-card h6 {
        color: #6c757d;
        font-size: 0.85rem;
        font-weight: 600;
        margin-bottom: 6px;
    }

    .metric-card h2 {
        color: #0d6efd;
        font-size: 1.35rem;
        font-weight: 800;
        margin: 0;
    }

    .dashboard-state {
        display: none;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 16px;
    }

    .dashboard-state.info {
        display: block;
        background: #e7f1ff;
        color: #084298;
    }

    .dashboard-state.error {
        display: block;
        background: #f8d7da;
        color: #842029;
    }

    .section-title {
        border-left: 4px solid #0d6efd;
        color: #495057;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 14px;
        padding-left: 10px;
    }

    .swal2-container {
        z-index: 20000 !important;
    }

    @media (max-width: 768px) {
        table.dataTable td {
            white-space: normal !important;
        }

        .dashboard-header {
            align-items: stretch;
        }

        .filter-inline,
        .filter-inline > div,
        .filter-inline input,
        .filter-inline select,
        .filter-inline .btn {
            width: 100%;
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3>Budget Utilization by Code</h3>
                <small class="text-white-50">Updated <?= date('F j, Y') ?></small>
            </div>

            <div class="filter-inline">
                <div>
                    <label for="startDate">From</label>
                    <input id="startDate" type="date" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="endDate">To</label>
                    <input id="endDate" type="date" class="form-control form-control-sm">
                </div>
                <div>
                    <label for="departmentSelect">Department</label>
                    <select id="departmentSelect" class="form-select form-select-sm">
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars($department) ?>" <?= $department === $sessionDepartment ? 'selected' : '' ?>>
                                <?= htmlspecialchars($department) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button id="clearFiltersBtn" type="button" class="btn btn-outline-light btn-sm">
                    <i class="ti ti-filter-off"></i> Clear
                </button>
            </div>
        </div>

        <div id="dashboardState" class="dashboard-state"></div>

        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>Approved Budget</h6>
                    <h2 id="totalApproved">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>Actual Expenses</h6>
                    <h2 id="totalActual">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>Liquidation Expenses</h6>
                    <h2 id="totalLiquidation">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>RFP</h6>
                    <h2 id="totalRFP">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>Cash Advances</h6>
                    <h2 id="totalCashAdvance">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-6 col-lg-4 col-xl">
                <div class="metric-card p-3 text-center">
                    <h6>Remaining Budget</h6>
                    <h2 id="totalRemaining">PHP 0.00</h2>
                </div>
            </div>
        </div>

        <div class="table-card p-3">
            <h6 class="section-title">Budget Code Summary</h6>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle" id="budgetTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Budget Code</th>
                            <th>Activity</th>
                            <th>Approved Budget</th>
                            <th>Actual Expenses</th>
                            <th>Liquidation Expenses</th>
                            <th>RFP</th>
                            <th>Cash Advances</th>
                            <th>Pending Transactions</th>
                            <th>Remaining Budget</th>
                            <th>Utilization</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="budgetTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addBudgetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Budget: <span id="addBudgetCodeLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addBudgetForm" enctype="multipart/form-data">
                    <input type="hidden" name="budget_id" id="addBudgetId">

                    <div class="mb-3">
                        <label for="addBudgetAmount" class="form-label">Additional Amount</label>
                        <input id="addBudgetAmount" name="amount" type="number" step="0.01" min="0.01" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label for="addBudgetFile" class="form-label">Attachment</label>
                        <input id="addBudgetFile" name="file" type="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png" required>
                    </div>

                    <div class="mb-3">
                        <label for="addBudgetRemarks" class="form-label">Remarks</label>
                        <textarea id="addBudgetRemarks" name="remarks" class="form-control" rows="3" required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="saveAddBudgetBtn" class="btn btn-success">
                    <i class="ti ti-device-floppy"></i> Save
                </button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="budgetHistoryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Budget History: <span id="historyBudgetCodeLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="historyState" class="alert alert-info py-2" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle" id="budgetHistoryTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Amount</th>
                                <th>Remarks</th>
                                <th>Date</th>
                                <th>Created By</th>
                                <th>File</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const canViewAllDepartments = <?= $canViewAllDepartments ? 'true' : 'false' ?>;
        const sessionDepartment = <?= json_encode($sessionDepartment) ?>;
        let budgetTable = null;

        function formatPeso(value) {
            const amount = Number(value || 0);
            return 'PHP ' + amount.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatPercent(value) {
            return Number(value || 0).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + '%';
        }

        function escapeHtml(value) {
            return $('<div>').text(value ?? '').html();
        }

        function setDashboardState(message = '', type = 'info') {
            const box = $('#dashboardState');
            box.text(message);
            box.attr('class', message ? `dashboard-state ${type}` : 'dashboard-state');
        }

        function filters() {
            return {
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                department: $('#departmentSelect').val() || sessionDepartment,
                allow_department_switch: '1'
            };
        }

        function loadDashboard() {
            setDashboardState('Loading budget codes...', 'info');

            $.ajax({
                url: 'data/get_budget_code_dashboard.php',
                type: 'GET',
                data: filters(),
                dataType: 'json',
                success: function(response) {
                    renderDashboard(response.data || []);
                },
                error: function() {
                    setDashboardState('Unable to load budget code data.', 'error');
                }
            });
        }

        function renderDashboard(rows) {
            setDashboardState(rows.length ? '' : 'No budget codes found for the selected filters.', 'info');

            const totals = rows.reduce((acc, row) => {
                acc.approved += Number(row.ApprovedBudget || 0);
                acc.actual += Number(row.ActualExpense || 0);
                acc.liquidation += Number(row.LiquidationExpense || 0);
                acc.rfp += Number(row.RFP || 0);
                acc.cashAdvance += Number(row.CashAdvance || 0);
                acc.pending += Number(row.PendingTransactions || 0);
                acc.remaining += Number(row.RemainingBudget || 0);
                return acc;
            }, {
                approved: 0,
                actual: 0,
                liquidation: 0,
                rfp: 0,
                cashAdvance: 0,
                pending: 0,
                remaining: 0
            });

            $('#totalApproved').text(formatPeso(totals.approved));
            $('#totalActual').text(formatPeso(totals.actual));
            $('#totalLiquidation').text(formatPeso(totals.liquidation));
            $('#totalRFP').text(formatPeso(totals.rfp));
            $('#totalCashAdvance').text(formatPeso(totals.cashAdvance));
            $('#totalRemaining').text(formatPeso(totals.remaining));

            renderTable(rows);
        }

        function renderTable(rows) {
            if ($.fn.DataTable.isDataTable('#budgetTable')) {
                $('#budgetTable').DataTable().clear().destroy();
            }

            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.BudgetDepartment || '-')}</td>
                    <td>${escapeHtml(row.BudgetCode || '-')}</td>
                    <td>${escapeHtml(row.BudgetActivity || '-')}</td>
                    <td>${formatPeso(row.ApprovedBudget)}</td>
                    <td>${formatPeso(row.ActualExpense)}</td>
                    <td>${formatPeso(row.LiquidationExpense)}</td>
                    <td>${formatPeso(row.RFP)}</td>
                    <td>${formatPeso(row.CashAdvance)}</td>
                    <td>${formatPeso(row.PendingTransactions)}</td>
                    <td>${formatPeso(row.RemainingBudget)}</td>
                    <td>${formatPercent(row.BudgetUtilization)}</td>
                    <td>
                        <button type="button"
                            class="btn btn-sm btn-outline-success add-budget-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#addBudgetModal"
                            data-budget-id="${Number(row.budget_id || 0)}"
                            data-budget-code="${escapeHtml(row.BudgetCode || '')}">
                            <i class="ti ti-plus"></i> Add Budget
                        </button>
                        <button type="button"
                            class="btn btn-sm btn-outline-info view-history-btn"
                            data-budget-id="${Number(row.budget_id || 0)}"
                            data-budget-code="${escapeHtml(row.BudgetCode || '')}">
                            <i class="ti ti-history"></i> History
                        </button>
                    </td>
                </tr>
            `).join('');

            $('#budgetTableBody').html(html);
            budgetTable = $('#budgetTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[0, 'asc'], [1, 'asc']]
            });
        }

        function resetAddBudgetForm() {
            $('#addBudgetForm')[0].reset();
            $('#addBudgetId').val('');
            $('#addBudgetCodeLabel').text('');
        }

        $('#startDate, #endDate, #departmentSelect').on('change', loadDashboard);
        $('#clearFiltersBtn').on('click', function() {
            $('#startDate').val('');
            $('#endDate').val('');
            $('#departmentSelect').val(sessionDepartment);
            loadDashboard();
        });

        $(document).on('click', '.add-budget-btn', function() {
            resetAddBudgetForm();
            $('#addBudgetId').val($(this).data('budget-id'));
            $('#addBudgetCodeLabel').text($(this).data('budget-code') || '-');
        });

        $('#saveAddBudgetBtn').on('click', function() {
            const amount = Number($('#addBudgetAmount').val() || 0);
            const fileInput = $('#addBudgetFile')[0];
            const remarks = $('#addBudgetRemarks').val().trim();
            const budgetCode = $('#addBudgetCodeLabel').text();

            if (!$('#addBudgetId').val() || amount <= 0 || !remarks || !fileInput.files.length) {
                Swal.fire('Missing Details', 'Please enter an amount, remarks, and upload an attachment.', 'warning');
                return;
            }

            Swal.fire({
                title: 'Add budget?',
                text: `Add ${formatPeso(amount)} to ${budgetCode}?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add it'
            }).then(result => {
                if (!result.isConfirmed) return;

                const formData = new FormData(document.getElementById('addBudgetForm'));
                $('#saveAddBudgetBtn').prop('disabled', true);

                $.ajax({
                    url: 'budget/add_budget.php',
                    type: 'POST',
                    data: formData,
                    dataType: 'json',
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#addBudgetModal').modal('hide');
                            resetAddBudgetForm();
                            loadDashboard();
                            Swal.fire('Saved', 'Additional budget was added.', 'success');
                        } else {
                            Swal.fire('Unable to Save', response.message || 'Something went wrong.', 'error');
                        }
                    },
                    error: function(xhr) {
                        Swal.fire('Server Error', xhr.responseText || 'Unable to add budget.', 'error');
                    },
                    complete: function() {
                        $('#saveAddBudgetBtn').prop('disabled', false);
                    }
                });
            });
        });

        $(document).on('click', '.view-history-btn', function() {
            const budgetId = $(this).data('budget-id');
            const budgetCode = $(this).data('budget-code') || '-';

            $('#historyBudgetCodeLabel').text(budgetCode);
            $('#historyState').removeClass('alert-danger').addClass('alert-info').show().text('Loading budget history...');
            $('#budgetHistoryTable tbody').html('');
            $('#budgetHistoryModal').modal('show');

            $.ajax({
                url: 'budget/get_budget_history.php',
                type: 'GET',
                data: {
                    budget_id: budgetId
                },
                dataType: 'json',
                success: function(response) {
                    const rows = response.data || [];
                    $('#historyState')
                        .toggle(!rows.length)
                        .text(rows.length ? '' : 'No additional budget history found.');

                    const html = rows.map((row, index) => {
                        const fileLink = row.file_path
                            ? `<a href="${escapeHtml(row.file_path)}" target="_blank" class="btn btn-sm btn-outline-primary">
                                <i class="ti ti-eye"></i> View
                               </a>`
                            : '-';

                        return `
                            <tr>
                                <td>${index + 1}</td>
                                <td>${formatPeso(row.amount)}</td>
                                <td>${escapeHtml(row.remarks || '')}</td>
                                <td>${escapeHtml(row.created_at || '')}</td>
                                <td>${escapeHtml(row.created_by || '-')}</td>
                                <td>${fileLink}</td>
                            </tr>
                        `;
                    }).join('');

                    $('#budgetHistoryTable tbody').html(html);
                },
                error: function() {
                    $('#historyState').removeClass('alert-info').addClass('alert-danger').show().text('Unable to load budget history.');
                }
            });
        });

        loadDashboard();
    });
</script>

<?php
include 'footer.php';
?>
