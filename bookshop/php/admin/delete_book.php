<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$book_id = $_GET['id'] ?? 0;
$search = $_GET['search'] ?? '';

if ($book_id) {
    try {
        $pdo->beginTransaction();
        
        // Get book details first for the message
        $stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
        $stmt->execute([$book_id]);
        $book = $stmt->fetch();
        
        if (!$book) {
            $_SESSION['error'] = 'Book not found.';
        } else {
            // Check if book exists in orders
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE book_id = ?");
            $stmt->execute([$book_id]);
            $order_count = $stmt->fetchColumn();
            
            if ($order_count > 0) {
                // Book has orders - soft delete approach
                // The trigger will handle moving to deleted_books table
                // and preserving order history in deleted_orders table
                
                // Delete from cart first
                $stmt = $pdo->prepare("DELETE FROM cart WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book (trigger handles the rest)
                $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                if ($stmt->execute([$book_id])) {
                    $_SESSION['success'] = "Book '{$book['title']}' deleted successfully. Related order history preserved.";
                } else {
                    $_SESSION['error'] = 'Failed to delete book.';
                }
            } else {
                // No orders exist - safe to delete completely
                // Delete from cart first
                $stmt = $pdo->prepare("DELETE FROM cart WHERE book_id = ?");
                $stmt->execute([$book_id]);
                
                // Delete book (trigger will still capture it for audit)
                $stmt = $pdo->prepare("DELETE FROM books WHERE id = ?");
                if ($stmt->execute([$book_id])) {
                    $_SESSION['success'] = "Book '{$book['title']}' deleted successfully.";
                } else {
                    $_SESSION['error'] = 'Failed to delete book.';
                }
            }
        }
        
        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollback();
        $_SESSION['error'] = 'Error deleting book: ' . $e->getMessage();
    }
} else {
    $_SESSION['error'] = 'Invalid book ID.';
}

// Redirect back to view_books.php with search parameter if it exists
$redirect_url = 'view_books.php';
if (!empty($search)) {
    $redirect_url .= '?search=' . urlencode($search);
}

header('Location: ' . $redirect_url);
exit();
?>