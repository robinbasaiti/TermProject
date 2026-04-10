<?php

function marketplace_product_seller_id(mysqli $conn, int $product_id): ?int
{
    $stmt = mysqli_prepare($conn, "SELECT seller_id FROM products WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $row ? (int)$row['seller_id'] : null;
}

function marketplace_user_has_purchased_product(mysqli $conn, int $user_id, int $product_id): bool
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ?
           AND oi.product_id = ?
           AND (o.status IS NULL OR o.status <> 'cancelled')
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $product_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_purchase = (bool)mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $has_purchase;
}

function marketplace_users_have_message_history(mysqli $conn, int $user_a, int $user_b): bool
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM messages
         WHERE (sender_id = ? AND receiver_id = ?)
            OR (sender_id = ? AND receiver_id = ?)
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_history = (bool)mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $has_history;
}

function marketplace_users_have_order_relationship(mysqli $conn, int $user_a, int $user_b): bool
{
    $stmt = mysqli_prepare(
        $conn,
        "SELECT 1
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         JOIN products p ON p.id = oi.product_id
         WHERE (o.status IS NULL OR o.status <> 'cancelled')
           AND ((o.user_id = ? AND p.seller_id = ?) OR (o.user_id = ? AND p.seller_id = ?))
         LIMIT 1"
    );
    mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $has_relationship = (bool)mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $has_relationship;
}

function marketplace_can_message_user(mysqli $conn, int $sender_id, int $receiver_id, ?int $product_id = null): bool
{
    if ($sender_id <= 0 || $receiver_id <= 0 || $sender_id === $receiver_id) {
        return false;
    }

    if (marketplace_users_have_message_history($conn, $sender_id, $receiver_id)) {
        return true;
    }

    if ($product_id !== null && $product_id > 0) {
        $seller_id = marketplace_product_seller_id($conn, $product_id);
        if ($seller_id !== null && $seller_id === $receiver_id && $seller_id !== $sender_id) {
            return true;
        }
    }

    return marketplace_users_have_order_relationship($conn, $sender_id, $receiver_id);
}
