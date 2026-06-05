<?php
/**
 * Session Reminder Script
 * Run via cron every 15 minutes:
 *   php cron/send_reminders.php
 *
 * Sends notifications for sessions starting within the next hour.
 */
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

// Find sessions starting in 30-60 minutes that haven't been reminded yet
$stmt = $pdo->prepare("
    SELECT s.id, s.scheduled_at, s.duration,
           sr.requester_id, sr.teacher_id, sk.name AS skill_name,
           req.name AS requester_name, t.name AS teacher_name
    FROM sessions s
    JOIN session_requests sr ON s.request_id = sr.id
    JOIN skills sk ON sr.skill_id = sk.id
    JOIN users req ON sr.requester_id = req.id
    JOIN users t ON sr.teacher_id = t.id
    WHERE s.status = 'scheduled'
    AND s.scheduled_at BETWEEN DATE_ADD(NOW(), INTERVAL 30 MINUTE) AND DATE_ADD(NOW(), INTERVAL 60 MINUTE)
");

$stmt->execute();
$sessions = $stmt->fetchAll();

$reminded = 0;
foreach ($sessions as $session) {
    // Create reminder notification for both participants
    $timeStr = date('g:i A', strtotime($session['scheduled_at']));
    $skillName = $session['skill_name'];

    createNotification($pdo, $session['requester_id'], 'session_reminder',
        "Reminder: Your {$skillName} session with {$session['teacher_name']} starts at {$timeStr}",
        '/pages/sessions.php'
    );

    createNotification($pdo, $session['teacher_id'], 'session_reminder',
        "Reminder: Your {$skillName} session with {$session['requester_name']} starts at {$timeStr}",
        '/pages/sessions.php'
    );

    $reminded++;
}

echo date('Y-m-d H:i:s') . " — Sent {$reminded} session reminder(s).\n";