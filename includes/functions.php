<?php
define('SESSION_TIMEOUT', 7200); // 2 hours in seconds

/**
 * Start session if not already started
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check session timeout based on last activity
 */
function checkSessionTimeout() {
    startSession();
    if (isset($_SESSION['user_id']) && isset($_SESSION['last_activity'])) {
        if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
            session_unset();
            session_destroy();
            header('Location: /auth/login.php?reason=timeout');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

/**
 * Check if user is logged in, redirect if not
 */
function requireLogin() {
    startSession();
    checkSessionTimeout();
    if (!isset($_SESSION['user_id'])) {
        header('Location: /auth/login.php');
        exit;
    }
}

/**
 * Get current logged-in user data
 */
function getCurrentUser($pdo) {
    startSession();
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Get user by ID
 */
function getUserById($pdo, $id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

/**
 * Add credit transaction and update user balance
 */
function addCredits($pdo, $userId, $amount, $type, $counterpartyId = null, $referenceType = null, $referenceId = null, $description = null) {
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("INSERT INTO credit_transactions (user_id, counterparty_id, amount, type, reference_type, reference_id, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $counterpartyId, $amount, $type, $referenceType, $referenceId, $description]);

        if ($type === 'earn' || $type === 'bonus') {
            $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")->execute([$amount, $userId]);
        } elseif ($type === 'spend') {
            // Floor check: prevent negative balance
            $stmt = $pdo->prepare("UPDATE users SET credits = GREATEST(credits - ?, 0) WHERE id = ?");
            $stmt->execute([$amount, $userId]);
        }

        $pdo->commit();
        return true;
    } catch (Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

/**
 * Get all skill categories
 */
function getCategories($pdo) {
    $stmt = $pdo->query("SELECT * FROM skill_categories ORDER BY name");
    return $stmt->fetchAll();
}

/**
 * Get skills by category
 */
function getSkillsByCategory($pdo, $categoryId = null) {
    if ($categoryId) {
        $stmt = $pdo->prepare("SELECT * FROM skills WHERE category_id = ? ORDER BY name");
        $stmt->execute([$categoryId]);
    } else {
        $stmt = $pdo->query("SELECT s.*, sc.name AS category_name FROM skills s JOIN skill_categories sc ON s.category_id = sc.id ORDER BY sc.name, s.name");
    }
    return $stmt->fetchAll();
}

/**
 * Get skills a user offers
 */
function getUserOfferedSkills($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT uso.*, s.name AS skill_name, sc.name AS category_name, sc.icon AS category_icon
        FROM user_skills_offered uso
        JOIN skills s ON uso.skill_id = s.id
        JOIN skill_categories sc ON s.category_id = sc.id
        WHERE uso.user_id = ?
        ORDER BY sc.name, s.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get skills a user wants to learn
 */
function getUserWantedSkills($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT usw.*, s.name AS skill_name, sc.name AS category_name, sc.icon AS category_icon
        FROM user_skills_wanted usw
        JOIN skills s ON usw.skill_id = s.id
        JOIN skill_categories sc ON s.category_id = sc.id
        WHERE usw.user_id = ?
        ORDER BY sc.name, s.name
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get user badges
 */
function getUserBadges($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT b.*, s.name AS skill_name, sc.name AS category_name, u.name AS issuer_name
        FROM badges b
        JOIN skills s ON b.skill_id = s.id
        JOIN skill_categories sc ON s.category_id = sc.id
        JOIN users u ON b.issuer_id = u.id
        WHERE b.recipient_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get user's credit transactions
 */
function getCreditTransactions($pdo, $userId, $limit = 20, $offset = 0) {
    $stmt = $pdo->prepare("
        SELECT ct.*, u.name AS counterparty_name
        FROM credit_transactions ct
        LEFT JOIN users u ON ct.counterparty_id = u.id
        WHERE ct.user_id = ?
        ORDER BY ct.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$userId, $limit, $offset]);
    return $stmt->fetchAll();
}

/**
 * Get total credits earned by a user
 */
function getTotalEarned($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM credit_transactions WHERE user_id = ? AND type = 'earn'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get total credits spent by a user
 */
function getTotalSpent($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) FROM credit_transactions WHERE user_id = ? AND type = 'spend'");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Get total completed session count for a user
 */
function getTotalSessions($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM sessions s
        JOIN session_requests sr ON s.request_id = sr.id
        WHERE (sr.requester_id = ? OR sr.teacher_id = ?) AND s.status = 'completed'
    ");
    $stmt->execute([$userId, $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Update user reputation based on reviews
 */
function updateReputation($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT ROUND(AVG(rating), 1) AS avg_rating
        FROM session_reviews sr
        JOIN sessions s ON sr.session_id = s.id
        JOIN session_requests srq ON s.request_id = srq.id
        WHERE srq.teacher_id = ?
    ");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    if ($result && $result['avg_rating']) {
        $pdo->prepare("UPDATE users SET reputation = ? WHERE id = ?")->execute([$result['avg_rating'], $userId]);
    }
}

/**
 * ===== FLASH MESSAGES =====
 */
function setFlash($type, $message) {
    startSession();
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function getFlashes() {
    startSession();
    $flashes = $_SESSION['flash'] ?? [];
    $_SESSION['flash'] = [];
    return $flashes;
}

function displayFlashes() {
    $flashes = getFlashes();
    $output = '';
    foreach ($flashes as $f) {
        $cls = $f['type'] === 'error' ? 'alert-error' : 'alert-success';
        $output .= '<div class="alert ' . $cls . '">' . h($f['message']) . '</div>';
    }
    return $output;
}

/**
 * ===== CSRF PROTECTION =====
 */
function generateCsrfToken() {
    startSession();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    startSession();
    return hash_equals($_SESSION['csrf_token'] ?? '', $token);
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrfToken() . '">';
}

function requireCsrf() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    $token = $_POST['csrf_token'] ?? '';
    if (!validateCsrfToken($token)) {
        die('Invalid or expired CSRF token. Please go back and try again.');
    }
}

/**
 * ===== NOTIFICATIONS =====
 */
function createNotification($pdo, $userId, $type, $message, $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$userId, $type, $message, $link]);
}

function getUnreadNotifications($pdo, $userId, $limit = 10) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function getAllNotifications($pdo, $userId, $limit = 50) {
    $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function markNotificationRead($pdo, $notificationId, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    return $stmt->execute([$notificationId, $userId]);
}

function markAllNotificationsRead($pdo, $userId) {
    $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    return $stmt->execute([$userId]);
}

function getUnreadCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Send a simple HTML email
 * Replace with PHPMailer for production SMTP
 */
function sendEmail($to, $subject, $htmlBody) {
    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: SkillLoop <noreply@skillloop.local>\r\n";
    $headers .= "X-Mailer: PHP/" . PHP_VERSION . "\r\n";
    return mail($to, $subject, $htmlBody, $headers);
}

/**
 * Simple IP-based rate limiter using the login_attempts table.
 * Returns true if the action is allowed, false if rate-limited.
 */
function checkRateLimit($pdo, $ip, $action, $maxAttempts = 10, $windowMinutes = 15) {
    // Clean old records
    $pdo->prepare("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL ? MINUTE) AND action = ?")
        ->execute([$windowMinutes, $action]);

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ? AND action = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? MINUTE)");
    $stmt->execute([$ip, $action, $windowMinutes]);

    if ((int)$stmt->fetchColumn() >= $maxAttempts) {
        return false;
    }

    $pdo->prepare("INSERT INTO login_attempts (ip_address, action) VALUES (?, ?)")
        ->execute([$ip, $action]);
    return true;
}

/**
 * Return a CSS hsl() color deterministically derived from a string
 */
function nameToColor($name) {
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
        $hash &= $hash;
    }
    $hue = abs($hash) % 360;
    return "hsl({$hue}, 60%, 55%)";
}

/**
 * Find users who want to learn skills the given user offers
 */
function getPotentialLearners($pdo, $userId, $limit = 5) {
    $stmt = $pdo->prepare("
        SELECT DISTINCT u.id, u.name, u.reputation, s.name AS skill_name, sc.icon AS category_icon
        FROM user_skills_wanted usw
        JOIN users u ON usw.user_id = u.id
        JOIN skills s ON usw.skill_id = s.id
        JOIN skill_categories sc ON s.category_id = sc.id
        JOIN user_skills_offered uso ON uso.skill_id = usw.skill_id AND uso.user_id = ?
        WHERE usw.user_id != ?
        ORDER BY u.reputation DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $userId, $limit]);
    return $stmt->fetchAll();
}

/**
 * ===== MESSAGING =====
 */

/**
 * Get all messages for a session (user must be a participant)
 */
function getSessionMessages($pdo, $sessionId, $userId) {
    $stmt = $pdo->prepare("
        SELECT m.*, u.name AS sender_name
        FROM messages m
        JOIN sessions s ON m.session_id = s.id
        JOIN session_requests sr ON s.request_id = sr.id
        JOIN users u ON m.sender_id = u.id
        WHERE m.session_id = ?
        AND (sr.requester_id = ? OR sr.teacher_id = ?)
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$sessionId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Count unread messages across all sessions for a user
 */
function countUnreadMessages($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM messages m
        JOIN sessions s ON m.session_id = s.id
        JOIN session_requests sr ON s.request_id = sr.id
        WHERE (sr.requester_id = ? OR sr.teacher_id = ?)
        AND m.sender_id != ?
        AND m.is_read = 0
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Mark all messages in a session as read (except messages sent by the current user)
 */
function markMessagesRead($pdo, $sessionId, $userId) {
    $stmt = $pdo->prepare("UPDATE messages SET is_read = 1 WHERE session_id = ? AND sender_id != ?");
    return $stmt->execute([$sessionId, $userId]);
}

/**
 * Check if a path matches the current page for nav active state
 */
function isActive($path) {
    $current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    return $current === $path || str_starts_with($current, $path) ? 'nav-active' : '';
}

/**
 * Sanitize output for HTML
 */
function h($str) {
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

/**
 * Time ago helper
 */
function timeAgo($timestamp) {
    $diff = time() - strtotime($timestamp);
    $absDiff = abs($diff);
    $future = $diff < 0;

    if ($absDiff < 60)      $str = 'just now';
    elseif ($absDiff < 3600)   $str = floor($absDiff / 60) . 'm';
    elseif ($absDiff < 86400)  $str = floor($absDiff / 3600) . 'h';
    elseif ($absDiff < 604800) $str = floor($absDiff / 86400) . 'd';
    else                    $str = date('M j', strtotime($timestamp));

    if ($str === 'just now') return $str;
    return $future ? 'in ' . $str : $str . ' ago';
}