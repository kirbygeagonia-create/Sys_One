<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireCsrf();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['avatar'])) {
    header('Location: /pages/profile.php?id=' . $userId);
    exit;
}

$file = $_FILES['avatar'];
$allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

if ($file['error'] !== UPLOAD_ERR_OK) {
    setFlash('error', 'Upload failed. Try again.');
    header('Location: /pages/profile.php?id=' . $userId);
    exit;
}

if (!in_array($file['type'], $allowedTypes)) {
    setFlash('error', 'Only JPG, PNG, GIF, and WebP images are allowed.');
    header('Location: /pages/profile.php?id=' . $userId);
    exit;
}

if ($file['size'] > 2 * 1024 * 1024) {
    setFlash('error', 'File too large. Max 2MB.');
    header('Location: /pages/profile.php?id=' . $userId);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/avatars/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
$path = $uploadDir . $filename;

if (move_uploaded_file($file['tmp_name'], $path)) {
    $stmt = $pdo->prepare("SELECT avatar FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $old = $stmt->fetchColumn();
    if ($old && file_exists($uploadDir . $old)) {
        unlink($uploadDir . $old);
    }

    $stmt = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
    $stmt->execute([$filename, $userId]);
    setFlash('success', 'Profile photo updated!');
} else {
    setFlash('error', 'Upload failed. Try again.');
}
header('Location: /pages/profile.php?id=' . $userId);
exit;