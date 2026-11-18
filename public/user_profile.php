<?php
require '../init.php';
include 'header.php';
include 'navigation.php';

$profileKey = isset($_GET['key']) ? $_GET['key'] : base64_encode((string)$_SESSION['user_id']);
$activeProfileTab = (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'settings' : 'profile';
?>

<style>
    .account-hero {
        background: #ffffff;
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        box-shadow: 0 12px 28px rgba(16, 24, 40, 0.06);
        padding: 22px;
    }

    .account-avatar {
        border: 4px solid #ffffff;
        box-shadow: 0 10px 24px rgba(16, 24, 40, 0.18);
        height: 96px;
        object-fit: cover;
        width: 96px;
    }

    .account-meta {
        color: #667085;
        font-size: 0.875rem;
    }

    .settings-card {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        box-shadow: 0 10px 24px rgba(16, 24, 40, 0.04);
    }

    .settings-card .card-header {
        background: #ffffff;
        border-bottom: 1px solid #eef1f5;
    }

    .readonly-field {
        background: #f8fafc;
        color: #475467;
    }

    .profile-upload-preview {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        height: 72px;
        object-fit: cover;
        width: 72px;
    }

    .signature-upload-preview {
        background-color: #ffffff;
        background-image:
            linear-gradient(45deg, #eef1f5 25%, transparent 25%),
            linear-gradient(-45deg, #eef1f5 25%, transparent 25%),
            linear-gradient(45deg, transparent 75%, #eef1f5 75%),
            linear-gradient(-45deg, transparent 75%, #eef1f5 75%);
        background-position: 0 0, 0 8px, 8px -8px, -8px 0;
        background-size: 16px 16px;
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        height: 120px;
        object-fit: contain;
        padding: 10px;
        width: 100%;
    }

    .security-item {
        border: 1px solid #eef1f5;
        border-radius: 8px;
        padding: 14px;
    }

    [data-pc-theme="dark"] .account-hero,
    [data-pc-theme="dark"] .settings-card {
        background: #1b2230;
        border-color: #2f3a4b;
        color: #e7ecf3;
    }

    [data-pc-theme="dark"] .settings-card .card-header {
        background: #1b2230;
        border-color: #2f3a4b;
    }

    [data-pc-theme="dark"] .account-meta,
    [data-pc-theme="dark"] .settings-card .text-muted {
        color: #a8b3c4 !important;
    }

    [data-pc-theme="dark"] .readonly-field,
    [data-pc-theme="dark"] .security-item,
    [data-pc-theme="dark"] .profile-upload-preview {
        background: #151a23;
        border-color: #2f3a4b;
        color: #d8dee9;
    }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Account Settings</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="profileAlert"></div>

        <div class="account-hero mb-4">
            <div class="d-flex flex-column flex-md-row align-items-md-center gap-3">
                <img id="profileAvatar" src="assets/images/pfp/Default_pfp.png" alt="Profile photo" class="account-avatar rounded-circle">
                <div class="flex-grow-1">
                    <h3 id="profileName" class="mb-1">Loading...</h3>
                    <div class="account-meta">
                        <span id="profilePosition">Position</span>
                        <span class="mx-2">|</span>
                        <span id="profileDepartment">Department</span>
                        <span class="mx-2">|</span>
                        <span id="profileDealer">Dealer</span>
                    </div>
                </div>
                <div class="d-flex gap-2">
                    <a href="user_change_password" class="btn btn-primary">
                        <i class="ti ti-lock me-1"></i> Change Password
                    </a>
                    <a href="dashboard" class="btn btn-light">
                        <i class="ti ti-arrow-left me-1"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-7">
                <div class="card settings-card">
                    <div class="card-header">
                        <ul class="nav nav-tabs card-header-tabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeProfileTab === 'profile' ? 'active' : ''; ?>" id="profile-details-tab" data-bs-toggle="tab" data-bs-target="#profile-details-pane" type="button" role="tab" aria-controls="profile-details-pane" aria-selected="<?php echo $activeProfileTab === 'profile' ? 'true' : 'false'; ?>">
                                    <i class="ti ti-user me-1"></i> Profile Details
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link <?php echo $activeProfileTab === 'settings' ? 'active' : ''; ?>" id="profile-settings-tab" data-bs-toggle="tab" data-bs-target="#profile-settings-pane" type="button" role="tab" aria-controls="profile-settings-pane" aria-selected="<?php echo $activeProfileTab === 'settings' ? 'true' : 'false'; ?>">
                                    <i class="ti ti-settings me-1"></i> Settings
                                </button>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <form id="profileForm" enctype="multipart/form-data">
                            <input type="hidden" name="user_id" value="<?php echo htmlspecialchars($profileKey); ?>">

                            <div class="tab-content">
                                <div class="tab-pane fade <?php echo $activeProfileTab === 'profile' ? 'show active' : ''; ?>" id="profile-details-pane" role="tabpanel" aria-labelledby="profile-details-tab" tabindex="0">
                                    <div class="d-flex align-items-center gap-3 mb-4">
                                        <img id="uploadPreview" src="assets/images/pfp/Default_pfp.png" alt="Profile preview" class="profile-upload-preview">
                                        <div class="flex-grow-1">
                                            <label class="form-label">Profile Photo</label>
                                            <input type="file" class="form-control" name="user_pfp" id="userPfp" accept=".jpg,.jpeg,.png,.webp">
                                            <small class="text-muted">JPG, PNG, or WebP up to 2 MB.</small>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Full Name</label>
                                                <input type="text" class="form-control readonly-field" name="full_name" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Username</label>
                                                <input type="text" class="form-control readonly-field" name="username" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Email Address</label>
                                                <input type="email" class="form-control" name="email_address" placeholder="name@example.com">
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Position</label>
                                                <input type="text" class="form-control readonly-field" name="position" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Department</label>
                                                <input type="text" class="form-control readonly-field" name="department" readonly>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label">Dealer</label>
                                                <input type="text" class="form-control readonly-field" name="dealer" readonly>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade <?php echo $activeProfileTab === 'settings' ? 'show active' : ''; ?>" id="profile-settings-pane" role="tabpanel" aria-labelledby="profile-settings-tab" tabindex="0">
                                    <h5 class="mb-1">Approval Signature</h5>
                                    <p class="text-muted mb-3">This signature is used on your approval and printable documents.</p>
                                    <div class="row g-3 align-items-center mb-3">
                                        <div class="col-md-5">
                                            <img id="signaturePreview" class="signature-upload-preview d-none" alt="Processed signature preview">
                                            <div id="signaturePlaceholder" class="signature-upload-preview d-flex align-items-center justify-content-center text-muted text-center">
                                                <div><i class="ti ti-signature f-28"></i><br><small>No signature uploaded</small></div>
                                            </div>
                                        </div>
                                        <div class="col-md-7">
                                            <label class="form-label" for="userSignature">Upload Signature</label>
                                            <input type="file" class="form-control" id="userSignature" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                                            <small class="text-muted">Upload a signature on white paper (JPG, PNG, or WebP, up to 5 MB). The white background is automatically removed and the result is saved as a transparent PNG.</small>
                                            <div id="signatureLockedNote" class="alert alert-warning py-2 px-3 mt-3 mb-0 d-none" role="note">
                                                <i class="ti ti-lock me-1"></i>
                                                A signature is already saved. Please contact the administrator if you need to change your current signature.
                                            </div>
                                            <div id="signatureProcessing" class="small text-primary mt-2 d-none">
                                                <span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Processing signature...
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end gap-2 border-top pt-3 mt-3">
                                <button type="button" class="btn btn-light" id="reloadProfile">
                                    <i class="ti ti-refresh me-1"></i> Reload
                                </button>
                                <button type="submit" class="btn btn-primary" id="saveProfile">
                                    <i class="ti ti-device-floppy me-1"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">Security</h5>
                    </div>
                    <div class="card-body">
                        <div class="security-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="avtar avtar-s btn-light-primary">
                                    <i class="ti ti-shield-lock f-22"></i>
                                </div>
                                <div class="ms-3 flex-grow-1">
                                    <h6 class="mb-1">Password</h6>
                                    <div class="text-muted">Last changed: <span id="lastPasswordChange">Loading...</span></div>
                                </div>
                                <a href="user_change_password" class="btn btn-sm btn-outline-primary">Update</a>
                            </div>
                        </div>

                        <div class="security-item">
                            <div class="d-flex align-items-start">
                                <div class="avtar avtar-s btn-light-warning">
                                    <i class="ti ti-info-circle f-22"></i>
                                </div>
                                <div class="ms-3">
                                    <h6 class="mb-1">Account Control</h6>
                                    <p class="text-muted mb-0">Name, role, department, dealer, and position are managed by the administrator to keep approvals and reports consistent.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">Recommendations</h5>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 ps-3">
                            <li>Use a unique password with at least 12 characters.</li>
                            <li>Keep your email current for approval and system notifications.</li>
                            <li>Ask an administrator to update organization details when your role changes.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        const profileKey = <?php echo json_encode($profileKey); ?>;
        const defaultAvatar = 'assets/images/pfp/Default_pfp.png';
        const canReplaceSignature = <?php echo json_encode(($_SESSION['position'] ?? '') === 'Administrator'); ?>;
        let processedSignature = null;
        let signatureObjectUrl = null;
        let signatureProcessingId = 0;

        function showAlert(type, message) {
            $('#profileAlert').html(
                '<div class="alert alert-' + type + ' alert-dismissible fade show" role="alert">' +
                $('<div>').text(message).html() +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>' +
                '</div>'
            );
        }

        function setAvatar(src) {
            const avatar = src || defaultAvatar;
            $('#profileAvatar, #uploadPreview').attr('src', avatar);
        }

        function setSignaturePreview(src) {
            if (src) {
                $('#signaturePreview').attr('src', src).removeClass('d-none');
                $('#signaturePlaceholder').addClass('d-none');
            } else {
                $('#signaturePreview').attr('src', '').addClass('d-none');
                $('#signaturePlaceholder').removeClass('d-none');
            }
        }

        function processSignature(file) {
            return new Promise(function(resolve, reject) {
                const image = new Image();
                const sourceUrl = URL.createObjectURL(file);

                image.onload = function() {
                    try {
                        const maxDimension = 2400;
                        const scale = Math.min(1, maxDimension / Math.max(image.naturalWidth, image.naturalHeight));
                        const width = Math.max(1, Math.round(image.naturalWidth * scale));
                        const height = Math.max(1, Math.round(image.naturalHeight * scale));
                        const canvas = document.createElement('canvas');
                        const context = canvas.getContext('2d', { willReadFrequently: true });
                        canvas.width = width;
                        canvas.height = height;
                        context.drawImage(image, 0, 0, width, height);

                        const imageData = context.getImageData(0, 0, width, height);
                        const pixels = imageData.data;
                        let minX = width;
                        let minY = height;
                        let maxX = -1;
                        let maxY = -1;

                        for (let y = 0; y < height; y++) {
                            for (let x = 0; x < width; x++) {
                                const index = (y * width + x) * 4;
                                const luminance = (0.2126 * pixels[index]) + (0.7152 * pixels[index + 1]) + (0.0722 * pixels[index + 2]);
                                const inkOpacity = Math.max(0, Math.min(1, (245 - luminance) / 105));
                                const alpha = Math.round(pixels[index + 3] * inkOpacity);

                                pixels[index] = 0;
                                pixels[index + 1] = 0;
                                pixels[index + 2] = 0;
                                pixels[index + 3] = alpha;

                                if (alpha > 12) {
                                    minX = Math.min(minX, x);
                                    minY = Math.min(minY, y);
                                    maxX = Math.max(maxX, x);
                                    maxY = Math.max(maxY, y);
                                }
                            }
                        }

                        URL.revokeObjectURL(sourceUrl);
                        if (maxX < minX || maxY < minY) {
                            reject(new Error('No dark signature marks were found. Please use a clear signature on white paper.'));
                            return;
                        }

                        context.putImageData(imageData, 0, 0);
                        const padding = Math.max(12, Math.round(Math.max(maxX - minX, maxY - minY) * 0.04));
                        const cropX = Math.max(0, minX - padding);
                        const cropY = Math.max(0, minY - padding);
                        const cropWidth = Math.min(width - cropX, maxX - minX + 1 + (padding * 2));
                        const cropHeight = Math.min(height - cropY, maxY - minY + 1 + (padding * 2));
                        const output = document.createElement('canvas');
                        output.width = cropWidth;
                        output.height = cropHeight;
                        output.getContext('2d').drawImage(canvas, cropX, cropY, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);
                        output.toBlob(function(blob) {
                            blob ? resolve(blob) : reject(new Error('Unable to prepare the signature image.'));
                        }, 'image/png');
                    } catch (error) {
                        URL.revokeObjectURL(sourceUrl);
                        reject(error);
                    }
                };

                image.onerror = function() {
                    URL.revokeObjectURL(sourceUrl);
                    reject(new Error('The selected signature image could not be read.'));
                };
                image.src = sourceUrl;
            });
        }

        function loadProfile() {
            $.ajax({
                url: 'admin/get_user_profile.php',
                type: 'GET',
                data: { user_id: profileKey },
                dataType: 'json',
                success: function(data) {
                    if (data.error) {
                        showAlert('danger', data.error);
                        return;
                    }

                    const displayName = data.full_name || data.username || 'Account User';
                    $('#profileName').text(displayName);
                    $('#profilePosition').text(data.position || 'No position');
                    $('#profileDepartment').text(data.department || 'No department');
                    $('#profileDealer').text(data.dealer || 'No dealer');
                    $('#lastPasswordChange').text(data.last_password_change || 'Never changed');
                    setAvatar(data.user_pfp);
                    if (!processedSignature) {
                        setSignaturePreview(data.user_signature || '');
                    }
                    const hasSavedSignature = Boolean(String(data.user_signature || '').trim());
                    const signatureLocked = hasSavedSignature && !canReplaceSignature;
                    $('#userSignature').prop('disabled', signatureLocked);
                    $('#signatureLockedNote').toggleClass('d-none', !signatureLocked);

                    $("input[name='full_name']").val(data.full_name || '');
                    $("input[name='username']").val(data.username || '');
                    $("input[name='email_address']").val(data.email_address || '');
                    $("input[name='position']").val(data.position || '');
                    $("input[name='department']").val(data.department || '');
                    $("input[name='dealer']").val(data.dealer || '');
                },
                error: function() {
                    showAlert('danger', 'Unable to load profile details.');
                }
            });
        }

        $('#userPfp').on('change', function() {
            const file = this.files && this.files[0];
            if (!file) {
                return;
            }
            if (file.size > 2 * 1024 * 1024) {
                showAlert('warning', 'Profile photo must be 2 MB or smaller.');
                this.value = '';
                return;
            }
            setAvatar(URL.createObjectURL(file));
        });

        $('#userSignature').on('change', async function() {
            const input = this;
            const file = input.files && input.files[0];
            const processingId = ++signatureProcessingId;
            processedSignature = null;
            $('#signatureProcessing').addClass('d-none');
            $('#saveProfile').prop('disabled', false);
            if (!file) {
                loadProfile();
                return;
            }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
                showAlert('warning', 'Signature must be a JPG, PNG, or WebP image.');
                input.value = '';
                loadProfile();
                return;
            }
            if (file.size > 5 * 1024 * 1024) {
                showAlert('warning', 'Signature image must be 5 MB or smaller.');
                input.value = '';
                loadProfile();
                return;
            }

            $('#signatureProcessing').removeClass('d-none');
            $('#saveProfile').prop('disabled', true);
            try {
                const result = await processSignature(file);
                if (processingId !== signatureProcessingId) return;
                processedSignature = result;
                if (signatureObjectUrl) URL.revokeObjectURL(signatureObjectUrl);
                signatureObjectUrl = URL.createObjectURL(processedSignature);
                setSignaturePreview(signatureObjectUrl);
            } catch (error) {
                if (processingId !== signatureProcessingId) return;
                input.value = '';
                showAlert('warning', error.message || 'Unable to process the signature image.');
                loadProfile();
            } finally {
                if (processingId === signatureProcessingId) {
                    $('#signatureProcessing').addClass('d-none');
                    $('#saveProfile').prop('disabled', false);
                }
            }
        });

        $('#reloadProfile').on('click', loadProfile);

        $('#profileForm').on('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);
            if (processedSignature) {
                formData.append('user_signature', processedSignature, 'signature.png');
            }
            $.ajax({
                url: 'data/update_user_profile.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                beforeSend: function() {
                    showAlert('info', 'Saving profile changes...');
                },
                success: function(response) {
                    if (response.status === 'success') {
                        showAlert('success', response.message);
                        $('#userPfp').val('');
                        $('#userSignature').val('');
                        processedSignature = null;
                        loadProfile();
                    } else {
                        showAlert('danger', response.message || 'Profile update failed.');
                    }
                },
                error: function(xhr) {
                    const message = xhr.responseJSON && xhr.responseJSON.message
                        ? xhr.responseJSON.message
                        : 'An error occurred while saving your profile.';
                    showAlert('danger', message);
                }
            });
        });

        loadProfile();
    });
</script>

<?php include 'footer.php'; ?>
