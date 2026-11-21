<?php
/**
 * borrow_return.php  (UPDATE - return process)
 * ------------------------------------------------------------
 * Marks a borrow record as "Returned", records the return date
 * and condition, and puts the units back into stock.
 * If returned "Damaged", the equipment's condition is updated.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $conn->prepare(
    "SELECT br.*, e.name AS equipment_name, e.equipment_code, b.full_name, b.id_number
     FROM borrow_records br
     JOIN equipment e ON e.equipment_id = br.equipment_id
     JOIN borrowers b ON b.borrower_id = br.borrower_id
     WHERE br.borrow_id = ? AND br.status = 'Borrowed'"
);
$stmt->bind_param('i', $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();

if (!$rec) {
    set_flash('warning', 'Record not found or already returned.');
    redirect('borrows.php');
}

$errors     = [];
$conditions = ['Good', 'Fair', 'Damaged'];
$returnDate = date('Y-m-d');
$condition  = 'Good';
$remarks    = $rec['remarks'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $returnDate = $_POST['return_date'] ?? '';
    $condition  = $_POST['return_condition'] ?? '';
    $remarks    = trim($_POST['remarks'] ?? '');

    if (!strtotime($returnDate))                  $errors[] = 'Invalid return date.';
    elseif ($returnDate < $rec['borrow_date'])     $errors[] = 'Return date cannot be before the borrow date.';
    if (!in_array($condition, $conditions, true))  $errors[] = 'Invalid condition.';

    if (!$errors) {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare(
                "UPDATE borrow_records
                 SET status = 'Returned', return_date = ?, return_condition = ?, remarks = ?
                 WHERE borrow_id = ? AND status = 'Borrowed'"
            );
            $rem = $remarks ?: null;
            $stmt->bind_param('sssi', $returnDate, $condition, $rem, $id);
            $stmt->execute();

            if ($condition === 'Damaged') {
                $stmt = $conn->prepare(
                    "UPDATE equipment SET quantity_available = quantity_available + ?, item_condition = 'Damaged'
                     WHERE equipment_id = ?"
                );
            } else {
                $stmt = $conn->prepare('UPDATE equipment SET quantity_available = quantity_available + ? WHERE equipment_id = ?');
            }
            $stmt->bind_param('ii', $rec['quantity'], $rec['equipment_id']);
            $stmt->execute();

            $conn->commit();
            set_flash('success', "{$rec['equipment_name']} returned by {$rec['full_name']}.");
            redirect('borrows.php');
        } catch (mysqli_sql_exception $ex) {
            $conn->rollback();
            $errors[] = 'Database error: ' . $ex->getMessage();
        }
    }
}

$daysLate   = max(0, (int)((strtotime($returnDate ?: 'today') - strtotime($rec['due_date'])) / 86400));
$pageTitle  = 'Return Equipment';
$activePage = 'borrows';
require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Check-In &amp; Inspection</div>
                <h1 class="page-title">Return Equipment <span class="code-chip ms-1">#<?= $id ?></span></h1>
            </div>
            <a href="borrows.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Back
            </a>
        </div>

        <div class="card">
            <div class="card-body p-4">
                <?php if ($errors): ?>
                    <div class="alert alert-danger border-0"><ul class="mb-0">
                        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
                    </ul></div>
                <?php endif; ?>

                <div class="p-3 rounded-3 mb-4" style="background: var(--surface-subtle); border: 1px solid var(--border-color);">
                    <div class="row g-2 small">
                        <div class="col-sm-6">
                            <div class="text-muted">Borrower</div>
                            <div class="fw-semibold fs-6"><?= e($rec['full_name']) ?> <span class="text-muted fw-normal">(<?= e($rec['id_number']) ?>)</span></div>
                        </div>
                        <div class="col-sm-6">
                            <div class="text-muted">Equipment</div>
                            <div class="fw-semibold fs-6"><?= e($rec['equipment_name']) ?> &times; <?= (int)$rec['quantity'] ?></div>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <div class="text-muted">Borrowed On</div>
                            <div class="fw-medium"><?= nice_date($rec['borrow_date']) ?></div>
                        </div>
                        <div class="col-sm-6 mt-2">
                            <div class="text-muted">Due Date</div>
                            <div class="fw-medium">
                                <?= nice_date($rec['due_date']) ?>
                                <?php if ($daysLate > 0): ?>
                                    <span class="status-pill pill-rose ms-1"><span class="dot"></span><?= $daysLate ?>d late</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <form method="post">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Return Date *</label>
                            <input type="date" name="return_date" class="form-control" value="<?= e($returnDate) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Condition on Return *</label>
                            <select name="return_condition" class="form-select">
                                <?php foreach ($conditions as $c): ?>
                                    <option <?= $c === $condition ? 'selected' : '' ?>><?= $c ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Inspection Remarks</label>
                            <input type="text" name="remarks" class="form-control" maxlength="255"
                                   value="<?= e($remarks) ?>" placeholder="Any notes on item condition...">
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="borrows.php" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Confirm Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
