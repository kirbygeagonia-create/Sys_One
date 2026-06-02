<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireCsrf();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/sessions.php');
    exit;
}

$sessionId = (int)($_POST['session_id'] ?? 0);

if (!$sessionId) {
    setFlash('error', 'Invalid session.');
    header('Location: /pages/sessions.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT s.*, sr.requester_id, sr.teacher_id, sr.skill_id, sk.name AS skill_name
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    WHERE s.id = ? AND s.status = 'scheduled'
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Session not found or already completed.');
    header('Location: /pages/sessions.php');
    exit;
}

if ($session['requester_id'] != $userId && $session['teacher_id'] != $userId) {
    setFlash('error', 'Unauthorized.');
    header('Location: /pages/sessions.php');
    exit;
}

// Mark confirmation
if ($session['requester_id'] == $userId) {
    $pdo->prepare("UPDATE sessions SET requester_confirmed = 1 WHERE id = ?")->execute([$sessionId]);
} else {
    $pdo->prepare("UPDATE sessions SET teacher_confirmed = 1 WHERE id = ?")->execute([$sessionId]);
}

// Check if both confirmed
$stmt = $pdo->prepare("SELECT requester_confirmed, teacher_confirmed FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$updated = $stmt->fetch();

if ($updated['requester_confirmed'] && $updated['teacher_confirmed']) {
    $pdo->prepare("UPDATE sessions SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$sessionId]);

    $requesterId = $session['requester_id'];
    $teacherId = $session['teacher_id'];

    addCredits($pdo, $requesterId, 1, 'spend', $teacherId, 'session', $sessionId, 'Session: ' . $session['skill_name']);
    addCredits($pdo, $teacherId, 1, 'earn', $requesterId, 'session', $sessionId, 'Session: ' . $session['skill_name']);

    // Notify both parties
    $requester = getUserById($pdo, $requesterId);
    $teacher = getUserById($pdo, $teacherId);

    createNotification($pdo, $requesterId, 'session_complete',
        'Your session with ' . $teacher['name'] . ' is complete! Leave a review.',
        '/actions/submit_review.php?session_id=' . $sessionId
    );
    createNotification($pdo, $teacherId, 'session_complete',
        'Your session with ' . $requester['name'] . ' is complete! You earned 1 credit.',
        '/actions/submit_review.php?session_id=' . $sessionId
    );

    setFlash('success', 'Session completed! Credits have been transferred. Please leave a review.');
    header('Location: /actions/submit_review.php?session_id=' . $sessionId);
    exit;
}

$otherName = $session['requester_id'] == $userId ?
    getUserById($pdo, $session['teacher_id'])['name'] :
    getUserById($pdo, $session['requester_id'])['name'];
setFlash('success', 'You confirmed completion. Waiting for ' . $otherName . ' to confirm too.');
header('Location: /pages/sessions.php');
exit;