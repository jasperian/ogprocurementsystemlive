<?php
require '../includes/db.php';
session_start();

// Fetch all PRs with their linked POs and suppliers
$dealer = $_SESSION['dealer'] ?? '';
$pr_stmt = $pdo->prepare("
    SELECT pr.*, po.po_id, po.po_number, po.po_date, po.amount_purchased, po.po_file, s.supplier_name
    FROM purchase_requisition pr
    LEFT JOIN purchase_order po ON pr.pr_number = po.pr_number
    LEFT JOIN suppliers s ON po.supplier_id = s.supplier_id
    WHERE pr.pr_dealer = :dealer
    ORDER BY pr.pr_number DESC, po.po_date ASC
");
$pr_stmt->execute(['dealer' => $dealer]);

$results = $pr_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group POs under their PR
$grouped = [];
foreach ($results as $row) {
    $pr_id = $row['pr_number'];
    if (!isset($grouped[$pr_id])) {
        $grouped[$pr_id] = [
            'pr_number' => $row['pr_number'],
            'created_at' => $row['created_at'],
            'total_amount' => 0,
            'pos' => []
        ];
    }
    if ($row['po_id']) { // Only add if PO exists
        $grouped[$pr_id]['pos'][] = [
            'po_number' => $row['po_number'],
            'po_date' => $row['po_date'],
            'supplier_name' => $row['supplier_name'],
            'amount_purchased' => $row['amount_purchased'],
            'po_file' => $row['po_file']
        ];
         $grouped[$pr_id]['total_amount'] += $row['amount_purchased'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Purchase Requisition List</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        body {
            background: #f8f9fa;
        }
        .accordion-button {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .amount-badge {
            font-size: 0.9rem;
            background: #0d6efd;
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
        }
        table th, table td {
            vertical-align: middle;
        }
    </style>
</head>
<body class="p-4">
    <div class="container">
        <h2 class="mb-4 text-center">📋 Purchase Requisition List</h2>
        <div class="accordion shadow-sm rounded" id="prAccordion">
            <?php foreach ($grouped as $pr_id => $pr): ?>
                <div class="accordion-item">
                    <h2 class="accordion-header" id="heading<?= $pr_id; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                data-bs-target="#collapse<?= $pr_id; ?>" aria-expanded="false">
                            <div>
                                <strong>PR #<?= htmlspecialchars($pr['pr_number']); ?></strong> 
                                <small class="text-muted ms-2">(Created: <?= date("M d, Y", strtotime($pr['created_at'])); ?>)</small>
                            </div>
                            <span class="amount-badge">
                                Total: ₱ <?= number_format($pr['total_amount'], 2); ?>
                            </span>
                        </button>
                    </h2>
                    <div id="collapse<?= $pr_id; ?>" class="accordion-collapse collapse" data-bs-parent="#prAccordion">
                        <div class="accordion-body bg-white">
                            <?php if (!empty($pr['pos'])): ?>
                                <table class="table table-hover table-bordered align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>PO Number</th>
                                            <th>PO Date</th>
                                            <th>Supplier</th>
                                            <th>Amount</th>
                                            <th>File</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pr['pos'] as $po): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($po['po_number']); ?></td>
                                                <td><?= date("M d, Y", strtotime($po['po_date'])); ?></td>
                                                <td><?= htmlspecialchars($po['supplier_name']); ?></td>
                                                <td>₱ <?= number_format($po['amount_purchased'], 2); ?></td>
                                                <td>
                                                    <?php if (!empty($po['po_file'])): ?>
                                                        <a href="uploads/<?= htmlspecialchars($po['po_file']); ?>" target="_blank" class="btn btn-sm btn-primary">View PDF</a>
                                                    <?php else: ?>
                                                        <span class="text-muted">No file</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <div class="alert alert-warning mb-0">No Purchase Orders yet.</div>
                            <?php endif; ?>
                            <div class="d-flex justify-content-end gap-2 mt-3">
                                <a href="add_po.php?pr_id=<?= $pr_id; ?>" class="btn btn-success btn-sm">➕ Add PO</a>
                                <a href="view_pr.php?pr_id=<?= $pr_id; ?>" class="btn btn-info btn-sm">🔍 View Full PR</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</body>
</html>