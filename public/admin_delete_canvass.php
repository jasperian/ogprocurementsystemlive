<?php
require '../init.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Administrator access is required.');
}

if (empty($_SESSION['delete_canvass_csrf'])) {
    $_SESSION['delete_canvass_csrf'] = bin2hex(random_bytes(32));
}

$stmt = $pdo->query("SELECT
        csb.pr_number,
        GROUP_CONCAT(csb.batch_id ORDER BY csb.batch_id SEPARATOR ', ') AS batch_ids,
        COUNT(DISTINCT csb.batch_id) AS batch_count,
        MIN(csb.created_at) AS created_at,
        MAX(csb.created_by) AS created_by,
        MAX(pr.pr_status) AS pr_status,
        MAX(pr.pr_department) AS pr_department,
        MAX(pr.pr_dealer) AS pr_dealer,
        (SELECT COUNT(*)
         FROM canvass_items ci
         INNER JOIN canvass_sheet_batches csb_items ON csb_items.batch_id = ci.batch_id
         WHERE csb_items.pr_number = csb.pr_number) AS item_count,
        (SELECT COUNT(DISTINCT ci.supplier_id)
         FROM canvass_items ci
         INNER JOIN canvass_sheet_batches csb_suppliers ON csb_suppliers.batch_id = ci.batch_id
         WHERE csb_suppliers.pr_number = csb.pr_number) AS supplier_count,
        (SELECT COUNT(*) FROM purchase_order po WHERE po.pr_number = csb.pr_number) AS po_count,
        (SELECT COUNT(*) FROM cash_advance ca
         WHERE ca.purchase_requisition_number = csb.pr_number
            OR ca.pr_number = csb.pr_number) AS cash_advance_count
    FROM canvass_sheet_batches csb
    LEFT JOIN purchase_requisition pr ON pr.pr_number = csb.pr_number
    GROUP BY csb.pr_number
    ORDER BY MIN(csb.created_at) DESC");
$canvasses = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'navigation.php';
?>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="page-header-title"><h2 class="mb-0">Delete Canvass Sheet</h2></div>
            </div>
        </div>

        <div class="alert alert-warning d-flex align-items-start" role="alert">
            <i class="ti ti-alert-triangle f-22 me-2 mt-1"></i>
            <div>
                <strong>Administrator-only permanent action.</strong>
                Deleting a canvass removes its suppliers, quotations, approvals, comments, history, drafts, and related notifications. The purchase requisition returns to <strong>For Canvass</strong>.
            </div>
        </div>

        <div class="card shadow-sm table-card">
            <div class="card-body mt-3">
                <div class="table-responsive">
                    <table class="table table-striped align-middle" id="canvassDeleteTable" style="width:100%">
                        <thead>
                            <tr>
                                <th>PR Number</th>
                                <th>Status</th>
                                <th>Department / Dealer</th>
                                <th>Canvass Contents</th>
                                <th>Created</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($canvasses as $canvass): ?>
                                <?php $hasDownstreamRecord = (int)$canvass['po_count'] > 0 || (int)$canvass['cash_advance_count'] > 0; ?>
                                <tr>
                                    <td>
                                        <a href="canvass_view?pr_number=<?= urlencode($canvass['pr_number']) ?>" class="fw-semibold">
                                            <?= htmlspecialchars($canvass['pr_number']) ?>
                                        </a>
                                        <?php if ((int)$canvass['batch_count'] > 1): ?>
                                            <small class="text-danger d-block"><?= (int)$canvass['batch_count'] ?> linked batches</small>
                                        <?php endif; ?>
                                    </td>
                                    <td><span class="badge bg-light-secondary text-secondary"><?= htmlspecialchars($canvass['pr_status'] ?? 'Unknown') ?></span></td>
                                    <td>
                                        <?= htmlspecialchars($canvass['pr_department'] ?? '-') ?>
                                        <small class="text-muted d-block"><?= htmlspecialchars($canvass['pr_dealer'] ?? '-') ?></small>
                                    </td>
                                    <td>
                                        <?= (int)$canvass['supplier_count'] ?> supplier(s), <?= (int)$canvass['item_count'] ?> item row(s)
                                        <?php if ($hasDownstreamRecord): ?>
                                            <small class="text-danger d-block">
                                                <?= (int)$canvass['po_count'] ?> PO, <?= (int)$canvass['cash_advance_count'] ?> cash advance
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $canvass['created_at'] ? date('M d, Y g:i A', strtotime($canvass['created_at'])) : '-' ?>
                                        <small class="text-muted d-block">by <?= htmlspecialchars($canvass['created_by'] ?? '-') ?></small>
                                    </td>
                                    <td class="text-center">
                                        <button type="button"
                                            class="btn btn-sm btn-outline-danger delete-canvass-btn"
                                            data-pr-number="<?= htmlspecialchars($canvass['pr_number'], ENT_QUOTES) ?>"
                                            <?= $hasDownstreamRecord ? 'disabled' : '' ?>
                                            title="<?= $hasDownstreamRecord ? 'Delete the existing PO or cash advance through its proper workflow first.' : 'Permanently delete this canvass sheet' ?>">
                                            <i class="ti ti-trash me-1"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    if ($.fn.DataTable) {
        $('#canvassDeleteTable').DataTable({
            responsive: true,
            order: [[4, 'desc']],
            pageLength: 25
        });
    }

    $(document).on('click', '.delete-canvass-btn', function () {
        const button = this;
        const prNumber = button.dataset.prNumber;

        Swal.fire({
            icon: 'warning',
            title: 'Delete canvass sheet?',
            html: `This permanently removes all canvass records for <strong>${escapeHtml(prNumber)}</strong> and resets the PR to <strong>For Canvass</strong>.<br><br>Type the PR number to confirm.`,
            input: 'text',
            inputPlaceholder: prNumber,
            showCancelButton: true,
            confirmButtonText: 'Delete permanently',
            confirmButtonColor: '#dc3545',
            showLoaderOnConfirm: true,
            inputValidator: value => value.trim() === prNumber ? undefined : 'The PR number does not match.',
            preConfirm: () => fetch('data/delete_canvass_sheet.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    pr_number: prNumber,
                    csrf_token: '<?= htmlspecialchars($_SESSION['delete_canvass_csrf'], ENT_QUOTES) ?>'
                })
            }).then(async response => {
                const result = await response.json();
                if (!response.ok || !result.success) throw new Error(result.message || 'Unable to delete the canvass sheet.');
                return result;
            }).catch(error => Swal.showValidationMessage(error.message))
        }).then(result => {
            if (!result.isConfirmed) return;
            Swal.fire({icon: 'success', title: 'Canvass deleted', text: result.value.message})
                .then(() => window.location.reload());
        });
    });

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }
});
</script>

<?php include 'footer.php'; ?>
