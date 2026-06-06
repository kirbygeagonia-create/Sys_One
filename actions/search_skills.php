<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
require_once __DIR__ . '/../config/database.php';

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

header('Content-Type: application/json');

// Only return user-name results to logged-in users
$includeUsers = isset($_SESSION['user_id']);

$stmt = $pdo->prepare("
    SELECT s.id, s.name, sc.name AS category
    FROM skills s
    JOIN skill_categories sc ON s.category_id = sc.id
    WHERE s.name LIKE ?
    ORDER BY s.name
    LIMIT 10
");
$stmt->execute(['%' . $q . '%']);
$results = $stmt->fetchAll();

// Also search by user name
$stmt2 = $pdo->prepare("
    SELECT u.id, u.name, 'User' AS category
    FROM users u
    WHERE u.name LIKE ?
    ORDER BY u.reputation DESC
    LIMIT 5
");
$stmt2->execute(['%' . $q . '%']);

$userResults = $includeUsers ? $stmt2->fetchAll() : [];
echo json_encode(array_merge($results, $userResults));