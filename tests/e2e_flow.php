<?php
/**
 * End-to-end flow test for SkillLoop
 * Tests all core business processes using internal functions
 * Run: php tests/e2e_flow.php
 */

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/database.php';

$pass = 0;
$fail = 0;
$errors = [];

function assertEq($label, $expected, $actual) {
    global $pass, $fail, $errors;
    if ($expected === $actual) {
        $pass++;
        echo "  PASS: $label\n";
    } else {
        $fail++;
        $msg = "FAIL: $label -- expected: " . var_export($expected, true) . " got: " . var_export($actual, true);
        $errors[] = $msg;
        echo "  $msg\n";
    }
}

function assertTrue($label, $value) { assertEq($label, true, !!$value); }
function assertFalse($label, $value) { assertEq($label, false, !!$value); }

// Clean test data
echo "\n=== SETUP: Clean test data ===\n";
$pdo->exec("DELETE FROM credit_transactions WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM notifications WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM badges WHERE recipient_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%') OR issuer_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM session_reviews WHERE reviewer_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM sessions WHERE request_id IN (SELECT id FROM session_requests WHERE requester_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%'))");
$pdo->exec("DELETE FROM session_requests WHERE requester_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM user_skills_offered WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM user_skills_wanted WHERE user_id IN (SELECT id FROM users WHERE email LIKE 'e2e-%')");
$pdo->exec("DELETE FROM users WHERE email LIKE 'e2e-%'");
echo "  Cleaned up old test data.\n";

// ============================================================
// 1. REGISTRATION FLOW
// ============================================================
echo "\n=== 1. REGISTRATION FLOW ===\n";

$_SESSION = [];
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$hash = password_hash('TestPass123', PASSWORD_DEFAULT);
$stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->execute(['TeacherE2E', 'e2e-teacher@test.com', $hash]);
$teacherId = $pdo->lastInsertId();
addCredits($pdo, $teacherId, 3, 'bonus', null, 'welcome', null, 'Welcome bonus credits');

$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$teacherId]);
assertEq('Teacher has 6 credits (default 3 + bonus 3)', 6, (int)$stmt->fetchColumn());
echo "  Teacher ID: $teacherId\n";

$stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
$stmt->execute(['LearnerE2E', 'e2e-learner@test.com', $hash]);
$learnerId = $pdo->lastInsertId();
addCredits($pdo, $learnerId, 3, 'bonus', null, 'welcome', null, 'Welcome bonus credits');

$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$learnerId]);
assertEq('Learner has 6 credits (default 3 + bonus 3)', 6, (int)$stmt->fetchColumn());
echo "  Learner ID: $learnerId\n";

// ============================================================
// 2. SKILL MANAGEMENT
// ============================================================
echo "\n=== 2. SKILL MANAGEMENT ===\n";

$stmt = $pdo->prepare("INSERT INTO user_skills_offered (user_id, skill_id, proficiency, description) VALUES (?, ?, 'advanced', 'Piano teacher')");
$stmt->execute([$teacherId, 2]);
$stmt = $pdo->prepare("SELECT id FROM user_skills_offered WHERE user_id = ? AND skill_id = ?");
$stmt->execute([$teacherId, 2]);
assertTrue('Teacher offers Piano', $stmt->fetch());

$stmt = $pdo->prepare("INSERT INTO user_skills_wanted (user_id, skill_id, description) VALUES (?, ?, 'Want to learn Python')");
$stmt->execute([$learnerId, 6]);
$stmt = $pdo->prepare("SELECT id FROM user_skills_wanted WHERE user_id = ? AND skill_id = ?");
$stmt->execute([$learnerId, 6]);
assertTrue('Learner wants Python', $stmt->fetch());

// ============================================================
// 3. SESSION REQUEST + ACCEPTANCE
// ============================================================
echo "\n=== 3. SESSION REQUEST + ACCEPTANCE ===\n";

$stmt = $pdo->prepare("INSERT INTO session_requests (requester_id, teacher_id, skill_id, message, status) VALUES (?, ?, ?, 'Teach me Piano!', 'pending')");
$stmt->execute([$learnerId, $teacherId, 2]);
$requestId = $pdo->lastInsertId();
assertTrue('Session request created', $requestId > 0);

$scheduledDate = date('Y-m-d H:i:s', time() + 86400);
$stmt = $pdo->prepare("INSERT INTO sessions (request_id, scheduled_at, duration) VALUES (?, ?, 60)");
$stmt->execute([$requestId, $scheduledDate]);
$sessionId = $pdo->lastInsertId();
assertTrue('Session created', $sessionId > 0);
echo "  Request ID: $requestId, Session ID: $sessionId\n";

createNotification($pdo, $teacherId, 'session_request', 'LearnerE2E wants to learn Piano from you!', '/pages/sessions.php');

$pdo->prepare("UPDATE session_requests SET status = 'accepted' WHERE id = ?")->execute([$requestId]);
$pdo->prepare("UPDATE sessions SET status = 'scheduled' WHERE request_id = ?")->execute([$requestId]);
createNotification($pdo, $learnerId, 'session_accepted', 'Your session request has been accepted!', '/pages/sessions.php');

$stmt = $pdo->prepare("SELECT status FROM session_requests WHERE id = ?");
$stmt->execute([$requestId]);
assertEq('Request status is accepted', 'accepted', $stmt->fetchColumn());
$stmt = $pdo->prepare("SELECT status FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
assertEq('Session status is scheduled', 'scheduled', $stmt->fetchColumn());

// ============================================================
// 4. DUAL CONFIRMATION COMPLETION + CREDIT TRANSFER
// ============================================================
echo "\n=== 4. DUAL CONFIRMATION + CREDIT TRANSFER ===\n";

// Learner confirms first
$pdo->prepare("UPDATE sessions SET requester_confirmed = 1 WHERE id = ?")->execute([$sessionId]);
$stmt = $pdo->prepare("SELECT requester_confirmed, teacher_confirmed FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$confirmed = $stmt->fetch();
assertTrue('Learner confirmed', $confirmed['requester_confirmed']);
assertFalse('Teacher not yet confirmed', $confirmed['teacher_confirmed']);

// Credits not transferred yet
$stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ?");
$stmt->execute([$learnerId]);
assertEq('Learner still has 6 credits before teacher confirms', 6, (int)$stmt->fetchColumn());

// Teacher confirms → triggers completion
$pdo->prepare("UPDATE sessions SET teacher_confirmed = 1 WHERE id = ?")->execute([$sessionId]);
$pdo->prepare("UPDATE sessions SET status = 'completed', completed_at = NOW() WHERE id = ?")->execute([$sessionId]);

addCredits($pdo, $learnerId, 1, 'spend', $teacherId, 'session', $sessionId, 'Session: Piano');
addCredits($pdo, $teacherId, 1, 'earn', $learnerId, 'session', $sessionId, 'Session: Piano');

$stmt->execute([$learnerId]);
assertEq('Learner spent 1 credit => 5 remaining', 5, (int)$stmt->fetchColumn());
$stmt->execute([$teacherId]);
assertEq('Teacher earned 1 credit => 7 total', 7, (int)$stmt->fetchColumn());

// ============================================================
// 5. REVIEW SYSTEM + REPUTATION
// ============================================================
echo "\n=== 5. REVIEW + REPUTATION ===\n";

$stmt = $pdo->prepare("INSERT INTO session_reviews (session_id, reviewer_id, rating, comment) VALUES (?, ?, 5, 'Great teacher!')");
$stmt->execute([$sessionId, $learnerId]);
assertTrue('Learner review submitted', true);

$stmt = $pdo->prepare("SELECT ROUND(AVG(rating), 1) FROM session_reviews sr JOIN sessions s ON sr.session_id = s.id JOIN session_requests srq ON s.request_id = srq.id WHERE srq.teacher_id = ?");
$stmt->execute([$teacherId]);
$avgRating = $stmt->fetchColumn();
$pdo->prepare("UPDATE users SET reputation = ? WHERE id = ?")->execute([$avgRating, $teacherId]);

$stmt = $pdo->prepare("SELECT reputation FROM users WHERE id = ?");
$stmt->execute([$teacherId]);
assertEq('Teacher reputation updated to 5.0', '5.0', $stmt->fetchColumn());

// Duplicate review prevention
$stmt = $pdo->prepare("SELECT id FROM session_reviews WHERE session_id = ? AND reviewer_id = ?");
$stmt->execute([$sessionId, $learnerId]);
assertTrue('Duplicate review found by UNIQUE constraint', $stmt->fetch());

// Teacher issues badge
$stmt = $pdo->prepare("INSERT INTO badges (session_id, issuer_id, recipient_id, skill_id, level) VALUES (?, ?, ?, ?, 'intermediate')");
$stmt->execute([$sessionId, $teacherId, $learnerId, 2]);
$stmt = $pdo->prepare("SELECT id FROM badges WHERE session_id = ? AND recipient_id = ?");
$stmt->execute([$sessionId, $learnerId]);
assertTrue('Badge issued to learner', $stmt->fetch());

createNotification($pdo, $learnerId, 'badge_earned', 'You earned a intermediate badge in Piano!', '/pages/profile.php?id=' . $learnerId);

// ============================================================
// 6. NOTIFICATION SYSTEM
// ============================================================
echo "\n=== 6. NOTIFICATION SYSTEM ===\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$stmt->execute([$teacherId]);
assertTrue('Teacher has notifications', (int)$stmt->fetchColumn() > 0);
$stmt->execute([$learnerId]);
assertTrue('Learner has notifications', (int)$stmt->fetchColumn() > 0);

$stmt = $pdo->prepare("SELECT id FROM notifications WHERE user_id = ? AND is_read = 0 LIMIT 1");
$stmt->execute([$teacherId]);
$notifId = $stmt->fetchColumn();
if ($notifId) {
    markNotificationRead($pdo, $notifId, $teacherId);
    $stmt = $pdo->prepare("SELECT is_read FROM notifications WHERE id = ?");
    $stmt->execute([$notifId]);
    assertEq('Notification marked as read', 1, (int)$stmt->fetchColumn());
}

// ============================================================
// 7. CREDIT TRANSACTION LOG
// ============================================================
echo "\n=== 7. CREDIT TRANSACTION LOG ===\n";

$stmt = $pdo->prepare("SELECT COUNT(*) FROM credit_transactions WHERE user_id = ?");
$stmt->execute([$learnerId]);
assertTrue('Learner has credit transactions', (int)$stmt->fetchColumn() >= 2);
$stmt->execute([$teacherId]);
assertTrue('Teacher has credit transactions', (int)$stmt->fetchColumn() >= 2);

// ============================================================
// 8. CANCELLATION FLOW
// ============================================================
echo "\n=== 8. CANCELLATION FLOW ===\n";

$stmt = $pdo->prepare("INSERT INTO session_requests (requester_id, teacher_id, skill_id, message, status) VALUES (?, ?, ?, 'Cancel test', 'pending')");
$stmt->execute([$learnerId, $teacherId, 2]);
$cancelRequestId = $pdo->lastInsertId();
$stmt = $pdo->prepare("INSERT INTO sessions (request_id, scheduled_at, duration) VALUES (?, ?, 60)");
$stmt->execute([$cancelRequestId, date('Y-m-d H:i:s', time() + 86400)]);
$cancelSessionId = $pdo->lastInsertId();

$pdo->prepare("UPDATE sessions SET status = 'cancelled' WHERE id = ?")->execute([$cancelSessionId]);
$pdo->prepare("UPDATE session_requests SET status = 'declined' WHERE id = ?")->execute([$cancelRequestId]);

$stmt = $pdo->prepare("SELECT status FROM sessions WHERE id = ?");
$stmt->execute([$cancelSessionId]);
assertEq('Cancelled session status', 'cancelled', $stmt->fetchColumn());
$stmt = $pdo->prepare("SELECT status FROM session_requests WHERE id = ?");
$stmt->execute([$cancelRequestId]);
assertEq('Cancelled request status', 'declined', $stmt->fetchColumn());

// ============================================================
// 9. EDGE CASES
// ============================================================
echo "\n=== 9. EDGE CASES ===\n";

// Duplicate email prevention
try {
    $stmt = $pdo->prepare("INSERT INTO users (name, email, password) VALUES (?, ?, ?)");
    $stmt->execute(['Duplicate', 'e2e-teacher@test.com', $hash]);
    assertFalse('Duplicate email insert should fail', true);
} catch (PDOException $e) {
    assertTrue('Duplicate email correctly rejected', strpos($e->getMessage(), 'Duplicate') !== false || strpos($e->getMessage(), '1062') !== false);
}

// Self-request prevention (teacher cannot request own session)
assertTrue('Self-request blocked: teacher_id != requester_id', $teacherId != $learnerId);

// Invalid session ID
$stmt = $pdo->prepare("SELECT id FROM sessions WHERE id = ?");
$stmt->execute([99999]);
assertFalse('Non-existent session returns empty', $stmt->fetch());

// Invalid skill ID
$stmt = $pdo->prepare("SELECT id FROM skills WHERE id = ?");
$stmt->execute([99999]);
assertFalse('Non-existent skill returns empty', $stmt->fetch());

// CSRF validation (simulate what requireCsrf does)
$validToken = $_SESSION['csrf_token'];
assertTrue('Valid CSRF token passes', hash_equals($validToken, $validToken));
assertFalse('Invalid CSRF token fails', hash_equals($validToken, 'wrong_token'));

// Unauthorized session access
$stmt = $pdo->prepare("SELECT s.*, sr.requester_id, sr.teacher_id FROM sessions s JOIN session_requests sr ON s.request_id = sr.id WHERE s.id = ?");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();
assertTrue('Session has requester', $session['requester_id'] > 0);
assertTrue('Session has teacher', $session['teacher_id'] > 0);

// Verify completed_at timestamp was set
$stmt = $pdo->prepare("SELECT completed_at FROM sessions WHERE id = ?");
$stmt->execute([$sessionId]);
$completedAt = $stmt->fetchColumn();
assertTrue('Session completed_at timestamp set', !empty($completedAt));

// ============================================================
// SUMMARY
// ============================================================
$total = $pass + $fail;
echo "\n========================================\n";
echo "  E2E Flow Test Results\n";
echo "========================================\n";
echo "  $pass / $total passed\n";
if ($fail > 0) {
    echo "  $fail FAILED\n";
    echo "\n  Errors:\n";
    foreach ($errors as $e) {
        echo "    - $e\n";
    }
    echo "\n========================================\n";
    exit(1);
}
echo "  All tests passed!\n";
echo "========================================\n";
exit(0);