<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$teacherId = isset($_GET['teacher_id']) ? (int)$_GET['teacher_id'] : 0;
$skillId = isset($_GET['skill_id']) ? (int)$_GET['skill_id'] : 0;

if (!$teacherId || !$skillId || $teacherId == $userId) {
    setFlash('error', 'Invalid session request.');
    header('Location: /pages/browse.php');
    exit;
}

$teacher = getUserById($pdo, $teacherId);
if (!$teacher) {
    setFlash('error', 'Teacher not found.');
    header('Location: /pages/browse.php');
    exit;
}

$stmt = $pdo->prepare("SELECT s.*, sc.name AS category_name, sc.icon AS category_icon FROM skills s JOIN skill_categories sc ON s.category_id = sc.id WHERE s.id = ?");
$stmt->execute([$skillId]);
$skill = $stmt->fetch();

if (!$skill) {
    setFlash('error', 'Skill not found.');
    header('Location: /pages/browse.php');
    exit;
}

$user = getCurrentUser($pdo);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $message = trim($_POST['message'] ?? '');
    $scheduledDate = $_POST['scheduled_date'] ?? '';
    $duration = (int)($_POST['duration'] ?? 60);

    if (empty($scheduledDate)) {
        $error = 'Please select a date and time.';
    } elseif (strtotime($scheduledDate) < time()) {
        $error = 'Session date must be in the future.';
    } elseif ($user['credits'] < 1) {
        $error = 'Not enough credits! Teach someone to earn more credits first.';
    } elseif (empty(trim($message))) {
        $error = 'Please include a message to the teacher.';
    } else {
        $stmt = $pdo->prepare("INSERT INTO session_requests (requester_id, teacher_id, skill_id, message, status) VALUES (?, ?, ?, ?, 'pending')");
        $stmt->execute([$userId, $teacherId, $skillId, $message]);
        $requestId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO sessions (request_id, scheduled_at, duration) VALUES (?, ?, ?)");
        $stmt->execute([$requestId, $scheduledDate, $duration]);

        // Notify teacher
        $skillName = $skill['name'];
        $user = getCurrentUser($pdo);
        createNotification($pdo, $teacherId, 'session_request',
            $user['name'] . ' wants to learn ' . $skillName . ' from you!',
            '/pages/sessions.php'
        );

        setFlash('success', 'Session request sent! Wait for ' . h($teacher['name']) . ' to respond.');
        header('Location: /pages/sessions.php');
        exit;
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<h1>Request a Session</h1>

<div class="card max-w-sm mx-auto">
    <div class="mb-16">
        <p><strong>Teacher:</strong> <?= h($teacher['name']) ?></p>
        <p><strong>Skill:</strong> <i class="fas <?= h($skill['category_icon']) ?>"></i> <?= h($skill['name']) ?></p>
        <?php if ($teacher['availability']): ?>
            <p class="mt-4"><strong><i class="fas fa-clock"></i> Availability:</strong> <?= h($teacher['availability']) ?></p>
        <?php endif; ?>
        <p><strong>Your balance:</strong> <i class="fas fa-coins"></i> <?= (int)$user['credits'] ?> credits <small class="text-muted">(1 credit per session)</small></p>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
            <label for="scheduled_date">When do you want this session?</label>
            <input type="datetime-local" id="scheduled_date" name="scheduled_date" required
                   min="<?= date('Y-m-d\TH:i', time() + 3600) ?>">
        </div>
        <div class="form-group">
            <label for="duration">Duration (minutes)</label>
            <select id="duration" name="duration">
                <option value="30">30 min</option>
                <option value="60" selected>60 min</option>
                <option value="90">90 min</option>
                <option value="120">120 min</option>
            </select>
        </div>
        <div class="form-group">
            <label for="message">Message to <?= h($teacher['name']) ?></label>
            <textarea id="message" name="message" placeholder="What specific topics do you want to learn? Any questions beforehand?" required></textarea>
        </div>
        <div class="flex gap-8 justify-end">
            <a href="/pages/browse.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Send Request (1 credit)</button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>