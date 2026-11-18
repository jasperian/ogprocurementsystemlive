<?php
require '../init.php';
require_once __DIR__ . '/data/budget_remaining_helper.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);
$pr_number = $_GET['pr_number'] ?? '';
if (!$pr_number) {
    die('PR Number missing.');
}


// Get batch info
$stmt = $pdo->prepare("SELECT * FROM canvass_sheet_batches WHERE pr_number = ?");
$stmt->execute([$pr_number]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die('PR Number not found.');
}

$batch_id = $batch['batch_id'];
$notes = $batch['notes'] ?? '';
$budgetCode = '';
$budgetActivity = '';
$approvedBudget = 0;
$poExpenses = 0;
$activeCashAdvance = 0;
$completedLiquidations = 0;
$completedRfp = 0;
$completedCashAdvanceLiquidations = 0;
$pendingLiquidations = 0;
$pendingRfp = 0;
$reservedLiquidationCashAdvances = 0;
$actualBudgetExpenses = 0;
$committedBudgetExpenses = 0;
$totalBudgetExpenses = 0;
$remainingBudget = 0;
$budgetUtilization = 0;

// Get current user info for comments
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);


// $priceViewerUserIds = [81, 82, 83];
// $canViewPrice =
//     ($_SESSION['position'] ?? '') !== 'Manager'
//     || ($_SESSION['department'] ?? '') === 'PURCHASING'
//     || in_array((int) ($_SESSION['user_id'] ?? 0), $priceViewerUserIds, true);

$priceViewerUserIds = [81, 82, 83];

$position = trim($_SESSION['position'] ?? '');

$canViewPrice =
    $position !== ''
    && (
        $position !== 'Manager'
        || ($_SESSION['department'] ?? '') === 'PURCHASING'
        || in_array((int) ($_SESSION['user_id'] ?? 0), $priceViewerUserIds, true)
    );


// Get all orders in this batch with item and supplier info
$stmt = $pdo->prepare("
    SELECT 
        po.*,
        s.supplier_name,
        s.contact_person,
        s.contact_number,
        s.payment_terms,
        i.item_name,
        i.description,
        i.product_image,
        i.unit_measure,
        cq.quotation_file,
        cq.overall_discount,
        cq.updated_at AS proposal_updated_at,
        po.updated_at AS item_updated_at
    FROM canvass_items po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN items i ON po.item_id = i.item_id
    LEFT JOIN canvass_quotation cq ON po.batch_id = cq.batch_id AND po.supplier_id = cq.supplier_id
    WHERE po.batch_id = ?
");
$stmt->execute([$batch_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group by supplier
$suppliers = [];
foreach ($orders as $order) {
    $sid = $order['supplier_id'];
    if (!isset($suppliers[$sid])) {
        $suppliers[$sid] = [
            'supplier_name' => $order['supplier_name'],
            'contact_person' => $order['contact_person'],
            'contact_number' => $order['contact_number'],
            'payment_terms' => $order['payment_terms'],
            'quotation_file' => $order['quotation_file'],
            'overall_discount' => $order['overall_discount'] ?? 0,
            'items' => []
        ];
    }
    $suppliers[$sid]['items'][] = $order;
}

$canvassLayout = [];
if (!empty($batch['canvass_layout_json'])) {
    $decodedLayout = json_decode($batch['canvass_layout_json'], true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decodedLayout)) {
        $canvassLayout = $decodedLayout;
    }
}

if (!empty($canvassLayout['suppliers']) && is_array($canvassLayout['suppliers'])) {
    foreach ($canvassLayout['suppliers'] as $layoutSupplier) {
        $supplierId = $layoutSupplier['supplier_id'] ?? null;
        $rows = $layoutSupplier['rows'] ?? [];

        if (!$supplierId || empty($suppliers[$supplierId]) || !is_array($rows)) {
            continue;
        }

        $itemQueues = [];
        foreach ($suppliers[$supplierId]['items'] as $item) {
            $itemQueues[(string)$item['item_id']][] = $item;
        }

        $orderedItems = [];
        foreach ($rows as $row) {
            if (($row['type'] ?? '') === 'no_quote') {
                $orderedItems[] = ['is_no_quotation' => true];
                continue;
            }

            if (($row['type'] ?? '') === 'item') {
                $itemId = (string)($row['item_id'] ?? '');
                if ($itemId !== '' && !empty($itemQueues[$itemId])) {
                    $orderedItems[] = array_shift($itemQueues[$itemId]);
                }
            }
        }

        foreach ($itemQueues as $queuedItems) {
            foreach ($queuedItems as $item) {
                $orderedItems[] = $item;
            }
        }

        $suppliers[$supplierId]['items'] = $orderedItems;
    }
}

// $stmt = $pdo->prepare("SELECT pr_status FROM purchase_requisition WHERE pr_number = ?");
// $stmt->execute([$pr_number]);
// $prStatus = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT pr_status, pr_dealer, pr_department 
    FROM purchase_requisition 
    WHERE pr_number = ?
");
$stmt->execute([$pr_number]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $prStatus = $result['pr_status'];
    $prDealer = $result['pr_dealer'];
    $prDepartment = $result['pr_department'];
} else {
    // No record found
    $prStatus = null;
    $prDealer = null;
    $prDepartment = null;

    // Optional: handle it
    echo "No purchase requisition found for PR number: " . htmlspecialchars($pr_number);
}

// $stmt = $pdo->prepare("SELECT csb.budget_code FROM canvass_sheet_batches csb LEFT JOIN budgets b ON csb.budget_code = b.budget_id WHERE pr_number = ?");
// $stmt->execute([$pr_number]);
// $budgetCode = $stmt->fetchColumn();
$stmt = $pdo->prepare("
    SELECT 
        csb.budget_code AS selected_budget_id,
        b.budget_code,
        b.budget_activity
    FROM canvass_sheet_batches csb
    LEFT JOIN budgets b 
        ON b.budget_id = csb.budget_code OR b.budget_code = csb.budget_code
    WHERE csb.pr_number = ?
    LIMIT 1
");
$stmt->execute([$pr_number]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $budgetCode = $result['budget_code'] ?? '';
    $budgetActivity = $result['budget_activity'] ?? '';

    if ($budgetCode !== '' && !empty($prDepartment)) {
        $budgetSummary = getUnifiedBudgetRemaining(
            $pdo,
            $budgetCode,
            $prDepartment,
            $prDealer ?? ($_SESSION['dealer'] ?? '')
        );
        if ($budgetSummary) {
            $approvedBudget = (float)$budgetSummary['approved_budget'];
            $poExpenses = (float)$budgetSummary['po_expenses'];
            $activeCashAdvance = (float)$budgetSummary['active_cash_advance'];
            $completedLiquidations = (float)$budgetSummary['completed_liquidations'];
            $completedRfp = (float)$budgetSummary['completed_rfp'];
            $completedCashAdvanceLiquidations = (float)$budgetSummary['completed_cash_advance_liquidations'];
            $pendingLiquidations = (float)$budgetSummary['pending_liquidations'];
            $pendingRfp = (float)$budgetSummary['pending_rfp'];
            $reservedLiquidationCashAdvances = (float)$budgetSummary['reserved_liquidation_cash_advances'];
            $actualBudgetExpenses = (float)$budgetSummary['actual_expenses'];
            $committedBudgetExpenses = (float)$budgetSummary['committed_expenses'];
            $totalBudgetExpenses = (float)$budgetSummary['total_expenses'];
            $remainingBudget = (float)$budgetSummary['remaining_budget'];
            $budgetUtilization = $approvedBudget > 0
                ? ($totalBudgetExpenses / $approvedBudget) * 100
                : 0;
        }
    }
}


// Get batch info
// $stmt = $pdo->prepare("SELECT *
//                     FROM canvass_sheet_batches csb
//                     LEFT JOIN canvass_approvals ca ON csb.batch_id = ca.batch_id
//                     LEFT JOIN users u ON ca.prepared_by = u.user_id
//                     WHERE pr_number = ?");
// $stmt->execute([$pr_number]);
// $signature = $stmt->fetch(PDO::FETCH_ASSOC);

// $signatories = [];

$sql = "SELECT 
    ca.batch_id,
    ca.checked_1,
    ca.checked_2,
    ca.checked_3,
    ca.checked_4,
    ca.checked_5,
    ca.checked_6,
    ca.checked_7,
    ca.checked_8,
    ca.checked_9,
    ca.assigned_dealer,

    u1.full_name AS approver_1_name,
    u1.position AS approver_1_position,
    u1.user_signature AS approver_1_signature,
    u1.department AS approver_1_department,

    u2.full_name AS approver_2_name,
    u2.position AS approver_2_position,
    u2.user_signature AS approver_2_signature,
    u2.department AS approver_2_department,

    u3.full_name AS approver_3_name,
    u3.position AS approver_3_position,
    u3.user_signature AS approver_3_signature,
    u3.department AS approver_3_department,

    u4.full_name AS approver_4_name,
    u4.position AS approver_4_position,
    u4.user_signature AS approver_4_signature,
    u4.department AS approver_4_department,

    u5.full_name AS approver_5_name,
    u5.position AS approver_5_position,
    u5.user_signature AS approver_5_signature,
    u5.department AS approver_5_department,

    u6.full_name AS approver_6_name,
    u6.position AS approver_6_position,
    u6.user_signature AS approver_6_signature,
    u6.department AS approver_6_department,

    u7.full_name AS approver_7_name,
    u7.position AS approver_7_position,
    u7.user_signature AS approver_7_signature,
    u7.department AS approver_7_department,

    u8.full_name AS approver_8_name,
    u8.position AS approver_8_position,
    u8.user_signature AS approver_8_signature,
    u8.department AS approver_8_department,

    u9.full_name AS approver_9_name,
    u9.position AS approver_9_position,
    u9.user_signature AS approver_9_signature,
    u9.department AS approver_9_department

FROM canvass_approvals ca
LEFT JOIN users u1 ON ca.approver_1 = u1.user_id
LEFT JOIN users u2 ON ca.approver_2 = u2.user_id
LEFT JOIN users u3 ON ca.approver_3 = u3.user_id
LEFT JOIN users u4 ON ca.approver_4 = u4.user_id
LEFT JOIN users u5 ON ca.approver_5 = u5.user_id
LEFT JOIN users u6 ON ca.approver_6 = u6.user_id
LEFT JOIN users u7 ON ca.approver_7 = u7.user_id
LEFT JOIN users u8 ON ca.approver_8 = u8.user_id
LEFT JOIN users u9 ON ca.approver_9 = u9.user_id
WHERE ca.batch_id = :vendor_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':vendor_id', $batch_id, PDO::PARAM_INT);
$stmt->execute();
$signature = $stmt->fetch(PDO::FETCH_ASSOC);

$assignedDealer = $signature['assigned_dealer'] ?? '';
if ($assignedDealer === 'TMR' || $assignedDealer === 'TSJ') {
    $customOrder = [1, 2, 3, 4, 8, 5, 6, 7, 9]; // your manual order
} else {
    $customOrder = [1, 2, 3, 4, 5, 6, 7, 9]; // default order

}

$isApprovedBy4 = !empty($signature['checked_4']);

// $customOrder = [1, 2, 6, 3, 4, 5]; // your manual order
$signatories = [];

$labels = [
    1 => 'Prepared By',
    2 => 'Checked By',
    3 => 'Noted By',
    4 => 'Approved By',
    5 => 'Approved By',
    6 => 'Approved By',
    7 => 'Approved By',
    8 => 'Approved By',
    9 => 'Approved By'
];


foreach ($customOrder as $index => $i) {
    $nameKey = "approver_{$i}_name";
    $positionKey = "approver_{$i}_position";
    $signatureKey = "approver_{$i}_signature";
    $deptKey = "approver_{$i}_department";
    $checkedKey = "checked_{$i}";
    $dateKey = "checked_{$i}";

    // Get previous approver's checked value (only if this is not the first in order)
    $prevChecked = null;
    if ($index > 0) {
        $prevI = $customOrder[$index - 1];
        $prevCheckedKey = "checked_{$prevI}";
        $prevChecked = $signature[$prevCheckedKey] ?? null;
    }

    if (!empty($signature[$nameKey])) {
        $signatories[] = [
            'index' => $i,
            'label' => $labels[$i] ?? 'Approved By',
            'name' => $signature[$nameKey],
            'signature' => !empty($signature[$checkedKey]) ? $signature[$signatureKey] : '',
            'position' => $signature[$positionKey] ?? '',
            'date' => $signature[$dateKey] ?? '',
            // 'department' => $signature[$department] ?? '',
            'assigned_department' => $signature[$deptKey] ?? '',
            'can_approve' => empty($signature[$checkedKey]) &&
                isset($_SESSION['position'], $_SESSION['department']) &&
                $_SESSION['department'] === $signature[$deptKey] &&
                $_SESSION['position'] === $signature[$positionKey] &&
                ($index === 0 || !empty($prevChecked)) // Only allow if first approver or previous is checked
        ];
    }
}
// Step 1: Find the index of the supplier with the lowest total
$lowestIndex = null;
$lowestTotal = null;

foreach (array_values($suppliers) as $i => $supplier) {
    $itemNetSum = 0;
    foreach ($supplier['items'] as $item) {
        $itemNetSum += ((float)$item['qty']) * ((float)$item['discounted_unit_price']);
    }
    $total = $itemNetSum - ((float)($supplier['overall_discount'] ?? 0));

    if ($lowestTotal === null || $total < $lowestTotal) {
        $lowestTotal = $total;
        $lowestIndex = $i;
    }
}


// $stmt = $pdo->prepare("SELECT r.*, u.full_name, u.user_pfp, u.department 
//                        FROM recommendations_suggestions r
//                        JOIN users u ON r.user_id = u.user_id
//                        WHERE r.pr_number = ?
//                        ORDER BY r.created_date ASC");
// $stmt->execute([$pr_number]);
// $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);



include 'header.php';
include 'navigation.php';
?>
<style>
    @media print {
        .no-print {
            display: none !important;
        }

        /* You might also hide other non-essential elements */
        .header-buttons,
        .sidebar,
        .navbar,
        .breadcrumb,


        footer {
            display: none;
        }

        /* And adjust content to fill the space */
        main {
            width: 100%;
            margin: 0;
            padding: 0;
        }
    }

    @page {
        size: A3 landscape;
        /* Suggest A4 in portrait */
        /* You can also use landscape, or specific dimensions like 21cm 29.7cm */
        margin: 1cm;
    }

    .description-text {
        display: inline-block;
        max-width: 100%;
        word-wrap: break-word;
    }

    .toggle-description {
        font-size: 0.75rem;
        display: inline-block;
        margin-top: 2px;
    }


    .list-group-item {
        display: flex;
        flex-wrap: nowrap;
        align-items: flex-start;
        gap: 1rem;
        padding: 0.75rem;
    }

    .item-image {
        flex-shrink: 0;
    }

    .item-content {
        flex-grow: 1;
        min-width: 0;
    }

    .item-info {
        display: flex;
        flex-direction: column;
    }

    .item-subtotal {
        text-align: right;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .budget-view-summary {
        margin: 18px 0 22px;
        border: 1px solid #dbe4f0;
        border-radius: 14px;
        background: #ffffff;
        box-shadow: 0 10px 30px rgba(15, 23, 42, 0.07);
        overflow: hidden;
    }

    .budget-summary-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        color: #ffffff;
        background: linear-gradient(135deg, #173b64 0%, #2563a8 100%);
    }

    .budget-summary-eyebrow {
        display: block;
        margin-bottom: 4px;
        color: rgba(255, 255, 255, 0.7);
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
    }

    .budget-summary-code {
        margin: 0;
        color: #ffffff !important;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.3;
    }

    .budget-summary-activity {
        max-width: 520px;
        color: rgba(255, 255, 255, 0.88);
        font-size: 0.82rem;
        line-height: 1.45;
        text-align: right;
    }

    .budget-summary-metrics {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 12px;
        padding: 18px 20px 14px;
    }

    .budget-summary-metric {
        min-width: 0;
        padding: 13px 14px;
        border: 1px solid #e5eaf1;
        border-radius: 10px;
        background: #f8fafc;
    }

    .budget-summary-metric-label {
        display: block;
        margin-bottom: 6px;
        color: #64748b;
        font-size: 0.66rem;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .budget-summary-metric-value {
        display: block;
        color: #172033;
        font-size: 1rem;
        font-weight: 700;
        line-height: 1.25;
        overflow-wrap: anywhere;
    }

    .budget-summary-metric.is-remaining {
        border-color: #b7e4cc;
        background: #effaf4;
    }

    .budget-summary-metric.is-remaining .budget-summary-metric-value {
        color: #18794e;
    }

    .budget-summary-metric.is-over-budget {
        border-color: #fecaca;
        background: #fff1f2;
    }

    .budget-summary-metric.is-over-budget .budget-summary-metric-value {
        color: #be123c;
    }

    .budget-summary-progress-wrap {
        padding: 0 20px 18px;
    }

    .budget-summary-breakdown {
        margin: 0 20px 16px;
        border: 1px solid #e5eaf1;
        border-radius: 10px;
        background: #ffffff;
        overflow: hidden;
    }

    .budget-summary-breakdown summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 11px 14px;
        color: #334155;
        font-size: 0.76rem;
        font-weight: 700;
        cursor: pointer;
        list-style: none;
        user-select: none;
    }

    .budget-summary-breakdown summary::-webkit-details-marker {
        display: none;
    }

    .budget-summary-breakdown summary::after {
        content: '+';
        flex: 0 0 auto;
        color: #2563a8;
        font-size: 1rem;
        line-height: 1;
    }

    .budget-summary-breakdown[open] summary::after {
        content: '\2212';
    }

    .budget-summary-breakdown-totals {
        margin-left: auto;
        color: #64748b;
        font-size: 0.7rem;
        font-weight: 600;
        text-align: right;
    }

    .budget-summary-breakdown-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
        padding: 14px;
        border-top: 1px solid #edf1f6;
        background: #f8fafc;
    }

    .budget-breakdown-heading {
        margin: 0 0 8px;
        color: #173b64;
        font-size: 0.7rem;
        font-weight: 800;
        letter-spacing: 0.05em;
        text-transform: uppercase;
    }

    .budget-breakdown-row {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding: 6px 0;
        border-bottom: 1px dashed #dbe3ed;
        color: #64748b;
        font-size: 0.73rem;
    }

    .budget-breakdown-row:last-child {
        border-bottom: 0;
    }

    .budget-breakdown-row strong {
        flex: 0 0 auto;
        color: #1e293b;
        font-weight: 700;
    }

    .budget-summary-progress-meta {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 7px;
        color: #64748b;
        font-size: 0.72rem;
        font-weight: 600;
    }

    .budget-summary-progress {
        height: 7px;
        border-radius: 999px;
        background: #e7edf5;
        overflow: hidden;
    }

    .budget-summary-progress-bar {
        height: 100%;
        border-radius: inherit;
        background: linear-gradient(90deg, #2f80ed, #56ccf2);
    }

    .budget-summary-progress-bar.is-over-budget {
        background: #e11d48;
    }

    @media (max-width: 991.98px) {
        .budget-summary-metrics {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 575.98px) {
        .budget-summary-header {
            flex-direction: column;
            gap: 10px;
        }

        .budget-summary-activity {
            max-width: none;
            text-align: left;
        }

        .budget-summary-metrics {
            grid-template-columns: 1fr;
        }

        .budget-summary-breakdown-grid {
            grid-template-columns: 1fr;
        }

        .budget-summary-breakdown-totals {
            display: none;
        }
    }

    .canvass-view-shell {
        padding: 18px;
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        background: #fbfcfe;
    }

    .supplier-card {
        border: 1px solid #e6eaf0;
        border-radius: 10px;
        background: #ffffff;
        box-shadow: 0 8px 24px rgba(15, 23, 42, 0.06);
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        overflow: hidden;
    }

    .supplier-card:hover {
        transform: translateY(-2px);
        border-color: #cdd7e5;
        box-shadow: 0 14px 32px rgba(15, 23, 42, 0.09);
    }

    .supplier-card .card-body {
        padding: 16px;
    }

    .supplier-view-header {
        margin: -16px -16px 14px;
        padding: 14px 16px;
        border-bottom: 1px solid #eef2f7;
        background: #f8fafc;
    }

    .supplier-title {
        color: #1e3a5f;
        font-size: 0.92rem;
        font-weight: 700;
        letter-spacing: 0.01em;
        line-height: 1.5;
    }

    .supplier-name {
        color: #0f172a;
        font-size: 1.05rem;
        font-weight: 800;
    }

    .supplier-meta {
        min-height: 58px;
        margin-top: 10px;
        padding: 10px;
        border: 1px solid #eef2f7;
        border-radius: 8px;
        background: #fbfcfe;
        color: #64748b;
        line-height: 1.45;
    }

    .supplier-card .selectedItems {
        min-height: 88px;
        border-color: #e6eaf0 !important;
        border-radius: 8px !important;
        background: #fbfcfe;
    }

    .supplier-card .no-quotation-item {
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 92px;
        border-style: dashed;
        color: #64748b;
        background: #f8fafc;
        font-size: 0.86rem;
        font-weight: 700;
        text-align: center;
    }

    .supplier-view-actions {
        padding: 10px;
        border: 1px solid #eef2f7;
        border-radius: 8px;
        background: #ffffff;
    }

    .supplier-total-panel {
        margin-top: 12px;
        padding: 12px;
        border: 1px solid #e6eaf0;
        border-radius: 8px;
        background: #f8fafc;
    }

    .supplier-total-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        color: #475569;
        font-size: 0.84rem;
        line-height: 1.45;
    }

    .supplier-total-row+.supplier-total-row {
        margin-top: 8px;
    }

    .supplier-total-row strong {
        color: #0f172a;
        font-weight: 800;
        text-align: right;
    }

    .supplier-total-row.final {
        margin-top: 10px;
        padding-top: 10px;
        border-top: 1px solid #e5e7eb;
        color: #1d4ed8;
        font-weight: 800;
    }

    .supplier-total-row.final strong {
        color: #1d4ed8;
        font-size: 1.04rem;
    }

    .supplier-total-meta {
        margin-top: 6px;
        color: #64748b;
        font-size: 0.78rem;
        text-align: right;
    }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.14.305/pdf.min.js"></script>
<script src="https://js.pusher.com/8.2.0/pusher.min.js"></script>

<!-- <a href="logout.php">Logout</a> -->
<!-- [ Main Content ] start -->
<div class="pc-container">

    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="btn-page text-end no-print mr-5">
                    <button onclick="window.print()" class="btn btn-primary no-print">Print This Page</button>
                </div>
                <div class="row align-items-center">

                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <img src="assets/images/<?php echo $prDealer; ?>.png" class="img-fluid logo-md" alt="logo" style="width: 250px; max-width: 250px" />
                            </div>
                            <div class="page-header-title text-center">
                                <img src="assets/images/logo.png" class="img-fluid logo-md text-center" alt="logo" style="width: 150px;" />
                                <h2 class="mb-0">Canvass Sheet</h2>

                            </div>
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                                <div>
                                    <p class="mb-0"><b>PR NUMBER: <?= htmlspecialchars($pr_number) ?></b></p>
                                    <p class="mb-0"><b>DATE: <?= date("F j, Y") ?></b></p>
                                </div>
                                <img src="assets/images/OG.png" class="img-fluid logo-md ml-4" alt="logo" style="width: 60px;" />
                                <!-- <img src="assets/images/logo.png" class="img-fluid logo-md" alt="logo" style="width: 150px;" /> -->
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ Main Content ] start -->
        <style>
            .item-card {
                padding: 12px;
                border: 1px solid #e5e7eb;
                border-radius: 8px;
                background: #ffffff;
                margin: 8px;
            }

            .item-wrapper {
                display: flex;
                gap: 12px;
                align-items: flex-start;
                flex-wrap: nowrap;
            }

            .item-image img {
                width: 60px;
                height: 60px;
                object-fit: cover;
                border-radius: 8px;
            }

            .item-details {
                flex: 1;
                min-width: 0;
                word-break: break-word;
            }

            .item-name {
                display: block;
                font-weight: bold;
                margin-bottom: 3px;
                text-overflow: ellipsis;
                white-space: nowrap;
                overflow: hidden;
                color: #0f172a;
            }

            .item-card .input-group-text,
            .item-card .form-control,
            .supplier-total-panel .input-group-text,
            .supplier-total-panel .form-control {
                border-color: #d7dde6;
                box-shadow: none;
            }

            .item-card .btn,
            .supplier-total-panel .btn {
                border-radius: 7px;
            }

            .description-box {
                font-size: 0.875rem;
                line-height: 1.3;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }

            .collapsed-desc {
                display: -webkit-box;
                -webkit-line-clamp: 2;
                -webkit-box-orient: vertical;
                max-height: 2.6em;
                line-clamp: 3;
                /* Standards-based future support */
                overflow: hidden;
            }

            .expanded-desc {
                display: block;
                max-height: 1000px;
            }

            /* Mobile adjustments */
            @media (max-width: 576px) {
                .item-wrapper {
                    flex-direction: column;
                    align-items: flex-start;
                }

                .item-image img {
                    width: 80px;
                    height: 80px;
                }

                .item-name {
                    white-space: normal;
                }

                .collapsed-desc {
                    -webkit-line-clamp: 3;
                    line-clamp: 3;
                    max-height: 3.9em;
                }
            }
            
              @media print {
        .budget-view-summary,
        .budget-view-summary * {
            color: #000 !important;
        }
        </style>
        <div class="canvass-view-shell">
            <div class="row">
                <?php $idx = 0;
                foreach ($suppliers as $sid => $data): $idx++; ?>
                    <?php
                    // Step 2: Calculate totals per supplier for display
                    $gross_total = 0;
                    $item_net_sum = 0;
                    $latest_date = null;
                    foreach ($data['items'] as $item) {
                        if (!empty($item['is_no_quotation'])) {
                            continue;
                        }
                        $gross_total += $item['qty'] * $item['unit_price'];
                        $item_net_sum += $item['qty'] * $item['discounted_unit_price'];

                        // Find latest date from item updates or proposal updates
                        $idate = strtotime($item['item_updated_at'] ?? '0');
                        $pdate = strtotime($item['proposal_updated_at'] ?? '0');
                        $max_item_date = max($idate, $pdate);

                        if (!$latest_date || $max_item_date > strtotime($latest_date)) {
                            $latest_date = date('Y-m-d H:i:s', $max_item_date);
                        }
                    }

                    // Apply Overall Discount
                    $overall_discount = $data['overall_discount'] ?? 0;
                    $net_total = $item_net_sum - $overall_discount;

                    // Step 3: Apply highlight class if this is the lowest
                    $isLowest = ($idx - 1 === $lowestIndex);
                    $isSelected = ($batch['selected_supplier_id'] == $sid);

                    $cardClass = '';
                    if ($isSelected) {
                        $cardClass = 'border-primary border-3 shadow';
                    } elseif ($isLowest) {
                        $cardClass = 'border-success border-2';
                    }
                    $createPoUrl = 'data/create_po_approval.php?' . http_build_query([
                        'batch_id' => $batch_id,
                        'supplier_id' => $sid,
                    ]);
                    ?>
                    <div class="col-md-4 col-lg-4 mb-3">
                        <div class="card supplier-card h-100 <?= $cardClass ?>" data-selected="<?= $isSelected ? '1' : '0' ?>" data-supplier="<?= (int)$sid ?>">
                            <div class="card-body d-flex flex-column">
                                <div class="supplier-view-header d-flex justify-content-between align-items-start gap-2">
                                    <h6 class="supplier-title fw-semibold text-primary mb-0">
                                        SUPPLIER <?= $idx ?>
                                        <?php if ($isSelected): ?>
                                            <span class="badge bg-primary ms-2"><i class="fas fa-check-circle"></i> RECOMMENDED SUPPLIER</span>
                                        <?php elseif ($isLowest): ?>
                                            <span class="badge bg-success ms-2 lowest-supplier-badge"><i class="fas fa-award"></i> LOWEST</span>
                                        <?php endif; ?>
                                    </h6>
                                    <div class="dropdown no-print">
                                        <button class="btn btn-light btn-sm text-muted" data-bs-toggle="dropdown">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <?php if ($prStatus === 'For PO'): ?>
                                                <li>
                                                    <a class="dropdown-item create-po-approval" href="<?= htmlspecialchars($createPoUrl) ?>" data-base-url="<?= htmlspecialchars($createPoUrl) ?>" data-supplier="<?= $sid ?>">
                                                        <i class="fas fa-file-invoice text-primary me-2"></i> Create PO
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                            <?php if ($canViewPrice): ?>
                                                <li>
                                                    <a class="dropdown-item select-supplier-btn" href="#" data-batch="<?= $batch_id ?>" data-supplier="<?= $sid ?>" data-name="<?= htmlspecialchars($data['supplier_name']) ?>">
                                                        <i class="fas fa-check text-success me-2"></i> Recommended Supplier
                                                    </a>
                                                </li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>

                               <?php if ($canViewPrice): ?>
                                    <div class="mb-2">
                                        <div class="supplier-name"><?= htmlspecialchars($data['supplier_name']) ?></div>
                                    </div>

                                    <div class="supplier-meta small no-print">
                                        <div>CONTACT PERSON: <?= htmlspecialchars($data['contact_person']) ?></div>
                                        <div>CONTACT NUMBER: <?= htmlspecialchars($data['contact_number']) ?></div>
                                        <div>TERMS OF PAYMENT: <?= htmlspecialchars($data['payment_terms'] ?? '') ?></div>
                                    </div>
                                <?php endif; ?>

                                <ul class="list-group list-group-flush selectedItems mb-2 rounded border mt-3">
                                    <?php foreach ($data['items'] as $item): ?>
                                        <?php if (!empty($item['is_no_quotation'])): ?>
                                            <li class="list-group-item no-quotation-item">
                                                No quotations
                                            </li>
                                            <?php continue; ?>
                                        <?php endif; ?>
                                        <?php
                                        $subtotal = $item['qty'] * $item['discounted_unit_price'];
                                        $description = htmlspecialchars($item['description']);
                                        $isLong = mb_strlen($description) > 60;
                                        $uniqueId = uniqid('desc_');
                                        $imagePath = 'data/' . (!empty($item['product_image']) ? $item['product_image'] : 'uploads/items/default.jpeg');
                                        ?>
                                        <li class="list-group-item item-card">
                                            <div class="form-check no-print mt-1" hidden>
                                                <input class="form-check-input print-item-checkbox" type="checkbox" value="<?= (int) $item['item_id'] ?>" data-supplier="<?= $sid ?>" id="print_item_<?= (int) $sid ?>_<?= (int) $item['item_id'] ?>">
                                            </div>
                                            <div class="item-wrapper">
                                                <!-- Image -->
                                                <div class="item-image">
                                                    <a href="#"
                                                        class="image-lightbox"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#imageModal"
                                                        data-img="<?= $imagePath ?>">
                                                        <img src="<?= $imagePath ?>" alt="Image" class="img-thumbnail" />
                                                    </a>
                                                </div>

                                                <!-- Info -->
                                                <div class="item-details">
                                                    <?php
                                                    $name = $item['item_name'] ?? '';
                                                    $shortName = mb_strlen($name) > 30 ? mb_substr($name, 0, 30) . '...' : $name;
                                                    ?>
                                                   <div class="d-flex justify-content-between align-items-center">
                                                        <strong class="item-name">
                                                            <?= htmlspecialchars($shortName) ?>
                                                        </strong>
                                                    
                                                        <?php if ($canViewPrice): ?>
                                                            <small class="badge bg-secondary mb-1">
                                                                Base Price: ₱<?= number_format($item['unit_price'], 2) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
       
                                                    <div id="<?= $uniqueId ?>"
                                                        class="description-box text-muted <?= $isLong ? 'collapsed-desc' : '' ?>">
                                                        <?= $description ?>
                                                    </div>

                                                    <?php if ($isLong): ?>
                                                        <a href="#"
                                                            class="toggle-description small"
                                                            data-id="<?= $uniqueId ?>">
                                                            View more
                                                        </a>
                                                    <?php endif; ?>
                                                    <!-- Qty & Subtotal -->
                                                    <div class="d-flex justify-content-between align-items-center flex-wrap m-2 gap-2">
                                                        <?php if ($canViewPrice): ?>
                                                            <div class="d-flex flex-column" hidden>
                                                                <small class="text-muted">
                                                                    ₱<?= number_format($item['discounted_unit_price'] ?? 0, 2) . ' per ' . htmlspecialchars($item['unit_measure'] ?? '') ?>
                                                                </small>
                                                            </div>
                                                        <?php endif; ?>


                                                        <div class="d-flex align-items-center gap-2">
                                                            
                                                            <?php if ($_SESSION['position'] === 'Purchasing Staff'): ?>
                                                            <small class="text-muted">Price:</small>
                                                                <div class="input-group input-group-sm" style="max-width: 150px; width: 100%;">
                                                                    <span class="input-group-text p-1">₱</span>
                                                                    <input type="number"
                                                                        step="0.01"
                                                                        class="form-control form-control-sm item-discount-edit text-end"
                                                                        value="<?= number_format($item['discounted_unit_price'], 2, '.', '') ?>"
                                                                        data-batch="<?= $batch_id ?>"
                                                                        data-supplier="<?= $item['supplier_id'] ?>"
                                                                        data-item="<?= $item['item_id'] ?>"
                                                                        data-qty="<?= $item['qty'] ?>"
                                                                        data-price="<?= $item['unit_price'] ?>"
                                                                        data-measure="<?= htmlspecialchars($item['unit_measure']) ?>"
                                                                        min="0"
                                                                    <?= $isApprovedBy4 ? 'disabled' : '' ?>>
                                                                    <button class="btn btn-primary update-discount-btn" type="button" title="Update Price" <?= $isApprovedBy4 ? 'disabled' : '' ?> >
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </div>
                                                            
                                                                <span class="badge bg-light text-dark border" hidden>₱<?= number_format($item['discounted_unit_price'] ?? 0, 2) ?></span>
                                                            <?php endif; ?>
                                                        </div>

                                                        <div class="d-flex align-items-center gap-2">
                                                            <small class="text-muted">Qty:</small>
                                                            <?php if ($_SESSION['position'] === 'Purchasing Staff'): ?>
                                                                <div class="input-group input-group-sm" style="width: 100px;">
                                                                    <input type="number"
                                                                        class="form-control form-control-sm item-qty-edit text-center"
                                                                        value="<?= $item['qty'] ?>"
                                                                        data-batch="<?= $batch_id ?>"
                                                                        data-supplier="<?= $item['supplier_id'] ?>"
                                                                        data-item="<?= $item['item_id'] ?>"
                                                                        data-price="<?= $item['unit_price'] ?>"
                                                                        min="1"
                                                                     <?= $isApprovedBy4 ? 'disabled' : '' ?>>
                                                                    <button class="btn btn-primary update-qty-btn" type="button" title="Update Quantity" <?= $isApprovedBy4 ? 'disabled' : '' ?> >
                                                                        <i class="fas fa-check"></i>
                                                                    </button>
                                                                </div>
                                                            <?php else: ?>
                                                                <small class="text-muted"><?= $item['qty'] ?></small>
                                                            <?php endif; ?>
                                                        </div>

                                                       <?php if ($canViewPrice): ?>
                                                            <small class="text-muted">
                                                                Subtotal: ₱<span class="item-subtotal-display"><?= number_format($subtotal, 2) ?></span>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>


                                                </div>

                                            </div>

                                        </li>



                                    <?php endforeach; ?>

                                </ul>
                                <?php if ($canViewPrice): ?>
                                    <div class="supplier-view-actions d-flex gap-2 align-items-center mt-3 flex-wrap">
                                        <?php if (!empty($data['quotation_file'])): ?>
                                            <?php
                                            $file_ext = strtolower(pathinfo($data['quotation_file'], PATHINFO_EXTENSION));
                                            $is_image = in_array($file_ext, ['jpg', 'jpeg', 'png']);
                                            ?>
                                            <button type="button"
                                                class="btn btn-outline-primary btn-sm <?= $is_image ? 'image-lightbox' : 'viewPdfBtn' ?>"
                                                <?= $is_image ? 'data-bs-toggle="modal" data-bs-target="#imageModal" data-img="data/uploads/proposals/' . htmlspecialchars($data['quotation_file']) . '"' : 'data-pdf="data/uploads/proposals/' . htmlspecialchars($data['quotation_file']) . '"' ?>>
                                                View Proposal
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($_SESSION['position'] === 'Purchasing Staff'): ?>
                                            <form class="uploadProposalForm" enctype="multipart/form-data">
                                                <input type="hidden" name="batch_id" value="<?= $batch_id ?>">
                                                <input type="hidden" name="supplier_id" value="<?= $sid ?>">
                                                <input type="hidden" name="pr_number" value="<?= $pr_number ?>">
                                                <input type="file" name="proposal_file" class="d-none proposalFileInput" accept=".pdf,image/*">
                                                <button type="button" class="btn btn-sm btn-outline-secondary uploadProposalBtn">
                                                    <i class="fas fa-upload"></i> <?= !empty($data['quotation_file']) ? 'Change Proposal' : 'Upload Proposal' ?>
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <?php if ($canViewPrice): ?>
                                    <div class="supplier-total-panel">
                                        <div class="supplier-total-row"><span>Gross Total</span><strong>
                                            &#8369;<span class="supplier-gross-total-display"><?= number_format($gross_total, 2) ?></span>
                                        </strong></div>
                                        <div class="supplier-total-row">
                                        <span>Discount</span>
                                        <?php if ($_SESSION['position'] === 'Purchasing Staff'): ?>
                                            <div class="input-group input-group-sm" style="max-width: 150px; width: 100%;">
                                                <span class="input-group-text p-1">₱</span>
                                                <input type="number"
                                                    class="form-control form-control-sm overall-discount-edit text-end"
                                                    value="<?= number_format($data['overall_discount'], 2, '.', '') ?>"
                                                    data-batch="<?= $batch_id ?>"
                                                    data-supplier="<?= $sid ?>"
                                                    min="0" step="0.01">
                                                <button class="btn btn-primary update-overall-discount-btn" type="button" title="Update Overall Deduction">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            </div>
                                        <?php else: ?>
                                            <strong>&#8369;<?= number_format($data['overall_discount'], 2) ?></strong>
                                        <?php endif; ?>
                                        </div>
                                        <div class="supplier-total-row final">
                                            <span>Total Amount</span>
                                            <strong>&#8369;<span class="supplier-total-display"><?= number_format($net_total, 2) ?></span></strong>
                                        </div>
                                        <div class="supplier-total-meta">
                                            Date: <span class="discounted-price-date"><?= $latest_date ? date("F j, Y", strtotime($latest_date)) : date("F j, Y") ?></span>
                                            <button type="button" class="btn btn-link btn-sm p-0 ms-1 viewHistoryBtn" style="color: grey;" data-batch="<?= $batch_id ?>" data-supplier="<?= $sid ?>" data-supplier-name="<?= htmlspecialchars($data['supplier_name']) ?>" title="View History">
                                                <i class="fas fa-history"></i>
                                            </button>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            <!-- History Modal -->
            <div class="modal fade" id="historyModal" tabindex="-1" aria-labelledby="historyModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="historyModalLabel">Change History - <span id="historySupplierName"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Details</th>
                                        </tr>
                                    </thead>
                                    <tbody id="historyTableBody">
                                        <!-- History items will be injected here -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php if (!empty($budgetCode)): ?>
                <div class="budget-view-summary">
                    <div class="budget-summary-header">
                        <div>
                            <span class="budget-summary-eyebrow">Budget Summary</span>
                            <h5 class="budget-summary-code"><?= htmlspecialchars($budgetCode) ?></h5>
                        </div>
                        <div class="budget-summary-activity">
                            <span class="budget-summary-eyebrow">Budget Activity</span>
                            <?= htmlspecialchars($budgetActivity ?: 'N/A') ?>
                        </div>
                    </div>
                    <div class="budget-summary-metrics">
                        <div class="budget-summary-metric">
                            <span class="budget-summary-metric-label">Approved Budget</span>
                            <strong class="budget-summary-metric-value">&#8369;<?= number_format($approvedBudget, 2) ?></strong>
                        </div>
                        <div class="budget-summary-metric">
                            <span class="budget-summary-metric-label">Expenses</span>
                            <strong class="budget-summary-metric-value">&#8369;<?= number_format($totalBudgetExpenses, 2) ?></strong>
                        </div>
                        <div class="budget-summary-metric <?= $remainingBudget < 0 ? 'is-over-budget' : 'is-remaining' ?>">
                            <span class="budget-summary-metric-label">Remaining Budget</span>
                            <strong class="budget-summary-metric-value">&#8369;<?= number_format($remainingBudget, 2) ?></strong>
                        </div>
                    </div>
                    <details class="budget-summary-breakdown">
                        <summary>
                            <span>View expense breakdown</span>
                            <span class="budget-summary-breakdown-totals">
                                Actual &#8369;<?= number_format($actualBudgetExpenses, 2) ?>
                                &middot; Pending &#8369;<?= number_format($committedBudgetExpenses, 2) ?>
                            </span>
                        </summary>
                        <div class="budget-summary-breakdown-grid">
                            <section>
                                <h6 class="budget-breakdown-heading">Actual Expenses</h6>
                                <div class="budget-breakdown-row"><span>Purchase orders</span><strong>&#8369;<?= number_format($poExpenses, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>Completed liquidations</span><strong>&#8369;<?= number_format($completedLiquidations, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>Completed RFP</span><strong>&#8369;<?= number_format($completedRfp, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>Completed CA liquidations</span><strong>&#8369;<?= number_format($completedCashAdvanceLiquidations, 2) ?></strong></div>
                            </section>
                            <section>
                                <h6 class="budget-breakdown-heading">Pending Transactions</h6>
                                <div class="budget-breakdown-row"><span>Active cash advances</span><strong>&#8369;<?= number_format($activeCashAdvance, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>Pending liquidations</span><strong>&#8369;<?= number_format($pendingLiquidations, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>Pending RFP</span><strong>&#8369;<?= number_format($pendingRfp, 2) ?></strong></div>
                                <div class="budget-breakdown-row"><span>CA awaiting liquidation</span><strong>&#8369;<?= number_format($reservedLiquidationCashAdvances, 2) ?></strong></div>
                            </section>
                        </div>
                    </details>
                    <div class="budget-summary-progress-wrap">
                        <div class="budget-summary-progress-meta">
                            <span>Budget utilization</span>
                            <span><?= number_format($budgetUtilization, 1) ?>%</span>
                        </div>
                        <div class="budget-summary-progress" role="progressbar" aria-label="Budget utilization" aria-valuenow="<?= round($budgetUtilization, 1) ?>" aria-valuemin="0" aria-valuemax="100">
                            <div class="budget-summary-progress-bar <?= $budgetUtilization > 100 ? 'is-over-budget' : '' ?>" style="width: <?= min(max($budgetUtilization, 0), 100) ?>%"></div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <div>
                <p><strong>Recommendations and Suggestions:</strong></p>

            </div>
            <?php
            // 1️⃣ Fetch all comments for this PR

            // $stmt = $pdo->prepare("SELECT r.*, u.full_name, u.user_pfp, u.department 
            //                        FROM recommendations_suggestions r
            //                        JOIN users u ON r.user_id = u.user_id
            //                        WHERE r.pr_number = ?
            //                        ORDER BY r.created_date ASC");
            // $stmt->execute([$pr_number]);
            // $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
    SELECT r.*, u.full_name, u.user_pfp, u.department 
    FROM recommendations_suggestions r
    JOIN users u ON r.user_id = u.user_id
    WHERE r.pr_number = ?
    ORDER BY r.parent_id ASC, r.created_date ASC
");
            $stmt->execute([$pr_number]);
            $allComments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 2️⃣ Group comments by parent_id for nesting
            $groupedComments = [];
            foreach ($allComments as $c) {
                $pid = ($c['parent_id'] == null || $c['parent_id'] == "") ? "top" : $c['parent_id'];
                $groupedComments[$pid][] = $c;
            }

            // 3️⃣ Recursive function to render comments (Recursive style)
            if (!function_exists('highlightMentions')) {
                function highlightMentions($text, $pdo)
                {
                    if (!$text) return "";
                    static $all_users = null;
                    if ($all_users === null) {
                        $stmt = $pdo->query("SELECT full_name FROM users WHERE is_active = 1");
                        $all_users = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        usort($all_users, function ($a, $b) {
                            return strlen($b) - strlen($a);
                        });
                    }
                    foreach ($all_users as $name) {
                        $mention_tag = '@' . $name;
                        $text = preg_replace('/' . preg_quote($mention_tag, '/') . '/i', '<span class="mention-highlight">$0</span>', $text);
                    }
                    return $text;
                }
            }

            function renderComments($comments, $groupedComments, $level = 0)
            {
                global $pdo;
                foreach ($comments as $comment):
                    $cid = $comment['id'];
            ?>
                    <div class="card mb-3 shadow-sm border-0 <?= $level > 0 ? 'ms-4 bg-light' : '' ?>" id="comment-<?= $cid ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-2">
                                <img src="<?= htmlspecialchars($comment['user_pfp']) ?>"
                                    alt="User image"
                                    class="rounded-circle me-2"
                                    style="width: 40px; height: 40px; object-fit: cover;">

                                <div class="flex-grow-1">
                                    <div class="fw-bold"><?= htmlspecialchars($comment['full_name']) ?></div>
                                    <small class="text-muted d-block comment-time-block">
                                        <?= htmlspecialchars($comment['department']) ?> •
                                        <span class="comment-time"><?= date('M d, Y h:i A', strtotime($comment['created_date'])) ?></span>
                                        <?php if (!empty($comment['updated_at'])): ?>
                                            <span class="edited text-muted ms-1">(Edited <?= date('M d, Y h:i A', strtotime($comment['updated_at'])) ?>)</span>
                                        <?php endif; ?>
                                    </small>
                                    <p class="mt-2 mb-0 comment-text text-break"><?= nl2br(highlightMentions(htmlspecialchars($comment['comments']), $pdo)) ?></p>

                                    <!-- ✅ Reply Button -->
                                    <div class="mt-2">
                                        <a href="#" class="text-primary small reply-btn text-decoration-none"
                                            data-comment-id="<?= $cid ?>"
                                            data-user-name="<?= htmlspecialchars($comment['full_name']) ?>">
                                            <i class="fas fa-reply small"></i> Reply
                                        </a>
                                    </div>

                                    <!-- Reply form container -->
                                    <div class="reply-form-container mt-2" id="reply-form-<?= $cid ?>"></div>

                                    <!-- Container for nested replies -->
                                    <div class="nested-replies-container ms-4 mt-3" id="replies-to-<?= $cid ?>">
                                        <?php
                                        if (!empty($groupedComments[$cid])) {
                                            renderComments($groupedComments[$cid], $groupedComments, $level + 1);
                                        }
                                        ?>
                                    </div>
                                </div>

                                <?php if ($_SESSION['user_id'] == $comment['user_id']): ?>
                                    <div class="dropdown ms-2">
                                        <a href="#" class="text-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="material-icons-two-tone">more_vert</i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item edit-comment" href="#" data-id="<?= $cid ?>" data-text="<?= htmlspecialchars($comment['comments'], ENT_QUOTES) ?>">Edit</a></li>
                                            <li><a class="dropdown-item delete-comment" href="#" data-id="<?= $cid ?>">Delete</a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
            <?php endforeach;
            }
            ?>

            <!-- 4️⃣ Render all top-level comments -->
            <div id="comments-container">
                <?php if (!empty($groupedComments["top"])): ?>
                    <?php renderComments($groupedComments["top"], $groupedComments); ?>
                <?php else: ?>
                    <p class="text-muted no-comments-msg">No comments yet.</p>
                <?php endif; ?>
            </div>

            <!-- 🔹 Real-time (new comments/replies) -->
            <div id="comments"></div>

            <!-- 5️⃣ Comment form -->
            <form id="commentForm" class="no-print mt-3">
                <input type="hidden" name="pr_number" value="<?= htmlspecialchars($pr_number) ?>">
                <input type="hidden" name="user_pfp" value="<?= htmlspecialchars($user['user_pfp']) ?>">
                <div class="d-flex align-items-center mt-3">
                    <img class="img-radius d-none d-sm-inline-flex me-3 wid-40 rounded-circle"
                        src="<?= htmlspecialchars($user['user_pfp']) ?>" alt="User image" />
                    <div class="flex-grow-1 me-3">
                        <input type="text" name="comments" class="form-control" placeholder="Write a comment..." required>
                    </div>
                    <button type="submit" class="avtar avtar-s btn btn-primary">
                        <i class="ti ti-send f-18"></i>
                    </button>
                </div>
            </form>


            <!-- Optional alert -->
            <div id="commentAlert" class="mt-2 text-success d-none" hidden>Comment submitted!</div>

            <!-- Edit Modal -->
            <div class="modal fade" id="editCommentModal" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog">
                    <form id="editCommentForm">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Comment</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <input type="hidden" name="comment_id" id="editCommentId">
                                <textarea name="comments" id="editCommentText" class="form-control" rows="3" required></textarea>
                            </div>
                            <div class="modal-footer">
                                <button type="submit" class="btn btn-primary">Save changes</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <div class="row justify-content-center my-5 gap-4">
                <!-- Signatories -->
                <?php foreach ($signatories as $signatory): ?>
                    <?php
                    $canApprove = $signatory['can_approve'];
                    $i = $signatory['index']; // This is 2, 1, 6, etc.
                    // $batchId = $_GET['vendor_id'] ?? '';
                    $position = $_SESSION['position'] ?? '';
                    ?>

                    <div class="text-center position-relative" style="max-width: 200px; margin: 30px auto;">
                        <p class="mb-1 fw-semibold"><?= htmlspecialchars($signatory['label']) ?>:</p>
                        <div style="position: relative; height: 40px;">
                            <!-- Signature line -->
                            <div style="border-bottom: 1px solid #000; position: absolute; top: 50%; left: 0; right: 0; z-index: 1;"></div>

                            <?php if (!empty($signatory['signature']) && file_exists($signatory['signature'])): ?>
                                <img src="<?= htmlspecialchars($signatory['signature']) ?>" alt="Signature"
                                    class="user-avtar wid-45 rounded-circle"
                                    style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 2; width: 100px" ondragstart="return false;" oncontextmenu="return false;" />
                            <?php endif; ?>
                        </div>

                        <p class="mb-1 fw-semibold"><?= htmlspecialchars($signatory['name']) ?></p>
                        <p class="small text-muted"><?= htmlspecialchars($signatory['position']) ?></p>

                        <?php if (!empty($signatory['date'])): ?>
                            <p class="small text-muted"><?= date("F j, Y, g:i A", strtotime($signatory['date'])) ?></p>
                        <?php endif; ?>
                        <?php if ($user['user_id'] !== 22): ?>
                            <?php if ($canApprove && strtolower($prStatus) !== 'rejected'): ?>
                                <button class="btn btn-success btn-sm mt-2 submit-btn"
                                    data-position="<?= htmlspecialchars($position) ?>"
                                    data-batch="<?= htmlspecialchars($batch_id) ?>"
                                    data-name="<?= htmlspecialchars($signatory['name']) ?>"
                                    data-index="<?= $i ?>">
                                    Approve
                                </button>

                                <button class="btn btn-danger btn-sm mt-2 disapprove-btn"
                                    data-position="<?= htmlspecialchars($position) ?>"
                                    data-batch="<?= htmlspecialchars($batch_id) ?>"
                                    data-name="<?= htmlspecialchars($signatory['name']) ?>"
                                    data-prnumber="<?= htmlspecialchars($pr_number) ?>"
                                    data-index="<?= $i ?>">
                                    Disapprove
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- [ Main Content ] end -->
            <div class="col-md-12" hidden>
                <div class="d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                        <div>
                            <p class="mb-0"><b>TOYOTA NORTH EDSA</b></p>
                            <p class="mb-0"><i>1010 EDSA, Brgy. Ramong Magsaysay, Quezon City</i></p>
                            <p class="mb-0"><i>Tel No. 8927-7215</i></p>
                        </div>
                    </div>
                    <div>
                        <img src="assets/images/logo.png" class="img-fluid logo-md" alt="logo" style="width: 150px;" />
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- PDF Viewer Modal -->
    <div class="modal fade" id="pdfModal" tabindex="-1">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">PDF Viewer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <canvas id="pdfCanvas"></canvas>
                    <p>Page <span id="pageNum">1</span> of <span id="pageCount">1</span></p>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <button class="btn btn-secondary" id="prevPage">Previous</button>
                        <button class="btn btn-secondary" id="nextPage">Next</button>
                    </div>
                    <div>
                        <button class="btn btn-secondary" id="zoomOut">-</button>
                        <button class="btn btn-secondary" id="zoomIn">+</button>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <!-- Image Lightbox Modal -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-body p-0">
                    <img id="modalPreviewImage" src="" class="w-100" alt="Product Image">
                </div>
            </div>
        </div>
    </div>

    <?php
    include 'footer.php';
    ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.submit-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const batch = this.dataset.batch;
                    const position = this.dataset.position;
                    const name = this.dataset.name;
                    const index = this.dataset.index;

                    // Optional confirmation before submission
                    Swal.fire({
                        title: 'Are you sure?',
                        text: `Submit for ${name}?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Yes, submit it!',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33'
                    }).then(result => {
                        if (result.isConfirmed) {
                            fetch('data/submit_signatory_canvass.php', {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/x-www-form-urlencoded'
                                    },
                                    body: new URLSearchParams({
                                        batch: batch,
                                        position: position,
                                        name: name,
                                        index: index
                                    })
                                })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Submitted!',
                                            text: 'Submitted successfully!',
                                            timer: 1500,
                                            showConfirmButton: false
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire({
                                            icon: 'error',
                                            title: 'Error',
                                            text: data.message || 'Something went wrong.'
                                        });
                                    }
                                })
                                .catch(err => {
                                    console.error('AJAX error:', err);
                                    Swal.fire({
                                        icon: 'error',
                                        title: 'AJAX Error',
                                        text: 'An unexpected error occurred.'
                                    });
                                });
                        }
                    });
                });
            });

            document.querySelectorAll('.disapprove-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const batch = this.dataset.batch;
                    const position = this.dataset.position;
                    const name = this.dataset.name;
                    const index = this.dataset.index;
                    const prnumber = this.dataset.prnumber;

                    // Step 1: Ask for remarks
                    Swal.fire({
                        title: 'Remarks Required',
                        text: `Please provide the reason for disapproval.`,
                        icon: 'warning',
                        input: 'textarea',
                        inputPlaceholder: 'Enter your remarks here...',
                        inputAttributes: {
                            'aria-label': 'Enter your remarks here'
                        },
                        showCancelButton: true,
                        confirmButtonText: 'Next',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        didOpen: () => {
                            const input = Swal.getInput();
                            if (input) tribute.attach(input);
                        },
                        inputValidator: (value) => {
                            if (!value) {
                                return 'Remarks are required before proceeding.';
                            }
                        }
                    }).then(result => {
                        if (result.isConfirmed) {
                            const remarks = result.value;

                            // Step 2: Confirm submission with remarks
                            Swal.fire({
                                title: 'Confirm Disapproval',
                                html: `
                                    <p>You are about to submit a disapproval.</p>
                                    <p><b>Your remarks:</b></p>
                                    <div style="text-align:left; border:1px solid #ccc; padding:8px; border-radius:5px; background:#f8f9fa;">
                                        ${remarks}
                                    </div>
                                    <p>Do you wish to proceed?</p>
                                `,
                                icon: 'question',
                                showCancelButton: true,
                                confirmButtonText: 'Yes, submit it!',
                                cancelButtonText: 'Cancel',
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33'
                            }).then(confirmResult => {
                                if (confirmResult.isConfirmed) {
                                    fetch('data/submit_disapproved_canvass.php', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/x-www-form-urlencoded'
                                            },
                                            body: new URLSearchParams({
                                                batch: batch,
                                                position: position,
                                                name: name,
                                                index: index,
                                                remarks: remarks,
                                                prnumber: prnumber
                                            })
                                        })
                                        .then(response => response.json())
                                        .then(data => {
                                            if (data.status === 'success') {
                                                Swal.fire({
                                                    icon: 'success',
                                                    title: 'Submitted!',
                                                    text: 'Your disapproval was submitted successfully!',
                                                    timer: 1500,
                                                    showConfirmButton: false
                                                }).then(() => {
                                                    location.reload();
                                                });
                                            } else {
                                                Swal.fire({
                                                    icon: 'error',
                                                    title: 'Error',
                                                    text: data.message || 'Something went wrong.'
                                                });
                                            }
                                        })
                                        .catch(err => {
                                            console.error('AJAX error:', err);
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'AJAX Error',
                                                text: 'An unexpected error occurred.'
                                            });
                                        });
                                }
                            });
                        }
                    });
                });
            });




            // document.querySelectorAll('.submit-btn').forEach(button => {
            //     button.addEventListener('click', function() {
            //         const batch = this.dataset.batch;
            //         const position = this.dataset.position;
            //         const name = this.dataset.name;
            //         const index = this.dataset.index; // ✅ grab the index

            //         // alert(`Batch ID: ${batch}\nRole: ${position}\nName: ${name}\nIndex: ${index}`);

            //         fetch('data/submit_signatory_canvass.php', {
            //                 method: 'POST',
            //                 headers: {
            //                     'Content-Type': 'application/x-www-form-urlencoded'
            //                 },
            //                 body: new URLSearchParams({
            //                     batch: batch,
            //                     position: position,
            //                     name: name,
            //                     index: index // ✅ send it
            //                 })
            //             })
            //             .then(response => response.json())
            //             .then(data => {
            //                 if (data.status === 'success') {
            //                     alert('Submitted successfully!');
            //                     location.reload();
            //                 } else {
            //                     alert('Error: ' + data.message);
            //                 }
            //             })
            //             .catch(err => {
            //                 console.error('AJAX error:', err);
            //                 alert('An unexpected error occurred.');
            //             });
            //     });
            // });



            // Image modal functionality
            $(document).on('click', '.image-lightbox', function(e) {
                e.preventDefault();
                const imgSrc = $(this).data('img');
                $('#modalPreviewImage').attr('src', imgSrc);
            });

            $(document).on('click', '.create-po-approval', function() {
                const supplierId = $(this).data('supplier');
                const selectedItemIds = $(`.print-item-checkbox[data-supplier="${supplierId}"]:checked`).map(function() {
                    return this.value;
                }).get();
                let createPoUrl = $(this).data('base-url');

                if (selectedItemIds.length > 0) {
                    createPoUrl += '&item_ids=' + encodeURIComponent(selectedItemIds.join(','));
                }

                $(this).attr('href', createPoUrl);
            });

            // Select Supplier functionality
            $(document).on('click', '.select-supplier-btn', function(e) {
                e.preventDefault();
                const batchId = $(this).data('batch');
                const supplierId = $(this).data('supplier');
                const supplierName = $(this).data('name');

                Swal.fire({
                    title: 'Select Supplier?',
                    text: `Are you sure you want to select ${supplierName} as the recommended supplier?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, select it!',
                    cancelButtonColor: '#d33',
                    confirmButtonColor: '#3085d6'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'data/submit_selected_supplier.php',
                            type: 'POST',
                            data: {
                                batch_id: batchId,
                                supplier_id: supplierId
                            },
                            dataType: 'json',
                            success: function(res) {
                                if (res.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Success',
                                        text: res.message,
                                        timer: 1500,
                                        showConfirmButton: false
                                    }).then(() => {
                                        location.reload();
                                    });
                                } else {
                                    Swal.fire('Error', res.message, 'error');
                                }
                            },
                            error: function() {
                                Swal.fire('Error', 'An unexpected error occurred.', 'error');
                            }
                        });
                    }
                });
            });
        });

        $(document).ready(function() {
            // Enable Pusher logging (optional)
            Pusher.logToConsole = true;

            const pr_number = "<?= htmlspecialchars($pr_number) ?>";

            // Initialize Pusher ONCE
            if (typeof pusher === 'undefined') {
                var pusher = new Pusher("7f506782df003022bff5", {
                    cluster: "ap1"
                });
            }

            // Subscribe to channel
            const channel = pusher.subscribe("comments-" + pr_number);

            // Bind to event (listen for new comments/replies)
            channel.bind("new-comment", function(data) {
                if (document.getElementById(`comment-${data.id}`)) return;

                const isReply = data.parent_id && data.parent_id !== "";
                const container = isReply ?
                    document.getElementById(`replies-to-${data.parent_id}`) :
                    document.getElementById("comments-container");

                if (!container) return;

                $('.no-comments-msg').remove();

                const card = `
                    <div class="card mb-3 shadow-sm border-0 ${isReply ? 'ms-4 bg-light' : ''}" id="comment-${data.id}">
                        <div class="card-body">
                            <div class="d-flex align-items-start mb-2">
                                <img src="${data.user_pfp}"
                                     alt="User image"
                                     class="rounded-circle me-2"
                                     style="width: 40px; height: 40px; object-fit: cover;">
                                <div class="flex-grow-1">
                                    <div class="fw-bold">${data.full_name}</div>
                                    <small class="text-muted d-block comment-time-block">
                                        ${data.department} • <span class="comment-time">${data.created_date}</span>
                                    </small>
                                    <p class="mt-2 mb-0 comment-text text-break">${highlightMentionsJS(data.message.replace(/\n/g, '<br>'))}</p>
                                    
                                    <div class="mt-2">
                                        <a href="#" class="text-primary small reply-btn text-decoration-none"
                                            data-comment-id="${data.id}"
                                            data-user-name="${data.full_name}">
                                            <i class="fas fa-reply small"></i> Reply
                                        </a>
                                    </div>

                                    <div class="reply-form-container mt-2" id="reply-form-${data.id}"></div>
                                    <div class="nested-replies-container ms-4 mt-3" id="replies-to-${data.id}"></div>
                                </div>
                                ${data.user_id == "<?= $_SESSION['user_id'] ?>" ? `
                                    <div class="dropdown ms-2">
                                        <a href="#" class="text-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                            <i class="material-icons-two-tone">more_vert</i>
                                        </a>
                                        <ul class="dropdown-menu dropdown-menu-end">
                                            <li><a class="dropdown-item edit-comment" href="#" data-id="${data.id}" data-text="${data.message}">Edit</a></li>
                                            <li><a class="dropdown-item delete-comment" href="#" data-id="${data.id}">Delete</a></li>
                                        </ul>
                                    </div>
                                ` : ''}
                            </div>
                        </div>
                    </div>
                `;

                container.insertAdjacentHTML("beforeend", card);
            });

            // Handle main comment form submit
            $('#commentForm').on('submit', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');
                $btn.prop('disabled', true);

                $.ajax({
                    url: 'data/submit_canvass_comment.php',
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        $form[0].reset();
                        $('#commentAlert').removeClass('d-none').fadeIn().delay(2000).fadeOut();
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Handle Reply button click (Recursive style)
            $(document).on('click', '.reply-btn', function(e) {
                e.preventDefault();
                const commentId = $(this).data('comment-id');
                const userName = $(this).data('user-name');
                const container = $(`#reply-form-${commentId}`);

                if (container.is(':empty')) {
                    const replyForm = `
                        <form class="replyForm mt-2">
                            <input type="hidden" name="pr_number" value="${pr_number}">
                            <input type="hidden" name="parent_id" value="${commentId}">
                            <div class="input-group input-group-sm">
                                <input type="text" name="comments" class="form-control" placeholder="Reply to ${userName}..." required autofocus>
                                <button class="btn btn-primary" type="submit">Reply</button>
                                <button class="btn btn-outline-secondary cancel-reply" type="button">Cancel</button>
                            </div>
                        </form>
                    `;
                    container.html(replyForm);
                } else {
                    container.find('input').focus();
                }
            });

            // Handle Cancel Reply
            $(document).on('click', '.cancel-reply', function() {
                $(this).closest('.reply-form-container').empty();
            });

            // Handle Reply form submit
            $(document).on('submit', '.replyForm', function(e) {
                e.preventDefault();
                const $form = $(this);
                const $btn = $form.find('button[type="submit"]');
                $btn.prop('disabled', true);

                $.ajax({
                    url: 'data/submit_canvass_comment.php',
                    method: 'POST',
                    data: $form.serialize(),
                    success: function(response) {
                        $form.closest('.reply-form-container').empty();
                    },
                    complete: function() {
                        $btn.prop('disabled', false);
                    }
                });
            });

            // Edit comment logic
            $(document).on("click", ".edit-comment", function(e) {
                e.preventDefault();
                const id = $(this).data("id");
                const text = $(this).closest('.card-body').find('.comment-text').text().trim();
                $("#editCommentId").val(id);
                $("#editCommentText").val(text);
                new bootstrap.Modal(document.getElementById("editCommentModal")).show();
            });

            $("#editCommentForm").on("submit", function(e) {
                e.preventDefault();
                $.ajax({
                    url: "comment_edit.php",
                    type: "POST",
                    data: $(this).serialize(),
                    success: function(response) {
                        if (response === "success") {
                            const id = $("#editCommentId").val();
                            const newText = $("#editCommentText").val();
                            const now = new Date().toLocaleString('en-US', {
                                month: 'short',
                                day: '2-digit',
                                year: 'numeric',
                                hour: '2-digit',
                                minute: '2-digit',
                                hour12: true
                            });

                            $(`#comment-${id} .comment-text`).html(newText.replace(/\n/g, '<br>'));
                            const $timeBlock = $(`#comment-${id} .comment-time-block`);
                            if ($timeBlock.find(".edited").length === 0) {
                                $timeBlock.append(` <span class="edited text-muted ms-1">(Edited ${now})</span>`);
                            } else {
                                $timeBlock.find(".edited").text(`(Edited ${now})`);
                            }
                            bootstrap.Modal.getInstance(document.getElementById("editCommentModal")).hide();
                        }
                    }
                });
            });

            // Delete comment logic
            $(document).on("click", ".delete-comment", function(e) {
                e.preventDefault();
                const id = $(this).data("id");
                Swal.fire({
                    title: "Delete Comment?",
                    text: "This action cannot be undone.",
                    icon: "warning",
                    showCancelButton: true,
                    confirmButtonText: "Delete",
                    confirmButtonColor: '#d33'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: "comment_delete.php",
                            type: "POST",
                            data: {
                                comment_id: id
                            },
                            success: function(response) {
                                if (response === "success") {
                                    $(`#comment-${id}`).fadeOut(300, function() {
                                        $(this).remove();
                                    });
                                } else {
                                    Swal.fire("Error", "Could not delete comment.", "error");
                                }
                            }
                        });
                    }
                });
            });
        });
    </script>

    <script>
        let pdfDoc = null,
            pageNum = 1,
            pageRendering = false,
            pageNumPending = null,
            scale = 1.2,
            canvas = document.getElementById('pdfCanvas'),
            ctx = canvas.getContext('2d');

        // Load and render page
        function renderPage(num) {
            pageRendering = true;
            pdfDoc.getPage(num).then(function(page) {
                const viewport = page.getViewport({
                    scale: scale
                });
                canvas.height = viewport.height;
                canvas.width = viewport.width;

                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };
                const renderTask = page.render(renderContext);

                renderTask.promise.then(function() {
                    pageRendering = false;
                    if (pageNumPending !== null) {
                        renderPage(pageNumPending);
                        pageNumPending = null;
                    }
                });
            });

            document.getElementById('pageNum').textContent = num;
        }

        // Queue rendering if another render is in progress
        function queueRenderPage(num) {
            if (pageRendering) {
                pageNumPending = num;
            } else {
                renderPage(num);
            }
        }

        // Show Previous Page
        document.getElementById('prevPage').addEventListener('click', function() {
            if (pageNum <= 1) return;
            pageNum--;
            queueRenderPage(pageNum);
        });

        // Show Next Page
        document.getElementById('nextPage').addEventListener('click', function() {
            if (pageNum >= pdfDoc.numPages) return;
            pageNum++;
            queueRenderPage(pageNum);
        });

        // Zoom In
        document.getElementById('zoomIn').addEventListener('click', function() {
            scale += 0.2;
            renderPage(pageNum);
        });

        // Zoom Out
        document.getElementById('zoomOut').addEventListener('click', function() {
            if (scale <= 0.4) return;
            scale -= 0.2;
            renderPage(pageNum);
        });

        // Load PDF into modal
        function openPdfInModal(url) {
            pdfjsLib.getDocument(url).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('pageCount').textContent = pdfDoc.numPages;
                pageNum = 1;
                renderPage(pageNum);
                // Open modal
                const modal = new bootstrap.Modal(document.getElementById('pdfModal'));
                modal.show();
            });
        }
    </script>
    <script>
        $(document).on('click', '.viewPdfBtn', function() {
            const pdfUrl = $(this).attr('data-pdf');
            openPdfInModal(pdfUrl);
        });

        // History Modal Logic
        $(document).on('click', '.viewHistoryBtn', function() {
            const batchId = $(this).data('batch');
            const supplierId = $(this).data('supplier');
            const supplierName = $(this).data('supplier-name');

            $('#historySupplierName').text(supplierName);
            $('#historyTableBody').html('<tr><td colspan="4" class="text-center">Loading...</td></tr>');
            $('#historyModal').modal('show');

            $.ajax({
                url: 'data/get_canvass_history.php',
                type: 'GET',
                data: {
                    batch_id: batchId,
                    supplier_id: supplierId
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        let html = '';
                        if (res.history.length === 0) {
                            html = '<tr><td colspan="4" class="text-center">No history found.</td></tr>';
                        } else {
                            res.history.forEach(item => {
                                let action = '';
                                if (item.action_type === 'discount_update') action = '<span class="badge bg-info">Price</span>';
                                else if (item.action_type === 'qty_update') action = '<span class="badge bg-secondary">Quantity</span>';
                                else action = '<span class="badge bg-warning">Proposal</span>';

                                function processHistoryText(text) {
                                    if (!text) return text;

                                    // Convert legacy discount % to actual calculated price
                                    if (text.includes('Discount:')) {
                                        let match = text.match(/Item:\s*(.*?),\s*Discount:\s*([\d.]+)%/);
                                        if (match) {
                                            let itemName = match[1];
                                            let disc = parseFloat(match[2]);
                                            let basePrice = 0;
                                            $('.item-card').each(function() {
                                                if ($(this).find('h6.text-truncate').text().trim() === itemName.trim()) {
                                                    basePrice = parseFloat($(this).find('.item-discount-edit').data('price')) || parseFloat($(this).find('.item-qty-edit').data('price')) || 0;
                                                }
                                            });
                                            if (basePrice > 0) {
                                                let actualPrice = basePrice - (basePrice * (disc / 100));
                                                text = `Item: ${itemName}, Price: ₱${actualPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
                                            }
                                        }
                                    }

                                    // Strip out formatting logic to clean text logic:
                                    let clnTxt = text;
                                    clnTxt = clnTxt.replace(/Item:\s*/g, '');
                                    clnTxt = clnTxt.replace(/,\s*Price:\s*/g, ' - ');
                                    clnTxt = clnTxt.replace(/,\s*Discount:\s*/g, ' - ');
                                    clnTxt = clnTxt.replace(/,\s*Qty:\s*/g, ' - Quantity: ');
                                    clnTxt = clnTxt.replace(/Overall Discount:/g, 'Overall Deduction:');

                                    return clnTxt;
                                }

                                let oldValRaw = processHistoryText(item.old_value);
                                let newValRaw = processHistoryText(item.new_value);

                                // Add View Proposal button if it's a proposal upload
                                if (item.action_type === 'proposal_upload') {
                                    const extractFile = (text) => {
                                        if (!text) return null;
                                        // Match filename from "Initial Proposal: filename.ext" or "New Proposal: filename.ext" or "Old Proposal: filename.ext"
                                        const match = text.match(/Proposal:\s*(.*)/i);
                                        return match ? match[1].trim() : null;
                                    };

                                    const renderViewBtn = (filename) => {
                                        if (!filename || filename === 'N/A') return '';
                                        const fileExt = filename.split('.').pop().toLowerCase();
                                        const isImg = ['jpg', 'jpeg', 'png'].includes(fileExt);
                                        const btnClass = isImg ? 'image-lightbox' : 'viewPdfBtn';
                                        const modalAttr = isImg ? 'data-bs-toggle="modal" data-bs-target="#imageModal"' : '';
                                        const dataAttr = isImg ? `data-img="data/uploads/proposals/${filename}"` : `data-pdf="data/uploads/proposals/${filename}"`;

                                        return `
                                            <div class="mt-1">
                                                <button type="button" class="btn btn-sm btn-outline-primary py-0 ${btnClass}" ${modalAttr} ${dataAttr} style="font-size: 0.75rem;">
                                                    <i class="fas fa-eye"></i> View Proposal
                                                </button>
                                            </div>
                                        `;
                                    };

                                    const oldFile = extractFile(item.old_value);
                                    const newFile = extractFile(item.new_value);

                                    if (oldFile) oldValRaw += renderViewBtn(oldFile);
                                    if (newFile) newValRaw += renderViewBtn(newFile);
                                }

                                // Color extraction:
                                let oldNum = 0,
                                    newNum = 0,
                                    colorClass = '';
                                if (item.action_type === 'discount_update' || item.action_type === 'qty_update' || item.action_type === 'overall_discount_update') {
                                    // Parse for numbers (look for numbers with or without decimal, allow commas)
                                    let oldMatch = oldValRaw.match(/([\d,]+(?:\.\d+)?)/);
                                    let newMatch = newValRaw.match(/([\d,]+(?:\.\d+)?)/);

                                    if (oldMatch && newMatch) {
                                        oldNum = parseFloat(oldMatch[1].replace(/,/g, ''));
                                        newNum = parseFloat(newMatch[1].replace(/,/g, ''));

                                        if (item.action_type === 'discount_update' || item.action_type === 'overall_discount_update') {
                                            // Lower price is good (green), higher price is bad (red)
                                            if (newNum < oldNum) colorClass = 'text-success'; // Price down
                                            else if (newNum > oldNum) colorClass = 'text-danger'; // Price up
                                        } else if (item.action_type === 'qty_update') {
                                            // Lower qty is green, higher qty is red
                                            if (newNum < oldNum) colorClass = 'text-success'; // Qty down
                                            else if (newNum > oldNum) colorClass = 'text-danger'; // Qty up
                                        }
                                    }
                                }

                                html += `
                                    <tr>
                                        <td>${new Date(item.created_at).toLocaleString()}</td>
                                        <td>${item.full_name}</td>
                                        <td>${action}</td>
                                        <td style="font-size: 0.85rem;">
                                            <div class="text-muted"><del>${oldValRaw}</del></div>
                                            <div class="fw-bold ${colorClass}">${newValRaw}</div>
                                        </td>
                                    </tr>
                                `;
                            });
                        }
                        $('#historyTableBody').html(html);
                    } else {
                        $('#historyTableBody').html(`<tr><td colspan="4" class="text-center text-danger">${res.message}</td></tr>`);
                    }
                },
                error: function() {
                    $('#historyTableBody').html('<tr><td colspan="4" class="text-center text-danger">Failed to fetch history.</td></tr>');
                }
            });
        });

        // Toggle Description
        $(document).on('click', '.toggle-description', function(e) {
            e.preventDefault();
            const descId = $(this).data('id');
            const $descElem = $('#' + descId);
            if ($descElem.hasClass('collapsed-desc')) {
                $descElem.removeClass('collapsed-desc').addClass('expanded-desc');
                $(this).text('View less');
            } else {
                $descElem.removeClass('expanded-desc').addClass('collapsed-desc');
                $(this).text('View more');
            }
        });

        function refreshLowestSupplierTag() {
            let $lowestCard = null;
            let lowestTotal = null;

            $('.supplier-card').each(function() {
                const $card = $(this);
                const totalText = $card.find('.supplier-total-display').text().replace(/,/g, '');
                const total = parseFloat(totalText);

                $card.removeClass('border-success border-2');
                $card.find('.lowest-supplier-badge').remove();

                if (Number.isNaN(total)) return;

                if (lowestTotal === null || total < lowestTotal) {
                    lowestTotal = total;
                    $lowestCard = $card;
                }
            });

            if (!$lowestCard || $lowestCard.data('selected') == 1) return;

            $lowestCard.addClass('border-success border-2');
            $lowestCard.find('.supplier-title').append(
                ' <span class="badge bg-success ms-2 lowest-supplier-badge"><i class="fas fa-award"></i> LOWEST</span>'
            );
        }

        // Discount editing logic
        $(document).on('click', '.update-discount-btn', function() {
            const $btn = $(this);
            const $input = $btn.closest('.input-group').find('.item-discount-edit');
            let changedPrice = parseFloat($input.val());
            const batchId = $input.data('batch');
            const supplierId = $input.data('supplier');
            const itemId = $input.data('item');
            const qty = parseInt($input.data('qty'));
            const price = parseFloat($input.data('price'));
            const $itemCard = $input.closest('.item-card');
            const $supplierCard = $input.closest('.card-body');

            // Basic validation
            if (isNaN(changedPrice) || changedPrice < 0) changedPrice = price;

            // calculate discount backward to send to update endpoint
            let discount = 0;
            if (price > 0) {
                discount = ((price - changedPrice) / price) * 100;
            }
            discount = Math.round(discount * 100) / 100;

            $.ajax({
                url: 'data/update_canvass_discount.php',
                type: 'POST',
                data: {
                    batch_id: batchId,
                    supplier_id: supplierId,
                    item_id: itemId,
                    discount: discount
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        // Update UI
                        const discountedUnitPrice = res.discounted_unit_price;
                        const subtotal = qty * discountedUnitPrice;

                        // Update subtotal display
                        $itemCard.find('.item-subtotal-display').text(subtotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));

                        // Update unit price display if needed
                        $itemCard.find('.text-muted:first').html(`₱${discountedUnitPrice.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})} per ${$input.data('measure') || ''}`);

                        // Update date to current
                        const options = {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        };
                        $supplierCard.find('.discounted-price-date').text(new Date().toLocaleDateString('en-US', options));

                        // Recalculate supplier totals
                        let newGrossTotal = 0;
                        let itemNetSum = 0;
                        $supplierCard.find('.item-card').each(function() {
                            const q = parseInt($(this).find('.item-discount-edit').data('qty')) || 0;
                            const p = parseFloat($(this).find('.item-discount-edit').data('price')) || 0;
                            newGrossTotal += q * p;

                            const subText = $(this).find('.item-subtotal-display').text().replace(/,/g, '');
                            itemNetSum += parseFloat(subText) || 0;
                        });

                        const overallDeduction = parseFloat($supplierCard.find('.overall-discount-edit').val()) || parseFloat($supplierCard.find('.badge.bg-light.text-dark').text().replace(/[^0-9.]/g, '')) || 0;
                        const newNetTotal = itemNetSum - overallDeduction;

                        $supplierCard.find('.supplier-gross-total-display').text(newGrossTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $supplierCard.find('.supplier-total-display').text(newNetTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        refreshLowestSupplierTag();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated',
                            text: 'Discount updated successfully',
                            timer: 1000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        // Visual flash
                        $itemCard.addClass('bg-success bg-opacity-10 transition-all');
                        setTimeout(() => {
                            $itemCard.removeClass('bg-success bg-opacity-10 transition-all');
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update discount'
                    });
                }
            });
        });

        // QTY editing logic
        $(document).on('click', '.update-qty-btn', function() {
            const $btn = $(this);
            const $input = $btn.closest('.input-group').find('.item-qty-edit');
            let qty = parseInt($input.val());
            const batchId = $input.data('batch');
            const supplierId = $input.data('supplier');
            const itemId = $input.data('item');

            // get price/discounted price to re-calculate subtotal
            const $itemCard = $input.closest('.item-card');
            const $supplierCard = $input.closest('.card-body');

            // Basic validation
            if (isNaN(qty) || qty < 1) {
                qty = 1;
                $input.val(1);
            }

            $.ajax({
                url: 'data/update_canvass_qty.php',
                type: 'POST',
                data: {
                    batch_id: batchId,
                    supplier_id: supplierId,
                    item_id: itemId,
                    qty: qty
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        // find associated .item-discount-edit or use item data price to calculate
                        const $discountInput = $itemCard.find('.item-discount-edit');
                        let discountedUnitPrice = 0;
                        if ($discountInput.length) {
                            discountedUnitPrice = parseFloat($discountInput.val());
                        } else {
                            // find price badge text instead
                            const badgeText = $itemCard.find('.badge.bg-light.text-dark').text();
                            discountedUnitPrice = parseFloat(badgeText.replace('₱', '').replace(/,/g, ''));
                        }

                        // also update the data-qty attribute on the discount input
                        if ($discountInput.length) {
                            $discountInput.data('qty', qty);
                        }

                        const subtotal = qty * discountedUnitPrice;

                        // Update subtotal display
                        $itemCard.find('.item-subtotal-display').text(subtotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));

                        // Update date to current
                        const options = {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        };
                        $supplierCard.find('.discounted-price-date').text(new Date().toLocaleDateString('en-US', options));

                        // Recalculate supplier totals
                        let newGrossTotal = 0;
                        let itemNetSum = 0;
                        $supplierCard.find('.item-card').each(function() {
                            const q = parseInt($(this).find('.item-qty-edit').val()) || parseInt($(this).find('.text-muted:contains("Qty")').text().replace(/[^0-9]/g, '')) || 0;
                            // Need to find original unit price...
                            const discountInput = $(this).find('.item-discount-edit');
                            let p = 0;
                            if (discountInput.length) {
                                p = parseFloat(discountInput.data('price')) || 0;
                            } else {
                                p = parseFloat($(this).find('.text-muted:contains("pe")').text().split(' per ')[0].replace(/[^0-9.]/g, '')) || 0; // fallback but text might be discounted... well for non Purchasing Staff we shouldn't get here easily. But if so:
                            }

                            newGrossTotal += q * p;

                            const subText = $(this).find('.item-subtotal-display').text().replace(/,/g, '');
                            itemNetSum += parseFloat(subText) || 0;
                        });

                        const overallDeduction = parseFloat($supplierCard.find('.overall-discount-edit').val()) || parseFloat($supplierCard.find('.badge.bg-light.text-dark').text().replace(/[^0-9.]/g, '')) || 0;
                        const newNetTotal = itemNetSum - overallDeduction;

                        $supplierCard.find('.supplier-gross-total-display').text(newGrossTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        $supplierCard.find('.supplier-total-display').text(newNetTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        refreshLowestSupplierTag();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated',
                            text: 'Quantity updated successfully',
                            timer: 1000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        // Visual flash
                        $itemCard.addClass('bg-success bg-opacity-10 transition-all');
                        setTimeout(() => {
                            $itemCard.removeClass('bg-success bg-opacity-10 transition-all');
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update quantity'
                    });
                }
            });
        });

        // Overall Discount editing logic
        $(document).on('click', '.update-overall-discount-btn', function() {
            const $btn = $(this);
            const $input = $btn.closest('.input-group').find('.overall-discount-edit');
            const discount = parseFloat($input.val()) || 0;
            const batchId = $input.data('batch');
            const supplierId = $input.data('supplier');
            const $supplierCard = $input.closest('.card-body');

            // Basic validation
            if (discount < 0) $input.val(0);
            if (discount > 100) $input.val(100);

            $.ajax({
                url: 'data/update_overall_discount.php',
                type: 'POST',
                data: {
                    batch_id: batchId,
                    supplier_id: supplierId,
                    overall_discount: discount
                },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        // Update date to current
                        const options = {
                            year: 'numeric',
                            month: 'long',
                            day: 'numeric'
                        };
                        $supplierCard.find('.discounted-price-date').text(new Date().toLocaleDateString('en-US', options));

                        // Recalculate total with new overall discount
                        let itemNetSum = 0;
                        $supplierCard.find('.item-card').each(function() {
                            const subText = $(this).find('.item-subtotal-display').text().replace(/,/g, '');
                            itemNetSum += parseFloat(subText) || 0;
                        });

                        const newNetTotal = itemNetSum - discount;
                        $supplierCard.find('.supplier-total-display').text(newNetTotal.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }));
                        refreshLowestSupplierTag();

                        Swal.fire({
                            icon: 'success',
                            title: 'Updated',
                            text: 'Overall discount updated successfully',
                            timer: 1000,
                            showConfirmButton: false,
                            toast: true,
                            position: 'top-end'
                        });

                        // Visual flash
                        $supplierCard.addClass('bg-success bg-opacity-10 transition-all');
                        setTimeout(() => {
                            $supplierCard.removeClass('bg-success bg-opacity-10 transition-all');
                        }, 1000);
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to update overall discount'
                    });
                }
            });
        });

        // Proposal Upload Logic
        $(document).on('click', '.uploadProposalBtn', function() {
            $(this).closest('.uploadProposalForm').find('.proposalFileInput').click();
        });

        $(document).on('change', '.proposalFileInput', function() {
            const $input = $(this);
            const $form = $input.closest('.uploadProposalForm');
            const formData = new FormData($form[0]);

            if (!$input.val()) return;

            Swal.fire({
                title: 'Uploading...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            $.ajax({
                url: 'data/upload_proposal.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Uploaded',
                            text: res.message,
                            timer: 2000
                        }).then(() => {
                            location.reload(); // Reload to update view buttons
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: res.message
                        });
                    }
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Failed to upload proposal'
                    });
                }
            });
        });
    </script>
