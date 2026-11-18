<?php
session_start();
require '../../includes/db.php';
require '../includes/po_approval_helpers.php';

po_approval_ensure_tables($pdo);

$batchId = (int)($_GET['batch_id'] ?? 0);
$supplierId = (int)($_GET['supplier_id'] ?? 0);
$itemIds = po_approval_parse_item_ids($_GET['item_ids'] ?? '');
$userId = $_SESSION['user_id'] ?? null;

if ($batchId <= 0 || $supplierId <= 0 || !$userId) {
    die('Invalid PO approval request.');
}

$stmt = $pdo->prepare("
    SELECT csb.*, pr.pr_dealer, pr.pr_status
    FROM canvass_sheet_batches csb
    LEFT JOIN purchase_requisition pr ON pr.pr_number = csb.pr_number
    WHERE csb.batch_id = ?
");
$stmt->execute([$batchId]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$batch) {
    die('Batch not found.');
}
if (($batch['pr_status'] ?? '') !== 'For PO') {
    die('PO approval requests can only be created after canvass approval is complete.');
}

$itemParams = [$batchId, $supplierId];
$itemFilter = '';
if (!empty($itemIds)) {
    $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
    $itemFilter = " AND ci.item_id IN ($placeholders)";
    $itemParams = array_merge($itemParams, $itemIds);
}

$stmt = $pdo->prepare("
    SELECT ci.*, i.item_name, i.unit_measure
    FROM canvass_items ci
    JOIN items i ON ci.item_id = i.item_id
    WHERE ci.batch_id = ? AND ci.supplier_id = ?$itemFilter
    ORDER BY i.item_name
");
$stmt->execute($itemParams);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (!$items) {
    die('No items found for this supplier.');
}

$stmt = $pdo->prepare("
    SELECT request_id
    FROM po_approval_requests
    WHERE batch_id = ? AND supplier_id = ? AND created_by = ? AND status = 'draft'
    ORDER BY request_id DESC
    LIMIT 1
");
$stmt->execute([$batchId, $supplierId, $userId]);
$requestId = (int)$stmt->fetchColumn();

$grossTotal = 0;
foreach ($items as $item) {
    $grossTotal += (float)$item['qty'] * (float)$item['discounted_unit_price'];
}

$stmt = $pdo->prepare("SELECT COALESCE(overall_discount, 0) FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
$stmt->execute([$batchId, $supplierId]);
$overallDiscount = (float)$stmt->fetchColumn();
$amount = max(0, $grossTotal - $overallDiscount);

try {
    po_approval_assert_budget_available(
        $pdo,
        $batch['pr_number'],
        $batch['budget_code'] ?? null,
        $batch['pr_dealer'] ?? ($_SESSION['dealer'] ?? ''),
        $amount
    );
} catch (Exception $e) {
    die($e->getMessage());
}

if ($requestId <= 0) {
    $stmt = $pdo->prepare("
        INSERT INTO po_approval_requests
        (pr_number, batch_id, supplier_id, dealer, budget_code, amount, status, created_by, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, 'draft', ?, NOW())
    ");
    $stmt->execute([
        $batch['pr_number'],
        $batchId,
        $supplierId,
        $batch['pr_dealer'] ?? ($_SESSION['dealer'] ?? ''),
        $batch['budget_code'] ?? null,
        $amount,
        $userId
    ]);
    $requestId = (int)$pdo->lastInsertId();
} else {
    $stmt = $pdo->prepare("
        UPDATE po_approval_requests
        SET amount = ?, final_po_number = NULL, po_date = NULL, updated_at = NOW()
        WHERE request_id = ?
    ");
    $stmt->execute([$amount, $requestId]);

    $stmt = $pdo->prepare("DELETE FROM po_approval_items WHERE request_id = ?");
    $stmt->execute([$requestId]);
}

$placeholder = 'PENDING-' . preg_replace('/[^A-Za-z0-9_\-]/', '-', $batch['pr_number']) . '-' . $supplierId . '-' . $requestId;
$stmt = $pdo->prepare("
    UPDATE po_approval_requests
    SET placeholder_po_number = ?, updated_at = NOW()
    WHERE request_id = ?
");
$stmt->execute([$placeholder, $requestId]);

$stmt = $pdo->prepare("
    INSERT INTO po_approval_items
    (request_id, item_id, item_name, qty, unit_measure, unit_price, discounted_unit_price, subtotal)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
");
foreach ($items as $item) {
    $qty = (float)$item['qty'];
    $discountedPrice = (float)$item['discounted_unit_price'];
    $stmt->execute([
        $requestId,
        $item['item_id'],
        $item['item_name'],
        $qty,
        $item['unit_measure'],
        (float)$item['unit_price'],
        $discountedPrice,
        $qty * $discountedPrice
    ]);
}

po_approval_seed_signatures($pdo, $requestId, $batchId, true);

header('Location: ../po_approval_view.php?po_approval_id=' . $requestId);
exit;
