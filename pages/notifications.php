<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$notifications = getAllNotifications($pdo, $userId);
$unreadCount = getUnreadCount($pdo, $userId);

$pageTitle = 'Notifications'; require_once __DIR__ . '/../includes/header.php';
?>

<h1>Notifications</h1>

<?php if (!empty($notifications) && $unreadCount > 0): ?>
    <div class="flex items-center gap-8 mb-16">
        <span class="text-muted"><i class="fas fa-envelope"></i> <?= $unreadCount ?> unread</span>
        <form method="POST" action="/actions/mark_all_read.php" class="inline">
            <?= csrfField() ?>
            <button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-check-double"></i> Mark All Read</button>
        </form>
    </div>
<?php endif; ?>

<?php if (empty($notifications)): ?>
    <div class="empty-state">
        <h3><i class="fas fa-bell"></i> All caught up!</h3>
        <p>Notifications about session requests, badges, and more will appear here.</p>
        <a href="/pages/dashboard.php" class="btn btn-primary btn-sm mt-12">Go to Dashboard</a>
    </div>
<?php else: ?>
    <div class="card card-flush">
        <?php foreach ($notifications as $notif): ?>
            <?php $isUnread = !$notif['is_read']; ?>
            <div class="notif-item <?= $isUnread ? 'unread' : '' ?>" data-id="<?= $notif['id'] ?>">
                <span class="notif-icon">
                    <?php
                    $icons = [
                        'session_request' => 'fa-envelope',
                        'session_accepted' => 'fa-check-circle',
                        'session_declined' => 'fa-times-circle',
                        'session_complete' => 'fa-check',
                        'session_cancelled' => 'fa-exclamation-triangle',
                        'badge_earned' => 'fa-medal',
                    ];
                    $iconClass = $icons[$notif['type']] ?? 'fa-thumbtack';
                    ?>
                    <i class="fas <?= $iconClass ?>"></i>
                </span>
                <div class="notif-content">
                    <?php if ($notif['link']): ?>
                        <p><a href="<?= h($notif['link']) ?>"><?= h($notif['message']) ?></a></p>
                    <?php else: ?>
                        <p><?= h($notif['message']) ?></p>
                    <?php endif; ?>
                    <span class="notif-time"><?= timeAgo($notif['created_at']) ?></span>
                </div>
                <?php if ($isUnread): ?>
                    <button class="btn btn-sm btn-outline mark-read-btn flex-shrink-0"
                            onclick="markRead(this, <?= $notif['id'] ?>)">
                        <i class="fas fa-check"></i> Read
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
function markRead(btn, id) {
    fetch('/actions/mark_read.php?id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                var item = btn.closest('.notif-item');
                item.classList.remove('unread');
                btn.remove();
            }
        })
        .catch(function() {});
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>