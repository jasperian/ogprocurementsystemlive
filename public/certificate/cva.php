<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require('../includes/fpdf/fpdf.php');
require('../../includes/db.php'); // DB connection

$vendor_id = $_GET['vendor_id'] ?? null;

if (!$vendor_id) {
    die("Vendor ID is required");
}

// Fetch vendor data
$stmt = $pdo->prepare("SELECT * FROM suppliers s INNER JOIN dealer_details dd ON s.dealer = dd.dealer WHERE s.vendor_id = ?");
$stmt->execute([$vendor_id]);
$vendor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$vendor) {
    die("Vendor not found");
}



class PDF extends FPDF
{
     public $dealer_logo; // Declare property
    // Header with Logo and Title
    function Header()
    {
        if (!empty($this->dealer_logo)) {
            $this->Image($this->dealer_logo, 20, 10, 50); // Use class property
        }
        $this->SetFont('Arial', 'B', 16);
        $this->SetY(35);
        $this->Cell(0, 10, 'CERTIFICATE OF VENDOR ACCREDITATION', 0, 1, 'C');
        $this->Ln(5);
    }

    // Footer
    function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
    }
}

// Original date from DB (e.g., 2025-07-31)
$raw_date = $vendor['accreditation_date'];

// Start Date: Format as "July 31, 2025"
$start_date = date('F j, Y', strtotime($raw_date));

// End Date: Add 2 years and format as "July 31, 2027"
$end_date = date('F j, Y', strtotime($raw_date . ' +2 years'));

// Issue Date: Format as "31st day of July, 2025"
$day = date('j', strtotime($raw_date)); // numeric day (1-31)
$day_suffix = date('S', strtotime($raw_date)); // st, nd, rd, th
$month_year = date('F, Y', strtotime($raw_date));

$issue_date = "{$day}{$day_suffix} day of {$month_year}";

$supplier_name = $vendor['supplier_name'];
$start_date = $start_date;
$end_date = $end_date;
$accreditation_no = $vendor['vendor_id'];
$issue_date = $issue_date;
$purchasing_manager = $vendor['purchasing_manager'] ?? 'N/A'; // Fallback if not set
$dealer = $vendor['dealer_name'] ?? 'OG'; // Default to 'OG' if not set
$dealer_address = $vendor['dealer_address'] ?? 'N/A'; // Fallback if not set
$purchasing_signed = $vendor['manager_sign'] ?? 'N/A'; // Fallback if not set
$dealer_logo = $vendor['dealer_logo'] ?? '../assets/images/default_logo.png'; // Fallback logo


$pdf = new PDF('P', 'mm', 'A4');
$pdf->dealer_logo = $dealer_logo; 
$pdf->AddPage();
$pdf->SetMargins(20, 45, 20);

// Certificate Body
$pdf->SetFont('Arial', '', 11);
$pdf->Ln(2);

// First part before supplier name
$pdf->Write(7, "This is to certify that ");

// Bold supplier name
$pdf->SetFont('Arial', 'B', 11);
$pdf->Write(7, $supplier_name);

// Back to normal text
$pdf->SetFont('Arial', '', 11);
$pdf->Write(7, " has been officially "); 

$pdf->SetFont('Arial', 'B', 11);
$pdf->Write(7, "accredited by $dealer "); 

$pdf->SetFont('Arial', '', 11);
$pdf->Write(7, "in recognition of their demonstrated compliance with our established standards, policies, and quality management system requirements.");

$pdf->Ln(10);
$pdf->MultiCell(0, 7, "This accreditation is conferred following a thorough evaluation of the supplier's capabilities, documentation, and operational practices, affirming their commitment to product and service excellence, regulatory compliance, and continuous improvement.");

$pdf->Ln(5);
$pdf->SetFont('Arial', '', 11);
$pdf->Write(7, "This recognition affirms the supplier's status as an authorized business partner of ");
$pdf->SetFont('Arial', 'B', 11);
$pdf->Write(7, $dealer);


// Accreditation Details
$pdf->Ln(10);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, "Accreditation Validity: $start_date to $end_date", 0, 1);
$pdf->Cell(0, 6, "Certificate No.: $accreditation_no", 0, 1);

$pdf->Ln(4);
$pdf->SetFont('Arial', '', 11);
$pdf->MultiCell(0, 6, "Issued this $issue_date\nAt $dealer, $dealer_address");

// Signatures
$pdf->Ln(30);

// Purchasing Manager
$pdf->Image($purchasing_signed, 85, $pdf->GetY(), 38);
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 6, "_________________________", 0, 1, 'C');
$pdf->Cell(0, 6, "$purchasing_manager", 0, 1, 'C');
$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(0, 5, "Purchasing Manager", 0, 1, 'C');

// VP Signatures (Side by Side)
$pdf->Ln(6);
$y = $pdf->GetY();
$pdf->Image('../assets/images/signature/68858ba9ea57f_sign.png', 35, $y, 30);
$pdf->Image('../assets/images/signature/68858b0d4507a_sign.png', 155, $y, 30);

$pdf->Ln(3);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(60, 6, "_____________________", 0, 0, 'C');
$pdf->Cell(60, 6, "", 0, 0, 'C');
$pdf->Cell(60, 6, "_____________________", 0, 1, 'C');

$pdf->Cell(60, 6, "Jocelyn G. Rigor", 0, 0, 'C');
$pdf->Cell(60, 6, "", 0, 0, 'C');
$pdf->Cell(60, 6, "Ana Ma. Teresa Oben-Reyes", 0, 1, 'C');

$pdf->SetFont('Arial', 'I', 10);
$pdf->Cell(60, 5, "VP for Accounting", 0, 0, 'C');
$pdf->Cell(60, 5, "", 0, 0, 'C');
$pdf->Cell(60, 5, "SVP for Finance and Administration", 0, 1, 'C');

// President Signature
// $pdf->Ln(8);
// $pdf->Image('../assets/images/signature/approved.png', 85, $pdf->GetY(), 35);
// $pdf->Ln(18);
// $pdf->SetFont('Arial', 'B', 11);
// $pdf->Cell(0, 6, "_________________________", 0, 1, 'C');
// $pdf->Cell(0, 6, "Don Juan Miguel A. Oben", 0, 1, 'C');
// $pdf->SetFont('Arial', 'I', 10);
// $pdf->Cell(0, 5, "President", 0, 1, 'C');

$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplier_name);
// Output PDF with supplier name in the filename
$pdf->Output('I', $filename . '_Accreditation.pdf');
?>
