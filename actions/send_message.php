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
$message = trim($_POST['message'] ?? '');

// Rate limit messages
$ip = $_SERVER['REMOTE_ADDR'];
if (!checkRateLimit($pdo, $ip, 'send_message', 10, 1)) {
    setFlash('error', 'You are sending messages too quickly. Please slow down.');
    header('Location: /pages/sessions.php');
    exit;
}

if (!$sessionId || empty($message)) {
    setFlash('error', 'Message cannot be empty.');
    header('Location: /pages/sessions.php');
    exit;
}

if (strlen($message) > 1000) {
    setFlash('error', 'Message must be 1000 characters or fewer.');
    header('Location: /pages/sessions.php');
    exit;
}

// Verify user is a participant of this session
$stmt = $pdo->prepare("
    SELECT s.id, sr.requester_id, sr.teacher_id, sk.name AS skill_name,
           req.name AS requester_name, t.name AS teacher_name
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN users req ON sr.requester_id = req.id
    JOIN users t ON sr.teacher_id = t.id
    WHERE s.id = ? AND (sr.requester_id = ? OR sr.teacher_id = ?)
");
$stmt->execute([$sessionId, $userId, $userId]);
$session = $stmt->fetch();

if (!$session) {
    setFlash('error', 'Session not found.');
    header('Location: /pages/sessions.php');
    exit;
}

// Insert the message
$stmt = $pdo->prepare("INSERT INTO messages (session_id, sender_id, message) VALUES (?, ?, ?)");
$stmt->execute([$sessionId, $userId, $message]);

// Notify the other participant
$otherId = ($session['requester_id'] == $userId) ? $session['teacher_id'] : $session['requester_id'];
$senderName = ($session['requester_id'] == $userId) ? $session['requester_name'] : $session['teacher_name'];
createNotification($pdo, $otherId, 'new_message',
    $senderName . ' sent you a message about ' . $session['skill_name'],
    '/pages/sessions.php'
);

setFlash('success', 'Message sent!');
header('Location: /pages/sessions.php');
exit;