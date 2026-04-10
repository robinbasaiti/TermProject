<?php
session_start();
require_once 'includes/session_check.php';
require_once 'db/conn.php';
require_once 'includes/order_schema.php';

$user_id = (int)$_SESSION['user_id'];
$total_column = marketplace_order_total_column($conn);
$date_column = marketplace_order_date_column($conn);
$date_select = $date_column !== null ? "$date_column AS order_timestamp" : "NULL AS order_timestamp";
$order_sort = $date_column !== null ? $date_column : 'id';

$orders = [];
$stmt = mysqli_prepare($conn,
    "SELECT id, $total_column AS total_amount, status, $date_select FROM orders WHERE user_id = ? ORDER BY $order_sort DESC");
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
while ($row = mysqli_fetch_assoc($res)) {
    $orders[] = $row;
}
mysqli_stmt_close($stmt);

$order_items = [];
foreach ($orders as $order) {
    $oid = (int)$order['id'];
    $order_items[$oid] = [];
    $item_stmt = mysqli_prepare($conn,
        "SELECT oi.quantity, oi.price,
                COALESCE(p.name, CONCAT('Product #', oi.product_id)) AS name,
                oi.product_id,
                p.id AS live_product_id
         FROM order_items oi
         LEFT JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?");
    mysqli_stmt_bind_param($item_stmt, 'i', $oid);
    mysqli_stmt_execute($item_stmt);
    $r = mysqli_stmt_get_result($item_stmt);
    while ($row = mysqli_fetch_assoc($r)) {
        $order_items[$oid][] = $row;
    }
    mysqli_stmt_close($item_stmt);
}

$status_badges = [
    'pending'   => 'warning',
    'confirmed' => 'info',
    'shipped'   => 'primary',
    'delivered' => 'success',
    'cancelled' => 'danger',
];
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Order history</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-box-seam me-2 text-primary"></i>My Orders</h2>
        <p class="mb-0 text-muted">Track previous purchases and check their current status at a glance.</p>
    </div>
</section>

<?php if (isset($_GET['placed'])): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i>Your order was placed successfully!
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if (empty($orders)): ?>
<div class="text-center py-5 text-muted empty-state">
    <i class="bi bi-inbox fs-1"></i>
    <p class="mt-3 fs-5">You haven't placed any orders yet.</p>
    <a href="/marketplace/products.php" class="btn btn-primary">Browse Listings</a>
</div>
<?php else: ?>
<?php foreach ($orders as $order): ?>
<?php $oid = (int)$order['id']; ?>
<div class="card shadow-sm border-0 mb-4 order-card">
    <div class="card-header bg-transparent d-flex justify-content-between align-items-center py-3 flex-wrap gap-2">
        <div>
            <span class="fw-bold">Order #<?= $oid ?></span>
            <span class="text-muted small ms-3">
                <i class="bi bi-calendar me-1"></i><?= $order['order_timestamp'] ? date('d M Y, H:i', strtotime($order['order_timestamp'])) : 'Date unavailable' ?>
            </span>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="fw-bold text-primary">&pound;<?= number_format($order['total_amount'], 2) ?></span>
            <span class="badge bg-<?= $status_badges[$order['status']] ?? 'secondary' ?> px-3 py-2">
                <?= ucfirst($order['status']) ?>
            </span>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-hover mb-0 align-middle small">
            <thead class="table-light">
                <tr>
                    <th>Product</th>
                    <th class="text-center">Qty</th>
                    <th class="text-end">Unit Price</th>
                    <th class="text-end">Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order_items[$oid] as $item): ?>
                <tr>
                    <td>
                        <?php if ($item['live_product_id'] !== null): ?>
                        <a href="/marketplace/product.php?id=<?= (int)$item['product_id'] ?>" class="text-decoration-none">
                            <?= htmlspecialchars($item['name']) ?>
                        </a>
                        <?php else: ?>
                        <?= htmlspecialchars($item['name']) ?>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?= (int)$item['quantity'] ?></td>
                    <td class="text-end">&pound;<?= number_format($item['price'], 2) ?></td>
                    <td class="text-end">&pound;<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php require_once 'includes/footer.php'; ?>
