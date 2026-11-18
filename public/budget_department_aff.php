<?php
require '../init.php';

include 'header.php';
include 'navigation.php';

$user_id = $_SESSION['user_id'] ?? null;
$dealer  = $_SESSION['dealer'] ?? '';

$aff_departments = [
    97 => ['TSURE'],     // Rosevie Bation
    98 => ['AUTOQUIX'],  // Eroll Balanquit
    99 => ['HINO'],
    100 => ['PFFC'],
    101 => ['DIVINE']// Quennie Jane Badajos
];

if (array_key_exists((int)$user_id, $aff_departments)) {

    $departments = $aff_departments[(int)$user_id];

    $placeholders = implode(',', array_fill(0, count($departments), '?'));

    $dealerStmt = $pdo->prepare("
        SELECT DISTINCT budget_department 
        FROM budgets 
        WHERE budget_dealer = ?
        AND budget_department IN ($placeholders)
        ORDER BY budget_department
    ");

    $dealerStmt->execute(array_merge([$dealer], $departments));

} else {

    $department = $_SESSION['department'] ?? '';

    $dealerStmt = $pdo->prepare("
        SELECT DISTINCT budget_department 
        FROM budgets 
        WHERE budget_dealer = ?
        AND budget_department = ?
        ORDER BY budget_department
    ");

    $dealerStmt->execute([
        $dealer,
        $department
    ]);
}

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
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 Budget Utilization (<?php echo $department; ?>)</h3>
                <small class="text-white-50">Updated: <?= date('F j, Y') ?></small>
            </div>
        </div>

        <!-- [ breadcrumb ] start -->
        <!-- <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="page-header-title">
                                <h2 class="mb-0">Budget Utilization</h2>
                            </div>
                            <div class="btn-page">
                                <a href="add_budget_code" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                    <i class="ti ti-plus f-18"></i> New Budget
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->
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
                        <h5 class="text-center">Approved Budget vs Actual Expenses</h5>
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
                                    <th>Activity</th>
                                    <th>Budget Code</th>
                                    <th>Approved Budget</th>
                                    <th>Actual Expenses</th>
                                    <th>Remaining Budget</th>
                                    <th>Budget Utilization</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="budgetTableBody"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 

        <div class="row">

            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">

                        <table class="table-stripped display responsive nowrap table-striped p-1" id="itemsTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 4.872881355932203%;">Dealer</th>
                                    <th style="width: 34.32203389830508%;">Department</th>
                                    <th style="width: 20.127118644067796%;">Budget Code</th>
                                    <th style="width: 40.67796610169492%;">Budget Activity</th>
                                    <th style="width: 40.67796610169492%;">Approved Budget</th>
                                    <th style="width: 40.67796610169492%;">Actual Expenses</th>
                                    <th style="width: 40.67796610169492%;">Remaining Budget</th>
                                    <th style="width: 40.67796610169492%;">Budget Utilization</th>
                                    <th style="width: 40.67796610169492%;">Action</th>
                                </tr>
                            </thead>
                        </table>
                    </div>
                </div>
            </div>
        </div> -->
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
    // $(document).on('click', '.view-btn', function() {
    //     var budgetCode = $(this).data('budgetcode');

    //     // Clear old data first
    //     $('#expense_modal tbody').html('<tr><td colspan="5" class="text-center text-muted">Loading...</td></tr>');

    //     // Fetch expenses for this budget code
    //     $.ajax({
    //         url: 'data/get_expenses.php',
    //         type: 'GET',
    //         data: {
    //             budget_code: budgetCode
    //         },
    //         dataType: 'json',
    //         success: function(response) {
    //             var tbody = $('#expense_modal tbody');
    //             tbody.empty();

    //             if (response.length > 0) {
    //                 $.each(response, function(index, expense) {
    //                     tbody.append(`
    //                     <tr>
    //                         <td>${expense.budget_code}</td>
    //                         <td>₱${parseFloat(expense.expense_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
    //                         <td>${expense.expense_date}</td>

    //                         <td>${expense.remarks ?? ''}</td>
    //                     </tr>
    //                 `);
    //                 });
    //             } else {
    //                 tbody.html('<tr><td colspan="5" class="text-center text-muted">No expenses found.</td></tr>');
    //             }
    //         },
    //         error: function() {
    //             $('#expense_modal tbody').html('<tr><td colspan="5" class="text-center text-danger">Error loading expenses.</td></tr>');
    //         }
    //     });
    // });



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
                url: "budget/get_budget_department_aff.php",
                type: "GET",
                data: {
                    start: start,
                    end: end,
                    dealer: dealer
                },
                dataType: "json",
                success: function(response) {

                    // Extract chart data
                    const labels = response.data.map(r => r.BudgetCode);
                    const approved = response.data.map(r => parseFloat(r.ApprovedBudget));
                    const actual = response.data.map(r => parseFloat(r.ActualExpense));
                    const utilization = response.data.map(r => parseFloat(r.BudgetUtilization));
                    const remaining = response.data.map(r => parseFloat(r.RemainingBudget));

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
            // Destroy old DataTable
            if ($.fn.DataTable.isDataTable("#budgetTable")) {
                $("#budgetTable").DataTable().clear().destroy();
            }

            // Build table rows
            let tableHTML = "";

            data.forEach(row => {
                tableHTML += `
            <tr>
                <td>${row.BudgetActivity}</td>
                <td>${row.BudgetCode || "-"}</td>
                <td>${numberFormat(row.ApprovedBudget)}</td>
                <td>${numberFormat(row.ActualExpense)}</td>
                <td>${numberFormat(row.RemainingBudget)}</td>
                <td>${parseFloat(row.BudgetUtilization)}%</td>

                <td>
                    <ul class="list-inline me-auto mb-0">

                        <li class="list-inline-item" title="View">
                            <a class="avtar avtar-xs btn-link-secondary btn view-btn"
                               data-bs-toggle="modal" 
                               data-bs-target="#expense_modal"
                               data-budgetcode="${row.BudgetCode}">
                                <i class="ti ti-eye f-18"></i>
                            </a>
                        </li>

                        <li class="list-inline-item" title="Add from Other Budget">
                            <a class="avtar avtar-xs btn-link-primary btn transfer-btn"
                               data-bs-toggle="modal" 
                               data-bs-target="#transfer_budget_modal"
                               data-budgetid="${row.budget_id}"
                               data-budgetcode="${row.BudgetCode}">
                                <i class="ti ti-edit-circle f-18"></i>
                            </a>
                        </li>

                    </ul>
                </td>
            </tr>`;
            });

            // Inject rows here 👇
            $("#budgetTableBody").html(tableHTML);

            // Initialize DataTable AFTER rows exist
            budgetTable = $("#budgetTable").DataTable({
                responsive: true,
                pageLength: 10,
                order: [
                    [1, "asc"]
                ],
                autoWidth: false
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
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: {
                            color: '#000', // black font
                            formatter: (value, context) => {
                                return ('₱' +
                                    Number(value).toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })
                                );
                                // context.chart.data.labels[context.dataIndex] + 
                            }
                        }
                    }
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
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: {
                            color: '#000', // black font
                            formatter: (value, context) => {
                                return ('₱' +
                                    Number(value).toLocaleString('en-PH', {
                                        minimumFractionDigits: 2,
                                        maximumFractionDigits: 2
                                    })
                                );
                            }
                        }
                    }
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
                    maintainAspectRatio: false,
                    plugins: {
                        datalabels: {
                            color: '#000', // black font
                            formatter: (value, context) => {
                                return value + '%';
                            }
                        }
                    }
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
                            fill: false
                        },
                        {
                            label: "Actual Expense",
                            data: actual,
                            borderColor: "#FF6384",
                            fill: false
                        }
                    ]
                },
                options: {
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            ticks: {
                                display: false // 👈 hides Y-axis labels
                            },
                            grid: {
                                drawBorder: false
                            }
                        }
                    },
                    plugins: {
                        datalabels: {
                            color: '#000',
                            formatter: (value) => {
                                return '₱' + Number(value).toLocaleString('en-PH', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
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
                                <td>
                                <a href=view_purchase_requisition?pr_number=${x.pr_number}>
                                    ${x.pr_number}
                                </a>
                                </td>
                                <td> 
                                <a href=view_purchase_requisition?pr_number=${x.pr_number}>
                                    ${x.po_number}
                                </a></td>
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
<script>
    // $(document).ready(function() {
    //     $(document).on('click', '.view-btn', function() { // fixed class
    //         var budgetCode = $(this).data('budgetcode');

    //         $('#expense_modal tbody').empty(); // fixed selector

    //         $.ajax({
    //             url: 'data/get_expenses.php',
    //             type: 'POST',
    //             data: {
    //                 budgetCode: budgetCode
    //             },
    //             dataType: 'json',
    //             success: function(response) {
    //                 if (response.length > 0) {
    //                     response.forEach(function(item) {
    //                         $('#expense_modal tbody').append(`
    //                         <tr>
    //                             <td>${item.budget_activity}</td>
    //                             <td>${item.budget_code}</td>
    //                             <td>${item.expense_amount}</td>
    //                             <td>${item.expense_date}</td>
    //                             <td>${item.remarks}</td>
    //                         </tr>
    //                     `);
    //                     });
    //                 } else {
    //                     $('#expense_modal tbody').append(`
    //                     <tr><td colspan="5" class="text-center">No expenses found</td></tr>
    //                 `);
    //                 }
    //             }
    //         });
    //     });
    // });
</script>

<!-- <script>
    $(document).ready(function() {
        $('#itemsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: 'data/item_data.php',
            columns: [{
                    data: 'supplier_name'
                },
                {
                    data: 'item_name'
                },
                {
                    data: 'description'
                },
                {
                    data: 'unit_price'
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data, type, row) {
                        return `
                        <ul class="list-inline me-auto mb-0">
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="View">
                                <a class="avtar avtar-xs btn-link-secondary btn-pc-default btn view-btn" 
                                data-sap="${row.supplier_id}" 
                                data-name="${row.item_name}"
                                data-tin="${row.description}" data-bs-toggle="modal" data-bs-target="#cust-modal"><i class="ti ti-eye f-18"></i></a>
                            </li>
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                <a href="edit_supplier.php?sap_id=${row.supplier_id}" class="avtar avtar-xs btn-link-success btn-pc-default"><i class="ti ti-edit-circle f-18"></i></a>
                            </li>
                        </ul>
                        `;
                    }
                }
            ]
        });
    });
</script> -->
<!-- <button 
                    class="btn btn-sm btn-outline-primary view-btn"
                    data-sap="${row.sap_id}" 
                    data-name="${row.supplier_name}"
                    data-tin="${row.tin_number}"
                    data-bs-toggle="modal" 
                    data-bs-target="#cust-modal"
                    title="View Details">
                    <i class="ti ti-eye"></i>
                </button>
                 <a 
                href="edit_supplier.php?sap_id=${row.sap_id}" 
                class="btn btn-sm btn-outline-warning"
                title="Edit Supplier">
                <i class="ti ti-pencil"></i>
            </a> -->
<?php
include 'footer.php';
?>