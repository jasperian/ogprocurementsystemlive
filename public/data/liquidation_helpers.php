<?php

require_once __DIR__ . '/budget_remaining_helper.php';
require_once __DIR__ . '/liquidation_accounting_assignments_helper.php';

const LIQUIDATION_CURRENT_WORKFLOW_VERSION = 2;
const LIQUIDATION_CA_MAX_AMOUNT = 10000.00;

function liquidation_is_department_head(): bool
{
    if (!isset($_SESSION['is_depthead']) && isset($_SESSION['user_id'])) {
        global $pdo;
        if ($pdo instanceof PDO) {
            $stmt = $pdo->prepare('SELECT is_depthead FROM users WHERE user_id = ?');
            $stmt->execute([$_SESSION['user_id']]);
            $_SESSION['is_depthead'] = (int)$stmt->fetchColumn();
        }
    }

    return (int)($_SESSION['is_depthead'] ?? 0) === 1
        || ($_SESSION['position'] ?? '') === 'Manager';
}

function liquidation_is_department_pic(): bool
{
    global $pdo;

    if ($pdo instanceof PDO && isset($_SESSION['user_id'])) {
        try {
            $tableStmt = $pdo->query("SHOW TABLES LIKE 'department_pic_assignments'");
            if ($tableStmt && $tableStmt->fetch(PDO::FETCH_NUM)) {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*)
                    FROM department_pic_assignments
                    WHERE pic_user_id = :user_id
                      AND UPPER(TRIM(department)) = UPPER(TRIM(:department))
                      AND dealer = :dealer
                ");
                $stmt->execute([
                    ':user_id' => (int)$_SESSION['user_id'],
                    ':department' => trim($_SESSION['department'] ?? ''),
                    ':dealer' => trim($_SESSION['dealer'] ?? ''),
                ]);

                if ((int)$stmt->fetchColumn() > 0) {
                    $_SESSION['is_dept_pic'] = 1;
                    return true;
                }
            }
        } catch (Throwable $e) {
            // Fall back to the legacy users.is_dept_pic flag below.
        }
    }

    if (!isset($_SESSION['is_dept_pic']) && isset($_SESSION['user_id'])) {
        if ($pdo instanceof PDO) {
            try {
                $stmt = $pdo->prepare('SELECT COALESCE(is_dept_pic, 0) FROM users WHERE user_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
                $_SESSION['is_dept_pic'] = (int)$stmt->fetchColumn();
            } catch (Throwable $e) {
                $_SESSION['is_dept_pic'] = 0;
            }
        }
    }

    return (int)($_SESSION['is_dept_pic'] ?? 0) === 1;
}

function liquidation_can_encode_department_request(): bool
{
    return liquidation_is_department_head() || liquidation_is_department_pic();
}

function liquidation_is_general_manager(): bool
{
    return ($_SESSION['position'] ?? '') === 'General Manager';
}

function liquidation_is_branch_manager(): bool
{
    return stripos((string)($_SESSION['position'] ?? ''), 'Branch Manager') !== false;
}

function liquidation_is_final_approver_role(): bool
{
    return liquidation_is_general_manager() || liquidation_is_branch_manager();
}

function liquidation_has_final_approver_assignment(): bool
{
    global $pdo;

    if (!$pdo instanceof PDO || !isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM department_pic_assignments
            WHERE final_approver_user_id = :user_id
        ");
        $stmt->execute([':user_id' => (int)$_SESSION['user_id']]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function liquidation_can_final_approve(): bool
{
    return liquidation_is_final_approver_role() || liquidation_has_final_approver_assignment();
}

function liquidation_get_default_final_approver(PDO $pdo, string $dealer): ?array
{
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, department, position, dealer
        FROM users
        WHERE is_active = 1
          AND (dealer = :dealer OR dealer = 'OG')
          AND position = 'General Manager'
        ORDER BY CASE WHEN dealer = :dealer_order THEN 0 ELSE 1 END, full_name ASC
        LIMIT 1
    ");
    $stmt->execute([
        ':dealer' => $dealer,
        ':dealer_order' => $dealer,
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function liquidation_get_final_approver_for_request(PDO $pdo, string $dealer, string $department, int $creatorUserId): ?array
{
    $route = liquidation_get_approval_route_for_request($pdo, $dealer, $department, $creatorUserId);
    if ($route && !empty($route['final_approver'])) {
        return $route['final_approver'];
    }

    return liquidation_get_default_final_approver($pdo, $dealer);
}

function liquidation_get_approval_route_for_request(PDO $pdo, string $dealer, string $department, int $creatorUserId): ?array
{
    $route = [
        'department_approver' => null,
        'final_approver' => null,
        'series_prefix' => null,
    ];

    try {
        $stmt = $pdo->prepare("
            SELECT
                department_approver.user_id AS department_approver_user_id,
                department_approver.full_name AS department_approver_name,
                department_approver.department AS department_approver_department,
                department_approver.position AS department_approver_position,
                department_approver.dealer AS department_approver_dealer,
                final_approver.user_id AS final_approver_user_id,
                final_approver.full_name AS final_approver_name,
                final_approver.department AS final_approver_department,
                final_approver.position AS final_approver_position,
                final_approver.dealer AS final_approver_dealer,
                dpa.series_prefix
            FROM department_pic_assignments dpa
            LEFT JOIN users department_approver
                ON department_approver.user_id = dpa.department_approver_user_id
               AND department_approver.is_active = 1
            LEFT JOIN users final_approver
                ON final_approver.user_id = dpa.final_approver_user_id
               AND final_approver.is_active = 1
            WHERE dpa.pic_user_id = :creator_user_id
              AND dpa.dealer = :dealer
              AND UPPER(TRIM(dpa.department)) = UPPER(TRIM(:department))
            LIMIT 1
        ");
        $stmt->execute([
            ':creator_user_id' => $creatorUserId,
            ':dealer' => $dealer,
            ':department' => $department,
        ]);
        $assigned = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($assigned) {
            $route['series_prefix'] = liquidation_normalize_series_prefix($assigned['series_prefix'] ?? null, $dealer);

            if (!empty($assigned['department_approver_user_id'])) {
                $route['department_approver'] = [
                    'user_id' => $assigned['department_approver_user_id'],
                    'full_name' => $assigned['department_approver_name'],
                    'department' => $assigned['department_approver_department'],
                    'position' => $assigned['department_approver_position'],
                    'dealer' => $assigned['department_approver_dealer'],
                ];
            }

            if (!empty($assigned['final_approver_user_id'])) {
                $route['final_approver'] = [
                    'user_id' => $assigned['final_approver_user_id'],
                    'full_name' => $assigned['final_approver_name'],
                    'department' => $assigned['final_approver_department'],
                    'position' => $assigned['final_approver_position'],
                    'dealer' => $assigned['final_approver_dealer'],
                ];
            }

            return $route;
        }
    } catch (Throwable $e) {
        // Fall back to the dealer General Manager for legacy setups.
    }

    return null;
}

function liquidation_department_approval_is_assigned_to_current_user(array $liquidation): bool
{
    $assignedDepartmentApproverId = (int)($liquidation['department_approver_user_id'] ?? 0);
    if ($assignedDepartmentApproverId > 0) {
        return isset($_SESSION['user_id']) && $assignedDepartmentApproverId === (int)$_SESSION['user_id'];
    }

    return liquidation_is_department_head()
        && strtoupper(trim($liquidation['department'] ?? '')) === strtoupper(trim($_SESSION['department'] ?? ''));
}

function liquidation_is_secretary(): bool
{
    return strcasecmp(trim((string)($_SESSION['position'] ?? '')), 'Secretary') === 0;
}

function liquidation_is_accounting_user(): bool
{
    return ($_SESSION['department'] ?? '') === 'ACCOUNTING';
}

function liquidation_is_accounting_staff(): bool
{
    $department = $_SESSION['department'] ?? '';
    $position = $_SESSION['position'] ?? '';

    return $department === 'ACCOUNTING' && !in_array($position, ['Manager', 'viewer'], true);
}

function liquidation_get_accounting_manager_user(PDO $pdo, string $dealer): ?array
{
    $stmt = $pdo->prepare("
        SELECT user_id, full_name, department, position, dealer
        FROM users
        WHERE is_active = 1
          AND dealer = :dealer
          AND department = 'ACCOUNTING'
        ORDER BY
            CASE
                WHEN department = 'ACCOUNTING' AND position = 'Manager' THEN 0
                ELSE 1
            END,
            full_name ASC
        LIMIT 1
    ");
    $stmt->execute([':dealer' => $dealer]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    return $user ?: null;
}

function liquidation_is_accounting_manager(?string $dealer = null): bool
{
    global $pdo;

    if (!$pdo instanceof PDO || !isset($_SESSION['user_id'])) {
        return false;
    }

    $targetDealer = trim($dealer ?? ($_SESSION['dealer'] ?? ''));
    if ($targetDealer === '') {
        return false;
    }

    $accountingManager = liquidation_get_accounting_manager_user($pdo, $targetDealer);
    if (!$accountingManager) {
        return false;
    }

    return (int)$accountingManager['user_id'] === (int)$_SESSION['user_id'];
}

function liquidation_workflow_version(array $request): int
{
    return max(1, (int)($request['workflow_version'] ?? 1));
}

function liquidation_is_current_workflow(array $request): bool
{
    return liquidation_workflow_version($request) >= LIQUIDATION_CURRENT_WORKFLOW_VERSION;
}

function liquidation_initial_status(string $requestType, float $amount, bool $createdByDepartmentHead, ?int $requiresLiquidation = null): string
{
    if (!$createdByDepartmentHead) {
        return 'For Department Manager Approval';
    }

    if ($requestType === 'Cash Advance' || $requestType === 'Liquidation' || ($requestType === 'Request for Payment' && $requiresLiquidation === 0)) {
        return 'Liquidated';
    }

    return $amount > LIQUIDATION_CA_MAX_AMOUNT
        ? 'For GM Approval'
        : 'For Accounting Approval';
}

function liquidation_status_after_department_approval(array $request): string
{
    if (!liquidation_is_current_workflow($request)) {
        return 'For GM Approval';
    }

    $requestType = (string)($request['request_type'] ?? 'Liquidation');
    if ($requestType === 'Cash Advance' || $requestType === 'Liquidation' || ($requestType === 'Request for Payment' && isset($request['requires_liquidation']) && (int)$request['requires_liquidation'] === 0)) {
        return 'Liquidated';
    }

    return (float)($request['liquidation_amount'] ?? 0) > LIQUIDATION_CA_MAX_AMOUNT
        ? 'For GM Approval'
        : 'For Accounting Approval';
}

function liquidation_status_after_final_approval(array $request): string
{
    return liquidation_is_current_workflow($request)
        ? 'For Accounting Approval'
        : 'Liquidated';
}

function liquidation_status_after_sap(array $request): string
{
    if (liquidation_is_current_workflow($request)
        && ($request['request_type'] ?? '') === 'Request for Payment'
        && isset($request['requires_liquidation'])
        && (int)$request['requires_liquidation'] === 0
        && (float)($request['liquidation_amount'] ?? 0) <= LIQUIDATION_CA_MAX_AMOUNT) {
        return 'For Accounting Approval';
    }
    return liquidation_is_current_workflow($request)
        ? 'For GM Approval'
        : 'For Accounting Approval';
}

function liquidation_requires_sap(array $request): bool
{
    $requestType = (string)($request['request_type'] ?? 'Liquidation');
    if ($requestType === 'Cash Advance') {
        return false;
    }

    return !liquidation_is_current_workflow($request) || $requestType === 'Liquidation'
        || ($requestType === 'Request for Payment' && isset($request['requires_liquidation']) && (int)$request['requires_liquidation'] === 0);
}

function liquidation_requires_final_approval(array $request): bool
{
    if (!liquidation_is_current_workflow($request)) {
        return true;
    }

    $requestType = (string)($request['request_type'] ?? 'Liquidation');
    return $requestType === 'Liquidation'
        || ($requestType === 'Request for Payment'
            && (float)($request['liquidation_amount'] ?? 0) > LIQUIDATION_CA_MAX_AMOUNT);
}

function liquidation_requires_accounting_manager(array $request): bool
{
    if (!liquidation_is_current_workflow($request)) {
        return (string)($request['request_type'] ?? 'Liquidation') !== 'Cash Advance';
    }

    return (string)($request['request_type'] ?? 'Liquidation') === 'Liquidation';
}

function liquidation_can_complete_request(array $request): bool
{
    if (!liquidation_is_accounting_user()) {
        return false;
    }

    global $pdo;
    return $pdo instanceof PDO
        && liquidation_can_complete_for_dealer($pdo, $request, (int)($_SESSION['user_id'] ?? 0));
}

function liquidation_require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['status' => 'danger', 'message' => 'Unauthorized']);
        exit;
    }
}

function liquidation_require_department_head(): void
{
    liquidation_require_login();
    if (!liquidation_is_department_head()) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Only department heads can use this liquidation module.']);
        exit;
    }
}

function liquidation_require_department_encoder(): void
{
    liquidation_require_login();
    if (!liquidation_can_encode_department_request()) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Only Department PICs or Department Managers can use this module.']);
        exit;
    }
}

function liquidation_require_general_manager(): void
{
    liquidation_require_login();
    if (!liquidation_can_final_approve()) {
        http_response_code(403);
        echo json_encode(['status' => 'danger', 'message' => 'Only the assigned final approver can approve liquidations.']);
        exit;
    }
}

function liquidation_normalize_series_prefix(?string $seriesPrefix, string $dealer): string
{
    $prefix = preg_replace('/[^A-Za-z0-9]/', '', strtoupper(trim((string)$seriesPrefix)));

    if ($prefix === '') {
        $dealerCode = strtoupper(trim($dealer));
        if ($dealerCode === 'TNESC') {
            return 'NES';
        }

        $prefix = preg_replace('/[^A-Za-z0-9]/', '', $dealerCode);
    }

    if ($prefix === '') {
        $prefix = 'DLR';
    }

    return substr($prefix, 0, 20);
}

function liquidation_generate_number(PDO $pdo, string $dealer, string $requestType = 'Liquidation', ?string $seriesPrefix = null): string
{
    $dealerCode = liquidation_normalize_series_prefix($seriesPrefix, $dealer);

    if ($requestType === 'Request for Payment') {
        $typeCode = 'RFP';
    } elseif ($requestType === 'Cash Advance') {
        $typeCode = 'CA';
    } else {
        $typeCode = 'LIQ';
    }
    $seriesPrefix = $dealerCode . '-' . $typeCode . '-';
    $prefix = $seriesPrefix . date('Y-m') . '-';
    $stmt = $pdo->prepare("
        SELECT liquidation_number
        FROM liquidations
        WHERE dealer = ?
          AND liquidation_number LIKE ?
        ORDER BY CAST(RIGHT(liquidation_number, 4) AS UNSIGNED) DESC, liquidation_id DESC
        LIMIT 1
    ");
    $stmt->execute([$dealer, $seriesPrefix . '%']);
    $lastNumber = (string)$stmt->fetchColumn();
    $nextSequence = 1;

    if ($lastNumber !== '') {
        $lastSequence = (int)substr($lastNumber, -4);
        $nextSequence = $lastSequence + 1;
    }

    $existsStmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM liquidations
        WHERE liquidation_number = ?
    ");

    do {
        $candidateNumber = $prefix . str_pad((string)$nextSequence, 4, '0', STR_PAD_LEFT);
        $existsStmt->execute([$candidateNumber]);
        $numberExists = (int)$existsStmt->fetchColumn() > 0;
        if ($numberExists) {
            $nextSequence++;
        }
    } while ($numberExists);

    return $candidateNumber;
}

function liquidation_get_allowed_budget(PDO $pdo, int $budgetId, string $department, string $dealer): ?array
{
    $sql = "
        SELECT budget_id, budget_code, budget_activity, budget_department, budget_dealer
        FROM budgets
        WHERE budget_id = :budget_id
          AND UPPER(TRIM(budget_department)) = UPPER(TRIM(:department))
    ";
    $params = [
        ':budget_id' => $budgetId,
        ':department' => $department
    ];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $sql .= " AND budget_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $budget = $stmt->fetch(PDO::FETCH_ASSOC);

    return $budget ?: null;
}

function liquidation_get_budget_monitoring(
    PDO $pdo,
    int $budgetId,
    string $department,
    string $dealer,
    bool $lockBudget = false
): ?array {
    return getUnifiedBudgetRemainingById($pdo, $budgetId, $department, $dealer, $lockBudget);
}

function liquidation_store_attachment(array $file, string $liquidationNumber): array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['status' => 'success', 'path' => null];
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return ['status' => 'danger', 'message' => 'Upload failed. Please choose a valid file.'];
    }

    if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
        return ['status' => 'danger', 'message' => 'File must be below 5MB.'];
    }

    $mime = mime_content_type($file['tmp_name']);
    $allowedMimes = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png'
    ];

    if (!array_key_exists($mime, $allowedMimes)) {
        return ['status' => 'danger', 'message' => 'Only PDF, JPG, and PNG files are allowed.'];
    }

    $uploadDir = __DIR__ . '/../../uploads/liquidations/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $safeNumber = preg_replace('/[^A-Za-z0-9_\-]/', '_', $liquidationNumber);
    $fileName = 'LIQ_' . $safeNumber . '_' . time() . '.' . $allowedMimes[$mime];
    $destPath = $uploadDir . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return ['status' => 'danger', 'message' => 'Upload failed.'];
    }

    return ['status' => 'success', 'path' => 'uploads/liquidations/' . $fileName];
}
