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
                                <h2 class="mb-0">Supplier Renewal List</h2>
                            </div>
                            <div class="btn-page">
                                <a href="add_supplier" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                    <i class="ti ti-plus f-18"></i> Add Supplier
                                </a>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow table-card">
                    <div class="card-body mt-3">


                        <table class="table-stripped display responsive nowrap table-striped p-1" id="suppliersTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th style="width: 4.872881355932203%;">Vendor Number</th>
                                    <th style="width: 34.32203389830508%;">Vendor Name</th>
                                    <th style="width: 20.127118644067796%;">Accreditation Date</th>
                                    <th style="width: 20.127118644067796%;">Remaining Days</th>
                                    <th style="width: 40.67796610169492%;">Action</th>
                                    <!-- <th class="text-end" style="width: 7.203389830508475%;">PRICE</th>
                                    <th class="text-end" style="width: 5.508474576271186%;">QTY</th>
                                    <th style="width: 12.182203389830509%;">STATUS</th>
                                    <th class="text-center" style="width: 15.783898305084746%;">ACTIONS</th> -->
                                </tr>
                            </thead>
                            <!-- Data will be populated here by DataTables -->
                        </table>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
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
<!-- Scripts -->
<script>
    $(document).on('click', '.view-btn', function() {
        const sapId = $(this).data('sap');
        const name = $(this).data('name');
        const tin = $(this).data('tin');

        // You can update your modal fields here
        $('#cust-modal h5').text(name);
        $('#cust-modal .modal-body').html(`
            <p><strong>SAP ID:</strong> ${sapId}</p>
            <p><strong>Vendor Name:</strong> ${name}</p>
            <p><strong>TIN Number:</strong> ${tin}</p>
        `);
    });
</script>

<script>
    $(document).ready(function() {
        $('#suppliersTable').DataTable({
            processing: true,
            serverSide: true,
            responsive: true,
            ajax: 'data/get_supplier_renewal.php',
            columns: [{
                    data: 'vendor_id'
                },
                {
                    data: 'supplier_name'
                },
                {
                    data: 'accreditation_date'
                },
                {
                    data: 'days_remaining',
                    render: function(data, type, row) {
                        let days = parseInt(data, 10);
                        if (isNaN(days)) return 'N/A';
                        if (days < 0) {
                            return `Expired ${Math.abs(days)} day(s) ago`;
                        } else if (days === 0) {
                            return 'Expires today';
                        } else {
                            return `${days} day(s) remaining`;
                        }
                    }
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
                                data-sap="${row.vendor_id}" 
                                data-name="${row.supplier_name}"
                                data-tin="${row.tin_number}" data-bs-toggle="modal" data-bs-target="#cust-modal"><i class="ti ti-eye f-18"></i></a>
                            </li>
                            <li class="list-inline-item align-bottom" data-bs-toggle="tooltip" title="Edit">
                                <a href="view_supplier?vendor_id=${row.vendor_id}" class="avtar avtar-xs btn-link-success btn-pc-default"><i class="ti ti-edit-circle f-18"></i></a>
                            </li>
                        </ul>
                        `;
                    }
                }
            ]
        });
    });
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