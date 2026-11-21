<?php
/**
 * borrower_delete.php  (DELETE)
 * ------------------------------------------------------------
 * Deletes a borrower (POST only).
 * Rules:
 * - BLOCK if the borrower has overdue or currently borrowed items.
 * - BLOCK if the borrower has any past borrow history.
 * - ALLOW only when the borrower has zero borrow records.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('borrowers.php');
}

$id = (int)($_POST['id'] ?? 0);

// Check active, overdue, and total borrow records for this borrower
$stmt = $conn->prepare("
    SELECT
        COUNT(*) AS total_records,
        SUM(CASE WHEN status = 'Borrowed' THEN 1 ELSE 0 END) AS active_records,
        SUM(CASE WHEN status = 'Borrowed' AND due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_records
    FROM borrow_records
    WHERE borrower_id = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$counts = $stmt->get_result()->fetch_assoc();

if ((int)$counts['overdue_records'] > 0) {
    set_flash('danger', 'Cannot delete: this borrower has overdue item(s) past their due date.');
    redirect('borrowers.php');
}

if ((int)$counts['active_records'] > 0) {
    set_flash('danger', 'Cannot delete: this borrower still has equipment checked out.');
    redirect('borrowers.php');
}

if ((int)$counts['total_records'] > 0) {
    set_flash('danger', 'Cannot delete: this borrower has past borrow history. Only borrowers with zero borrow records can be deleted.');
    redirect('borrowers.php');
}

try {
    $stmt = $conn->prepare('DELETE FROM borrowers WHERE borrower_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    set_flash($stmt->affected_rows > 0 ? 'success' : 'warning',
              $stmt->affected_rows > 0 ? 'Borrower deleted.' : 'Borrower not found.');
} catch (mysqli_sql_exception $e) {
    set_flash('danger', 'Database error: ' . $e->getMessage());
}

redirect('borrowers.php');
