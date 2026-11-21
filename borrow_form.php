<?php
/**
 * borrow_form.php  (CREATE + UPDATE)
 * ------------------------------------------------------------
 * - No ?id=   -> New borrow transaction (INSERT)
 *                Deducts the quantity from equipment stock.
 * - With ?id= -> Edit a borrow record (UPDATE)
 *                Only dates and remarks can be changed; the
 *                borrower / equipment / quantity are locked so
 *                stock numbers stay correct.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];
$today  = date('Y-m-d');

$rec = [
    'borrower_id' => 0, 'equipment_id' => 0, 'quantity' => 1,
    'borrow_date' => $today,
    'due_date'    => date('Y-m-d', strtotime('+3 days')),
    'remarks'     => '',
];

if ($isEdit) {
    $stmt = $conn->prepare(
        'SELECT br.*, e.name AS equipment_name, b.full_name
         FROM borrow_records br
         JOIN equipment e ON e.equipment_id = br.equipment_id
         JOIN borrowers b ON b.borrower_id = br.borrower_id
         WHERE br.borrow_id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if (!$found) {
        set_flash('danger', 'Borrow record not found.');
        redirect('borrows.php');
    }
    $rec = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rec['borrow_date'] = $_POST['borrow_date'] ?? '';
    $rec['due_date']    = $_POST['due_date'] ?? '';
    $rec['remarks']     = trim($_POST['remarks'] ?? '');

    if (!$isEdit) {
        $rec['borrower_id']  = (int)($_POST['borrower_id'] ?? 0);
        $rec['equipment_id'] = (int)($_POST['equipment_id'] ?? 0);
        $rec['quantity']     = (int)($_POST['quantity'] ?? 0);

        if ($rec['borrower_id'] <= 0)  $errors[] = 'Please select a borrower.';
        if ($rec['equipment_id'] <= 0) $errors[] = 'Please select equipment.';
        if ($rec['quantity'] < 1)      $errors[] = 'Quantity must be at least 1.';
    }

    if (!strtotime($rec['borrow_date'])) $errors[] = 'Invalid borrow date.';
    if (!strtotime($rec['due_date']))    $errors[] = 'Invalid due date.';
    if (!$errors && $rec['due_date'] < $rec['borrow_date']) $errors[] = 'Due date cannot be earlier than borrow date.';

    if (!$errors) {
        $remarks = $rec['remarks'] ?: null;

        if ($isEdit) {
            $stmt = $conn->prepare('UPDATE borrow_records SET borrow_date = ?, due_date = ?, remarks = ? WHERE borrow_id = ?');
            $stmt->bind_param('sssi', $rec['borrow_date'], $rec['due_date'], $remarks, $id);
            $stmt->execute();
            set_flash('success', "Borrow record #$id updated.");
            redirect('borrows.php');
        }

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('SELECT name, quantity_available FROM equipment WHERE equipment_id = ? FOR UPDATE');
            $stmt->bind_param('i', $rec['equipment_id']);
            $stmt->execute();
            $eq = $stmt->get_result()->fetch_assoc();

            if (!$eq) {
                throw new RuntimeException('Selected equipment does not exist.');
            }
            if ($eq['quantity_available'] < $rec['quantity']) {
                throw new RuntimeException("Only {$eq['quantity_available']} unit(s) of {$eq['name']} available.");
            }

            $stmt = $conn->prepare(
                'INSERT INTO borrow_records (equipment_id, borrower_id, quantity, borrow_date, due_date, remarks)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param('iiisss', $rec['equipment_id'], $rec['borrower_id'], $rec['quantity'],
                $rec['borrow_date'], $rec['due_date'], $remarks);
            $stmt->execute();

            $stmt = $conn->prepare('UPDATE equipment SET quantity_available = quantity_available - ? WHERE equipment_id = ?');
            $stmt->bind_param('ii', $rec['quantity'], $rec['equipment_id']);
            $stmt->execute();

            $conn->commit();
            set_flash('success', "Borrow recorded: {$rec['quantity']} × {$eq['name']}.");
            redirect('borrows.php');
        } catch (RuntimeException $ex) {
            $conn->rollback();
            $errors[] = $ex->getMessage();
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }
}

if (!$isEdit) {
    $borrowerList  = $conn->query('SELECT borrower_id, id_number, full_name FROM borrowers ORDER BY full_name');
    $equipmentList = $conn->query(
        "SELECT equipment_id, equipment_code, name, quantity_available
         FROM equipment
         WHERE quantity_available > 0 AND item_condition <> 'Damaged'
         ORDER BY name"
    );
}

$pageTitle  = $isEdit ? "Edit Borrow #$id" : 'New Borrow';
$activePage = 'borrows';
require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Lending Log</div>
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
            </div>
            <a href="borrows.php" class="btn btn-outline-secondary">
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
                        <?php if ($isEdit): ?>
                            <div class="col-md-6">
                                <label class="form-label">Borrower</label>
                                <input class="form-control" value="<?= e($rec['full_name']) ?>" disabled>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Equipment</label>
                                <input class="form-control" value="<?= e($rec['equipment_name']) ?>" disabled>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Qty</label>
                                <input class="form-control" value="<?= (int)$rec['quantity'] ?>" disabled>
                            </div>
                        <?php else: ?>
                            <div class="col-md-6">
                                <label class="form-label">Borrower *</label>
                                <select name="borrower_id" class="form-select" required>
                                    <option value="">— Select borrower —</option>
                                    <?php while ($b = $borrowerList->fetch_assoc()): ?>
                                        <option value="<?= (int)$b['borrower_id'] ?>" <?= $b['borrower_id'] == $rec['borrower_id'] ? 'selected' : '' ?>>
                                            <?= e($b['full_name']) ?> (<?= e($b['id_number']) ?>)
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                                <div class="form-text"><a href="borrower_form.php" class="text-decoration-none">+ Register a new borrower</a></div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Equipment *</label>
                                <select name="equipment_id" class="form-select" required>
                                    <option value="">— Select equipment —</option>
                                    <?php while ($eq = $equipmentList->fetch_assoc()): ?>
                                        <option value="<?= (int)$eq['equipment_id'] ?>" <?= $eq['equipment_id'] == $rec['equipment_id'] ? 'selected' : '' ?>>
                                            <?= e($eq['name']) ?> — <?= (int)$eq['quantity_available'] ?> avail.
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">Qty *</label>
                                <input type="number" name="quantity" class="form-control" min="1"
                                       value="<?= (int)$rec['quantity'] ?>" required>
                            </div>
                        <?php endif; ?>

                        <div class="col-md-6">
                            <label class="form-label">Borrow Date *</label>
                            <input type="date" name="borrow_date" class="form-control" value="<?= e($rec['borrow_date']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Expected Due Date *</label>
                            <input type="date" name="due_date" class="form-control" value="<?= e($rec['due_date']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Purpose / Remarks</label>
                            <input type="text" name="remarks" class="form-control" maxlength="255"
                                   value="<?= e($rec['remarks']) ?>" placeholder="e.g. Used for IT101 class presentation">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="borrows.php" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Record</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
