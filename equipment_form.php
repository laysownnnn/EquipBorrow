<?php
/**
 * equipment_form.php  (CREATE + UPDATE)
 * ------------------------------------------------------------
 * - No ?id=   -> Add new equipment   (INSERT)
 * - With ?id= -> Edit equipment      (UPDATE)
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$categories = ['Laptop', 'Projector', 'Audio', 'Camera', 'Sports', 'Laboratory', 'Others'];
$conditions = ['Good', 'Fair', 'Damaged'];

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];

$item = [
    'equipment_code' => '', 'name' => '', 'category' => 'Others',
    'quantity_total' => 1, 'quantity_available' => 1,
    'item_condition' => 'Good', 'description' => '',
];

if ($isEdit) {
    $stmt = $conn->prepare('SELECT * FROM equipment WHERE equipment_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if (!$found) {
        set_flash('danger', 'Equipment not found.');
        redirect('equipment.php');
    }
    $item = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $item['equipment_code'] = strtoupper(trim($_POST['equipment_code'] ?? ''));
    $item['name']           = trim($_POST['name'] ?? '');
    $item['category']       = $_POST['category'] ?? '';
    $item['item_condition'] = $_POST['item_condition'] ?? '';
    $item['description']    = trim($_POST['description'] ?? '');
    $newTotal               = (int)($_POST['quantity_total'] ?? 0);

    if ($item['equipment_code'] === '') $errors[] = 'Equipment code is required.';
    if ($item['name'] === '')           $errors[] = 'Name is required.';
    if (!in_array($item['category'], $categories, true))       $errors[] = 'Invalid category.';
    if (!in_array($item['item_condition'], $conditions, true)) $errors[] = 'Invalid condition.';
    if ($newTotal < 1)                  $errors[] = 'Quantity must be at least 1.';

    $borrowedNow = $isEdit ? ($item['quantity_total'] - $item['quantity_available']) : 0;
    if ($newTotal < $borrowedNow) {
        $errors[] = "Quantity cannot be less than $borrowedNow (units currently borrowed).";
    }

    if (!$errors) {
        $newAvailable = $newTotal - $borrowedNow;
        try {
            if ($isEdit) {
                $stmt = $conn->prepare(
                    'UPDATE equipment
                     SET equipment_code = ?, name = ?, category = ?, quantity_total = ?,
                         quantity_available = ?, item_condition = ?, description = ?
                     WHERE equipment_id = ?'
                );
                $stmt->bind_param('sssiissi', $item['equipment_code'], $item['name'], $item['category'],
                    $newTotal, $newAvailable, $item['item_condition'], $item['description'], $id);
                $stmt->execute();
                set_flash('success', 'Equipment updated successfully.');
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO equipment (equipment_code, name, category, quantity_total,
                                            quantity_available, item_condition, description)
                     VALUES (?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('sssiiss', $item['equipment_code'], $item['name'], $item['category'],
                    $newTotal, $newAvailable, $item['item_condition'], $item['description']);
                $stmt->execute();
                set_flash('success', 'Equipment added successfully.');
            }
            redirect('equipment.php');
        } catch (mysqli_sql_exception $e) {
            $errors[] = $e->getCode() == 1062
                ? 'That equipment code already exists.'
                : 'Database error: ' . $e->getMessage();
        }
    }
    $item['quantity_total'] = $newTotal;
}

if (!$isEdit && $item['equipment_code'] === '') {
    $next = $conn->query('SELECT COALESCE(MAX(equipment_id), 0) + 1 AS n FROM equipment')->fetch_assoc()['n'];
    $item['equipment_code'] = sprintf('EQ-%04d', $next);
}

$pageTitle  = $isEdit ? 'Edit Equipment' : 'Add Equipment';
$activePage = 'equipment';
require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Inventory Catalog</div>
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
            </div>
            <a href="equipment.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back to List
            </a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if ($errors): ?>
                    <div class="alert alert-danger border-0"><ul class="mb-0">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Equipment Code *</label>
                            <input type="text" name="equipment_code" class="form-control" maxlength="20"
                                   value="<?= e($item['equipment_code']) ?>" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Equipment Name *</label>
                            <input type="text" name="name" class="form-control" maxlength="100"
                                   value="<?= e($item['name']) ?>" placeholder="e.g. Epson EB-X51 Projector" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Category *</label>
                            <select name="category" class="form-select">
                                <?php foreach ($categories as $c): ?>
                                    <option <?= $c === $item['category'] ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Total Quantity *</label>
                            <input type="number" name="quantity_total" class="form-control" min="1"
                                   value="<?= (int)$item['quantity_total'] ?>" required>
                            <?php if ($isEdit): ?>
                                <div class="form-text">Currently available: <?= (int)$item['quantity_available'] ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Condition *</label>
                            <select name="item_condition" class="form-select">
                                <?php foreach ($conditions as $c): ?>
                                    <option <?= $c === $item['item_condition'] ? 'selected' : '' ?>><?= e($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Description / Accessories</label>
                            <textarea name="description" class="form-control" rows="3"
                                      placeholder="Optional notes, serial numbers, or included cables..."><?= e($item['description']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="equipment.php" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Equipment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
