<?php
require '../init.php';

$stmt = $pdo->prepare("SELECT su.*, u.full_name AS creator,
        GROUP_CONCAT(sud.department ORDER BY sud.department SEPARATOR ', ') AS departments,
        MAX(CASE WHEN sur.user_id = :read_user_id THEN 1 ELSE 0 END) AS is_read
    FROM system_updates su
    LEFT JOIN users u ON u.user_id = su.created_by
    LEFT JOIN system_update_departments sud ON sud.update_id = su.update_id
    LEFT JOIN system_update_reads sur ON sur.update_id = su.update_id
    WHERE su.is_active = 1
      AND su.published_at <= NOW()
      AND (su.expires_at IS NULL OR su.expires_at > NOW())
      AND (
          NOT EXISTS (SELECT 1 FROM system_update_departments all_sud WHERE all_sud.update_id = su.update_id)
          OR EXISTS (
              SELECT 1 FROM system_update_departments own_sud
              WHERE own_sud.update_id = su.update_id
                AND UPPER(TRIM(own_sud.department)) = UPPER(TRIM(:department))
          )
      )
    GROUP BY su.update_id
    ORDER BY su.published_at DESC");
$stmt->execute([
    ':read_user_id' => $_SESSION['user_id'],
    ':department' => $_SESSION['department'] ?? ''
]);
$updates = $stmt->fetchAll(PDO::FETCH_ASSOC);

include 'header.php';
include 'navigation.php';
?>

<style>
.system-update-card { border-left: 4px solid var(--bs-primary); scroll-margin-top: 95px; }
.system-update-card.update-success { border-left-color: var(--bs-success); }
.system-update-card.update-warning { border-left-color: var(--bs-warning); }
.system-update-card.update-critical { border-left-color: var(--bs-danger); }
.system-update-card.unread { background: rgba(var(--bs-primary-rgb), .035); }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="page-header-title"><h2 class="mb-0">System Updates</h2></div>
                <?php if (array_filter($updates, fn($update) => !(int)$update['is_read'])): ?>
                    <button class="btn btn-outline-primary btn-sm" id="markAllUpdatesRead">Mark all as read</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9">
                <?php if (!$updates): ?>
                    <div class="card shadow-sm"><div class="card-body text-center text-muted py-5">
                        <i class="ti ti-speakerphone f-36 d-block mb-2"></i>No system updates are available for you.
                    </div></div>
                <?php endif; ?>

                <?php foreach ($updates as $update): ?>
                    <?php
                    $badgeClass = ['info' => 'primary', 'success' => 'success', 'warning' => 'warning', 'critical' => 'danger'][$update['severity']] ?? 'primary';
                    ?>
                    <article id="update-<?= (int)$update['update_id'] ?>" class="card shadow-sm system-update-card update-<?= htmlspecialchars($update['severity']) ?> <?= !(int)$update['is_read'] ? 'unread' : '' ?>" data-update-id="<?= (int)$update['update_id'] ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <span class="badge bg-<?= $badgeClass ?> mb-2"><?= htmlspecialchars(ucfirst($update['severity'])) ?></span>
                                    <h4 class="mb-1"><?= htmlspecialchars($update['title']) ?></h4>
                                </div>
                                <?php if (!(int)$update['is_read']): ?><span class="badge bg-primary unread-label">New</span><?php endif; ?>
                            </div>
                            <p class="mt-3 mb-3" style="white-space: pre-line"><?= htmlspecialchars($update['message']) ?></p>
                            <div class="small text-muted">
                                <?= date('M d, Y g:i A', strtotime($update['published_at'])) ?>
                                &middot; <?= $update['departments'] ? 'For ' . htmlspecialchars($update['departments']) : 'For everyone' ?>
                            </div>
                            <?php if (!(int)$update['is_read']): ?>
                                <button class="btn btn-link btn-sm px-0 mt-2 mark-update-read" type="button">Mark as read</button>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    function markRead(id, card) {
        return fetch('data/mark_system_update_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id: id})
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Unable to mark update as read.');
            if (card) {
                card.classList.remove('unread');
                card.querySelector('.unread-label')?.remove();
                card.querySelector('.mark-update-read')?.remove();
            }
        });
    }

    document.querySelectorAll('.mark-update-read').forEach(button => {
        button.addEventListener('click', function () {
            const card = this.closest('.system-update-card');
            markRead(card.dataset.updateId, card).catch(error => alert(error.message));
        });
    });

    document.getElementById('markAllUpdatesRead')?.addEventListener('click', function () {
        fetch('data/mark_system_update_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({all: '1'})
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Unable to mark updates as read.');
            document.querySelectorAll('.system-update-card').forEach(card => {
                card.classList.remove('unread');
                card.querySelector('.unread-label')?.remove();
                card.querySelector('.mark-update-read')?.remove();
            });
            this.remove();
        }).catch(error => alert(error.message));
    });

    const hashCard = window.location.hash ? document.querySelector(window.location.hash) : null;
    if (hashCard?.classList.contains('unread')) {
        markRead(hashCard.dataset.updateId, hashCard).catch(() => {});
    }
});
</script>

<?php include 'footer.php'; ?>
