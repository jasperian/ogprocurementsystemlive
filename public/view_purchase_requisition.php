<?php
require '../init.php';
// echo "Welcome, " . htmlspecialchars($_SESSION['username']);

ini_set('display_errors', 1);
error_reporting(E_ALL);
$pr_number = $_GET['pr_number'] ?? '';
if (!$pr_number) {
    die('PR Number missing.');
}

// Get batch info
$stmt = $pdo->prepare("SELECT * FROM purchase_requisition WHERE pr_number = ?");
$stmt->execute([$pr_number]);
$batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$batch) {
    die('PR Number not found.');
}

$pr_department = $batch['pr_department'];
$pr_created = $batch['pr_date'];
$pr_file = $batch['pr_file'];

if (!empty($batch['canvass_created']) && $batch['canvass_created'] !== '0000-00-00') {
    $canvass_created = date('F j, Y', strtotime($batch['canvass_created']));
    $canvass_disabled = false;
} else {
    $canvass_created = 'N/A';
    $canvass_disabled = true;
}


$po_stmt = $pdo->prepare("
    SELECT po.po_id, po.po_number, po.po_date, po.amount_purchased, po.po_file,
           s.supplier_name
    FROM purchase_order po
    JOIN suppliers s ON po.supplier_id = s.supplier_id
    JOIN purchase_requisition pr ON po.pr_number = pr.pr_number
    WHERE pr.pr_number = :pr_number
    ORDER BY po.po_date ASC
");
$po_stmt->execute(['pr_number' => $pr_number]);
$purchase_orders = $po_stmt->fetchAll(PDO::FETCH_ASSOC);


$total_amount = 0;
foreach ($purchase_orders as $po) {
    $total_amount += $po['amount_purchased'];
}

$stmt = $pdo->prepare("SELECT * FROM v_pr_leadtime_tracking WHERE pr_number = :pr_number");
$stmt->execute([':pr_number' => $pr_number]);
$leadtime = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper function to format days+hours properly
function formatDaysHours($hours)
{
    $days = floor($hours / 24);
    $remaining_hours = $hours % 24;

    if ($days > 0 && $remaining_hours > 0) {
        return $days . " Day" . ($days != 1 ? "s" : "") . " " .
            $remaining_hours . " Hour" . ($remaining_hours != 1 ? "s" : "");
    } elseif ($days > 0) {
        return $days . " Day" . ($days != 1 ? "s" : "");
    } elseif ($remaining_hours > 0) {
        return $remaining_hours . " Hour" . ($remaining_hours != 1 ? "s" : "");
    } else {
        return "0 Hours";
    }
}

// Now all steps are already in hours
$step1 = $leadtime['leadtime_received_to_canvass'] ?? 0;
$step2 = $leadtime['leadtime_canvass_to_approval'] ?? 0;
$step3 = $leadtime['leadtime_approved_to_po'] ?? 0;

$total_hours = $step1 + $step2 + $step3;

// Avoid division by zero for width %
$w1 = $total_hours ? ($step1 / $total_hours) * 100 : 0;
$w2 = $total_hours ? ($step2 / $total_hours) * 100 : 0;
$w3 = $total_hours ? ($step3 / $total_hours) * 100 : 0;

// Format each step
$step1_formatted = formatDaysHours($step1);
$step2_formatted = formatDaysHours($step2);
$step3_formatted = formatDaysHours($step3);
// $total_leadtime_formatted = formatDaysHours($total_hours);

// Format total lead time
$total_days = floor($total_hours / 24);
$remaining_hours = $total_hours % 24;
$total_leadtime_formatted = formatDaysHours($total_hours);


include 'header.php';
include 'navigation.php';
?>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

<style>
    /* Target Select2 placeholder text */
    .select2-container--bootstrap-5 .select2-selection__placeholder {
        font-size: 0.85rem;
        /* Smaller than normal */
        color: #bec8d0;
        /* Bootstrap's secondary text */
    }
</style>
<!-- [ Main Content ] start -->
<div class="pc-container">
    <div class="pc-content">
        <!-- [ breadcrumb ] start -->
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">View Purchase Requisition</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- [ breadcrumb ] end --><!-- [ Main Content ] start -->
        <div class="row">
            <!-- [ sample-page ] start -->
            <div class="col-sm-9">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="card-title">Purchase Requisition Details</h5>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="supplierSelect" class="form-label">PR Number</label>
                                    <input class="form-control" type="text" value="<?php echo htmlspecialchars($pr_number); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="supplierSelect" class="form-label">PR Created</label>
                                    <input class="form-control" type="text" value="<?php echo date('F j, Y', strtotime($pr_created)); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="prDepartment" class="form-label">Department</label>
                                    <input class="form-control" id="prDepartment" type="text" value="<?php echo htmlspecialchars($pr_department); ?>" readonly>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="prFile" class="form-label">PR File</label>
                                    <div class="input-group">
                                        <a href="data/<?php echo htmlspecialchars($pr_file); ?>" target="_blank" class="btn btn-primary">
                                            View File
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-3">
                <div class="card shadow">
                    <div class="card-body">
                        <h5 class="card-title">Canvass Sheet</h5>
                        <p class="card-text">Created on: <?php echo $canvass_created; ?></p>
                        <div class="mb-3">
                            <div class="input-group">
                                <a href="canvass_view?pr_number=<?php echo htmlspecialchars($pr_number); ?>"
                                    class="btn btn-primary <?php echo $canvass_disabled ? 'disabled' : ''; ?>"
                                    <?php echo $canvass_disabled ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>
                                    View Canvass Sheet
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card shadow">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="card-title mb-0">Purchase Orders</h5>
                            <span class="badge bg-primary fs-6">
                                Total: ₱ <?php echo number_format($total_amount, 2); ?>
                            </span>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th scope="col">PO Number</th>
                                        <th scope="col">PO Date</th>
                                        <th scope="col">Supplier</th>
                                        <th scope="col" class="text-end">Amount</th>
                                        <th scope="col" class="text-center">File</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $total_amount = 0;
                                    foreach ($purchase_orders as $po):
                                        $total_amount += $po['amount_purchased'];
                                    ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($po['po_number']); ?></td>
                                            <td><?php echo date("M d, Y", strtotime($po['po_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($po['supplier_name']); ?></td>
                                            <td class="text-end">₱ <?php echo number_format($po['amount_purchased'], 2); ?></td>
                                            <td class="text-center">
                                                <?php if (!empty($po['po_file'])): ?>
                                                    <a href="../<?php echo htmlspecialchars($po['po_file']); ?>"
                                                        target="_blank" class="btn btn-sm btn-outline-primary">
                                                        View
                                                    </a>
                                                <?php else: ?>
                                                    <span class="text-muted">No file</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-12">
                <div class="card shadow p-3 mb-4">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="text-uppercase fw-bold mb-0">Lead Time</h6>
                            <span class="badge bg-light text-dark"><i class="bi bi-hand-thumbs-up"></i></span>
                        </div>
                        <h4 class="fw-bold">
                            <?= $total_leadtime_formatted ?>
                            <small class="text-muted">Lead Time</small>
                        </h4>

                        <!-- Progress Steps -->
                        <div class="d-flex align-items-center mt-3">
                            <!-- Step 1 -->
                            <div class="flex-fill text-center">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-primary" style="width: <?= $w1 ?>%;"></div>
                                </div>
                                <p class="mb-1 mt-2 text-muted text-uppercase small">PR Received → Canvass</p>
                                <h6 class="fw-bold">
                                    <?= $step1_formatted ?: 'N/A' ?>
                                </h6>
                            </div>

                            <i class="bi bi-chevron-right mx-2 text-muted"></i>

                            <!-- Step 2 -->
                            <div class="flex-fill text-center">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-info" style="width: <?= $w2 ?>%;"></div>
                                </div>
                                <p class="mb-1 mt-2 text-muted text-uppercase small">Canvass → Approval</p>
                                <h6 class="fw-bold">
                                    <?= $step2_formatted ?: 'N/A' ?>
                                </h6>
                            </div>

                            <i class="bi bi-chevron-right mx-2 text-muted"></i>

                            <!-- Step 4 -->
                            <div class="flex-fill text-center">
                                <div class="progress" style="height: 6px;">
                                    <div class="progress-bar bg-success" style="width: <?= $w3 ?>%;"></div>
                                </div>
                                <p class="mb-1 mt-2 text-muted text-uppercase small">Approved → PO</p>
                                <h6 class="fw-bold">
                                    <?= $step3_formatted ?: 'N/A' ?>
                                </h6>
                            </div>
                        </div>
                    </div>
                </div>

            </div>




            <!-- [ sample-page ] end -->
        </div>
        <!-- [ Main Content ] end -->
    </div>
</div>


<?php
include 'footer.php';
?>