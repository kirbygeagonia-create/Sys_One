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

$pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?")->execute([$sessionId]);
$pdo->prepare("UPDATE session_requests SET status = 'declined' WHERE id = ?")->execute([$session['request_id']]);

// Notify the other party
$otherId = $session['requester_id'] == $userId ? $session['teacher_id'] : $session['requester_id'];
$canceller = getUserById($pdo, $userId);
createNotification($pdo, $otherId, 'session_cancelled',
    $canceller['name'] . ' cancelled the ' . $session['skill_name'] . ' session.',
    '/pages/sessions.php'
);

setFlash('info', 'Session cancelled.');
header('Location: /pages/sessions.php');
exit;