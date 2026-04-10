<?php
session_start();
require_once 'includes/session_check.php';
require_once 'db/conn.php';
require_once 'includes/csrf.php';
require_once 'includes/product_images.php';

$categories = ['electronics', 'clothing', 'books', 'cds', 'vinyl', 'collectibles'];
$conditions = ['new', 'like new', 'used'];
$upload_dir = __DIR__ . '/uploads';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name        = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $price       = (float)($_POST['price'] ?? 0);
    $category    = $_POST['category'] ?? '';
    $condition   = $_POST['condition'] ?? '';
    $stock       = (int)($_POST['stock'] ?? 1);
    $seller_id   = (int)$_SESSION['user_id'];

    if ($name === '' || strlen($name) > 120 || $description === '' || strlen($description) > 2000
        || $price <= 0 || !in_array($category, $categories, true)
        || !in_array($condition, $conditions, true) || $stock < 1) {
        $error = 'Please fill in all required fields correctly.';
    } else {
        $moved_paths = [];
        $transaction_started = false;

        try {
            $uploads = marketplace_prepare_product_image_uploads($_FILES['images'] ?? [], 4);

            mysqli_begin_transaction($conn);
            $transaction_started = true;

            $stmt = mysqli_prepare($conn,
                "INSERT INTO products (seller_id, name, description, price, category, `condition`, stock, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($stmt, 'issdssi', $seller_id, $name, $description, $price, $category, $condition, $stock);
            mysqli_stmt_execute($stmt);
            $product_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);

            $moved_paths = marketplace_store_product_images($conn, $product_id, $uploads, $upload_dir);

            mysqli_commit($conn);
            $transaction_started = false;
            header("Location: /marketplace/product.php?id=$product_id&listed=1");
            exit();
        } catch (Throwable $e) {
            if ($transaction_started) {
                mysqli_rollback($conn);
            }
            marketplace_delete_files($moved_paths);
            error_log('Listing creation failed for user ' . $seller_id . ': ' . $e->getMessage());
            $error = 'Unable to create the listing right now. Please try again.';
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro mb-4">
    <div>
        <span class="page-intro__eyebrow">Seller workspace</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-tag me-2 text-primary"></i>List an Item for Sale</h2>
        <p class="mb-0 text-muted">Create a polished listing with strong details, a clear price, and up to four product images.</p>
    </div>
</section>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data" class="card shadow-sm border-0 form-surface" data-validate-form="listing">
            <?= csrf_field() ?>
            <div class="card-body p-4">
                <div class="mb-3">
                    <label for="name" class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                           value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" maxlength="120" required>
                </div>

                <div class="mb-3">
                    <label for="description" class="form-label fw-semibold">Description <span class="text-danger">*</span></label>
                    <textarea class="form-control" id="description" name="description" rows="4"
                              maxlength="2000" required><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-4">
                        <label for="price" class="form-label fw-semibold">Price (&pound;) <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="price" name="price"
                               min="0.01" step="0.01" value="<?= htmlspecialchars($_POST['price'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="stock" class="form-label fw-semibold">Stock <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" id="stock" name="stock"
                               min="1" value="<?= htmlspecialchars($_POST['stock'] ?? '1') ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label for="condition" class="form-label fw-semibold">Condition <span class="text-danger">*</span></label>
                        <select class="form-select" id="condition" name="condition" required>
                            <option value="">Select&hellip;</option>
                            <?php foreach ($conditions as $cond): ?>
                            <option value="<?= $cond ?>" <?= (($_POST['condition'] ?? '') === $cond) ? 'selected' : '' ?>>
                                <?= ucfirst($cond) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="category" class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                    <select class="form-select" id="category" name="category" required>
                        <option value="">Select a category&hellip;</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat ?>" <?= (($_POST['category'] ?? '') === $cat) ? 'selected' : '' ?>>
                            <?= ucfirst($cat) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label for="product-images" class="form-label fw-semibold">Images <small class="text-muted">(up to 4, max 5 MB each)</small></label>
                    <input type="file" class="form-control" id="product-images" name="images[]"
                           accept="image/*" multiple>
                    <div class="img-preview-container mt-2" id="image-previews"></div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="bi bi-plus-circle me-2"></i>List Item
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
