<?php
session_start();
require_once '../includes/session_check.php';
require_once '../includes/csrf.php';
require_once '../db/conn.php';
require_once '../includes/marketplace_relations.php';
csrf_verify();

$product_id = (int)($_POST['product_id'] ?? 0);
$rating     = (int)($_POST['rating'] ?? 0);
$comment    = trim($_POST['comment'] ?? '');
$user_id    = (int)$_SESSION['user_id'];

if ($product_id <= 0 || $rating < 1 || $rating > 5) {
    header("Location: /marketplace/product.php?id=$product_id");
    exit();
}

if (mb_strlen($comment) > 1000) {
    $comment = mb_substr($comment, 0, 1000);
}

if (!marketplace_user_has_purchased_product($conn, $user_id, $product_id)) {
    header("Location: /marketplace/product.php?id=$product_id");
    exit();
}

$seller_id = marketplace_product_seller_id($conn, $product_id);
if ($seller_id === null || $seller_id === $user_id) {
    header("Location: /marketplace/product.php?id=$product_id");
    exit();
}

$existing_stmt = mysqli_prepare($conn, "SELECT 1 FROM reviews WHERE product_id = ? AND user_id = ? LIMIT 1");
mysqli_stmt_bind_param($existing_stmt, 'ii', $product_id, $user_id);
mysqli_stmt_execute($existing_stmt);
$existing_res = mysqli_stmt_get_result($existing_stmt);
$already_reviewed = (bool)mysqli_fetch_assoc($existing_res);
mysqli_stmt_close($existing_stmt);

if ($already_reviewed) {
    header("Location: /marketplace/product.php?id=$product_id#reviews");
    exit();
}

$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO reviews (product_id, user_id, rating, comment, created_at)
     VALUES (?, ?, ?, ?, NOW())"
);
mysqli_stmt_bind_param($stmt, 'iiis', $product_id, $user_id, $rating, $comment);
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: /marketplace/product.php?id=$product_id#reviews");
exit();
