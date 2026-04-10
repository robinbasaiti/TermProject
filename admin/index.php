<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: /marketplace/login.php");
    exit();
}
require_once '../db/conn.php';
require_once '../includes/order_schema.php';

$total_users    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM users"))['c'];
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM products"))['c'];
$total_orders   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders"))['c'];
$total_column   = marketplace_order_total_column($conn);
$total_revenue  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM($total_column),0) AS c FROM orders"))['c'];
?>
<?php require_once '../includes/header.php'; ?>

<section class="page-intro mb-4">
    <div>
        <span class="page-intro__eyebrow">Management overview</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-speedometer2 me-2 text-primary"></i>Admin Dashboard</h2>
        <p class="mb-0 text-muted">Monitor users, listings, orders, and revenue from one control panel.</p>
    </div>
</section>

<div class="row g-4 mb-5">
    <div class="col-6 col-lg-3">
        <div class="card stat-card users border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Users</p>
                        <h2 class="fw-bold mb-0"><?= number_format($total_users) ?></h2>
                    </div>
                    <i class="bi bi-people fs-2 text-primary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card products border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Products</p>
                        <h2 class="fw-bold mb-0"><?= number_format($total_products) ?></h2>
                    </div>
                    <i class="bi bi-box-seam fs-2 text-success opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card orders border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Orders</p>
                        <h2 class="fw-bold mb-0"><?= number_format($total_orders) ?></h2>
                    </div>
                    <i class="bi bi-cart-check fs-2 text-warning opacity-50"></i>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card stat-card revenue border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted small mb-1">Total Revenue</p>
                        <h2 class="fw-bold mb-0">&pound;<?= number_format($total_revenue, 2) ?></h2>
                    </div>
                    <i class="bi bi-currency-pound fs-2 opacity-50 revenue-icon"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-4">
        <a href="/marketplace/admin/products.php" class="card border-0 shadow-sm text-decoration-none text-dark dashboard-link">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="bi bi-box fs-2 text-success"></i>
                <div>
                    <div class="fw-semibold">Manage Products</div>
                    <div class="text-muted small">Edit or delete listings</div>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/marketplace/admin/orders.php" class="card border-0 shadow-sm text-decoration-none text-dark dashboard-link">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="bi bi-clipboard-check fs-2 text-warning"></i>
                <div>
                    <div class="fw-semibold">Manage Orders</div>
                    <div class="text-muted small">Update order statuses</div>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
    <div class="col-md-4">
        <a href="/marketplace/index.php" class="card border-0 shadow-sm text-decoration-none text-dark dashboard-link">
            <div class="card-body d-flex align-items-center gap-3 py-4">
                <i class="bi bi-house fs-2 text-primary"></i>
                <div>
                    <div class="fw-semibold">View Site</div>
                    <div class="text-muted small">Back to front end</div>
                </div>
                <i class="bi bi-chevron-right ms-auto text-muted"></i>
            </div>
        </a>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
