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
    .panel-card,
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

    .chart-body {
        height: 340px;
        min-height: 340px;
        position: relative;
    }

    .chart-note {
        color: #6c757d;
        font-size: 0.78rem;
        margin-top: 8px;
        min-height: 18px;
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

<script src="https://unpkg.com/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://unpkg.com/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>

<div class="pc-container">
    <div class="pc-content">
        <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3>Budget Codes</h3>
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
                <div>
                    <label for="chartSortSelect">Charts</label>
                    <select id="chartSortSelect" class="form-select form-select-sm">
                        <option value="highest">Highest Utilization</option>
                        <option value="lowest">Lowest Utilization</option>
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

        <div class="row g-4 mb-4">
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Budget Utilization by Code</h6>
                    <div class="chart-body">
                        <canvas id="budgetBarChart"></canvas>
                    </div>
                    <div id="budgetBarChartNote" class="chart-note"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Approved vs Pending Transactions</h6>
                    <div class="chart-body">
                        <canvas id="budgetLineChart"></canvas>
                    </div>
                    <div id="budgetLineChartNote" class="chart-note"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Remaining Budget by Code</h6>
                    <div class="chart-body">
                        <canvas id="remainingBarChart"></canvas>
                    </div>
                    <div id="remainingBarChartNote" class="chart-note"></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Budget Distribution by Code</h6>
                    <div class="chart-body">
                        <canvas id="budgetDistributionChart"></canvas>
                    </div>
                    <div id="budgetDistributionChartNote" class="chart-note"></div>
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
        let charts = {
            utilization: null,
            approvedVsPending: null,
            remaining: null,
            distribution: null
        };
        let budgetTable = null;
        let budgetTransactionsTable = null;
        const colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#6c757d'];
        const maxChartItems = 20;
        const maxLabelsWithDataValues = 12;

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

        function setDashboardState(message = '', type = 'info') {
            const box = $('#dashboardState');
            box.text(message);
            box.attr('class', message ? `dashboard-state ${type}` : 'dashboard-state');
        }

        function filters() {
            return {
                start: $('#startDate').val(),
                end: $('#endDate').val(),
                department: canViewAllDepartments ? $('#departmentSelect').val() : sessionDepartment
            };
        }

        function chartSortMode() {
            return $('#chartSortSelect').val() === 'lowest' ? 'lowest' : 'highest';
        }

        function chartPlugins() {
            return typeof ChartDataLabels === 'undefined' ? [] : [ChartDataLabels];
        }

        function destroyCharts() {
            Object.keys(charts).forEach(key => {
                if (charts[key]) {
                    charts[key].destroy();
                    charts[key] = null;
                }
            });
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

            renderCharts(rows);
            renderTable(rows);
        }

        function chartLabels(rows) {
            return rows.map(row => {
                const code = row.BudgetCode || 'Unknown';
                return canViewAllDepartments ? `${row.BudgetDepartment || '-'} - ${code}` : code;
            });
        }

        function rankedRows(rows, metric, limit = maxChartItems, direction = 'desc') {
            return [...rows]
                .sort((a, b) => {
                    const first = Number(a[metric] || 0);
                    const second = Number(b[metric] || 0);
                    return direction === 'asc' ? first - second : second - first;
                })
                .slice(0, limit);
        }

        function chartRows(rows) {
            const direction = chartSortMode() === 'lowest' ? 'asc' : 'desc';
            return rankedRows(rows, 'BudgetUtilization', maxChartItems, direction);
        }

        function setChartNote(selector, rows) {
            const hiddenCount = Math.max(rows.length - maxChartItems, 0);
            const modeLabel = chartSortMode() === 'lowest' ? 'lowest utilization' : 'highest utilization';
            const message = hiddenCount
                ? `Showing ${maxChartItems} of ${rows.length} budget codes by ${modeLabel}.`
                : '';

            $(selector).text(message);
        }

        function moneyAxisTick(value) {
            const amount = Number(value || 0);
            if (amount >= 1000000) {
                return 'PHP ' + (amount / 1000000).toLocaleString('en-PH', { maximumFractionDigits: 1 }) + 'M';
            }
            if (amount >= 1000) {
                return 'PHP ' + (amount / 1000).toLocaleString('en-PH', { maximumFractionDigits: 0 }) + 'K';
            }
            return 'PHP ' + amount.toLocaleString('en-PH');
        }

        function renderCharts(rows) {
            destroyCharts();

            const visibleRows = chartRows(rows);

            setChartNote('#budgetBarChartNote', rows);
            setChartNote('#budgetLineChartNote', rows);
            setChartNote('#remainingBarChartNote', rows);
            setChartNote('#budgetDistributionChartNote', rows);

            const labels = chartLabels(visibleRows);
            const utilization = visibleRows.map(row => Number(row.BudgetUtilization || 0));
            const approved = visibleRows.map(row => Number(row.ApprovedBudget || 0));
            const pending = visibleRows.map(row => Number(row.PendingTransactions || 0));
            const remaining = visibleRows.map(row => Number(row.RemainingBudget || 0));
            const rowColors = labels.map((_, index) => colors[index % colors.length]);

            charts.utilization = new Chart(document.getElementById('budgetBarChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Utilization',
                        data: utilization,
                        backgroundColor: rowColors,
                        borderRadius: 6
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            display: visibleRows.length <= maxLabelsWithDataValues,
                            color: '#fff',
                            formatter: value => formatPercent(value),
                            font: { size: 11 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            suggestedMax: 100,
                            ticks: {
                                callback: value => value + '%'
                            }
                        },
                        x: {
                            ticks: {
                                autoSkip: true,
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    }
                },
                plugins: chartPlugins()
            });

            charts.approvedVsPending = new Chart(document.getElementById('budgetLineChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                            label: 'Approved Budget',
                            data: approved,
                            backgroundColor: '#0d6efd'
                        },
                        {
                            label: 'Pending Transactions',
                            data: pending,
                            backgroundColor: '#dc3545'
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: { display: false },
                        tooltip: {
                            callbacks: {
                                label: context => `${context.dataset.label}: ${formatPeso(context.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => moneyAxisTick(value)
                            }
                        },
                        x: {
                            ticks: {
                                autoSkip: true,
                                maxRotation: 45,
                                minRotation: 0
                            }
                        }
                    }
                },
                plugins: chartPlugins()
            });

            charts.remaining = new Chart(document.getElementById('remainingBarChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        data: remaining,
                        backgroundColor: rowColors,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            display: visibleRows.length <= maxLabelsWithDataValues,
                            color: '#212529',
                            anchor: 'end',
                            align: 'right',
                            formatter: value => formatPeso(value),
                            font: { size: 11 }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => moneyAxisTick(value)
                            }
                        },
                        y: {
                            ticks: {
                                autoSkip: false
                            }
                        }
                    }
                },
                plugins: chartPlugins()
            });

            charts.distribution = new Chart(document.getElementById('budgetDistributionChart'), {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data: approved,
                        backgroundColor: rowColors
                    }]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                boxWidth: 12
                            }
                        },
                        datalabels: {
                            display: visibleRows.length <= maxLabelsWithDataValues,
                            color: '#fff',
                            formatter: value => formatPeso(value),
                            font: { size: 10 }
                        }
                    }
                },
                plugins: chartPlugins()
            });
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

            $('#budgetTableBody').html(html);
            budgetTable = $('#budgetTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[0, 'asc'], [1, 'asc']]
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

        $('#startDate, #endDate, #departmentSelect').on('change', loadDashboard);
        $('#chartSortSelect').on('change', loadDashboard);
        $('#clearFiltersBtn').on('click', function() {
            $('#startDate').val('');
            $('#endDate').val('');
            $('#chartSortSelect').val('highest');
            if (canViewAllDepartments) {
                $('#departmentSelect').val('');
            }
            loadDashboard();
        });
        $(document).on('click', '.view-transactions-btn', function() {
            loadBudgetTransactions($(this));
        });

        loadDashboard();
    });
</script>

<?php
include 'footer.php';
?>
