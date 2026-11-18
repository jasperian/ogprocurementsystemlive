<?php
require '../init.php';
require 'includes/po_approval_helpers.php';

po_approval_ensure_tables($pdo);

$requestId = (int)($_GET['po_approval_id'] ?? 0);
if ($requestId <= 0) {
    die('PO approval request missing.');
}

$stmt = $pdo->prepare("
    SELECT par.*, s.supplier_name, s.street, s.contact_person, s.contact_number, s.payment_terms
    FROM po_approval_requests par
    JOIN suppliers s ON par.supplier_id = s.supplier_id
    WHERE par.request_id = ?
");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    die('PO approval request not found.');
}

$stmt = $pdo->prepare("SELECT * FROM po_approval_items WHERE request_id = ? ORDER BY item_name");
$stmt->execute([$requestId]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare("SELECT COALESCE(overall_discount, 0) FROM canvass_quotation WHERE batch_id = ? AND supplier_id = ?");
$stmt->execute([$request['batch_id'], $request['supplier_id']]);
$overallDiscount = (float)$stmt->fetchColumn();

$signatures = po_approval_get_signatures($pdo, $requestId);
$nextSignature = po_approval_get_next_signature($signatures);
$isPurchasing = po_approval_is_purchasing_user();
$isManager = po_approval_is_manager_user();
$currentUserId = (int)($_SESSION['user_id'] ?? 0);
$canSubmit = $request['status'] === 'draft' && $isPurchasing;
$canApprove = $request['status'] === 'pending' && $nextSignature && (int)$nextSignature['approver_user_id'] === $currentUserId;
$canCancel = $isManager && !in_array($request['status'], ['approved', 'cancelled'], true);
$canEditPoDetails = !in_array($request['status'], ['approved', 'cancelled'], true) && ($isPurchasing || (int)$request['created_by'] === $currentUserId);
$displayPoNumber = $request['final_po_number'] ?: $request['placeholder_po_number'];
$budgetSummary = null;
$budgetSummaryError = '';
try {
    $budgetSummary = po_approval_get_budget_summary(
        $pdo,
        $request['pr_number'],
        $request['budget_code'] ?? null,
        $request['dealer'] ?? null
    );
} catch (Exception $e) {
    $budgetSummaryError = $e->getMessage();
}
$remainingAfterPo = $budgetSummary ? ((float)$budgetSummary['remaining_budget'] - (float)$request['amount']) : null;

include 'header.php';
include 'navigation.php';
?>

<style>
    .budget-view-summary {
        margin-bottom: 16px;
        border: 1px solid #dbe4f0;
        border-radius: 8px;
        background: #fff;
        overflow: hidden;
    }

    .budget-summary-header {
        display: flex;
        justify-content: space-between;
        gap: 14px;
        padding: 14px 16px;
        color: #fff;
        background: #173b64;
    }

    .budget-summary-eyebrow,
    .budget-summary-metric-label {
        display: block;
        color: #64748b;
        font-size: .68rem;
        font-weight: 700;
        text-transform: uppercase;
    }

    .budget-summary-header .budget-summary-eyebrow {
        color: rgba(255,255,255,.72);
    }

    .budget-summary-code {
        margin: 0;
        color: #fff;
        font-size: 1rem;
        font-weight: 700;
    }

    .budget-summary-activity {
        color: rgba(255,255,255,.9);
        font-size: .78rem;
        text-align: right;
    }

    .budget-summary-metrics {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
        padding: 14px 16px;
    }

    .budget-summary-metric {
        min-width: 0;
        padding: 11px 12px;
        border: 1px solid #e5eaf1;
        border-radius: 8px;
        background: #f8fafc;
    }

    .budget-summary-metric-value {
        display: block;
        margin-top: 5px;
        color: #172033;
        font-size: .92rem;
        font-weight: 700;
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
        padding: 0 16px 16px;
    }

    .budget-summary-progress-meta {
        display: flex;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 7px;
        color: #64748b;
        font-size: .72rem;
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
        background: #2f80ed;
    }

    .budget-summary-progress-bar.is-over-budget {
        background: #e11d48;
    }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="page-header-title">
                                <h2 class="mb-0">PO Approval</h2>
                            </div>
                            <a class="btn btn-outline-primary" href="print_supplier_canvass.php?po_approval_id=<?= $requestId ?>" target="_blank">
                                Preview PO PDF
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-3">
                            <div>
                                <h5 class="mb-1"><?= htmlspecialchars($request['supplier_name']) ?></h5>
                                <div class="text-muted small">PR Number: <?= htmlspecialchars($request['pr_number']) ?></div>
                                <div class="text-muted small">
                                    Status:
                                    <?php if ($request['status'] === 'cancelled'): ?>
                                        <span class="badge bg-danger">CANCELLED</span>
                                    <?php elseif ($request['status'] === 'approved'): ?>
                                        <span class="badge bg-success">APPROVED</span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(strtoupper($request['status'])) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-end">
                                <div class="fw-semibold">PO Number</div>
                                <div><?= htmlspecialchars($displayPoNumber) ?></div>
                            </div>
                        </div>

                        <form id="poApprovalForm">
                            <input type="hidden" name="po_approval_id" value="<?= $requestId ?>">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Final PO Number</label>
                                    <input type="text" name="final_po_number" class="form-control" value="<?= htmlspecialchars($displayPoNumber) ?>" <?= $canEditPoDetails ? '' : 'readonly' ?>>
                                    <small class="text-muted">Replace the pending number before final approval.</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">PO Date</label>
                                    <input type="date" name="po_date" class="form-control" value="<?= htmlspecialchars($request['po_date'] ?: date('Y-m-d')) ?>" <?= $canEditPoDetails ? '' : 'readonly' ?>>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Qty</th>
                                            <th class="text-center">Unit</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($item['item_name']) ?></td>
                                                <td class="text-center"><?= number_format((float)$item['qty'], 2) ?></td>
                                                <td class="text-center"><?= htmlspecialchars($item['unit_measure'] ?? '') ?></td>
                                                <td class="text-end"><?= number_format((float)$item['discounted_unit_price'], 2) ?></td>
                                                <td class="text-end"><?= number_format((float)$item['subtotal'], 2) ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <div class="text-end">
                                <div>Overall Deduction: PHP <?= number_format($overallDiscount, 2) ?></div>
                                <h5 class="text-primary mt-1">Net Total: PHP <?= number_format((float)$request['amount'], 2) ?></h5>
                            </div>

                            <div class="d-flex justify-content-end gap-2 mt-4">
                                <?php if ($canEditPoDetails && $request['status'] !== 'approved'): ?>
                                    <button type="button" class="btn btn-outline-secondary po-action-btn" data-action="save_details">Save PO Details</button>
                                <?php endif; ?>
                                <?php if ($canSubmit): ?>
                                    <button type="button" class="btn btn-primary po-action-btn" data-action="submit">Submit for Approval</button>
                                <?php endif; ?>
                                <?php if ($canApprove): ?>
                                    <button type="button" class="btn btn-success po-action-btn" data-action="approve">Approve PO</button>
                                <?php endif; ?>
                                <?php if ($canCancel): ?>
                                    <button type="button" class="btn btn-outline-danger po-action-btn" data-action="cancel">Cancel PO</button>
                                <?php endif; ?>
                                <?php if (!$canSubmit && !$canApprove && !in_array($request['status'], ['approved', 'cancelled'], true)): ?>
                                    <span class="text-muted align-self-center">
                                        Waiting for <?= $nextSignature ? htmlspecialchars($nextSignature['full_name']) : 'approval setup' ?>.
                                    </span>
                                <?php elseif ($request['status'] === 'cancelled'): ?>
                                    <span class="text-danger align-self-center">This PO has been cancelled.</span>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <?php if ($budgetSummary): ?>
                    <?php
                    $approvedBudget = (float)$budgetSummary['approved_budget'];
                    $totalBudgetExpenses = (float)$budgetSummary['total_expenses'];
                    $remainingBudget = (float)$budgetSummary['remaining_budget'];
                    $budgetUtilization = (float)$budgetSummary['budget_utilization'];
                    ?>
                    <div class="budget-view-summary">
                        <div class="budget-summary-header">
                            <div>
                                <span class="budget-summary-eyebrow">Budget Summary</span>
                                <h5 class="budget-summary-code"><?= htmlspecialchars($budgetSummary['budget_code']) ?></h5>
                            </div>
                            <div class="budget-summary-activity">
                                <span class="budget-summary-eyebrow">Activity</span>
                                <?= htmlspecialchars($budgetSummary['budget_activity'] ?: 'N/A') ?>
                            </div>
                        </div>
                        <div class="budget-summary-metrics">
                            <div class="budget-summary-metric">
                                <span class="budget-summary-metric-label">Approved</span>
                                <strong class="budget-summary-metric-value">PHP <?= number_format($approvedBudget, 2) ?></strong>
                            </div>
                            <div class="budget-summary-metric">
                                <span class="budget-summary-metric-label">Used/Pending</span>
                                <strong class="budget-summary-metric-value">PHP <?= number_format($totalBudgetExpenses, 2) ?></strong>
                            </div>
                            <div class="budget-summary-metric <?= $remainingBudget < 0 ? 'is-over-budget' : 'is-remaining' ?>">
                                <span class="budget-summary-metric-label">Remaining</span>
                                <strong class="budget-summary-metric-value">PHP <?= number_format($remainingBudget, 2) ?></strong>
                            </div>
                            <div class="budget-summary-metric <?= $remainingAfterPo !== null && $remainingAfterPo < 0 ? 'is-over-budget' : 'is-remaining' ?>">
                                <span class="budget-summary-metric-label">After This PO</span>
                                <strong class="budget-summary-metric-value">PHP <?= number_format((float)$remainingAfterPo, 2) ?></strong>
                            </div>
                        </div>
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
                <?php elseif ($budgetSummaryError !== ''): ?>
                    <div class="alert alert-warning"><?= htmlspecialchars($budgetSummaryError) ?></div>
                <?php endif; ?>
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h5 class="mb-3">Approvers</h5>
                        <?php foreach ($signatures as $signature): ?>
                            <div class="border-bottom py-2">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <div class="fw-semibold"><?= htmlspecialchars($signature['label']) ?></div>
                                        <div><?= htmlspecialchars($signature['full_name']) ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars($signature['position'] ?? '') ?></div>
                                    </div>
                                    <div class="text-end">
                                        <?php if (!empty($signature['checked_at'])): ?>
                                            <span class="badge bg-success">Approved</span>
                                            <div class="text-muted small"><?= date('M j, Y', strtotime($signature['checked_at'])) ?></div>
                                        <?php elseif ($nextSignature && (int)$nextSignature['signature_id'] === (int)$signature['signature_id']): ?>
                                            <span class="badge bg-warning text-dark">Next</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Waiting</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(document).on('click', '.po-action-btn', function() {
        const action = $(this).data('action');
        const formData = $('#poApprovalForm').serializeArray();
        formData.push({
            name: 'action',
            value: action
        });

        if (action === 'cancel' && !confirm('Cancel this PO approval request?')) {
            return;
        }

        $.ajax({
            url: 'data/submit_po_approval.php',
            type: 'POST',
            data: $.param(formData),
            dataType: 'json',
            success: function(res) {
                if (res.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success',
                        text: res.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', res.message || 'Unable to process PO approval.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Unable to process PO approval.', 'error');
            }
        });
    });
</script>

<?php include 'footer.php'; ?>
