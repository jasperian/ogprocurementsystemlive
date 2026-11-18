<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';

$dealerStmt = $pdo->prepare("SELECT DISTINCT dealer FROM users WHERE dealer IS NOT NULL AND dealer <> '' AND dealer <> 'OG' AND dealer <> 'ADMIN' ORDER BY dealer");
$dealerStmt->execute();
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
?>

<style>
    @media (max-width: 768px) {
        table.dataTable td {
            white-space: normal !important;
        }
    }

    .dataTables_wrapper .dataTables_length {
        float: right;
        margin-right: 10px;
    }

    .dataTables_wrapper .dataTables_filter {
        float: left;
        margin-left: 10px;
    }


    .dataTables_info {
        margin-left: 10px;
    }

    /* Force SweetAlert2 above Bootstrap modal */
    .swal2-container {
        z-index: 20000 !important;
    }

    canvas {
        max-height: 340px;
    }

    .dashboard-header {
        background: linear-gradient(90deg, #0d6efd, #007bff);
        color: #fff;
        padding: 18px 22px;
        border-radius: 12px;
        box-shadow: 0 6px 18px rgba(13, 110, 253, 0.12);
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        flex-wrap: wrap;
    }

    .dashboard-title {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .filter-inline {
        display: flex;
        gap: 12px;
        align-items: center;
    }

    .filter-inline label {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.95);
        margin-bottom: 0;
        display: block;
    }

    .filter-inline input,
    .filter-inline select {
        min-width: 170px;
    }
</style>
<script src="https://unpkg.com/chart.js@4.4.0/dist/chart.umd.js"></script>
<script src="https://unpkg.com/chartjs-plugin-datalabels@2.2.0/dist/chartjs-plugin-datalabels.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-datalabels@2.2.0"></script>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">

        <!-- Header with date filters & dealer dropdown -->
        <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 Budget Utilization (Dealer)</h3>
                <small class="text-white-50">Updated: <?= date('F j, Y') ?></small>
            </div>

            <div class="filter-inline">
                <!-- <div>
                    <label for="startDate">From</label>
                    <input id="startDate" type="date" class="form-control form-control-sm" />
                </div>
                <div>
                    <label for="endDate">To</label>
                    <input id="endDate" type="date" class="form-control form-control-sm" />
                </div> -->
                <div>
                    <label for="dealerSelect">Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealer</option>
                        <?php foreach ($dealers as $d): ?>
                            <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="color: rgba(255,255,255,0.85); font-size:0.85rem;">(auto updates)</div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Budget Utilization (%)</h5>
                        <canvas id="budgetBarChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Budget vs Actual Expenses</h5>
                        <canvas id="budgetLineChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Remaining Budget by Department</h5>
                        <canvas id="remainingPieChart" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Budget Distribution by Department</h5>
                        <canvas id="budgetPieChart" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">
                        <table class="table-stripped display responsive nowrap table-striped p-1" id="budgetTable">
                            <thead>
                                <tr>
                                    <th>Dealer</th>
                                    <th>Approved Budget</th>
                                    <th>Actual Expenses</th>
                                    <th>Remaining Budget</th>
                                    <th>Budget Utilization</th>

                                </tr>
                            </thead>
                            <tbody id="budgetTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="expense_modal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="card table-card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h5>Expenses</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover table-borderless mb-0">
                            <thead>
                                <tr>
                                    <th>Budget Code</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>PR Number</th>
                                    <th>PO Number</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>

                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Transfer Budget Modal -->
<div class="modal fade" id="transfer_budget_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form id="transferBudgetForm" class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Transfer From Other Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <!-- Hidden fields to receive selected budget details -->
                <input type="hidden" name="from_budget_id" id="from_budget_id">
                <input type="hidden" name="from_budget_code" id="from_budget_code">

                <div class="mb-3">
                    <label for="to_budget_code" class="form-label">To Budget Code</label>
                    <input type="text" class="form-control" id="to_budget_code" name="to_budget_code" required>
                </div>

                <div class="mb-3">
                    <label for="transfer_amount" class="form-label">Amount</label>
                    <input type="number" step="0.01" class="form-control" id="transfer_amount" name="transfer_amount" required>
                </div>

                <div class="mb-3">
                    <label for="transfer_remarks" class="form-label">Remarks</label>
                    <textarea class="form-control" id="transfer_remarks" name="transfer_remarks" rows="2"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="submit" class="btn btn-primary">Transfer</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </form>
    </div>
</div>


<!-- Scripts -->
<script>
    $(document).ready(function() {

        // Global Chart Instances
        let budgetPieChart, remainingPieChart, budgetBarChart, budgetLineChart;
        let budgetTable;

        // ==========================
        // LOAD DATA (TABLE + CHARTS)
        // ==========================
        function loadDashboard() {
            const start = $("#startDate").val();
            const end = $("#endDate").val();
            const dealer = $("#dealerSelect").val();

            $.ajax({
                url: "data/get_budget_summary_dealer.php",
                type: "GET",
                data: {
                    start: start,
                    end: end,
                    dealer: dealer
                },
                dataType: "json",
                success: function(response) {

                    // Extract chart data
                    const labels = response.data.map(r => r.BudgetDealer);
                    const approved = response.data.map(r => parseFloat(r.TotalApprovedBudget));
                    const actual = response.data.map(r => parseFloat(r.TotalActualExpense));
                    const utilization = response.data.map(r => parseFloat(r.BudgetUtilization));
                    const remaining = response.data.map(r => parseFloat(r.TotalRemainingBudget));

                    // RENDER CHARTS
                    renderPieChart(labels, approved);
                    renderRemainingPieChart(labels, remaining);
                    renderBarChart(labels, utilization);
                    renderLineChart(labels, approved, actual);

                    // RENDER TABLE
                    buildTable(response.data);
                },
                error: function(xhr, status, error) {
                    console.error("Loading error:", error);
                }
            });
        }

        // ==========================
        // BUILD TABLE
        // ==========================
        function buildTable(data) {

            // If DataTable already exists → fully destroy it cleanly
            if ($.fn.DataTable.isDataTable("#budgetTable")) {
                $("#budgetTable").DataTable().clear().destroy();
            }

            // Clear table body
            $("#budgetTableBody").empty();

            let tableHTML = "";

            data.forEach(row => {
                tableHTML += `
            <tr>
                <td>${row.BudgetDealer || "-"}</td>
                <td>${numberFormat(row.TotalApprovedBudget)}</td>
                <td>${numberFormat(row.TotalActualExpense)}</td>
                <td>${numberFormat(row.TotalRemainingBudget)}</td>
                <td>${parseFloat(row.BudgetUtilization)}%</td>

                
            </tr>`;
            });

            $("#budgetTableBody").html(tableHTML);

            // Reinitialize DataTable AFTER new rows are inserted
            budgetTable = $("#budgetTable").DataTable({
                responsive: true,
                pageLength: 10,
                autoWidth: false,
                order: [
                    [1, "asc"]
                ]
            });
        }

        // Number formatter
        function numberFormat(num) {
            return parseFloat(num).toLocaleString("en-US", {
                minimumFractionDigits: 2
            });
        }

        // ==========================
        // CHART FUNCTIONS
        // ==========================

        function renderPieChart(labels, data) {
            const ctx = document.getElementById("budgetPieChart").getContext("2d");
            if (budgetPieChart) budgetPieChart.destroy();

            budgetPieChart = new Chart(ctx, {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data
                    }]
                },
                options: {
                    maintainAspectRatio: false
                },
                plugins: [ChartDataLabels]
            });
        }

        function renderRemainingPieChart(labels, data) {
            const ctx = document.getElementById("remainingPieChart").getContext("2d");
            if (remainingPieChart) remainingPieChart.destroy();

            remainingPieChart = new Chart(ctx, {
                type: "pie",
                data: {
                    labels,
                    datasets: [{
                        data
                    }]
                },
                options: {
                    maintainAspectRatio: false
                },
                plugins: [ChartDataLabels]
            });
        }

        function renderBarChart(labels, data) {
            const ctx = document.getElementById("budgetBarChart").getContext("2d");
            if (budgetBarChart) budgetBarChart.destroy();

            budgetBarChart = new Chart(ctx, {
                type: "bar",
                data: {
                    labels,
                    datasets: [{
                        label: "Utilization (%)",
                        data
                    }]
                },
                options: {
                    maintainAspectRatio: false
                },
                plugins: [ChartDataLabels]
            });
        }


        function renderLineChart(labels, approved, actual) {
            const ctx = document.getElementById("budgetLineChart").getContext("2d");
            if (budgetLineChart) budgetLineChart.destroy();

            budgetLineChart = new Chart(ctx, {
                type: "line",
                data: {
                    labels,
                    datasets: [{
                            label: "Approved Budget",
                            data: approved,
                            borderColor: "#36A2EB",
                        },
                        {
                            label: "Actual Expense",
                            data: actual,
                            borderColor: "#FF6384",
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            ticks: {
                                display: false
                            }
                        }
                    },
                    plugins: {
                        datalabels: {
                            formatter: (value) => {
                                return "₱" + value.toFixed(2);
                            },
                        },
                        tooltip: {
                            callbacks: {
                                label: (context) => {
                                    const label = context.dataset.label || "";
                                    const value = context.raw;
                                    return `${label}: ₱${value.toFixed(2)}`;
                                }
                            }
                        }
                    }
                },
                plugins: [ChartDataLabels]
            });
        }


        // ==========================
        // FILTER LISTENERS
        // ==========================

        $("#startDate, #endDate, #dealerSelect").on("change", function() {
            loadDashboard();
        });

        // ==========================
        // INITIAL LOAD
        // ==========================
        loadDashboard();


        // ==========================
        // VIEW EXPENSES (MODAL)
        // ==========================
        $(document).on("click", ".view-btn", function() {
            const budgetCode = $(this).data("budgetcode");

            const tbody = $("#expense_modal tbody");
            tbody.html(`<tr><td colspan="6" class="text-center">Loading...</td></tr>`);

            $.ajax({
                url: "data/get_expenses.php",
                type: "GET",
                data: {
                    budget_code: budgetCode
                },
                dataType: "json",
                success: function(res) {
                    tbody.empty();

                    if (res.length > 0) {
                        res.forEach(x => {
                            tbody.append(`
                            <tr>
                                <td>${x.budget_code}</td>
                                <td>₱${numberFormat(x.expense_amount)}</td>
                                <td>${x.expense_date}</td>
                                <td>${x.pr_number}</td>
                                <td>${x.po_number}</td>
                                <td>${x.remarks || ""}</td>
                            </tr>
                        `);
                        });
                    } else {
                        tbody.html(`<tr><td colspan="6" class="text-center text-muted">No expenses found.</td></tr>`);
                    }
                }
            });
        });


        // ==========================
        // TRANSFER BUDGET SUBMISSION
        // ==========================
        $("#transferBudgetForm").on("submit", function(e) {
            e.preventDefault();

            $.ajax({
                url: "data/transfer_budget.php",
                type: "POST",
                data: $(this).serialize(),
                success: function(res) {
                    alert("Budget transferred successfully!");
                    $("#transfer_budget_modal").modal("hide");
                    loadDashboard();
                }
            });
        });

    });
</script>

<?php
include 'footer.php';
?>