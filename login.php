<?php
/**
 * login.php
 * ------------------------------------------------------------
 * Centered minimal admin login page. Verifies username &
 * password against the `admins` table using password_verify().
 * ------------------------------------------------------------
 */
require_once 'includes/functions.php';
require_once 'db_connect.php';

if (!empty($_SESSION['admin_id'])) {
    redirect('index.php');
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare('SELECT admin_id, full_name, password_hash FROM admins WHERE username = ?');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $admin = $stmt->get_result()->fetch_assoc();

    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id']   = $admin['admin_id'];
        $_SESSION['admin_name'] = $admin['full_name'];
        redirect('index.php');
    } else {
        $error = 'Invalid username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sign In | EquipBorrow</title>
    <script>
        (function () {
            const saved = localStorage.getItem('eb_theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.setAttribute('data-bs-theme', saved);
            }
        })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css?v=<?= filemtime('assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<div class="login-center d-flex align-items-center justify-content-center min-vh-100 p-3">
    <div class="login-box mx-auto">
        <div class="text-center mb-4">
            <span class="brand-mark mb-3" style="width: 44px; height: 44px; border-radius: 12px; font-size: 1.25rem;">
                <i class="bi bi-box-seam-fill"></i>
            </span>
            <h1 class="h3 fw-bold mb-1">EquipBorrow</h1>
            <p class="text-muted small mb-0">Sign in to your account</p>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 py-2 border-0 mb-3" role="alert">
                <i class="bi bi-exclamation-octagon-fill"></i>
                <div><?= e($error) ?></div>
            </div>
        <?php endif; ?>

        <div class="card p-4">
            <form method="post" novalidate>
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-person"></i>
                        <input type="text" class="form-control" id="username" name="username"
                               value="<?= e($username) ?>" required autofocus>
                    </div>
                </div>
                <div class="mb-4">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon-wrap">
                        <i class="bi bi-lock"></i>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2">
                    Sign In
                </button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
