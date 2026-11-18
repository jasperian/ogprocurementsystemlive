<?php
require '../init.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    header('Location: dashboard');
    exit;
}

include 'header.php';
include 'navigation.php';

$uploadGroups = [
    [
        'name' => 'Cash Advance',
        'icon' => 'ti-cash',
        'tone' => 'danger',
        'summary' => 'CA request and update attachments saved directly in the root uploads folder.',
        'directories' => [
            [
                'path' => 'uploads/',
                'recursive' => false,
                'match' => '/^CA(?:_|REQ_|UPD_)/i',
            ],
        ],
    ],
    [
        'name' => 'Liquidation / RFP / CA',
        'icon' => 'ti-receipt-2',
        'tone' => 'secondary',
        'summary' => 'Liquidation, RFP, and cash advance liquidation attachments.',
        'directories' => [
            [
                'path' => 'uploads/liquidations/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Purchase Order',
        'icon' => 'ti-file-invoice',
        'tone' => 'success',
        'summary' => 'PO documents and supplier/item selection attachments.',
        'directories' => [
            [
                'path' => 'uploads/po/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Budget',
        'icon' => 'ti-chart-pie',
        'tone' => 'primary',
        'summary' => 'Budget adjustment uploads and budget module attachments.',
        'directories' => [
            [
                'path' => 'uploads/budget_adjustments/',
                'recursive' => true,
            ],
            [
                'path' => 'public/budget/uploads/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Canvass',
        'icon' => 'ti-clipboard-list',
        'tone' => 'warning',
        'summary' => 'Proposal documents uploaded during canvassing.',
        'directories' => [
            [
                'path' => 'public/data/uploads/proposals/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Item Master',
        'icon' => 'ti-package',
        'tone' => 'info',
        'summary' => 'Item images used by the item master list.',
        'directories' => [
            [
                'path' => 'public/data/uploads/items/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Supplier Profile',
        'icon' => 'ti-files',
        'tone' => 'dark',
        'summary' => 'Supplier accreditation profile documents from registration and later profile file updates.',
        'directories' => [
            [
                'path' => 'public/uploads/',
                'recursive' => true,
            ],
        ],
    ],
    [
        'name' => 'Purchase Requisition',
        'icon' => 'ti-file-description',
        'tone' => 'info',
        'summary' => 'Purchase requisition uploaded documents saved by the PR creation endpoint.',
        'directories' => [
            [
                'path' => 'public/data/uploads/',
                'recursive' => false,
            ],
        ],
    ],
    [
        'name' => 'Test / Temporary Uploads',
        'icon' => 'ti-flask',
        'tone' => 'secondary',
        'summary' => 'Files saved in test upload folders.',
        'directories' => [
            [
                'path' => 'uploads/test/',
                'recursive' => true,
            ],
        ],
    ],
];

function uploadRootPath(): string
{
    return dirname(__DIR__);
}

function uploadAbsolutePath(string $relativePath): string
{
    return uploadRootPath() . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($relativePath, '/\\'));
}

function uploadPublicHref(string $relativePath): string
{
    $relativePath = str_replace('\\', '/', $relativePath);
    if (str_starts_with($relativePath, 'public/')) {
        return substr($relativePath, strlen('public/'));
    }

    return '../' . $relativePath;
}

function formatUploadSize(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $size = (float)$bytes;
    $unitIndex = 0;

    while ($size >= 1024 && $unitIndex < count($units) - 1) {
        $size /= 1024;
        $unitIndex++;
    }

    return number_format($size, $unitIndex === 0 ? 0 : 2) . ' ' . $units[$unitIndex];
}

function uploadFileIcon(string $extension): string
{
    return match (strtolower($extension)) {
        'pdf' => 'ti-file-type-pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp' => 'ti-photo',
        'doc', 'docx' => 'ti-file-type-doc',
        'xls', 'xlsx', 'csv' => 'ti-file-spreadsheet',
        default => 'ti-file',
    };
}

function collectUploadFiles(array $directory): array
{
    $baseRelative = trim($directory['path'], '/\\') . '/';
    $baseAbsolute = uploadAbsolutePath($baseRelative);
    $files = [];

    if (!is_dir($baseAbsolute)) {
        return $files;
    }

    if (!empty($directory['recursive'])) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($baseAbsolute, FilesystemIterator::SKIP_DOTS)
        );
    } else {
        $iterator = new IteratorIterator(new DirectoryIterator($baseAbsolute));
    }

    foreach ($iterator as $fileInfo) {
        if (!$fileInfo->isFile()) {
            continue;
        }

        $filename = $fileInfo->getFilename();
        if (!empty($directory['match']) && !preg_match($directory['match'], $filename)) {
            continue;
        }

        $absolutePath = $fileInfo->getPathname();
        $relativePath = str_replace('\\', '/', substr($absolutePath, strlen(uploadRootPath()) + 1));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        $files[] = [
            'name' => $filename,
            'relative_path' => $relativePath,
            'folder' => dirname($relativePath) === '.' ? '' : dirname($relativePath),
            'href' => uploadPublicHref($relativePath),
            'extension' => $extension !== '' ? strtoupper($extension) : 'FILE',
            'size_bytes' => $fileInfo->getSize(),
            'size' => formatUploadSize($fileInfo->getSize()),
            'modified_at' => $fileInfo->getMTime(),
            'modified_label' => date('M d, Y h:i A', $fileInfo->getMTime()),
            'icon' => uploadFileIcon($extension),
        ];
    }

    usort($files, fn($a, $b) => $b['modified_at'] <=> $a['modified_at']);

    return $files;
}

$groupsWithFiles = [];
$totalFiles = 0;
$totalBytes = 0;
$latestModifiedAt = 0;

foreach ($uploadGroups as $group) {
    $groupFiles = [];
    foreach ($group['directories'] as $directory) {
        $groupFiles = array_merge($groupFiles, collectUploadFiles($directory));
    }

    usort($groupFiles, fn($a, $b) => $b['modified_at'] <=> $a['modified_at']);

    foreach ($groupFiles as $file) {
        $totalFiles++;
        $totalBytes += $file['size_bytes'];
        $latestModifiedAt = max($latestModifiedAt, $file['modified_at']);
    }

    $group['files'] = $groupFiles;
    $group['file_count'] = count($groupFiles);
    $groupsWithFiles[] = $group;
}
?>

<style>
    .uploaded-files-wrap {
        --upload-border: #dbe4ef;
        --upload-muted: #667085;
    }

    .upload-summary,
    .upload-group,
    .upload-stat {
        background: #fff;
        border: 1px solid var(--upload-border);
        border-radius: 8px;
    }

    .upload-stat {
        padding: 16px;
    }

    .upload-group-header {
        align-items: flex-start;
        border-bottom: 1px solid var(--upload-border);
        display: flex;
        gap: 14px;
        padding: 18px;
    }

    .upload-icon {
        align-items: center;
        border-radius: 8px;
        display: inline-flex;
        flex: 0 0 38px;
        height: 38px;
        justify-content: center;
        width: 38px;
    }

    .upload-table-wrap {
        overflow-x: auto;
    }

    .upload-table {
        min-width: 920px;
    }

    .upload-table td,
    .upload-table th {
        vertical-align: middle;
    }

    .upload-path {
        color: #344054;
        font-size: 0.82rem;
        white-space: normal;
        word-break: break-word;
    }

    .upload-group p {
        color: var(--upload-muted);
    }

    .upload-filter {
        max-width: 360px;
    }

    .upload-pagination {
        align-items: center;
        border-top: 1px solid var(--upload-border);
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        justify-content: space-between;
        padding: 12px 18px;
    }

    .upload-page-size {
        max-width: 170px;
    }

    @media (max-width: 767.98px) {
        .upload-group-header {
            flex-direction: column;
        }

        .upload-filter {
            max-width: none;
            width: 100%;
        }
    }
</style>

<div class="pc-container">
    <div class="pc-content uploaded-files-wrap">
        <div class="page-header">
            <div class="page-block">
                <div class="row align-items-center">
                    <div class="col-md-12">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="page-header-title">
                                <h2 class="mb-0">Uploaded Files by Process</h2>
                            </div>
                            <div class="input-group upload-filter">
                                <span class="input-group-text"><i class="ti ti-search"></i></span>
                                <input type="search" id="uploadSearch" class="form-control" placeholder="Search file or process">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12">
                <div class="upload-summary p-3 p-md-4">
                    <div class="row g-3 align-items-center">
                        <div class="col-lg-8">
                            <span class="badge bg-light-primary mb-2">Upload inventory</span>
                            <h4 class="mb-2">All known upload folders grouped by process</h4>
                            <p class="mb-0">
                                This page scans only application upload directories and lists the files currently saved for each process.
                            </p>
                        </div>
                        <div class="col-lg-4">
                            <div class="row g-2">
                                <div class="col-4">
                                    <div class="upload-stat">
                                        <div class="text-muted small">Groups</div>
                                        <h4 class="mb-0"><?= count($groupsWithFiles) ?></h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="upload-stat">
                                        <div class="text-muted small">Files</div>
                                        <h4 class="mb-0"><?= number_format($totalFiles) ?></h4>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <div class="upload-stat">
                                        <div class="text-muted small">Size</div>
                                        <h4 class="mb-0"><?= htmlspecialchars(formatUploadSize($totalBytes)) ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="text-muted small mt-2">
                                Latest upload: <?= $latestModifiedAt ? htmlspecialchars(date('M d, Y h:i A', $latestModifiedAt)) : 'No files found' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="uploadGroups">
            <?php foreach ($groupsWithFiles as $group) {
                $processSearchText = strtolower($group['name'] . ' ' . $group['summary']);
            ?>
                <div class="upload-group shadow-sm mb-3" data-process-search="<?= htmlspecialchars($processSearchText) ?>">
                    <div class="upload-group-header">
                        <span class="upload-icon bg-light-<?= htmlspecialchars($group['tone']) ?>">
                            <i class="ti <?= htmlspecialchars($group['icon']) ?> f-22 text-<?= htmlspecialchars($group['tone']) ?>"></i>
                        </span>
                        <div class="flex-grow-1">
                            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-1">
                                <h5 class="mb-0"><?= htmlspecialchars($group['name']) ?></h5>
                                <span class="badge bg-light-secondary"><?= number_format($group['file_count']) ?> files</span>
                            </div>
                            <p class="mb-0"><?= htmlspecialchars($group['summary']) ?></p>
                        </div>
                    </div>

                    <div class="upload-table-wrap">
                        <table class="table table-hover upload-table mb-0">
                            <thead>
                                <tr>
                                    <th>File</th>
                                    <th>Folder</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!$group['files']) { ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted py-4">No uploaded files found for this process.</td>
                                    </tr>
                                <?php } ?>
                                <?php foreach ($group['files'] as $file) { ?>
                                    <tr class="upload-file-row" data-search="<?= htmlspecialchars(strtolower($group['name'] . ' ' . $file['name'] . ' ' . $file['relative_path'] . ' ' . $file['extension'])) ?>">
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <i class="ti <?= htmlspecialchars($file['icon']) ?> f-20 text-muted"></i>
                                                <div>
                                                    <div class="fw-semibold"><?= htmlspecialchars($file['name']) ?></div>
                                                    <code class="upload-path"><?= htmlspecialchars($file['relative_path']) ?></code>
                                                </div>
                                            </div>
                                        </td>
                                        <td><code class="upload-path"><?= htmlspecialchars($file['folder']) ?></code></td>
                                        <td><span class="badge bg-light-secondary"><?= htmlspecialchars($file['extension']) ?></span></td>
                                        <td><?= htmlspecialchars($file['size']) ?></td>
                                        <td><?= htmlspecialchars($file['modified_label']) ?></td>
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
                    <div class="upload-pagination" data-page="1">
                        <div class="text-muted small upload-page-info"></div>
                        <div class="d-flex align-items-center gap-2 flex-wrap">
                            <select class="form-select form-select-sm upload-page-size" aria-label="<?= htmlspecialchars($group['name']) ?> files per page">
                                <option value="10">10 per page</option>
                                <option value="25" selected>25 per page</option>
                                <option value="50">50 per page</option>
                                <option value="999999">All</option>
                            </select>
                            <div class="btn-group" role="group" aria-label="<?= htmlspecialchars($group['name']) ?> file pagination">
                                <button type="button" class="btn btn-sm btn-light-secondary upload-prev-page">
                                    <i class="ti ti-chevron-left"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-light-secondary upload-next-page">
                                    <i class="ti ti-chevron-right"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php } ?>
        </div>

        <div id="uploadEmpty" class="card shadow-sm d-none mt-3">
            <div class="card-body text-center py-5">
                <i class="ti ti-search-off f-32 text-muted mb-2"></i>
                <h5 class="mb-1">No matching uploaded files</h5>
                <p class="text-muted mb-0">Try another process name, file name, folder, or extension.</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const searchInput = document.getElementById('uploadSearch');
        const groups = Array.from(document.querySelectorAll('.upload-group'));
        const emptyState = document.getElementById('uploadEmpty');

        function renderUploadGroup(group, resetPage) {
            const query = searchInput.value.trim().toLowerCase();
            const rows = Array.from(group.querySelectorAll('.upload-file-row'));
            const pagination = group.querySelector('.upload-pagination');
            const pageInfo = group.querySelector('.upload-page-info');
            const pageSizeSelect = group.querySelector('.upload-page-size');
            const prevButton = group.querySelector('.upload-prev-page');
            const nextButton = group.querySelector('.upload-next-page');
            const processMatches = group.dataset.processSearch.includes(query);
            const matchingRows = rows.filter(function (row) {
                return row.dataset.search.includes(query) || processMatches;
            });
            const pageSize = Number(pageSizeSelect.value);
            const totalPages = Math.max(1, Math.ceil(matchingRows.length / pageSize));
            let currentPage = resetPage ? 1 : Number(pagination.dataset.page || '1');

            currentPage = Math.min(Math.max(currentPage, 1), totalPages);
            pagination.dataset.page = String(currentPage);

            const startIndex = (currentPage - 1) * pageSize;
            const endIndex = startIndex + pageSize;

            rows.forEach(function (row) {
                row.classList.add('d-none');
            });

            matchingRows.slice(startIndex, endIndex).forEach(function (row) {
                row.classList.remove('d-none');
            });

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

        function renderUploads(resetPage) {
            let visibleGroups = 0;
            groups.forEach(function (group) {
                if (renderUploadGroup(group, resetPage)) {
                    visibleGroups++;
                }
            });

            emptyState.classList.toggle('d-none', visibleGroups > 0);
        }

        searchInput.addEventListener('input', function () {
            renderUploads(true);
        });

        groups.forEach(function (group) {
            const pagination = group.querySelector('.upload-pagination');
            const pageSizeSelect = group.querySelector('.upload-page-size');
            const prevButton = group.querySelector('.upload-prev-page');
            const nextButton = group.querySelector('.upload-next-page');

            pageSizeSelect.addEventListener('change', function () {
                renderUploadGroup(group, true);
            });
            prevButton.addEventListener('click', function () {
                pagination.dataset.page = String(Number(pagination.dataset.page || '1') - 1);
                renderUploadGroup(group, false);
            });
            nextButton.addEventListener('click', function () {
                pagination.dataset.page = String(Number(pagination.dataset.page || '1') + 1);
                renderUploadGroup(group, false);
            });
        });

        renderUploads(true);
    });
</script>

<?php
include 'footer.php';
?>
