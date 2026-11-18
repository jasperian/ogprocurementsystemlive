<?php
header('Content-Type: application/json');
session_start();
include '../../includes/db.php';
require_once __DIR__ . '/budget_remaining_helper.php';
require_once __DIR__ . '/cash_advance_number_helper.php';
require_once __DIR__ . '/budget_access_helper.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';

$response = ['status' => 'danger', 'message' => 'Invalid request'];
ensureBudgetUserAccessTable($pdo);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode($response);
    exit;
}

$dealer      = trim($_SESSION['dealer'] ?? '');
$user_id     = $_SESSION['user_id'] ?? null;

$ca_amount   = trim($_POST['ca_amount'] ?? '');
$ca_date     = trim($_POST['ca_date'] ?? '');
$department  = trim($_POST['pr_department'] ?? '');
$submittedDepartment = trim($_POST['pr_department'] ?? '');
$budget_id   = (int)($_POST['budget_id'] ?? 0);
$purchase_requisition_number = trim($_POST['purchase_requisition_number'] ?? '');
$remarks     = trim($_POST['remarks'] ?? '');
$request_type = trim($_POST['request_type'] ?? 'Cash Advance');
$payableTo = trim($_POST['payable_to'] ?? '');
$requiresLiquidation = ($_POST['requires_liquidation'] ?? '1') === '0' ? 0 : 1;
$activityDate = trim($_POST['activity_date'] ?? '');
$contentName = trim($_POST['content_name'] ?? '');
$submittedAllocations = $_POST['allocations'] ?? [];
$validatedAllocations = [];
$uploadedFilePath = null;
$caAttachmentFilePath = null;
$caFileExtension = null;
$ca_number = null;
$requestInserted = false;
$hasCaFile = isset($_FILES['ca_file']) && ($_FILES['ca_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE;
$allowedMimes = [
    'application/pdf' => 'pdf',
    'image/jpeg' => 'jpg',
    'image/png' => 'png',
];

if (!in_array($request_type, ['Cash Advance', 'Request for Payment', 'Liquidation'], true)) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid request type.']);
    exit;
}

$requestLabel = $request_type === 'Request for Payment' ? 'RFP' : $request_type;

if (
    !$ca_date ||
    ($request_type !== 'Request for Payment' && (!$department || !$ca_amount || $budget_id <= 0))
) {
    echo json_encode(['status' => 'danger', 'message' => 'All fields are required.']);
    exit;
}

if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'danger', 'message' => 'Unauthorized.']);
    exit;
}

if ($request_type === 'Request for Payment') {
    if ($payableTo === '' || strlen($payableTo) > 255) {
        echo json_encode(['status' => 'danger', 'message' => 'Payable To is required and must be 255 characters or less.']);
        exit;
    }
    if (!is_array($submittedAllocations) || count($submittedAllocations) === 0) {
        echo json_encode(['status' => 'danger', 'message' => 'At least one budget allocation is required for an RFP.']);
        exit;
    }

    $ca_amount = 0.0;
    foreach ($submittedAllocations as $allocation) {
        $allocationDepartment = trim($allocation['department'] ?? '');
        $allocationBudgetId = (int)($allocation['budget_id'] ?? 0);
        $allocationAmount = $allocation['amount'] ?? '';
        $allocationPr = trim($allocation['purchase_requisition_number'] ?? '');
        if ($allocationDepartment === '' || $allocationBudgetId <= 0 || !is_numeric($allocationAmount) || (float)$allocationAmount <= 0) {
            echo json_encode(['status' => 'danger', 'message' => 'Every RFP allocation requires a department, budget, and amount greater than zero.']);
            exit;
        }
        $validatedAllocations[] = [
            'department' => $allocationDepartment,
            'budget_id' => $allocationBudgetId,
            'amount' => round((float)$allocationAmount, 2),
            'purchase_requisition_number' => $allocationPr,
        ];
        $ca_amount += round((float)$allocationAmount, 2);
    }
    $department = $validatedAllocations[0]['department'];
    $budget_id = $validatedAllocations[0]['budget_id'];
    $purchase_requisition_number = $validatedAllocations[0]['purchase_requisition_number'];
}

if ($request_type === 'Liquidation') {
    $activityParts = explode('-', $activityDate);
    if (count($activityParts) !== 3 || !checkdate((int)($activityParts[1] ?? 0), (int)($activityParts[2] ?? 0), (int)($activityParts[0] ?? 0))) {
        echo json_encode(['status' => 'danger', 'message' => 'A valid activity date is required for Liquidation.']);
        exit;
    }
    if (!$hasCaFile) {
        echo json_encode(['status' => 'danger', 'message' => 'An attachment is required for Liquidation.']);
        exit;
    }
    if (strlen($contentName) > 255) {
        echo json_encode(['status' => 'danger', 'message' => 'Content must be 255 characters or less.']);
        exit;
    }
}

if (!is_numeric($ca_amount) || (float)$ca_amount <= 0) {
    echo json_encode(['status' => 'danger', 'message' => $requestLabel . ' amount must be greater than zero.']);
    exit;
}

if ($request_type === 'Cash Advance' && (float)$ca_amount > 10000) {
    echo json_encode([
        'status' => 'danger',
        'code' => 'CA_AMOUNT_REQUIRES_RFP',
        'message' => 'Cash Advance is limited to PHP 10,000. Amounts above PHP 10,000 must be submitted as Request for Payment.'
    ]);
    exit;
}

$dateParts = explode('-', $ca_date);
if (
    count($dateParts) !== 3 ||
    !ctype_digit($dateParts[0]) ||
    !ctype_digit($dateParts[1]) ||
    !ctype_digit($dateParts[2]) ||
    !checkdate((int)$dateParts[1], (int)$dateParts[2], (int)$dateParts[0])
) {
    echo json_encode(['status' => 'danger', 'message' => 'Invalid Cash Advance date.']);
    exit;
}

if (strlen($remarks) > 1000) {
    echo json_encode(['status' => 'danger', 'message' => 'Remarks must be 1000 characters or less.']);
    exit;
}

if ($hasCaFile) {
    $file = $_FILES['ca_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['status' => 'danger', 'message' => 'Attachment upload failed. Please choose a valid file.']);
        exit;
    }

    if ($file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['status' => 'danger', 'message' => 'Attachment must be below 5MB.']);
        exit;
    }

    $mime = mime_content_type($file['tmp_name']);
    if (!array_key_exists($mime, $allowedMimes)) {
        echo json_encode(['status' => 'danger', 'message' => 'Only PDF, JPG, and PNG attachments are allowed.']);
        exit;
    }

    $caFileExtension = $allowedMimes[$mime];
}

if ($purchase_requisition_number !== '') {
    $prSql = "
        SELECT COUNT(*)
        FROM purchase_requisition
        WHERE pr_number = :pr_number
          AND UPPER(TRIM(pr_department)) = UPPER(:department)
          AND UPPER(TRIM(pr_status)) = 'FOR CANVASS'
    ";
    $prParams = [
        ':pr_number' => $purchase_requisition_number,
        ':department' => $department,
    ];

    if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
        $prSql .= " AND UPPER(TRIM(pr_dealer)) = UPPER(:dealer)";
        $prParams[':dealer'] = $dealer;
    }

    $prStmt = $pdo->prepare($prSql);
    $prStmt->execute($prParams);

    if ((int)$prStmt->fetchColumn() === 0) {
        echo json_encode(['status' => 'danger', 'message' => 'Selected Purchase Requisition is invalid for the chosen department.']);
        exit;
    }
}

$allowedBudget = budgetAccessGetAllowedBudget(
    $pdo,
    $budget_id,
    (int)$user_id,
    $department,
    $dealer
);

if ($allowedBudget === null) {
    http_response_code(403);
    echo json_encode(['status' => 'danger', 'message' => 'Selected budget code is not accessible to your account.']);
    exit;
}

if ($request_type === 'Request for Payment') {
    $amountByBudget = [];
    $departmentByBudget = [];
    foreach ($validatedAllocations as &$allocation) {
        $allocationDepartment = $allocation['department'];
        $allowedAllocationBudget = budgetAccessGetAllowedBudget($pdo, $allocation['budget_id'], (int)$user_id, $allocationDepartment, $dealer);
        if ($allowedAllocationBudget === null) {
            http_response_code(403);
            echo json_encode(['status' => 'danger', 'message' => 'One of the selected budget codes is not accessible to your account.']);
            exit;
        }
        $allocation['budget_code'] = $allowedAllocationBudget['budget_code'];
        $amountByBudget[$allocation['budget_id']] = ($amountByBudget[$allocation['budget_id']] ?? 0) + $allocation['amount'];
        $departmentByBudget[$allocation['budget_id']] = $allocationDepartment;

        if ($allocation['purchase_requisition_number'] !== '') {
            $allocationPrSql = "
                SELECT COUNT(*) FROM purchase_requisition
                WHERE pr_number = :pr_number
                  AND UPPER(TRIM(pr_department)) = UPPER(:department)
                  AND UPPER(TRIM(pr_status)) = 'FOR CANVASS'
            ";
            $allocationPrParams = [
                ':pr_number' => $allocation['purchase_requisition_number'],
                ':department' => $allocationDepartment,
            ];
            if ($dealer !== '' && strtoupper($dealer) !== 'OG') {
                $allocationPrSql .= " AND UPPER(TRIM(pr_dealer)) = UPPER(:dealer)";
                $allocationPrParams[':dealer'] = $dealer;
            }
            $allocationPrStmt = $pdo->prepare($allocationPrSql);
            $allocationPrStmt->execute($allocationPrParams);
            if ((int)$allocationPrStmt->fetchColumn() === 0) {
                echo json_encode(['status' => 'danger', 'message' => 'One of the selected Purchase Requisitions is invalid.']);
                exit;
            }
        }
    }
    unset($allocation);

    foreach ($amountByBudget as $allocationBudgetId => $allocatedAmount) {
        $allowed = budgetAccessGetAllowedBudget($pdo, (int)$allocationBudgetId, (int)$user_id, $departmentByBudget[$allocationBudgetId], $dealer);
        $remaining = getUnifiedBudgetRemainingById($pdo, (int)$allocationBudgetId, $allowed['budget_department'], $allowed['budget_dealer']);
        if (!$remaining || $allocatedAmount > (float)$remaining['remaining_budget']) {
            echo json_encode(['status' => 'danger', 'message' => 'An RFP allocation exceeds the remaining budget for ' . $allowed['budget_code'] . '.']);
            exit;
        }
    }
}

$budget = getUnifiedBudgetRemainingById(
    $pdo,
    $budget_id,
    $allowedBudget['budget_department'],
    $allowedBudget['budget_dealer']
);
$budget_code = $allowedBudget['budget_code'];

if ($request_type !== 'Request for Payment' && (float)$ca_amount > (float)$budget['remaining_budget']) {
    echo json_encode([
        'status' => 'danger',
        'message' => 'Budget exceeded. Remaining budget is ₱' . number_format((float)$budget['remaining_budget'], 2) . '.'
    ]);
    exit;
}

try {
    $hasPurchaseRequisitionNumber = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'purchase_requisition_number'")->fetch(PDO::FETCH_ASSOC);
    $hasCaAttachmentFile = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'ca_attachment_file'")->fetch(PDO::FETCH_ASSOC);
    $hasRequestType = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'request_type'")->fetch(PDO::FETCH_ASSOC);
    $hasPayableTo = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'payable_to'")->fetch(PDO::FETCH_ASSOC);
    $hasRequiresLiquidation = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'requires_liquidation'")->fetch(PDO::FETCH_ASSOC);
    $hasLiquidationExemptionReason = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'liquidation_exemption_reason'")->fetch(PDO::FETCH_ASSOC);
    $hasLiquidationDueDate = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'liquidation_due_date'")->fetch(PDO::FETCH_ASSOC);
    $hasAllocationsTable = (bool)$pdo->query("SHOW TABLES LIKE 'cash_advance_allocations'")->fetchColumn();
    $hasActivityDate = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'activity_date'")->fetch(PDO::FETCH_ASSOC);
    $hasContentName = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'content_name'")->fetch(PDO::FETCH_ASSOC);
    $hasFinalApproverUserId = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'final_approver_user_id'")->fetch(PDO::FETCH_ASSOC);

    if (!$hasRequestType) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Database is missing request_type column. Run add_ca_request_type_col.php first.'
        ]);
        exit;
    }

    if ($request_type === 'Request for Payment' && (!$hasPayableTo || !$hasAllocationsTable)) {
        echo json_encode(['status' => 'danger', 'message' => 'RFP allocation schema is missing. Run add_ca_rfp_allocations.php first.']);
        exit;
    }
    if ($request_type === 'Request for Payment' && (!$hasRequiresLiquidation || !$hasLiquidationExemptionReason || !$hasLiquidationDueDate)) {
        echo json_encode(['status' => 'danger', 'message' => 'RFP liquidation fields are missing. Run add_rfp_liquidation_requirement.php first.']);
        exit;
    }
    if ($request_type === 'Liquidation' && (!$hasActivityDate || !$hasContentName)) {
        echo json_encode(['status' => 'danger', 'message' => 'Liquidation fields are missing. Run add_ca_liquidation_fields.php first.']);
        exit;
    }

    if ($hasCaFile && !$hasCaAttachmentFile) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Database is missing ca_attachment_file column. Run add_ca_attachment_file_col.php first.'
        ]);
        exit;
    }

    $pdo->exec('LOCK TABLES cash_advance WRITE, cash_advance_allocations WRITE');

    $ca_number = generateCashAdvanceNumber($pdo, $dealer, $request_type);

    if ($hasCaFile) {
        $uploadDir = __DIR__ . '/../../uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $safeCaNumber = preg_replace('/[^A-Za-z0-9_\-]/', '_', $ca_number);
        $fileName = 'CA_ATT_' . $safeCaNumber . '_' . time() . '.' . $caFileExtension;
        $destPath = $uploadDir . $fileName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            $pdo->exec('UNLOCK TABLES');
            echo json_encode(['status' => 'danger', 'message' => 'Failed to save attachment.']);
            exit;
        }

        $uploadedFilePath = $destPath;
        $caAttachmentFilePath = 'uploads/' . $fileName;
    }

    if ($hasPurchaseRequisitionNumber) {
        $columns = "pr_number, request_type, purchase_requisition_number, ca_amount, ca_date, remarks, pr_status, pr_department, budget_code, budget_id, pr_date, pr_file, pr_dealer, created_by";
        $placeholders = "?, ?, ?, ?, ?, ?, 'CA Request', ?, ?, ?, NOW(), ?, ?, ?";
        $values = [
            $ca_number,
            $request_type,
            $purchase_requisition_number !== '' ? $purchase_requisition_number : null,
            $ca_amount,
            $ca_date,
            $remarks !== '' ? $remarks : null,
            $department,
            $budget_code,
            $budget_id,
            null,
            $dealer,
            $user_id
        ];
    } else {
        $columns = "pr_number, request_type, ca_amount, ca_date, remarks, pr_status, pr_department, budget_code, budget_id, pr_date, pr_file, pr_dealer, created_by";
        $placeholders = "?, ?, ?, ?, ?, 'CA Request', ?, ?, ?, NOW(), ?, ?, ?";
        $values = [
            $ca_number,
            $request_type,
            $ca_amount,
            $ca_date,
            $remarks !== '' ? $remarks : null,
            $department,
            $budget_code,
            $budget_id,
            null,
            $dealer,
            $user_id
        ];
    }

    if ($hasCaAttachmentFile) {
        $columns .= ", ca_attachment_file";
        $placeholders .= ", ?";
        $values[] = $caAttachmentFilePath;
    }

    if ($hasPayableTo) {
        $columns .= ", payable_to";
        $placeholders .= ", ?";
        $values[] = $request_type === 'Request for Payment' ? $payableTo : null;
    }
    if ($hasRequiresLiquidation) {
        $columns .= ", requires_liquidation";
        $placeholders .= ", ?";
        $values[] = $request_type === 'Request for Payment' ? $requiresLiquidation : null;
    }
    if ($hasLiquidationExemptionReason) {
        $columns .= ", liquidation_exemption_reason";
        $placeholders .= ", ?";
        $values[] = null;
    }
    if ($hasLiquidationDueDate) {
        $columns .= ", liquidation_due_date";
        $placeholders .= ", ?";
        $values[] = null;
    }
    if ($hasActivityDate) {
        $columns .= ", activity_date";
        $placeholders .= ", ?";
        $values[] = $request_type === 'Liquidation' ? $activityDate : null;
    }
    if ($hasContentName) {
        $columns .= ", content_name";
        $placeholders .= ", ?";
        $values[] = $request_type === 'Liquidation' && $contentName !== '' ? $contentName : null;
    }
    if ($hasFinalApproverUserId) {
        $columns .= ", final_approver_user_id";
        $placeholders .= ", ?";
        $values[] = purchasingWorkflowResolveFinalApproverId(
            $pdo,
            $dealer,
            $department,
            (int)$user_id
        );
    }

    $stmt = $pdo->prepare("
        INSERT INTO cash_advance
        ($columns)
        VALUES ($placeholders)
    ");
    $stmt->execute($values);
    $requestInserted = true;

    if ($request_type === 'Request for Payment') {
        $allocationStmt = $pdo->prepare("
            INSERT INTO cash_advance_allocations
                (cash_advance_number, department, budget_id, budget_code, purchase_requisition_number, allocation_amount)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        foreach ($validatedAllocations as $allocation) {
            $allocationStmt->execute([
                $ca_number,
                $allocation['department'],
                $allocation['budget_id'],
                $allocation['budget_code'],
                $allocation['purchase_requisition_number'] !== '' ? $allocation['purchase_requisition_number'] : null,
                $allocation['amount'],
            ]);
        }
    }

    $next_ca_number = generateCashAdvanceNumber($pdo, $dealer, $request_type);
    $nextCashAdvanceNumber = generateCashAdvanceNumber($pdo, $dealer, 'Cash Advance');
    $nextRfpNumber = generateCashAdvanceNumber($pdo, $dealer, 'Request for Payment');
    $nextLiquidationNumber = generateCashAdvanceNumber($pdo, $dealer, 'Liquidation');

    $pdo->exec('UNLOCK TABLES');

    echo json_encode([
        'status' => 'success',
        'message' => $requestLabel . ' created successfully. Request Number: ' . $ca_number,
        'ca_number' => $ca_number,
        'next_ca_number' => $next_ca_number,
        'next_numbers' => [
            'cash_advance' => $nextCashAdvanceNumber,
            'request_for_payment' => $nextRfpNumber,
            'liquidation' => $nextLiquidationNumber
        ]
    ]);
} catch (Exception $e) {
    if ($requestInserted && $ca_number) {
        try {
            $cleanupAllocations = $pdo->prepare('DELETE FROM cash_advance_allocations WHERE cash_advance_number = ?');
            $cleanupAllocations->execute([$ca_number]);
            $cleanupRequest = $pdo->prepare('DELETE FROM cash_advance WHERE pr_number = ?');
            $cleanupRequest->execute([$ca_number]);
        } catch (Exception $cleanupException) {
        }
    }
    try {
        $pdo->exec('UNLOCK TABLES');
    } catch (Exception $unlockException) {
    }

    if ($uploadedFilePath && is_file($uploadedFilePath)) {
        @unlink($uploadedFilePath);
    }

    if (strpos($e->getMessage(), "Unknown column 'budget_code'") !== false) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Database is missing budget_code column. Run add_ca_budget_code_col.php first.'
        ]);
        exit;
    }

    if (strpos($e->getMessage(), "Unknown column 'ca_date'") !== false || strpos($e->getMessage(), "Unknown column 'remarks'") !== false) {
        echo json_encode([
            'status' => 'danger',
            'message' => 'Database is missing ca_date or remarks column. Run add_ca_date_remarks_cols.php first.'
        ]);
        exit;
    }

    echo json_encode([
        'status' => 'danger',
        'message' => 'Database error'
    ]);
}
