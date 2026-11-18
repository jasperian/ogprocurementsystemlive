<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';


$vendorid = $_GET['vendor_id'] ?? '';
if (!$vendorid) {
    die('Vendor Number missing.');
}

$stmt = $pdo->prepare("SELECT is_rejected FROM suppliers WHERE vendor_id = ?");
$stmt->execute([$vendorid]);
$vendorStatus = $stmt->fetchColumn();
// Fetch signature data

$sql = "SELECT 
    sa.vendor_id,
    sa.checked_1,
    sa.checked_2,
    sa.checked_3,
    sa.assigned_dealer,

    u1.full_name AS approver_1_name,
    u1.position AS approver_1_position,
    u1.user_signature AS approver_1_signature,
    u1.department AS approver_1_department,

    u2.full_name AS approver_2_name,
    u2.position AS approver_2_position,
    u2.user_signature AS approver_2_signature,
    u2.department AS approver_2_department,

    u3.full_name AS approver_3_name,
    u3.position AS approver_3_position,
    u3.user_signature AS approver_3_signature,
    u3.department AS approver_3_department

FROM supplier_approvals sa
LEFT JOIN users u1 ON sa.approver_1 = u1.user_id
LEFT JOIN users u2 ON sa.approver_2 = u2.user_id
LEFT JOIN users u3 ON sa.approver_3 = u3.user_id
WHERE sa.vendor_id = :vendor_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':vendor_id', $_GET['vendor_id']);
$stmt->execute();
$signature = $stmt->fetch(PDO::FETCH_ASSOC);

// $assignedDealer = $signature['assigned_dealer'] ?? '';
// if ($assignedDealer === 'TMR' || $assignedDealer === 'TSJ') {
//     $customOrder = [1, 2, 6, 3, 4, 5]; // your manual order
// } else {
//     $customOrder = [1, 2, 3, 4, 5]; // default order

// }

$customOrder = [1, 2, 3]; // your manual order
$signatories = [];

foreach ($customOrder as $index => $i) {
    $nameKey = "approver_{$i}_name";
    $positionKey = "approver_{$i}_position";
    $signatureKey = "approver_{$i}_signature";
    $deptKey = "approver_{$i}_department";
    $checkedKey = "checked_{$i}";
    $dateKey = "checked_{$i}";

    // Get previous approver's checked value (only if this is not the first in order)
    $prevChecked = null;
    if ($index > 0) {
        $prevI = $customOrder[$index - 1];
        $prevCheckedKey = "checked_{$prevI}";
        $prevChecked = $signature[$prevCheckedKey] ?? null;
    }

    if (!empty($signature[$nameKey])) {
        $signatories[] = [
            'index' => $i,
            'label' => 'Approved By',
            'name' => $signature[$nameKey],
            'signature' => !empty($signature[$checkedKey]) ? $signature[$signatureKey] : '',
            'position' => $signature[$positionKey] ?? '',
            'date' => $signature[$dateKey] ?? '',
            'assigned_department' => $signature[$deptKey] ?? '',
            'can_approve' => empty($signature[$checkedKey]) &&
                isset($_SESSION['position'], $_SESSION['department']) &&
                $_SESSION['department'] === $signature[$deptKey] &&
                $_SESSION['position'] === $signature[$positionKey] &&
                ($index === 0 || !empty($prevChecked)) // Only allow if first approver or previous is checked
        ];
    }
}

?>
<!-- [ Main Content ] start -->
<style>
    .pdf-preview {
        width: 100%;
        height: 500px;
        margin-top: 20px;
        border: 1px solid #ccc;
        display: none;
    }

    .spinner {
        display: none;
        margin-top: 20px;
        text-align: center;
    }

    .spinner div {
        width: 40px;
        height: 40px;
        margin: auto;
        border: 4px solid #ccc;
        border-top: 4px solid #007bff;
        border-radius: 50%;
        animation: spin 1s linear infinite;
    }

    @keyframes spin {
        to {
            transform: rotate(360deg);
        }
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.4.120/pdf.min.js"></script>

<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Supplier Profile</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card shadow">
                    <div class="card-body">
                        <form id="vendorForm" enctype="multipart/form-data">
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label class="form-label">Business Type</label>
                                    <input type="text" class="form-control" name="businessType" placeholder="Business Type" disabled />

                                </div>
                                <div class="mb-3 col-md-6">
                                    <label class="form-label">Product Category</label>
                                    <input type="text" class="form-control" name="productCategory" placeholder="Product Category" disabled />
                                </div>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-6">
                                    <label class="form-label">Vendor Number</label>
                                    <input type="text" class="form-control" name="vendorNumber" placeholder="Vendor Number" disabled />
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label class="form-label">Vendor Name</label>
                                    <input type="text" class="form-control" name="vendorName" placeholder="Vendor Name" disabled />
                                    <input type="text" hidden id="vendorId" name="vendorId" value="<?= htmlspecialchars($_GET['vendor_id'] ?? '') ?>" />
                                </div>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Contact Person</label>
                                    <input type="text" class="form-control" name="contactPerson" placeholder="Contact Person" disabled />
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Contact Number</label>
                                    <input type="text" class="form-control" name="contactNumber" placeholder="Contact Number" disabled />
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Mobile Number</label>
                                    <input type="text" class="form-control" name="mobileNumber" placeholder="Mobile Number" disabled />
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Email</label>
                                    <input type="text" class="form-control" name="email" placeholder="Email" disabled />
                                </div>

                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">TIN Number</label>
                                    <input type="text" class="form-control" name="tinNumber" placeholder="TIN Number" disabled />
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Terms of Payment</label>
                                    <input type="text" class="form-control" name="termsPayment" placeholder="Terms of Payment" disabled />
                                </div>
                                <div class="mb-3 col-md-6">
                                    <label class="form-label">Address</label>
                                    <input type="text" class="form-control" name="vendorAddress" placeholder="Address" disabled />
                                </div>
                            </div>
                            <div class="row">
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Created By</label>
                                    <input type="text" class="form-control" name="createdBy" placeholder="Created By" disabled />
                                </div>
                                <div class="mb-3 col-md-3">
                                    <label class="form-label">Dealer</label>
                                    <input type="text" class="form-control" name="dealer" placeholder="Dealer" disabled />
                                </div>
                            </div>
                            <div class="text-end" hidden>
                                <button class="btn btn-outline-secondary" type="button" onclick="window.location.href='supplier_approvals'">Cancel</button> <button type="submit" class="btn btn-success">Approve</button>
                            </div>



                            <div id="responseMessage" class="mt-3"></div>
                        </form>

                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <!-- Vendor Files Container -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Vendor Files</h5>
                    </div>
                    <div class="card-body">
                        <div id="vendorFilesContainer" class="d-flex flex-wrap gap-2"></div>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Approvers</h5>
                    </div>
                    <div class="card-body">
                        <div class="row justify-content-center my-5 gap-4">
                            <!-- Signatories -->
                            <?php foreach ($signatories as $signatory): ?>
                                <?php
                                $canApprove = $signatory['can_approve'];
                                $i = $signatory['index']; // This is 2, 1, 6, etc.
                                $batchId = $_GET['vendor_id'] ?? '';
                                $vendorName = $_GET['vendor_name'] ?? '';
                                $dealer = $_GET['dealer'] ?? '';
                                $position = $_SESSION['position'] ?? '';
                                ?>

                                <div class="text-center position-relative" style="max-width: 200px; margin: 30px auto;">
                                    <p class="mb-1 fw-semibold"><?= htmlspecialchars($signatory['label']) ?>:</p>

                                    <div style="position: relative; height: 40px;">
                                        <!-- Signature line -->
                                        <div style="border-bottom: 1px solid #000; position: absolute; top: 50%; left: 0; right: 0; z-index: 1;"></div>

                                        <?php if (!empty($signatory['signature']) && file_exists($signatory['signature'])): ?>
                                            <img src="<?= htmlspecialchars($signatory['signature']) ?>" alt="Signature"
                                                class="user-avtar wid-45 rounded-circle"
                                                style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 2; width: 100px" ondragstart="return false;" oncontextmenu="return false;" />
                                        <?php endif; ?>
                                    </div>

                                    <p class="mb-1 fw-semibold"><?= htmlspecialchars($signatory['name']) ?></p>
                                    <p class="small text-muted"><?= htmlspecialchars($signatory['position']) ?></p>

                                    <?php if (!empty($signatory['date'])): ?>
                                        <p class="small text-muted"><?= date("F j, Y, g:i A", strtotime($signatory['date'])) ?></p>
                                    <?php endif; ?>

                                    <?php if ($canApprove && $vendorStatus == 0): ?>
                                        <button class="btn btn-success btn-sm mt-2 submit-btn"
                                            data-position="<?= htmlspecialchars($position) ?>"
                                            data-batch="<?= htmlspecialchars($batchId) ?>"
                                            data-vendor-name="<?= htmlspecialchars($vendorName) ?>"
                                            data-dealer="<?= htmlspecialchars($dealer) ?>"
                                            data-name="<?= htmlspecialchars($signatory['name']) ?>"
                                            data-index="<?= $i ?>"> <!-- ✅ use the real DB index -->
                                            Approve
                                        </button>
                                        <button class="btn btn-danger btn-sm mt-2 disapprove-btn"
                                            data-position="<?= htmlspecialchars($position) ?>"
                                            data-batch="<?= htmlspecialchars($batchId) ?>"
                                            data-vendor-name="<?= htmlspecialchars($vendorName) ?>"
                                            data-dealer="<?= htmlspecialchars($dealer) ?>"
                                            data-name="<?= htmlspecialchars($signatory['name']) ?>"
                                            data-vendorid="<?= htmlspecialchars($vendorid) ?>"
                                            data-index="<?= $i ?>"> <!-- ✅ use the real DB index -->

                                            Disapprove
                                        </button>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>

                        </div>
                    </div>

                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>

        <!-- [ Main Content ] end -->
    </div>
</div>
<!-- PDF Viewer Modal -->
<div class="modal fade" id="pdfModal" tabindex="-1">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">PDF Viewer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <canvas id="pdfCanvas"></canvas>
                <p>Page <span id="pageNum">1</span> of <span id="pageCount">1</span></p>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button class="btn btn-secondary" id="prevPage">Previous</button>
                    <button class="btn btn-secondary" id="nextPage">Next</button>
                </div>
                <div>
                    <button class="btn btn-secondary" id="zoomOut">-</button>
                    <button class="btn btn-secondary" id="zoomIn">+</button>
                </div>
            </div>
        </div>
    </div>
</div>


<script>
    $(document).ready(function() {
        // Get vendor_id from URL
        const urlParams = new URLSearchParams(window.location.search);
        const vendorId = urlParams.get('vendor_id');

        if (vendorId) {
            $.ajax({
                url: 'data/supplier_view_data.php',
                method: 'GET',
                data: {
                    vendor_id: vendorId
                },
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        const data = response.data;
                        // Fill form fields
                        $('input[name="vendorNumber"]').val(data.vendor_id);
                        $('input[name="vendorName"]').val(data.supplier_name);
                        $('input[name="contactPerson"]').val(data.contact_person);
                        $('input[name="contactNumber"]').val(data.contact_number);
                        $('input[name="email"]').val(data.email);
                        $('input[name="mobileNumber"]').val(data.mobile_number);
                        $('input[name="tinNumber"]').val(data.tin_number);
                        $('input[name="termsPayment"]').val(data.payment_terms);
                        $('input[name="vendorAddress"]').val(data.street);
                        $('input[name="createdBy"]').val(data.created_by);
                        $('input[name="dealer"]').val(data.dealer);
                        $('input[name="businessType"]').val(data.business_type);
                        $('input[name="productCategory"]').val(data.product_category);


                        // Optional: You could also preview PDF if saved path exists
                        if (data.vendor_pdf) {
                            viewUploadedPDF('data/', data.vendor_pdf); // This will trigger the modal viewer
                        }
                    } else {
                        $('#responseMessage').html('<div class="alert alert-danger">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#responseMessage').html('<div class="alert alert-danger">Failed to fetch vendor data</div>');
                }
            });
        }
    });

    // $('#vendorForm').submit(function(e) {
    //     e.preventDefault(); // Prevent default form submission

    //     Swal.fire({
    //         title: "Do you want to save the changes?",
    //         showDenyButton: true,
    //         showCancelButton: true,
    //         confirmButtonText: "Save",
    //         denyButtonText: `Don't save`
    //     }).then((result) => {
    //         if (result.isConfirmed) {
    //             const formData = new FormData(this);
    //             $('#loadingSpinner').show();

    //             $.ajax({
    //                 url: 'data/supplier_approved.php',
    //                 method: 'POST',
    //                 data: formData,
    //                 contentType: false,
    //                 processData: false,
    //                 dataType: 'json',
    //                 success: function(response) {
    //                     $('#loadingSpinner').hide();

    //                     if (response.status === 'success') {
    //                         Swal.fire({
    //                             icon: 'success',
    //                             title: 'Saved!',
    //                             text: response.message
    //                         }).then(() => {
    //                             window.location.href = 'suppliers';
    //                         });
    //                     } else {
    //                         Swal.fire({
    //                             icon: 'error',
    //                             title: 'Error',
    //                             text: response.message
    //                         });
    //                     }
    //                 },
    //                 error: function() {
    //                     $('#loadingSpinner').hide();
    //                     Swal.fire({
    //                         icon: 'error',
    //                         title: 'Submission Failed',
    //                         text: 'Something went wrong. Please try again.'
    //                     });
    //                 }
    //             });

    //         } else if (result.isDenied) {
    //             Swal.fire("Changes are not saved", "", "info");
    //         }
    //         // If canceled, do nothing
    //     });
    // });
    $(document).on('click', '.submit-btn', function() {
        const button = $(this);
        const batchId = button.data('batch');
        const vendorName = button.data('vendorName');
        const dealer = button.data('dealer');
        const position = button.data('position');
        const index = button.data('index');
        const name = button.data('name');

        Swal.fire({
            title: 'Are you sure?',
            text: "Do you want to approve this entry?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, approve it!'
        }).then((result) => {
            if (result.isConfirmed) {

                // Show loading **before** sending AJAX
                Swal.fire({
                    title: 'Processing...',
                    text: 'Please wait while we send notifications and emails.',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: 'data/submit_signatory_supplier.php',
                    method: 'POST',
                    dataType: 'json',
                    data: {
                        batch_id: batchId,
                        position: position,
                        index: index,
                        name: name,
                        vendorName: vendorName,
                        dealer: dealer
                    },
                    success: function(response) {
                        if (response.status === 'success') {
                            Swal.fire({
                                icon: 'success',
                                title: 'Approved!',
                                text: 'The supplier has been successfully approved.',
                                timer: 1000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: response.message || 'Something went wrong.'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.log("AJAX error details:");
                        console.log("Status:", status);
                        console.log("Error:", error);
                        console.log("Response Text:", xhr.responseText);
                        Swal.fire({
                            icon: 'error',
                            title: 'AJAX Error',
                            text: error
                        });
                    }
                });
            }
        });
    });


document.querySelectorAll('.disapprove-btn').forEach(button => {
            button.addEventListener('click', function() {
                const batch = this.dataset.batch;
                const position = this.dataset.position;
                const name = this.dataset.name;
                const index = this.dataset.index;
                const vendorid = this.dataset.vendorid;

                // Step 1: Ask for remarks
                Swal.fire({
                    title: 'Remarks Required',
                    text: `Please provide the reason for disapproval.`,
                    icon: 'warning',
                    input: 'textarea',
                    inputPlaceholder: 'Enter your remarks here...',
                    inputAttributes: {
                        'aria-label': 'Enter your remarks here'
                    },
                    showCancelButton: true,
                    confirmButtonText: 'Next',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    inputValidator: (value) => {
                        if (!value) {
                            return 'Remarks are required before proceeding.';
                        }
                    }
                }).then(result => {
                    if (result.isConfirmed) {
                        const remarks = result.value;

                        // Step 2: Confirm submission with remarks
                        Swal.fire({
                            title: 'Confirm Disapproval',
                            html: `
                                    <p>You are about to submit a disapproval.</p>
                                    <p><b>Your remarks:</b></p>
                                    <div style="text-align:left; border:1px solid #ccc; padding:8px; border-radius:5px; background:#f8f9fa;">
                                        ${remarks}
                                    </div>
                                    <p>Do you wish to proceed?</p>
                                `,
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonText: 'Yes, submit it!',
                            cancelButtonText: 'Cancel',
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33'
                        }).then(confirmResult => {
                            if (confirmResult.isConfirmed) {
                                fetch('data/submit_disapproved_supplier.php', {
                                        method: 'POST',
                                        headers: {
                                            'Content-Type': 'application/x-www-form-urlencoded'
                                        },
                                        body: new URLSearchParams({
                                            batch: batch,
                                            position: position,
                                            name: name,
                                            index: index,
                                            remarks: remarks,
                                            vendorid: vendorid
                                        })
                                    })
                                    .then(response => response.json())
                                    .then(data => {
                                        if (data.status === 'success') {
                                            Swal.fire({
                                                icon: 'success',
                                                title: 'Submitted!',
                                                text: 'Your disapproval was submitted successfully!',
                                                timer: 1500,
                                                showConfirmButton: false
                                            }).then(() => {
                                                location.reload();
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Error',
                                                text: data.message || 'Something went wrong.'
                                            });
                                        }
                                    })
                                    .catch(err => {
                                        console.error('AJAX error:', err);
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'AJAX Error',
                                            text: 'An unexpected error occurred.'
                                        });
                                    });
                            }
                        });
                    }
                });
            });
        });
    // $(document).on('click', '.submit-btn', function() {
    //     const button = $(this);
    //     const batchId = button.data('batch');
    //     const vendorName = button.data('vendorName');
    //     const dealer = button.data('dealer');
    //     const position = button.data('position');
    //     const index = button.data('index');
    //     const name = button.data('name');

    //     Swal.fire({
    //         title: 'Are you sure?',
    //         text: "Do you want to approve this entry?",
    //         icon: 'question',
    //         showCancelButton: true,
    //         confirmButtonColor: '#3085d6',
    //         cancelButtonColor: '#d33',
    //         confirmButtonText: 'Yes, approve it!'
    //     }).then((result) => {
    //         if (result.isConfirmed) {
    //             $.ajax({
    //                 url: 'data/submit_signatory_supplier.php',
    //                 method: 'POST',
    //                 dataType: 'json',
    //                 data: {
    //                     batch_id: batchId,
    //                     position: position,
    //                     index: index,
    //                     name: name,
    //                     vendorName: vendorName,
    //                     dealer: dealer
    //                 },
    //                 success: function(response) {
    //                     if (response.status === 'success') {
    //                         Swal.fire({
    //                             icon: 'success',
    //                             title: 'Approved!',
    //                             text: 'The supplier has been successfully approved.',
    //                             timer: 1000,
    //                             showConfirmButton: false
    //                         }).then(() => {
    //                             location.reload();
    //                         });
    //                     } else {
    //                         Swal.fire({
    //                             icon: 'error',
    //                             title: 'Error!',
    //                             text: response.message || 'Something went wrong.'
    //                         });
    //                     }
    //                 },
    //                 error: function(xhr, status, error) {
    //                     console.log("AJAX error details:");
    //                     console.log("Status:", status);
    //                     console.log("Error:", error);
    //                     console.log("Response Text:", xhr.responseText);
    //                     Swal.fire({
    //                         icon: 'error',
    //                         title: 'AJAX Error',
    //                         text: error
    //                     });
    //                 }
    //             });
    //         }
    //     });
    // });

    const documentRequirements = {
        "Small Business": ["DTI", "Brgy. Permit", "BIR"],
        "Sole Proprietorship": ["DTI", "Brgy. Permit", "BIR or 2303", "Mayor's Permit"],
        "Micro": ["Brgy. Permit", "Mayor's Permit", "BIR"],
        "Corporate": [
            "Company Profile", "List of Products and Services", "Business Permit",
            "Certificate of Registration (2303)", "Audited Financial Statement",
            "Organizational Chart", "Certificate (Authority to Sell / Distributor)",
            "GIS (General Information Sheet)", "Mayor's Permit", "SEC certificate"
        ]
    };

    const documentFieldMap = {
        "DTI": "dti",
        "Brgy. Permit": "brgy_permit",
        "BIR": "bir_cert",
        "BIR or 2303": "bir_cert",
        "Mayor's Permit": "mayors_permit",
        "Company Profile": "company_profile",
        "List of Products and Services": "product_services",
        "Business Permit": "business_permit",
        "Certificate of Registration (2303)": "bir_cert",
        "Audited Financial Statement": "fin_statement",
        "Organizational Chart": "org_chart",
        "Certificate (Authority to Sell / Distributor)": "authorize_distributor",
        "GIS (General Information Sheet)": "gen_info",
        "SEC certificate": "sec_cert"
    };

    // PDF viewer globals
    let pdfDoc = null;
    let currentPage = 1;
    let scale = 1.5;
    let fileURL = '';
    const canvas = document.getElementById('pdfCanvas');
    const ctx = canvas.getContext('2d');
    const pageNumSpan = document.getElementById('pageNum');
    const pageCountSpan = document.getElementById('pageCount');

    // Render selected page
    function renderPage(num) {
        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({
                scale: scale
            });
            canvas.width = viewport.width;
            canvas.height = viewport.height;
            page.render({
                canvasContext: ctx,
                viewport: viewport
            });

            pageNumSpan.textContent = num;
            pageCountSpan.textContent = pdfDoc.numPages;
        });
    }

    // Button controls
    document.getElementById('zoomIn').addEventListener('click', () => {
        scale += 0.2;
        renderPage(currentPage);
    });

    document.getElementById('zoomOut').addEventListener('click', () => {
        if (scale > 0.4) {
            scale -= 0.2;
            renderPage(currentPage);
        }
    });

    document.getElementById('prevPage').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            renderPage(currentPage);
        }
    });

    document.getElementById('nextPage').addEventListener('click', () => {
        if (currentPage < pdfDoc.numPages) {
            currentPage++;
            renderPage(currentPage);
        }
    });

    // Show modal and render PDF
    function viewUploadedPDF(path) {
        fileURL = path;

        pdfjsLib.getDocument(fileURL).promise.then(pdf => {
            pdfDoc = pdf;
            currentPage = 1;
            scale = 1.5;
            renderPage(currentPage);

            const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
            modal.show();
        }).catch(err => {
            console.error("PDF load error:", err);
            // alert("Failed to load PDF.");
        });
    }

    // Load vendor files dynamically
    function loadVendorFiles(vendorId) {
        const container = document.getElementById('vendorFilesContainer');
        container.innerHTML = '';

        if (!vendorId) {
            container.innerHTML = `<p class="text-danger">Vendor ID is missing.</p>`;
            return;
        }

        fetch(`data/get_supplier_files.php?vendor_id=${vendorId}`)
            .then(res => res.json())
            .then(response => {
                if (response.status === 'success') {
                    const files = response.files;
                    const businessType = response.business_type;

                    if (!businessType || !documentRequirements[businessType]) {
                        container.innerHTML = `<p class="text-warning">Unsupported business type: ${businessType}</p>`;
                        return;
                    }

                    documentRequirements[businessType].forEach(docLabel => {
                        const field = documentFieldMap[docLabel];
                        const filePath = files[field];

                        if (filePath) {
                            const btn = document.createElement('button');
                            btn.className = 'btn btn-outline-primary me-2 mb-2';
                            btn.type = 'button';
                            btn.innerHTML = `<i class="ti ti-eye me-1"></i> View ${docLabel}`;
                            btn.onclick = () => viewUploadedPDF(filePath);
                            container.appendChild(btn);
                        }
                    });
                } else {
                    container.innerHTML = `<p class="text-danger">${response.message}</p>`;
                }
            })
            .catch(error => {
                console.error("Fetch error:", error);
                container.innerHTML = `<p class="text-danger">Failed to load vendor files.</p>`;
            });
    }

    // Auto-load files on page load
    document.addEventListener('DOMContentLoaded', () => {
        const vendorId = document.getElementById('vendorId')?.value;
        if (vendorId) {
            loadVendorFiles(vendorId);
        }
    });
</script>





<?php
include 'footer.php';
?>