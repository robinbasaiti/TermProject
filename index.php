<?php
session_start();
require_once 'db/conn.php';

$result = mysqli_query($conn,
    "SELECT p.id, p.name, p.price, p.condition, p.category,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image
     FROM products p
     WHERE p.stock > 0
     ORDER BY p.created_at DESC
     LIMIT 8"
);
$products = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products[] = $row;
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<!-- Hero Section -->
<section class="hero-banner">
    <div class="hero-content">
        <span class="hero-pill"><i class="bi bi-stars me-1"></i> Your preloved marketplace</span>
        <h1>Discover unique<br>second-hand finds</h1>
        <p class="hero-subtitle">Buy and sell preloved electronics, clothing, books, vinyl, and collectibles from real people.</p>
        <div class="hero-actions">
            <a href="/marketplace/products.php" class="btn btn-light btn-lg">
                <i class="bi bi-search me-2"></i>Browse Now
            </a>
            <a href="/marketplace/sell.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-plus-circle me-2"></i>Start Selling
            </a>
        </div>
    </div>
</section>

<!-- Category Quick-Links -->
<section class="categories-section">
    <div class="row g-2 g-md-3">
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=electronics" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-phone"></i></span>
                Electronics
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=clothing" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-bag-heart"></i></span>
                Clothing
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=books" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-book"></i></span>
                Books
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=cds" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-disc"></i></span>
                CDs
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=vinyl" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-vinyl"></i></span>
                Vinyl
            </a>
        </div>
        <div class="col-4 col-md-2">
            <a href="/marketplace/products.php?category=collectibles" class="category-chip">
                <span class="category-chip-icon"><i class="bi bi-gem"></i></span>
                Collectibles
            </a>
        </div>
    </div>
</section>

<!-- Recent Listings -->
<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Just dropped</span>
        <h2 class="mb-1 fw-bold">Recent Listings</h2>
        <p class="mb-0 text-muted">The newest items on the marketplace.</p>
    </div>
    <span class="page-intro__meta"><?= count($products) ?> item<?= count($products) !== 1 ? 's' : '' ?></span>
</section>

<?php if (empty($products)): ?>
<div class="text-center py-5 text-muted empty-state">
    <i class="bi bi-inbox fs-1 d-block mb-3"></i>
    <p class="fs-5 mb-2 fw-semibold">No products yet</p>
    <p class="text-muted mb-3">Be the first to list something on the marketplace.</p>
    <a href="/marketplace/sell.php" class="btn btn-primary">
        <i class="bi bi-plus-circle me-2"></i>List an Item
    </a>
</div>
<?php else: ?>
<div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-xl-4 g-4 product-grid">
    <?php foreach ($products as $p): ?>
    <div class="col">
        <div class="card product-card shadow-sm h-100">
            <?php if ($p['image']): ?>
            <img src="/marketplace/uploads/<?= htmlspecialchars($p['image']) ?>"
                 class="card-img-top" alt="<?= htmlspecialchars($p['name']) ?>">
            <?php else: ?>
            <div class="card-img-top no-image-placeholder">
                <i class="bi bi-image"></i>
            </div>
            <?php endif; ?>
            <div class="card-body d-flex flex-column">
                <h6 class="card-title fw-semibold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
                <div class="mb-2">
                    <span class="badge rounded-pill badge-<?= htmlspecialchars(strtolower($p['category'])) ?> text-white small">
                        <?= htmlspecialchars(ucfirst($p['category'])) ?>
                    </span>
                    <span class="badge rounded-pill badge-<?= htmlspecialchars(str_replace(' ', '-', strtolower($p['condition']))) ?> text-white small ms-1">
                        <?= htmlspecialchars(ucfirst($p['condition'])) ?>
                    </span>
                </div>
                <div class="product-card__footer mt-auto">
                    <p class="fw-bold text-primary fs-5 mb-0 product-price-tag">&pound;<?= number_format($p['price'], 2) ?></p>
                    <a href="/marketplace/product.php?id=<?= (int)$p['id'] ?>" class="btn btn-outline-primary btn-sm">
                        View
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="text-center mt-5">
    <a href="/marketplace/products.php" class="btn btn-primary btn-lg">
        <i class="bi bi-grid-3x3-gap me-2"></i>View All Listings
    </a>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
