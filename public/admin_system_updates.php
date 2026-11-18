<?php
require '../init.php';

if (($_SESSION['position'] ?? '') !== 'Administrator') {
    http_response_code(403);
    exit('Administrator access is required.');
}

if (empty($_SESSION['system_updates_csrf'])) {
    $_SESSION['system_updates_csrf'] = bin2hex(random_bytes(32));
}

$flash = $_SESSION['system_updates_flash'] ?? null;
unset($_SESSION['system_updates_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['system_updates_csrf'], $token)) {
        $_SESSION['system_updates_flash'] = ['type' => 'danger', 'message' => 'Your session token expired. Please try again.'];
        header('Location: admin_system_updates');
        exit;
    }

    $action = $_POST['action'] ?? 'create';
    try {
        if ($action === 'create') {
            $title = trim($_POST['title'] ?? '');
            $message = trim($_POST['message'] ?? '');
            $severity = $_POST['severity'] ?? 'info';
            $audience = $_POST['audience'] ?? 'all';
            $departments = array_values(array_unique(array_filter(array_map('trim', (array)($_POST['departments'] ?? [])))));
            $expiresAt = trim($_POST['expires_at'] ?? '');

            if ($title === '' || $message === '') {
                throw new RuntimeException('Title and update details are required.');
            }
            if (mb_strlen($title) > 180) {
                throw new RuntimeException('Title must be 180 characters or fewer.');
            }
            if (!in_array($severity, ['info', 'success', 'warning', 'critical'], true)) {
                throw new RuntimeException('Invalid update type.');
            }
            if ($audience === 'departments' && !$departments) {
                throw new RuntimeException('Select at least one department.');
            }

            $expiresSql = null;
            if ($expiresAt !== '') {
                $expiresDate = DateTime::createFromFormat('Y-m-d\TH:i', $expiresAt);
                if (!$expiresDate) {
                    throw new RuntimeException('Invalid expiration date.');
                }
                $expiresSql = $expiresDate->format('Y-m-d H:i:s');
                if ($expiresDate <= new DateTime()) {
                    throw new RuntimeException('Expiration must be in the future.');
                }
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO system_updates (title, message, severity, created_by, expires_at) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$title, $message, $severity, $_SESSION['user_id'], $expiresSql]);
            $updateId = (int)$pdo->lastInsertId();

            if ($audience === 'departments') {
                $validStmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_active = 1 AND UPPER(TRIM(department)) = UPPER(TRIM(?))");
                $targetStmt = $pdo->prepare('INSERT INTO system_update_departments (update_id, department) VALUES (?, ?)');
                foreach ($departments as $department) {
                    $validStmt->execute([$department]);
                    if (!(int)$validStmt->fetchColumn()) {
                        throw new RuntimeException('One of the selected departments is no longer valid.');
                    }
                    $targetStmt->execute([$updateId, $department]);
                }
            }

            $pdo->commit();
            $_SESSION['system_updates_flash'] = ['type' => 'success', 'message' => 'System update published successfully.'];
        } elseif ($action === 'toggle') {
            $updateId = filter_input(INPUT_POST, 'update_id', FILTER_VALIDATE_INT);
            if (!$updateId) {
                throw new RuntimeException('Invalid system update.');
            }
            $stmt = $pdo->prepare('UPDATE system_updates SET is_active = IF(is_active = 1, 0, 1) WHERE update_id = ?');
            $stmt->execute([$updateId]);
            $_SESSION['system_updates_flash'] = ['type' => 'success', 'message' => 'System update status changed.'];
        } else {
            throw new RuntimeException('Unsupported action.');
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['system_updates_flash'] = ['type' => 'danger', 'message' => $e->getMessage()];
    }

    header('Location: admin_system_updates');
    exit;
}

$departments = $pdo->query("SELECT DISTINCT TRIM(department) AS department FROM users WHERE is_active = 1 AND department IS NOT NULL AND TRIM(department) <> '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
$updates = $pdo->query("SELECT su.*, u.full_name AS creator,
        GROUP_CONCAT(sud.department ORDER BY sud.department SEPARATOR ', ') AS departments,
        (SELECT COUNT(*) FROM system_update_reads sur WHERE sur.update_id = su.update_id) AS read_count
    FROM system_updates su
    LEFT JOIN users u ON u.user_id = su.created_by
    LEFT JOIN system_update_departments sud ON sud.update_id = su.update_id
    GROUP BY su.update_id
    ORDER BY su.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'navigation.php';
?>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block">
                <div class="page-header-title"><h2 class="mb-0">System Updates</h2></div>
            </div>
        </div>

        <?php if ($flash): ?>
            <div class="alert alert-<?= htmlspecialchars($flash['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-xl-4">
                <div class="card shadow-sm">
                    <div class="card-header"><h5 class="mb-0">Publish an Update</h5></div>
                    <div class="card-body">
                        <form method="post" id="systemUpdateForm">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['system_updates_csrf']) ?>">
                            <input type="hidden" name="action" value="create">
                            <div class="mb-3">
                                <label class="form-label" for="updateTitle">Title</label>
                                <input class="form-control" id="updateTitle" name="title" maxlength="180" required placeholder="What changed?">
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="updateMessage">Update details</label>
                                <textarea class="form-control" id="updateMessage" name="message" rows="6" required placeholder="Describe the new feature, fix, or action users should take."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="updateSeverity">Type</label>
                                <select class="form-select" id="updateSeverity" name="severity">
                                    <option value="info">Information</option>
                                    <option value="success">New feature</option>
                                    <option value="warning">Important notice</option>
                                    <option value="critical">Critical update</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label d-block">Audience</label>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="radio" name="audience" id="audienceAll" value="all" checked>
                                    <label class="form-check-label" for="audienceAll">Everyone</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="audience" id="audienceDepartments" value="departments">
                                    <label class="form-check-label" for="audienceDepartments">Selected departments</label>
                                </div>
                            </div>
                            <div class="mb-3 d-none" id="departmentPicker">
                                <label class="form-label">Departments</label>
                                <div class="border rounded p-3" style="max-height: 240px; overflow-y: auto;">
                                    <?php foreach ($departments as $index => $department): ?>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input department-checkbox" type="checkbox"
                                                name="departments[]"
                                                id="department<?= (int)$index ?>"
                                                value="<?= htmlspecialchars($department) ?>">
                                            <label class="form-check-label" for="department<?= (int)$index ?>">
                                                <?= htmlspecialchars($department) ?>
                                            </label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <small class="text-muted">Select one or more departments.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label" for="expiresAt">Expires (optional)</label>
                                <input class="form-control" type="datetime-local" id="expiresAt" name="expires_at">
                            </div>
                            <button class="btn btn-primary w-100" type="submit"><i class="ti ti-send me-1"></i> Publish update</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-xl-8">
                <div class="card shadow-sm">
                    <div class="card-header"><h5 class="mb-0">Published Updates</h5></div>
                    <div class="card-body">
                        <?php if (!$updates): ?>
                            <div class="text-center text-muted py-5">No system updates have been published.</div>
                        <?php else: ?>
                            <?php foreach ($updates as $update): ?>
                                <?php
                                $badgeClass = ['info' => 'primary', 'success' => 'success', 'warning' => 'warning', 'critical' => 'danger'][$update['severity']] ?? 'primary';
                                $expired = $update['expires_at'] && strtotime($update['expires_at']) <= time();
                                ?>
                                <div class="border rounded p-3 mb-3 <?= !$update['is_active'] || $expired ? 'bg-light opacity-75' : '' ?>">
                                    <div class="d-flex flex-wrap justify-content-between gap-2">
                                        <div>
                                            <span class="badge bg-<?= $badgeClass ?> me-1"><?= htmlspecialchars(ucfirst($update['severity'])) ?></span>
                                            <?php if (!$update['is_active']): ?><span class="badge bg-secondary">Archived</span><?php endif; ?>
                                            <?php if ($expired): ?><span class="badge bg-dark">Expired</span><?php endif; ?>
                                            <h5 class="mt-2 mb-1"><?= htmlspecialchars($update['title']) ?></h5>
                                        </div>
                                        <form method="post">
                                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['system_updates_csrf']) ?>">
                                            <input type="hidden" name="action" value="toggle">
                                            <input type="hidden" name="update_id" value="<?= (int)$update['update_id'] ?>">
                                            <button class="btn btn-sm btn-outline-<?= $update['is_active'] ? 'danger' : 'success' ?>" type="submit">
                                                <?= $update['is_active'] ? 'Archive' : 'Restore' ?>
                                            </button>
                                        </form>
                                    </div>
                                    <p class="mb-2" style="white-space: pre-line"><?= htmlspecialchars($update['message']) ?></p>
                                    <div class="small text-muted">
                                        Audience: <?= $update['departments'] ? htmlspecialchars($update['departments']) : 'Everyone' ?>
                                        &middot; Published by <?= htmlspecialchars($update['creator'] ?? 'Administrator') ?>
                                        &middot; <?= date('M d, Y g:i A', strtotime($update['published_at'])) ?>
                                        &middot; <?= (int)$update['read_count'] ?> read
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const audienceInputs = document.querySelectorAll('input[name="audience"]');
    const picker = document.getElementById('departmentPicker');
    const departmentCheckboxes = document.querySelectorAll('.department-checkbox');
    function updateAudience() {
        const targeted = document.getElementById('audienceDepartments').checked;
        picker.classList.toggle('d-none', !targeted);
        if (!targeted) departmentCheckboxes.forEach(checkbox => checkbox.checked = false);
    }
    audienceInputs.forEach(input => input.addEventListener('change', updateAudience));
    document.getElementById('systemUpdateForm').addEventListener('submit', function (event) {
        if (!document.getElementById('audienceDepartments').checked) return;
        if (Array.from(departmentCheckboxes).some(checkbox => checkbox.checked)) return;
        event.preventDefault();
        alert('Select at least one department.');
    });
    updateAudience();
});
</script>

<?php include 'footer.php'; ?>
