<?php
include '../../includes/db.php';

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $vendorId       = $_POST['vendorId'] ?? '';
    $vendorName     = $_POST['vendorName'] ?? '';
    $contactPerson  = $_POST['contactPerson'] ?? '';
    $contactNumber  = $_POST['contactNumber'] ?? '';
    $mobileNumber   = $_POST['mobileNumber'] ?? '';
    $email          = $_POST['email'] ?? '';
    $tinNumber      = $_POST['tinNumber'] ?? '';
    $paymentTerms   = $_POST['termsPayment'] ?? '';
    $vendorAddress  = $_POST['vendorAddress'] ?? '';
    $dealer         = $_POST['dealer'] ?? '';
    $businessType   = $_POST['businessType'] ?? '';
    $productCategory= $_POST['productCategory'] ?? '';

    try {
        $sql = "UPDATE suppliers 
                SET supplier_name = ?, contact_person = ?, contact_number = ?, mobile_number = ?, 
                    email = ?, tin_number = ?, payment_terms = ?, street = ?, dealer = ?, 
                    business_type = ?, product_category = ?
                WHERE vendor_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $vendorName, $contactPerson, $contactNumber, $mobileNumber,
            $email, $tinNumber, $paymentTerms, $vendorAddress, $dealer,
            $businessType, $productCategory, $vendorId
        ]);

        $response = ['status' => 'success', 'message' => 'Vendor updated successfully'];
    } catch (Exception $e) {
        $response = ['status' => 'error', 'message' => $e->getMessage()];
    }
}

echo json_encode($response);
