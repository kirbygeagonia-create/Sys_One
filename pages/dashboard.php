<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$user = getCurrentUser($pdo);

// Stats — sessions table uses request_id, join with session_requests for user IDs
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    WHERE (sr.requester_id = ? OR sr.teacher_id = ?) AND s.status = 'completed'
");
$stmt->execute([$userId, $userId]);
$completedSessions = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    WHERE sr.teacher_id = ? AND s.status = 'completed'
");
$stmt->execute([$userId]);
$taughtSessions = (int)$stmt->fetchColumn();

$offeredCount = $pdo->prepare("SELECT COUNT(*) FROM user_skills_offered WHERE user_id = ?");
$offeredCount->execute([$userId]);
$offeredSkillsCount = (int)$offeredCount->fetchColumn();

$badges = getUserBadges($pdo, $userId);
$badgesCount = count($badges);

// Upcoming sessions
$stmt = $pdo->prepare("
    SELECT s.*, sr.skill_id, sk.name AS skill_name, sc.icon AS category_icon,
           req.name AS requester_name, t.name AS teacher_name,
           sr.requester_id, sr.teacher_id
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN skill_categories sc ON sk.category_id = sc.id
    JOIN users req ON sr.requester_id = req.id
    JOIN users t ON sr.teacher_id = t.id
    WHERE (sr.requester_id = ? OR sr.teacher_id = ?) AND s.status = 'scheduled'
    ORDER BY s.scheduled_at ASC
    LIMIT 5
");
$stmt->execute([$userId, $userId]);
$upcomingSessions = $stmt->fetchAll();

// Recent badges (latest 4)
$recentBadges = array_slice($badges, 0, 4);

// Pending requests count (as teacher)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM session_requests WHERE teacher_id = ? AND status = 'pending'");
$stmt->execute([$userId]);
$pendingCount = (int)$stmt->fetchColumn();

$pageTitle = 'Dashboard'; require_once __DIR__ . '/../includes/header.php';
?>

<div class="flex justify-between items-center flex-wrap mb-16">
    <h1>Welcome, <?= h($user['name']) ?> <i class="fas fa-hand-peace"></i></h1>
    <a href="/pages/profile.php?id=<?= $userId ?>" class="btn btn-outline btn-sm">View Profile</a>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-value"><i class="fas fa-coins"></i> <?= (int)$user['credits'] ?></div>
        <div class="stat-label">Credits</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $completedSessions ?></div>
        <div class="stat-label">Sessions Done</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $taughtSessions ?></div>
        <div class="stat-label">Taught</div>
    </div>
    <div class="stat-card">
        <div class="stat-value"><?= $badgesCount ?></div>
        <div class="stat-label">Badges Earned</div>
    </div>
</div>

<div class="grid-2">
    <!-- Upcoming Sessions -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-calendar"></i> Upcoming Sessions</h2>
            <a href="/pages/sessions.php" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($upcomingSessions)): ?>
            <div class="empty-state-sm">
                <p>No upcoming sessions.</p>
                <a href="/pages/browse.php" class="btn btn-primary btn-sm mt-8">Find a Teacher</a>
            </div>
        <?php else: ?>
            <?php foreach ($upcomingSessions as $session): ?>
                <?php $isTeacher = $session['teacher_id'] == $userId; ?>
                <div class="flex justify-between items-center border-bottom item-row">
                    <div>
                        <span class="skill-tag"><i class="fas <?= h($session['category_icon']) ?>"></i> <?= h($session['skill_name']) ?></span>
                        <span class="text-sm text-muted ml-8">
                            <?= date('D, M j • g:i A', strtotime($session['scheduled_at'])) ?>
                        </span>
                        <br>
                        <small class="text-muted">
                            <?= $isTeacher ? '<i class="fas fa-graduation-cap"></i> ' . h($session['requester_name']) : '<i class="fas fa-chalkboard-teacher"></i> ' . h($session['teacher_name']) ?>
                        </small>
                    </div>
                    <span class="status-badge status-scheduled">Scheduled</span>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Recent Badges -->
    <div class="card">
        <div class="card-header">
            <h2><i class="fas fa-medal"></i> Recent Badges</h2>
            <a href="/pages/profile.php?id=<?= $userId ?>" class="btn btn-sm btn-outline">View All</a>
        </div>
        <?php if (empty($recentBadges)): ?>
            <div class="empty-state-sm">
                <p>No badges yet. Complete sessions to earn them!</p>
            </div>
        <?php else: ?>
            <div class="flex flex-wrap gap-12">
                <?php foreach ($recentBadges as $badge): ?>
                    <div class="badge-item flex-1 min-w-100">
                        <span class="badge-icon">
                            <?php if ($badge['level'] === 'beginner'): ?><i class="fas fa-medal badge-medal-beginner"></i>
                            <?php elseif ($badge['level'] === 'intermediate'): ?><i class="fas fa-medal badge-medal-intermediate"></i>
                            <?php else: ?><i class="fas fa-medal badge-medal-advanced"></i><?php endif; ?>
                        </span>
                        <span class="badge-name"><?= h($badge['skill_name']) ?></span>
                        <span class="badge-level"><?= ucfirst(h($badge['level'])) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Actions -->
<div class="card mt-24">
    <div class="card-header"><h2><i class="fas fa-bolt"></i> Quick Actions</h2></div>
    <div class="flex gap-12 flex-wrap">
        <a href="/pages/browse.php" class="btn btn-primary"><i class="fas fa-search"></i> Find a Teacher</a>
        <a href="/pages/skills.php" class="btn btn-outline"><i class="fas fa-clipboard-list"></i> Manage My Skills</a>
        <?php if ($pendingCount > 0): ?>
            <a href="/pages/sessions.php" class="btn btn-outline accent-btn">
                <i class="fas fa-envelope"></i> <?= $pendingCount ?> Pending Request<?= $pendingCount > 1 ? 's' : '' ?>
            </a>
        <?php endif; ?>
        <a href="/pages/credits.php" class="btn btn-outline"><i class="fas fa-coins"></i> Credit History</a>
    </div>
</div>

<!-- Skill Recommendations -->
<?php
// Recommend skills based on what others offer in categories the user engages with
$stmt = $pdo->prepare("
    SELECT s.id, s.name AS skill_name, sc.id AS category_id, sc.name AS category_name, sc.icon AS category_icon,
           COUNT(uso.id) AS teacher_count
    FROM skills s
    JOIN skill_categories sc ON s.category_id = sc.id
    JOIN user_skills_offered uso ON uso.skill_id = s.id
    WHERE s.id NOT IN (
        SELECT skill_id FROM user_skills_offered WHERE user_id = ?
        UNION
        SELECT skill_id FROM user_skills_wanted WHERE user_id = ?
    )
    AND (s.category_id IN (
        SELECT s2.category_id FROM user_skills_offered uso2
        JOIN skills s2 ON uso2.skill_id = s2.id WHERE uso2.user_id = ?
    ) OR s.category_id IN (
        SELECT s3.category_id FROM user_skills_wanted usw3
        JOIN skills s3 ON usw3.skill_id = s3.id WHERE usw3.user_id = ?
    ))
    GROUP BY s.id, s.name, sc.name, sc.icon
    ORDER BY teacher_count DESC
    LIMIT 6
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$recommendations = $stmt->fetchAll();
?>

<?php if (!empty($recommendations)): ?>
<div class="card mt-24">
    <div class="card-header">
        <h2><i class="fas fa-lightbulb"></i> Recommended Skills For You</h2>
        <a href="/pages/browse.php" class="btn btn-sm btn-outline">Browse All</a>
    </div>
    <div class="flex flex-wrap gap-8">
        <?php foreach ($recommendations as $rec): ?>
            <a href="/pages/browse.php?category_id=<?= $rec['category_id'] ?>" class="skill-tag no-underline">
                <i class="fas <?= h($rec['category_icon']) ?>"></i> <?= h($rec['skill_name']) ?>
                <small class="text-muted">(<?= $rec['teacher_count'] ?> teachers)</small>
            </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>