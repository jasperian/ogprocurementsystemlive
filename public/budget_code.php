<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';

$dealerStmt = $pdo->prepare("SELECT DISTINCT budget_dealer FROM budgets ORDER BY budget_dealer");
$dealerStmt->execute();
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);

$deptStmt = $pdo->prepare("SELECT DISTINCT budget_department FROM budgets ORDER BY budget_department");
$deptStmt->execute();
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);
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
                <h3 class="mb-0" style="font-weight:700; color: white;">📊 Budget Utilization</h3>
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
                    <label>Dealer</label>
                    <select id="dealerSelect" class="form-select form-select-sm">
                        <option value="">All Dealers</option>
                        <?php foreach ($dealers as $dealer): ?>
                            <option value="<?= htmlspecialchars($dealer) ?>">
                                <?= htmlspecialchars($dealer) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label>Department</label>
                    <select id="departmentSelect" class="form-select form-select-sm">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?= htmlspecialchars($dept) ?>">
                                <?= htmlspecialchars($dept) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="color: rgba(255,255,255,0.85); font-size:0.85rem;">(auto updates)</div>
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
                                    <th>Department</th>
                                    <th>Budget Activity</th>
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

<!-- Add Budget Modal -->
<div class="modal fade" id="add_budget_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-plus"></i> Add Budget
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <form id="addBudgetForm" enctype="multipart/form-data">

                    <!-- PASSED FROM TABLE -->
                    <input type="hidden" name="budget_id" id="add_budget_id">

                    <div class="mb-3">
                        <label class="form-label">Budget Code</label>
                        <input type="text" id="add_budget_code" class="form-control" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Additional Budget Amount</label>
                        <input type="number"
                            class="form-control"
                            name="amount"
                            min="1"
                            step="0.01"
                            required>
                    </div>

                    <div class="mb-3">
                        <label for="demo-input-file" class="form-label">File</label>
                        <input class="form-control" type="file" id="demo-input-file" name="file">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control"
                            name="remarks"
                            rows="3"
                            required></textarea>
                    </div>

                </form>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="saveAddBudget">
                    <i class="ti ti-device-floppy"></i> Save
                </button>
            </div>

        </div>
    </div>
</div>

<!-- Budget History Modal -->
<div class="modal fade" id="budget_history_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-history"></i> Budget History
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <table class="table table-striped table-bordered" id="budgetHistoryTable">
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
                    <tbody>
                        <!-- AJAX will populate here -->
                    </tbody>
                </table>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

        </div>
    </div>
</div>

<div class="modal fade" id="viewFileModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="ti ti-file"></i> View Attachment
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <iframe id="fileViewer"
                    src=""
                    style="width:100%; height:500px;"
                    frameborder="0">
                </iframe>
            </div>

            <div class="modal-footer">
                <a id="downloadFile" class="btn btn-secondary" download>
                    <i class="ti ti-download"></i> Download
                </a>
                <button class="btn btn-danger" data-bs-dismiss="modal">Close</button>
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
            const department = $("#departmentSelect").val();

            $.ajax({
                url: "data/view_budget_code.php",
                type: "GET",
                data: {
                    start: start,
                    end: end,
                    dealer: dealer,
                    department: department
                },
                dataType: "json",
                success: function(response) {



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
                <td>${row.BudgetDealer || "-"}</td>
                <td>${row.BudgetDepartment || "-"}</td>
                <td>
                  ${row.BudgetActivity 
                    ? row.BudgetActivity.substring(0, 20) + (row.BudgetActivity.length > 20 ? '...' : '') 
                    : "-"}
                </td>
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


                        <li class="list-inline-item">
                            <a class="btn btn-info btn-sm view-history-btn"
                            data-budgetid="${row.budget_id}"
                            data-bs-toggle="modal"
                            data-bs-target="#budget_history_modal">
                            <i class="ti ti-history"></i>
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
        // FILTER LISTENERS
        // ==========================

        $("#startDate, #endDate, #dealerSelect, #departmentSelect").on("change", function() {
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
        // VIEW BUDGET HISTORY (MODAL)



        $(document).on("click", ".add-budget-btn", function() {

            const budgetId = $(this).data("budgetid");
            const budgetCode = $(this).data("budgetcode");

            $("#add_budget_id").val(budgetId);
            $("#add_budget_code").val(budgetCode);

        });


        $(document).on("click", "#saveAddBudget", function(e) {
            e.preventDefault();

            const amount = $("input[name='amount']").val();
            const fileInput = $("input[name='file']")[0];
            const budgetCode = $("#add_budget_code").val();

            // ❌ Validation
            if (!amount || amount <= 0) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Amount',
                    text: 'Please enter a valid budget amount.'
                });
                return;
            }

            if (!fileInput.files.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'File Required',
                    text: 'Please attach a file before submitting.'
                });
                return;
            }

            // ✅ Confirmation
            Swal.fire({
                title: 'Are you sure?',
                html: `Add <b>${amount}</b> to budget <b>${budgetCode}</b>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: 'Yes, add it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {

                if (result.isConfirmed) {

                    let formData = new FormData(document.getElementById("addBudgetForm"));

                    $.ajax({
                        url: "budget/add_budget.php",
                        type: "POST",
                        data: formData,
                        dataType: "json",
                        processData: false,
                        contentType: false,
                        success: function(res) {

                            if (res.status === "success") {
                                $("#add_budget_modal").modal("hide");
                                $("#addBudgetForm")[0].reset();

                                if (typeof loadBudgetTable === "function") {
                                    loadBudgetTable();
                                }

                                Swal.fire({
                                    icon: 'success',
                                    title: 'Success!',
                                    text: 'Budget updated successfully.',
                                    timer: 2000,
                                    showConfirmButton: false
                                });

                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    html: res.message || 'Something went wrong.'
                                });
                            }
                        },
                        error: function(xhr) {
                            Swal.fire({
                                icon: 'error',
                                title: 'Server Error',
                                html: xhr.responseText
                            });
                        }
                    });
                }
            });
        });

        $(document).on("click", ".view-file-btn", function() {
            const filePath = $(this).data("file");

            if (!filePath) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No File',
                    text: 'No attachment available.'
                });
                return;
            }

            $("#fileViewer").attr("src", filePath);
            $("#downloadFile").attr("href", filePath);

            $("#viewFileModal").modal("show");
        });


        $(document).on("click", ".view-history-btn", function() {
            const budgetId = $(this).data("budgetid");

            $.ajax({
                url: "budget/get_budget_history.php",
                type: "GET",
                data: {
                    budget_id: budgetId
                },
                dataType: "json",
                success: function(res) {
                    let tbody = "";
                    if (res.status === "success" && res.data.length > 0) {
                        res.data.forEach((row, index) => {

                            let viewBtn = row.file_path ?
                                `<button class="btn btn-sm btn-primary view-file-btn"
                        data-file="${row.file_path}">
                        <i class="ti ti-eye"></i> View
                   </button>` :
                                '-';

                            tbody += `
                <tr>
                    <td>${index + 1}</td>
                    <td>${numberFormat(row.amount)}</td>
                    <td>${row.remarks}</td>
                    <td>${row.created_at}</td>
                    <td>${row.created_by || '-'}</td>
                    <td>${viewBtn}</td>
                </tr>
            `;
                        });
                    } else {
                        tbody = `<tr><td colspan="6" class="text-center">No history found</td></tr>`;
                    }
                    $("#budgetHistoryTable tbody").html(tbody);
                },
                error: function(xhr) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Server Error',
                        text: xhr.responseText || 'Could not load history'
                    });
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