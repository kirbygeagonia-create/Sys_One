<?php
/**
 * Start session if not already started
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

/**
 * Check if user is logged in, redirect if not
 */
function requireLogin() {
    startSession();
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
function getCreditTransactions($pdo, $userId, $limit = 20) {
    $stmt = $pdo->prepare("
        SELECT ct.*, u.name AS counterparty_name
        FROM credit_transactions ct
        LEFT JOIN users u ON ct.counterparty_id = u.id
        WHERE ct.user_id = ?
        ORDER BY ct.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
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
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j', strtotime($timestamp));
}