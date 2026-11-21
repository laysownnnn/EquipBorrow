<?php
/**
 * index.php  (Dashboard)
 * ------------------------------------------------------------
 * Shows KPI summary cards, overdue items, and recent activity.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

// ---- Summary numbers (SELECT with aggregate functions) ----
$stats = $conn->query("
    SELECT
        (SELECT COALESCE(SUM(quantity_total), 0)     FROM equipment WHERE item_condition NOT IN ('Lost','Retired','Disposed')) AS total_units,
        (SELECT COALESCE(SUM(quantity_available), 0) FROM equipment WHERE item_condition NOT IN ('Lost','Retired','Disposed')) AS available_units,
        (SELECT COUNT(*) FROM borrow_records WHERE status = 'Borrowed') AS active_borrows,
        (SELECT COUNT(*) FROM borrow_records WHERE status = 'Borrowed' AND due_date < CURDATE()) AS overdue
")->fetch_assoc();

// ---- Overdue list (JOIN across 3 tables) ----
$overdue = $conn->query("
    SELECT br.borrow_id, br.quantity, br.due_date,
           e.name AS equipment_name, e.equipment_code,
           b.full_name, b.contact_no,
           DATEDIFF(CURDATE(), br.due_date) AS days_late
    FROM borrow_records br
    JOIN equipment e ON e.equipment_id = br.equipment_id
    JOIN borrowers b ON b.borrower_id = br.borrower_id
    WHERE br.status = 'Borrowed' AND br.due_date < CURDATE()
    ORDER BY br.due_date ASC
");

// ---- 5 most recent transactions ----
$recent = $conn->query("
    SELECT br.borrow_id, br.borrow_date, br.due_date, br.status, br.quantity,
           e.name AS equipment_name, b.full_name
    FROM borrow_records br
    JOIN equipment e ON e.equipment_id = br.equipment_id
    JOIN borrowers b ON b.borrower_id = br.borrower_id
    ORDER BY br.borrow_id DESC
    LIMIT 5
");

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
require 'includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-eyebrow">Overview &amp; Live Metrics</div>
        <h1 class="page-title">Dashboard</h1>
        <div class="text-muted small mt-1"><i class="bi bi-calendar3 me-1"></i><?= date('l, F d, Y') ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="equipment_form.php" class="btn btn-outline-secondary">
            <i class="bi bi-plus-lg"></i> Add Equipment
        </a>
        <a href="borrow_form.php" class="btn btn-primary">
            <i class="bi bi-arrow-up-right-circle"></i> New Borrow
        </a>
    </div>
</div>

<!-- KPI Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Available Units</div>
                    <div class="kpi-value"><?= (int)$stats['available_units'] ?> <span class="fs-5 text-muted fw-normal">/ <?= (int)$stats['total_units'] ?></span></div>
                    <div class="kpi-sub">Ready on the shelf</div>
                </div>
                <span class="kpi-icon emerald"><i class="bi bi-check2-circle"></i></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Currently Borrowed</div>
                    <div class="kpi-value"><?= (int)$stats['active_borrows'] ?></div>
                    <div class="kpi-sub">Active open loans</div>
                </div>
                <span class="kpi-icon amber"><i class="bi bi-arrow-left-right"></i></span>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="kpi-card">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Overdue</div>
                    <div class="kpi-value <?= $stats['overdue'] > 0 ? 'text-danger' : '' ?>"><?= (int)$stats['overdue'] ?></div>
                    <div class="kpi-sub">Past due date</div>
                </div>
                <span class="kpi-icon rose"><i class="bi bi-exclamation-triangle"></i></span>
            </div>
        </div>
    </div>
</div>

<div class="row g-4">
    <!-- Overdue items -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-alarm text-danger me-1"></i> Overdue Items</span>
                <span class="status-pill pill-rose"><?= (int)$stats['overdue'] ?> Needs Attention</span>
            </div>
            <div class="card-body p-0">
                <?php if ($overdue->num_rows === 0): ?>
                    <div class="text-center py-5 px-3">
                        <div class="kpi-icon emerald mx-auto mb-2"><i class="bi bi-check2-all"></i></div>
                        <div class="fw-semibold">All caught up!</div>
                        <div class="text-muted small">No equipment is past its due date.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr><th>Borrower</th><th>Equipment</th><th>Due</th><th>Late</th><th class="text-end">Action</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($row = $overdue->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= e($row['full_name']) ?></div>
                                        <div class="small text-muted"><?= e($row['contact_no'] ?: 'No phone') ?></div>
                                    </td>
                                    <td>
                                        <div class="fw-medium"><?= e($row['equipment_name']) ?> <span class="text-muted">&times;<?= (int)$row['quantity'] ?></span></div>
                                        <span class="code-chip mt-1"><?= e($row['equipment_code']) ?></span>
                                    </td>
                                    <td class="text-nowrap small"><?= nice_date($row['due_date']) ?></td>
                                    <td><span class="status-pill pill-rose"><span class="dot"></span><?= (int)$row['days_late'] ?>d late</span></td>
                                    <td class="text-end">
                                        <a href="borrow_return.php?id=<?= (int)$row['borrow_id'] ?>" class="btn btn-sm btn-primary">
                                            Return
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent activity -->
    <div class="col-lg-6">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-clock-history text-muted me-1"></i> Recent Transactions</span>
                <a href="borrows.php" class="small text-decoration-none fw-semibold">View all &rarr;</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr><th>#</th><th>Borrower</th><th>Equipment</th><th>Date</th><th>Status</th></tr>
                        </thead>
                        <tbody>
                        <?php $rowNum = 1; while ($row = $recent->fetch_assoc()): ?>
                            <tr>
                                <td><span class="code-chip">#<?= $rowNum++ ?></span></td>
                                <td class="fw-medium"><?= e($row['full_name']) ?></td>
                                <td><?= e($row['equipment_name']) ?> <span class="text-muted small">&times;<?= (int)$row['quantity'] ?></span></td>
                                <td class="text-nowrap small text-muted"><?= nice_date($row['borrow_date']) ?></td>
                                <td><?= status_badge($row['status'], $row['due_date']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
