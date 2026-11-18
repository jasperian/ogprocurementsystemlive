<?php
require __DIR__ . '/includes/fpdf/fpdf.php';
require __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/data/liquidation_helpers.php';
session_start();

$liquidationId = (int)($_GET['id'] ?? 0);
if ($liquidationId <= 0) {
    $caNumber = trim($_GET['ca_number'] ?? '');
    if ($caNumber !== '') {
        header('Location: print_cash_advance_liquidation.php?ca_number=' . urlencode($caNumber));
        exit;
    }

    die('Liquidation ID is required.');
}

$stmt = $pdo->prepare("
    SELECT
        l.*,
        (SELECT child.liquidation_number FROM liquidations child WHERE child.source_rfp_id = l.liquidation_id LIMIT 1) AS linked_rfp_liquidation_number,
        (SELECT child.status FROM liquidations child WHERE child.source_rfp_id = l.liquidation_id LIMIT 1) AS linked_rfp_liquidation_status,
        creator.full_name AS created_by_name,
        creator.position AS created_by_position,
        creator.user_signature AS created_by_signature,
        dept_approver.full_name AS department_approved_by_name,
        dept_approver.position AS department_approved_by_position,
        dept_approver.user_signature AS department_approved_by_signature,
        assigned_dept_approver.full_name AS department_assigned_approver_name,
        assigned_dept_approver.position AS department_assigned_approver_position,
        approver.full_name AS approved_by_name,
        approver.position AS approved_by_position,
        approver.user_signature AS approved_by_signature,
        liquidation_approver.full_name AS liquidation_approved_by_name,
        liquidation_approver.position AS liquidation_approved_by_position,
        liquidation_approver.user_signature AS liquidation_approved_by_signature,
        assigned_final_approver.full_name AS assigned_final_approver_name,
        assigned_final_approver.position AS assigned_final_approver_position,
        rejector.full_name AS rejected_by_name,
        rejector.position AS rejected_by_position,
        rejector.user_signature AS rejected_by_signature,
        completer.full_name AS completed_by_name,
        completer.position AS completed_by_position,
        completer.user_signature AS completed_by_signature
    FROM liquidations l
    LEFT JOIN users creator ON creator.user_id = l.created_by
    LEFT JOIN users dept_approver ON dept_approver.user_id = l.department_approved_by
    LEFT JOIN users assigned_dept_approver ON assigned_dept_approver.user_id = l.department_approver_user_id
    LEFT JOIN users approver ON approver.user_id = l.approved_by
    LEFT JOIN users liquidation_approver ON liquidation_approver.user_id = l.liquidation_approved_by
    LEFT JOIN users assigned_final_approver ON assigned_final_approver.user_id = l.final_approver_user_id
    LEFT JOIN users rejector ON rejector.user_id = l.rejected_by
    LEFT JOIN users completer ON completer.user_id = l.completed_by
    WHERE l.liquidation_id = ?
");
$stmt->execute([$liquidationId]);
$liquidation = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$liquidation) {
    die('Liquidation not found.');
}

$sourceRequest = null;
$sourceRequestType = !empty($liquidation['source_cash_advance_id']) ? 'Cash Advance' : 'Request for Payment';
$sourceRequestId = (int)(($liquidation['source_cash_advance_id'] ?? 0) ?: ($liquidation['source_rfp_id'] ?? 0));
if ($sourceRequestId > 0) {
    $stmt = $pdo->prepare("
        SELECT liquidation_id, liquidation_number, liquidation_amount
        FROM liquidations
        WHERE liquidation_id = ?
          AND request_type = ?
        LIMIT 1
    ");
    $stmt->execute([$sourceRequestId, $sourceRequestType]);
    $sourceRequest = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$budgetMonitoring = liquidation_get_budget_monitoring(
    $pdo,
    (int)$liquidation['budget_id'],
    (string)$liquidation['department'],
    (string)$liquidation['dealer']
);

$sessionDealer = $_SESSION['dealer'] ?? '';
$sessionUserId = (int)($_SESSION['user_id'] ?? 0);
$sessionPosition = $_SESSION['position'] ?? '';
$sessionDepartment = $_SESSION['department'] ?? '';
$isOwner = $sessionUserId === (int)$liquidation['created_by'];
$isAllowedDealer = $sessionDealer !== '' && (strtoupper($sessionDealer) === 'OG' || $sessionDealer === $liquidation['dealer']);
$isAccountingUser = in_array($sessionDepartment, ['ACCOUNTING', 'FINANCE'], true);
$isDepartmentManager = liquidation_department_approval_is_assigned_to_current_user($liquidation)
    && $isAllowedDealer;
$isDepartmentPic = liquidation_is_department_pic()
    && strtoupper(trim($sessionDepartment)) === strtoupper(trim($liquidation['department'] ?? ''))
    && $isAllowedDealer;
$assignedFinalApproverId = (int)($liquidation['final_approver_user_id'] ?? 0);
$isAssignedFinalApprover = $assignedFinalApproverId > 0 && $assignedFinalApproverId === $sessionUserId && $isAllowedDealer;
$isLegacyGeneralManagerApprover = $assignedFinalApproverId === 0 && $sessionPosition === 'General Manager' && $isAllowedDealer;

if (!$isOwner && !$isDepartmentManager && !$isDepartmentPic && !$isAssignedFinalApprover && !$isLegacyGeneralManagerApprover && !(liquidation_is_secretary() && $isAllowedDealer) && !($isAccountingUser && $isAllowedDealer)) {
    die('You are not allowed to view this liquidation form.');
}

$dealerInfo = [
    'dealer_name' => 'TOYOTA NORTH EDSA',
    'dealer_address' => '1010 EDSA, Quezon City, Metro Manila',
    'telephone' => '(02) 8-927-7215',
    'dealer_logo' => 'logo.png'
];

if (!empty($liquidation['dealer'])) {
    $stmt = $pdo->prepare("SELECT * FROM dealer_details WHERE dealer = ?");
    $stmt->execute([$liquidation['dealer']]);
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
    return 'PHP ' . number_format((float)$value, 2);
}

function add_signature_image(FPDF $pdf, string $path, float $x, float $y): void
{
    if (!is_file($path)) {
        return;
    }

    $imagePath = $path;
    $temporaryPath = null;
    $header = @file_get_contents($path, false, null, 0, 29);
    $isInterlacedPng = $header !== false
        && strlen($header) === 29
        && substr($header, 0, 8) === "\x89PNG\r\n\x1a\n"
        && ord($header[28]) !== 0;

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

        $temporaryPath = tempnam(sys_get_temp_dir(), 'liquidation_signature_');
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

function display_liquidation_status(array $liquidation, bool $isAccountingUser): string
{
    $status = (string)($liquidation['status'] ?? '');
    $requestType = (string)($liquidation['request_type'] ?? 'Liquidation');

    if ($requestType === 'Cash Advance') {
        if ($status === 'Liquidated') {
            return 'For Release';
        }
        if ($status === 'Completed') {
            return 'Released';
        }
    }

    if (liquidation_is_current_workflow($liquidation) && $requestType === 'Request for Payment') {
        if ($status === 'For Accounting Approval') {
            return 'For Accounting Completion';
        }
        if ($status === 'Completed') {
            return 'Completed';
        }
    }

    if (in_array($requestType, ['Liquidation', 'Request for Payment'], true)) {
        if ($status === 'Liquidated') {
            return $isAccountingUser ? 'For SAP Document Number' : 'Recommended';
        }
        if ($status === 'Completed') {
            return 'Liquidated';
        }
    }

    return $status;
}

class StandaloneLiquidationPDF extends FPDF
{
    public $dealerInfo = [];
    public $liquidationNumber = '';
    public $requestType = 'Liquidation';
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
        $this->Cell(0, 7, pdf_text(strtoupper($this->requestType) . ' FORM'), 0, 1, 'C');

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

    function pairedValues(array $items, $height = 6)
    {
        $labelWidth = 31;
        $valueWidth = 64;

        foreach (array_chunk($items, 2) as $row) {
            $wrappedValues = [];
            $rowHeight = $height;
            $this->SetFont('Arial', '', 8);
            foreach ($row as $item) {
                $valueText = trim(pdf_text($item[1] ?? ''));
                $lines = [];
                $line = '';
                foreach (str_split($valueText) as $character) {
                    $candidate = $line . $character;
                    if ($line !== '' && $this->GetStringWidth($candidate) > $valueWidth - 4) {
                        $lines[] = $line;
                        $line = $character === ' ' ? '' : $character;
                    } else {
                        $line = $candidate;
                    }
                }
                $lines[] = $line === '' ? ' ' : $line;
                $wrappedValues[] = $lines;
                $rowHeight = max($rowHeight, count($lines) * 4 + 2);
            }

            $this->ensureSpace($rowHeight);
            $rowX = $this->GetX();
            $rowY = $this->GetY();
            foreach ($row as $index => $item) {
                $x = $rowX + $index * ($labelWidth + $valueWidth);
                $this->SetXY($x, $rowY);
                $this->SetFont('Arial', 'B', 8);
                $this->Cell($labelWidth, $rowHeight, pdf_text($item[0]), 1, 0, 'L');
                $this->Rect($x + $labelWidth, $rowY, $valueWidth, $rowHeight);
                $this->SetXY($x + $labelWidth + 2, $rowY + 1);
                $this->SetFont('Arial', '', 8);
                foreach ($wrappedValues[$index] as $line) {
                    $this->Cell($valueWidth - 4, 4, $line, 0, 2,
                        strpos((string)$item[1], 'PHP ') === 0 ? 'R' : 'L');
                }
            }
            if (count($row) === 1) {
                $this->Rect($rowX + $labelWidth + $valueWidth, $rowY,
                    $labelWidth + $valueWidth, $rowHeight);
            }
            $this->SetXY($rowX, $rowY + $rowHeight);
        }
    }

    function sectionTitle($title)
    {
        $this->SetFillColor(235, 235, 235);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(190, 6, pdf_text($title), 1, 1, 'L', true);
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
}

$pdf = new StandaloneLiquidationPDF();
$pdf->AliasNbPages();
$pdf->dealerInfo = $dealerInfo;
$pdf->liquidationNumber = $liquidation['liquidation_number'];
$pdf->requestType = $liquidation['request_type'] ?? 'Liquidation';
$pdf->AddPage();

$pdf->sectionTitle(($liquidation['request_type'] ?? 'Liquidation') . ' Details');
$detailItems = [
    ['Request No.', $liquidation['liquidation_number']],
    ['Request Type', $liquidation['request_type'] ?? 'Liquidation'],
    ['Department', $liquidation['department']],
    ['Budget Code', $liquidation['budget_code']],
    ['Budget Activity', $liquidation['budget_activity'] ?: 'N/A'],
    ['Content', $liquidation['content_name'] ?: 'N/A'],
    ['Liquidation Date', format_date_value($liquidation['liquidation_date'])],
    ['Activity Date', format_date_value($liquidation['activity_date'] ?? null)],
];
if (($liquidation['request_type'] ?? '') === 'Request for Payment') {
    $detailItems[] = ['Payable To', $liquidation['rfp_department_head_name'] ?: 'N/A'];
    $detailItems[] = ['Liquidation Requirement', !isset($liquidation['requires_liquidation']) ? 'Unclassified' : ((int)$liquidation['requires_liquidation'] === 1 ? 'Requires Liquidation' : 'Direct Payment / No Liquidation')];
    if (!empty($liquidation['linked_rfp_liquidation_number'])) {
        $detailItems[] = ['Linked Liquidation', $liquidation['linked_rfp_liquidation_number'] . ' (' . $liquidation['linked_rfp_liquidation_status'] . ')'];
    }
}
$detailItems[] = ['Amount', money_value($liquidation['liquidation_amount'])];
$detailItems[] = ['Status', display_liquidation_status($liquidation, $isAccountingUser)];
if (liquidation_requires_sap($liquidation) || !empty($liquidation['sap_document_number'])) {
    $detailItems[] = ['SAP Document No.', $liquidation['sap_document_number'] ?: 'N/A'];
}
$detailItems[] = ['Attachment', !empty($liquidation['attachment_path']) ? 'Attached' : 'None'];
$pdf->pairedValues($detailItems);

if ($sourceRequest) {
    $sourceShortLabel = $sourceRequestType === 'Cash Advance' ? 'CA' : 'RFP';
    $requestedAmount = (float)$sourceRequest['liquidation_amount'];
    $actualAmount = isset($liquidation['actual_amount']) && $liquidation['actual_amount'] !== null
        ? (float)$liquidation['actual_amount']
        : (float)$liquidation['liquidation_amount'];
    $difference = round($actualAmount - $requestedAmount, 2);
    $returnAmount = max(-$difference, 0);
    $paymentAmount = max($difference, 0);

    $pdf->ensureSpace(26);
    $pdf->Ln(2);
    $pdf->sectionTitle($sourceRequestType . ' Liquidation Summary');
    $pdf->pairedValues([
        ['Source ' . $sourceShortLabel . ' No.', $sourceRequest['liquidation_number']],
        [$sourceShortLabel . ' Amount', money_value($requestedAmount)],
        ['Actual Amount', money_value($actualAmount)],
        ['Cash to Return', money_value($returnAmount)],
        ['For Payment', money_value($paymentAmount)],
    ]);
}

$pdf->Ln(2);
$pdf->sectionTitle('Budget Report');

if ($budgetMonitoring) {
    $totalCommitments =
        (float)$budgetMonitoring['actual_expense']
        + (float)$budgetMonitoring['active_cash_advance']
        + (float)$budgetMonitoring['committed_liquidations'];

    $pdf->pairedValues([
        ['Approved Budget', money_value($budgetMonitoring['approved_budget'])],
        ['Expenses', money_value($budgetMonitoring['actual_expense'])],
        ['Pending CAs', money_value($budgetMonitoring['active_cash_advance'])],
        ['Other Pending', money_value($budgetMonitoring['committed_liquidations'])],
        ['Transactions', money_value($totalCommitments)],
        ['Remaining', money_value($budgetMonitoring['remaining_budget'])],
    ]);
} else {
    $pdf->labelValue('Budget Report', 'Unavailable');
}

$pdf->Ln(2);
$pdf->sectionTitle('Remarks');
$pdf->SetFont('Arial', '', 8);
$remarks = trim($liquidation['remarks'] ?? '');
$pdf->MultiCell(190, 5, pdf_text($remarks !== '' ? $remarks : 'N/A'), 1, 'L');

if ($liquidation['status'] === 'Rejected') {
    $pdf->Ln(4);
    $pdf->sectionTitle('Rejection Reason');
    $pdf->SetFont('Arial', '', 10);
    $pdf->MultiCell(190, 7, pdf_text($liquidation['rejection_reason'] ?: 'N/A'), 1, 'L');
}

$pdf->Ln(7);

$signatories = [
    [
        'label' => 'Prepared By',
        'name' => $liquidation['created_by_name'] ?: '',
        'position' => $liquidation['created_by_position'] ?: '',
        'signature' => $liquidation['created_by_signature'] ?: '',
        'date' => $liquidation['submitted_at'] ?? ''
    ],
];

if (!empty($liquidation['department_approved_by']) || !empty($liquidation['department_approver_user_id'])) {
    $signatories[] = [
        'label' => 'Noted By',
        'name' => $liquidation['department_approved_by_name']
            ?: ($liquidation['department_assigned_approver_name'] ?: ''),
        'position' => $liquidation['department_approved_by_position']
            ?: ($liquidation['department_assigned_approver_position'] ?: ''),
        'signature' => $liquidation['department_approved_by_signature'] ?: '',
        'date' => $liquidation['department_approved_at'] ?? ''
    ];
}

$requiresFinalApprover = liquidation_requires_final_approval($liquidation);
if ($requiresFinalApprover) {
    $rejectedAtFinalStage = $liquidation['status'] === 'Rejected'
        && (!empty($liquidation['department_approved_at'])
            || ($liquidation['created_by_position'] ?? '') === 'Manager');
    $signatories[] = [
        'label' => $rejectedAtFinalStage ? 'Rejected By' : 'Approved By',
        'name' => ($rejectedAtFinalStage
            ? $liquidation['rejected_by_name']
            : ($liquidation['approved_by_name']
                ?: ($liquidation['liquidation_approved_by_name']
                    ?: $liquidation['assigned_final_approver_name']))) ?: '',
        'position' => ($rejectedAtFinalStage
            ? $liquidation['rejected_by_position']
            : ($liquidation['approved_by_position']
                ?: ($liquidation['liquidation_approved_by_position']
                    ?: $liquidation['assigned_final_approver_position']))) ?: '',
        'signature' => ($rejectedAtFinalStage
            ? $liquidation['rejected_by_signature']
            : ($liquidation['approved_by_signature'] ?: $liquidation['liquidation_approved_by_signature'])) ?: '',
        'date' => $rejectedAtFinalStage
            ? ($liquidation['rejected_at'] ?? '')
            : ($liquidation['approved_at'] ?? $liquidation['liquidation_approved_at'] ?? '')
    ];
}

if ($liquidation['status'] === 'Completed') {
    $signatories[] = [
        'label' => 'Verified By',
        'name' => $liquidation['completed_by_name'] ?: '',
        'position' => $liquidation['completed_by_position'] ?: '',
        'signature' => $liquidation['completed_by_signature'] ?: '',
        'date' => $liquidation['completed_at'] ?? ''
    ];
}

$signatureRows = [$signatories];
$signatureRowHeight = 36;
$pdf->ensureSpace(count($signatureRows) * $signatureRowHeight);
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

        if (!empty($sig['signature'])) {
            add_signature_image($pdf, $sig['signature'], $x + (($colWidth - 20) / 2), $rowY + 5);
        }

        $pdf->Line($x + 3, $rowY + 21, $x + $colWidth - 3, $rowY + 21);
        $pdf->SetXY($x, $rowY + 22);
        $pdf->fitCell($colWidth, 4, $sig['name'], 0, 1, 'C', 'B', 8, 6);
        $pdf->SetX($x);
        $pdf->fitCell($colWidth, 4, $sig['position'], 0, 1, 'C', '', 7, 5.5);

        if (!empty($sig['date'])) {
            $pdf->SetX($x);
            $pdf->fitCell($colWidth, 4, format_date_value($sig['date']), 0, 1, 'C', 'I', 7, 5.5);
        }
    }
}

$pdf->SetY($yStart + (count($signatureRows) * $signatureRowHeight));

$filename = preg_replace('/[^A-Za-z0-9_\-]/', '_', $liquidation['request_type'] ?? 'Liquidation')
    . '_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', $liquidation['liquidation_number']) . '.pdf';
$pdf->Output('I', $filename);
