<?php

ini_set('display_errors', 1);
error_reporting(E_ALL);
require '../init.php';
require_once __DIR__ . '/data/department_pic_assignments_helper.php';
require_once __DIR__ . '/data/liquidation_accounting_assignments_helper.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    die('Only Administrator can manage Department PIC assignments.');
}

ensureDepartmentPicAssignmentsTable($pdo);
ensureLiquidationAccountingAssignmentsTable($pdo);

$sessionDealer = trim($_SESSION['dealer'] ?? 'TNE');
$sessionDealer = $sessionDealer !== '' ? $sessionDealer : 'TNE';
$requestedDealer = trim($_GET['dealer'] ?? $sessionDealer);
$dealer = $requestedDealer !== '' ? $requestedDealer : $sessionDealer;

$dealerStmt = $pdo->query("
    SELECT DISTINCT dealer
    FROM users
    WHERE dealer IS NOT NULL
      AND TRIM(dealer) <> ''
    ORDER BY dealer ASC
");
$dealers = $dealerStmt->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($dealer, $dealers, true)) {
    array_unshift($dealers, $dealer);
}

$departmentStmt = $pdo->prepare("
    SELECT department
    FROM (
        SELECT department
        FROM users
        WHERE dealer = :dealer_users
          AND TRIM(COALESCE(department, '')) <> ''
        UNION
        SELECT budget_department AS department
        FROM budgets
        WHERE budget_dealer = :dealer_budgets
          AND TRIM(COALESCE(budget_department, '')) <> ''
    ) departments
    ORDER BY department ASC
");
$departmentStmt->execute([
    ':dealer_users' => $dealer,
    ':dealer_budgets' => $dealer,
]);
$departments = $departmentStmt->fetchAll(PDO::FETCH_COLUMN);

$assignmentStmt = $pdo->prepare("
    SELECT assignment_id, department, pic_user_id, department_approver_user_id, final_approver_user_id, series_prefix
    FROM department_pic_assignments
    WHERE dealer = :dealer
    ORDER BY department ASC, assignment_id ASC
");
$assignmentStmt->execute([':dealer' => $dealer]);
$assignmentsByDepartment = [];
foreach ($assignmentStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $assignmentsByDepartment[strtoupper(trim($row['department']))][] = $row;
}
$routeCount = array_reduce($assignmentsByDepartment, function (int $count, array $routes): int {
    return $count + count($routes);
}, 0);

$userStmt = $pdo->prepare("
    SELECT user_id, full_name, department, position, dealer, is_depthead
    FROM users
    WHERE is_active = 1
      AND dealer = :dealer
      AND TRIM(COALESCE(department, '')) <> ''
    ORDER BY department ASC, full_name ASC
");
$userStmt->execute([':dealer' => $dealer]);
$usersByDepartment = [];
$dealerManagers = [];
foreach ($userStmt->fetchAll(PDO::FETCH_ASSOC) as $userRow) {
    $departmentKey = strtoupper(trim($userRow['department'] ?? ''));
    $usersByDepartment[$departmentKey][] = $userRow;
    if ((int)($userRow['is_depthead'] ?? 0) === 1 || ($userRow['position'] ?? '') === 'Manager') {
        $dealerManagers[] = $userRow;
    }
}

$finalApproverStmt = $pdo->prepare("
    SELECT user_id, full_name, department, position, dealer
    FROM users
    WHERE is_active = 1
      AND (dealer = :dealer OR dealer = 'OG')
      AND (
          position = 'General Manager'
          OR position LIKE '%Branch Manager%'
      )
    ORDER BY CASE WHEN dealer = :dealer_order THEN 0 ELSE 1 END, position ASC, full_name ASC
");
$finalApproverStmt->execute([
    ':dealer' => $dealer,
    ':dealer_order' => $dealer,
]);
$finalApproverUsers = $finalApproverStmt->fetchAll(PDO::FETCH_ASSOC);

$accountingUserStmt = $pdo->prepare("
    SELECT user_id, full_name, department, position, dealer
    FROM users
    WHERE is_active = 1
      AND dealer = :dealer
      AND department IN ('ACCOUNTING', 'FINANCE')
      AND position <> 'viewer'
    ORDER BY department ASC, position ASC, full_name ASC
");
$accountingUserStmt->execute([':dealer' => $dealer]);
$accountingUsers = $accountingUserStmt->fetchAll(PDO::FETCH_ASSOC);
$sapEncoderUsers = array_values(array_filter($accountingUsers, function (array $userRow): bool {
    return strtoupper(trim((string)($userRow['department'] ?? ''))) === 'ACCOUNTING';
}));
$assignedSapEncoders = getLiquidationAccountingAssignedUsers($pdo, $dealer, 'SAP Encoder');
$assignedAccountingApprovers = getLiquidationAccountingAssignedUsers($pdo, $dealer, 'Accounting Approver');
$assignedSapEncoderIds = array_map('strval', array_column($assignedSapEncoders, 'user_id'));
$assignedAccountingApproverIds = array_map('strval', array_column($assignedAccountingApprovers, 'user_id'));

$renderUserOptions = function (array $users, string $selectedUserId, string $emptyLabel): string {
    $html = '<option value="">' . htmlspecialchars($emptyLabel) . '</option>';
    foreach ($users as $userRow) {
        $userId = (string)$userRow['user_id'];
        $userLabel = formatDepartmentPicUserLabel($userRow);
        $html .= '<option value="' . htmlspecialchars($userId) . '"' . ($userId === $selectedUserId ? ' selected' : '') . '>' . htmlspecialchars($userLabel) . '</option>';
    }
    return $html;
};

$renderMultiUserOptions = function (array $users, array $selectedUserIds): string {
    $selectedLookup = array_fill_keys(array_map('strval', $selectedUserIds), true);
    $html = '';
    foreach ($users as $userRow) {
        $userId = (string)$userRow['user_id'];
        $userLabel = formatDepartmentPicUserLabel($userRow);
        $html .= '<option value="' . htmlspecialchars($userId) . '"' . (isset($selectedLookup[$userId]) ? ' selected' : '') . '>' . htmlspecialchars($userLabel) . '</option>';
    }
    return $html;
};

include 'header.php';
include 'navigation.php';
?>

<style>
    .department-pic-page .page-header-title h2 {
        color: #1f2937;
        font-weight: 700;
    }

    .department-pic-subtitle {
        color: #667085;
        font-size: 14px;
        margin: 8px 0 0;
    }

    .department-pic-card {
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(16, 24, 40, 0.06);
        overflow: hidden;
    }

    .department-pic-card .card-header {
        background: #ffffff;
        border-bottom: 1px solid #eef1f5;
        padding: 18px 20px;
    }

    .department-pic-toolbar {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: flex-end;
    }

    .department-pic-filter {
        min-width: 170px;
    }

    .department-pic-summary {
        align-items: center;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .summary-chip {
        align-items: center;
        background: #f8fafc;
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        color: #475467;
        display: inline-flex;
        font-size: 13px;
        font-weight: 600;
        gap: 7px;
        padding: 7px 10px;
    }

    .summary-chip i {
        color: #1677ff;
    }

    .department-pic-table {
        margin-bottom: 0;
    }

    .department-pic-table thead th {
        background: #f8fafc;
        border-bottom: 1px solid #e6e9ef;
        color: #667085;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.04em;
        padding: 12px 14px;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .department-pic-table tbody td {
        border-color: #eef1f5;
        padding: 14px;
        vertical-align: middle;
    }

    .department-pic-table tbody tr:hover td {
        background: #fbfdff;
    }

    .department-name-cell {
        min-width: 190px;
    }

    .department-name-text {
        color: #1f2937;
        display: block;
        font-weight: 700;
    }

    .department-meta {
        color: #667085;
        display: block;
        font-size: 12px;
        margin-top: 3px;
    }

    .route-flow {
        color: #1f2937;
        font-weight: 700;
        min-width: 245px;
    }

    .route-flow-meta {
        color: #667085;
        display: block;
        font-size: 12px;
        line-height: 1.4;
        margin-top: 4px;
    }

    .department-pic-table .form-select,
    .department-pic-table .select2-container--default .select2-selection--single {
        border-color: #d0d5dd;
        border-radius: 8px;
        min-height: 40px;
    }

    .department-pic-table .select2-container--default .select2-selection--single .select2-selection__rendered {
        color: #344054;
        line-height: 38px;
    }

    .department-pic-table .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 38px;
    }

    .department-pic-table .form-select:focus,
    .department-pic-table .select2-container--focus .select2-selection--single {
        border-color: #1677ff;
        box-shadow: 0 0 0 3px rgba(22, 119, 255, 0.12);
    }

    .route-action-group {
        display: flex;
        justify-content: center;
    }

    .route-action-btn {
        align-items: center;
        background: #ffffff;
        border: 1px solid #e6e9ef;
        border-radius: 8px;
        color: #667085;
        display: inline-flex;
        height: 36px;
        justify-content: center;
        transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease;
        width: 36px;
    }

    .route-action-btn:hover,
    .route-action-btn:focus {
        background: #fef2f2;
        border-color: #fecaca;
        color: #dc2626;
    }

    .empty-route-state {
        color: #667085;
        padding: 34px 16px !important;
    }

    @media (max-width: 991.98px) {
        .department-pic-toolbar {
            justify-content: flex-start;
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content department-pic-page">
        <div class="page-header">
            <div class="page-block">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                    <div class="page-header-title">
                        <h2 class="mb-0">Department PIC Assignments</h2>
                        <p class="department-pic-subtitle">Maintain encoder, department head, and final approver routes per dealer.</p>
                    </div>
                    <div class="department-pic-toolbar">
                        <select class="form-select department-pic-filter" id="dealerFilter" aria-label="Dealer">
                            <?php foreach ($dealers as $dealerOption) { ?>
                                <option value="<?= htmlspecialchars($dealerOption) ?>" <?= $dealerOption === $dealer ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($dealerOption) ?>
                                </option>
                            <?php } ?>
                        </select>
                        <button type="button" class="btn btn-primary d-inline-flex align-items-center gap-2" id="saveDepartmentPicsBtn">
                            <i class="ti ti-device-floppy f-18"></i> Save Routes
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-12 mb-3">
                <div class="card department-pic-card">
                    <div class="card-header">
                        <h5 class="mb-1">Accounting Assignments</h5>
                        <small class="text-muted">Choose who can encode SAP documents and complete standalone requests for <?= htmlspecialchars($dealer) ?>.</small>
                    </div>
                    <div class="card-body">
                        <form id="accountingAssignmentForm">
                            <div class="row g-3">
                                <div class="col-lg-6">
                                    <label for="sapEncoderUserId" class="form-label">SAP Document Encoders</label>
                                    <select class="form-select accounting-assignment-select" id="sapEncoderUserId" name="accounting[sap_encoder_user_ids][]" multiple data-placeholder="Select SAP encoders">
                                        <?= $renderMultiUserOptions($sapEncoderUsers, $assignedSapEncoderIds) ?>
                                    </select>
                                    <small class="text-muted">All selected users can enter SAP Document Numbers. Leave blank to use current Accounting staff access.</small>
                                </div>
                                <div class="col-lg-6">
                                    <label for="accountingApproverUserId" class="form-label">Accounting Approvers / Completers</label>
                                    <select class="form-select accounting-assignment-select" id="accountingApproverUserId" name="accounting[accounting_approver_user_ids][]" multiple data-placeholder="Select Accounting approvers">
                                        <?= $renderMultiUserOptions($accountingUsers, $assignedAccountingApproverIds) ?>
                                    </select>
                                    <small class="text-muted">All selected users can release CA, complete RFP, and verify Liquidations. Leave blank for role-based access.</small>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-12">
                <div class="card department-pic-card">
                    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <div>
                            <h5 class="mb-1">Approval Routes</h5>
                            <small class="text-muted">Cash Advance, Liquidation, and Request for Payment routing.</small>
                        </div>
                        <div class="department-pic-summary">
                            <span class="summary-chip"><i class="ti ti-building f-16"></i><?= count($departments) ?> Departments</span>
                            <span class="summary-chip"><i class="ti ti-route f-16"></i><?= $routeCount ?> Routes</span>
                            <span class="summary-chip"><i class="ti ti-building-store f-16"></i><?= htmlspecialchars($dealer) ?></span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <form id="departmentPicForm">
                            <input type="hidden" name="dealer" value="<?= htmlspecialchars($dealer) ?>">
                            <div class="table-responsive">
                                <table class="table department-pic-table align-middle">
                                    <thead>
                                        <tr>
                                            <th>Department</th>
                                            <th>Department PIC</th>
                                            <th>Department Manager Approver</th>
                                            <th>Final Approver</th>
                                            <th>Series Prefix</th>
                                            <th>Approval Flow</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($departments)) { ?>
                                            <tr>
                                                <td colspan="7" class="text-center empty-route-state">No departments found for this dealer.</td>
                                            </tr>
                                        <?php } ?>
                                        <?php foreach ($departments as $department) {
                                            $departmentKey = strtoupper(trim($department));
                                            $departmentUsers = $usersByDepartment[$departmentKey] ?? [];
                                            $departmentRoutes = $assignmentsByDepartment[$departmentKey] ?? [[]];
                                            $defaultApprovers = getDepartmentLiquidationDefaultApproverLabels($pdo, $dealer, $department);
                                            $rowspan = count($departmentRoutes);
                                            $rowIndex = 0;
                                        ?>
                                            <?php foreach ($departmentRoutes as $routeIndex => $route) {
                                                $assignedUserId = (string)($route['pic_user_id'] ?? '');
                                                $assignedDepartmentApproverId = (string)($route['department_approver_user_id'] ?? '');
                                                $assignedFinalApproverId = (string)($route['final_approver_user_id'] ?? '');
                                                $assignedSeriesPrefix = (string)($route['series_prefix'] ?? $dealer);
                                            ?>
                                                <tr class="department-pic-route-row" data-department="<?= htmlspecialchars($department) ?>">
                                                    <?php if ($rowIndex === 0) { ?>
                                                        <td rowspan="<?= (int)$rowspan ?>" class="department-cell department-name-cell">
                                                            <span class="department-name-text"><?= htmlspecialchars($department) ?></span>
                                                            <span class="department-meta"><?= htmlspecialchars($dealer) ?></span>
                                                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 mt-3 add-route-btn" data-department="<?= htmlspecialchars($department) ?>">
                                                                <i class="ti ti-plus f-16"></i> Add PIC Route
                                                            </button>
                                                        </td>
                                                    <?php } ?>
                                                    <td>
                                                        <select class="form-select department-pic-select" name="assignments[<?= htmlspecialchars($department) ?>][<?= (int)$routeIndex ?>][pic_user_id]">
                                                            <?= $renderUserOptions($departmentUsers, $assignedUserId, 'No Department PIC assigned') ?>
                                                        </select>
                                                        <?php if (empty($departmentUsers)) { ?>
                                                            <small class="text-muted">No active users found in this department.</small>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <select class="form-select department-approver-select" name="assignments[<?= htmlspecialchars($department) ?>][<?= (int)$routeIndex ?>][department_approver_user_id]">
                                                            <?= $renderUserOptions($dealerManagers, $assignedDepartmentApproverId, 'Select Department Manager') ?>
                                                        </select>
                                                        <?php if (empty($dealerManagers)) { ?>
                                                            <small class="text-muted">No active manager found for this dealer.</small>
                                                        <?php } ?>
                                                    </td>
                                                    <td>
                                                        <select class="form-select final-approver-select" name="assignments[<?= htmlspecialchars($department) ?>][<?= (int)$routeIndex ?>][final_approver_user_id]">
                                                            <?= $renderUserOptions($finalApproverUsers, $assignedFinalApproverId, 'Select final approver') ?>
                                                        </select>
                                                    </td>
                                                    <td>
                                                        <input type="text" class="form-control series-prefix-input" name="assignments[<?= htmlspecialchars($department) ?>][<?= (int)$routeIndex ?>][series_prefix]" value="<?= htmlspecialchars($assignedSeriesPrefix) ?>" maxlength="20" placeholder="<?= htmlspecialchars($dealer) ?>">
                                                    </td>
                                                    <td>
                                                        <div class="route-flow">PIC -> Dept. Head -> Conditional Final -> Assigned Accounting</div>
                                                        <span class="route-flow-meta">Series: <?= htmlspecialchars($assignedSeriesPrefix ?: $dealer) ?>-CA / <?= htmlspecialchars($assignedSeriesPrefix ?: $dealer) ?>-LIQ / <?= htmlspecialchars($assignedSeriesPrefix ?: $dealer) ?>-RFP</span>
                                                    </td>
                                                    <td>
                                                        <div class="route-action-group">
                                                            <button type="button" class="route-action-btn remove-route-btn" title="Remove route" aria-label="Remove route">
                                                            <i class="ti ti-trash f-16"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php $rowIndex++; } ?>
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
        const departmentPicOptions = <?= json_encode(array_map(function ($users) use ($renderUserOptions) {
            return $renderUserOptions($users, '', 'No Department PIC assigned');
        }, $usersByDepartment)) ?>;
        const departmentApproverOptions = <?= json_encode($renderUserOptions($dealerManagers, '', 'Select Department Manager')) ?>;
        const finalApproverOptions = <?= json_encode($renderUserOptions($finalApproverUsers, '', 'Select final approver')) ?>;

        function initSelect2($scope) {
            if ($.fn.select2) {
                $scope.find('.department-pic-select, .department-approver-select, .final-approver-select').select2({
                    width: '100%'
                });
            }
        }

        function escapeHtml(value) {
            return $('<div>').text(value == null ? '' : String(value)).html();
        }

        function departmentRows(department) {
            return $('.department-pic-route-row').filter(function() {
                return String($(this).data('department')) === String(department);
            });
        }

        function reindexDepartmentRows(department) {
            const $rows = departmentRows(department);
            const rowCount = $rows.length;
            $rows.each(function(index) {
                const $row = $(this);
                $row.find('select').each(function() {
                    let field = 'final_approver_user_id';
                    if ($(this).hasClass('department-pic-select')) {
                        field = 'pic_user_id';
                    } else if ($(this).hasClass('department-approver-select')) {
                        field = 'department_approver_user_id';
                    }
                    $(this).attr('name', `assignments[${department}][${index}][${field}]`);
                });
                $row.find('.series-prefix-input').attr('name', `assignments[${department}][${index}][series_prefix]`);

                const $departmentCell = $row.find('.department-cell');
                if (index === 0) {
                    if ($departmentCell.length === 0) {
                        const departmentHtml = `<td rowspan="${rowCount}" class="department-cell department-name-cell">
                            <span class="department-name-text">${escapeHtml(department)}</span>
                            <span class="department-meta"><?= htmlspecialchars($dealer) ?></span>
                            <button type="button" class="btn btn-sm btn-outline-primary d-inline-flex align-items-center gap-1 mt-3 add-route-btn" data-department="${escapeHtml(department)}">
                                <i class="ti ti-plus f-16"></i> Add PIC Route
                            </button>
                        </td>`;
                        $row.prepend(departmentHtml);
                    } else {
                        $departmentCell.attr('rowspan', rowCount);
                    }
                } else {
                    $departmentCell.remove();
                }
            });
        }

        if ($.fn.select2) {
            $('.department-pic-select, .department-approver-select, .final-approver-select').select2({
                width: '100%'
            });
            $('.accounting-assignment-select').select2({
                width: '100%',
                closeOnSelect: false
            });
        }

        $(document).on('click', '.add-route-btn', function() {
            const department = $(this).data('department') || '';
            const departmentKey = department.toString().trim().toUpperCase();
            const picOptions = departmentPicOptions[departmentKey] || '<option value="">No Department PIC assigned</option>';
            const departmentHeadOptions = departmentApproverOptions || '<option value="">Select Department Manager</option>';
            const $lastRow = departmentRows(department).last();
            const routeHtml = `<tr class="department-pic-route-row" data-department="${escapeHtml(department)}">
                <td><select class="form-select department-pic-select">${picOptions}</select></td>
                <td><select class="form-select department-approver-select">${departmentHeadOptions}</select></td>
                <td><select class="form-select final-approver-select">${finalApproverOptions}</select></td>
                <td><input type="text" class="form-control series-prefix-input" maxlength="20" placeholder="<?= htmlspecialchars($dealer) ?>" value="<?= htmlspecialchars($dealer) ?>"></td>
                <td>
                    <div class="route-flow">PIC -> Dept. Head -> Conditional Final -> Assigned Accounting</div>
                    <span class="route-flow-meta">Set NES for TNESC numbering.</span>
                </td>
                <td>
                    <div class="route-action-group">
                        <button type="button" class="route-action-btn remove-route-btn" title="Remove route" aria-label="Remove route">
                            <i class="ti ti-trash f-16"></i>
                        </button>
                    </div>
                </td>
            </tr>`;
            const $newRow = $(routeHtml);
            $lastRow.after($newRow);
            reindexDepartmentRows(department);
            initSelect2($newRow);
        });

        $(document).on('click', '.remove-route-btn', function() {
            const $row = $(this).closest('.department-pic-route-row');
            const department = $row.data('department') || '';
            const $rows = departmentRows(department);
            if ($rows.length <= 1) {
                $row.find('select').val('').trigger('change');
                return;
            }

            $row.remove();
            reindexDepartmentRows(department);
        });

        $(document).on('input', '.series-prefix-input', function() {
            this.value = this.value.replace(/[^A-Za-z0-9]/g, '').toUpperCase();
        });

        $('#saveDepartmentPicsBtn').on('click', function() {
            $.ajax({
                url: 'data/save_department_pic_assignments.php',
                type: 'POST',
                data: $('#departmentPicForm, #accountingAssignmentForm').serialize(),
                dataType: 'json',
                success: function(response) {
                    if (response.status === 'success') {
                        Swal.fire('Success', response.message, 'success');
                    } else {
                        Swal.fire('Error', response.message || 'Failed to save Department PIC assignments.', 'error');
                    }
                },
                error: function() {
                    Swal.fire('Error', 'Failed to save Department PIC assignments.', 'error');
                }
            });
        });

        $('#dealerFilter').on('change', function() {
            window.location.href = 'manage_department_pics?dealer=' + encodeURIComponent($(this).val());
        });
    });
</script>

<?php include 'footer.php'; ?>
