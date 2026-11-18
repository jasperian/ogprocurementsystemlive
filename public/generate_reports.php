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
</style>
<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="page-header-title">
                                <h2 class="mb-0">Reports</h2>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <div class="container mt-3">
                <div class="row mb-3">
                    <!-- Date Range -->
                    <div class="col-md-2">
                        <label>Start Date</label>
                        <input type="date" id="start_date" class="form-control">
                    </div>
                    <div class="col-md-2">
                        <label>End Date</label>
                        <input type="date" id="end_date" class="form-control">
                    </div>

                    <!-- Dealer/Status Dropdown -->
                    <div class="col-md-2">
                        <label>Dealer</label>
                        <select id="dealer" class="form-select">
                            <option value="">-- All --</option>
                            <option value="TNE">TNE</option>
                            <option value="TBK">TBK</option>
                            <option value="TOT">TOT</option>
                            <option value="TSJ">TSJ</option>
                            <option value="TMR">TMR</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label for="user">User</label>
                        <select id="user" class="form-control">
                            <option value="">-- All Users --</option>
                        </select>
                    </div>


                    <!-- Export Button -->
                    <div class="col-md-2 d-flex align-items-end">
                        <button id="exportBtn" class="btn btn-success w-100">
                            <i class="bi bi-file-earmark-arrow-down"></i> Export CSV
                        </button>
                    </div>
                </div>
                <!-- [ sample-page ] start -->
                <div class="col-sm-12">
                    <div class="card shadow table-card">
                        <div class="card-body mt-3">


                            <table class="table-stripped display responsive nowrap table-striped p-1" id="suppliersTable" style="width:100%">
                                <thead>
                                    <tr>
                                        <th style="width: 4.872881355932203%;">PR Number</th>
                                        <th style="width: 34.32203389830508%;">PR Date</th>
                                        <th style="width: 20.127118644067796%;">Supplier Name</th>
                                        <th style="width: 40.67796610169492%;">Terms of Payment</th>
                                        <th style="width: 4.872881355932203%;">PO Number</th>
                                        <th style="width: 34.32203389830508%;">PO Date</th>
                                        <th style="width: 20.127118644067796%;">PO Amount</th>
                                        <th style="width: 40.67796610169492%;">User</th>
                                        <th style="width: 40.67796610169492%;">Dealer</th>
                                        <!-- <th style="width: 40.67796610169492%;">Action</th> -->
                                    </tr>
                                </thead>
                            </table>
                        </div>
                    </div>
                </div>
                <!-- [ sample-page ] end -->
            </div>


            <table id="suppliersTable" class="table table-bordered table-striped"></table>
        </div>

        <!-- [ Main Content ] end -->
    </div>
</div>
<div class="modal fade" id="cust-modal" data-bs-keyboard="false" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <h5 class="mb-0">Product Details</h5>
                <a href="#" class="avtar avtar-s btn-link-danger btn-pc-default ms-auto" data-bs-dismiss="modal"><i class="ti ti-x f-20"></i></a>
            </div>
            <div class="modal-body">
                <div class="row align-items-center">
                    <div class="col-sm-4">
                        <div class="bg-light rounded position-relative">
                            <div class="text-center">
                                <div class="chat-avtar d-inline-flex mx-auto"><img class="img-fluid rounded" src="assets/images/application/img-prod-4.jpg" alt="User image" /></div>
                            </div>
                            <div class="position-absolute end-0 top-0 p-3"><span class="badge bg-success">In Stock</span></div>
                        </div>
                    </div>
                    <div class="col-sm-8">
                        <h5>Canon EOS 1500D 24.1 Digital</h5>
                        <p class="text-muted">
                            Image Enlargement: After shooting, you can enlarge photographs of the objects for clear zoomed view. Change In Aspect Ratio: Boldly crop the subject and save it with a composition that has impact.
                        </p>
                        <div class="table-responsive">
                            <table class="table w-auto table-borderless">
                                <tbody>
                                    <tr>
                                        <td class="text-muted py-1">Categories</td>
                                        <td class="py-1">Electronics, Camera</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Qty</td>
                                        <td class="py-1">21</td>
                                    </tr>
                                    <tr>
                                        <td class="text-muted py-1">Price</td>
                                        <td class="py-1">
                                            <h5 class="mb-0">$399</h5>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="cust-modal" tabindex="-1" aria-labelledby="custModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="custModalLabel">Canvass Items</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Items will be loaded here -->
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let table = $('#suppliersTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: {
                url: 'reports/requisitiontopurchased.php',
                type: 'GET',
                data: function(d) {
                    d.start_date = $('#start_date').val();
                    d.end_date = $('#end_date').val();
                    d.dealer = $('#dealer').val();
                    d.user = $('#user').val();
                }
            },
            columns: [{
                    data: 'pr_number'
                },
                {
                    data: 'pr_date',
                    render: function(data, type, row) {
                        if ((type === 'display' || type === 'filter') && data) {
                            const date = new Date(data);
                            return date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        }
                        return data;
                    }
                },
                {
                    data: 'supplier_name'
                },
                {
                    data: 'payment_terms'
                },
                {
                    data: 'po_number'
                },
                {
                    data: 'po_date',
                    render: function(data, type, row) {
                        if ((type === 'display' || type === 'filter') && data) {
                            const date = new Date(data);
                            return date.toLocaleDateString('en-US', {
                                year: 'numeric',
                                month: 'long',
                                day: 'numeric'
                            });
                        }
                        return data;
                    }
                },
                {
                    data: 'amount_purchased',
                    render: function(data, type, row) {
                        if (type === 'display' || type === 'filter') {
                            return '₱' + parseFloat(data).toLocaleString('en-PH', {
                                minimumFractionDigits: 2,
                                maximumFractionDigits: 2
                            });
                        }
                        return data;
                    }
                },
                {
                    data: 'full_name'
                },
                {
                    data: 'dealer'
                }
            ],
            dom: 'Bfrtip'
        });

        // 🔹 Dealer filter (dropdown) - realtime
        $('#dealer').on('change', function() {
            table.ajax.reload(null, false);
        });

        // 🔹 Date filters (start & end) - reload when changed
        $('#start_date, #end_date').on('change', function() {
            table.ajax.reload(null, false);
        });

        // 🔹 Global search (reload on typing, not only Enter)
        $('#suppliersTable_filter input').unbind().bind('input', function() {
            table.search(this.value).draw();
        });

        // Load users when dealer changes
        $('#dealer').on('change', function() {
            let dealer = $(this).val();

            // Clear user dropdown
            $('#user').empty().append('<option value="">-- All Users --</option>');

            // Fetch users from selected dealer
            $.getJSON('data/get_users.php', {
                dealer: dealer
            }, function(data) {
                $.each(data, function(index, user) {
                    $('#user').append(
                        $('<option>', {
                            value: user.user_id,
                            text: user.full_name
                        })
                    );
                });
            });

            // Reload table with dealer filter
            table.ajax.reload();
        });

        // Reload table when user changes
        $('#user').on('change', function() {
            table.ajax.reload();
        });
        

        // 🔹 Export filtered data
        $('#exportBtn').on('click', function() {
            let params = {
                search: table.search(),
                start_date: $('#start_date').val(),
                end_date: $('#end_date').val(),
                dealer: $('#dealer').val(),
                user: $('#user').val()
            };
            let query = $.param(params);

            $.ajax({
                url: 'export/export_purchase.php?' + query,
                method: 'GET',
                xhrFields: {
                    responseType: 'blob'
                },
                success: function(data, status, xhr) {
                    const contentType = xhr.getResponseHeader('Content-Type');
                    if (contentType && contentType.indexOf('application/json') !== -1) {
                        let reader = new FileReader();
                        reader.onload = function() {
                            let result = JSON.parse(reader.result);
                            alert(result.error);
                        };
                        reader.readAsText(data);
                    } else {
                        let blob = new Blob([data], {
                            type: 'text/csv'
                        });
                        let link = document.createElement('a');
                        link.href = window.URL.createObjectURL(blob);
                        link.download = 'purchase_export.csv';
                        link.click();
                    }
                },
                error: function() {
                    alert('No data found.');
                }
            });
        });
    });

    // $(document).ready(function() {
    //     let table = $('#suppliersTable').DataTable({
    //         processing: true,
    //         serverSide: true,
    //         responsive: true,
    //         ajax: {
    //             url: 'reports/requisitiontopurchased.php',
    //             type: 'GET',
    //             data: function(d) {
    //                 d.start_date = $('#start_date').val();
    //                 d.end_date = $('#end_date').val();
    //                 d.dealer = $('#dealer').val();
    //             }
    //         },
    //         columns: [{
    //                 data: 'pr_number'
    //             },
    //             {
    //                 data: 'pr_date',
    //                 render: function(data, type, row) {
    //                     if ((type === 'display' || type === 'filter') && data) {
    //                         const date = new Date(data);
    //                         return date.toLocaleDateString('en-US', {
    //                             year: 'numeric',
    //                             month: 'long',
    //                             day: 'numeric'
    //                         });
    //                     }
    //                     return data;
    //                 }
    //             },
    //             {
    //                 data: 'supplier_name'
    //             },
    //             {
    //                 data: 'payment_terms'
    //             },
    //             {
    //                 data: 'po_number'
    //             },
    //             {
    //                 data: 'po_date',
    //                 render: function(data, type, row) {
    //                     if ((type === 'display' || type === 'filter') && data) {
    //                         const date = new Date(data);
    //                         return date.toLocaleDateString('en-US', {
    //                             year: 'numeric',
    //                             month: 'long',
    //                             day: 'numeric'
    //                         });
    //                     }
    //                     return data;
    //                 }
    //             },
    //             {
    //                 data: 'amount_purchased',
    //                 render: function(data, type, row) {
    //                     if (type === 'display' || type === 'filter') {
    //                         return '₱' + parseFloat(data).toLocaleString('en-PH', {
    //                             minimumFractionDigits: 2,
    //                             maximumFractionDigits: 2
    //                         });
    //                     }
    //                     return data;
    //                 }
    //             },
    //             {
    //                 data: 'full_name'
    //             },
    //             {
    //                 data: 'dealer'
    //             }
    //         ],
    //         dom: 'Bfrtip'
    //     });

    //     // Reload when filters change
    //     $('#start_date, #end_date, #dealer').on('change', function() {
    //         table.ajax.reload();
    //     });

    //     $('#exportBtn').on('click', function() {
    //         let params = {
    //             search: table.search(),
    //             start_date: $('#start_date').val(),
    //             end_date: $('#end_date').val(),
    //             dealer: $('#dealer').val()
    //         };
    //         let query = $.param(params);

    //         $.ajax({
    //             url: 'export/export_purchase.php?' + query,
    //             method: 'GET',
    //             xhrFields: {
    //                 responseType: 'blob' // so we can handle CSV download
    //             },
    //             success: function(data, status, xhr) {
    //                 // Check if response is JSON error
    //                 const contentType = xhr.getResponseHeader('Content-Type');
    //                 if (contentType && contentType.indexOf('application/json') !== -1) {
    //                     let reader = new FileReader();
    //                     reader.onload = function() {
    //                         let result = JSON.parse(reader.result);
    //                         alert(result.error); // Show alert if no data
    //                     };
    //                     reader.readAsText(data);
    //                 } else {
    //                     // Otherwise, trigger CSV download
    //                     let blob = new Blob([data], {
    //                         type: 'text/csv'
    //                     });
    //                     let link = document.createElement('a');
    //                     link.href = window.URL.createObjectURL(blob);
    //                     link.download = 'purchase_export.csv';
    //                     link.click();
    //                 }
    //             },
    //             error: function() {
    //                 alert('No data found.');
    //             }
    //         });
    //     });

    // });



    // Handle modal view
    $('#suppliersTable').on('click', '.view-btn', function() {
        let items = $(this).data('items');
        $('#cust-modal .modal-body').html(items || "No items found");
    });
</script>

</script>
<!-- <button 
                    class="btn btn-sm btn-outline-primary view-btn"
                    data-sap="${row.vendor_id}" 
                    data-name="${row.supplier_name}"
                    data-tin="${row.tin_number}"
                    data-bs-toggle="modal" 
                    data-bs-target="#cust-modal"
                    title="View Details">
                    <i class="ti ti-eye"></i>
                </button>
                 <a 
                href="edit_supplier.php?vendor_id=${row.vendor_id}" 
                class="btn btn-sm btn-outline-warning"
                title="Edit Supplier">
                <i class="ti ti-pencil"></i>
            </a> -->
<?php
include 'footer.php';
?>