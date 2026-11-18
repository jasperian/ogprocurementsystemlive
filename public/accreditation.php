<?php /* accreditation_form.php */ ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supplier Accreditation Form</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/signature_pad@4.0.0/dist/signature_pad.umd.min.js"></script>
</head>
<body>
<div class="container mt-5">
    <div class="card shadow rounded">
        <div class="card-body">
            <h2 class="card-title mb-4">Supplier Accreditation Form</h2>

            <form action="submit_accreditation.php" method="POST" onsubmit="return validateSignatures()">
                <!-- Company Information -->
                <div class="mb-3">
                    <label class="form-label">Company Name</label>
                    <input type="text" name="company_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" required></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label">Service/Good Supplied</label>
                    <input type="text" name="service" class="form-control" required>
                </div>

                <!-- Company Profile -->
                <h5 class="mt-4">1. Company Profile</h5>

                <div class="mb-3">
                    <label class="form-label">Registered with government entities?</label>
                    <select name="registered_rating" class="form-select">
                        <option value="100">Excellent</option>
                        <option value="90">Very Satisfactory</option>
                        <option value="80">Satisfactory</option>
                        <option value="75">Fair</option>
                        <option value="70">Poor</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label">Owner/Partners reputable?</label>
                    <select name="reputable_rating" class="form-select">
                        <option value="100">Excellent</option>
                        <option value="90">Very Satisfactory</option>
                        <option value="80">Satisfactory</option>
                        <option value="75">Fair</option>
                        <option value="70">Poor</option>
                    </select>
                </div>

                <!-- Quality System Section -->
                <div class="section-title">2. QUALITY SYSTEM</div>
                <div class="mb-2">
                    <label class="form-label">A. Table of Organization in order?</label>
                    <input type="text" name="quality_a" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">B. Accessibility of contact person?</label>
                    <input type="text" name="quality_b" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">C. Scope of services?</label>
                    <input type="text" name="quality_c" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">D. With complete facilities to serve requirements of TNE & TNESC?</label>
                    <input type="text" name="quality_d" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">E. With proper documented guidelines?</label>
                    <input type="text" name="quality_e" class="form-control">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Total</label>
                        <input type="number" step="0.01" name="quality_total" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Average (/2)</label>
                        <input type="number" step="0.01" name="quality_avg" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Final (x 40%)</label>
                        <input type="number" step="0.01" name="quality_final" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justification</label>
                    <textarea name="quality_justification" class="form-control" rows="2"></textarea>
                </div>

                <!-- Price Section -->
                <div class="section-title">3. PRICE (COMPETITIVENESS)</div>
                <div class="mb-2">
                    <label class="form-label">A. Discounts/incentive provided? Rates/Price list provided?</label>
                    <input type="text" name="price_a" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">B. Terms of payments offered to TNE & TNESC acceptable?</label>
                    <input type="text" name="price_b" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">C. With warranty on products/service?</label>
                    <input type="text" name="price_c" class="form-control">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Total</label>
                        <input type="number" step="0.01" name="price_total" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Average (/2)</label>
                        <input type="number" step="0.01" name="price_avg" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Final (x 35%)</label>
                        <input type="number" step="0.01" name="price_final" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justification</label>
                    <textarea name="price_justification" class="form-control" rows="2"></textarea>
                </div>

                <!-- Industry Expertise Section -->
                <div class="section-title">4. INDUSTRY EXPERTISE</div>
                <div class="mb-2">
                    <label class="form-label">A. Affiliated with other reputable companies (if with affiliations)?</label>
                    <input type="text" name="industry_a" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">B. Background investigation favorable?</label>
                    <input type="text" name="industry_b" class="form-control">
                </div>
                <div class="mb-2">
                    <label class="form-label">C. With aftersales program?</label>
                    <input type="text" name="industry_c" class="form-control">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <label class="form-label">Total</label>
                        <input type="number" step="0.01" name="industry_total" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Average (/2)</label>
                        <input type="number" step="0.01" name="industry_avg" class="form-control">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Final (x 15%)</label>
                        <input type="number" step="0.01" name="industry_final" class="form-control">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Justification</label>
                    <textarea name="industry_justification" class="form-control" rows="2"></textarea>
                </div>

                <!-- Signatory 1 -->
                <h5 class="mt-4">Signatory 1</h5>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="signatory1_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" name="signatory1_position" class="form-control" required>
                </div>
                <div class="mb-3">
                    <canvas id="sig1" width="400" height="150" style="border:1px solid #ccc; width:100%; max-width:400px;"></canvas>
                    <input type="hidden" name="signature1_image" id="signature1_image">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSignature(1)">Clear Signature 1</button>
                </div>

                <!-- Signatory 2 -->
                <h5 class="mt-4">Signatory 2</h5>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="signatory2_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" name="signatory2_position" class="form-control" required>
                </div>
                <div class="mb-3">
                    <canvas id="sig2" width="400" height="150" style="border:1px solid #ccc; width:100%; max-width:400px; opacity:0.5;"></canvas>
                    <input type="hidden" name="signature2_image" id="signature2_image">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSignature(2)">Clear Signature 2</button>
                </div>
                

                <!-- Signatory 3 -->
                <h5 class="mt-4">Signatory 3</h5>
                <div class="mb-3">
                    <label class="form-label">Name</label>
                    <input type="text" name="signatory3_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Position</label>
                    <input type="text" name="signatory3_position" class="form-control" required>
                </div>
                <div class="mb-3">
                    <canvas id="sig3" width="400" height="150" style="border:1px solid #ccc; width:100%; max-width:400px; opacity:0.5;"></canvas>
                    <input type="hidden" name="signature3_image" id="signature3_image">
                    <button type="button" class="btn btn-outline-secondary btn-sm mt-2" onclick="clearSignature(3)">Clear Signature 3</button>
                </div>

                <div class="mb-3">
                    <label class="form-label">Email (optional copy)</label>
                    <input type="email" name="email" class="form-control">
                </div>

                <button type="submit" class="btn btn-primary">Submit</button>
            </form>
        </div>
    </div>
</div>

<script>
    const pad1 = new SignaturePad(document.getElementById("sig1"));
    const pad2 = new SignaturePad(document.getElementById("sig2"));
    const pad3 = new SignaturePad(document.getElementById("sig3"));

    function clearSignature(index) {
        if (index === 1) pad1.clear();
        if (index === 2) pad2.clear();
        if (index === 3) pad3.clear();
    }

    function validateSignatures() {
        if (!pad1.isEmpty()) {
            document.getElementById("signature1_image").value = pad1.toDataURL();
            pad2.canvas.style.opacity = "1";
        } else {
            alert("Signatory 1 must sign first.");
            return false;
        }

        if (!pad2.isEmpty()) {
            document.getElementById("signature2_image").value = pad2.toDataURL();
            pad3.canvas.style.opacity = "1";
        } else {
            alert("Signatory 2 must sign before proceeding to Signatory 3.");
            return false;
        }

        if (!pad3.isEmpty()) {
            document.getElementById("signature3_image").value = pad3.toDataURL();
        } else {
            alert("Signatory 3 must sign to complete the form.");
            return false;
        }

        return true;
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>