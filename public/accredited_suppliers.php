<?php
require '../init.php';

function directory_escape($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

$category = is_string($_GET['category'] ?? null) ? trim($_GET['category']) : '';
$search = is_string($_GET['q'] ?? null) ? trim($_GET['q']) : '';
$page = max(1, (int)($_GET['page'] ?? 1));
$pageSize = 25;
$categories = $suppliers = [];
$total = 0;
$pages = 1;
$loadError = false;

try {
    // Use the same eligibility flags as supplier selection for purchasing.
    $eligible = 'is_approved = 1 AND is_active = 1 AND is_rejected = 0';
    $categories = $pdo->query("SELECT DISTINCT TRIM(product_category) AS category
        FROM suppliers WHERE $eligible AND TRIM(COALESCE(product_category, '')) <> ''
        ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
    $where = $eligible;
    $params = [];
    if ($category !== '') {
        $where .= ' AND TRIM(product_category) = ?';
        $params[] = $category;
    }
    if ($search !== '') {
        $where .= ' AND (supplier_name LIKE ? OR vendor_id LIKE ? OR product_category LIKE ? OR contact_person LIKE ?)';
        // Treat user-entered wildcard characters as literal search text.
        $term = '%' . strtr($search, ['\\' => '\\\\', '%' => '\\%', '_' => '\\_']) . '%';
        array_push($params, $term, $term, $term, $term);
    }
    $count = $pdo->prepare("SELECT COUNT(*) FROM suppliers WHERE $where");
    $count->execute($params);
    $total = (int)$count->fetchColumn();
    $pages = max(1, (int)ceil($total / $pageSize));
    $page = min($page, $pages);
    $offset = ($page - 1) * $pageSize;
    $stmt = $pdo->prepare("SELECT supplier_id, vendor_id, supplier_name, product_category, contact_person,
        contact_number, mobile_number, email,
        EXISTS (
            SELECT 1 FROM purchase_order po
            INNER JOIN purchase_requisition pr ON pr.pr_number = po.pr_number
            WHERE po.supplier_id = suppliers.supplier_id AND pr.pr_status = 'Completed'
        ) AS has_completed_po
        FROM suppliers WHERE $where ORDER BY supplier_name, vendor_id LIMIT $pageSize OFFSET $offset");
    $stmt->execute($params);
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log('Accredited supplier directory: ' . $e->getMessage());
    $loadError = true;
}

function directory_page_url(int $page, string $category, string $search): string
{
    return 'accredited_suppliers.php?' . http_build_query(['category' => $category, 'q' => $search, 'page' => $page]);
}

include 'header.php';
include 'navigation.php';
?>
<link rel="stylesheet" href="assets/css/accredited-suppliers.css">
<div class="pc-container accredited-suppliers-page">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div class="page-header-title">
                    <h2 class="mb-0">Accredited Suppliers</h2>
                    <p class="supplier-subtitle">Browse approved, active suppliers and their completed PO history.</p>
                </div>
                <a href="suppliers" class="btn btn-primary d-inline-flex align-items-center gap-2"><i class="ti ti-arrow-left f-18" aria-hidden="true"></i> Supplier List</a>
            </div>
        </div>
        <div class="card supplier-table-card">
            <div class="card-body">
                <form id="directory-filters" method="get" action="accredited_suppliers.php" class="row g-3 align-items-end">
                    <div class="col-lg-4">
                        <label for="directory-category" class="form-label">Business category</label>
                        <select id="directory-category" name="category" class="form-select">
                            <option value="">All categories</option>
                            <?php if ($category !== '' && !in_array($category, $categories, true)): ?>
                                <option value="<?= directory_escape($category) ?>" selected><?= directory_escape($category) ?></option>
                            <?php endif; ?>
                            <?php foreach ($categories as $option): ?>
                                <option value="<?= directory_escape($option) ?>" <?= $category === $option ? 'selected' : '' ?>><?= directory_escape($option) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-5">
                        <label for="directory-search" class="form-label">Search suppliers or categories</label>
                        <input id="directory-search" type="search" name="q" value="<?= directory_escape($search) ?>" class="form-control" placeholder="e.g. printing or stationery">
                    </div>
                    <div class="col-lg-3 d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary d-inline-flex align-items-center gap-2" type="submit"><i class="ti ti-search f-18" aria-hidden="true"></i> Search</button>
                        <a href="accredited_suppliers.php" class="btn btn-outline-secondary d-inline-flex align-items-center gap-2"><i class="ti ti-refresh f-18" aria-hidden="true"></i> Reset</a>
                    </div>
                </form>
                <p id="directory-refresh-status" class="text-muted small mt-3 mb-0" role="status" aria-live="polite">Auto-refresh every 60 seconds.</p>
            </div>
        </div>
        <div id="directory-results" data-page="<?= $page ?>" data-load-error="<?= $loadError ? '1' : '0' ?>">
        <?php if ($loadError): ?>
            <div class="alert alert-danger" role="alert">Unable to load accredited suppliers. Please try again.</div>
        <?php else: ?>
        <div class="card supplier-table-card">
            <div class="card-header"><h5 class="mb-0 d-flex align-items-center gap-2"><i class="ti ti-building-store" aria-hidden="true"></i> Accredited Suppliers <span class="supplier-count"><?= number_format($total) ?></span></h5></div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead><tr><th scope="col">Supplier</th><th scope="col">Business category</th><th scope="col">Contact person</th><th scope="col">Contact details</th><th scope="col">Actions</th><th scope="col">Has Completed PO?</th></tr></thead>
                    <tbody>
                    <?php if (!$suppliers): ?>
                        <tr><td colspan="6" class="text-center py-5">No accredited suppliers match your filters. Try another category or clear your search.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($suppliers as $supplier): ?>
                        <tr>
                            <td class="supplier-name-cell"><span class="supplier-name-text"><?= directory_escape($supplier['supplier_name']) ?></span><span class="supplier-meta">Vendor <?= directory_escape($supplier['vendor_id']) ?></span></td>
                            <td><?= directory_escape($supplier['product_category'] ?: 'Uncategorized') ?></td>
                            <td><?= directory_escape($supplier['contact_person'] ?: '—') ?></td>
                            <td>
                                <?php foreach (['contact_number', 'mobile_number', 'email'] as $field): ?>
                                    <?php if (!empty($supplier[$field])): ?><div><?= directory_escape($supplier[$field]) ?></div><?php endif; ?>
                                <?php endforeach; ?>
                                <?php if (empty($supplier['contact_number']) && empty($supplier['mobile_number']) && empty($supplier['email'])): ?>—<?php endif; ?>
                            </td>
                            <td><div class="supplier-action-group">
                                <a class="supplier-action-btn success" title="Open profile" href="view_supplier?vendor_id=<?= rawurlencode((string)$supplier['vendor_id']) ?>" aria-label="<?= directory_escape('View ' . $supplier['supplier_name']) ?>"><i class="ti ti-eye f-18" aria-hidden="true"></i></a>
                                <a class="supplier-action-btn" title="Completed PO items" href="completed_po_selected_items.php?supplier_id=<?= (int)$supplier['supplier_id'] ?>" aria-label="<?= directory_escape('View completed PO items for ' . $supplier['supplier_name']) ?>"><i class="ti ti-file-text f-18" aria-hidden="true"></i></a>
                            </div></td>
                            <td><span class="supplier-status <?= (int)$supplier['has_completed_po'] ? 'status-success' : 'status-neutral' ?>"><?= (int)$supplier['has_completed_po'] ? 'Yes' : 'No' ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($total > 0): ?>
            <div class="card-footer d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <span class="text-muted">Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $pageSize, $total)) ?> of <?= number_format($total) ?></span>
                <nav aria-label="Supplier results pages" class="d-flex align-items-center gap-2">
                    <?php if ($page > 1): ?><a class="btn btn-outline-secondary btn-sm" href="<?= directory_escape(directory_page_url($page - 1, $category, $search)) ?>">Previous</a><?php endif; ?>
                    <span>Page <?= $page ?> of <?= $pages ?></span>
                    <?php if ($page < $pages): ?><a class="btn btn-outline-secondary btn-sm" href="<?= directory_escape(directory_page_url($page + 1, $category, $search)) ?>">Next</a><?php endif; ?>
                </nav>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        </div>
    </div>
</div>
<script src="assets/js/accredited-suppliers.js"></script>
<?php include 'footer.php'; ?>
