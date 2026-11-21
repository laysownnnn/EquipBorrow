<?php
/**
 * equipment_delete.php  (DELETE)
 * ------------------------------------------------------------
 * Deletes an equipment record (POST only).
 * Rules:
 * - BLOCK if any borrow record is overdue or currently borrowed.
 * - BLOCK if it has past borrow history (suggest Retired/Disposed).
 * - ALLOW only when the equipment has zero borrow records.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('equipment.php');
}

$id = (int)($_POST['id'] ?? 0);

// Check active, overdue, and total borrow records for this equipment
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_records,
        SUM(CASE WHEN status = 'Borrowed' THEN 1 ELSE 0 END) AS active_records,
        SUM(CASE WHEN status = 'Borrowed' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_records
    FROM borrow_records
    WHERE equipment_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

if ((int)$counts['overdue_records'] > 0) {
    set_flash('danger', 'Cannot delete: this equipment has overdue borrow record(s) that have not been returned.');
    redirect('equipment.php');
}

if ((int)$counts['active_records'] > 0) {
    set_flash('danger', 'Cannot delete: this equipment is currently borrowed and in someone\'s possession.');
    redirect('equipment.php');
}

if ((int)$counts['total_records'] > 0) {
    set_flash('danger', 'Cannot delete: this equipment has borrow history. If it is lost or broken beyond repair, edit it and mark it as Retired or Disposed instead.');
    redirect('equipment.php');
}

try {
    $stmt = $conn->prepare('DELETE FROM equipment WHERE equipment_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    set_flash($stmt->affected_rows > 0 ? 'success' : 'warning',
              $stmt->affected_rows > 0 ? 'Equipment deleted.' : 'Equipment not found.');
} catch (mysqli_sql_exception $e) {
    set_flash('danger', 'Database error: ' . $e->getMessage());
}

redirect('equipment.php');
