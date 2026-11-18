<?php
require '../init.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    header('Location: dashboard');
    exit;
}

include 'header.php';
include 'navigation.php';

$uploadGroups = [
    'Cash Advance' => [
        'icon' => 'ti-cash',
        'tone' => 'danger',
        'directories' => [['path' => 'uploads/', 'recursive' => false, 'match' => '/^CA(?:_|REQ_|UPD_)/i']],
    ],
    'Liquidation / RFP / CA' => [
        'icon' => 'ti-receipt-2',
        'tone' => 'secondary',
        'directories' => [['path' => 'uploads/liquidations/', 'recursive' => true]],
    ],
    'Purchase Order' => [
        'icon' => 'ti-file-invoice',
        'tone' => 'success',
        'directories' => [['path' => 'uploads/po/', 'recursive' => true]],
    ],
    'Budget' => [
        'icon' => 'ti-chart-pie',
        'tone' => 'primary',
        'directories' => [
            ['path' => 'uploads/budget_adjustments/', 'recursive' => true],
            ['path' => 'public/budget/uploads/', 'recursive' => true],
        ],
    ],
    'Canvass' => [
        'icon' => 'ti-clipboard-list',
        'tone' => 'warning',
        'directories' => [['path' => 'public/data/uploads/proposals/', 'recursive' => true]],
    ],
    'Item Master' => [
        'icon' => 'ti-package',
        'tone' => 'info',
        'directories' => [['path' => 'public/data/uploads/items/', 'recursive' => true]],
    ],
    'Supplier Profile' => [
        'icon' => 'ti-files',
        'tone' => 'dark',
        'directories' => [['path' => 'public/uploads/', 'recursive' => true]],
    ],
    'Purchase Requisition' => [
        'icon' => 'ti-file-description',
        'tone' => 'info',
        'directories' => [['path' => 'public/data/uploads/', 'recursive' => false]],
    ],
    'Test / Temporary Uploads' => [
        'icon' => 'ti-flask',
        'tone' => 'secondary',
        'directories' => [['path' => 'uploads/test/', 'recursive' => true]],
    ],
];

function auditRootPath(): string
{
    return dirname(__DIR__);
}

function auditAbsolutePath(string $relativePath): string
{
    return auditRootPath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath, '/\\'));
}

function auditPublicHref(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    return str_starts_with($relativePath, 'public/') ? substr($relativePath, 7) : '../' . $relativePath;
}

function auditFormatSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float)$bytes;
    $index = 0;
    while ($size >= 1024 && $index < count($units) - 1) {
        $size /= 1024;
        $index++;
    }
    return number_format($size, $index === 0 ? 0 : 2) . ' ' . $units[$index];
}

function auditIcon(string $extension): string
{
    return match (strtolower($extension)) {
        'pdf' => 'ti-file-type-pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'ti-photo',
        'doc', 'docx' => 'ti-file-type-doc',
        'xls', 'xlsx', 'csv' => 'ti-file-spreadsheet',
        default => 'ti-file',
    };
}

function auditTableExists(PDO $pdo, string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }
    try {
        $stmt = $pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$table]);
        return $cache[$table] = (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return $cache[$table] = false;
    }
}

function auditColumnExists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $key = $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return $cache[$key] = (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return $cache[$key] = false;
    }
}

function auditPathCandidates(string $relativePath): array
{
    $relativePath = str_replace('\\', '/', $relativePath);
    $candidates = [$relativePath, basename($relativePath)];
    if (str_starts_with($relativePath, 'public/')) {
        $candidates[] = substr($relativePath, 7);
    }
    if (str_starts_with($relativePath, 'public/data/')) {
        $candidates[] = substr($relativePath, 12);
    }
    return array_values(array_unique(array_filter($candidates)));
}

function auditHasMatch(PDO $pdo, string $relativePath): bool
{
    $candidates = auditPathCandidates($relativePath);
    $placeholders = implode(',', array_fill(0, count($candidates), '?'));

    $checks = [
        ['purchase_order', ['po_file']],
        ['canvass_quotation', ['quotation_file']],
        ['purchase_requisition', ['pr_file']],
        ['budget_adjustments', ['file_path']],
        ['liquidations', ['attachment_path']],
    ];

    if (auditTableExists($pdo, 'cash_advance')) {
        $checks[] = ['cash_advance', ['pr_file', 'po_file', 'ca_attachment_file']];
    }

    foreach ($checks as [$table, $columns]) {
        if (!auditTableExists($pdo, $table)) {
            continue;
        }
        $where = [];
        $params = [];
        foreach ($columns as $column) {
            if (!auditColumnExists($pdo, $table, $column)) {
                continue;
            }
            $where[] = "`$column` IN ($placeholders)";
            $params = array_merge($params, $candidates);
        }
        if (!$where) {
            continue;
        }
        try {
            $stmt = $pdo->prepare('SELECT 1 FROM `' . $table . '` WHERE ' . implode(' OR ', $where) . ' LIMIT 1');
            $stmt->execute($params);
            if ($stmt->fetchColumn()) {
                return true;
            }
        } catch (Throwable $e) {
            continue;
        }
    }

    if (auditTableExists($pdo, 'supplier_profile')) {
        $supplierColumns = [
            'company_profile',
            'product_services',
            'business_permit',
            'fin_statement',
            'org_chart',
            'authorize_distributor',
            'gen_info',
            'mayors_permit',
            'sec_cert',
            'bir_cert',
            'brgy_permit',
            'dti',
            'memorandum_association',
        ];
        $where = [];
        $params = [];
        foreach ($supplierColumns as $column) {
            if (!auditColumnExists($pdo, 'supplier_profile', $column)) {
                continue;
            }
            $where[] = "`$column` IN ($placeholders)";
            $params = array_merge($params, $candidates);
        }
        if ($where) {
            try {
                $stmt = $pdo->prepare('SELECT 1 FROM supplier_profile WHERE ' . implode(' OR ', $where) . ' LIMIT 1');
                $stmt->execute($params);
                if ($stmt->fetchColumn()) {
                    return true;
                }
            } catch (Throwable $e) {
                return false;
            }
        }
    }

    return false;
}

function collectAuditFiles(array $directory): array
{
    $baseRelative = trim($directory['path'], '/\\') . '/';
    $baseAbsolute = auditAbsolutePath($baseRelative);
    $files = [];

    if (!is_dir($baseAbsolute)) {
        return $files;
    }

    $iterator = !empty($directory['recursive'])
        ? new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseAbsolute, FilesystemIterator::SKIP_DOTS))
        : new IteratorIterator(new DirectoryIterator($baseAbsolute));

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }
        $filename = $fileInfo->getFilename();
        if (!empty($directory['match']) && !preg_match($directory['match'], $filename)) {
            continue;
        }
        $relativePath = str_replace('\\', '/', substr($fileInfo->getPathname(), strlen(auditRootPath()) + 1));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $files[] = [
            'name' => $filename,
            'relative_path' => $relativePath,
            'folder' => dirname($relativePath) === '.' ? '' : dirname($relativePath),
            'href' => auditPublicHref($relativePath),
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'size_bytes' => $fileInfo->getSize(),
            'size' => auditFormatSize($fileInfo->getSize()),
            'modified_at' => $fileInfo->getMTime(),
            'modified_label' => date('M d, Y h:i A', $fileInfo->getMTime()),
            'icon' => auditIcon($extension),
        ];
    }

    return $files;
}

$groupsWithFiles = [];
$totalUnmatched = 0;
$totalBytes = 0;
$latestModifiedAt = 0;

foreach ($uploadGroups as $groupName => $group) {
    $unmatchedFiles = [];
    foreach ($group['directories'] as $directory) {
        foreach (collectAuditFiles($directory) as $file) {
            if (!auditHasMatch($pdo, $file['relative_path'])) {
                $unmatchedFiles[] = $file;
                $totalUnmatched++;
                $totalBytes += $file['size_bytes'];
                $latestModifiedAt = max($latestModifiedAt, $file['modified_at']);
            }
        }
    }
    usort($unmatchedFiles, fn($a, $b) => $b['modified_at'] <=> $a['modified_at']);
    $group['name'] = $groupName;
    $group['files'] = $unmatchedFiles;
    $group['file_count'] = count($unmatchedFiles);
    $groupsWithFiles[] = $group;
}
?>

<style>
    .unmatched-files-wrap {
        --audit-border: #dbe4ef;
        --audit-muted: #667085;
    }

    .audit-summary,
    .audit-group,
    .audit-stat {
        background: #fff;
        border: 1px solid var(--audit-border);
        border-radius: 8px;
    }

    .audit-stat {
        padding: 16px;
    }

    .audit-group-header {
        align-items: flex-start;
        border-bottom: 1px solid var(--audit-border);
        display: flex;
        gap: 14px;
        padding: 18px;
    }

    .audit-icon {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        flex: 0 0 38px;
        height: 38px;
        justify-content: center;
        width: 38px;
    }

    .audit-table-wrap {
        overflow-x: auto;
    }

    .audit-table {
        min-width: 980px;
    }

    .audit-table td,
    .audit-table th {
        vertical-align: middle;
    }

    .audit-path {
        color: #344054;
        font-size: 0.82rem;
        white-space: normal;
        word-break: break-word;
    }

    .audit-group p {
        color: var(--audit-muted);
    }

    .audit-filter {
        max-width: 360px;
    }

    .audit-pagination {
        align-items: center;
        border-top: 1px solid var(--audit-border);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        padding: 12px 18px;
    }

    .audit-page-size {
        max-width: 170px;
    }

    @media (max-width: 767.98px) {
        .audit-group-header {
            flex-direction: column;
        }

        .audit-filter {
            max-width: none;
            width: 100%;
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content unmatched-files-wrap">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="page-header-title">
                                <h2 class="mb-0">Unmatched Uploaded Files</h2>
                            </div>
                            <div class="input-group audit-filter">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="search" id="auditSearch" class="form-control" placeholder="Search file or process">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="audit-summary p-3 p-md-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <span class="badge bg-light-warning mb-2">Upload audit</span>
                            <h4 class="mb-2">Files found in upload folders without matching database records</h4>
                            <p class="mb-0">
                                These files exist on disk, but no PR, PO, supplier profile, cash advance, liquidation, canvass, or budget record currently points to them.
                            </p>
                        </div>
                        <div class="col-lg-4">
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="audit-stat">
                                        <div class="text-muted small">Groups</div>
                                        <h4 class="mb-0"><?= count($groupsWithFiles) ?></h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="audit-stat">
                                        <div class="text-muted small">Files</div>
                                        <h4 class="mb-0"><?= number_format($totalUnmatched) ?></h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="audit-stat">
                                        <div class="text-muted small">Size</div>
                                        <h4 class="mb-0"><?= htmlspecialchars(auditFormatSize($totalBytes)) ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                Latest unmatched file: <?= $latestModifiedAt ? htmlspecialchars(date('M d, Y h:i A', $latestModifiedAt)) : 'No files found' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="auditGroups">
            <?php foreach ($groupsWithFiles as $group) {
                $processSearchText = strtolower($group['name']);
            ?>
                <div class="audit-group shadow-sm mb-3" data-process-search="<?= htmlspecialchars($processSearchText) ?>">
                    <div class="audit-group-header">
                        <span class="audit-icon bg-light-<?= htmlspecialchars($group['tone']) ?>">
                            <i class="ti <?= htmlspecialchars($group['icon']) ?> f-22 text-<?= htmlspecialchars($group['tone']) ?>"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                <h5 class="mb-0"><?= htmlspecialchars($group['name']) ?></h5>
                                <span class="badge bg-light-warning"><?= number_format($group['file_count']) ?> unmatched</span>
                            </div>
                            <p class="mb-0">No database record points to these uploaded files.</p>
                        </div>
                    </div>

                    <div class="audit-table-wrap">
                        <table class="table table-hover audit-table mb-0">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Folder</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th>Reason</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$group['files']) { ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted py-4">No unmatched files found for this process.</td>
                                    </tr>
                                <?php } ?>
                                <?php foreach ($group['files'] as $file) { ?>
                                    <tr class="audit-file-row" data-search="<?= htmlspecialchars(strtolower($group['name'] . ' ' . $file['name'] . ' ' . $file['relative_path'] . ' ' . $file['extension'])) ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ti <?= htmlspecialchars($file['icon']) ?> f-20 text-muted"></i>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($file['name']) ?></div>
                                                    <code class="audit-path"><?= htmlspecialchars($file['relative_path']) ?></code>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code class="audit-path"><?= htmlspecialchars($file['folder']) ?></code></td>
                                        <td><span class="badge bg-light-secondary"><?= htmlspecialchars($file['extension']) ?></span></td>
                                        <td><?= htmlspecialchars($file['size']) ?></td>
                                        <td><?= htmlspecialchars($file['modified_label']) ?></td>
                                        <td><span class="badge bg-light-warning">No DB match</span></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex gap-1">
                                                <a href="<?= htmlspecialchars($file['href']) ?>" target="_blank" class="btn btn-icon btn-sm btn-light-primary" title="View file" aria-label="View <?= htmlspecialchars($file['name']) ?>">
                                                    <i class="ti ti-eye"></i>
                                                </a>
                                                <a href="<?= htmlspecialchars($file['href']) ?>" download class="btn btn-icon btn-sm btn-light-secondary" title="Download file" aria-label="Download <?= htmlspecialchars($file['name']) ?>">
                                                    <i class="ti ti-download"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="audit-pagination" data-page="1">
                        <div class="text-muted small audit-page-info"></div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm audit-page-size" aria-label="<?= htmlspecialchars($group['name']) ?> files per page">
                                <option value="10">10 per page</option>
                                <option value="25" selected>25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="999999">All</option>
                            </select>
                            <div class="btn-group" role="group" aria-label="<?= htmlspecialchars($group['name']) ?> unmatched file pagination">
                                <button type="button" class="btn btn-sm btn-light-secondary audit-prev-page">
                                    <i class="ti ti-chevron-left"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light-secondary audit-next-page">
                                    <i class="ti ti-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div id="auditEmpty" class="card shadow-sm d-none mt-3">
            <div class="card-body text-center py-5">
                <i class="ti ti-search-off f-32 text-muted mb-2"></i>
                <h5 class="mb-1">No matching unmatched files</h5>
                <p class="text-muted mb-0">Try another process, file name, folder, or extension.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('auditSearch');
        const groups = Array.from(document.querySelectorAll('.audit-group'));
        const emptyState = document.getElementById('auditEmpty');

        function renderGroup(group, resetPage) {
            const query = searchInput.value.trim().toLowerCase();
            const rows = Array.from(group.querySelectorAll('.audit-file-row'));
            const pagination = group.querySelector('.audit-pagination');
            const pageInfo = group.querySelector('.audit-page-info');
            const pageSizeSelect = group.querySelector('.audit-page-size');
            const prevButton = group.querySelector('.audit-prev-page');
            const nextButton = group.querySelector('.audit-next-page');
            const processMatches = group.dataset.processSearch.includes(query);
            const matchingRows = rows.filter(row => row.dataset.search.includes(query) || processMatches);
            const pageSize = Number(pageSizeSelect.value);
            const totalPages = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            let currentPage = resetPage ? 1 : Number(pagination.dataset.page || '1');

            currentPage = Math.min(Math.max(currentPage, 1), totalPages);
            pagination.dataset.page = String(currentPage);
            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach(row => row.classList.add('d-none'));
            matchingRows.slice(startIndex, endIndex).forEach(row => row.classList.remove('d-none'));

            const showGroup = processMatches || matchingRows.length > 0 || rows.length === 0 && query === '';
            group.classList.toggle('d-none', !showGroup);
            pagination.classList.toggle('d-none', matchingRows.length === 0);

            const firstItem = matchingRows.length === 0 ? 0 : startIndex + 1;
            const lastItem = Math.min(endIndex, matchingRows.length);
            pageInfo.textContent = 'Showing ' + firstItem + '-' + lastItem + ' of ' + matchingRows.length + ' files';
            prevButton.disabled = currentPage <= 1;
            nextButton.disabled = currentPage >= totalPages;

            return showGroup;
        }

        function renderAudit(resetPage) {
            let visibleGroups = 0;
            groups.forEach(group => {
                if (renderGroup(group, resetPage)) {
                    visibleGroups++;
                }
            });
            emptyState.classList.toggle('d-none', visibleGroups > 0);
        }

        searchInput.addEventListener('input', function () {
            renderAudit(true);
        });

        groups.forEach(group => {
            const pagination = group.querySelector('.audit-pagination');
            group.querySelector('.audit-page-size').addEventListener('change', function () {
                renderGroup(group, true);
            });
            group.querySelector('.audit-prev-page').addEventListener('click', function () {
                pagination.dataset.page = String(Number(pagination.dataset.page || '1') - 1);
                renderGroup(group, false);
            });
            group.querySelector('.audit-next-page').addEventListener('click', function () {
                pagination.dataset.page = String(Number(pagination.dataset.page || '1') + 1);
                renderGroup(group, false);
            });
        });

        renderAudit(true);
    });
</script>

<?php
include 'footer.php';
?>
