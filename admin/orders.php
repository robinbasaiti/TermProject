<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: /marketplace/login.php");
    exit();
}
require_once '../db/conn.php';
require_once '../includes/csrf.php';
require_once '../includes/order_schema.php';

$statuses = ['pending', 'confirmed', 'shipped', 'delivered', 'cancelled'];
$message  = '';
$total_column = marketplace_order_total_column($conn);
$date_column = marketplace_order_date_column($conn);
$date_select = $date_column !== null ? "o.$date_column AS order_timestamp" : "NULL AS order_timestamp";
$order_sort = $date_column !== null ? "o.$date_column" : 'o.id';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id'], $_POST['status'])) {
    csrf_verify();
    $order_id  = (int)$_POST['order_id'];
    $new_status = $_POST['status'];
    if (in_array($new_status, $statuses)) {
        $stmt = mysqli_prepare($conn, "UPDATE orders SET status = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt, 'si', $new_status, $order_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $message = "Order #$order_id status updated to " . ucfirst($new_status) . '.';
    }
}

$res = mysqli_query($conn,
    "SELECT o.id, o.$total_column AS total_amount, o.status, $date_select, u.name AS user_name, u.email
     FROM orders o
     JOIN users u ON u.id = o.user_id
     ORDER BY $order_sort DESC");

$orders = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $orders[] = $row;
    }
}

$status_colors = [
    'pending'   => 'warning',
    'confirmed' => 'info',
    'shipped'   => 'primary',
    'delivered' => 'success',
    'cancelled' => 'danger',
];
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-intro page-intro--compact mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <span class="page-intro__eyebrow">Order management</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-clipboard-check me-2 text-primary"></i>All Orders</h2>
        <p class="mb-0 text-muted">Update order statuses and monitor recent customer activity.</p>
    </div>
    <a href="/marketplace/admin/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($message) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm table-card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Date</th>
                        <th class="text-end">Total</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                    <tr><td colspan="7" class="text-center text-muted py-4">No orders found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($orders as $order): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$order['id'] ?></td>
                        <td class="fw-semibold"><?= htmlspecialchars($order['user_name']) ?></td>
                        <td class="small text-muted"><?= htmlspecialchars($order['email']) ?></td>
                        <td class="small"><?= $order['order_timestamp'] ? date('d M Y, H:i', strtotime($order['order_timestamp'])) : 'Date unavailable' ?></td>
                        <td class="text-end fw-semibold">&pound;<?= number_format($order['total_amount'], 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $status_colors[$order['status']] ?? 'secondary' ?> px-2 py-1">
                                <?= ucfirst($order['status']) ?>
                            </span>
                        </td>
                        <td class="text-center">
                            <form method="POST" action="" class="d-flex gap-2 justify-content-center align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
                                <select name="status" class="form-select form-select-sm" style="width:130px;">
                                    <?php foreach ($statuses as $s): ?>
                                    <option value="<?= $s ?>" <?= $order['status'] === $s ? 'selected' : '' ?>>
                                        <?= ucfirst($s) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class="bi bi-check-lg"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
