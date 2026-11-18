<?php
include '../../includes/db.php';
require_once __DIR__ . '/cash_advance_workflow_helper.php';
session_start();
header('Content-Type: application/json');

try {
    $dealer = $_SESSION['dealer'] ?? null;
    // Status to filter by
    $status_filter = $_GET['status'] ?? 'CA Request';

    // Setup DataTables parameters
    $draw   = $_GET['draw'] ?? 0;
    $start  = $_GET['start'] ?? 0;
    $length = $_GET['length'] ?? 100;
    $search = $_GET['search']['value'] ?? '';

    // Backward-compatible columns: may not exist yet in some environments
    $hasGmApprovedAt = false;
    $colStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'gm_approved_at'");
    if ($colStmt && $colStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasGmApprovedAt = true;
    }
    $hasPoAmount = false;
    $poAmtColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'po_amount'");
    if ($poAmtColStmt && $poAmtColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasPoAmount = true;
    }
    $hasSapDocumentNumber = false;
    $sapColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'sap_document_number'");
    if ($sapColStmt && $sapColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasSapDocumentNumber = true;
    }
    $hasPurchaseRequisitionNumber = false;
    $prColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'purchase_requisition_number'");
    if ($prColStmt && $prColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasPurchaseRequisitionNumber = true;
    }
    $hasLiquidationRemarks = false;
    $liqRemarksColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'liquidation_remarks'");
    if ($liqRemarksColStmt && $liqRemarksColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasLiquidationRemarks = true;
    }
    $hasCaAttachmentFile = false;
    $caAttachmentColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'ca_attachment_file'");
    if ($caAttachmentColStmt && $caAttachmentColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasCaAttachmentFile = true;
    }
    $hasRequestType = false;
    $requestTypeColStmt = $pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'request_type'");
    if ($requestTypeColStmt && $requestTypeColStmt->fetch(PDO::FETCH_ASSOC)) {
        $hasRequestType = true;
    }
    $hasPayableTo = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'payable_to'")->fetch(PDO::FETCH_ASSOC);
    $hasRfpLiquidationRequirement = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'requires_liquidation'")->fetch(PDO::FETCH_ASSOC);
    $hasAllocationsTable = (bool)$pdo->query("SHOW TABLES LIKE 'cash_advance_allocations'")->fetchColumn();
    $hasFinalApproverUserId = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'final_approver_user_id'")->fetch(PDO::FETCH_ASSOC);
    $hasSourceRfpId = (bool)$pdo->query("SHOW COLUMNS FROM cash_advance LIKE 'source_rfp_id'")->fetch(PDO::FETCH_ASSOC);

    // Build Where Clause
    $where = " WHERE pr_status = :status ";
    $params = [':status' => $status_filter];

    if (!empty($dealer) && $dealer !== 'OG') {
        $where .= " AND pr_dealer = :dealer";
        $params[':dealer'] = $dealer;
    }

    if ($status_filter === 'For GM Approval'
        && strtoupper(trim((string)($_SESSION['position'] ?? ''))) === 'GENERAL MANAGER') {
        $where .= " AND NOT EXISTS (SELECT 1 FROM users u WHERE u.user_id = cash_advance.created_by AND LOWER(TRIM(u.username)) = :tnesc_creator)";
        $params[':tnesc_creator'] = PURCHASING_TNESC_SIGNATORY_CREATOR;
        if ($hasFinalApproverUserId) {
            $where .= " AND (final_approver_user_id = :current_gm_user_id OR final_approver_user_id IS NULL)";
            $params[':current_gm_user_id'] = (int)($_SESSION['user_id'] ?? 0);
        }
    }

    // Count total records for this status
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM cash_advance $where");
    $stmtTotal->execute($params);
    $totalRecords = $stmtTotal->fetchColumn();

    // Add search to where clause
    if (!empty($search)) {
        $poAmountSearchClause = $hasPoAmount ? " OR po_amount LIKE :search" : "";
        $sapDocumentSearchClause = $hasSapDocumentNumber ? " OR sap_document_number LIKE :search" : "";
        $purchaseRequisitionSearchClause = $hasPurchaseRequisitionNumber ? " OR purchase_requisition_number LIKE :search" : "";
        $liquidationRemarksSearchClause = $hasLiquidationRemarks ? " OR liquidation_remarks LIKE :search" : "";
        $requestTypeSearchClause = $hasRequestType ? " OR request_type LIKE :search" : "";
        $payableToSearchClause = $hasPayableTo ? " OR payable_to LIKE :search" : "";
        $allocationSearchClause = $hasAllocationsTable ? " OR EXISTS (SELECT 1 FROM cash_advance_allocations caa_search WHERE caa_search.cash_advance_number = cash_advance.pr_number AND (caa_search.budget_code LIKE :search OR caa_search.purchase_requisition_number LIKE :search))" : "";
        $where .= " AND (pr_number LIKE :search{$purchaseRequisitionSearchClause}{$requestTypeSearchClause}{$payableToSearchClause} OR pr_department LIKE :search OR budget_code LIKE :search OR ca_amount LIKE :search{$poAmountSearchClause}{$sapDocumentSearchClause} OR ca_date LIKE :search OR remarks LIKE :search{$liquidationRemarksSearchClause}{$allocationSearchClause})";
        $params[':search'] = "%$search%";
    }

    // Get filtered records count
    $stmtFiltered = $pdo->prepare("SELECT COUNT(*) FROM cash_advance $where");
    $stmtFiltered->execute($params);
    $totalFiltered = $stmtFiltered->fetchColumn();

    // Fetch data
    $sql = "
        SELECT 
            pr_number as ca_number, 
            " . ($hasRequestType ? "request_type" : "'Cash Advance' AS request_type") . ",
            " . ($hasPayableTo ? "payable_to" : "NULL AS payable_to") . ",
            " . ($hasRfpLiquidationRequirement ? "requires_liquidation" : "NULL AS requires_liquidation") . ",
            " . ($hasPurchaseRequisitionNumber ? "purchase_requisition_number" : "NULL AS purchase_requisition_number") . ",
            ca_amount,
            created_by,
            " . ($hasFinalApproverUserId ? "final_approver_user_id" : "NULL AS final_approver_user_id") . ",
            " . ($hasSourceRfpId ? "source_rfp_id, source_rfp_number" : "NULL AS source_rfp_id, NULL AS source_rfp_number") . ",
            pr_status, 
            pr_department, 
            budget_code,
            " . ($hasAllocationsTable ? "(SELECT GROUP_CONCAT(caa.budget_code ORDER BY caa.allocation_id SEPARATOR ', ') FROM cash_advance_allocations caa WHERE caa.cash_advance_number = cash_advance.pr_number) AS allocation_budget_codes," : "NULL AS allocation_budget_codes,") . "
            " . ($hasAllocationsTable ? "(SELECT GROUP_CONCAT(COALESCE(caa.purchase_requisition_number, 'N/A') ORDER BY caa.allocation_id SEPARATOR ', ') FROM cash_advance_allocations caa WHERE caa.cash_advance_number = cash_advance.pr_number) AS allocation_pr_numbers," : "NULL AS allocation_pr_numbers,") . "
            ca_date,
            remarks,
            " . ($hasLiquidationRemarks ? "liquidation_remarks" : "NULL AS liquidation_remarks") . ",
            pr_date, 
            pr_file,
            " . ($hasCaAttachmentFile ? "ca_attachment_file" : "NULL AS ca_attachment_file") . ",
            po_file,
            " . ($hasPoAmount ? "po_amount" : "NULL AS po_amount") . ",
            " . ($hasSapDocumentNumber ? "sap_document_number" : "NULL AS sap_document_number") . ",
            " . ($hasGmApprovedAt ? "gm_approved_at" : "NULL AS gm_approved_at") . ",
            DATEDIFF(CURDATE(), pr_date) AS aging
        FROM cash_advance
        $where
        ORDER BY aging DESC
        LIMIT :start, :length
    ";

    $stmt = $pdo->prepare($sql);

    foreach ($params as $key => $val) {
        if ($key === ':start' || $key === ':length') continue;
        $stmt->bindValue($key, $val, is_int($val) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    $stmt->bindValue(':start', (int)$start, PDO::PARAM_INT);
    $stmt->bindValue(':length', (int)$length, PDO::PARAM_INT);
    $stmt->execute();

    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($data as &$row) {
        $row['phase_label'] = purchasingWorkflowPhaseLabel([
            'request_type' => $row['request_type'] ?? 'Cash Advance',
            'pr_status' => $row['pr_status'] ?? '',
        ]);
    }
    unset($row);

    // Return response
    echo json_encode([
        "draw" => intval($draw),
        "recordsTotal" => $totalRecords,
        "recordsFiltered" => $totalFiltered,
        "data" => $data
    ]);
} catch (Throwable $e) {
    echo json_encode([
        "draw" => intval($_GET['draw'] ?? 0),
        "recordsTotal" => 0,
        "recordsFiltered" => 0,
        "data" => [],
        "error" => "Failed to load cash advance list: " . $e->getMessage()
    ]);
}
