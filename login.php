<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: /marketplace/index.php");
    exit();
}

require_once 'db/conn.php';
require_once 'includes/csrf.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please fill in all fields.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id, name, password, is_admin FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);
        mysqli_stmt_close($stmt);

        if ($user && password_verify($password, $user['password'])) {
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['is_admin']  = (int)$user['is_admin'];
            header("Location: /marketplace/index.php");
            exit();
        } else {
            $error = 'Invalid email or password.';
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="auth-shell">
    <div class="row justify-content-center">
        <div class="col-md-5 col-lg-4">
            <div class="card shadow-sm border-0 mt-4 auth-card">
                <div class="card-body p-4 p-lg-5">
                    <span class="page-intro__eyebrow">Welcome back</span>
                    <h2 class="card-title text-center mb-4 fw-bold">
                        <i class="bi bi-box-arrow-in-right me-2 text-primary"></i>Login
                    </h2>
                    <p class="text-center text-muted auth-copy mb-4">Access your cart, messages, listings, and order history.</p>
                    <?php if (isset($_GET['registered'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>Account created successfully! Please log in.
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="" data-validate-form="auth">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Login</button>
                        </div>
                    </form>
                    <hr>
                    <p class="text-center mb-0 text-muted small">
                        Don't have an account? <a href="/marketplace/register.php">Register here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
