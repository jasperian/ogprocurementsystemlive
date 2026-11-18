<?php
require '../init.php';
require_once __DIR__ . '/data/budget_access_helper.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!budgetAccessCanManage($_SESSION)) {
    http_response_code(403);
    die('Only Administrators or Accounting users can manage budget access.');
}

ensureBudgetUserAccessTable($pdo);

$sessionDealer = trim($_SESSION['dealer'] ?? '');
$canManageAllDealers = budgetAccessCanManageAllDealers($_SESSION);

if ($sessionDealer === '') {
    http_response_code(403);
    die('Your account has no assigned dealer.');
}

if ($canManageAllDealers) {
    $dealerStmt = $pdo->query("
        SELECT dealer FROM (
            SELECT dealer FROM users WHERE TRIM(COALESCE(dealer, '')) <> ''
            UNION
            SELECT budget_dealer AS dealer FROM budgets WHERE TRIM(COALESCE(budget_dealer, '')) <> ''
        ) dealers ORDER BY dealer
    ");
    $dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
    $dealer = trim($_GET['dealer'] ?? $sessionDealer);
    if (!in_array($dealer, $dealers, true)) {
        $dealer = in_array($sessionDealer, $dealers, true) ? $sessionDealer : (string)($dealers[0] ?? '');
    }
} else {
    $dealer = $sessionDealer;
    $dealers = [$sessionDealer];
}

$picStmt = $pdo->prepare("
    SELECT DISTINCT u.user_id, u.full_name, u.department
    FROM users u
    INNER JOIN department_pic_assignments dpa
        ON dpa.pic_user_id = u.user_id
       AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(u.department))
       AND UPPER(TRIM(dpa.dealer)) = UPPER(TRIM(u.dealer))
    WHERE u.is_active = 1
      AND UPPER(TRIM(u.dealer)) = UPPER(TRIM(:dealer))
    ORDER BY u.department, u.full_name
");
$picStmt->execute([':dealer' => $dealer]);
$pics = $picStmt->fetchAll(PDO::FETCH_ASSOC);

$budgetStmt = $pdo->prepare("
    SELECT budget_id, budget_code, budget_activity, budget_department
    FROM budgets
    WHERE UPPER(TRIM(budget_dealer)) = UPPER(TRIM(:dealer))
    ORDER BY budget_department, budget_code
");
$budgetStmt->execute([':dealer' => $dealer]);
$budgets = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

$grantStmt = $pdo->prepare("
    SELECT bua.access_id, bua.budget_id, bua.user_id, bua.created_at,
           u.full_name, u.department AS user_department,
           b.budget_code, b.budget_activity, b.budget_department,
           grantor.full_name AS granted_by_name
    FROM budget_user_access bua
    INNER JOIN users u ON u.user_id = bua.user_id
    INNER JOIN budgets b ON b.budget_id = bua.budget_id
    LEFT JOIN users grantor ON grantor.user_id = bua.granted_by
    WHERE UPPER(TRIM(b.budget_dealer)) = UPPER(TRIM(:dealer))
    ORDER BY u.department, u.full_name, b.budget_department, b.budget_code
");
$grantStmt->execute([':dealer' => $dealer]);
$grants = $grantStmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'navigation.php';
?>

<link href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/css/select2.min.css" rel="stylesheet">
<link href="https://unpkg.com/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/select2/4.0.13/js/select2.min.js"></script>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="page-header-title"><h2 class="mb-0">Budget Access Management</h2></div>
            </div>
        </div>

        <div class="card shadow mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="mb-1">Grant Cross-Department Budget Access</h5>
                    <small class="text-muted">Only active, currently assigned Department PICs are eligible.</small>
                </div>
                <?php if ($canManageAllDealers) { ?>
                    <select class="form-select" id="dealerFilter" style="max-width: 180px;">
                        <?php foreach ($dealers as $dealerOption) { ?>
                            <option value="<?= htmlspecialchars($dealerOption) ?>" <?= strcasecmp($dealerOption, $dealer) === 0 ? 'selected' : '' ?>>
                                <?= htmlspecialchars($dealerOption) ?>
                            </option>
                        <?php } ?>
                    </select>
                <?php } else { ?>
                    <div style="min-width: 180px;">
                        <label for="dealerDisplay" class="form-label small mb-1">Dealer</label>
                        <input type="text" class="form-control" id="dealerDisplay" value="<?= htmlspecialchars($dealer, ENT_QUOTES, 'UTF-8') ?>" readonly>
                    </div>
                <?php } ?>
            </div>
            <div class="card-body">
                <form id="grantBudgetAccessForm" class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label for="picUserId" class="form-label">Department PIC</label>
                        <select class="form-select" name="user_id" id="picUserId" required>
                            <option value="">Select PIC</option>
                            <?php foreach ($pics as $pic) { ?>
                                <option value="<?= (int)$pic['user_id'] ?>" data-department="<?= htmlspecialchars($pic['department'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($pic['department'] . ' — ' . $pic['full_name']) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-5">
                        <label for="budgetAccessId" class="form-label">External Budget Code</label>
                        <select class="form-select" name="budget_id" id="budgetAccessId" required disabled>
                            <option value="">Select PIC first</option>
                            <?php foreach ($budgets as $budget) { ?>
                                <option value="<?= (int)$budget['budget_id'] ?>" data-department="<?= htmlspecialchars($budget['budget_department'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($budget['budget_department'] . ' — ' . $budget['budget_code'] . ($budget['budget_activity'] ? ' — ' . $budget['budget_activity'] : '')) ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary" id="grantAccessBtn">Grant Access</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-header"><h5 class="mb-0">Existing Grants</h5></div>
            <div class="card-body table-responsive">
                <table class="table table-striped align-middle">
                    <thead><tr><th>PIC</th><th>Requester Department</th><th>Budget Owner</th><th>Budget Code</th><th>Granted By</th><th>Granted</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($grants)) { ?>
                        <tr><td colspan="7" class="text-center text-muted">No cross-department grants for this dealer.</td></tr>
                    <?php } ?>
                    <?php foreach ($grants as $grant) { ?>
                        <tr>
                            <td><?= htmlspecialchars($grant['full_name']) ?></td>
                            <td><?= htmlspecialchars($grant['user_department']) ?></td>
                            <td><?= htmlspecialchars($grant['budget_department']) ?></td>
                            <td><?= htmlspecialchars($grant['budget_code'] . ($grant['budget_activity'] ? ' — ' . $grant['budget_activity'] : '')) ?></td>
                            <td><?= htmlspecialchars($grant['granted_by_name'] ?: 'Unknown') ?></td>
                            <td><?= htmlspecialchars($grant['created_at']) ?></td>
                            <td class="text-end"><button type="button" class="btn btn-sm btn-outline-danger revoke-access" data-access-id="<?= (int)$grant['access_id'] ?>">Revoke</button></td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
$(function() {
    const allBudgetOptions = $('#budgetAccessId option[value!=""]').clone();

    $('#picUserId').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search Department PIC',
        allowClear: true
    });

    $('#budgetAccessId').select2({
        theme: 'bootstrap-5',
        width: '100%',
        placeholder: 'Search external budget code',
        allowClear: true
    });

    $('#picUserId').on('change', function() {
        const department = String($(this).find(':selected').data('department') || '').trim().toUpperCase();
        const $budget = $('#budgetAccessId');
        $budget.empty().append('<option value="">Select external budget code</option>');
        allBudgetOptions.each(function() {
            if (String($(this).data('department') || '').trim().toUpperCase() !== department) {
                $budget.append($(this).clone());
            }
        });
        $budget.prop('disabled', !department);
        $budget.val('').trigger('change.select2');
    });

    $('#dealerFilter').on('change', function() {
        window.location.href = 'manage_budget_access?dealer=' + encodeURIComponent($(this).val());
    });

    $('#grantBudgetAccessForm').on('submit', function(e) {
        e.preventDefault();
        $.ajax({
            url: 'data/save_budget_user_access.php', type: 'POST', dataType: 'json',
            data: $(this).serialize() + '&action=grant',
            success: function(res) {
                if (res.status === 'success') window.location.reload();
                else Swal.fire('Error', res.message || 'Unable to grant access.', 'error');
            },
            error: function(xhr) { Swal.fire('Error', xhr.responseJSON?.message || 'Unable to grant access.', 'error'); }
        });
    });

    $('.revoke-access').on('click', function() {
        const accessId = $(this).data('access-id');
        Swal.fire({ title: 'Revoke access?', icon: 'warning', showCancelButton: true, confirmButtonText: 'Revoke' })
            .then(function(result) {
                if (!result.isConfirmed) return;
                $.ajax({
                    url: 'data/save_budget_user_access.php', type: 'POST', dataType: 'json',
                    data: { action: 'revoke', access_id: accessId },
                    success: function(res) {
                        if (res.status === 'success') window.location.reload();
                        else Swal.fire('Error', res.message || 'Unable to revoke access.', 'error');
                    },
                    error: function(xhr) { Swal.fire('Error', xhr.responseJSON?.message || 'Unable to revoke access.', 'error'); }
                });
            });
    });
});
</script>

<?php include 'footer.php'; ?>
