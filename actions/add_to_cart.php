<?php
session_start();
require_once '../includes/session_check.php';
require_once '../includes/csrf.php';
require_once '../db/conn.php';
csrf_verify();

$product_id = (int)($_POST['product_id'] ?? 0);
$redirect   = trim((string)($_POST['redirect'] ?? ''));

if ($redirect === '' || strpos($redirect, '/marketplace/') !== 0) {
    $redirect = '/marketplace/product.php?id=' . $product_id;
}

if ($product_id <= 0) {
    header("Location: /marketplace/index.php");
    exit();
}

// Verify product exists and has stock
$stmt = mysqli_prepare($conn, "SELECT id, stock, seller_id FROM products WHERE id = ?");
mysqli_stmt_bind_param($stmt, 'i', $product_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = mysqli_fetch_assoc($res);
mysqli_stmt_close($stmt);

if (!$product || $product['stock'] <= 0) {
    header("Location: $redirect");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

if ((int)$product['seller_id'] === $user_id) {
    header("Location: $redirect");
    exit();
}

// Merge duplicate cart rows if the table does not enforce uniqueness.
$stmt2 = mysqli_prepare($conn, "SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ? ORDER BY id ASC");
mysqli_stmt_bind_param($stmt2, 'ii', $user_id, $product_id);
mysqli_stmt_execute($stmt2);
$res2 = mysqli_stmt_get_result($stmt2);
$existing_rows = [];
while ($row = mysqli_fetch_assoc($res2)) {
    $existing_rows[] = $row;
}
mysqli_stmt_close($stmt2);

$transaction_started = false;

try {
    if ($existing_rows) {
        mysqli_begin_transaction($conn);
        $transaction_started = true;

        $primary_id = (int)$existing_rows[0]['id'];
        $existing_qty = 0;
        foreach ($existing_rows as $row) {
            $existing_qty += (int)$row['quantity'];
        }

        $new_qty = max(1, min($existing_qty + 1, (int)$product['stock']));
        $stmt3 = mysqli_prepare($conn, "UPDATE cart SET quantity = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt3, 'ii', $new_qty, $primary_id);
        mysqli_stmt_execute($stmt3);
        mysqli_stmt_close($stmt3);

        for ($i = 1; $i < count($existing_rows); $i++) {
            $duplicate_id = (int)$existing_rows[$i]['id'];
            $cleanup_stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id = ? AND user_id = ?");
            mysqli_stmt_bind_param($cleanup_stmt, 'ii', $duplicate_id, $user_id);
            mysqli_stmt_execute($cleanup_stmt);
            mysqli_stmt_close($cleanup_stmt);
        }

        mysqli_commit($conn);
        $transaction_started = false;
    } elseif ((int)$product['stock'] > 0) {
        $stmt4 = mysqli_prepare($conn, "INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, 1)");
        mysqli_stmt_bind_param($stmt4, 'ii', $user_id, $product_id);
        mysqli_stmt_execute($stmt4);
        mysqli_stmt_close($stmt4);
    }
} catch (Throwable $e) {
    if ($transaction_started) {
        mysqli_rollback($conn);
    }
    error_log('Add to cart failed for user ' . $user_id . ' and product ' . $product_id . ': ' . $e->getMessage());
}

header("Location: $redirect");
exit();
