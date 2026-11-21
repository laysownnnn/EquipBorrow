<?php
/**
 * includes/header.php
 * ------------------------------------------------------------
 * Page top: <head>, Google Fonts, Bootstrap 5.3, dark-mode
 * initializer, and the slate navigation bar.
 * Before including, set:  $pageTitle  and  $activePage
 * ------------------------------------------------------------
 */
$pageTitle  = $pageTitle  ?? 'Equipment Borrowing System';
$activePage = $activePage ?? '';

/** Helper: adds "active" class to the current nav link */
function nav_active(string $page, string $active): string
{
    return $page === $active ? 'active' : '';
}

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$initial   = strtoupper(substr($adminName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> | EquipBorrow</title>

    <!-- Prevent flash of wrong theme on page load -->
    <script>
        (function () {
            const saved = localStorage.getItem('eb_theme');
            if (saved === 'dark' || saved === 'light') {
                document.documentElement.setAttribute('data-bs-theme', saved);
            }
        })();
    </script>

    <!-- Bootstrap 5.3 + Bootstrap Icons (CDN) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Apple Design System -->
    <link href="assets/css/style.css?v=<?= filemtime(__DIR__ . '/../assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark app-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2 fw-bold text-white me-4" href="index.php">
            <span class="brand-mark"><i class="bi bi-box-seam-fill"></i></span>
            <span>EquipBorrow</span>
        </a>

        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#mainNav" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto gap-1 my-2 my-lg-0">
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('dashboard', $activePage) ?>" href="index.php">
                        <i class="bi bi-grid-1x2-fill"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('equipment', $activePage) ?>" href="equipment.php">
                        <i class="bi bi-cpu-fill"></i> Equipment
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('borrowers', $activePage) ?>" href="borrowers.php">
                        <i class="bi bi-people-fill"></i> Borrowers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= nav_active('borrows', $activePage) ?>" href="borrows.php">
                        <i class="bi bi-arrow-left-right"></i> Borrow Records
                    </a>
                </li>
            </ul>

            <div class="d-flex align-items-center gap-2 pt-2 pt-lg-0">
                <button type="button" class="btn-icon-nav" id="themeToggle"
                        title="Toggle dark / light mode" aria-label="Toggle color theme">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                </button>
                <div class="user-chip">
                    <span class="user-avatar"><?= e($initial) ?></span>
                    <span class="d-none d-sm-inline"><?= e($adminName) ?></span>
                </div>
                <a href="logout.php" class="btn btn-sm btn-outline-light d-inline-flex align-items-center gap-1">
                    <i class="bi bi-box-arrow-right"></i> <span>Logout</span>
                </a>
            </div>
        </div>
    </div>
</nav>

<main class="container py-4">
    <?php show_flash(); ?>
