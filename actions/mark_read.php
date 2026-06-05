<?php
require_once __DIR__ . '/../includes/functions.php';
requireLogin();
require_once __DIR__ . '/../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Validate CSRF from JSON body
$input = json_decode(file_get_contents('php://input'), true);
$token = $input['csrf_token'] ?? '';
if (!validateCsrfToken($token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = $_SESSION['user_id'];
$notificationId = (int)($input['id'] ?? 0);

if ($notificationId) {
    markNotificationRead($pdo, $notificationId, $userId);
}
header('Content-Type: application/json');
echo json_encode(['success' => true]);