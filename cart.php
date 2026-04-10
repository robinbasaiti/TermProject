<?php
session_start();
require_once 'includes/session_check.php';
require_once 'db/conn.php';
require_once 'includes/csrf.php';

$user_id = (int)$_SESSION['user_id'];

$items = [];
$total = 0;
$has_stock_issue = false;
$stmt = mysqli_prepare($conn,
    "SELECT MIN(c.id) AS cart_id, SUM(c.quantity) AS quantity, p.id AS product_id, p.name, p.price, p.stock,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image
      FROM cart c
      JOIN products p ON p.id = c.product_id
      WHERE c.user_id = ?
      GROUP BY p.id, p.name, p.price, p.stock
      ORDER BY cart_id ASC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $row['has_stock_issue'] = ((int)$row['stock'] < (int)$row['quantity']) || ((int)$row['stock'] < 1);
    if ($row['has_stock_issue']) {
        $has_stock_issue = true;
    }
    $items[] = $row;
    $total += $row['price'] * $row['quantity'];
}
mysqli_stmt_close($stmt);
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Ready to order</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-cart3 me-2 text-primary"></i>Your Cart</h2>
        <p class="mb-0 text-muted">Review your selected items before checkout.</p>
    </div>
</section>

<?php if (empty($items)): ?>
<div class="text-center py-5 text-muted empty-state">
    <i class="bi bi-cart-x fs-1"></i>
    <p class="mt-3 fs-5">Your cart is empty.</p>
    <a href="/marketplace/products.php" class="btn btn-primary mt-2">
        <i class="bi bi-grid me-1"></i>Browse Listings
    </a>
</div>
<?php else: ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?php foreach ($items as $item): ?>
        <div class="card mb-3 shadow-sm border-0 cart-line">
            <div class="card-body d-flex gap-3 align-items-center flex-wrap">
                <?php if ($item['image']): ?>
                <img src="/marketplace/uploads/<?= htmlspecialchars($item['image']) ?>"
                     alt="<?= htmlspecialchars($item['name']) ?>"
                     class="rounded" style="width:80px;height:80px;object-fit:cover;">
                <?php else: ?>
                <div class="rounded no-image-placeholder" style="width:80px;height:80px;min-height:auto;">
                    <i class="bi bi-image"></i>
                </div>
                <?php endif; ?>
                <div class="flex-grow-1">
                    <h6 class="fw-semibold mb-1">
                        <a href="/marketplace/product.php?id=<?= (int)$item['product_id'] ?>" class="text-decoration-none text-dark">
                            <?= htmlspecialchars($item['name']) ?>
                        </a>
                    </h6>
                    <span class="text-muted small d-block">Qty: <?= (int)$item['quantity'] ?></span>
                    <?php if ($item['has_stock_issue']): ?>
                    <span class="text-danger small">
                        <?= (int)$item['stock'] > 0 ? 'Only ' . (int)$item['stock'] . ' left in stock.' : 'Currently out of stock.' ?>
                    </span>
                    <?php endif; ?>
                </div>
                <div class="text-end">
                    <p class="fw-bold text-primary mb-1">&pound;<?= number_format($item['price'] * $item['quantity'], 2) ?></p>
                    <small class="text-muted">&pound;<?= number_format($item['price'], 2) ?> each</small>
                </div>
                <form method="POST" action="/marketplace/actions/remove_from_cart.php"
                      data-confirm="Remove this item from your cart?">
                    <?= csrf_field() ?>
                    <input type="hidden" name="product_id" value="<?= (int)$item['product_id'] ?>">
                    <button type="submit" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-trash"></i>
                    </button>
                </form>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 sticky-top summary-card" style="top:96px;">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Order Summary</h5>
                <?php if ($has_stock_issue): ?>
                <div class="alert alert-warning small">
                    One or more items need attention before you can check out.
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Subtotal (<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)</span>
                    <span>&pound;<?= number_format($total, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-3">
                    <span class="text-muted">Shipping</span>
                    <span class="text-success">Free</span>
                </div>
                <hr>
                <div class="d-flex justify-content-between fw-bold fs-5 mb-4">
                    <span>Total</span>
                    <span class="text-primary">&pound;<?= number_format($total, 2) ?></span>
                </div>
                <?php if ($has_stock_issue): ?>
                <button type="button" class="btn btn-primary btn-lg w-100" disabled>
                    <i class="bi bi-exclamation-triangle me-2"></i>Resolve Stock Issues
                </button>
                <?php else: ?>
                <a href="/marketplace/checkout.php" class="btn btn-primary btn-lg w-100">
                    <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
