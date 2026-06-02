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

$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';

if (!$requestId || !in_array($action, ['accept', 'decline'])) {
    setFlash('error', 'Invalid request.');
    header('Location: /pages/sessions.php');
    exit;
}

$stmt = $pdo->prepare("SELECT sr.*, sr.requester_id, s.id AS session_id FROM session_requests sr JOIN sessions s ON s.request_id = sr.id WHERE sr.id = ? AND sr.teacher_id = ?");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'Request not found.');
    header('Location: /pages/sessions.php');
    exit;
}

if ($action === 'accept') {
    $pdo->prepare("UPDATE session_requests SET status = 'accepted' WHERE id = ?")->execute([$requestId]);
    $pdo->prepare("UPDATE sessions SET status = 'scheduled' WHERE request_id = ?")->execute([$requestId]);

    // Notify requester
    createNotification($pdo, $request['requester_id'], 'session_accepted',
        'Your session request has been accepted! Check your sessions.',
        '/pages/sessions.php'
    );

    setFlash('success', 'Session request accepted!');
} else {
    $pdo->prepare("UPDATE session_requests SET status = 'declined' WHERE id = ?")->execute([$requestId]);
    $pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE request_id = ?")->execute([$requestId]);

    createNotification($pdo, $request['requester_id'], 'session_declined',
        'Your session request was declined.',
        '/pages/sessions.php'
    );

    setFlash('info', 'Session request declined.');
}

header('Location: /pages/sessions.php');
exit;