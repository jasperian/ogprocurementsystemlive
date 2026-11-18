<?php
require 'includes/fpdf/fpdf.php';
require '../includes/db.php';
session_start();

$caNumber = trim($_GET['ca_number'] ?? '');
if ($caNumber === '') {
    die('Cash Advance number is required.');
}

$stmt = $pdo->prepare("
    SELECT 
        ca.*, 
        u.full_name AS created_by_name, u.position AS created_by_position, u.user_signature AS created_by_signature,
        prepared.full_name AS prepared_by_name, prepared.position AS prepared_by_position, prepared.user_signature AS prepared_by_signature,
        liquidated.full_name AS liquidated_by_name, liquidated.position AS liquidated_by_position, liquidated.user_signature AS liquidated_by_signature,
        completed.full_name AS completed_by_name, completed.position AS completed_by_position, completed.user_signature AS completed_by_signature,
        gm.full_name AS gm_approved_by_name, gm.position AS gm_approved_by_position, gm.user_signature AS gm_approved_by_signature
    FROM cash_advance ca
    LEFT JOIN users u ON ca.created_by = u.user_id
    LEFT JOIN users prepared ON ca.for_liquidation_by = prepared.user_id
    LEFT JOIN users liquidated ON ca.liquidated_by = liquidated.user_id
    LEFT JOIN users completed ON ca.completed_by = completed.user_id
    LEFT JOIN users gm ON ca.gm_approved_by = gm.user_id
    WHERE ca.pr_number = ?
");
$stmt->execute([$caNumber]);
$cashAdvance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cashAdvance) {
    die('Cash Advance not found.');
}

$sessionDealer = $_SESSION['dealer'] ?? '';
if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG' && $sessionDealer !== ($cashAdvance['pr_dealer'] ?? '')) {
    die('You are not allowed to view this Liquidation form.');
}

$dealerInfo = [
    'dealer_name' => 'TOYOTA NORTH EDSA',
    'dealer_address' => '1010 EDSA, Quezon City, Metro Manila',
    'telephone' => '(02) 8-927-7215',
    'dealer_logo' => 'logo.png'
];

if (!empty($cashAdvance['pr_dealer'])) {
    $stmt = $pdo->prepare("SELECT * FROM dealer_details WHERE dealer = ?");
    $stmt->execute([$cashAdvance['pr_dealer']]);
    $fetchedDealer = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($fetchedDealer) {
        $dealerInfo = array_merge($dealerInfo, $fetchedDealer);
        $dealerInfo['dealer_logo'] = basename($dealerInfo['dealer_logo'] ?? 'logo.png');
    }
}

function pdf_text($value): string
{
    $text = (string)($value ?? '');
    $converted = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
    return $converted !== false ? $converted : $text;
}

function format_date_value($value): string
{
    if (empty($value)) {
        return 'N/A';
    }

    $timestamp = strtotime($value);
    return $timestamp ? date('F j, Y', $timestamp) : (string)$value;
}

function money_value($value): string
{
    return 'PHP ' . number_format((float)($value ?? 0), 2);
}

class CashAdvanceLiquidationPDF extends FPDF
{
    public $dealerInfo = [];
    public $caNumber = '';
    public $formTitle = 'LIQUIDATION FORM';
    protected $angle = 0;

    function Header()
    {
        $this->Watermark();

        $logo = $this->dealerInfo['dealer_logo'] ?? 'logo.png';
        if ($logo && file_exists('assets/images/' . $logo)) {
            $this->Image('assets/images/' . $logo, 10, 10, 30);
        }

        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 5, pdf_text(strtoupper($this->dealerInfo['dealer_name'] ?? '')), 0, 1, 'C');

        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 4, pdf_text($this->dealerInfo['dealer_address'] ?? ''), 0, 1, 'C');
        $this->Cell(0, 4, pdf_text('Tel: ' . ($this->dealerInfo['telephone'] ?? '')), 0, 1, 'C');

        $this->Ln(5);
        $this->SetFont('Arial', 'B', 14);
        $this->Cell(0, 7, pdf_text($this->formTitle), 0, 1, 'C');

        $this->Ln(2);
        $this->SetDrawColor(0, 0, 0);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->Ln(4);
    }

    function Footer()
    {
        $this->SetY(-20);
        $this->Line(10, $this->GetY(), 200, $this->GetY());
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'This is a system generated document from OGPS.', 0, 1, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
    }

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
        $this->SetTextColor(220, 220, 220);
        $this->SetFont('Arial', 'B', 110);
        $pageCenterX = $this->w / 2;
        $pageCenterY = $this->h / 2;
        $textX = $pageCenterX - ($this->GetStringWidth('OGPS') / 2);
        $textY = $pageCenterY + 12;
        $this->Rotate(45, $pageCenterX, $pageCenterY);
        $this->Text($textX, $textY, 'OGPS');
        $this->Rotate(0);
        $this->SetTextColor(0, 0, 0);
    }

    function labelValue($label, $value, $labelWidth = 55, $valueWidth = 135, $height = 8)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($labelWidth, $height, pdf_text($label), 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell($valueWidth, $height, pdf_text($value), 1, 1, 'L');
    }

    function ensureSpace($height)
    {
        if ($this->GetY() + $height > ($this->h - $this->bMargin)) {
            $this->AddPage();
        }
    }

    function fitCell($width, $height, $text, $border = 0, $ln = 1, $align = 'C', $style = '', $maxSize = 8, $minSize = 6)
    {
        $text = pdf_text($text ?: ' ');
        $fontSize = $maxSize;

        do {
            $this->SetFont('Arial', $style, $fontSize);
            $fontSize -= 0.5;
        } while ($this->GetStringWidth($text) > ($width - 2) && $fontSize >= $minSize);

        $this->Cell($width, $height, $text, $border, $ln, $align);
    }

    function sectionTitle($title)
    {
        $this->SetFillColor(235, 235, 235);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(190, 6, pdf_text($title), 1, 1, 'L', true);
    }

    function pairedValues(array $items, $height = 6)
    {
        $labelWidth = 31;
        $valueWidth = 64;
        foreach (array_chunk($items, 2) as $row) {
            foreach ($row as $item) {
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($labelWidth, $height, pdf_text($item[0]), 1, 0, 'L');
                $alignment = strpos((string)$item[1], 'PHP ') === 0 ? 'R' : 'L';
                $this->fitCell($valueWidth, $height, $item[1], 1, 0, $alignment, '', 8, 5.5);
            }
            if (count($row) === 1) {
                $this->Cell($labelWidth + $valueWidth, $height, '', 1, 0);
            }
            $this->Ln($height);
        }
    }
}

$caAmount = (float)($cashAdvance['ca_amount'] ?? 0);
$liquidatedAmount = (float)($cashAdvance['po_amount'] ?? 0);
$returnAmount = max($caAmount - $liquidatedAmount, 0);
$reimbursementAmount = max($liquidatedAmount - $caAmount, 0);
$requestType = $cashAdvance['request_type'] ?? 'Cash Advance';
$isRfp = $requestType === 'Request for Payment';
$requestShortLabel = $isRfp ? 'RFP' : 'CA';
$requestLongLabel = $isRfp ? 'Request for Payment' : 'Cash Advance';

$pdf = new CashAdvanceLiquidationPDF();
$pdf->AliasNbPages();
$pdf->dealerInfo = $dealerInfo;
$pdf->caNumber = $cashAdvance['pr_number'];
$pdf->formTitle = $isRfp ? 'REQUEST FOR PAYMENT LIQUIDATION FORM' : 'LIQUIDATION FORM';
$pdf->AddPage();

$purchaseRequisitionNumber = trim((string)($cashAdvance['purchase_requisition_number'] ?? ''));
$pdf->sectionTitle('Reference Details');
$pdf->pairedValues([
    [$requestShortLabel . ' Number', $cashAdvance['pr_number']],
    ['Request Type', $requestLongLabel],
    [$requestShortLabel . ' Date', format_date_value($cashAdvance['ca_date'] ?? $cashAdvance['pr_date'] ?? '')],
    ['PR Number', $purchaseRequisitionNumber !== '' ? $purchaseRequisitionNumber : 'N/A'],
    ['Department', $cashAdvance['pr_department'] ?? 'N/A'],
    ['Budget Code', $cashAdvance['budget_code'] ?? 'N/A'],
    ['Dealer', $cashAdvance['pr_dealer'] ?? 'N/A'],
    ['Status', $cashAdvance['pr_status'] ?? 'N/A'],
]);

$pdf->Ln(2);
$pdf->sectionTitle('Liquidation Summary');
$pdf->pairedValues([
    [$requestShortLabel . ' Amount', money_value($caAmount)],
    ['Liquidated / PO', money_value($liquidatedAmount)],
    ['Cash to Return', money_value($returnAmount)],
    ['For Payment', money_value($reimbursementAmount)],
    ['SAP Document No.', !empty($cashAdvance['sap_document_number']) ? $cashAdvance['sap_document_number'] : 'N/A'],
    ['PO Attachment', !empty($cashAdvance['po_file']) ? basename($cashAdvance['po_file']) : 'N/A'],
]);

$truncateRemarks = static function ($value, $limit = 420) {
    $text = trim((string)$value) ?: 'N/A';
    if (function_exists('mb_strlen') && mb_strlen($text) > $limit) {
        return mb_substr($text, 0, $limit - 3) . '...';
    }
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
};

$pdf->Ln(2);
$pdf->sectionTitle($requestLongLabel . ' Remarks');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(190, 5, pdf_text($truncateRemarks($cashAdvance['remarks'] ?? '')), 1, 'L');

$pdf->Ln(2);
$pdf->sectionTitle('Liquidation Remarks');
$pdf->SetFont('Arial', '', 8);
$pdf->MultiCell(190, 5, pdf_text($truncateRemarks($cashAdvance['liquidation_remarks'] ?? '')), 1, 'L');

$pdf->Ln(7);

$signatories = [
    [
        'label' => 'Prepared By',
        'name' => ($cashAdvance['prepared_by_name'] ?: $cashAdvance['created_by_name']) ?: '',
        'position' => ($cashAdvance['prepared_by_position'] ?: $cashAdvance['created_by_position']) ?: '',
        'signature' => ($cashAdvance['prepared_by_signature'] ?: $cashAdvance['created_by_signature']) ?: '',
        'date' => $cashAdvance['for_liquidation_at'] ?? $cashAdvance['created_at'] ?? $cashAdvance['pr_date'] ?? ''
    ],
    [
        'label' => 'Liquidated By',
        'name' => $cashAdvance['liquidated_by_name'] ?: '',
        'position' => $cashAdvance['liquidated_by_position'] ?: '',
        'signature' => $cashAdvance['liquidated_by_signature'] ?: '',
        'date' => $cashAdvance['liquidated_at'] ?? ''
    ],
    [
        'label' => !empty($cashAdvance['gm_approved_at']) ? 'GM Approved By' : 'Approved By',
        'name' => ($cashAdvance['gm_approved_by_name'] ?: $cashAdvance['completed_by_name']) ?: '',
        'position' => ($cashAdvance['gm_approved_by_position'] ?: $cashAdvance['completed_by_position']) ?: '',
        'signature' => ($cashAdvance['gm_approved_by_signature'] ?: $cashAdvance['completed_by_signature']) ?: '',
        'date' => $cashAdvance['gm_approved_at'] ?? $cashAdvance['completed_at'] ?? ''
    ],
];

$signatureRows = [$signatories];
$signatureRowHeight = 36;
$yStart = $pdf->GetY();

foreach ($signatureRows as $rowIndex => $rowSignatories) {
    $rowCount = count($rowSignatories);
    $colWidth = $rowCount === 1 ? 80 : min(58, 184 / $rowCount);
    $colGap = $rowCount === 1 ? 0 : 2;
    $totalRowWidth = ($rowCount * $colWidth) + (($rowCount - 1) * $colGap);
    $xStart = 10 + ((190 - $totalRowWidth) / 2);
    $rowY = $yStart + ($rowIndex * $signatureRowHeight);

    foreach ($rowSignatories as $index => $sig) {
        $x = $xStart + ($index * ($colWidth + $colGap));
        $pdf->SetXY($x, $rowY);
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->Cell($colWidth, 5, pdf_text($sig['label']), 0, 1, 'C');

        if (!empty($sig['signature']) && file_exists($sig['signature'])) {
            $pdf->Image($sig['signature'], $x + (($colWidth - 20) / 2), $rowY + 5, 20);
        }

        $pdf->Line($x + 4, $rowY + 21, $x + $colWidth - 4, $rowY + 21);
        $pdf->SetXY($x, $rowY + 22);
        $pdf->fitCell($colWidth, 4, $sig['name'], 0, 1, 'C', 'B', 8, 5.5);
        $pdf->SetX($x);
        $pdf->fitCell($colWidth, 4, $sig['position'], 0, 1, 'C', '', 7, 5.5);

        if (!empty($sig['date'])) {
            $pdf->SetX($x);
            $pdf->fitCell($colWidth, 4, format_date_value($sig['date']), 0, 1, 'C', 'I', 7, 5.5);
        }
    }
}

$pdf->SetY($yStart + (count($signatureRows) * $signatureRowHeight));

$filename = ($isRfp ? 'RFP_Liquidation_' : 'Cash_Advance_Liquidation_') . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cashAdvance['pr_number']) . '.pdf';
$pdf->Output('I', $filename);
