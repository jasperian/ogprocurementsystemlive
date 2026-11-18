<?php
require '../init.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);


include 'header.php';
include 'navigation.php';
?>

<style>
    /* Target Select2 placeholder text */
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        font-size: 0.85rem;
        /* Smaller than normal */
        color: #bec8d0;
        /* Bootstrap's secondary text */
    }
</style>
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Add New Budget</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <div class="col-sm-12">
                <div class="card shadow">
                    <div class="card-body">
                        <!-- Alert messages -->
                        <div id="alertContainer"></div>

                        <form id="budgetForm" class="needs-validation" novalidate>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="budgetCode" class="form-label">Budget Code</label>
                                    <input type="text" class="form-control" id="budgetCode" name="budget_code" placeholder="e.g. BC-2025-001" required>
                                    <div class="invalid-feedback">Please provide a budget code.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="budgetDepartment" class="form-label">Department</label>
                                    <select class="form-select" id="budgetDepartment" name="budget_department" required>
                                        <option value="" selected disabled>Select Department</option>
                                                <option value="CRD">CRD</option>
                                                <option value="HR">HR</option>
                                                <option value="ADMIN">ADMIN</option>
                                                <option value="ACCOUNTING">ACCOUNTING</option>
                                                <option value="FINANCING AND INSURANCE">FINANCING AND INSURANCE</option>
                                                <option value="MIS">MIS</option>
                                                <option value="VSD">VSD</option>
                                                <option value="PURCHASING">PURCHASING</option>
                                                <option value="PARTS">PARTS</option>
                                                <option value="AFTER SALES">AFTER SALES</option>
                                                <option value="VSPA">VSPA</option>
                                                <option value="TSURE">TSURE</option>
                                                <option value="AUTOQUIX">AUTOQUIX</option>
                                                <option value="HINO">HINO</option>
                                                <option value="PFFC">PFFC</option>
                                    </select>
                                    <div class="invalid-feedback">Please select a department.</div>
                                </div>

                                <div class="col-6">
                                    <label for="budgetActivity" class="form-label">Budget Activity</label>
                                    <textarea class="form-control" id="budgetActivity" name="budget_activity" rows="1" placeholder="Describe the activity or purpose" required></textarea>
                                    <div class="invalid-feedback">Please describe the budget activity.</div>
                                </div>

                                <div class="col-md-6">
                                    <label for="approvedBudget" class="form-label">Approved Budget</label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" class="form-control" id="approvedBudget" name="approved_budget" placeholder="0.00" step="0.01" min="0" required>
                                    </div>
                                    <div class="form-text">Enter the approved amount.</div>
                                    <div class="invalid-feedback">Please enter an approved budget (zero or more).</div>
                                </div>

                                <div class="col-md-12 d-flex justify-content-end mt-4">
                                    <div>
                                        <button type="reset" class="btn btn-outline-secondary me-2">Reset</button>
                                        <button type="submit" class="btn btn-primary">Save</button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>  
    <script>
        (function() {
            'use strict';
            const form = document.querySelector('#budgetForm');

            form.addEventListener('submit', function(event) {
                event.preventDefault();
                event.stopPropagation();

                if (form.checkValidity()) {
                    const fd = new FormData(form);

                    fetch('data/create_new_budget_code.php', {
                            method: 'POST',
                            body: fd
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Saved!',
                                    text: 'Budget record has been saved successfully.',
                                    showConfirmButton: false,
                                    timer: 2000
                                });

                                form.reset();
                                form.classList.remove('was-validated');
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: data.message || 'Failed to save budget.',
                                    confirmButtonColor: '#d33'
                                });
                            }
                        })
                        .catch(err => {
                            console.error('Error:', err);
                            Swal.fire({
                                icon: 'error',
                                title: 'Unexpected Error',
                                text: 'An error occurred while saving. Please try again later.',
                                confirmButtonColor: '#d33'
                            });
                        });
                }

                form.classList.add('was-validated');
            }, false);
        })();
    </script>




    <?php
    include 'footer.php';
    ?>