<?php
require '../init.php';

$userId = (int)($_SESSION['user_id'] ?? 0);
$filter = strtolower(trim($_GET['filter'] ?? ''));
$showUnreadOnly = $filter === 'unread';
$notificationWhere = "
    WHERE nr.user_id = :user_id
        OR n.user_id = :user_id
";

if ($showUnreadOnly) {
    $notificationWhere = "WHERE (nr.user_id = :user_id OR n.user_id = :user_id)
        AND COALESCE(nr.is_read, n.is_read, 0) = 0";
}

$stmt = $pdo->prepare("
    SELECT DISTINCT
        n.id,
        n.title,
        n.message,
        n.type,
        n.link,
        COALESCE(nr.is_read, n.is_read, 0) AS is_read,
        n.created_at
    FROM notifications n
    LEFT JOIN notification_recipients nr
        ON nr.notification_id = n.id
        AND nr.user_id = :user_id
    $notificationWhere
    ORDER BY n.created_at DESC
");
$stmt->execute([':user_id' => $userId]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM notifications n
    LEFT JOIN notification_recipients nr
        ON nr.notification_id = n.id
        AND nr.user_id = :user_id
    WHERE (nr.user_id = :user_id OR n.user_id = :user_id)
        AND COALESCE(nr.is_read, n.is_read, 0) = 0
");
$unreadStmt->execute([':user_id' => $userId]);
$unreadCount = (int)$unreadStmt->fetchColumn();

function notification_time_label($datetime)
{
    $time = strtotime($datetime);
    if (!$time) return 'Just now';

    $diff = time() - $time;
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hour ago';
    return date('M d, Y g:i A', $time);
}

function notification_badge_class($type)
{
    return [
        'success' => 'success',
        'warning' => 'warning',
        'error' => 'danger',
        'danger' => 'danger',
        'info' => 'primary',
    ][$type] ?? 'primary';
}

include 'header.php';
include 'navigation.php';
?>

<style>
.notification-list-card {
    border-left: 4px solid var(--bs-primary);
    scroll-margin-top: 95px;
}

.notification-list-card.type-success { border-left-color: var(--bs-success); }
.notification-list-card.type-warning { border-left-color: var(--bs-warning); }
.notification-list-card.type-error,
.notification-list-card.type-danger { border-left-color: var(--bs-danger); }
.notification-list-card.read { opacity: .72; }
.notification-list-card.unread { background: rgba(var(--bs-primary-rgb), .035); }
</style>

<div class="pc-container">
    <div class="pc-content">
        <div class="page-header">
            <div class="page-block d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="page-header-title">
                    <h2 class="mb-0"><?= $showUnreadOnly ? 'Unread Notifications' : 'Notifications' ?></h2>
                </div>
                <?php if ($unreadCount > 0): ?>
                    <button class="btn btn-outline-primary btn-sm" id="markAllNotificationsReadPage">Mark all as read</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-xl-9">
                <div class="d-flex flex-wrap gap-2 mb-3">
                    <a href="notifications" class="btn btn-sm <?= $showUnreadOnly ? 'btn-outline-secondary' : 'btn-primary' ?>">All</a>
                    <a href="notifications?filter=unread" class="btn btn-sm <?= $showUnreadOnly ? 'btn-primary' : 'btn-outline-secondary' ?>">Unread<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a>
                </div>

                <?php if (!$notifications): ?>
                    <div class="card shadow-sm" id="notificationEmptyState">
                        <div class="card-body text-center text-muted py-5">
                            <i class="ti ti-bell f-36 d-block mb-2"></i><?= $showUnreadOnly ? 'No unread notifications.' : 'No notifications yet.' ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php foreach ($notifications as $notification): ?>
                    <?php
                    $isRead = (int)$notification['is_read'] === 1;
                    $type = $notification['type'] ?: 'info';
                    $badgeClass = notification_badge_class($type);
                    ?>
                    <article id="notification-<?= (int)$notification['id'] ?>"
                        class="card shadow-sm notification-list-card type-<?= htmlspecialchars($type) ?> <?= $isRead ? 'read' : 'unread' ?>"
                        data-notification-id="<?= (int)$notification['id'] ?>"
                        data-link="<?= htmlspecialchars($notification['link'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between gap-3">
                                <div>
                                    <span class="badge bg-<?= $badgeClass ?> mb-2"><?= htmlspecialchars(ucfirst($type)) ?></span>
                                    <h4 class="mb-1"><?= htmlspecialchars($notification['title'] ?: 'Notification') ?></h4>
                                </div>
                                <?php if (!$isRead): ?><span class="badge bg-primary unread-label">New</span><?php endif; ?>
                            </div>
                            <p class="mt-3 mb-3" style="white-space: pre-line"><?= htmlspecialchars($notification['message'] ?? '') ?></p>
                            <div class="d-flex flex-wrap align-items-center gap-3 small text-muted">
                                <span><?= notification_time_label($notification['created_at']) ?></span>
                                <?php if (!empty($notification['link'])): ?>
                                    <a href="<?= htmlspecialchars($notification['link'], ENT_QUOTES, 'UTF-8') ?>" class="notification-open-link">Open related item</a>
                                <?php endif; ?>
                            </div>
                            <?php if (!$isRead): ?>
                                <button class="btn btn-link btn-sm px-0 mt-2 mark-notification-read" type="button">Mark as read</button>
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
    const showUnreadOnly = <?= $showUnreadOnly ? 'true' : 'false' ?>;

    function showUnreadEmptyStateIfNeeded() {
        if (!showUnreadOnly || document.querySelector('.notification-list-card')) return;
        const listColumn = document.querySelector('.col-xl-9');
        if (!listColumn || document.getElementById('notificationEmptyState')) return;
        listColumn.insertAdjacentHTML('beforeend', `
            <div class="card shadow-sm" id="notificationEmptyState">
                <div class="card-body text-center text-muted py-5">
                    <i class="ti ti-bell f-36 d-block mb-2"></i>No unread notifications.
                </div>
            </div>
        `);
    }

    function markRead(id, card) {
        return fetch('data/update_mark_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams({id: id})
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Unable to mark notification as read.');
            if (card) {
                if (showUnreadOnly) {
                    card.remove();
                    showUnreadEmptyStateIfNeeded();
                    return;
                }
                card.classList.remove('unread');
                card.classList.add('read');
                card.querySelector('.unread-label')?.remove();
                card.querySelector('.mark-notification-read')?.remove();
            }
        });
    }

    document.querySelectorAll('.mark-notification-read').forEach(button => {
        button.addEventListener('click', function () {
            const card = this.closest('.notification-list-card');
            markRead(card.dataset.notificationId, card).catch(error => alert(error.message));
        });
    });

    document.querySelectorAll('.notification-open-link').forEach(link => {
        link.addEventListener('click', function () {
            const card = this.closest('.notification-list-card');
            if (card?.classList.contains('unread')) {
                markRead(card.dataset.notificationId, card).catch(() => {});
            }
        });
    });

    document.getElementById('markAllNotificationsReadPage')?.addEventListener('click', function () {
        fetch('data/update_mark_all_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json'},
            body: new URLSearchParams()
        }).then(response => response.json()).then(result => {
            if (!result.success) throw new Error(result.message || 'Unable to mark notifications as read.');
            document.querySelectorAll('.notification-list-card').forEach(card => {
                if (showUnreadOnly) {
                    card.remove();
                    return;
                }
                card.classList.remove('unread');
                card.classList.add('read');
                card.querySelector('.unread-label')?.remove();
                card.querySelector('.mark-notification-read')?.remove();
            });
            this.remove();
            showUnreadEmptyStateIfNeeded();
        }).catch(error => alert(error.message));
    });

    const hashCard = window.location.hash ? document.querySelector(window.location.hash) : null;
    if (hashCard?.classList.contains('unread')) {
        markRead(hashCard.dataset.notificationId, hashCard).catch(() => {});
    }
});
</script>

<?php include 'footer.php'; ?>
