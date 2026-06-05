<?php
require_once __DIR__ . '/../includes/functions.php';
startSession();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
}
session_unset();
session_destroy();
header('Location: /index.php');
exit;