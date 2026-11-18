<?php
require '../init.php';
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
        cq.quotation_file
    FROM canvass_items po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN items i ON po.item_id = i.item_id
    LEFT JOIN canvass_quotation cq ON po.batch_id = cq.batch_id AND po.supplier_id = cq.supplier_id
    WHERE po.batch_id = ?

");
    // ORDER BY s.supplier_name
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
            'items' => []
        ];
    }
    $suppliers[$sid]['items'][] = $order;
}

$stmt = $pdo->prepare("SELECT pr_status FROM purchase_requisition WHERE pr_number = ?");
$stmt->execute([$pr_number]);
$prStatus = $stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT 
        csb.budget_code,
        b.budget_activity,
        b.approved_budget,
        COALESCE(SUM(e.expense_amount), 0) AS total_expenses,
        (b.approved_budget - COALESCE(SUM(e.expense_amount), 0)) AS remaining_budget
    FROM canvass_sheet_batches csb
    LEFT JOIN budgets b 
        ON csb.budget_code = b.budget_code
    LEFT JOIN expenses e
        ON b.budget_code = e.budget_code
    WHERE csb.pr_number = ?
    GROUP BY csb.budget_code, b.budget_activity, b.approved_budget
    LIMIT 1
");
$stmt->execute([$pr_number]);

$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result) {
    $budgetCode      = $result['budget_code'];
    $budgetActivity  = $result['budget_activity'];
    $budgetApproved  = $result['approved_budget'];
    $totalExpenses   = $result['total_expenses'];
    $remainingBudget = $result['remaining_budget'];

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
    $total = 0;
    foreach ($supplier['items'] as $item) {
        $total += $item['qty'] * $item['unit_price'];
    }

    if ($lowestTotal === null || $total < $lowestTotal) {
        $lowestTotal = $total;
        $lowestIndex = $i;
    }
}


$stmt = $pdo->prepare("SELECT r.*, u.full_name, u.user_pfp, u.department 
                       FROM recommendations_suggestions r
                       JOIN users u ON r.user_id = u.user_id
                       WHERE r.pr_number = ?
                       ORDER BY r.created_date ASC");
$stmt->execute([$pr_number]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);



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
                                <img src="assets/images/<?php echo $_SESSION['dealer']; ?>.png" class="img-fluid logo-md" alt="logo" style="width: 250px;" />
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
        <div style="border: 2px solid #000000; padding: 20px; border-radius: 10px;">
            <div class="row">
                <?php foreach (array_values($suppliers) as $index => $data): ?>
                    <?php
                    // Step 2: Calculate total again per supplier for display
                    $total = 0;
                    foreach ($data['items'] as $item) {
                        $total += $item['qty'] * $item['unit_price'];
                    }

                    // Step 3: Apply highlight class if this is the lowest
                    $highlightClass = ($index === $lowestIndex) ? 'border-success border-3' : '';
                    ?>
                    <div class="col-md-4 col-lg-4 mb-3">
                        <div class="card shadow-sm rounded-4 <?= $highlightClass ?>">
                            <div class="card-body">

                                <h6 class="fw-semibold mb-2 text-primary">
                                    SUPPLIER <?= $index + 1 ?>
                                    <?php if ($_SESSION['position'] !== 'Manager' || $_SESSION['department'] === 'PURCHASING'): ?>

                                        <div><?= htmlspecialchars($data['supplier_name']) ?></div>
                                </h6>

                                <div class="mt-2 text-muted small lh-sm no-print">
                                    <div>CONTACT PERSON: <?= htmlspecialchars($data['contact_person']) ?></div>
                                    <div>CONTACT NUMBER: <?= htmlspecialchars($data['contact_number']) ?></div>
                                    <div>TERMS OF PAYMENT: <?= htmlspecialchars($data['payment_terms']) ?></div>
                                </div>
                            <?php endif; ?>

                            <ul class="list-group list-group-flush selectedItems mb-2 rounded border mt-3">
                                <?php foreach ($data['items'] as $item): ?>
                                    <?php
                                    $subtotal = $item['qty'] * $item['unit_price'];
                                    $description = htmlspecialchars($item['description']);
                                    $isLong = mb_strlen($description) > 60;
                                    $uniqueId = uniqid('desc_');
                                    $imagePath = 'data/' . (!empty($item['product_image']) ? $item['product_image'] : 'uploads/items/default.jpeg');
                                    ?>
                                    <li class="list-group-item item-card">
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
                                                $shortName = mb_strlen($name) > 60 ? mb_substr($name, 0, 40) . '...' : $name;
                                                ?>
                                                <strong class="item-name"><?= htmlspecialchars($shortName) ?></strong>

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
                                                <div class="d-flex justify-content-around flex-wrap m-2">
                                                    <?php if ($_SESSION['position'] !== 'Manager' || $_SESSION['department'] === 'PURCHASING'): ?>
                                                        <small class="text-muted">
                                                            ₱<?= number_format($item['unit_price'], 2) . ' per ' . htmlspecialchars($item['unit_measure']) ?>
                                                        </small>
                                                    <?php endif; ?>

                                                    <small class="text-muted">Qty: <?= $item['qty'] ?></small>

                                                    <?php if ($_SESSION['position'] !== 'Manager' || $_SESSION['department'] === 'PURCHASING'): ?>
                                                        <small class="text-muted">
                                                            Subtotal: ₱<?= number_format($subtotal, 2) ?>
                                                        </small>
                                                    <?php endif; ?>
                                                </div>


                                            </div>

                                        </div>

                                    </li>


                                <?php endforeach; ?>

                            </ul>
                            <?php if ($_SESSION['position'] !== 'Manager' || $_SESSION['department'] === 'PURCHASING'): ?>
                                <?php if (!empty($data['quotation_file'])): ?>
                                    <button type="button"
                                        class="btn btn-outline-primary btn-sm viewPdfBtn"
                                        data-pdf="data/uploads/proposals/<?= htmlspecialchars($data['quotation_file']) ?>">
                                        View Proposal
                                    </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <style>
                                .item-card {
                                    padding: 10px;
                                }

                                .item-wrapper {
                                    display: flex;
                                    gap: 10px;
                                    align-items: flex-start;
                                    flex-wrap: nowrap;
                                }

                                .item-image img {
                                    width: 60px;
                                    height: 60px;
                                    object-fit: cover;
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
                            </style>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    document.querySelectorAll('.toggle-description').forEach(link => {
                                        link.addEventListener('click', function(e) {
                                            e.preventDefault();
                                            const descId = this.getAttribute('data-id');
                                            const descElem = document.getElementById(descId);

                                            if (descElem.classList.contains('collapsed-desc')) {
                                                descElem.classList.remove('collapsed-desc');
                                                descElem.classList.add('expanded-desc');
                                                this.textContent = 'View less';
                                            } else {
                                                descElem.classList.remove('expanded-desc');
                                                descElem.classList.add('collapsed-desc');
                                                this.textContent = 'View more';
                                            }
                                        });
                                    });
                                });
                            </script>
                            <?php if ($_SESSION['position'] !== 'Manager' || $_SESSION['department'] === 'PURCHASING'): ?>
                                <div class="text-end fw-semibold text-dark">
                                    Total: ₱<?= number_format($total, 2) ?>
                                </div>
                            <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

            </div>
            
             <br><p class="mb-0"><b>BUDGET CODE: <?= htmlspecialchars($budgetCode ?? '') ?></b></p>
                                    <p class="mb-0"><b>BUDGET ACTIVITY: <?= htmlspecialchars($budgetActivity ?? '') ?></b></p>
                                    <p class="mb-0">
    <b>APPROVED BUDGET: ₱<?= number_format((float)($budgetApproved ?? 0), 2) ?></b>
</p>

<p class="mb-0">
    <b>TOTAL EXPENSES: ₱<?= number_format((float)($totalExpenses ?? 0), 2) ?></b>
</p>

<p class="mb-0">
    <b>REMAINING BUDGET: ₱<?= number_format((float)($remainingBudget ?? 0), 2) ?></b>
</p></br>
            <div>
                <p><strong>Recommendations and Suggestions:</strong></p>

            </div>
            <?php foreach ($comments as $comment): ?>
                <div class="card mb-3 shadow-sm border-0" id="comment-<?= $comment['id'] ?>">
                    <div class="card-body">
                        <div class="d-flex align-items-start mb-2">
                            <img src="<?php echo htmlspecialchars($comment['user_pfp']); ?>"
                                alt="User image"
                                class="rounded-circle me-2"
                                style="width: 40px; height: 40px; object-fit: cover;">

                            <div class="flex-grow-1">
                                <div class="fw-bold">
                                    <?php echo htmlspecialchars($comment['full_name']); ?>
                                </div>
                                <small class="text-muted d-block comment-time">
                                    <?php echo htmlspecialchars($comment['department']); ?> •
                                    <?php echo date('M d, Y h:i A', strtotime($comment['created_date'])); ?>
                                    <?php if (!empty($comment['updated_at'])): ?>
                                        <span class="edited text-muted">(Edited <?= date('M d, Y h:i A', strtotime($comment['updated_at'])); ?>)</span>
                                    <?php endif; ?>
                                </small>
                                <p class="mt-2 mb-0 comment-text">
                                    <?php echo nl2br(htmlspecialchars($comment['comments'])); ?>
                                </p>
                            </div>

                            <?php if ($_SESSION['user_id'] == $comment['user_id']): ?>
                                <div class="dropdown ms-2">
                                    <a href="#" class="text-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                                        <i class="material-icons-two-tone">more_vert</i>
                                    </a>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <a class="dropdown-item edit-comment" href="#"
                                                data-id="<?= $comment['id'] ?>"
                                                data-text="<?= htmlspecialchars($comment['comments'], ENT_QUOTES) ?>">
                                                Edit
                                            </a>
                                        </li>
                                        <li>
                                            <a class="dropdown-item delete-comment" href="#"
                                                data-id="<?= $comment['id'] ?>">
                                                Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <div id="comments"></div>

            <form id="commentForm" class="no-print">
                <input type="hidden" name="pr_number" value="<?= htmlspecialchars($pr_number) ?>">
                <input type="hidden" name="user_id" value="<?= $_SESSION['user_id'] ?>">
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

                    <?php if ($user['user_id'] !== 84): ?>
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
    });

     $(document).ready(function() {
            // Enable Pusher logging
            Pusher.logToConsole = true;

            const pr_number = "<?= htmlspecialchars($pr_number) ?>";

            // Initialize Pusher once
            const pusherView = new Pusher("7f506782df003022bff5", {
                cluster: "ap1"
            });

            // Subscribe to channel
            const channel = pusherView.subscribe("comments-" + pr_number);

            // Bind to event (listen for new comments)
            channel.bind("new-comment", function(data) {
                const commentsDiv = document.getElementById("comments");

                // Build Bootstrap card
                const card = `
            <div class="card mb-3 shadow-sm border-0">
                <div class="card-body">
                    <div class="d-flex align-items-start mb-2">
                        <img src="${data.user_pfp}"
                             alt="User image"
                             class="rounded-circle me-2"
                             style="width: 40px; height: 40px; object-fit: cover;">
                        <div class="flex-grow-1">
                            <div class="fw-bold">${data.full_name}</div>
                            <small class="text-muted d-block">
                                ${data.department} • ${data.created_date}
                            </small>
                            <p class="mt-2 mb-0">${data.message}</p>
                        </div>
                    </div>
                </div>
            </div>
        `;

                // Append to top (newest first)
                commentsDiv.insertAdjacentHTML("afterbegin", card);
            });

            // Handle form submit (AJAX)
            $('#commentForm').on('submit', function(e) {
                e.preventDefault();

                $.ajax({
                    url: 'data/submit_canvass_comment.php',
                    method: 'POST',
                    data: $(this).serialize() + '&pr_number=' + encodeURIComponent(pr_number),
                    success: function(response) {
                        $('#commentAlert').removeClass('d-none').text('Comment submitted!');
                        $('#commentForm')[0].reset();
                    },
                    error: function() {
                        alert('Error submitting comment. Please try again.');
                    }
                });
            });
        });

        // Edit comment (open modal)
        $(document).on("click", ".edit-comment", function(e) {
            e.preventDefault();
            let commentId = $(this).data("id");
            let commentText = $(this).data("text");

            $("#editCommentId").val(commentId);
            $("#editCommentText").val(commentText);

            let modal = new bootstrap.Modal(document.getElementById("editCommentModal"));
            modal.show();
        });

        // Submit edit form
        $("#editCommentForm").on("submit", function(e) {
            e.preventDefault();
            $.ajax({
                url: "comment_edit.php",
                type: "POST",
                data: $(this).serialize(),
                success: function(response) {
                    if (response === "success") {
                        $("#editCommentModal").modal("hide");

                        let id = $("#editCommentId").val();
                        let newText = $("#editCommentText").val();
                        let now = new Date().toLocaleString('en-US', {
                            month: 'short',
                            day: '2-digit',
                            year: 'numeric',
                            hour: '2-digit',
                            minute: '2-digit',
                            hour12: true
                        });

                        // update comment text
                        $(`#comment-${id} .comment-text`).text(newText);

                        // update "Edited" timestamp
                        let timeBlock = $(`#comment-${id} .comment-time`);
                        if (timeBlock.find(".edited").length === 0) {
                            timeBlock.append(` <span class="edited text-muted">(Edited ${now})</span>`);
                        } else {
                            timeBlock.find(".edited").text(`(Edited ${now})`);
                        }
                    }
                }
            });
        });

        // Delete comment
        $(document).on("click", ".delete-comment", function(e) {
            e.preventDefault();
            let commentId = $(this).data("id");

            Swal.fire({
                title: "Are you sure?",
                text: "This will permanently delete your comment.",
                icon: "warning",
                showCancelButton: true,
                confirmButtonText: "Yes, delete it!",
                cancelButtonText: "Cancel"
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: "comment_delete.php",
                        type: "POST",
                        data: {
                            comment_id: commentId
                        },
                        success: function(response) {
                            if (response === "success") {
                                $(`#comment-${commentId}`).remove();
                                Swal.fire("Deleted!", "Your comment has been deleted.", "success");
                            } else {
                                Swal.fire("Error", "Could not delete comment.", "error");
                            }
                        }
                    });
                }
            });
        });

    // Function to reload comments without refresh
    // function loadComments() {
    //     const prNumber = $('input[name="pr_number"]').val();
    //     $.ajax({
    //         url: 'load_comments.php',
    //         method: 'GET',
    //         data: {
    //             pr_number: prNumber
    //         },
    //         success: function(data) {
    //             $('#commentContainer').html(data);
    //         }
    //     });
    // }
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
    document.querySelectorAll('.viewPdfBtn').forEach(btn => {
        btn.addEventListener('click', function() {
            const pdfUrl = this.getAttribute('data-pdf');
            openPdfInModal(pdfUrl);
        });
    });
</script>
<!-- <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.toggle-description').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const descId = this.getAttribute('data-id');
                const descElem = document.getElementById(descId);
                const fullText = this.getAttribute('data-full');

                if (this.textContent === 'View more') {
                    descElem.textContent = fullText;
                    this.textContent = 'View less';
                } else {
                    descElem.textContent = fullText.substring(0, 60) + '...';
                    this.textContent = 'View more';
                }
            });
        });
    });
</script> -->