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

$stmt = $pdo->prepare("SELECT sr.*, sr.requester_id, s.id AS session_id, s.scheduled_at, s.duration FROM session_requests sr JOIN sessions s ON s.request_id = sr.id WHERE sr.id = ? AND sr.teacher_id = ?");
$stmt->execute([$requestId, $userId]);
$request = $stmt->fetch();

if (!$request) {
    setFlash('error', 'Request not found.');
    header('Location: /pages/sessions.php');
    exit;
}

if ($action === 'accept') {
    // Verify no schedule conflict since the request was made
    $sessionDate = $request['scheduled_at'];
    $duration = (int)$request['duration'];
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM sessions s2
        JOIN session_requests sr2 ON s2.request_id = sr2.id
        WHERE sr2.teacher_id = ?
        AND s2.id != ?
        AND s2.status IN ('scheduled', 'completed')
        AND s2.scheduled_at < DATE_ADD(?, INTERVAL ? MINUTE)
        AND DATE_ADD(s2.scheduled_at, INTERVAL s2.duration MINUTE) > ?
    ");
    $stmt->execute([$userId, $request['session_id'], $sessionDate, $duration, $sessionDate]);
    if ((int)$stmt->fetchColumn() > 0) {
        setFlash('error', 'Cannot accept: you have a scheduling conflict at that time.');
        header('Location: /pages/sessions.php');
        exit;
    }

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

    // Refund the reserved credit to the requester
    addCredits($pdo, $request['requester_id'], 1, 'bonus', null, 'session_refund', $requestId, 'Refund for declined session request');

    createNotification($pdo, $request['requester_id'], 'session_declined',
        'Your session request was declined. 1 credit has been refunded.',
        '/pages/sessions.php'
    );

    setFlash('info', 'Session request declined. Credit refunded to the requester.');
}

header('Location: /pages/sessions.php');
exit;