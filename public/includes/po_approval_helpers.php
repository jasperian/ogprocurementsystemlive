<?php
require_once __DIR__ . '/../data/budget_remaining_helper.php';

function po_approval_get_budget_summary(PDO $pdo, string $prNumber, ?string $budgetCode, ?string $dealer, bool $lockBudget = false): ?array
{
    $stmt = $pdo->prepare("
        SELECT pr_department, pr_dealer
        FROM purchase_requisition
        WHERE pr_number = ?
    ");
    $stmt->execute([$prNumber]);
    $pr = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pr) {
        throw new Exception('Purchase requisition not found for budget validation.');
    }

    $department = trim((string)($pr['pr_department'] ?? ''));
    $resolvedDealer = trim((string)($dealer ?: ($pr['pr_dealer'] ?? ($_SESSION['dealer'] ?? ''))));
    $resolvedBudgetCode = trim((string)($budgetCode ?? ''));

    if ($resolvedBudgetCode === '') {
        $stmt = $pdo->prepare("SELECT budget_code FROM canvass_sheet_batches WHERE pr_number = ? LIMIT 1");
        $stmt->execute([$prNumber]);
        $resolvedBudgetCode = trim((string)$stmt->fetchColumn());
    }

    if ($department === '' || $resolvedBudgetCode === '') {
        throw new Exception('Purchase order has no budget code or department to validate.');
    }

    if (is_numeric($resolvedBudgetCode) && (int)$resolvedBudgetCode > 0) {
        $budget = getUnifiedBudgetRemainingById($pdo, (int)$resolvedBudgetCode, $department, $resolvedDealer, $lockBudget);
        if ($budget !== null) {
            return $budget;
        }
    }

    return getUnifiedBudgetRemaining($pdo, $resolvedBudgetCode, $department, $resolvedDealer, $lockBudget);
}

function po_approval_assert_budget_available(PDO $pdo, string $prNumber, ?string $budgetCode, ?string $dealer, float $amount, bool $lockBudget = false): array
{
    if ($amount <= 0) {
        throw new Exception('PO amount must be greater than zero.');
    }

    $budget = po_approval_get_budget_summary($pdo, $prNumber, $budgetCode, $dealer, $lockBudget);
    if (!$budget) {
        throw new Exception('Budget not found for this purchase order.');
    }

    $remainingBudget = (float)($budget['remaining_budget'] ?? 0);
    if ($amount > $remainingBudget) {
        throw new Exception(
            'Budget exceeded. PO amount PHP ' . number_format($amount, 2)
            . ' is higher than the remaining budget of PHP ' . number_format($remainingBudget, 2) . '.'
        );
    }

    return $budget;
}
