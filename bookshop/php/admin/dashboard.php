<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) as total_books FROM books");
$total_books = $stmt->fetch()['total_books'];

$stmt = $pdo->query("SELECT COUNT(*) as total_customers FROM customers");
$total_customers = $stmt->fetch()['total_customers'];

$stmt = $pdo->query("SELECT COUNT(*) as total_orders FROM orders");
$total_orders = $stmt->fetch()['total_orders'];

$stmt = $pdo->query("SELECT COUNT(*) as pending_orders FROM orders WHERE status = 'pending'");
$pending_orders = $stmt->fetch()['pending_orders'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Bookshop</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/scripts.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <!-- ✅ Logout Button moved inside .dashboard -->
            <a href="../auth/logout_admin.php" class="logout-btn" onclick="return confirmLogout()">Logout</a>

            <h1>Admin Dashboard</h1>
            <p>Welcome, Admin</p>
            
            <div class="dashboard-stats">
                <div class="stat-card stat-books">
                    <h3><?php echo $total_books; ?></h3>
                    <p>Total Books</p>
                </div>
                <div class="stat-card stat-customers">
                    <h3><?php echo $total_customers; ?></h3>
                    <p>Total Customers</p>
                </div>
                <div class="stat-card stat-orders">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
                <div class="stat-card stat-pending">
                    <h3><?php echo $pending_orders; ?></h3>
                    <p>Pending Orders</p>
                </div>
            </div>
            
            <div class="dashboard-menu">
                <a href="add_book.php" class="menu-item">
                    <h3>📚 Add Book</h3>
                    <p>Add new books to inventory</p>
                </a>
                <a href="view_books.php" class="menu-item">
                    <h3>📖 Manage Books</h3>
                    <p>View, edit, and delete books</p>
                </a>
                <a href="manage_orders.php" class="menu-item">
                    <h3>⚙️ Manage Orders</h3>
                    <p>Update order status and manage orders</p>
                </a>
                <a href="view_orders.php" class="menu-item">
                    <h3>📋 View Orders</h3>
                    <p>View all customer orders</p>
                </a>
                <a href="manage_customers.php" class="menu-item">
                    <h3>👥 Manage Customers</h3>
                    <p>Edit, manage, and view customer details</p>
                </a>
                <a href="view_customers.php" class="menu-item">
                    <h3>👤 View Customers</h3>
                    <p>Simple view of all registered customers</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>