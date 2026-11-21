<?php
/**
 * borrower_delete.php  (DELETE)
 * ------------------------------------------------------------
 * Deletes a borrower (POST only). Blocked if the borrower has
 * borrow records (FOREIGN KEY ... ON DELETE RESTRICT).
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('borrowers.php');
}

$id = (int)($_POST['id'] ?? 0);

try {
    $stmt = $conn->prepare('DELETE FROM borrowers WHERE borrower_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    set_flash($stmt->affected_rows > 0 ? 'success' : 'warning',
              $stmt->affected_rows > 0 ? 'Borrower deleted.' : 'Borrower not found.');
} catch (mysqli_sql_exception $e) {
    set_flash('danger', $e->getCode() == 1451
        ? 'Cannot delete: this borrower has borrow records. Delete those records first.'
        : 'Database error: ' . $e->getMessage());
}

redirect('borrowers.php');
