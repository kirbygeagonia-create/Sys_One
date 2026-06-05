<?php
http_response_code(500);
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Server Error';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="error-page">
    <i class="fas fa-exclamation-triangle error-icon error-500"></i>
    <h1>500 — Something Went Wrong</h1>
    <p class="text-muted">An unexpected error occurred. Please try again later.</p>
    <a href="/index.php" class="btn btn-primary mt-16"><i class="fas fa-home"></i> Go Home</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>