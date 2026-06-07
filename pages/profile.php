<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$profileId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$profileId) {
    header('Location: /index.php');
    exit;
}

$profile = getUserById($pdo, $profileId);
if (!$profile) {
    header('Location: /index.php');
    exit;
}

$offeredSkills = getUserOfferedSkills($pdo, $profileId);
$wantedSkills = getUserWantedSkills($pdo, $profileId);
$badges = getUserBadges($pdo, $profileId);

// Get session stats — sessions table uses request_id, join with session_requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    WHERE (sr.requester_id = ? OR sr.teacher_id = ?) AND s.status = 'completed'
");
$stmt->execute([$profileId, $profileId]);
$completedSessions = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    WHERE sr.teacher_id = ? AND s.status = 'completed'
");
$stmt->execute([$profileId]);
$taughtSessions = (int)$stmt->fetchColumn();

$isOwner = isset($_SESSION['user_id']) && $_SESSION['user_id'] == $profileId;

// Handle profile update BEFORE including header so flash messages display correctly
if ($isOwner && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    requireCsrf();
    $name = trim($_POST['name'] ?? '');
    $bio = trim($_POST['bio'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $avail = trim($_POST['availability'] ?? '');

    if (empty($name)) {
        setFlash('error', 'Name is required.');
    } elseif (strlen($name) > 100) {
        setFlash('error', 'Name must be 100 characters or fewer.');
    } elseif (strlen($bio) > 1000) {
        setFlash('error', 'Bio must be 1000 characters or fewer.');
    } elseif (strlen($location) > 100) {
        setFlash('error', 'Location must be 100 characters or fewer.');
    } elseif (strlen($avail) > 500) {
        setFlash('error', 'Availability must be 500 characters or fewer.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, bio = ?, location = ?, availability = ? WHERE id = ?");
        $stmt->execute([$name, $bio, $location, $avail, $profileId]);
        $profile = getUserById($pdo, $profileId);
        setFlash('success', 'Profile updated!');
    }
    header('Location: /pages/profile.php?id=' . $profileId);
    exit;
}

$pageTitle = 'Profile'; require_once __DIR__ . '/../includes/header.php';
?>

<div class="profile-header">
    <div class="profile-avatar"<?php if (!$profile['avatar']): ?> style="background: <?= nameToColor($profile['name']) ?>"<?php endif; ?>>
        <?php if ($profile['avatar']): ?>
            <img src="/uploads/avatars/<?= h($profile['avatar']) ?>" alt="Avatar" class="avatar-img">
        <?php else: ?>
            <?= h(strtoupper(substr($profile['name'], 0, 1))) ?>
        <?php endif; ?>
    </div>
    <div class="profile-info">
        <h1><?= h($profile['name']) ?></h1>
        <div class="profile-meta">
            <?php if ($profile['location']): ?><i class="fas fa-location-dot"></i> <?= h($profile['location']) ?> &middot; <?php endif; ?>
            <i class="fas fa-coins"></i> <?= (int)$profile['credits'] ?> credits
            <?php if ($profile['reputation'] > 0): ?> &middot; <i class="fas fa-star"></i> <?= number_format($profile['reputation'], 1) ?> rating<?php endif; ?>
            &middot; <i class="fas fa-book"></i> <?= $taughtSessions ?> taught
            <?php if ($profile['updated_at']): ?> &middot; <i class="fas fa-circle dot-active"></i> Active <?= timeAgo($profile['updated_at']) ?><?php endif; ?>
        </div>
        <?php if ($profile['availability']): ?>
            <p class="mt-4"><i class="fas fa-clock"></i> <strong>Availability:</strong> <?= h($profile['availability']) ?></p>
        <?php endif; ?>
        <?php if ($profile['bio']): ?>
            <p class="profile-bio"><?= h($profile['bio']) ?></p>
        <?php endif; ?>

        <?php if (!$isOwner && isset($_SESSION['user_id'])): ?>
            <?php if (!empty($offeredSkills)): ?>
                <?php if (count($offeredSkills) === 1): ?>
                    <a href="/actions/request_session.php?teacher_id=<?= $profileId ?>&skill_id=<?= $offeredSkills[0]['skill_id'] ?>"
                       class="btn btn-primary btn-sm mt-8"><i class="fas fa-calendar-plus"></i> Request Session</a>
                <?php else: ?>
                    <form method="GET" action="/actions/request_session.php" class="flex gap-8 items-center mt-8 flex-wrap">
                        <input type="hidden" name="teacher_id" value="<?= $profileId ?>">
                        <select name="skill_id" class="form-select-inline" required>
                            <?php foreach ($offeredSkills as $sk): ?>
                                <option value="<?= $sk['skill_id'] ?>">
                                    <?= h($sk['skill_name']) ?> (<?= h($sk['proficiency']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-calendar-plus"></i> Request Session</button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($isOwner): ?>
            <button class="btn btn-outline btn-sm mt-8" onclick="openModal('editProfileModal')">Edit Profile</button>
            <button class="btn btn-outline btn-sm mt-8" onclick="document.getElementById('avatarInput').click()">Change Photo</button>
            <button class="btn btn-outline btn-sm mt-8" onclick="copyProfileLink(this)" title="Copy your profile link"><i class="fas fa-link"></i> Copy Link</button>
            <form method="POST" enctype="multipart/form-data" action="/actions/upload_avatar.php" class="hidden">
                <?= csrfField() ?>
                <input type="file" id="avatarInput" name="avatar" accept="image/*" onchange="this.form.submit()">
            </form>
        <?php endif; ?>
    </div>
</div>

<div class="grid-2">
    <!-- Skills Offered -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-chalkboard-teacher"></i> Teaches</h2></div>
        <?php if (empty($offeredSkills)): ?>
            <p class="text-muted">No skills listed yet.</p>
        <?php else: ?>
            <div class="flex flex-wrap gap-8">
                <?php foreach ($offeredSkills as $skill): ?>
                    <div class="skill-tag skill-tag-offer">
                        <i class="fas <?= h($skill['category_icon']) ?>"></i> <?= h($skill['skill_name']) ?>
                        <small>(<?= h($skill['proficiency']) ?>)</small>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Skills Wanted -->
    <div class="card">
        <div class="card-header"><h2><i class="fas fa-bullseye"></i> Wants to Learn</h2></div>
        <?php if (empty($wantedSkills)): ?>
            <p class="text-muted">Nothing listed yet.</p>
        <?php else: ?>
            <div class="flex flex-wrap gap-8">
                <?php foreach ($wantedSkills as $skill): ?>
                    <div class="skill-tag skill-tag-want">
                        <i class="fas <?= h($skill['category_icon']) ?>"></i> <?= h($skill['skill_name']) ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Badges -->
<div class="card mt-24">
    <div class="card-header"><h2><i class="fas fa-medal"></i> Skill Badges</h2></div>
    <?php if (empty($badges)): ?>
        <p class="text-muted">No badges earned yet. Complete sessions to earn badges!</p>
    <?php else: ?>
        <div class="flex flex-wrap gap-12">
            <?php foreach ($badges as $badge): ?>
                <div class="badge-item">
                    <span class="badge-icon">
                        <?php if ($badge['level'] === 'beginner'): ?><i class="fas fa-medal badge-medal-beginner"></i>
                        <?php elseif ($badge['level'] === 'intermediate'): ?><i class="fas fa-medal badge-medal-intermediate"></i>
                        <?php else: ?><i class="fas fa-medal badge-medal-advanced"></i><?php endif; ?>
                    </span>
                    <span class="badge-name"><?= h($badge['skill_name']) ?></span>
                    <span class="badge-level"><?= ucfirst(h($badge['level'])) ?></span>
                    <span class="badge-issuer">by <?= h($badge['issuer_name']) ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Profile Modal -->
<?php if ($isOwner): ?>
<div class="modal-overlay" id="editProfileModal">
    <div class="modal">
        <h2>Edit Profile</h2>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="update_profile" value="1">
            <div class="form-group">
                <label for="name">Name</label>
                <input type="text" id="name" name="name" value="<?= h($profile['name']) ?>" maxlength="100" required>
            </div>
            <div class="form-group">
                <label for="bio">Bio</label>
                <textarea id="bio" name="bio" placeholder="Tell others about yourself..." maxlength="1000"><?= h($profile['bio'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label for="location">Location</label>
                <input type="text" id="location" name="location" placeholder="e.g. Manila, Philippines" value="<?= h($profile['location'] ?? '') ?>" maxlength="100">
            </div>
            <div class="form-group">
                <label for="availability">Availability</label>
                <textarea id="availability" name="availability" placeholder="e.g. Weekday evenings, weekends 10am–4pm" maxlength="500"><?= h($profile['availability'] ?? '') ?></textarea>
                <p class="form-hint">Let others know when you're free for sessions.</p>
            </div>
            <div class="flex gap-8 justify-end">
                <button type="button" class="btn btn-outline" onclick="closeModal('editProfileModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>