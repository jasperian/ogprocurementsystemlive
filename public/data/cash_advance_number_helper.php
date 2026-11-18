<?php

function generateCashAdvanceNumber(PDO $pdo, string $dealer, string $requestType = 'Cash Advance'): string
{
    $dealerCode = strtoupper(trim($dealer));
    $dealerCode = preg_replace('/[^A-Z0-9]/', '', $dealerCode);

    if ($dealerCode === '') {
        $dealerCode = 'CA';
    }

    $isRfp = $requestType === 'Request for Payment';
    $isLiquidation = $requestType === 'Liquidation';
    $year = date('Y');
    $month = date('m');
    $yearPrefix = $isRfp
        ? $dealerCode . '-RFP-' . $year . '-'
        : ($isLiquidation ? $dealerCode . '-LIQ-' . $year . '-' : $dealerCode . '-' . $year . '-');
    $prefix = $yearPrefix . $month . '-';

    $stmt = $pdo->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(pr_number, '-', -1) AS UNSIGNED)) AS last_sequence
        FROM cash_advance
        WHERE pr_number LIKE ?
    ");
    $stmt->execute([$yearPrefix . '%']);

    $lastSequence = (int)($stmt->fetchColumn() ?: 0);

    return $prefix . str_pad((string)($lastSequence + 1), 3, '0', STR_PAD_LEFT);
}
