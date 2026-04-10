<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get cart item count
$cart_count = 0;
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../db/conn.php';
    $uid = (int)$_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT COALESCE(SUM(quantity), 0) AS total FROM cart WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $uid);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    $cart_count = (int)($row['total'] ?? 0);
    mysqli_stmt_close($stmt);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MarketPlace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Outfit:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/marketplace/css/style.css" rel="stylesheet">
</head>
<body class="site-body">
<nav class="navbar navbar-expand-lg sticky-top marketplace-nav">
    <div class="container nav-shell">
        <a class="navbar-brand brand-mark" href="/marketplace/index.php">
            <span class="brand-icon"><i class="bi bi-shop-window"></i></span>
            <span class="brand-text">MarketPlace</span>
        </a>

        <!-- Search bar — desktop only -->
        <form action="/marketplace/products.php" method="GET" class="nav-search d-none d-lg-flex">
            <div class="nav-search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="q" class="nav-search-input" placeholder="Search for items...">
            </div>
        </form>

        <div class="nav-right">
            <a class="nav-icon-btn" href="/marketplace/cart.php" title="Cart">
                <i class="bi bi-bag"></i>
                <?php if ($cart_count > 0): ?>
                <span class="cart-badge" id="cart-count"><?= $cart_count ?></span>
                <?php else: ?>
                <span class="cart-badge d-none" id="cart-count">0</span>
                <?php endif; ?>
            </a>

            <?php if (isset($_SESSION['user_id'])): ?>
            <a class="nav-icon-btn d-none d-lg-inline-flex" href="/marketplace/messages.php" title="Messages">
                <i class="bi bi-chat-dots"></i>
            </a>
            <?php endif; ?>

            <button class="navbar-toggler ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <span class="navbar-toggler-icon"></span>
            </button>
        </div>

        <div class="collapse navbar-collapse" id="mainNav">
            <!-- Mobile search -->
            <form action="/marketplace/products.php" method="GET" class="d-lg-none my-3">
                <input type="text" name="q" class="form-control" placeholder="Search for items...">
            </form>

            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="/marketplace/products.php"><i class="bi bi-grid-3x3-gap me-1"></i>Browse</a>
                </li>
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item">
                    <a class="nav-link" href="/marketplace/sell.php"><i class="bi bi-plus-circle me-1"></i>Sell</a>
                </li>
                <li class="nav-item d-lg-none">
                    <a class="nav-link" href="/marketplace/messages.php"><i class="bi bi-chat-dots me-1"></i>Messages</a>
                </li>
                <?php endif; ?>
            </ul>

            <ul class="navbar-nav mb-2 mb-lg-0 align-items-lg-center">
                <?php if (isset($_SESSION['user_id'])): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($_SESSION['user_name'] ?? 'Account') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/marketplace/orders.php"><i class="bi bi-box me-2"></i>My Orders</a></li>
                        <?php if (!empty($_SESSION['is_admin'])): ?>
                        <li><a class="dropdown-item" href="/marketplace/admin/index.php"><i class="bi bi-gear me-2"></i>Admin Panel</a></li>
                        <?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="/marketplace/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Logout</a></li>
                    </ul>
                </li>
                <?php else: ?>
                <li class="nav-item">
                    <a class="nav-link" href="/marketplace/login.php">Login</a>
                </li>
                <li class="nav-item ms-lg-1">
                    <a class="btn btn-primary btn-sm" href="/marketplace/register.php">Sign Up</a>
                </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="site-main py-4">
<div class="container page-shell">
