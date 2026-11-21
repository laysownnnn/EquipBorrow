<?php
/**
 * equipment_delete.php  (DELETE)
 * ------------------------------------------------------------
 * Deletes an equipment record (POST only).
 * Blocked if the equipment has borrow history, because of the
 * FOREIGN KEY ... ON DELETE RESTRICT rule in the database.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('equipment.php');
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $conn->prepare('DELETE FROM equipment WHERE equipment_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        set_flash('success', 'Equipment deleted.');
    } else {
        set_flash('warning', 'Equipment not found.');
    }
} catch (mysqli_sql_exception $e) {
    // 1451 = row is referenced by a foreign key (borrow_records)
    if ($e->getCode() == 1451) {
        set_flash('danger', 'Cannot delete: this equipment has borrow records. Delete those records first.');
    } else {
        set_flash('danger', 'Database error: ' . $e->getMessage());
    }
}

redirect('equipment.php');
