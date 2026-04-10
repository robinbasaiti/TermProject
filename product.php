<?php
session_start();
require_once 'db/conn.php';
require_once 'includes/csrf.php';
require_once 'includes/marketplace_relations.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: /marketplace/products.php");
    exit();
}

$stmt = mysqli_prepare($conn,
    "SELECT p.*, u.name AS seller_name, u.id AS seller_id
     FROM products p
     JOIN users u ON u.id = p.seller_id
     WHERE p.id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: /marketplace/products.php");
    exit();
}

$images = [];
$img_stmt = mysqli_prepare($conn, "SELECT image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($img_stmt, 'i', $id);
mysqli_stmt_execute($img_stmt);
$img_res = mysqli_stmt_get_result($img_stmt);
while ($row = mysqli_fetch_assoc($img_res)) {
    $images[] = $row['image_url'];
}
mysqli_stmt_close($img_stmt);

$reviews = [];
$avg_rating = 0;
$rev_stmt = mysqli_prepare($conn,
    "SELECT r.rating, r.comment, r.created_at, u.name AS reviewer_name
     FROM reviews r
     JOIN users u ON u.id = r.user_id
     WHERE r.product_id = ?
     ORDER BY r.created_at DESC");
mysqli_stmt_bind_param($rev_stmt, 'i', $id);
mysqli_stmt_execute($rev_stmt);
$rev_res = mysqli_stmt_get_result($rev_stmt);
while ($row = mysqli_fetch_assoc($rev_res)) {
    $reviews[] = $row;
}
mysqli_stmt_close($rev_stmt);
if ($reviews) {
    $avg_rating = array_sum(array_column($reviews, 'rating')) / count($reviews);
}

$already_reviewed = false;
if (isset($_SESSION['user_id'])) {
    $uid = (int)$_SESSION['user_id'];
    $chk_stmt = mysqli_prepare($conn, "SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
    mysqli_stmt_bind_param($chk_stmt, 'ii', $id, $uid);
    mysqli_stmt_execute($chk_stmt);
    $chk = mysqli_stmt_get_result($chk_stmt);
    $already_reviewed = (bool)mysqli_fetch_assoc($chk);
    mysqli_stmt_close($chk_stmt);
}

$current_user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
$is_seller = $current_user_id > 0 && (int)$product['seller_id'] === $current_user_id;
$can_review = false;
$review_notice = '';
if ($current_user_id > 0) {
    if ($is_seller) {
        $review_notice = 'You cannot review your own listing.';
    } elseif ($already_reviewed) {
        $review_notice = 'You have already reviewed this product.';
    } elseif (marketplace_user_has_purchased_product($conn, $current_user_id, $id)) {
        $can_review = true;
    } else {
        $review_notice = 'Only buyers who purchased this item can leave a review.';
    }
}

function stars(float $rating): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        if ($rating >= $i) {
            $out .= '<i class="bi bi-star-fill"></i>';
        } elseif ($rating >= $i - 0.5) {
            $out .= '<i class="bi bi-star-half"></i>';
        } else {
            $out .= '<i class="bi bi-star"></i>';
        }
    }
    return $out;
}
?>
<?php require_once 'includes/header.php'; ?>

<?php if (isset($_GET['listed'])): ?>
<div class="alert alert-success alert-dismissible fade show mb-4">
    <i class="bi bi-check-circle me-2"></i>Your listing is live.
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<nav aria-label="breadcrumb" class="mb-3">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="/marketplace/index.php">Home</a></li>
        <li class="breadcrumb-item"><a href="/marketplace/products.php">Browse</a></li>
        <li class="breadcrumb-item active"><?= htmlspecialchars($product['name']) ?></li>
    </ol>
</nav>

<div class="row g-5 align-items-start product-layout">
    <div class="col-md-6">
        <?php if ($images): ?>
        <div id="productCarousel" class="carousel slide shadow rounded-3 overflow-hidden product-gallery-card" data-bs-ride="carousel">
            <div class="carousel-inner">
                <?php foreach ($images as $i => $img): ?>
                <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                    <img src="/marketplace/uploads/<?= htmlspecialchars($img) ?>"
                         class="d-block w-100" style="height:380px;object-fit:cover;"
                         alt="Product image <?= $i + 1 ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <?php if (count($images) > 1): ?>
            <button class="carousel-control-prev" type="button" data-bs-target="#productCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#productCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon"></span>
            </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="rounded-3 shadow product-gallery-card no-image-placeholder" style="height:380px;">
            <i class="bi bi-image" style="font-size:4rem;"></i>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-md-6">
        <div class="product-summary-card">
            <div class="mb-2">
                <span class="badge rounded-pill badge-<?= htmlspecialchars(strtolower($product['category'])) ?> text-white me-2">
                    <?= htmlspecialchars(ucfirst($product['category'])) ?>
                </span>
                <span class="badge rounded-pill badge-<?= htmlspecialchars(str_replace(' ', '-', strtolower($product['condition']))) ?> text-white">
                    <?= htmlspecialchars(ucfirst($product['condition'])) ?>
                </span>
            </div>
            <h2 class="fw-bold mb-2"><?= htmlspecialchars($product['name']) ?></h2>
            <?php if ($reviews): ?>
            <div class="stars mb-3">
                <?= stars($avg_rating) ?>
                <small class="text-muted ms-1"><?= number_format($avg_rating, 1) ?> (<?= count($reviews) ?> review<?= count($reviews) !== 1 ? 's' : '' ?>)</small>
            </div>
            <?php endif; ?>
            <p class="product-price mb-3">&pound;<?= number_format($product['price'], 2) ?></p>
            <p class="text-muted mb-4"><?= nl2br(htmlspecialchars($product['description'])) ?></p>

            <ul class="list-unstyled mb-4 product-meta">
                <li><i class="bi bi-person me-2 text-primary"></i>Sold by <strong><?= htmlspecialchars($product['seller_name']) ?></strong></li>
                <li><i class="bi bi-box-seam me-2 text-primary"></i>Stock: <?= (int)$product['stock'] ?> available</li>
            </ul>

            <div class="product-actions">
                <?php if ($product['stock'] > 0): ?>
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <?php if ($is_seller): ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle me-2"></i>This is your listing.
                    </div>
                    <?php else: ?>
                    <form method="POST" action="/marketplace/actions/add_to_cart.php" class="d-inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                        <button type="submit" class="btn btn-primary btn-lg me-2">
                            <i class="bi bi-cart-plus me-1"></i>Add to Cart
                        </button>
                    </form>
                    <a href="/marketplace/messages.php?with=<?= (int)$product['seller_id'] ?>&product=<?= (int)$product['id'] ?>"
                       class="btn btn-outline-secondary btn-lg">
                        <i class="bi bi-chat-dots me-1"></i>Contact Seller
                    </a>
                    <?php endif; ?>
                    <?php else: ?>
                    <a href="/marketplace/login.php" class="btn btn-primary btn-lg">
                        <i class="bi bi-box-arrow-in-right me-1"></i>Login to Buy
                    </a>
                    <?php endif; ?>
                <?php else: ?>
                <span class="badge bg-danger fs-6 px-3 py-2"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<hr class="my-5" id="reviews">
<div class="row g-5">
    <div class="col-lg-7">
        <h4 class="fw-bold mb-4"><i class="bi bi-star me-2 text-warning"></i>Reviews</h4>
        <?php if (empty($reviews)): ?>
        <div class="empty-state text-start py-4">
            <p class="text-muted mb-0">No reviews yet. Be the first!</p>
        </div>
        <?php else: ?>
        <?php foreach ($reviews as $rev): ?>
        <div class="card border-0 shadow-sm mb-3 review-card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <strong><?= htmlspecialchars($rev['reviewer_name']) ?></strong>
                    <small class="text-muted"><?= date('d M Y', strtotime($rev['created_at'])) ?></small>
                </div>
                <div class="stars mb-2"><?= stars((float)$rev['rating']) ?></div>
                <?php if ($rev['comment']): ?>
                <p class="mb-0 text-muted"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <div class="col-lg-5">
        <?php if (!isset($_SESSION['user_id'])): ?>
        <div class="card border-0 shadow-sm review-form-card">
            <div class="card-body text-center py-5">
                <p class="text-muted mb-3">Please log in to leave a review.</p>
                <a href="/marketplace/login.php" class="btn btn-primary">Login</a>
            </div>
        </div>
        <?php elseif (!$can_review): ?>
        <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i><?= htmlspecialchars($review_notice) ?></div>
        <?php else: ?>
        <div class="card border-0 shadow-sm review-form-card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Leave a Review</h5>
                <form method="POST" action="/marketplace/actions/submit_review.php">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$product['id'] ?>">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Rating</label>
                        <div class="star-rating">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <input type="radio" name="rating" id="star<?= $i ?>" value="<?= $i ?>">
                            <label for="star<?= $i ?>" title="<?= $i ?> star<?= $i > 1 ? 's' : '' ?>">
                                <i class="bi bi-star-fill"></i>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="comment" class="form-label fw-semibold">Comment <small class="text-muted">(optional)</small></label>
                        <textarea class="form-control" id="comment" name="comment" rows="3"
                                  placeholder="Share your experience&hellip;"></textarea>
                    </div>
                    <button type="submit" class="btn btn-warning w-100">
                        <i class="bi bi-star me-1"></i>Submit Review
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
