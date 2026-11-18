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
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="page-header-title">
                                <h2 class="mb-0">Budgets</h2>
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
        </div>
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
                                    <th style="width: 40.67796610169492%;">Activity</th>
                                    <th style="width: 40.67796610169492%;">Approved Budget</th>
                                    <th style="width: 40.67796610169492%;">Action</th>
                                </tr>
                            </thead>
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

<div class="modal fade" id="transfer_budget_modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Budget from Another Budget</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="transferBudgetForm">
                    <input type="hidden" name="target_budget_id" id="transfer_target_budget_id">

                    <div class="mb-3">
                        <label class="form-label">Target Budget Code</label>
                        <input type="text" class="form-control" id="transfer_target_budget_code" readonly>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Source Budget Code</label>
                        <select class="form-select" name="source_budget_id" id="source_budget_id" required>
                            <option value="">Select Source Budget</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Amount to Transfer (₱)</label>
                        <input type="number" class="form-control" name="transfer_amount" step="0.01" required>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Transfer</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>


<!-- Scripts -->
<script>
    // View budget expenses
    $(document).on('click', '.view-btn', function() {
        const budgetCode = $(this).data('budgetcode');
        const tbody = $('#expense_modal tbody');
        tbody.html('<tr><td colspan="6" class="text-center text-muted">Loading...</td></tr>');

        $.ajax({
            url: 'data/get_expenses.php',
            type: 'GET',
            data: {
                budget_code: budgetCode
            },
            dataType: 'json',
            success: function(response) {
                tbody.empty();
                if (response.length > 0) {
                    response.forEach(expense => {
                        tbody.append(`
                            <tr>
                                <td>${expense.budget_code}</td>
                                <td>₱${parseFloat(expense.expense_amount).toLocaleString('en-US', { minimumFractionDigits: 2 })}</td>
                                <td>${expense.expense_date}</td>
                                 <td>${expense.pr_number}</td>
                            <td>${expense.po_number}</td>
                                <td>${expense.remarks ?? ''}</td>
                            </tr>
                        `);
                    });
                } else {
                    tbody.html('<tr><td colspan="6" class="text-center text-muted">No expenses found.</td></tr>');
                }
            },
            error: function() {
                tbody.html('<tr><td colspan="6" class="text-center text-danger">Error loading expenses.</td></tr>');
            }
        });
    });


    function updatePrice(productId, currentPrice) {
        if ($.fn.modal) {
            $.fn.modal.Constructor.prototype.enforceFocus = function() {};
        }

        Swal.fire({
            title: 'Update Budget',
            input: 'text',
            inputLabel: 'Adjust budget amount:',
            inputValue: parseFloat(currentPrice).toFixed(2), // show plain number initially
            inputAttributes: {
                inputmode: 'decimal'
            },
            showCancelButton: true,
            confirmButtonText: 'Update',
            showLoaderOnConfirm: true,
            customClass: {
                popup: 'swal2-front'
            },
            didOpen: () => {
                const input = Swal.getInput();

                // Let user type freely (only restrict invalid chars)
                input.addEventListener('input', () => {
                    let value = input.value.replace(/,/g, '');
                    value = value.replace(/[^0-9.]/g, ''); // keep only numbers + decimal

                    // Only allow one decimal point
                    const parts = value.split('.');
                    if (parts.length > 2) {
                        value = parts[0] + '.' + parts.slice(1).join('');
                    }

                    // Limit decimals to 2
                    if (parts[1]) {
                        parts[1] = parts[1].slice(0, 2);
                        value = parts[0] + '.' + parts[1];
                    }

                    input.value = value;
                });

                // On blur (when leaving the field), format with commas
                input.addEventListener('blur', () => {
                    let value = input.value.replace(/,/g, '');
                    if (!value) return;

                    const num = parseFloat(value);
                    if (!isNaN(num)) {
                        input.value = num.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                });
            },
            // preConfirm: (newPrice) => {
            //     if (!newPrice) {
            //         Swal.showValidationMessage('Please enter a valid number');
            //         return false;
            //     }

            //     // Remove commas before saving
            //     let cleanValue = newPrice.replace(/,/g, '');
            //     if (isNaN(cleanValue)) {
            //         Swal.showValidationMessage('Invalid number format');
            //         return false;
            //     }

            //     cleanValue = parseFloat(cleanValue).toFixed(2); // store as 1234.56

            //     return fetch('data/update_item_price.php', {
            //             method: 'POST',
            //             headers: {
            //                 'Content-Type': 'application/x-www-form-urlencoded'
            //             },
            //             body: `id=${productId}&price=${cleanValue}`
            //         })
            //         .then(response => response.json())
            //         .then(data => {
            //             if (data.status === "success") {
            //                 Swal.fire({
            //                     icon: "success",
            //                     title: "Price updated!",
            //                     showConfirmButton: false,
            //                     timer: 1000
            //                 }).then(() => {
            //                     location.reload(); // reload page after alert disappears
            //                 });
            //             } else {
            //                 Swal.fire({
            //                     icon: "error",
            //                     title: "Update failed",
            //                     text: data.message || "Something went wrong."
            //                 });
            //             }
            //         }).catch(error => {
            //             Swal.showValidationMessage(`Request failed: ${error}`);
            //         });

            // },
            preConfirm: (newPrice) => {
                if (!newPrice) {
                    Swal.showValidationMessage('Please enter a valid number');
                    return false;
                }

                // Remove commas before saving
                let cleanValue = newPrice.replace(/,/g, '');

                // ✅ Validate with regex (only numbers + optional 2 decimals)
                if (!/^\d+(\.\d{1,2})?$/.test(cleanValue)) {
                    Swal.showValidationMessage('Invalid number format (max 2 decimals)');
                    return false;
                }

                // ✅ Send as plain string (e.g. "123.95"), no float conversion
                return fetch('data/update_item_price.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded'
                        },
                        body: `id=${productId}&price=${encodeURIComponent(cleanValue)}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === "success") {
                            Swal.fire({
                                icon: "success",
                                title: "Price updated!",
                                showConfirmButton: false,
                                timer: 1000
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Update failed",
                                text: data.message || "Something went wrong."
                            });
                        }
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
            },
            allowOutsideClick: () => !Swal.isLoading()
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Price Updated!',
                    text: 'The product price has been updated successfully.'
                });
            }
        });
    }








    $(document).ready(function() {
        $('#itemsTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: 'budget/get_budgets.php',
            columns: [{
                    data: 'budget_dealer',
                    render: function(data, type, row) {
                        return truncate(data, 20);
                    }
                },
                {
                    data: 'budget_department',
                    render: function(data, type, row) {
                        return truncate(data, 20);
                    }
                },
                {
                    data: 'budget_code',
                    render: function(data, type, row) {
                        return truncate(data, 20);
                    }
                },
                {
                    data: 'budget_activity',
                    render: function(data, type, row) {
                        return truncate(data, 20);
                    }
                },
                {
                    data: 'approved_budget',
                    render: function(data, type, row) {
                        if (data == null || data === '') return '';
                        return '₱' + parseFloat(data).toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        });
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: row => `
                    <ul class="list-inline me-auto mb-0">
                        <li class="list-inline-item" title="View">
                            <a class="avtar avtar-xs btn-link-secondary btn view-btn" 
                               data-bs-toggle="modal" data-bs-target="#expense_modal"
                               data-budgetcode="${row.budget_code}">
                               <i class="ti ti-eye f-18"></i>
                            </a>
                        </li>
                        <li class="list-inline-item" title="Add from Other Budget">
                            <a class="avtar avtar-xs btn-link-primary btn transfer-btn"
                               data-bs-toggle="modal" data-bs-target="#transfer_budget_modal"
                               data-budgetid="${row.budget_id}" data-budgetcode="${row.BudgetCode}">
                               <i class="ti ti-edit-circle f-18"></i>
                            </a>
                        </li>
                    </ul>
                `
                }
            ]
        });

        // Helper function to truncate text
        function truncate(str, maxLength) {
            if (!str) return '';
            str = str.toString();
            return str.length > maxLength ? str.substring(0, maxLength) + '…' : str;
        }
    });
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