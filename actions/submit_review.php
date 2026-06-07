<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$sessionId = isset($_GET['session_id']) ? (int)$_GET['session_id'] : 0;

if (!$sessionId) {
    header('Location: /pages/sessions.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, sr.requester_id, sr.teacher_id, sr.skill_id,
           sk.name AS skill_name, sc.name AS category_name, sc.icon AS category_icon,
           req.name AS requester_name, t.name AS teacher_name
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN skill_categories sc ON sk.category_id = sc.id
    JOIN users req ON sr.requester_id = req.id
    JOIN users t ON sr.teacher_id = t.id
    WHERE s.id = ? AND s.status = 'completed'
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Session not found or not yet completed.');
    header('Location: /pages/sessions.php');
    exit;
}

$isTeacher = $session['teacher_id'] == $userId;
$isLearner = $session['requester_id'] == $userId;
if (!$isTeacher && !$isLearner) {
    header('Location: /pages/sessions.php');
    exit;
}

// Check if already reviewed
$stmt = $pdo->prepare("SELECT id FROM session_reviews WHERE session_id = ? AND reviewer_id = ?");
$stmt->execute([$sessionId, $userId]);
if ($stmt->fetch()) {
    setFlash('info', 'You already reviewed this session.');
    header('Location: /pages/sessions.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');

    if ($rating < 1 || $rating > 5) {
        $error = 'Please select a rating between 1 and 5.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO session_reviews (session_id, reviewer_id, rating, comment) VALUES (?, ?, ?, ?)");
        $stmt->execute([$sessionId, $userId, $rating, $comment]);

        updateReputation($pdo, $session['teacher_id']);

        if ($isTeacher) {
            $badgeLevel = $_POST['badge_level'] ?? 'beginner';
            $allowedLevels = ['beginner', 'intermediate', 'advanced'];
            if (!in_array($badgeLevel, $allowedLevels)) {
                $badgeLevel = 'beginner';
            }
            $stmt = $pdo->prepare("INSERT INTO badges (session_id, issuer_id, recipient_id, skill_id, level) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$sessionId, $userId, $session['requester_id'], $session['skill_id'], $badgeLevel]);

            createNotification($pdo, $session['requester_id'], 'badge_earned',
                'You earned a ' . $badgeLevel . ' badge in ' . $session['skill_name'] . '!',
                '/pages/profile.php?id=' . $session['requester_id']
            );
        }

        setFlash('success', 'Review submitted! Thank you.');
        header('Location: /pages/sessions.php');
        exit;
    }
}

$pageTitle = 'Submit Review'; require_once __DIR__ . '/../includes/header.php';
?>

<h1>Review Session</h1>

<div class="card max-w-sm mx-auto">
    <div class="mb-16">
        <p><strong>Skill:</strong> <i class="fas <?= h($session['category_icon']) ?>"></i> <?= h($session['skill_name']) ?></p>
        <p><strong>Teacher:</strong> <?= h($session['teacher_name']) ?></p>
        <p><strong>Learner:</strong> <?= h($session['requester_name']) ?></p>
        <p><strong>Date:</strong> <?= date('F j, Y', strtotime($session['scheduled_at'])) ?></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
            <label>Rating</label>
            <div class="star-rating">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="rating" value="<?= $i ?>" class="sr-only" onchange="highlightStars(this)">
                        <span class="star" data-value="<?= $i ?>" tabindex="0" role="radio" aria-label="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>" aria-checked="false">&#9733;</span>
                    </label>
                <?php endfor; ?>
            </div>
        </div>
        <div class="form-group">
            <label for="comment">Comment (optional)</label>
            <textarea id="comment" name="comment" placeholder="How did the session go?"></textarea>
        </div>

        <?php if ($isTeacher): ?>
            <div class="form-group">
                <label for="badge_level">Issue a Badge to <?= h($session['requester_name']) ?></label>
                <select id="badge_level" name="badge_level">
                    <option value="beginner"> Beginner</option>
                    <option value="intermediate"> Intermediate</option>
                    <option value="advanced"> Advanced</option>
                </select>
                <p class="form-hint">Endorse the learner's skill level based on this session.</p>
            </div>
        <?php endif; ?>

        <div class="flex gap-8 justify-end">
            <a href="/pages/sessions.php" class="btn btn-outline">Skip</a>
            <button type="submit" class="btn btn-primary">Submit Review</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>