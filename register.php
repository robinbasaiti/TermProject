<?php
session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: /marketplace/index.php");
    exit();
}

require_once 'db/conn.php';
require_once 'includes/csrf.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($name === '' || strlen($name) > 100 || $email === '' || $password === '' || $confirm === '') {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, 's', $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);
        if (mysqli_stmt_num_rows($stmt) > 0) {
            mysqli_stmt_close($stmt);
            $error = 'An account with that email already exists.';
        } else {
            mysqli_stmt_close($stmt);
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt2  = mysqli_prepare($conn, "INSERT INTO users (name, email, password, is_admin, created_at) VALUES (?, ?, ?, 0, NOW())");
            mysqli_stmt_bind_param($stmt2, 'sss', $name, $email, $hashed);
            if (mysqli_stmt_execute($stmt2)) {
                header("Location: /marketplace/login.php?registered=1");
                exit();
            } else {
                $error = 'Registration failed. Please try again.';
            }
            mysqli_stmt_close($stmt2);
        }
    }
}
?>
<?php require_once 'includes/header.php'; ?>

<section class="auth-shell">
    <div class="row justify-content-center">
        <div class="col-md-6 col-lg-5">
            <div class="card shadow-sm border-0 mt-4 auth-card">
                <div class="card-body p-4 p-lg-5">
                    <span class="page-intro__eyebrow">Create your account</span>
                    <h2 class="card-title text-center mb-4 fw-bold">
                        <i class="bi bi-person-plus me-2 text-primary"></i>Create Account
                    </h2>
                    <p class="text-center text-muted auth-copy mb-4">Start buying, selling, and managing orders from one clean dashboard.</p>
                    <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($error) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <form method="POST" action="" data-validate-form="auth">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label for="name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="name" name="name"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" maxlength="100" required autofocus>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" class="form-control" id="email" name="email"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password <small class="text-muted">(min. 6 characters)</small></label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="6">
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                        </div>
                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-primary btn-lg">Register</button>
                        </div>
                    </form>
                    <hr>
                    <p class="text-center mb-0 text-muted small">
                        Already have an account? <a href="/marketplace/login.php">Login here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once 'includes/footer.php'; ?>
