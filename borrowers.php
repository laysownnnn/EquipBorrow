<?php
/**
 * borrowers.php  (READ)
 * ------------------------------------------------------------
 * Lists borrowers with search, plus how many borrow records
 * each one currently has open (correlated subquery).
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$q = trim($_GET['q'] ?? '');

$sql = "SELECT b.*,
               (SELECT COUNT(*) FROM borrow_records br
                 WHERE br.borrower_id = b.borrower_id AND br.status = 'Borrowed') AS active_borrows,
               (SELECT GROUP_CONCAT(CONCAT(e.name, ' ×', br.quantity) SEPARATOR ', ')
                  FROM borrow_records br
                  JOIN equipment e ON e.equipment_id = br.equipment_id
                 WHERE br.borrower_id = b.borrower_id AND br.status = 'Borrowed') AS borrowed_items
        FROM borrowers b";
$params = [];
if ($q !== '') {
    $sql   .= ' WHERE b.full_name LIKE ? OR b.id_number LIKE ? OR b.department LIKE ?';
    $like   = "%$q%";
    $params = [$like, $like, $like];
}
$sql .= ' ORDER BY b.full_name';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param('sss', ...$params);
}
$stmt->execute();
$borrowers = $stmt->get_result();

$pageTitle  = 'Borrowers';
$activePage = 'borrowers';
require 'includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-eyebrow">Directory</div>
        <h1 class="page-title">Borrowers</h1>
    </div>
    <a href="borrower_form.php" class="btn btn-primary">
        <i class="bi bi-person-plus"></i> Add Borrower
    </a>
</div>

<form class="filter-bar row g-2 align-items-center mx-0" method="get">
    <div class="col-md-10">
        <div class="input-icon-wrap">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" onsearch="this.form.submit()"
                   placeholder="Search by name, school ID number, or department..." value="<?= e($q) ?>">
        </div>
    </div>
    <div class="col-md-2 d-grid">
        <button class="btn btn-primary"><i class="bi bi-search"></i> Search</button>
    </div>
</form>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>ID Number</th>
                    <th>Full Name</th>
                    <th>Role</th>
                    <th>Department / Course</th>
                    <th>Contact</th>
                    <th class="text-center">On Hand</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($borrowers->num_rows === 0): ?>
                <tr>
                    <td colspan="7" class="text-center text-muted py-5">
                        <i class="bi bi-people fs-2 d-block mb-2"></i>
                        No borrowers found.
                    </td>
                </tr>
            <?php endif; ?>

            <?php while ($row = $borrowers->fetch_assoc()):
                $roleClass = $row['borrower_type'] === 'Faculty' ? 'pill-indigo' : 'pill-slate';
            ?>
                <tr>
                    <td><span class="code-chip"><?= e($row['id_number']) ?></span></td>
                    <td class="fw-semibold"><?= e($row['full_name']) ?></td>
                    <td><span class="status-pill <?= $roleClass ?>"><?= e($row['borrower_type']) ?></span></td>
                    <td><?= e($row['department']) ?></td>
                    <td>
                        <div><?= e($row['contact_no'] ?: '—') ?></div>
                        <?php if ($row['email']): ?>
                            <div class="small text-muted"><?= e($row['email']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($row['active_borrows'] > 0): ?>
                            <span class="status-pill pill-amber"><span class="dot"></span><?= (int)$row['active_borrows'] ?> active</span>
                            <?php if (!empty($row['borrowed_items'])): ?>
                                <div class="small text-muted mt-1"><?= e($row['borrowed_items']) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-muted small">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a href="borrows.php?borrower_id=<?= (int)$row['borrower_id'] ?>"
                           class="btn btn-action btn-outline-secondary" title="Borrow history" aria-label="View borrow history">
                            <i class="bi bi-clock-history"></i>
                        </a>
                        <a href="borrower_form.php?id=<?= (int)$row['borrower_id'] ?>"
                           class="btn btn-action btn-outline-secondary" title="Edit" aria-label="Edit borrower">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="borrower_delete.php" method="post" class="d-inline"
                              onsubmit="return confirm('Delete this borrower? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?= (int)$row['borrower_id'] ?>">
                            <button class="btn btn-action btn-outline-danger" title="Delete" aria-label="Delete borrower">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
