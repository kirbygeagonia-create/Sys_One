<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
$notificationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($notificationId) {
    markNotificationRead($pdo, $notificationId, $userId);
}

header('Content-Type: application/json');
echo json_encode(['success' => true]);