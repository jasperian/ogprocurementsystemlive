<?php
require '../init.php';

include 'header.php';
include 'navigation.php';
?>
<style>
    .password-card {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        box-shadow: 0 12px 28px rgba(16, 24, 40, 0.06);
    }

    .password-rule-list {
        color: #667085;
        font-size: 0.875rem;
        margin: 10px 0 0;
        padding-left: 18px;
    }

    .password-rule-list li.valid {
        color: #16a34a;
    }

    [data-pc-theme="dark"] .password-card {
        background: #1b2230;
        border-color: #2f3a4b;
        color: #e7ecf3;
    }

    [data-pc-theme="dark"] .password-card .card-header {
        background: #1b2230;
        border-color: #2f3a4b;
    }

    [data-pc-theme="dark"] .password-card .text-muted,
    [data-pc-theme="dark"] .password-rule-list {
        color: #a8b3c4 !important;
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
                            <h2 class="mb-0">Change Password</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row justify-content-center">
            <!-- [ sample-page ] start -->
            <div class="col-lg-5 col-md-8">
                <div class="card password-card my-4">
                    <div class="card-header">
                        <h5 class="mb-1">Update Password</h5>
                        <p class="text-muted mb-0">Choose a strong password that you do not use anywhere else.</p>
                    </div>
                    <div class="card-body">
                        <form id="changepasswordForm">
                            <!-- Current Password -->
                            <div class="mb-3 position-relative">
                                <label class="form-label">Current Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="currentPassword" name="currentPassword" placeholder="Current Password" required />
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#currentPassword">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- New Password -->
                            <div class="mb-3 position-relative">
                                <label class="form-label">New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="newPassword" name="newPassword" placeholder="New Password" required />
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#newPassword">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </div>
                                <div class="progress mt-2" style="height: 6px;">
                                    <div id="passwordStrengthBar" class="progress-bar" role="progressbar"></div>
                                </div>
                                <small id="passwordHelp" class="form-text text-muted">
                                    Must be at least 12 characters and include uppercase, lowercase, number, and symbol.
                                </small>
                                <ul class="password-rule-list">
                                    <li data-rule="length">12 or more characters</li>
                                    <li data-rule="upper">Uppercase letter</li>
                                    <li data-rule="lower">Lowercase letter</li>
                                    <li data-rule="number">Number</li>
                                    <li data-rule="symbol">Symbol</li>
                                </ul>
                            </div>

                            <!-- Confirm Password -->
                            <div class="mb-3 position-relative">
                                <label class="form-label">Confirm New Password</label>
                                <div class="input-group">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirmnewPassword" placeholder="Confirm New Password" required />
                                    <button type="button" class="btn btn-outline-secondary toggle-password" data-target="#confirmPassword">
                                        <i class="ti ti-eye"></i>
                                    </button>
                                </div>
                                <small id="confirmHelp" class="form-text text-muted"></small>
                            </div>

                            <div class="d-grid mt-4">
                                <button type="submit" class="btn btn-primary">Change Password</button>
                            </div>
                        </form>
                        <div id="message" class="mt-3"></div>
                    </div>
                </div>
            </div>
            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>

<script>
    $(document).ready(function() {

        // Toggle password visibility
        $('.toggle-password').on('click', function() {
            const target = $($(this).data('target'));
            const icon = $(this).find('i');
            if (target.attr('type') === 'password') {
                target.attr('type', 'text');
                icon.removeClass('ti-eye').addClass('ti-eye-off');
            } else {
                target.attr('type', 'password');
                icon.removeClass('ti-eye-off').addClass('ti-eye');
            }
        });

        // Calculate password strength score
        function calculateStrength(password) {
            let score = 0;
            if (password.length >= 12) score += 20;
            if (/[A-Z]/.test(password)) score += 20;
            if (/[a-z]/.test(password)) score += 20;
            if (/\d/.test(password)) score += 20;
            if (/[^A-Za-z0-9]/.test(password)) score += 20;
            return score;
        }

        function updateRuleList(password) {
            const rules = {
                length: password.length >= 12,
                upper: /[A-Z]/.test(password),
                lower: /[a-z]/.test(password),
                number: /\d/.test(password),
                symbol: /[^A-Za-z0-9]/.test(password)
            };

            Object.keys(rules).forEach(function(rule) {
                $('.password-rule-list [data-rule="' + rule + '"]').toggleClass('valid', rules[rule]);
            });
        }

        // Update password strength bar
        $('#newPassword').on('input', function() {
            const password = $(this).val();
            const strength = calculateStrength(password);
            const bar = $('#passwordStrengthBar');

            bar.removeClass('bg-danger bg-warning bg-success');
            updateRuleList(password);

            if (strength === 0) {
                bar.css('width', '0%');
                $('#passwordHelp').text("Must be at least 12 characters and include uppercase, lowercase, number, and symbol.")
                    .removeClass('text-success text-danger');
            } else if (strength <= 40) {
                bar.css('width', '40%').addClass('bg-danger');
                $('#passwordHelp').text("Weak password.").removeClass('text-success').addClass('text-danger');
            } else if (strength <= 80) {
                bar.css('width', '80%').addClass('bg-warning');
                $('#passwordHelp').text("Medium strength password.").removeClass('text-danger').addClass('text-warning');
            } else {
                bar.css('width', '100%').addClass('bg-success');
                $('#passwordHelp').text("Strong password.").removeClass('text-danger text-warning').addClass('text-success');
            }
        });

        // Confirm password match validation
        $('#confirmPassword').on('input', function() {
            const confirmPass = $(this).val();
            const newPass = $('#newPassword').val();
            if (confirmPass !== newPass) {
                $('#confirmHelp').text("Passwords do not match.").removeClass('text-success').addClass('text-danger');
            } else {
                $('#confirmHelp').text("Passwords match.").removeClass('text-danger').addClass('text-success');
            }
        });

        // Form submission via AJAX
        $('#changepasswordForm').on('submit', function(e) {
            e.preventDefault();

            const newPass = $('#newPassword').val();
            const confirmPass = $('#confirmPassword').val();
            const strength = calculateStrength(newPass);

            if (strength < 80) {
                $('#message').html('<div class="text-danger">Please choose a stronger password.</div>');
                return;
            }
            if (newPass !== confirmPass) {
                $('#message').html('<div class="text-danger">Passwords do not match.</div>');
                return;
            }

            $.ajax({
                url: 'data/update_user_password.php',
                type: 'POST',
                data: $(this).serialize(),
                dataType: 'json',
                beforeSend: function() {
                    $('#message').html('<div class="text-info">Processing...</div>');
                },
                success: function(response) {
                    if (response.status === 'success') {
                        $('#message').html('<div class="text-success">' + response.message + '</div>');
                        $('#changepasswordForm')[0].reset();
                        $('#passwordStrengthBar').css('width', '0%').removeClass('bg-danger bg-warning bg-success');
                        $('#passwordHelp, #confirmHelp').text('').removeClass('text-danger text-success');
                        updateRuleList('');
                    } else {
                        $('#message').html('<div class="text-danger">' + response.message + '</div>');
                    }
                },
                error: function() {
                    $('#message').html('<div class="text-danger">An error occurred. Please try again.</div>');
                }
            });
        });
    });
</script>



<?php
include 'footer.php';
?>
