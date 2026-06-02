<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
requireCsrf();
require_once __DIR__ . '/../config/database.php';

$userId = $_SESSION['user_id'];
markAllNotificationsRead($pdo, $userId);

setFlash('success', 'All notifications marked as read.');
header('Location: /pages/notifications.php');