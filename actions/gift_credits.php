<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireCsrf();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/credits.php');
    exit;
}

$recipientId = (int)($_POST['recipient_id'] ?? 0);
$amount = (int)($_POST['amount'] ?? 0);

if ($recipientId <= 0 || $amount < 1 || $amount > 10) {
    setFlash('error', 'You can gift between 1 and 10 credits at a time.');
    header('Location: /pages/credits.php');
    exit;
}

if ($recipientId == $userId) {
    setFlash('error', 'You cannot gift credits to yourself.');
    header('Location: /pages/credits.php');
    exit;
}

// Verify recipient exists
$stmt = $pdo->prepare("SELECT id, name FROM users WHERE id = ?");
$stmt->execute([$recipientId]);
$recipient = $stmt->fetch();

if (!$recipient) {
    setFlash('error', 'Recipient not found.');
    header('Location: /pages/credits.php');
    exit;
}

// Rate limit gifting
$ip = $_SERVER['REMOTE_ADDR'];
if (!checkRateLimit($pdo, $ip, 'gift_credits', 5, 60)) {
    setFlash('error', 'Too many credit gifts. Please try again later.');
    header('Location: /pages/credits.php');
    exit;
}

// Get sender name for transaction description — fetch before transaction
$sender = getCurrentUser($pdo);
$senderName = $sender ? $sender['name'] : 'Someone';

try {
    $pdo->beginTransaction();

    // Lock sender's row
    $stmt = $pdo->prepare("SELECT credits FROM users WHERE id = ? FOR UPDATE");
    $stmt->execute([$userId]);
    $senderCredits = (int)$stmt->fetchColumn();

    if ($senderCredits < $amount) {
        $pdo->rollBack();
        setFlash('error', 'Not enough credits.');
        header('Location: /pages/credits.php');
        exit;
    }

    // Deduct from sender
    $pdo->prepare("UPDATE users SET credits = GREATEST(credits - ?, 0) WHERE id = ?")
        ->execute([$amount, $userId]);

    // Add to recipient
    $pdo->prepare("UPDATE users SET credits = credits + ? WHERE id = ?")
        ->execute([$amount, $recipientId]);

    // Transaction records
    $stmt = $pdo->prepare("INSERT INTO credit_transactions (user_id, counterparty_id, amount, type, description) VALUES (?, ?, ?, 'spend', ?)");
    $stmt->execute([$userId, $recipientId, $amount, 'Gift to ' . $recipient['name']]);

    $stmt = $pdo->prepare("INSERT INTO credit_transactions (user_id, counterparty_id, amount, type, description) VALUES (?, ?, ?, 'earn', ?)");
    $stmt->execute([$recipientId, $userId, $amount, 'Gift from ' . $senderName]);

    $pdo->commit();

    createNotification($pdo, $recipientId, 'credit_gift',
        $sender['name'] . ' gifted you ' . $amount . ' credit(s)!',
        '/pages/credits.php'
    );

    setFlash('success', 'Gifted ' . $amount . ' credit(s) to ' . h($recipient['name']) . '!');
} catch (Exception $e) {
    $pdo->rollBack();
    setFlash('error', 'An error occurred. Please try again.');
}

header('Location: /pages/credits.php');
exit;