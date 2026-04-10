<?php
session_start();
http_response_code(404);
?>
<?php require_once 'includes/header.php'; ?>

<div class="error-page">
    <div class="error-code">404</div>
    <h2 class="fw-bold mb-3">Page Not Found</h2>
    <p class="lead text-muted mb-4">The page you're looking for doesn't exist or has been moved.</p>
    <div class="d-flex gap-3 justify-content-center flex-wrap">
        <a href="/marketplace/index.php" class="btn btn-primary btn-lg">
            <i class="bi bi-house me-2"></i>Go Home
        </a>
        <a href="/marketplace/products.php" class="btn btn-outline-primary btn-lg">
            <i class="bi bi-grid me-2"></i>Browse Listings
        </a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
