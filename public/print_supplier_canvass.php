<?php
require 'includes/fpdf/fpdf.php';
require '../includes/db.php';
session_start();

$batch_id = $_GET['batch_id'] ?? null;
$supplier_id = $_GET['supplier_id'] ?? null;
$pr_dealer = $_GET['pr_dealer'] ?? null;

if (!$batch_id || !$supplier_id) {
    die("Batch ID and Supplier ID are required");
}

// Default Dealer Info (fallback)
$dealerInfo = [
    'dealer_name' => 'TOYOTA NORTH EDSA',
    'dealer_address' => '1010 EDSA, Quezon City, Metro Manila',
    'telephone' => '(02) 8-927-7215',
    'dealer_logo' => 'logo.png' // Default logo filename
];

if ($pr_dealer) {
    $stmt = $pdo->prepare("SELECT * FROM dealer_details WHERE dealer = ?");
    $stmt->execute([$pr_dealer]);
    $fetchedDealer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetchedDealer) {
        $dealerInfo = $fetchedDealer;
        // Strip path if necessary for dealer_logo, assuming it's in assets/images/
        // My check showed '../assets/images/tne.png', so basename will work.
        $dealerInfo['dealer_logo'] = basename($fetchedDealer['dealer_logo']);
    }
}

// Fetch batch info
$stmt = $pdo->prepare("SELECT * FROM canvass_sheet_batches WHERE batch_id = ?");
$stmt->execute([$batch_id]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die("Batch not found");
}

// Fetch budget info if exists
$budgetCode = '';
if (!empty($batch['budget_code'])) {
    $stmt = $pdo->prepare("SELECT budget_code FROM budgets WHERE budget_id = ?");
    $stmt->execute([$batch['budget_code']]);
    $budgetCode = $stmt->fetchColumn();
}

// Fetch supplier info
$stmt = $pdo->prepare("SELECT * FROM suppliers WHERE supplier_id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$supplier) {
    die("Supplier not found");
}

// Fetch items
$stmt = $pdo->prepare("
    SELECT ci.*, i.item_name, i.description, i.unit_measure
    FROM canvass_items ci
    JOIN items i ON ci.item_id = i.item_id
    WHERE ci.batch_id = ? AND ci.supplier_id = ?
");
$stmt->execute([$batch_id, $supplier_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch overall discount
$stmt = $pdo->prepare("SELECT overall_discount FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
$stmt->execute([$batch_id, $supplier_id]);
$overall_discount = $stmt->fetchColumn() ?: 0;

// Fetch Signatories
$sql = "SELECT 
    ca.batch_id,
    ca.checked_1, ca.checked_3, ca.checked_4, ca.checked_5, ca.checked_6, ca.checked_7, ca.checked_8, ca.checked_9,
    ca.assigned_dealer,
    u1.full_name AS approver_1_name, u1.position AS approver_1_position, u1.user_signature AS approver_1_signature,
    u3.full_name AS approver_3_name, u3.position AS approver_3_position, u3.user_signature AS approver_3_signature,
    u4.full_name AS approver_4_name, u4.position AS approver_4_position, u4.user_signature AS approver_4_signature,
    u5.full_name AS approver_5_name, u5.position AS approver_5_position, u5.user_signature AS approver_5_signature,
    u6.full_name AS approver_6_name, u6.position AS approver_6_position, u6.user_signature AS approver_6_signature,
    u7.full_name AS approver_7_name, u7.position AS approver_7_position, u7.user_signature AS approver_7_signature,
    u8.full_name AS approver_8_name, u8.position AS approver_8_position, u8.user_signature AS approver_8_signature,
    u9.full_name AS approver_9_name, u9.position AS approver_9_position, u9.user_signature AS approver_9_signature
FROM canvass_approvals ca
LEFT JOIN users u1 ON ca.approver_1 = u1.user_id
LEFT JOIN users u3 ON ca.approver_3 = u3.user_id
LEFT JOIN users u4 ON ca.approver_4 = u4.user_id
LEFT JOIN users u5 ON ca.approver_5 = u5.user_id
LEFT JOIN users u6 ON ca.approver_6 = u6.user_id
LEFT JOIN users u7 ON ca.approver_7 = u7.user_id
LEFT JOIN users u8 ON ca.approver_8 = u8.user_id
LEFT JOIN users u9 ON ca.approver_9 = u9.user_id
WHERE ca.batch_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$batch_id]);
$signature = $stmt->fetch(PDO::FETCH_ASSOC);

$assignedDealer = $signature['assigned_dealer'] ?? '';
if ($assignedDealer === 'TMR' || $assignedDealer === 'TSJ') {
    $customOrder = [1, 2, 3, 4, 8, 5, 6, 7, 9];
} else {
    $customOrder = [1, 2, 3, 4, 5, 6, 7, 9];
}

$labels = [
    1 => 'Created By',
    3 => 'Endorsed By',
    4 => 'Approved By',
    5 => 'Approved By',
    6 => 'Approved By',
    7 => 'Approved By',
    8 => 'Approved By',
    9 => 'Approved By'
];

$positionKey = [
    1 => 'Purchasing Officer',
    3 => 'Purchasing Manager',
    4 => 'General Manager'
];

$signatories = [];
foreach ($customOrder as $i) {
    if (!empty($signature["approver_{$i}_name"])) {
        $signatories[] = [
            'label' => $labels[$i] ?? 'Approved By',
            'name' => $signature["approver_{$i}_name"],
            'position' => $positionKey[$i] ?? $signature["approver_{$i}_position"],
            'signature' => !empty($signature["checked_{$i}"]) ? $signature["approver_{$i}_signature"] : '',
            'date' => $signature["checked_{$i}"] ?? ''
        ];
    }
}

class PDF extends FPDF
{
    public $pr_number;
    public $budget_code;
    public $dealer_info;

    function Header()
    {
        $this->Watermark();
        // 🔹 Logo (Left)
        $logo = $this->dealer_info['dealer_logo'] ?? 'logo.png';
        if ($logo && file_exists('assets/images/' . $logo)) {
            $this->Image('assets/images/' . $logo, 10, 10, 30);
        }

        // 🔹 Company Name (Center)
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 5, strtoupper($this->dealer_info['dealer_name']), 0, 1, 'C');

        // 🔹 Address (Center - smaller)
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, $this->dealer_info['dealer_address'], 0, 1, 'C');
        $this->Cell(0, 4, 'Tel: ' . $this->dealer_info['telephone'] . ' | www.toyotanorthedsa.com', 0, 1, 'C');

        // 🔹 Title
        $this->Ln(3);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 6, 'PURCHASE ORDER', 0, 1, 'C');

        // 🔹 Right-side details (aligned properly)
        $this->SetFont('Arial', '', 9);
        $this->SetY(10);
        $this->SetX(-60);

        $this->Cell(70, 5, 'PR No: ' . $this->pr_number, 0, 1, 'L');
        $this->SetX(-60);
        $this->Cell(70, 5, 'Budget Code: ' . ($this->budget_code ?: 'N/A'), 0, 1, 'L');
        $this->SetX(-60);
        $this->Cell(70, 5, 'Date: ' . date('F j, Y'), 0, 1, 'L');

        // 🔹 Divider line
        $this->Ln(10);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        $this->Ln(5);
    }

    function Footer()
    {
        // 🔹 Line above footer
        $this->SetY(-20);
        $this->Line(10, $this->GetY(), 200, $this->GetY());

        // 🔹 Footer text
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'This is a system generated document from OGPS.', 0, 1, 'C');

        // 🔹 Page number
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

    protected $angle = 0;

    function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) $x = $this->x;
        if ($y == -1) $y = $this->y;

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;

            $this->_out(sprintf(
                'q %.5F %.5F %.5F %.5F %.5F %.5F cm 1 0 0 1 %.5F %.5F cm',
                $c,
                $s,
                -$s,
                $c,
                $cx,
                $cy,
                -$cx,
                -$cy
            ));
        }
    }

    function Watermark()
    {
        // Light gray color
        $this->SetTextColor(200, 200, 200);

        // Big font
        $this->SetFont('Arial', 'B', 150);

        // Rotate text
        $this->Rotate(45, 55, 190);

        // Watermark text
        $this->Text(70, 190, 'OGPS');

        // Reset rotation
        $this->Rotate(0);

        // Reset text color back to black
        $this->SetTextColor(0, 0, 0);
    }
}



$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->pr_number = $batch['pr_number'];
$pdf->budget_code = $budgetCode;
$pdf->dealer_info = $dealerInfo;

$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 4, 'Supplier Information', 0, 1);

$pdf->SetFont('Arial', '', 10);

$pdf->Cell(30, 4, 'Supplier Name:', 0, 0);
$pdf->Cell(0, 4, $supplier['supplier_name'], 0, 1);

$pdf->Cell(30, 4, 'Supplier Address:', 0, 0);
$pdf->Cell(0, 4, $supplier['street'], 0, 1);

$pdf->Cell(30, 4, 'Contact Person:', 0, 0);
$pdf->Cell(0, 4, $supplier['contact_person'], 0, 1);

$pdf->Cell(30, 4, 'Contact Number:', 0, 0);
$pdf->Cell(0, 4, $supplier['contact_number'], 0, 1);

$pdf->Cell(30, 4, 'Payment Terms:', 0, 0);
$pdf->Cell(0, 4, $supplier['payment_terms'], 0, 1);

$pdf->Ln(10);

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(0, 4, 'Delivery Address:', 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 4, strtoupper($dealerInfo['dealer_name']), 0, 1);
$pdf->Cell(0, 4, $dealerInfo['dealer_address'], 0, 1);
$pdf->Cell(0, 4, 'Tel: ' . $dealerInfo['telephone'], 0, 1);
$pdf->Cell(0, 4, 'Website: www.toyotanorthedsa.com', 0, 1);
$pdf->Cell(0, 4, 'VAT Reg. TIN: 000-300-956-00001', 0, 1);

// Items Table Header
$pdf->SetFont('Arial', 'B', 10);
$pdf->SetFillColor(230, 230, 230);
$pdf->Cell(80, 7, 'Item Name', 1, 0, 'C', true);
$pdf->Cell(15, 7, 'Qty', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Unit', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Price', 1, 0, 'C', true);
$pdf->Cell(40, 7, 'Subtotal', 1, 1, 'C', true);

$pdf->SetFont('Arial', '', 9);
$gross_total = 0;
foreach ($items as $item) {
    $subtotal = $item['qty'] * $item['discounted_unit_price'];
    $gross_total += $subtotal;

    $pdf->Cell(80, 7, $item['item_name'], 1);
    $pdf->Cell(15, 7, $item['qty'], 1, 0, 'C');
    $pdf->Cell(20, 7, $item['unit_measure'], 1, 0, 'C');
    $pdf->Cell(35, 7, number_format($item['discounted_unit_price'], 2), 1, 0, 'R');
    $pdf->Cell(40, 7, number_format($subtotal, 2), 1, 1, 'R');
}

$net_total = $gross_total - $overall_discount;

$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(150, 7, 'Gross Total:', 0, 0, 'R');
$pdf->Cell(40, 7, number_format($gross_total, 2), 1, 1, 'R');
$pdf->Cell(150, 7, 'Overall Deduction:', 0, 0, 'R');
$pdf->Cell(40, 7, number_format($overall_discount, 2), 1, 1, 'R');
$pdf->SetFont('Arial', 'B', 11);
$pdf->SetTextColor(0, 102, 204);
$pdf->Cell(150, 10, 'Net Total:', 0, 0, 'R');
$pdf->Cell(40, 10, 'PHP ' . number_format($net_total, 2), 1, 1, 'R');

$pdf->Ln(15);
$pdf->SetTextColor(0, 0, 0);

// Check if we need a new page for signatures
if ($pdf->GetY() > 200) {
    $pdf->AddPage();
}



$x_start = 10;
$y_start = $pdf->GetY();
$col_width = 60;
$row_height = 40;
$count = 0;

foreach ($signatories as $sig) {
    if ($count > 0 && $count % 3 == 0) {
        $pdf->Ln(10);
        $y_start = $pdf->GetY();
    }

    $x = $x_start + ($count % 3) * ($col_width + 5);
    $pdf->SetXY($x, $y_start);

    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($col_width, 5, $sig['label'] . ':', 0, 1, 'C');

    // Draw signature line
    $pdf->Line($x + 5, $y_start + 20, $x + $col_width - 5, $y_start + 20);

    // Output signature image if exists
    if (!empty($sig['signature']) && file_exists($sig['signature'])) {
        $pdf->Image($sig['signature'], $x + ($col_width / 2) - 10, $y_start + 6, 20);
    }

    $pdf->SetXY($x, $y_start + 22);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($col_width, 4, $sig['name'], 0, 1, 'C');

    $pdf->SetXY($x, $pdf->GetY());
    $pdf->SetFont('Arial', '', 7);
    $pdf->MultiCell($col_width, 3, $sig['position'], 0, 'C');

    if (!empty($sig['date'])) {
        $pdf->SetX($x);
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell($col_width, 4, date('M j, Y', strtotime($sig['date'])), 0, 1, 'C');
    }

    $count++;
    if ($count % 3 != 0) {
        $pdf->SetY($y_start);
    }
}

$filename = 'Canvass_' . $batch['pr_number'] . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $supplier['supplier_name']) . '.pdf';
$pdf->Output('I', $filename);
