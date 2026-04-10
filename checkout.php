<?php
session_start();
require_once 'includes/session_check.php';
require_once 'db/conn.php';
require_once 'includes/csrf.php';
require_once 'includes/order_schema.php';

$user_id = (int)$_SESSION['user_id'];

function load_checkout_items(mysqli $conn, int $user_id, bool $for_update = false): array
{
    $items = [];
    $total = 0.0;
    $sql = "SELECT MIN(c.id) AS cart_id, SUM(c.quantity) AS quantity, p.id AS product_id, p.name, p.price, p.stock, p.seller_id
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id = ?
            GROUP BY p.id, p.name, p.price, p.stock, p.seller_id
            ORDER BY cart_id ASC";
    if ($for_update) {
        $sql .= " FOR UPDATE";
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
        $total += $row['price'] * $row['quantity'];
    }
    mysqli_stmt_close($stmt);

    return [$items, $total];
}

[$items, $total] = load_checkout_items($conn, $user_id);

if (empty($items)) {
    header("Location: /marketplace/cart.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_order'])) {
    csrf_verify();
    $transaction_started = false;
    try {
        mysqli_begin_transaction($conn);
        $transaction_started = true;
        [$items, $total] = load_checkout_items($conn, $user_id, true);
        if (empty($items)) {
            mysqli_rollback($conn);
            header("Location: /marketplace/cart.php");
            exit();
        }

        foreach ($items as $item) {
            if ($item['quantity'] > $item['stock']) {
                throw new RuntimeException($item['name'] . ' does not have enough stock.');
            }
        }

        $total_column = marketplace_order_total_column($conn);
        $date_column = marketplace_order_date_column($conn);
        $insert_columns = ['user_id', $total_column, 'status'];
        $values_sql = "?, ?, 'pending'";
        if ($date_column !== null) {
            $insert_columns[] = $date_column;
            $values_sql .= ", NOW()";
        }

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO orders (" . implode(', ', $insert_columns) . ") VALUES ($values_sql)"
        );
        mysqli_stmt_bind_param($stmt, 'id', $user_id, $total);
        mysqli_stmt_execute($stmt);
        $order_id = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        foreach ($items as $item) {
            $stmt2 = mysqli_prepare($conn,
                "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt2, 'iiid', $order_id, $item['product_id'], $item['quantity'], $item['price']);
            mysqli_stmt_execute($stmt2);
            mysqli_stmt_close($stmt2);

            $stmt3 = mysqli_prepare($conn,
                "UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
            mysqli_stmt_bind_param($stmt3, 'iii', $item['quantity'], $item['product_id'], $item['quantity']);
            mysqli_stmt_execute($stmt3);
            if (mysqli_stmt_affected_rows($stmt3) !== 1) {
                throw new RuntimeException('Stock changed before checkout completed. Please review your cart and try again.');
            }
            mysqli_stmt_close($stmt3);
        }

        $stmt4 = mysqli_prepare($conn, "DELETE FROM cart WHERE user_id = ?");
        mysqli_stmt_bind_param($stmt4, 'i', $user_id);
        mysqli_stmt_execute($stmt4);
        mysqli_stmt_close($stmt4);

        mysqli_commit($conn);
        $transaction_started = false;
        header("Location: /marketplace/orders.php?placed=1");
        exit();
    } catch (Throwable $e) {
        if ($transaction_started) {
            mysqli_rollback($conn);
        }
        error_log('Checkout failed for user ' . $user_id . ': ' . $e->getMessage());
        $message = $e->getMessage();
        if (stripos($message, 'stock') !== false || stripos($message, 'enough stock') !== false) {
            $error = $message;
        } else {
            $error = 'Order failed. Please try again.';
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="page-intro page-intro--compact mb-4">
    <div>
        <span class="page-intro__eyebrow">Final review</span>
        <h2 class="fw-bold mb-2"><i class="bi bi-credit-card me-2 text-primary"></i>Checkout</h2>
        <p class="mb-0 text-muted">Confirm the items and total before placing your order.</p>
    </div>
</section>

<?php if ($error): ?>
<div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm border-0 table-card">
            <div class="card-header bg-transparent fw-bold py-3">Order Summary</div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th class="text-center">Qty</th>
                            <th class="text-end">Price</th>
                            <th class="text-end">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td class="text-center"><?= (int)$item['quantity'] ?></td>
                            <td class="text-end">&pound;<?= number_format($item['price'], 2) ?></td>
                            <td class="text-end fw-semibold">&pound;<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr>
                            <td colspan="3" class="text-end fw-bold">Total</td>
                            <td class="text-end fw-bold text-primary fs-5">&pound;<?= number_format($total, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm border-0 summary-card">
            <div class="card-body">
                <h5 class="fw-bold mb-3">Confirm Your Order</h5>
                <p class="text-muted small mb-4">By clicking Confirm Order you agree to purchase the above items. This is a demo &ndash; no real payment is processed.</p>
                <form method="POST" action="" data-validate-form="checkout">
                        <?= csrf_field() ?>
                    <button type="submit" name="confirm_order" class="btn btn-success btn-lg w-100">
                        <i class="bi bi-check-circle me-2"></i>Confirm Order
                    </button>
                </form>
                <a href="/marketplace/cart.php" class="btn btn-outline-secondary w-100 mt-2">
                    <i class="bi bi-arrow-left me-1"></i>Back to Cart
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
