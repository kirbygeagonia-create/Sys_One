<?php
header('Content-Type: application/json');

$status = ['status' => 'ok', 'timestamp' => date('c'), 'checks' => []];

// DB check
try {
    require_once __DIR__ . '/config/database.php';
    $pdo->query("SELECT 1");
    $status['checks']['database'] = 'ok';
} catch (Exception $e) {
    $status['status'] = 'error';
    $status['checks']['database'] = 'failed';
}

// PHP version check
$status['checks']['php'] = PHP_VERSION;
$status['checks']['php_ok'] = version_compare(PHP_VERSION, '8.0.0', '>=');

$httpCode = $status['status'] === 'ok' ? 200 : 503;
http_response_code($httpCode);
echo json_encode($status, JSON_PRETTY_PRINT);