<?php
require '../init.php';

include 'header.php';
include 'navigation.php';

$sessionDepartment = trim($_SESSION['department'] ?? '');
$canViewAllDepartments = in_array(strtoupper($sessionDepartment), ['PURCHASING', 'EO'], true);

if ($canViewAllDepartments) {
    $departmentStmt = $pdo->prepare("
        SELECT DISTINCT budget_department
        FROM budgets
        WHERE budget_department IS NOT NULL
          AND budget_department <> ''
        ORDER BY budget_department
    ");
    $departmentStmt->execute();
    $departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN);
} else {
    $departments = $sessionDepartment === '' ? [] : [$sessionDepartment];
}
?>

<style>
    body {
        background: #f4f6f9;
        font-family: 'Inter', 'Segoe UI', sans-serif;
        color: #212529;
    }

    .dashboard-header {
        background: #dc3545;
        color: #fff;
        padding: 18px 22px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(220, 53, 69, 0.14);
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
        color: #dc3545;
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
        border-left: 4px solid #dc3545;
        color: #495057;
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 14px;
        padding-left: 10px;
    }

    .overrun-amount {
        color: #dc3545;
        font-weight: 700;
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
                <h3>Over-Exceeded Budget Codes</h3>
                <small class="text-white-50">Budget codes with negative remaining budget</small>
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
                    <select id="departmentSelect" class="form-select form-select-sm" <?= $canViewAllDepartments ? '' : 'disabled' ?>>
                        <?php if ($canViewAllDepartments): ?>
                            <option value="">All Departments</option>
                        <?php endif; ?>
                        <?php foreach ($departments as $department): ?>
                            <option value="<?= htmlspecialchars($department) ?>" <?= !$canViewAllDepartments && $department === $sessionDepartment ? 'selected' : '' ?>>
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
            <div class="col-md-4">
                <div class="metric-card p-3 text-center">
                    <h6>Over-Exceeded Codes</h6>
                    <h2 id="totalCodes">0</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card p-3 text-center">
                    <h6>Total Over-Exceeded Amount</h6>
                    <h2 id="totalOverrun">PHP 0.00</h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="metric-card p-3 text-center">
                    <h6>Total Committed</h6>
                    <h2 id="totalCommitted">PHP 0.00</h2>
                </div>
            </div>
        </div>

        <div class="table-card p-3">
            <h6 class="section-title">Over-Exceeded Budget Code List</h6>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle" id="overExceededTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Dealer</th>
                            <th>Department</th>
                            <th>Budget Code</th>
                            <th>Activity</th>
                            <th>Approved Budget</th>
                            <th>Actual Expenses</th>
                            <th>Liquidation</th>
                            <th>RFP</th>
                            <th>Cash Advances</th>
                            <th>Committed Amount</th>
                            <th>Remaining Budget</th>
                            <th>Over-Exceeded Amount</th>
                            <th>Liquidation Utilization</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="overExceededTableBody"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="budgetTransactionsModal" tabindex="-1" aria-labelledby="budgetTransactionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="budgetTransactionsModalLabel">Budget Code Transactions</h5>
                    <small id="budgetTransactionsSubtitle" class="text-muted"></small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle" id="budgetTransactionsTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Reference</th>
                                <th>Related No.</th>
                                <th>Date</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody id="budgetTransactionsTableBody"></tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const canViewAllDepartments = <?= $canViewAllDepartments ? 'true' : 'false' ?>;
        const sessionDepartment = <?= json_encode($sessionDepartment) ?>;
        let overExceededTable = null;
        let budgetTransactionsTable = null;

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

        function escapeAttr(value) {
            return escapeHtml(value).replace(/"/g, '&quot;');
        }

        function filters() {
            return {
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                department: canViewAllDepartments ? $('#departmentSelect').val() : sessionDepartment
            };
        }

        function setDashboardState(message = '', type = 'info') {
            const box = $('#dashboardState');
            box.text(message);
            box.attr('class', message ? `dashboard-state ${type}` : 'dashboard-state');
        }

        function loadOverExceeded() {
            setDashboardState('Loading over-exceeded budget codes...', 'info');

            $.ajax({
                url: 'data/get_over_exceeded_budget_codes.php',
                type: 'GET',
                data: filters(),
                dataType: 'json',
                success: function(response) {
                    renderDashboard(response.data || []);
                },
                error: function() {
                    setDashboardState('Unable to load over-exceeded budget codes.', 'error');
                }
            });
        }

        function renderDashboard(rows) {
            setDashboardState(rows.length ? '' : 'No over-exceeded budget codes found for the selected filters.', 'info');

            const totals = rows.reduce((acc, row) => {
                acc.overrun += Number(row.OverExceededAmount || 0);
                acc.committed += Number(row.CommittedAmount || 0);
                return acc;
            }, { overrun: 0, committed: 0 });

            $('#totalCodes').text(rows.length.toLocaleString('en-PH'));
            $('#totalOverrun').text(formatPeso(totals.overrun));
            $('#totalCommitted').text(formatPeso(totals.committed));
            renderTable(rows);
        }

        function renderTable(rows) {
            if ($.fn.DataTable.isDataTable('#overExceededTable')) {
                $('#overExceededTable').DataTable().clear().destroy();
            }

            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.BudgetDealer || '-')}</td>
                    <td>${escapeHtml(row.BudgetDepartment || '-')}</td>
                    <td>${escapeHtml(row.BudgetCode || '-')}</td>
                    <td>${escapeHtml(row.BudgetActivity || '-')}</td>
                    <td>${formatPeso(row.ApprovedBudget)}</td>
                    <td>${formatPeso(row.ActualExpense)}</td>
                    <td>${formatPeso(row.LiquidationExpense)}</td>
                    <td>${formatPeso(row.RFP)}</td>
                    <td>${formatPeso(row.CashAdvance)}</td>
                    <td>${formatPeso(row.CommittedAmount)}</td>
                    <td class="overrun-amount">${formatPeso(row.RemainingBudget)}</td>
                    <td class="overrun-amount">${formatPeso(row.OverExceededAmount)}</td>
                    <td>${formatPercent(row.BudgetUtilization)}</td>
                    <td class="text-center">
                        <button type="button"
                            class="btn btn-outline-primary btn-sm view-transactions-btn"
                            data-bs-toggle="modal"
                            data-bs-target="#budgetTransactionsModal"
                            data-budget-id="${escapeAttr(row.budget_id || row.BUDGET_ID || row.BudgetId || '')}"
                            data-budget-code="${escapeAttr(row.BudgetCode || '')}"
                            data-budget-activity="${escapeAttr(row.BudgetActivity || '')}"
                            data-budget-department="${escapeAttr(row.BudgetDepartment || '')}"
                            data-budget-dealer="${escapeAttr(row.BudgetDealer || '')}">
                            <i class="ti ti-eye"></i>
                        </button>
                    </td>
                </tr>
            `).join('');

            $('#overExceededTableBody').html(html);
            overExceededTable = $('#overExceededTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[11, 'desc']]
            });
        }

        function resetTransactionsTable(message, type = 'muted') {
            if ($.fn.DataTable.isDataTable('#budgetTransactionsTable')) {
                $('#budgetTransactionsTable').DataTable().clear().destroy();
                budgetTransactionsTable = null;
            }

            $('#budgetTransactionsTableBody').html(
                `<tr><td colspan="7" class="text-center text-${type}">${escapeHtml(message)}</td></tr>`
            );
        }

        function renderTransactionsTable(rows) {
            if ($.fn.DataTable.isDataTable('#budgetTransactionsTable')) {
                $('#budgetTransactionsTable').DataTable().clear().destroy();
            }

            if (!rows.length) {
                resetTransactionsTable('No transactions found for this budget code.');
                return;
            }

            const html = rows.map(row => {
                const reference = row.detail_url && row.reference_number
                    ? `<a href="${escapeAttr(row.detail_url)}" target="_blank">${escapeHtml(row.reference_number)}</a>`
                    : escapeHtml(row.reference_number || '-');

                return `
                    <tr>
                        <td>${escapeHtml(row.transaction_type || '-')}</td>
                        <td>${reference}</td>
                        <td>${escapeHtml(row.related_number || '-')}</td>
                        <td>${escapeHtml(row.transaction_date || '-')}</td>
                        <td>${formatPeso(row.amount)}</td>
                        <td>${escapeHtml(row.status || '-')}</td>
                        <td>${escapeHtml(row.remarks || '')}</td>
                    </tr>
                `;
            }).join('');

            $('#budgetTransactionsTableBody').html(html);
            budgetTransactionsTable = $('#budgetTransactionsTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[3, 'desc']]
            });
        }

        function loadBudgetTransactions(button) {
            const budgetId = button.attr('data-budget-id') || '';
            const budgetCode = button.attr('data-budget-code') || '-';
            const budgetActivity = button.attr('data-budget-activity') || '';
            const budgetDepartment = button.attr('data-budget-department') || '';
            const budgetDealer = button.attr('data-budget-dealer') || '';

            $('#budgetTransactionsModalLabel').text(`Transactions: ${budgetCode}`);
            $('#budgetTransactionsSubtitle').text(budgetActivity);
            resetTransactionsTable('Loading transactions...');

            $.ajax({
                url: 'data/get_budget_code_transactions.php',
                type: 'GET',
                data: {
                    budget_id: budgetId,
                    budget_code: budgetCode,
                    department: budgetDepartment,
                    dealer: budgetDealer,
                    start: $('#startDate').val(),
                    end: $('#endDate').val()
                },
                dataType: 'json',
                success: function(response) {
                    if (!response.success) {
                        resetTransactionsTable(response.message || 'Unable to load transactions.', 'danger');
                        return;
                    }

                    renderTransactionsTable(response.data || []);
                },
                error: function() {
                    resetTransactionsTable('Unable to load transactions.', 'danger');
                }
            });
        }

        $('#startDate, #endDate, #departmentSelect').on('change', loadOverExceeded);
        $('#clearFiltersBtn').on('click', function() {
            $('#startDate').val('');
            $('#endDate').val('');
            if (canViewAllDepartments) {
                $('#departmentSelect').val('');
            }
            loadOverExceeded();
        });
        $(document).on('click', '.view-transactions-btn', function() {
            loadBudgetTransactions($(this));
        });

        loadOverExceeded();
    });
</script>

<?php
include 'footer.php';
?>
