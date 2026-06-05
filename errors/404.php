<?php
http_response_code(404);
require_once __DIR__ . '/../includes/functions.php';
$pageTitle = 'Page Not Found';
require_once __DIR__ . '/../includes/header.php';
?>
<div class="error-page">
    <i class="fas fa-map-signs error-icon error-404"></i>
    <h1>404 — Page Not Found</h1>
    <p class="text-muted">The page you're looking for doesn't exist or has been moved.</p>
    <a href="/index.php" class="btn btn-primary mt-16"><i class="fas fa-home"></i> Go Home</a>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>