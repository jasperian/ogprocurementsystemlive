<?php
require '../init.php';

include 'header.php';
include 'navigation.php';

$dealerStmt = $pdo->prepare("SELECT DISTINCT budget_dealer FROM budgets WHERE budget_dealer IS NOT NULL AND budget_dealer <> '' AND budget_dealer <> 'OG' AND budget_dealer <> 'ADMIN' ORDER BY budget_dealer");
$dealerStmt->execute();
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
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
                <h3>Budget Utilization by Dealer</h3>
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
                    <label for="dealerSelect">Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealers</option>
                        <?php foreach ($dealers as $dealer): ?>
                            <option value="<?= htmlspecialchars($dealer) ?>"><?= htmlspecialchars($dealer) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button id="clearFiltersBtn" type="button" class="btn btn-outline-light btn-sm">
                    <i class="ti ti-filter-off"></i> Clear
                </button>

                <button id="exportExcelBtn" type="button" class="btn btn-success btn-sm">
                    <i class="ti ti-file-spreadsheet"></i> Export Excel
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
                    <h6 class="section-title">Budget Utilization by Dealer</h6>
                    <div class="chart-body">
                        <canvas id="budgetBarChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Approved vs Used + Pending Transactions</h6>
                    <div class="chart-body">
                        <canvas id="budgetLineChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Remaining Budget by Dealer</h6>
                    <div class="chart-body">
                        <canvas id="remainingBarChart"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="panel-card p-3">
                    <h6 class="section-title">Budget Distribution by Dealer</h6>
                    <div class="chart-body">
                        <canvas id="budgetDistributionChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card p-3">
            <h6 class="section-title">Dealer Budget Summary</h6>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle" id="budgetTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Dealer</th>
                            <th>Approved Budget</th>
                            <th>Actual Expenses</th>
                            <th>Liquidation Expenses</th>
                            <th>RFP</th>
                            <th>Cash Advances</th>
                            <th>Used + Pending Transactions</th>
                            <th>Remaining Budget</th>
                            <th>Utilization</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="budgetTableBody"></tbody>
                </table>
            </div>
        </div>

        <div class="row g-4 mt-1">
            <div class="col-12">
                <div class="panel-card p-3">
                    <h6 class="section-title">Budget Utilization by Department</h6>
                    <div class="chart-body">
                        <canvas id="departmentUtilizationChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="table-card p-3 mt-4">
            <h6 class="section-title">Department Budget Summary</h6>
            <div id="departmentState" class="alert alert-info py-2" style="display:none;"></div>
            <div class="table-responsive">
                <table class="table table-striped table-bordered align-middle" id="departmentBudgetTable" style="width:100%">
                    <thead>
                        <tr>
                            <th>Dealer</th>
                            <th>Department</th>
                            <th>Approved Budget</th>
                            <th>Actual Expenses</th>
                            <th>Liquidation Expenses</th>
                            <th>RFP</th>
                            <th>Cash Advances</th>
                            <th>Used + Pending Transactions</th>
                            <th>Remaining Budget</th>
                            <th>Utilization</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="dealerDetailModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Dealer Budget Details: <span id="detailDealerName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="detailState" class="alert alert-info py-2" style="display:none;"></div>
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle" id="dealerDetailTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>Department</th>
                                <th>Budget Code</th>
                                <th>Activity</th>
                                <th>Approved</th>
                                <th>Actual</th>
                                <th>Liquidation</th>
                                <th>RFP</th>
                                <th>Cash Advance</th>
                                <th>Remaining</th>
                                <th>Utilization</th>
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
        let charts = {
            utilization: null,
            approvedVsCommitted: null,
            remaining: null,
            distribution: null,
            departmentUtilization: null
        };
        let budgetTable = null;
        let departmentTable = null;
        let detailTable = null;

        const colors = ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6f42c1', '#20c997', '#6c757d'];

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
                dealer: $('#dealerSelect').val()
            };
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
            setDashboardState('Loading budget utilization...', 'info');

            const selectedFilters = filters();

            $.when(
                $.ajax({
                    url: 'data/get_budget_summary_dealer.php',
                    type: 'GET',
                    data: selectedFilters,
                    dataType: 'json'
                }),
                $.ajax({
                    url: 'data/get_budget_summary_dealer_department.php',
                    type: 'GET',
                    data: selectedFilters,
                    dataType: 'json'
                })
            ).done(function(dealerResponse, departmentResponse) {
                renderDashboard(dealerResponse[0].data || []);
                renderDepartmentDashboard(departmentResponse[0].data || []);
            }).fail(function() {
                setDashboardState('Unable to load budget utilization data.', 'error');
            });
        }

        function renderDashboard(rows) {
            setDashboardState(rows.length ? '' : 'No dealer budget data found for the selected filters.', 'info');

            const totals = rows.reduce((acc, row) => {
                acc.approved += Number(row.TotalApprovedBudget || 0);
                acc.actual += Number(row.TotalActualExpense || 0);
                acc.liquidation += Number(row.TotalLiquidationExpense || 0);
                acc.rfp += Number(row.TotalRFP || 0);
                acc.cashAdvance += Number(row.TotalCashAdvance || 0);
                acc.committed += Number(row.TotalCommittedAmount || 0);
                acc.remaining += Number(row.TotalRemainingBudget || 0);
                return acc;
            }, {
                approved: 0,
                actual: 0,
                liquidation: 0,
                rfp: 0,
                cashAdvance: 0,
                committed: 0,
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

        function renderCharts(rows) {
            destroyCharts();

            const labels = rows.map(row => row.BudgetDealer || 'Unknown');
            const approved = rows.map(row => Number(row.TotalApprovedBudget || 0));
            const committed = rows.map(row => Number(row.TotalCommittedAmount || 0));
            const utilization = rows.map(row => Number(row.BudgetUtilization || 0));
            const remaining = rows.map(row => Number(row.TotalRemainingBudget || 0));
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
                            color: '#fff',
                            formatter: value => formatPercent(value),
                            font: { size: 11 }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => value + '%'
                            }
                        }
                    }
                },
                plugins: chartPlugins()
            });

            charts.approvedVsCommitted = new Chart(document.getElementById('budgetLineChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                            label: 'Approved Budget',
                            data: approved,
                            backgroundColor: '#0d6efd'
                        },
                        {
                            label: 'Used + Pending Transactions',
                            data: committed,
                            backgroundColor: '#dc3545'
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: {
                            display: false
                        },
                        tooltip: {
                            callbacks: {
                                label: context => `${context.dataset.label}: ${formatPeso(context.raw)}`
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true
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
                            color: '#212529',
                            anchor: 'end',
                            align: 'right',
                            formatter: value => formatPeso(value),
                            font: { size: 11 }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true
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
                        datalabels: {
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

            const html = rows.map(row => {
                const dealer = row.BudgetDealer || '';
                return `
                    <tr>
                        <td>${escapeHtml(dealer || '-')}</td>
                        <td>${formatPeso(row.TotalApprovedBudget)}</td>
                        <td>${formatPeso(row.TotalActualExpense)}</td>
                        <td>${formatPeso(row.TotalLiquidationExpense)}</td>
                        <td>${formatPeso(row.TotalRFP)}</td>
                        <td>${formatPeso(row.TotalCashAdvance)}</td>
                        <td>${formatPeso(row.TotalCommittedAmount)}</td>
                        <td>${formatPeso(row.TotalRemainingBudget)}</td>
                        <td>${formatPercent(row.BudgetUtilization)}</td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-primary view-dealer-btn" data-dealer="${escapeHtml(dealer)}">
                                <i class="ti ti-eye"></i> View Details
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');

            $('#budgetTableBody').html(html);
            budgetTable = $('#budgetTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[0, 'asc']]
            });
        }

        function renderDepartmentDashboard(rows) {
            $('#departmentState')
                .toggle(!rows.length)
                .text(rows.length ? '' : 'No department budget data found for the selected filters.');

            renderDepartmentChart(rows);
            renderDepartmentTable(rows);
        }

        function renderDepartmentChart(rows) {
            if (charts.departmentUtilization) {
                charts.departmentUtilization.destroy();
                charts.departmentUtilization = null;
            }

            const labels = rows.map(row => {
                const department = row.BudgetDepartment || 'Unknown';
                return $('#dealerSelect').val()
                    ? department
                    : `${row.BudgetDealer || 'Unknown'} - ${department}`;
            });
            $('#departmentUtilizationChart').parent().css('height', Math.max(340, rows.length * 42) + 'px');

            charts.departmentUtilization = new Chart(document.getElementById('departmentUtilizationChart'), {
                type: 'bar',
                data: {
                    labels,
                    datasets: [{
                        label: 'Utilization',
                        data: rows.map(row => Number(row.BudgetUtilization || 0)),
                        backgroundColor: labels.map((_, index) => colors[index % colors.length]),
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        datalabels: {
                            color: '#212529',
                            anchor: 'end',
                            align: 'right',
                            formatter: value => formatPercent(value),
                            font: { size: 11 }
                        },
                        tooltip: {
                            callbacks: {
                                afterLabel: context => {
                                    const row = rows[context.dataIndex];
                                    return [
                                        `Approved: ${formatPeso(row.TotalApprovedBudget)}`,
                                        `Pending Transactions: ${formatPeso(row.TotalCommittedAmount)}`,
                                        `Remaining: ${formatPeso(row.TotalRemainingBudget)}`
                                    ];
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            beginAtZero: true,
                            ticks: {
                                callback: value => value + '%'
                            }
                        }
                    }
                },
                plugins: chartPlugins()
            });
        }

        function renderDepartmentTable(rows) {
            if ($.fn.DataTable.isDataTable('#departmentBudgetTable')) {
                $('#departmentBudgetTable').DataTable().clear().destroy();
            }

            const html = rows.map(row => `
                <tr>
                    <td>${escapeHtml(row.BudgetDealer || '-')}</td>
                    <td>${escapeHtml(row.BudgetDepartment || '-')}</td>
                    <td>${formatPeso(row.TotalApprovedBudget)}</td>
                    <td>${formatPeso(row.TotalActualExpense)}</td>
                    <td>${formatPeso(row.TotalLiquidationExpense)}</td>
                    <td>${formatPeso(row.TotalRFP)}</td>
                    <td>${formatPeso(row.TotalCashAdvance)}</td>
                    <td>${formatPeso(row.TotalCommittedAmount)}</td>
                    <td>${formatPeso(row.TotalRemainingBudget)}</td>
                    <td>${formatPercent(row.BudgetUtilization)}</td>
                </tr>
            `).join('');

            $('#departmentBudgetTable tbody').html(html);
            departmentTable = $('#departmentBudgetTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[0, 'asc'], [1, 'asc']]
            });
        }

        function loadDealerDetails(dealer) {
            $('#detailDealerName').text(dealer);
            $('#detailState').removeClass('alert-danger').addClass('alert-info').show().text('Loading dealer budget details...');
            $('#dealerDetailModal').modal('show');

            const detailFilters = filters();
            detailFilters.dealer = dealer;

            $.ajax({
                url: 'data/get_budget_dealer_details.php',
                type: 'GET',
                data: detailFilters,
                dataType: 'json',
                success: function(response) {
                    renderDealerDetails(response.data || []);
                },
                error: function() {
                    $('#detailState').removeClass('alert-info').addClass('alert-danger').show().text('Unable to load dealer budget details.');
                }
            });
        }

        function renderDealerDetails(rows) {
            $('#detailState').toggle(!rows.length).text(rows.length ? '' : 'No budget codes found for this dealer.');

            if ($.fn.DataTable.isDataTable('#dealerDetailTable')) {
                $('#dealerDetailTable').DataTable().clear().destroy();
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
                    <td>${formatPeso(row.RemainingBudget)}</td>
                    <td>${formatPercent(row.BudgetUtilization)}</td>
                </tr>
            `).join('');

            $('#dealerDetailTable tbody').html(html);
            detailTable = $('#dealerDetailTable').DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [[0, 'asc'], [1, 'asc']]
            });
        }

        $('#startDate, #endDate, #dealerSelect').on('change', loadDashboard);
        $('#clearFiltersBtn').on('click', function() {
            $('#startDate').val('');
            $('#endDate').val('');
            $('#dealerSelect').val('');
            loadDashboard();
        });
        $('#exportCsvBtn').on('click', function() {
            const url = new URL('export/export_budget_utilization_dealer_csv.php', window.location.href);
            const selectedFilters = filters();

            Object.entries(selectedFilters).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });

            window.location.href = url.toString();
        });
        $('#exportExcelBtn').on('click', function() {
            const url = new URL('export/export_budget_utilization_dealer_excel.php', window.location.href);
            const selectedFilters = filters();

            Object.entries(selectedFilters).forEach(([key, value]) => {
                if (value) url.searchParams.set(key, value);
            });

            window.location.href = url.toString();
        });

        $(document).on('click', '.view-dealer-btn', function() {
            loadDealerDetails($(this).data('dealer'));
        });

        loadDashboard();
    });
</script>

<?php
include 'footer.php';
?>
