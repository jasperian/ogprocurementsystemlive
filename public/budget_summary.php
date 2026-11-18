<!-- Mode controls -->
<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';

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
<div class="pc-container">
    <div class="pc-content">
        <!-- Header with date filters & dealer dropdown -->
        <!-- <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 Budget Utilization</h3>
                <small class="text-white-50">Updated: <?= date('F j, Y') ?></small>
            </div>

            <div class="filter-inline">
  
                <div>
                    <label for="dealerSelect">Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealer</option>
                        <option value="TOT">TOT</option>
                        <option value="TBK">TBK</option>
                        <option value="TNE">TNE</option>
                        <option value="TMR">TMR</option>
                        <option value="TSJ">TSJ</option>
                    </select>
                </div>
                <div>
                    <label for="departmentSelect">Department</label>
                    <select id="departmentSelect" class="form-select form-select-sm">
                        <option value="">All Department</option>
                        <option value="HR">HR</option>
                        <option value="ADMIN">ADMIN</option>
                        <option value="ACCOUNTING">ACCOUNTING</option>
                        <option value="FINANCE">FINANCE</option>
                        <option value="MIS">MIS</option>
                        <option value="VSD">VSD</option>
                        <option value="PURCHASING">PURCHASING</option>
                        <option value="PARTS">PARTS</option>
                    </select>
                </div>
                <div>
                    <label for="dealerSelect">Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealer</option>
                        <option value="TOT">TOT</option>
                        <option value="TBK">TBK</option>
                        <option value="TNE">TNE</option>
                        <option value="TMR">TMR</option>
                        <option value="TSJ">TSJ</option>
                    </select>
                </div>
                <div style="color: rgba(255,255,255,0.85); font-size:0.85rem;">(auto updates)</div>
            </div>
        </div> -->

        <div class="dashboard-header mb-4">
            <div class="dashboard-title">
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 Budget Utilization</h3>
                <small class="text-white-50">Updated: <?= date('F j, Y') ?></small>
            </div>

            <div class="filter-inline" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">

                <div class="btn-group mb-2" aria-label="Dashboard mode" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">
                    <button type="button" class="btn btn-sm btn-outline-primary" id="mode-dealer" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">By Dealer</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="mode-department" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">By Department</button>
                    <button type="button" class="btn btn-sm btn-outline-primary" id="mode-code" style="color: rgba(255,255,255,0.85); font-size:0.85rem;">By Budget Code</button>
                </div>
                   <!-- Filters -->
        <div class="mb-2">
            <!-- <input type="date" id="startDate">
            <input type="date" id="endDate"> -->
            <select id="dealerSelect" class="form-select form-select-sm">
                <option value="">All Dealer</option>
                <option value="TOT">TOT</option>
                <option value="TBK">TBK</option>
                <option value="TNE">TNE</option>
                <option value="TMR">TMR</option>
                <option value="TSJ">TSJ</option>
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

                        <canvas id="chartBar" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Budget vs Actual Expenses</h5>
                        <canvas id="chartLine" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Remaining Budget by Department</h5>
                        <canvas id="chartRemainingPie" height="250"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body" style="height:400px;">
                        <h5 class="text-center">Budget Distribution by Department</h5>
                        <canvas id="chartPie" height="250"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts (example placeholders) -->





        <!-- Table -->
        <table id="budgetTable" class="table table-striped table-bordered">
            <thead>
                <tr id="budgetTableHead">
                    <!-- headers will be set by JS -->
                </tr>
            </thead>
            <tbody id="budgetTableBody"></tbody>
        </table>

        <!-- Example modals -->
        <!-- expense_modal and transfer_budget_modal should exist in page and use data attributes -->
        <div class="modal fade" id="expense_modal" tabindex="-1" aria-hidden="true">...</div>
        <div class="modal fade" id="transfer_budget_modal" tabindex="-1" aria-hidden="true">...</div>

    </div>
</div>


<script>
    // ====== Unified Dashboard Script ======

    // Global chart / datatable references
    let chartInstances = {
        pie: null,
        remainingPie: null,
        bar: null,
        line: null
    };
    let budgetTable = null;

    // Mode → endpoint mapping
    const MODE_CONFIG = {
        code: {
            url: "data/get_budget_summary_department_code.php",
            labelField: "BudgetCode",
            columns: [{
                    key: "BudgetDealer",
                    title: "Dealer"
                },
                {
                    key: "BudgetDepartment",
                    title: "Department"
                },
                {
                    key: "BudgetCode",
                    title: "Code"
                },
                {
                    key: "ApprovedBudget",
                    title: "Approved"
                },
                {
                    key: "ActualExpense",
                    title: "Actual"
                },
                {
                    key: "RemainingBudget",
                    title: "Remaining"
                },
                {
                    key: "BudgetUtilization",
                    title: "Utilization"
                }
            ]
        },
        dealer: {
            url: "data/get_budget_summary_dealer.php",
            labelField: "BudgetDealer",
            columns: [{
                    key: "BudgetDealer",
                    title: "Dealer"
                },
                {
                    key: "TotalApprovedBudget",
                    title: "Approved"
                },
                {
                    key: "TotalActualExpense",
                    title: "Actual"
                },
                {
                    key: "TotalRemainingBudget",
                    title: "Remaining"
                },
                {
                    key: "BudgetUtilization",
                    title: "Utilization"
                }
            ]
        },
        department: {
            url: "data/get_budget_summary_department.php",
            labelField: "BudgetDepartment",
            columns: [{
                    key: "BudgetDealer",
                    title: "Dealer"
                },
                {
                    key: "BudgetDepartment",
                    title: "Department"
                },
                {
                    key: "TotalApprovedBudget",
                    title: "Approved"
                },
                {
                    key: "TotalActualExpense",
                    title: "Actual"
                },
                {
                    key: "TotalRemainingBudget",
                    title: "Remaining"
                },
                {
                    key: "BudgetUtilization",
                    title: "Utilization"
                }
            ]
        }
    };

    // --------------------------
    // Helpers
    // --------------------------
    function safeFloat(v) {
        // Accept string or number, fallback 0
        const n = parseFloat(v);
        return Number.isFinite(n) ? n : 0;
    }

    function numberFormat(v) {
        const n = safeFloat(v);
        return n.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function percentFormat(v) {
        const n = safeFloat(v);
        return `${n.toFixed(2)}%`;
    }

    function destroyCharts() {
        Object.keys(chartInstances).forEach(k => {
            const inst = chartInstances[k];
            if (inst && typeof inst.destroy === "function") {
                inst.destroy();
                chartInstances[k] = null;
            }
        });
    }

    // Create a simple Chart.js config generator
    function createChart(ctx, config) {
        return new Chart(ctx, config);
    }

    // --------------------------
    // Chart renderers
    // --------------------------
    function renderCharts(rows, labelField, valueFields) {
        // Normalize arrays
        const labels = rows.map(r => r[labelField] ?? "-");
        const approved = rows.map(r => safeFloat(r[valueFields.approved]));
        const actual = rows.map(r => safeFloat(r[valueFields.actual]));
        const remaining = rows.map(r => safeFloat(r[valueFields.remaining]));
        const utilization = rows.map(r => safeFloat(r[valueFields.utilization]));

        // Destroy old
        destroyCharts();

        // Pie chart (approved)
        const pieCtx = document.getElementById("chartPie").getContext("2d");
        chartInstances.pie = createChart(pieCtx, {
            type: "pie",
            data: {
                labels,
                datasets: [{
                    label: "Approved",
                    data: approved
                }]
            },
            options: {
                responsive: true
            }
        });

        // Remaining pie
        const remCtx = document.getElementById("chartRemainingPie").getContext("2d");
        chartInstances.remainingPie = createChart(remCtx, {
            type: "pie",
            data: {
                labels,
                datasets: [{
                    label: "Remaining",
                    data: remaining
                }]
            },
            options: {
                responsive: true
            }
        });

        // Bar (utilization)
        const barCtx = document.getElementById("chartBar").getContext("2d");
        chartInstances.bar = createChart(barCtx, {
            type: "bar",
            data: {
                labels,
                datasets: [{
                    label: "Utilization",
                    data: utilization
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Line (approved vs actual)
        const lineCtx = document.getElementById("chartLine").getContext("2d");
        chartInstances.line = createChart(lineCtx, {
            type: "line",
            data: {
                labels,
                datasets: [{
                        label: "Approved",
                        data: approved,
                        fill: false
                    },
                    {
                        label: "Actual",
                        data: actual,
                        fill: false
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    // --------------------------
    // Table builder (generic)
    // --------------------------
    function buildTable(rows, modeKey) {
        const cfg = MODE_CONFIG[modeKey];
        const cols = cfg.columns;

        // Destroy old DataTable
        if ($.fn.DataTable.isDataTable("#budgetTable")) {
            $("#budgetTable").DataTable().clear().destroy();
        }

        // Build header
        let theadHtml = "";
        cols.forEach(c => {
            theadHtml += `<th>${c.title}</th>`;
        });
        theadHtml += `<th>Actions</th>`;
        $("#budgetTableHead").html(theadHtml);

        // Build body
        let bodyHtml = "";
        rows.forEach(row => {
            bodyHtml += "<tr>";

            cols.forEach(c => {
                const rawVal = row[c.key];
                // format numeric columns (Approved/Actual/Remaining)
                if (/Approved|Actual|Remaining/i.test(c.key)) {
                    bodyHtml += `<td>${numberFormat(rawVal)}</td>`;
                } else if (/Utilization/i.test(c.key)) {
                    bodyHtml += `<td>${percentFormat(rawVal)}</td>`;
                } else {
                    bodyHtml += `<td>${(rawVal ?? "-")}</td>`;
                }
            });

            // Actions column (view + transfer)
            // We still set data-budgetcode and data-budgetid where present in row
            const bc = row.BudgetCode ?? "";
            const bid = row.budget_id ?? "";
            bodyHtml += `<td>
        <ul class="list-inline me-auto mb-0">
          <li class="list-inline-item" title="View">
            <a class="avtar avtar-xs btn-link-secondary btn view-btn"
               data-bs-toggle="modal"
               data-bs-target="#expense_modal"
               data-budgetcode="${bc}">
               <i class="ti ti-eye f-18"></i>
            </a>
          </li>
          <li class="list-inline-item" title="Add from Other Budget">
            <a class="avtar avtar-xs btn-link-primary btn transfer-btn"
               data-bs-toggle="modal"
               data-bs-target="#transfer_budget_modal"
               data-budgetid="${bid}"
               data-budgetcode="${bc}">
               <i class="ti ti-edit-circle f-18"></i>
            </a>
          </li>
        </ul>
      </td>`;

            bodyHtml += "</tr>";
        });

        $("#budgetTableBody").html(bodyHtml);

        // Initialize DataTable after DOM insertion
        budgetTable = $("#budgetTable").DataTable({
            responsive: true,
            pageLength: 10,
            order: [
                [1, "asc"]
            ],
            autoWidth: false
        });

        // Re-attach click handlers for dynamically created elements if needed
        attachRowButtonHandlers();
    }

    // --------------------------
    // Row button handlers
    // --------------------------
    function attachRowButtonHandlers() {
        // View button
        $("#budgetTable").off("click", ".view-btn").on("click", ".view-btn", function() {
            const code = $(this).data("budgetcode") || "";
            // set modal fields as needed
            $("#expense_modal").find("[data-modal-budgetcode]").text(code); // example
            // You can trigger additional AJAX to fetch details if required
        });

        // Transfer button
        $("#budgetTable").off("click", ".transfer-btn").on("click", ".transfer-btn", function() {
            const budgetId = $(this).data("budgetid");
            const code = $(this).data("budgetcode");
            // set modal values
            $("#transfer_budget_modal").find("[name='budget_id']").val(budgetId);
            $("#transfer_budget_modal").find("[name='budget_code']").val(code);
        });
    }

    // --------------------------
    // Main loader
    // --------------------------
    function loadDashboard(modeKey) {
        if (!MODE_CONFIG[modeKey]) {
            console.error("Invalid mode:", modeKey);
            return;
        }
        const cfg = MODE_CONFIG[modeKey];

        const start = $("#startDate").val();
        const end = $("#endDate").val();
        const dealer = $("#dealerSelect").val();

        $.ajax({
            url: cfg.url,
            type: "GET",
            data: {
                start,
                end,
                dealer
            },
            dataType: "json",
            success: function(response) {
                const rows = Array.isArray(response.data) ? response.data : [];
                // Determine which fields map to approved/actual/remaining/utilization
                // For flexibility, support both "ApprovedBudget" and "TotalApprovedBudget"
                const approvedField = rows.length && ("TotalApprovedBudget" in rows[0]) ? "TotalApprovedBudget" :
                    ("ApprovedBudget" in (rows[0] || {}) ? "ApprovedBudget" : "TotalApprovedBudget");
                const actualField = rows.length && ("TotalActualExpense" in rows[0]) ? "TotalActualExpense" :
                    ("ActualExpense" in (rows[0] || {}) ? "ActualExpense" : "TotalActualExpense");
                const remainingField = rows.length && ("TotalRemainingBudget" in rows[0]) ? "TotalRemainingBudget" :
                    ("RemainingBudget" in (rows[0] || {}) ? "RemainingBudget" : "TotalRemainingBudget");
                const utilField = "BudgetUtilization";

                // Render charts
                renderCharts(rows, cfg.labelField, {
                    approved: approvedField,
                    actual: actualField,
                    remaining: remainingField,
                    utilization: utilField
                });

                // Render table
                buildTable(rows, modeKey);
            },
            error: function(xhr, status, err) {
                console.error("Loading error:", err);
            }
        });
    }

    // --------------------------
    // Init & UI wiring
    // --------------------------
    $(document).ready(function() {
        // Default mode
        let currentMode = "code";

        // Mode buttons
        $("#mode-code").on("click", function() {
            currentMode = "code";
            loadDashboard(currentMode);
        });
        $("#mode-dealer").on("click", function() {
            currentMode = "dealer";
            loadDashboard(currentMode);
        });
        $("#mode-department").on("click", function() {
            currentMode = "department";
            loadDashboard(currentMode);
        });

        // Manual load button (filters)
        $("#btnLoad").on("click", function() {
            loadDashboard(currentMode);
        });

        // Initial load
        loadDashboard(currentMode);
    });
</script>