<?php
session_start();
require_once '../includes/session_check.php';
require_once '../includes/csrf.php';
require_once '../db/conn.php';
csrf_verify();

$cart_id = (int)($_POST['cart_id'] ?? 0);
$product_id = (int)($_POST['product_id'] ?? 0);
$user_id = (int)$_SESSION['user_id'];

if ($product_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE product_id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $product_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
} elseif ($cart_id > 0) {
    $stmt = mysqli_prepare($conn, "DELETE FROM cart WHERE id = ? AND user_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

header("Location: /marketplace/cart.php");
exit();
