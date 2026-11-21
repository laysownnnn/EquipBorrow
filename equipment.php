<?php
/**
 * equipment.php  (READ)
 * ------------------------------------------------------------
 * Lists all equipment with search, category filter, and a
 * visual stock progress meter.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$categories = ['Laptop', 'Projector', 'Audio', 'Camera', 'Sports', 'Laboratory', 'Others'];

$q        = trim($_GET['q'] ?? '');
$category = $_GET['category'] ?? '';

$sql    = 'SELECT * FROM equipment WHERE 1';
$types  = '';
$params = [];

if ($q !== '') {
    $sql     .= ' AND (name LIKE ? OR equipment_code LIKE ?)';
    $like     = "%$q%";
    $types   .= 'ss';
    $params[] = $like;
    $params[] = $like;
}
if (in_array($category, $categories, true)) {
    $sql     .= ' AND category = ?';
    $types   .= 's';
    $params[] = $category;
}
$sql .= ' ORDER BY equipment_code';

$stmt = $conn->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();

$pageTitle  = 'Equipment';
$activePage = 'equipment';
require 'includes/header.php';
?>

<div class="page-header">
    <div>
        <div class="page-eyebrow">Inventory Catalog</div>
        <h1 class="page-title">Equipment</h1>
    </div>
    <a href="equipment_form.php" class="btn btn-primary">
        <i class="bi bi-plus-lg"></i> Add Equipment
    </a>
</div>

<!-- Search / filter toolbar -->
<form class="filter-bar row g-2 align-items-center mx-0" method="get">
    <div class="col-md-8">
        <div class="input-icon-wrap">
            <i class="bi bi-search"></i>
            <input type="search" name="q" class="form-control" onsearch="this.form.submit()"
                   placeholder="Search by equipment name or code..." value="<?= e($q) ?>">
        </div>
    </div>
    <div class="col-md-4">
        <select name="category" class="form-select" aria-label="Filter by category" onchange="this.form.submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<div class="table-card">
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Equipment</th>
                    <th>Category</th>
                    <th>Available</th>
                    <th>Condition</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($items->num_rows === 0): ?>
                <tr>
                    <td colspan="6" class="text-center text-muted py-5">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                        No equipment matched your filter.
                    </td>
                </tr>
            <?php endif; ?>

            <?php while ($row = $items->fetch_assoc()):
                $avail = (int)$row['quantity_available'];
                $total = max(1, (int)$row['quantity_total']);
                $pct   = (int)round(($avail / $total) * 100);
                $pill  = $avail === 0 ? 'pill-rose' : ($avail < $total ? 'pill-amber' : 'pill-emerald');
                $bar   = $avail === 0 ? '#FF3B30' : ($avail < $total ? '#FF9500' : '#1D1D1F');
            ?>
                <tr>
                    <td><span class="code-chip"><?= e($row['equipment_code']) ?></span></td>
                    <td>
                        <div class="fw-semibold"><?= e($row['name']) ?></div>
                        <?php if ($row['description']): ?>
                            <div class="small text-muted"><?= e($row['description']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><?= category_pill($row['category']) ?></td>
                    <td class="stock-cell">
                        <span class="status-pill <?= $pill ?>"><?= $avail ?> / <?= (int)$row['quantity_total'] ?></span>
                        <div class="stock-bar">
                            <div class="stock-bar-fill" style="width: <?= $pct ?>%; background: <?= $bar ?>"></div>
                        </div>
                    </td>
                    <td><?= condition_badge($row['item_condition']) ?></td>
                    <td class="text-end text-nowrap">
                        <a href="equipment_form.php?id=<?= (int)$row['equipment_id'] ?>"
                           class="btn btn-action btn-outline-secondary" title="Edit" aria-label="Edit equipment">
                            <i class="bi bi-pencil"></i>
                        </a>
                        <form action="equipment_delete.php" method="post" class="d-inline"
                              onsubmit="return confirm('Delete this equipment? This cannot be undone.');">
                            <input type="hidden" name="id" value="<?= (int)$row['equipment_id'] ?>">
                            <button class="btn btn-action btn-outline-danger" title="Delete" aria-label="Delete equipment">
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
