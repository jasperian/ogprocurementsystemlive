<?php
require 'includes/fpdf/fpdf.php';
require '../includes/db.php';
require_once __DIR__ . '/data/liquidation_helpers.php';
session_start();

function add_cash_advance_signature_image(FPDF $pdf, string $path, float $x, float $y): void
{
    if (!is_file($path)) {
        return;
    }

    $header = @file_get_contents($path, false, null, 0, 29);
    $isInterlacedPng = $header !== false
        && strlen($header) === 29
        && substr($header, 0, 8) === "\x89PNG\r\n\x1a\n"
        && ord($header[28]) !== 0;
    $imagePath = $path;
    $temporaryPath = null;

    if ($isInterlacedPng) {
        if (!function_exists('imagecreatefrompng') || !function_exists('imagepng')) {
            error_log('Cannot render interlaced signature PNG without PHP GD: ' . $path);
            return;
        }

        $image = @imagecreatefrompng($path);
        if ($image === false) {
            error_log('Cannot convert signature PNG: ' . $path);
            return;
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'cash_advance_signature_');
        if ($temporaryPath === false) {
            imagedestroy($image);
            error_log('Cannot create temporary signature PNG: ' . $path);
            return;
        }

        imageinterlace($image, false);
        imagesavealpha($image, true);
        $converted = @imagepng($image, $temporaryPath);
        imagedestroy($image);
        if (!$converted) {
            unlink($temporaryPath);
            error_log('Cannot save converted signature PNG: ' . $path);
            return;
        }
        $imagePath = $temporaryPath;
    }

    try {
        $pdf->Image($imagePath, $x, $y, 20, 0, $isInterlacedPng ? 'PNG' : '');
    } finally {
        if ($temporaryPath !== null) {
            unlink($temporaryPath);
        }
    }
}

$caNumber = trim($_GET['ca_number'] ?? '');
if ($caNumber === '') {
    die('Cash Advance number is required.');
}

$stmt = $pdo->prepare("
    SELECT 
        ca.*, 
        u.full_name AS created_by_name, u.position AS created_by_position, u.user_signature AS created_by_signature,
        released.full_name AS released_by_name, released.position AS released_by_position, released.user_signature AS released_by_signature,
        checked.full_name AS checked_by_name, checked.position AS checked_by_position, checked.user_signature AS checked_by_signature,
        gm.full_name AS gm_approved_by_name, gm.position AS gm_approved_by_position, gm.user_signature AS gm_approved_by_signature
    FROM cash_advance ca
    LEFT JOIN users u ON ca.created_by = u.user_id
    LEFT JOIN users released ON ca.ca_released_by = released.user_id
    LEFT JOIN users checked ON ca.for_po_by = checked.user_id
    LEFT JOIN users gm ON ca.gm_approved_by = gm.user_id
    WHERE ca.pr_number = ?
");
$stmt->execute([$caNumber]);
$cashAdvance = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cashAdvance) {
    die('Cash Advance not found.');
}

$allocations = [];
$hasAllocationsTable = (bool)$pdo->query("SHOW TABLES LIKE 'cash_advance_allocations'")->fetchColumn();
if ($hasAllocationsTable) {
    $allocationStmt = $pdo->prepare("
        SELECT caa.*, b.budget_activity
        FROM cash_advance_allocations caa
        LEFT JOIN budgets b ON b.budget_id = caa.budget_id
        WHERE caa.cash_advance_number = ?
        ORDER BY caa.allocation_id
    ");
    $allocationStmt->execute([$caNumber]);
    $allocations = $allocationStmt->fetchAll(PDO::FETCH_ASSOC);
}

$sessionDealer = $_SESSION['dealer'] ?? '';
if ($sessionDealer !== '' && strtoupper($sessionDealer) !== 'OG' && $sessionDealer !== ($cashAdvance['pr_dealer'] ?? '')) {
    die('You are not allowed to view this Cash Advance form.');
}

$budgetMonitoring = null;
$budgetCode = trim((string)($cashAdvance['budget_code'] ?? ''));
$department = trim((string)($cashAdvance['pr_department'] ?? ''));
$dealer = trim((string)($cashAdvance['pr_dealer'] ?? ''));
$budgetId = (int)($cashAdvance['budget_id'] ?? 0);

if ($budgetId > 0) {
    $budgetStmt = $pdo->prepare("SELECT budget_department, budget_dealer FROM budgets WHERE budget_id = :budget_id");
    $budgetStmt->execute([':budget_id' => $budgetId]);
    $budgetOwner = $budgetStmt->fetch(PDO::FETCH_ASSOC);
    if ($budgetOwner) {
        $budgetMonitoring = liquidation_get_budget_monitoring(
            $pdo,
            $budgetId,
            $budgetOwner['budget_department'],
            $budgetOwner['budget_dealer']
        );
    }
} elseif ($budgetCode !== '' && $department !== '') {
    $budgetSql = "
        SELECT budget_id
        FROM budgets
        WHERE budget_code = :budget_code
          AND UPPER(TRIM(budget_department)) = UPPER(TRIM(:department))
    ";
    $budgetParams = [
        ':budget_code' => $budgetCode,
        ':department' => $department
    ];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $budgetSql .= " AND budget_dealer = :dealer";
        $budgetParams[':dealer'] = $dealer;
    }

    $budgetSql .= " ORDER BY budget_id DESC LIMIT 1";
    $budgetStmt = $pdo->prepare($budgetSql);
    $budgetStmt->execute($budgetParams);
    $budgetId = (int)$budgetStmt->fetchColumn();

    if ($budgetId > 0) {
        $budgetMonitoring = liquidation_get_budget_monitoring($pdo, $budgetId, $department, $dealer);
    }
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

class CashAdvancePDF extends FPDF
{
    public $dealerInfo = [];
    public $caNumber = '';
    public $requestTitle = 'CASH ADVANCE REQUEST FORM (CARF)';
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
        $this->Cell(0, 7, pdf_text($this->requestTitle), 0, 1, 'C');

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

    function labelValue($label, $value, $labelWidth = 55, $valueWidth = 135, $height = 7)
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($labelWidth, $height, pdf_text($label), 1, 0, 'L');
        $this->SetFont('Arial', '', 10);
        $this->Cell($valueWidth, $height, pdf_text($value), 1, 1, 'L');
    }

    function sectionTitle($title)
    {
        $this->SetFillColor(235, 235, 235);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(190, 6, pdf_text($title), 1, 1, 'L', true);
    }

    function fitCell($width, $height, $text, $border = 0, $ln = 1, $align = 'C', $style = '', $maxSize = 8, $minSize = 5.5)
    {
        $text = pdf_text($text ?: ' ');
        $fontSize = $maxSize;
        do {
            $this->SetFont('Arial', $style, $fontSize);
            $fontSize -= 0.5;
        } while ($this->GetStringWidth($text) > ($width - 2) && $fontSize >= $minSize);
        $this->Cell($width, $height, $text, $border, $ln, $align);
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

    function ensureSpace($height)
    {
        if ($this->GetY() + $height > $this->PageBreakTrigger) {
            $this->AddPage();
        }
    }
}

$pdf = new CashAdvancePDF();
$pdf->AliasNbPages();
$pdf->dealerInfo = $dealerInfo;
$pdf->caNumber = $cashAdvance['pr_number'];
$requestType = $cashAdvance['request_type'] ?? 'Cash Advance';
$isRfp = $requestType === 'Request for Payment';
$isLiquidation = $requestType === 'Liquidation';
$requestShortLabel = $isRfp ? 'RFP' : ($isLiquidation ? 'Liquidation' : 'CA');
$requestLongLabel = $isRfp ? 'Request for Payment' : $requestType;
$pdf->requestTitle = $isRfp ? 'REQUEST FOR PAYMENT FORM (RFP)' : ($isLiquidation ? 'LIQUIDATION FORM' : 'CASH ADVANCE REQUEST FORM (CARF)');
$pdf->AddPage();

$pdf->sectionTitle($requestLongLabel . ' Details');
$purchaseRequisitionNumber = trim((string)($cashAdvance['purchase_requisition_number'] ?? ''));
$detailItems = [
    [$requestShortLabel . ' Number', $cashAdvance['pr_number']],
    ['Request Type', $requestLongLabel],
    ['Department', $cashAdvance['pr_department'] ?? 'N/A'],
    ['Dealer', $cashAdvance['pr_dealer'] ?? 'N/A'],
    ['Budget Code', $cashAdvance['budget_code'] ?? 'N/A'],
    ['Budget Activity', $budgetMonitoring['budget_activity'] ?? 'N/A'],
    [$requestShortLabel . ' Date', format_date_value($cashAdvance['ca_date'] ?? $cashAdvance['pr_date'] ?? '')],
    ['PR Number', $purchaseRequisitionNumber !== '' ? $purchaseRequisitionNumber : 'N/A'],
];
if ($isRfp) {
    $detailItems[] = ['Payable To', $cashAdvance['payable_to'] ?? 'N/A'];
    $requiresLiquidation = $cashAdvance['requires_liquidation'] ?? null;
    $detailItems[] = ['Settlement', $requiresLiquidation === null ? 'Unclassified' : ((int)$requiresLiquidation === 1 ? 'Requires Liquidation' : 'Direct Payment')];
    if ((int)$requiresLiquidation === 1) {
        $detailItems[] = ['Liquidation Due', format_date_value($cashAdvance['liquidation_due_date'] ?? '')];
    } elseif ($requiresLiquidation !== null) {
        $detailItems[] = ['Exemption Reason', $cashAdvance['liquidation_exemption_reason'] ?? 'N/A'];
    }
    $detailItems[] = ['GM Approval', !empty($cashAdvance['gm_approved_at']) ? 'Approved - ' . format_date_value($cashAdvance['gm_approved_at']) : 'Required / Pending'];
}
if ($isLiquidation) {
    $detailItems[] = ['Activity Date', format_date_value($cashAdvance['activity_date'] ?? '')];
    $detailItems[] = ['Content', trim((string)($cashAdvance['content_name'] ?? '')) ?: 'N/A'];
}
$detailItems[] = [$requestShortLabel . ' Amount', 'PHP ' . number_format((float)($cashAdvance['ca_amount'] ?? 0), 2)];
$detailItems[] = ['Status', $cashAdvance['pr_status'] ?? 'N/A'];
$detailItems[] = ['Attachment', trim((string)($cashAdvance['attachment'] ?? '')) !== '' ? basename($cashAdvance['attachment']) : 'None'];
$pdf->pairedValues($detailItems);

$pdf->Ln(2);
if ($allocations) {
    $pdf->sectionTitle('Budget Information');
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell(40, 6, 'Department', 1, 0, 'C');
    $pdf->Cell(45, 6, 'Budget Code', 1, 0, 'C');
    $pdf->Cell(60, 6, 'Purchase Requisition', 1, 0, 'C');
    $pdf->Cell(45, 6, 'Amount', 1, 1, 'C');
    foreach (array_slice($allocations, 0, 6) as $allocation) {
        $pdf->fitCell(40, 6, $allocation['department'] ?: 'N/A', 1, 0, 'L');
        $pdf->fitCell(45, 6, $allocation['budget_code'] ?: 'N/A', 1, 0, 'L');
        $pdf->fitCell(60, 6, $allocation['purchase_requisition_number'] ?: 'N/A', 1, 0, 'L');
        $pdf->fitCell(45, 6, 'PHP ' . number_format((float)$allocation['allocation_amount'], 2), 1, 1, 'R');
    }
    if (count($allocations) > 6) {
        $pdf->SetFont('Arial', 'I', 7);
        $pdf->Cell(190, 5, pdf_text((count($allocations) - 6) . ' additional allocation(s) are recorded in the system.'), 1, 1, 'L');
    }
} else {
    $pdf->sectionTitle('Budget Report');
    if ($budgetMonitoring) {
        $totalCommitments = (float)$budgetMonitoring['actual_expense']
            + (float)$budgetMonitoring['active_cash_advance']
            + (float)$budgetMonitoring['committed_liquidations'];
        $pdf->pairedValues([
            ['Approved Budget', 'PHP ' . number_format((float)$budgetMonitoring['approved_budget'], 2)],
            ['Expenses', 'PHP ' . number_format((float)$budgetMonitoring['actual_expense'], 2)],
            ['Pending CAs', 'PHP ' . number_format((float)$budgetMonitoring['active_cash_advance'], 2)],
            ['Other Pending', 'PHP ' . number_format((float)$budgetMonitoring['committed_liquidations'], 2)],
            ['Transactions', 'PHP ' . number_format($totalCommitments, 2)],
            ['Remaining', 'PHP ' . number_format((float)$budgetMonitoring['remaining_budget'], 2)],
        ]);
    } else {
        $pdf->pairedValues([['Budget Report', 'Unavailable']]);
    }
}

$pdf->Ln(2);
$pdf->sectionTitle('Purpose / Remarks');
$pdf->SetFont('Arial', '', 8);
$remarks = trim((string)($cashAdvance['remarks'] ?? '')) ?: 'N/A';
$maxRemarksLength = 500;
if (function_exists('mb_strlen') && mb_strlen($remarks) > $maxRemarksLength) {
    $remarks = mb_substr($remarks, 0, $maxRemarksLength - 3) . '...';
} elseif (strlen($remarks) > $maxRemarksLength) {
    $remarks = substr($remarks, 0, $maxRemarksLength - 3) . '...';
}
$pdf->MultiCell(190, 5, pdf_text($remarks), 1, 'L');

$signatoryBlockHeight = 36;
$pdf->Ln(7);

$signatories = [
    [
        'label' => 'Requested By',
        'name' => $cashAdvance['created_by_name'] ?: '',
        'position' => $cashAdvance['created_by_position'] ?: '',
        'signature' => $cashAdvance['created_by_signature'] ?: '',
        'date' => $cashAdvance['created_at'] ?? $cashAdvance['pr_date'] ?? ''
    ],
];

$requiresEarlyGmApproval = $isRfp;
if ($requiresEarlyGmApproval) {
    $signatories[] = [
        'label' => 'Purchasing Noted By',
        'name' => $cashAdvance['released_by_name'] ?: '',
        'position' => $cashAdvance['released_by_position'] ?: '',
        'signature' => $cashAdvance['released_by_signature'] ?: '',
        'date' => $cashAdvance['ca_released_at'] ?? ''
    ];
    $signatories[] = [
        'label' => 'GM Approved By',
        'name' => $cashAdvance['gm_approved_by_name'] ?: '',
        'position' => $cashAdvance['gm_approved_by_position'] ?: '',
        'signature' => $cashAdvance['gm_approved_by_signature'] ?: '',
        'date' => $cashAdvance['gm_approved_at'] ?? ''
    ];
    $signatories[] = [
        'label' => 'Released By',
        'name' => $cashAdvance['checked_by_name'] ?: '',
        'position' => $cashAdvance['checked_by_position'] ?: '',
        'signature' => $cashAdvance['checked_by_signature'] ?: '',
        'date' => $cashAdvance['for_po_at'] ?? ''
    ];
} else {
    $signatories[] = [
        'label' => 'Approved By',
        'name' => $cashAdvance['released_by_name'] ?: '',
        'position' => $cashAdvance['released_by_position'] ?: '',
        'signature' => $cashAdvance['released_by_signature'] ?: '',
        'date' => $cashAdvance['ca_released_at'] ?? ''
    ];
    $signatories[] = [
        'label' => 'Checked By',
        'name' => $cashAdvance['checked_by_name'] ?: '',
        'position' => $cashAdvance['checked_by_position'] ?: '',
        'signature' => $cashAdvance['checked_by_signature'] ?: '',
        'date' => $cashAdvance['for_po_at'] ?? ''
    ];
}

$rowCount = count($signatories);
$colWidth = $rowCount === 1 ? 80 : min(58, 184 / $rowCount);
$colGap = $rowCount === 1 ? 0 : 2;
$totalRowWidth = ($rowCount * $colWidth) + (($rowCount - 1) * $colGap);
$xStart = 10 + ((190 - $totalRowWidth) / 2);
$yStart = $pdf->GetY();

foreach ($signatories as $index => $sig) {
    $x = $xStart + ($index * ($colWidth + $colGap));
    $pdf->SetXY($x, $yStart);
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->Cell($colWidth, 5, pdf_text($sig['label']), 0, 1, 'C');

    if (!empty($sig['signature'])) {
        add_cash_advance_signature_image($pdf, $sig['signature'], $x + (($colWidth - 20) / 2), $yStart + 5);
    }

    $pdf->Line($x + 4, $yStart + 21, $x + $colWidth - 4, $yStart + 21);
    $pdf->SetXY($x, $yStart + 22);
    $pdf->fitCell($colWidth, 4, $sig['name'] ?: ' ', 0, 1, 'C', 'B', 8, 5.5);
    $pdf->SetX($x);
    $pdf->fitCell($colWidth, 4, $sig['position'] ?: ' ', 0, 1, 'C', '', 7, 5.5);

    if (!empty($sig['date'])) {
        $pdf->SetX($x);
        $pdf->fitCell($colWidth, 4, format_date_value($sig['date']), 0, 1, 'C', 'I', 7, 5.5);
    }
}

$pdf->SetY($yStart + $signatoryBlockHeight);

$filename = ($isRfp ? 'RFP_' : ($isLiquidation ? 'Liquidation_' : 'Cash_Advance_')) . preg_replace('/[^A-Za-z0-9_\-]/', '_', $cashAdvance['pr_number']) . '.pdf';
$pdf->Output('I', $filename);
