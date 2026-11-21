<?php
/**
 * borrower_form.php  (CREATE + UPDATE)
 * ------------------------------------------------------------
 * - No ?id=   -> Add new borrower (INSERT) and optionally
 *                record an initial equipment borrow in one step
 *                (deducting from equipment.quantity_available).
 * - With ?id= -> Edit borrower details (UPDATE).
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

$types  = ['Student', 'Faculty', 'Staff'];
$id     = (int)($_GET['id'] ?? 0);
$isEdit = $id > 0;
$errors = [];
$today  = date('Y-m-d');

$b = [
    'id_number' => '', 'full_name' => '', 'borrower_type' => 'Student',
    'department' => '', 'contact_no' => '', 'email' => '',
];

$borrow = [
    'equipment_id' => 0,
    'quantity'     => 1,
    'borrow_date'  => $today,
    'due_date'     => date('Y-m-d', strtotime('+3 days')),
    'remarks'      => '',
];

if ($isEdit) {
    $stmt = $conn->prepare('SELECT * FROM borrowers WHERE borrower_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $found = $stmt->get_result()->fetch_assoc();
    if (!$found) {
        set_flash('danger', 'Borrower not found.');
        redirect('borrowers.php');
    }
    $b = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['id_number', 'full_name', 'borrower_type', 'department', 'contact_no', 'email'] as $field) {
        $b[$field] = trim($_POST[$field] ?? '');
    }

    if ($b['id_number'] === '')  $errors[] = 'ID number is required.';
    if ($b['full_name'] === '')  $errors[] = 'Full name is required.';
    if ($b['department'] === '') $errors[] = 'Department / course is required.';
    if (!in_array($b['borrower_type'], $types, true)) $errors[] = 'Invalid borrower type.';
    if ($b['email'] !== '' && !filter_var($b['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email address.';
    if ($b['contact_no'] !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $b['contact_no'])) $errors[] = 'Invalid contact number.';

    if (!$isEdit) {
        $borrow['equipment_id'] = (int)($_POST['equipment_id'] ?? 0);
        $borrow['quantity']     = (int)($_POST['quantity'] ?? 1);
        $borrow['borrow_date']  = $_POST['borrow_date'] ?? $today;
        $borrow['due_date']     = $_POST['due_date'] ?? '';
        $borrow['remarks']      = trim($_POST['remarks'] ?? '');

        if ($borrow['equipment_id'] > 0) {
            if ($borrow['quantity'] < 1) $errors[] = 'Borrow quantity must be at least 1.';
            if (!strtotime($borrow['borrow_date'])) $errors[] = 'Invalid borrow date.';
            if (!strtotime($borrow['due_date']))    $errors[] = 'Invalid due date.';
            if (strtotime($borrow['borrow_date']) && strtotime($borrow['due_date'])
                && $borrow['due_date'] < $borrow['borrow_date']) {
                $errors[] = 'Due date cannot be earlier than borrow date.';
            }
        }
    }

    if (!$errors) {
        $contact = $b['contact_no'] ?: null;
        $email   = $b['email'] ?: null;

        if ($isEdit) {
            try {
                $stmt = $conn->prepare(
                    'UPDATE borrowers SET id_number = ?, full_name = ?, borrower_type = ?,
                            department = ?, contact_no = ?, email = ?
                     WHERE borrower_id = ?'
                );
                $stmt->bind_param('ssssssi', $b['id_number'], $b['full_name'], $b['borrower_type'],
                    $b['department'], $contact, $email, $id);
                $stmt->execute();
                set_flash('success', 'Borrower updated successfully.');
                redirect('borrowers.php');
            } catch (mysqli_sql_exception $e) {
                $errors[] = $e->getCode() == 1062
                    ? 'That ID number is already registered.'
                    : 'Database error: ' . $e->getMessage();
            }
        } else {
            $conn->begin_transaction();
            try {
                // 1) Insert borrower
                $stmt = $conn->prepare(
                    'INSERT INTO borrowers (id_number, full_name, borrower_type, department, contact_no, email)
                     VALUES (?, ?, ?, ?, ?, ?)'
                );
                $stmt->bind_param('ssssss', $b['id_number'], $b['full_name'], $b['borrower_type'],
                    $b['department'], $contact, $email);
                $stmt->execute();
                $newBorrowerId = $conn->insert_id;

                // 2) If equipment was selected, record borrow & deduct stock
                $flashMsg = 'Borrower added successfully.';
                if ($borrow['equipment_id'] > 0) {
                    $stmt = $conn->prepare('SELECT name, quantity_available FROM equipment WHERE equipment_id = ? FOR UPDATE');
                    $stmt->bind_param('i', $borrow['equipment_id']);
                    $stmt->execute();
                    $eq = $stmt->get_result()->fetch_assoc();

                    if (!$eq) {
                        throw new RuntimeException('Selected equipment does not exist.');
                    }
                    if ($eq['quantity_available'] < $borrow['quantity']) {
                        throw new RuntimeException("Only {$eq['quantity_available']} unit(s) of {$eq['name']} available.");
                    }

                    $remarks = $borrow['remarks'] ?: null;
                    $stmt = $conn->prepare(
                        'INSERT INTO borrow_records (equipment_id, borrower_id, quantity, borrow_date, due_date, remarks)
                         VALUES (?, ?, ?, ?, ?, ?)'
                    );
                    $stmt->bind_param('iiisss', $borrow['equipment_id'], $newBorrowerId, $borrow['quantity'],
                        $borrow['borrow_date'], $borrow['due_date'], $remarks);
                    $stmt->execute();

                    $stmt = $conn->prepare('UPDATE equipment SET quantity_available = quantity_available - ? WHERE equipment_id = ?');
                    $stmt->bind_param('ii', $borrow['quantity'], $borrow['equipment_id']);
                    $stmt->execute();

                    $flashMsg = "Borrower added and {$borrow['quantity']} × {$eq['name']} borrowed.";
                }

                $conn->commit();
                set_flash('success', $flashMsg);
                redirect('borrowers.php');
            } catch (RuntimeException $ex) {
                $conn->rollback();
                $errors[] = $ex->getMessage();
            } catch (mysqli_sql_exception $e) {
                $conn->rollback();
                $errors[] = $e->getCode() == 1062
                    ? 'That ID number is already registered.'
                    : 'Database error: ' . $e->getMessage();
            }
        }
    }
}

if (!$isEdit) {
    $equipmentList = $conn->query(
        "SELECT equipment_id, equipment_code, name, quantity_available
         FROM equipment
         WHERE quantity_available > 0 AND item_condition IN ('Good', 'Fair')
         ORDER BY name"
    );
}

$pageTitle  = $isEdit ? 'Edit Borrower' : 'Add Borrower';
$activePage = 'borrowers';
require 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="page-header">
            <div>
                <div class="page-eyebrow">Directory</div>
                <h1 class="page-title"><?= e($pageTitle) ?></h1>
            </div>
            <a href="borrowers.php" class="btn btn-outline-secondary">
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
                            <label class="form-label">School / Employee ID *</label>
                            <input type="text" name="id_number" class="form-control" maxlength="20"
                                   value="<?= e($b['id_number']) ?>" placeholder="2024-00001" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Full Name *</label>
                            <input type="text" name="full_name" class="form-control" maxlength="100"
                                   value="<?= e($b['full_name']) ?>" placeholder="Juan Dela Cruz" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Borrower Type *</label>
                            <select name="borrower_type" class="form-select">
                                <?php foreach ($types as $t): ?>
                                    <option <?= $t === $b['borrower_type'] ? 'selected' : '' ?>><?= e($t) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Department / Course *</label>
                            <input type="text" name="department" class="form-control" maxlength="100"
                                   value="<?= e($b['department']) ?>" placeholder="BS Information Technology" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_no" class="form-control" maxlength="20"
                                   value="<?= e($b['contact_no']) ?>" placeholder="09XXXXXXXXX">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" maxlength="100"
                                   value="<?= e($b['email']) ?>" placeholder="name@example.com">
                        </div>
                    </div>

                    <?php if (!$isEdit): ?>
                        <hr class="my-4">
                        <div class="mb-3">
                            <div class="page-eyebrow mb-1">Optional</div>
                            <h2 class="h6 fw-semibold mb-0">Equipment to Borrow</h2>
                        </div>
                        <div class="row g-3">
                            <div class="col-md-9">
                                <label class="form-label">Equipment</label>
                                <select name="equipment_id" id="equipmentSelect" class="form-select">
                                    <option value="0">— None (register borrower only) —</option>
                                    <?php while ($eq = $equipmentList->fetch_assoc()): ?>
                                        <option value="<?= (int)$eq['equipment_id'] ?>"
                                                <?= $eq['equipment_id'] == $borrow['equipment_id'] ? 'selected' : '' ?>>
                                            <?= e($eq['name']) ?> (<?= e($eq['equipment_code']) ?>) — <?= (int)$eq['quantity_available'] ?> avail.
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Qty</label>
                                <input type="number" name="quantity" class="form-control borrow-field" min="1"
                                       value="<?= (int)$borrow['quantity'] ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Borrow Date</label>
                                <input type="date" name="borrow_date" class="form-control borrow-field"
                                       value="<?= e($borrow['borrow_date']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Expected Due Date</label>
                                <input type="date" name="due_date" class="form-control borrow-field"
                                       value="<?= e($borrow['due_date']) ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Purpose / Remarks</label>
                                <input type="text" name="remarks" class="form-control borrow-field" maxlength="255"
                                       value="<?= e($borrow['remarks']) ?>" placeholder="e.g. Used for class presentation">
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                        <a href="borrowers.php" class="btn btn-outline-secondary">Cancel</a>
                        <button class="btn btn-primary"><i class="bi bi-check2-circle"></i> Save Borrower</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require 'includes/footer.php'; ?>
