<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireCsrf();

require_once __DIR__ . '/../config/database.php';
$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /pages/skills.php');
    exit;
}

$type = $_POST['type'] ?? '';
$skillId = (int)($_POST['skill_id'] ?? 0);
$action = $_POST['action'] ?? '';

// Handle remove action
if ($action === 'remove') {
    if (!$skillId || !in_array($type, ['offer', 'want'])) {
        setFlash('error', 'Invalid request.');
        header('Location: /pages/skills.php');
        exit;
    }
    if ($type === 'offer') {
        $stmt = $pdo->prepare("DELETE FROM user_skills_offered WHERE user_id = ? AND skill_id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM user_skills_wanted WHERE user_id = ? AND skill_id = ?");
    }
    $stmt->execute([$userId, $skillId]);
    setFlash('success', 'Skill removed from your list.');
    header('Location: /pages/skills.php');
    exit;
}

if (!$skillId || !in_array($type, ['offer', 'want'])) {
    setFlash('error', 'Invalid request.');
    header('Location: /pages/skills.php');
    exit;
}

// Verify skill exists
$stmt = $pdo->prepare("SELECT id FROM skills WHERE id = ?");
$stmt->execute([$skillId]);
if (!$stmt->fetch()) {
    setFlash('error', 'Skill not found.');
    header('Location: /pages/skills.php');
    exit;
}

if ($type === 'offer') {
    $proficiency = $_POST['proficiency'] ?? 'intermediate';
    $allowedProficiency = ['beginner', 'intermediate', 'advanced', 'expert'];
    if (!in_array($proficiency, $allowedProficiency)) {
        $proficiency = 'intermediate';
    }
    $description = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare("SELECT id FROM user_skills_offered WHERE user_id = ? AND skill_id = ?");
    $stmt->execute([$userId, $skillId]);
    if ($stmt->fetch()) {
        setFlash('error', 'You already offer this skill.');
        header('Location: /pages/skills.php');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO user_skills_offered (user_id, skill_id, proficiency, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $skillId, $proficiency, $description]);
    setFlash('success', 'Skill added to your teaching list!');
} else {
    $description = trim($_POST['description'] ?? '');

    $stmt = $pdo->prepare("SELECT id FROM user_skills_wanted WHERE user_id = ? AND skill_id = ?");
    $stmt->execute([$userId, $skillId]);
    if ($stmt->fetch()) {
        setFlash('error', 'You already want to learn this skill.');
        header('Location: /pages/skills.php');
        exit;
    }

    $stmt = $pdo->prepare("INSERT INTO user_skills_wanted (user_id, skill_id, description) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $skillId, $description]);
    setFlash('success', 'Skill added to your learning wishlist!');
}

header('Location: /pages/skills.php');
exit;