<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>
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
                                <h2 class="mb-0">Users</h2>
                            </div>
                            <div class="btn-page">
                                <a href="add_purchase_requisition" class="btn btn-primary d-inline-flex align-items-center gap-2">
                                    <i class="ti ti-plus f-18"></i> Add User
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
                                    <th style="width: 20.127118644067796%;">Username</th>
                                    <th style="width: 4.872881355932203%;">Name</th>
                                    <th style="width: 4.872881355932203%;">Email</th>
                                    <th style="width: 34.32203389830508%; ">Department</th>
                                    <th style="width: 20.127118644067796%;">Dealer</th>
                                    <th style="width: 20.127118644067796%;">Signature</th>
                                    <th style="width: 20.127118644067796%;">Action</th>
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
    $('#suppliersTable').DataTable({
        processing: true,
        serverSide: true,
        responsive: true,
        ajax: 'admin/get_users.php',
        order: [
            [0, 'desc']
        ],
        columns: [

            {
                data: null, // combine user_profile + username
                orderable: false,
                render: function(data, type, row) {
                    // Check profile image
                    let imgSrc = row.user_pfp && row.user_pfp !== '' ?
                        row.user_pfp :
                        '../assets/images/user/default-avatar.jpg';

                    return `
            <div class="d-flex align-items-center">
                <img src="${imgSrc}" alt="user image" 
                     class="img-radius wid-40 align-top m-r-10" />
                <span>${row.username}</span>
            </div>
        `;
                }
            },
            {
                data: 'full_name',
            },
            {
                data: 'email_address',
            },
            {
                data: 'department',

            },

            {
                data: 'dealer'
            },
            {
            data: 'user_signature', // assuming your PHP returns the profile image filename/path
            orderable: false,
            render: function(data, type, row) {
                // fallback image if none exists
                let imgSrc = data && data !== '' ? data : '../assets/images/user/default-avatar.jpg';
                return `
                    <div class="flex-shrink-0">
                        <img src="${imgSrc}" alt="user image" 
                             class="img-radius wid-40 align-top m-r-15" />
                    </div>
                `;
            }
        },
            {
                data: null,
                className: 'text-center',
                orderable: false,
                render: function(data, type, row) {
                    const encodedUserId = btoa(row.user_id); // Base64 encode in JS

                    return `
                    <ul class="list-inline me-auto mb-0">
                        <li class="list-inline-item" title="View Profile">
                            <a href="user_profile?key=${encodedUserId}" 
                            class="avtar avtar-xs btn-link-success btn-pc-default">
                            <i class="ti ti-eye f-18"></i>
                            </a>
                        </li>
                        <li class="list-inline-item" title="Reset Password">
                            <a href="#" 
                            onclick="resetPassword('${encodedUserId}')"
                            class="avtar avtar-xs btn-link-success btn-pc-default">
                                <i class="ti ti-rotate-clockwise-2 f-18"></i>
                            </a>
                        </li>
                    </ul>
        `;
                }
            }
        ]
    });

    // Function for SweetAlert + AJAX
    function resetPassword(encodedUserId) {
        Swal.fire({
            title: 'Reset Password?',
            text: "This will reset the user's password to default.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, reset it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'admin/reset_password.php',
                    type: 'POST',
                    data: {
                        key: encodedUserId
                    },
                    success: function(response) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Password Reset',
                            text: 'The password has been reset to default.',
                            timer: 2000,
                            showConfirmButton: false
                        });
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Unable to reset password. Please try again.'
                        });
                    }
                });
            }
        });
    }
</script>
<?php
include 'footer.php';
?>