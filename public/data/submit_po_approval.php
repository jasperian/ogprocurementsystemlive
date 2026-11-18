<?php
session_start();
header('Content-Type: application/json');
require '../../includes/db.php';
require '../includes/po_approval_helpers.php';

po_approval_ensure_tables($pdo);

$requestId = (int)($_POST['po_approval_id'] ?? 0);
$action = $_POST['action'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);

if ($requestId <= 0 || $userId <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM po_approval_requests WHERE request_id = ?");
$stmt->execute([$requestId]);
$request = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$request) {
    echo json_encode(['status' => 'error', 'message' => 'PO approval request not found.']);
    exit;
}

try {
    if ($request['status'] === 'cancelled' && $action !== '') {
        throw new Exception('This PO approval request has been cancelled.');
    }

    if ($action === 'cancel') {
        if (!po_approval_is_manager_user()) {
            throw new Exception('Only Manager users can cancel a PO.');
        }
        if ($request['status'] === 'approved') {
            throw new Exception('Approved POs cannot be cancelled from this approval page.');
        }

        $stmt = $pdo->prepare("UPDATE po_approval_requests SET status = 'cancelled', updated_at = NOW() WHERE request_id = ?");
        $stmt->execute([$requestId]);

        echo json_encode(['status' => 'success', 'message' => 'PO approval request cancelled.']);
        exit;
    }

    if ($action === 'save_details') {
        if ($request['status'] === 'approved') {
            throw new Exception('Approved PO details can no longer be changed.');
        }
        if (!po_approval_is_purchasing_user() && (int)$request['created_by'] !== $userId) {
            throw new Exception('Only the PO creator or Purchasing can update PO number/date.');
        }

        $finalPoNumber = trim($_POST['final_po_number'] ?? '');
        $poDate = trim($_POST['po_date'] ?? '');
        if ($finalPoNumber === '') {
            $finalPoNumber = $request['placeholder_po_number'];
        }
        if ($poDate === '') {
            $poDate = date('Y-m-d');
        }

        if ($finalPoNumber !== ($request['placeholder_po_number'] ?? '')) {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_number = ?");
            $stmt->execute([$finalPoNumber]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('Final PO number already exists.');
            }
        }

        $stmt = $pdo->prepare("UPDATE po_approval_requests SET final_po_number = ?, po_date = ?, updated_at = NOW() WHERE request_id = ?");
        $stmt->execute([$finalPoNumber, $poDate, $requestId]);

        echo json_encode(['status' => 'success', 'message' => 'PO details saved.']);
        exit;
    }

    if ($action === 'submit') {
        if (!po_approval_is_purchasing_user()) {
            throw new Exception('Only Purchasing users can submit PO approval requests.');
        }
        if ($request['status'] !== 'draft') {
            throw new Exception('This PO approval request has already been submitted.');
        }

        $finalPoNumber = trim($_POST['final_po_number'] ?? '');
        $poDate = trim($_POST['po_date'] ?? '');
        if ($finalPoNumber === '') {
            $finalPoNumber = $request['placeholder_po_number'];
        }
        if ($poDate === '') {
            $poDate = date('Y-m-d');
        }

        po_approval_assert_budget_available(
            $pdo,
            $request['pr_number'],
            $request['budget_code'] ?? null,
            $request['dealer'] ?? null,
            (float)$request['amount']
        );

        $stmt = $pdo->prepare("
            UPDATE po_approval_requests
            SET final_po_number = ?, po_date = ?, status = 'pending', current_step = 1, updated_at = NOW()
            WHERE request_id = ?
        ");
        $stmt->execute([$finalPoNumber, $poDate, $requestId]);

        echo json_encode(['status' => 'success', 'message' => 'PO submitted for approval.']);
        exit;
    }

    if ($action === 'approve') {
        if ($request['status'] !== 'pending') {
            throw new Exception('Only pending PO approval requests can be approved.');
        }

        $finalPoNumber = trim($_POST['final_po_number'] ?? ($request['final_po_number'] ?? ''));
        $poDate = trim($_POST['po_date'] ?? ($request['po_date'] ?? ''));
        if ($finalPoNumber !== ($request['final_po_number'] ?? '') || $poDate !== ($request['po_date'] ?? '')) {
            if (!po_approval_is_purchasing_user() && (int)$request['created_by'] !== $userId) {
                throw new Exception('Only the PO creator or Purchasing can update PO number/date.');
            }
            $stmt = $pdo->prepare("UPDATE po_approval_requests SET final_po_number = ?, po_date = ?, updated_at = NOW() WHERE request_id = ?");
            $stmt->execute([$finalPoNumber, $poDate ?: date('Y-m-d'), $requestId]);
            $request['final_po_number'] = $finalPoNumber;
            $request['po_date'] = $poDate ?: date('Y-m-d');
        }

        $signatures = po_approval_get_signatures($pdo, $requestId);
        $next = po_approval_get_next_signature($signatures);
        if (!$next) {
            throw new Exception('This PO approval request is already fully approved.');
        }
        if ((int)$next['approver_user_id'] !== $userId) {
            throw new Exception('You are not the next approver for this PO.');
        }

        $isLast = true;
        foreach ($signatures as $signature) {
            if ((int)$signature['sort_order'] > (int)$next['sort_order'] && empty($signature['checked_at'])) {
                $isLast = false;
                break;
            }
        }

        if ($isLast) {
            $effectivePoNumber = trim($request['final_po_number'] ?? '');
            if ($effectivePoNumber === '' || $effectivePoNumber === ($request['placeholder_po_number'] ?? '')) {
                throw new Exception('Replace the pending PO number before final approval.');
            }

            po_approval_assert_budget_available(
                $pdo,
                $request['pr_number'],
                $request['budget_code'] ?? null,
                $request['dealer'] ?? null,
                (float)$request['amount']
            );

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM purchase_order WHERE po_number = ?");
            $stmt->execute([$effectivePoNumber]);
            if ((int)$stmt->fetchColumn() > 0) {
                throw new Exception('Final PO number already exists.');
            }
        }

        $stmt = $pdo->prepare("UPDATE po_approval_signatures SET checked_at = NOW() WHERE signature_id = ?");
        $stmt->execute([$next['signature_id']]);

        $stmt = $pdo->prepare("UPDATE po_approval_requests SET current_step = current_step + 1, updated_at = NOW() WHERE request_id = ?");
        $stmt->execute([$requestId]);

        if ($isLast) {
            po_approval_finalize($pdo, $requestId);
            echo json_encode(['status' => 'success', 'message' => 'PO approved and posted successfully.']);
            exit;
        }

        echo json_encode(['status' => 'success', 'message' => 'Approval recorded.']);
        exit;
    }

    throw new Exception('Invalid action.');
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    exit;
}
