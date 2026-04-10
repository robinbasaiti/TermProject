<?php
session_start();
require_once 'db/conn.php';

$categories  = ['electronics', 'clothing', 'books', 'cds', 'vinyl', 'collectibles'];
$conditions  = ['new', 'like new', 'used'];

$q         = trim($_GET['q'] ?? '');
$category  = $_GET['category'] ?? '';
$condition = $_GET['condition'] ?? '';
$min_price = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$max_price = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;

$where  = ["p.stock > 0"];
$params = [];
$types  = '';

if ($q !== '') {
    $where[]  = "(p.name LIKE ? OR p.description LIKE ?)";
    $like = "%$q%";
    $params[] = $like;
    $params[] = $like;
    $types   .= 'ss';
}
if ($category !== '' && in_array($category, $categories)) {
    $where[]  = "p.category = ?";
    $params[] = $category;
    $types   .= 's';
}
if ($condition !== '' && in_array($condition, $conditions)) {
    $where[]  = "p.condition = ?";
    $params[] = $condition;
    $types   .= 's';
}
if ($min_price !== null) {
    $where[]  = "p.price >= ?";
    $params[] = $min_price;
    $types   .= 'd';
}
if ($max_price !== null) {
    $where[]  = "p.price <= ?";
    $params[] = $max_price;
    $types   .= 'd';
}

$whereSQL = implode(' AND ', $where);
$sql = "SELECT p.id, p.name, p.price, p.condition, p.category,
               (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image
        FROM products p
        WHERE $whereSQL
        ORDER BY p.created_at DESC";

$stmt = mysqli_prepare($conn, $sql);
$products = [];
if ($stmt) {
    if ($params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $products[] = $row;
    }
    mysqli_stmt_close($stmt);
}

$has_filters = ($q !== '' || $category !== '' || $condition !== '' || $min_price !== null || $max_price !== null);
$cat_icons = [
    'electronics' => 'bi-phone',
    'clothing'    => 'bi-bag-heart',
    'books'       => 'bi-book',
    'cds'         => 'bi-disc',
    'vinyl'       => 'bi-vinyl',
    'collectibles'=> 'bi-gem',
];
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Marketplace directory</span>
        <h2 class="fw-bold mb-1">Browse Listings</h2>
        <p class="mb-0 text-muted">Find preloved items by category, condition, or price.</p>
    </div>
    <span class="page-intro__meta"><?= count($products) ?> result<?= count($products) !== 1 ? 's' : '' ?></span>
</section>

<div class="row g-4">
    <!-- Filter Sidebar -->
    <div class="col-lg-3">
        <div class="filter-sidebar">
            <div class="card shadow-sm border-0">
                <div class="card-body">
                    <h6 class="fw-bold mb-3"><i class="bi bi-funnel me-2"></i>Filters</h6>
                    <form method="GET" action="">
                        <div class="mb-3">
                            <label class="form-label">Search</label>
                            <div class="position-relative">
                                <input type="text" name="q" class="form-control form-control-sm"
                                       placeholder="Keywords..." value="<?= htmlspecialchars($q) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Category</label>
                            <?php foreach ($categories as $cat): ?>
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="radio" name="category"
                                       id="cat_<?= $cat ?>" value="<?= $cat ?>"
                                       <?= $category === $cat ? 'checked' : '' ?>>
                                <label class="form-check-label small" for="cat_<?= $cat ?>">
                                    <i class="<?= $cat_icons[$cat] ?? 'bi-tag' ?> me-1 text-muted"></i>
                                    <?= ucfirst($cat) ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Condition</label>
                            <select name="condition" class="form-select form-select-sm">
                                <option value="">Any</option>
                                <?php foreach ($conditions as $cond): ?>
                                <option value="<?= $cond ?>" <?= $condition === $cond ? 'selected' : '' ?>>
                                    <?= ucfirst($cond) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Price range</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" name="min_price" class="form-control form-control-sm"
                                           min="0" step="0.01" placeholder="Min"
                                           value="<?= htmlspecialchars($_GET['min_price'] ?? '') ?>">
                                </div>
                                <div class="col-6">
                                    <input type="number" name="max_price" class="form-control form-control-sm"
                                           min="0" step="0.01" placeholder="Max"
                                           value="<?= htmlspecialchars($_GET['max_price'] ?? '') ?>">
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-sm w-100 mb-2">
                            <i class="bi bi-search me-1"></i>Apply Filters
                        </button>

                        <?php if ($has_filters): ?>
                        <a href="/marketplace/products.php" class="btn btn-outline-secondary btn-sm w-100">
                            <i class="bi bi-x-lg me-1"></i>Clear All
                        </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Product Grid -->
    <div class="col-lg-9">
        <?php if ($has_filters): ?>
        <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
            <small class="text-muted fw-semibold me-1">Active:</small>
            <?php if ($q !== ''): ?>
            <span class="badge bg-light text-dark border">&ldquo;<?= htmlspecialchars($q) ?>&rdquo; <a href="?<?= http_build_query(array_diff_key($_GET, ['q' => ''])) ?>" class="text-muted ms-1">&times;</a></span>
            <?php endif; ?>
            <?php if ($category !== ''): ?>
            <span class="badge bg-light text-dark border"><?= ucfirst($category) ?> <a href="?<?= http_build_query(array_diff_key($_GET, ['category' => ''])) ?>" class="text-muted ms-1">&times;</a></span>
            <?php endif; ?>
            <?php if ($condition !== ''): ?>
            <span class="badge bg-light text-dark border"><?= ucfirst($condition) ?> <a href="?<?= http_build_query(array_diff_key($_GET, ['condition' => ''])) ?>" class="text-muted ms-1">&times;</a></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($products)): ?>
        <div class="text-center py-5 text-muted empty-state">
            <i class="bi bi-search fs-1 d-block mb-3"></i>
            <p class="fs-5 fw-semibold mb-2">No products found</p>
            <p class="text-muted mb-3">Try adjusting your filters or browse all listings.</p>
            <a href="/marketplace/products.php" class="btn btn-primary btn-sm">Clear Filters</a>
        </div>
        <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-xl-3 g-3 product-grid">
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
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
