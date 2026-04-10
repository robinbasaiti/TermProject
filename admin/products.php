<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: /marketplace/login.php");
    exit();
}
require_once '../db/conn.php';
require_once '../includes/csrf.php';
require_once '../includes/product_images.php';

$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    csrf_verify();
    $del_id = (int)$_POST['delete_id'];

    $order_check = mysqli_prepare($conn, "SELECT 1 FROM order_items WHERE product_id = ? LIMIT 1");
    mysqli_stmt_bind_param($order_check, 'i', $del_id);
    mysqli_stmt_execute($order_check);
    $order_res = mysqli_stmt_get_result($order_check);
    $has_orders = (bool)mysqli_fetch_assoc($order_res);
    mysqli_stmt_close($order_check);

    if ($has_orders) {
        $message = 'This product appears in past orders and cannot be deleted without breaking order history. Set its stock to 0 instead.';
        $message_type = 'warning';
    } else {
        $files_to_delete = [];
        $upload_dir = __DIR__ . '/../uploads';
        $transaction_started = false;

        try {
            mysqli_begin_transaction($conn);
            $transaction_started = true;

            $img_stmt = mysqli_prepare($conn, "SELECT image_url FROM product_images WHERE product_id = ?");
            mysqli_stmt_bind_param($img_stmt, 'i', $del_id);
            mysqli_stmt_execute($img_stmt);
            $img_res = mysqli_stmt_get_result($img_stmt);
            while ($img = mysqli_fetch_assoc($img_res)) {
                $files_to_delete[] = marketplace_resolve_uploaded_image_path($upload_dir, $img['image_url']);
            }
            mysqli_stmt_close($img_stmt);

            foreach ([
                "DELETE FROM product_images WHERE product_id = ?",
                "DELETE FROM cart WHERE product_id = ?",
                "DELETE FROM reviews WHERE product_id = ?",
                "UPDATE messages SET product_id = NULL WHERE product_id = ?",
                "DELETE FROM products WHERE id = ?",
            ] as $sql) {
                $stmt = mysqli_prepare($conn, $sql);
                mysqli_stmt_bind_param($stmt, 'i', $del_id);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
            }

            mysqli_commit($conn);
            $transaction_started = false;
            marketplace_delete_files($files_to_delete);
            $message = 'Product deleted.';
        } catch (Throwable $e) {
            if ($transaction_started) {
                mysqli_rollback($conn);
            }
            error_log('Admin product delete failed for product ' . $del_id . ': ' . $e->getMessage());
            $message = 'Unable to delete this product right now.';
            $message_type = 'warning';
        }
    }
}

$res = mysqli_query($conn,
    "SELECT p.id, p.name, p.price, p.category, p.condition, p.stock, p.created_at, u.name AS seller_name
     FROM products p
     JOIN users u ON u.id = p.seller_id
     ORDER BY p.created_at DESC");

$products = [];
if ($res) {
    while ($row = mysqli_fetch_assoc($res)) {
        $products[] = $row;
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-intro page-intro--compact mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <span class="page-intro__eyebrow">Listings management</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-box-seam me-2 text-primary"></i>All Products</h2>
        <p class="mb-0 text-muted">Review every listing and make quick edits or removals.</p>
    </div>
    <a href="/marketplace/admin/index.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Dashboard
    </a>
</div>

<?php if ($message): ?>
<div class="alert alert-<?= $message_type === 'warning' ? 'warning' : 'success' ?> alert-dismissible fade show">
    <?= htmlspecialchars($message) ?>
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
                        <th>Name</th>
                        <th>Seller</th>
                        <th>Category</th>
                        <th>Condition</th>
                        <th class="text-end">Price</th>
                        <th class="text-center">Stock</th>
                        <th>Listed</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($products)): ?>
                    <tr><td colspan="9" class="text-center text-muted py-4">No products found.</td></tr>
                    <?php else: ?>
                    <?php foreach ($products as $p): ?>
                    <tr>
                        <td class="text-muted small"><?= (int)$p['id'] ?></td>
                        <td>
                            <a href="/marketplace/product.php?id=<?= (int)$p['id'] ?>" target="_blank"
                               class="text-decoration-none fw-semibold">
                                <?= htmlspecialchars($p['name']) ?>
                            </a>
                        </td>
                        <td class="small"><?= htmlspecialchars($p['seller_name']) ?></td>
                        <td>
                            <span class="badge rounded-pill badge-<?= strtolower($p['category']) ?> text-white">
                                <?= ucfirst($p['category']) ?>
                            </span>
                        </td>
                        <td class="small"><?= ucfirst($p['condition']) ?></td>
                        <td class="text-end fw-semibold">&pound;<?= number_format($p['price'], 2) ?></td>
                        <td class="text-center">
                            <span class="badge bg-<?= $p['stock'] > 0 ? 'success' : 'danger' ?>">
                                <?= (int)$p['stock'] ?>
                            </span>
                        </td>
                        <td class="small text-muted"><?= date('d M Y', strtotime($p['created_at'])) ?></td>
                        <td class="text-center">
                            <a href="/marketplace/admin/edit_product.php?id=<?= (int)$p['id'] ?>"
                               class="btn btn-sm btn-outline-primary me-1">
                                <i class="bi bi-pencil"></i>
                            </a>
                            <form method="POST" action="" class="d-inline"
                                  data-confirm="Delete '<?= htmlspecialchars($p['name']) ?>'? This cannot be undone.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= (int)$p['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="bi bi-trash"></i>
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
