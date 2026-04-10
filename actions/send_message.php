<?php
session_start();
require_once '../includes/session_check.php';
require_once '../includes/csrf.php';
require_once '../db/conn.php';
require_once '../includes/marketplace_relations.php';
csrf_verify();

$receiver_id = (int)($_POST['receiver_id'] ?? 0);
$product_id  = (int)($_POST['product_id'] ?? 0) ?: null;
$message     = trim($_POST['message'] ?? '');
$sender_id   = (int)$_SESSION['user_id'];

if ($receiver_id <= 0 || $sender_id === $receiver_id || $message === '') {
    header("Location: /marketplace/messages.php");
    exit();
}

if (mb_strlen($message) > 2000) {
    header("Location: /marketplace/messages.php?with=$receiver_id");
    exit();
}

if (!marketplace_can_message_user($conn, $sender_id, $receiver_id, $product_id)) {
    header("Location: /marketplace/messages.php");
    exit();
}

if ($product_id !== null) {
    $product_stmt = mysqli_prepare($conn, "SELECT id, seller_id FROM products WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($product_stmt, 'i', $product_id);
    mysqli_stmt_execute($product_stmt);
    $product_res = mysqli_stmt_get_result($product_stmt);
    $product = mysqli_fetch_assoc($product_res);
    mysqli_stmt_close($product_stmt);

    if (!$product || (int)$product['seller_id'] !== $receiver_id) {
        $product_id = null;
    }
}

$sql = "INSERT INTO messages (sender_id, receiver_id, product_id, message, created_at) VALUES (?, ?, ?, ?, NOW())";
if ($product_id === null) {
    $stmt = mysqli_prepare(
        $conn,
        "INSERT INTO messages (sender_id, receiver_id, product_id, message, created_at)
         VALUES (?, ?, NULL, ?, NOW())"
    );
    mysqli_stmt_bind_param($stmt, 'iis', $sender_id, $receiver_id, $message);
} else {
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iiis', $sender_id, $receiver_id, $product_id, $message);
}
mysqli_stmt_execute($stmt);
mysqli_stmt_close($stmt);

header("Location: /marketplace/messages.php?with=$receiver_id");
exit();
