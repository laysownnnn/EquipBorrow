<?php
/**
 * borrow_delete.php  (DELETE)
 * ------------------------------------------------------------
 * Deletes a borrow record (POST only). If the items were still
 * out ("Borrowed"), their quantity is put back into stock first.
 * ------------------------------------------------------------
 */
require_once 'includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('borrows.php');
}

$id = (int)($_POST['id'] ?? 0);

$conn->begin_transaction();
try {
    $stmt = $conn->prepare('SELECT equipment_id, quantity, status FROM borrow_records WHERE borrow_id = ? FOR UPDATE');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $rec = $stmt->get_result()->fetch_assoc();

    if (!$rec) {
        $conn->rollback();
        set_flash('warning', 'Record not found.');
        redirect('borrows.php');
    }

    // Restore stock if the items were never returned
    if ($rec['status'] === 'Borrowed') {
        $stmt = $conn->prepare('UPDATE equipment SET quantity_available = quantity_available + ? WHERE equipment_id = ?');
        $stmt->bind_param('ii', $rec['quantity'], $rec['equipment_id']);
        $stmt->execute();
    }

    $stmt = $conn->prepare('DELETE FROM borrow_records WHERE borrow_id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $conn->commit();
    set_flash('success', "Borrow record #$id deleted.");
} catch (mysqli_sql_exception $e) {
    $conn->rollback();
    set_flash('danger', 'Database error: ' . $e->getMessage());
}

redirect('borrows.php');
