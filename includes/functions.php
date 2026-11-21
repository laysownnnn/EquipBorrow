<?php
/**
 * includes/functions.php
 * ------------------------------------------------------------
 * Shared helper functions (escaping, flash alerts, semantic
 * status & condition pills, category icons).
 * ------------------------------------------------------------
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** Escape output to prevent XSS. Always use when echoing user data. */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/** Redirect to another page and stop the script. */
function redirect(string $url): void
{
    header("Location: $url");
    exit;
}

/** Save a one-time message (shown on the next page load). */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

/** Print and clear the flash message as a modern Bootstrap alert. */
function show_flash(): void
{
    if (!empty($_SESSION['flash'])) {
        $f = $_SESSION['flash'];
        $icons = [
            'success' => 'bi-check-circle-fill',
            'danger'  => 'bi-exclamation-octagon-fill',
            'warning' => 'bi-exclamation-triangle-fill',
            'info'    => 'bi-info-circle-fill',
        ];
        $icon = $icons[$f['type']] ?? 'bi-bell-fill';
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm border-0 rounded-3 mb-4" role="alert">'
           . '<i class="bi ' . $icon . ' fs-5"></i>'
           . '<div>' . e($f['message']) . '</div>'
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        unset($_SESSION['flash']);
    }
}

/** Format a MySQL date (Y-m-d) as "Nov 16, 2026". */
function nice_date(?string $date): string
{
    return $date ? date('M d, Y', strtotime($date)) : '—';
}

/**
 * Returns a semantic status pill for a borrow record.
 * A "Borrowed" record past its due date is automatically shown as "Overdue".
 */
function status_badge(string $status, string $dueDate): string
{
    if ($status === 'Returned') {
        return '<span class="status-pill pill-emerald"><span class="dot"></span>Returned</span>';
    }
    if ($dueDate < date('Y-m-d')) {
        return '<span class="status-pill pill-rose"><span class="dot"></span>Overdue</span>';
    }
    return '<span class="status-pill pill-amber"><span class="dot"></span>Borrowed</span>';
}

/** Semantic pill for equipment condition. */
function condition_badge(?string $condition): string
{
    if (!$condition) return '<span class="text-muted">—</span>';
    $map = [
        'Good'    => 'pill-emerald',
        'Fair'    => 'pill-amber',
        'Damaged' => 'pill-rose',
    ];
    $cls = $map[$condition] ?? 'pill-slate';
    return '<span class="status-pill ' . $cls . '"><span class="dot"></span>' . e($condition) . '</span>';
}

/** Category icon + soft pill for equipment table. */
function category_pill(string $category): string
{
    $icons = [
        'Laptop'     => 'bi-laptop',
        'Projector'  => 'bi-projector',
        'Audio'      => 'bi-mic',
        'Camera'     => 'bi-camera',
        'Sports'     => 'bi-dribbble',
        'Laboratory' => 'bi-eyedropper',
        'Others'     => 'bi-box-seam',
    ];
    $icon = $icons[$category] ?? 'bi-box-seam';
    return '<span class="status-pill pill-slate"><i class="bi ' . $icon . '"></i>' . e($category) . '</span>';
}
