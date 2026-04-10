<?php
session_start();
if (!isset($_SESSION['user_id']) || empty($_SESSION['is_admin'])) {
    header("Location: /marketplace/login.php");
    exit();
}
require_once '../db/conn.php';
require_once '../includes/csrf.php';
require_once '../includes/product_images.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header("Location: /marketplace/admin/products.php");
    exit();
}

$categories = ['electronics', 'clothing', 'books', 'cds', 'vinyl', 'collectibles'];
$conditions = ['new', 'like new', 'used'];
$error      = '';
$success    = '';
$upload_dir = __DIR__ . '/../uploads';

$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $id);
mysqli_stmt_execute($stmt);
$res     = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product) {
    header("Location: /marketplace/admin/products.php");
    exit();
}

$images = [];
$img_stmt = mysqli_prepare($conn, "SELECT id, image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($img_stmt, 'i', $id);
mysqli_stmt_execute($img_stmt);
$img_res = mysqli_stmt_get_result($img_stmt);
while ($row = mysqli_fetch_assoc($img_res)) {
    $images[] = $row;
}
mysqli_stmt_close($img_stmt);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = $_POST['category'] ?? '';
    $condition   = $_POST['condition'] ?? '';
    $stock       = (int)($_POST['stock'] ?? 0);

    if ($name === '' || strlen($name) > 120 || $description === '' || strlen($description) > 2000 || $price <= 0
        || !in_array($category, $categories, true) || !in_array($condition, $conditions, true) || $stock < 0) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        $moved_paths = [];
        $files_to_delete = [];
        $transaction_started = false;
        $image_lookup = [];
        foreach ($images as $img) {
            $image_lookup[(int)$img['id']] = $img['image_url'];
        }

        $delete_ids = [];
        if (!empty($_POST['delete_images']) && is_array($_POST['delete_images'])) {
            foreach ($_POST['delete_images'] as $img_id) {
                $img_id = (int)$img_id;
                if ($img_id > 0 && isset($image_lookup[$img_id])) {
                    $delete_ids[$img_id] = $img_id;
                }
            }
        }

        try {
            $remaining_slots = max(0, 4 - (count($images) - count($delete_ids)));
            $uploads = marketplace_prepare_product_image_uploads($_FILES['images'] ?? [], $remaining_slots);

            mysqli_begin_transaction($conn);
            $transaction_started = true;

            $stmt2 = mysqli_prepare($conn,
                "UPDATE products SET name=?, description=?, price=?, category=?, `condition`=?, stock=? WHERE id=?");
            mysqli_stmt_bind_param($stmt2, 'ssdssii', $name, $description, $price, $category, $condition, $stock, $id);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            foreach ($delete_ids as $img_id) {
                $delete_stmt = mysqli_prepare($conn, "DELETE FROM product_images WHERE id = ? AND product_id = ?");
                mysqli_stmt_bind_param($delete_stmt, 'ii', $img_id, $id);
                mysqli_stmt_execute($delete_stmt);
                if (mysqli_stmt_affected_rows($delete_stmt) === 1) {
                    $files_to_delete[] = marketplace_resolve_uploaded_image_path($upload_dir, $image_lookup[$img_id]);
                }
                mysqli_stmt_close($delete_stmt);
            }

            $moved_paths = marketplace_store_product_images($conn, $id, $uploads, $upload_dir);

            mysqli_commit($conn);
            $transaction_started = false;
            marketplace_delete_files($files_to_delete);

            $stmt4 = mysqli_prepare($conn, "SELECT * FROM products WHERE id = ?");
            mysqli_stmt_bind_param($stmt4, 'i', $id);
            mysqli_stmt_execute($stmt4);
            $product = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt4));
            mysqli_stmt_close($stmt4);

            $images = [];
            $img_stmt = mysqli_prepare($conn, "SELECT id, image_url FROM product_images WHERE product_id = ? ORDER BY id ASC");
            mysqli_stmt_bind_param($img_stmt, 'i', $id);
            mysqli_stmt_execute($img_stmt);
            $img_res2 = mysqli_stmt_get_result($img_stmt);
            while ($row = mysqli_fetch_assoc($img_res2)) {
                $images[] = $row;
            }
            mysqli_stmt_close($img_stmt);

            $success = 'Product updated successfully.';
        } catch (Throwable $e) {
            if ($transaction_started) {
                mysqli_rollback($conn);
            }
            marketplace_delete_files($moved_paths);
            error_log('Admin product update failed for product ' . $id . ': ' . $e->getMessage());
            $error = 'Unable to save changes right now. Please try again.';
        }
    }
}
?>
<?php require_once '../includes/header.php'; ?>

<div class="page-intro page-intro--compact mb-4 d-flex align-items-center justify-content-between flex-wrap gap-2">
    <div>
        <span class="page-intro__eyebrow">Product editor</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-pencil me-2 text-primary"></i>Edit Product</h2>
        <p class="mb-0 text-muted">Refine listing details, pricing, stock, and gallery images.</p>
    </div>
    <a href="/marketplace/admin/products.php" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-arrow-left me-1"></i>Back
    </a>
</div>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
    <?= htmlspecialchars($error) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
<?php if ($success): ?>
<div class="alert alert-success alert-dismissible fade show">
    <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="card border-0 shadow-sm form-surface">
    <div class="card-body p-4">
        <form method="POST" action="" enctype="multipart/form-data" data-validate-form="listing">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label for="name" class="form-label fw-semibold">Name</label>
                <input type="text" class="form-control" id="name" name="name"
                       value="<?= htmlspecialchars($product['name']) ?>" maxlength="120" required>
            </div>

            <div class="mb-3">
                <label for="description" class="form-label fw-semibold">Description</label>
                <textarea class="form-control" id="description" name="description" rows="4" maxlength="2000" required><?= htmlspecialchars($product['description']) ?></textarea>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-3">
                    <label for="price" class="form-label fw-semibold">Price (&pound;)</label>
                    <input type="number" class="form-control" id="price" name="price"
                           min="0.01" step="0.01" value="<?= number_format($product['price'], 2) ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="stock" class="form-label fw-semibold">Stock</label>
                    <input type="number" class="form-control" id="stock" name="stock"
                           min="0" value="<?= (int)$product['stock'] ?>" required>
                </div>
                <div class="col-md-3">
                    <label for="category" class="form-label fw-semibold">Category</label>
                    <select class="form-select" id="category" name="category" required>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= $product['category'] === $cat ? 'selected' : '' ?>>
                            <?= ucfirst($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="condition" class="form-label fw-semibold">Condition</label>
                    <select class="form-select" id="condition" name="condition" required>
                        <?php foreach ($conditions as $cond): ?>
                        <option value="<?= $cond ?>" <?= $product['condition'] === $cond ? 'selected' : '' ?>>
                            <?= ucfirst($cond) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($images): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Current Images <small class="text-muted">(check to delete)</small></label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($images as $img): ?>
                    <div class="position-relative">
                        <img src="/marketplace/uploads/<?= htmlspecialchars($img['image_url']) ?>"
                             class="rounded border" style="width:90px;height:90px;object-fit:cover;">
                        <div class="form-check position-absolute top-0 start-0 m-1">
                            <input class="form-check-input" type="checkbox"
                                   name="delete_images[]" value="<?= (int)$img['id'] ?>"
                                   id="del_img_<?= (int)$img['id'] ?>">
                            <label class="form-check-label" for="del_img_<?= (int)$img['id'] ?>">
                                <span class="badge bg-danger">Del</span>
                            </label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (count($images) < 4): ?>
            <div class="mb-4">
                <label for="product-images" class="form-label fw-semibold">
                    Add Images <small class="text-muted">(up to <?= 4 - count($images) ?> more)</small>
                </label>
                <input type="file" class="form-control" id="product-images" name="images[]"
                       accept="image/*" multiple>
                <div class="img-preview-container mt-2" id="image-previews"></div>
            </div>
            <?php endif; ?>

            <div class="d-flex gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-save me-1"></i>Save Changes
                </button>
                <a href="/marketplace/product.php?id=<?= $id ?>" target="_blank" class="btn btn-outline-secondary">
                    <i class="bi bi-eye me-1"></i>View Product
                </a>
            </div>
        </form>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
