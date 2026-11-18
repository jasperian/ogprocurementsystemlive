<?php
require '../init.php';
require_once __DIR__ . '/data/cash_advance_approval_assignments_helper.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    die('Only Administrator can manage Cash Advance approvers.');
}

$sessionDealer = trim($_SESSION['dealer'] ?? 'OG');
$sessionDealer = $sessionDealer !== '' ? $sessionDealer : 'OG';
$requestedDealer = trim($_GET['dealer'] ?? $sessionDealer);
$dealer = $requestedDealer !== '' ? $requestedDealer : 'OG';

$statuses = getCashAdvanceApprovalStatuses();
$assignments = getCashAdvanceAssignedApprovers($pdo, $dealer);

$userWhere = ["is_active = 1"];
$userParams = [];
if (strtoupper($dealer) !== 'OG') {
    $userWhere[] = "(dealer = :dealer OR dealer = 'OG')";
    $userParams[':dealer'] = $dealer;
}

$userStmt = $pdo->prepare("
    SELECT user_id, full_name, department, position, dealer
    FROM users
    WHERE " . implode(' AND ', $userWhere) . "
    ORDER BY full_name ASC
");
$userStmt->execute($userParams);
$users = $userStmt->fetchAll(PDO::FETCH_ASSOC);

$dealerStmt = $pdo->query("
    SELECT DISTINCT dealer
    FROM users
    WHERE dealer IS NOT NULL
      AND TRIM(dealer) <> ''
    ORDER BY dealer ASC
");
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array('OG', $dealers, true)) {
    array_unshift($dealers, 'OG');
}

include 'header.php';
include 'navigation.php';
?>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="page-header-title">
                            <h2 class="mb-0">Cash Advance Approvers</h2>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card shadow">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <h5 class="mb-1">Approval Assignments</h5>
                            <small class="text-muted">Dealer: <?= htmlspecialchars($dealer) ?></small>
                        </div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select" id="dealerFilter" style="min-width: 160px;">
                                <?php foreach ($dealers as $dealerOption) { ?>
                                    <option value="<?= htmlspecialchars($dealerOption) ?>" <?= $dealerOption === $dealer ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dealerOption) ?>
                                    </option>
                                <?php } ?>
                            </select>
                            <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="saveCaApproversBtn">
                                <i class="ti ti-device-floppy f-18"></i> Save
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <form id="caApproverForm">
                            <input type="hidden" name="dealer" value="<?= htmlspecialchars($dealer) ?>">
                            <div class="table-responsive">
                                <table class="table table-striped align-middle">
                                    <thead>
                                        <tr>
                                            <th>Status / Step</th>
                                            <th>Assigned Approver</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statuses as $status => $label) {
                                            $assignedUserId = $assignments[$status]['user_id'] ?? '';
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold"><?= htmlspecialchars($label) ?></div>
                                                    <div class="text-muted small"><?= htmlspecialchars($status) ?></div>
                                                </td>
                                                <td>
                                                    <select class="form-select ca-approver-select" name="assignments[<?= htmlspecialchars($status) ?>]">
                                                        <option value="">Use default role-based approver</option>
                                                        <?php foreach ($users as $userRow) {
                                                            $userId = (string)$userRow['user_id'];
                                                            $userLabel = trim(($userRow['full_name'] ?? '') . ' - ' . ($userRow['department'] ?? '') . ' / ' . ($userRow['position'] ?? '') . ' / ' . ($userRow['dealer'] ?? ''));
                                                        ?>
                                                            <option value="<?= htmlspecialchars($userId) ?>" <?= $userId === (string)$assignedUserId ? 'selected' : '' ?>>
                                                                <?= htmlspecialchars($userLabel) ?>
                                                            </option>
                                                        <?php } ?>
                                                    </select>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                    </tbody>
                                </table>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $(function() {
        if ($.fn.select2) {
            $('.ca-approver-select').select2({
                width: '100%'
            });
        }

        $('#saveCaApproversBtn').on('click', function() {
            $.ajax({
                url: 'data/save_ca_approval_assignments.php',
                type: 'POST',
                data: $('#caApproverForm').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Success', response.message, 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to save approvers.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to save approvers.', 'error');
                }
            });
        });

        $('#dealerFilter').on('change', function() {
            window.location.href = 'manage_ca_approvers?dealer=' + encodeURIComponent($(this).val());
        });
    });
</script>

<?php include 'footer.php'; ?>
