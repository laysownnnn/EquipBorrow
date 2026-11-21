<?php
/**
 * borrows.php  (READ)
 * ------------------------------------------------------------
 * Lists borrow transactions (JOIN of borrow_records, equipment,
 * and borrowers) with status filter, search, and per-borrower
 * history (?borrower_id=).
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$q          = trim($_GET['q'] ?? '');
$status     = $_GET['status'] ?? '';
$borrowerId = (int)($_GET['borrower_id'] ?? 0);

$sql = "SELECT br.*, e.name AS equipment_name, e.equipment_code,
               b.full_name, b.id_number
        FROM borrow_records br
        JOIN equipment e ON e.equipment_id = br.equipment_id
        JOIN borrowers b ON b.borrower_id = br.borrower_id
        WHERE 1";
$types  = '';
$params = [];

if ($q !== '') {
    $sql     .= ' AND (b.full_name LIKE ? OR b.id_number LIKE ? OR e.name LIKE ? OR e.equipment_code LIKE ?)';
    $like     = "%$q%";
    $types   .= 'ssss';
    array_push($params, $like, $like, $like, $like);
}

if ($status === 'Borrowed') {
    $sql .= " AND br.status = 'Borrowed' AND br.due_date >= CURDATE()";
} elseif ($status === 'Overdue') {
    $sql .= " AND br.status = 'Borrowed' AND br.due_date < CURDATE()";
} elseif ($status === 'Returned') {
    $sql .= " AND br.status = 'Returned'";
}

$borrowerName = '';
if ($borrowerId > 0) {
    $sql     .= ' AND br.borrower_id = ?';
    $types   .= 'i';
    $params[] = $borrowerId;

    $s = $conn->prepare('SELECT full_name FROM borrowers WHERE borrower_id = ?');
    $s->bind_param('i', $borrowerId);
    $s->execute();
    $borrowerName = $s->get_result()->fetch_row()[0] ?? '';
}

$sql .= " ORDER BY br.status = 'Returned', br.due_date ASC, br.borrow_id DESC";

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$records = $stmt->get_result();

$today      = date('Y-m-d');
$pageTitle  = 'Borrow Records';
$activePage = 'borrows';
require 'includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-eyebrow">Lending Log</div>
        <h1 class="page-title">
            Borrow Records
            <?php if ($borrowerName): ?>
                <span class="fs-6 fw-normal text-muted ms-1">&mdash; <?= e($borrowerName) ?></span>
            <?php endif; ?>
        </h1>
    </div>
    <a href="borrow_form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> New Borrow
    </a>
</div>

<form class="filter-bar row g-2 align-items-center mx-0" method="get">
    <?php if ($borrowerId): ?><input type="hidden" name="borrower_id" value="<?= $borrowerId ?>"><?php endif; ?>
    <div class="col-md-8">
        <div class="input-icon-wrap">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" onsearch="this.form.submit()"
                   placeholder="Search borrower name, ID, or equipment..." value="<?= e($q) ?>">
        </div>
    </div>
    <div class="col-md-4">
        <select name="status" class="form-select" aria-label="Filter by status" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <?php foreach (['Borrowed', 'Overdue', 'Returned'] as $s): ?>
                <option <?= $s === $status ? 'selected' : '' ?>><?= $s ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Borrower</th>
                    <th>Equipment</th>
                    <th class="text-center">Qty</th>
                    <th>Borrowed</th>
                    <th>Due Date</th>
                    <th>Returned</th>
                    <th>Status</th>
                    <th class="text-end no-print">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($records->num_rows === 0): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-5">
                        <i class="bi bi-journal-x fs-2 d-block mb-2"></i>
                        No borrow records found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php $rowNum = 1; while ($r = $records->fetch_assoc()):
                $isOverdue = $r['status'] === 'Borrowed' && $r['due_date'] < $today; ?>
                <tr class="<?= $isOverdue ? 'row-overdue' : '' ?>">
                    <td><span class="code-chip">#<?= $rowNum ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($r['full_name']) ?></div>
                        <div class="small text-muted"><?= e($r['id_number']) ?></div>
                    </td>
                    <td>
                        <div class="fw-medium"><?= e($r['equipment_name']) ?></div>
                        <div class="small text-muted">
                            <span class="code-chip"><?= e($r['equipment_code']) ?></span>
                            <?php if ($r['remarks']): ?>
                                &middot; <?= e($r['remarks']) ?>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="text-center fw-semibold">&times;<?= (int)$r['quantity'] ?></td>
                    <td class="text-nowrap small"><?= nice_date($r['borrow_date']) ?></td>
                    <td class="text-nowrap small <?= $isOverdue ? 'text-danger fw-semibold' : '' ?>">
                        <?= nice_date($r['due_date']) ?>
                    </td>
                    <td class="text-nowrap small">
                        <div><?= nice_date($r['return_date']) ?></div>
                        <?php if ($r['return_condition']): ?>
                            <div class="mt-1"><?= condition_badge($r['return_condition']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($r['status'], $r['due_date']) ?></td>
                    <td class="text-end text-nowrap no-print">
                        <?php if ($r['status'] === 'Borrowed'): ?>
                            <a href="borrow_return.php?id=<?= (int)$r['borrow_id'] ?>"
                               class="btn btn-sm btn-primary me-1" title="Mark as returned">
                                <i class="bi bi-box-arrow-in-down"></i> Return
                            </a>
                        <?php endif; ?>
                        <a href="borrow_form.php?id=<?= (int)$r['borrow_id'] ?>"
                           class="btn btn-action btn-outline-secondary" title="Edit" aria-label="Edit record">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="borrow_delete.php" method="post" class="d-inline"
                              onsubmit="return confirm('Delete borrow record #<?= $rowNum ?>?');">
                            <input type="hidden" name="id" value="<?= (int)$r['borrow_id'] ?>">
                            <button class="btn btn-action btn-outline-danger" title="Delete" aria-label="Delete record">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php $rowNum++; endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
