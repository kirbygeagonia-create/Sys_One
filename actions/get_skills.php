<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([]);
    exit;
}

require_once __DIR__ . '/../config/database.php';

$categoryId = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;

if ($categoryId) {
    $stmt = $pdo->prepare("SELECT id, name FROM skills WHERE category_id = ? ORDER BY name");
    $stmt->execute([$categoryId]);
} else {
    $stmt = $pdo->query("SELECT id, name FROM skills ORDER BY name");
}

header('Content-Type: application/json');
echo json_encode($stmt->fetchAll());