<?php
session_start();
include '../config/db.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: ../../customer_login.php');
    exit();
}

// Get customer statistics
$customer_id = $_SESSION['customer_id'];

$stmt = $pdo->prepare("SELECT COUNT(*) as cart_items FROM cart WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetch()['cart_items'];

$stmt = $pdo->prepare("SELECT COUNT(*) as total_orders FROM orders WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$total_orders = $stmt->fetch()['total_orders'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard - Bookshop</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/scripts.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <a href="../auth/logout_customer.php" class="logout-btn" onclick="return confirmLogout()">Logout</a>
            
            <h1>Customer Dashboard</h1>
            <p>Welcome, <?php echo htmlspecialchars($_SESSION['customer_name']); ?>!</p>
            
            <div class="dashboard-stats">
                <div class="stat-card stat-books">
                    <h3><?php echo $cart_items; ?></h3>
                    <p>Items in Cart</p>
                </div>
                <div class="stat-card stat-customers">
                    <h3><?php echo $total_orders; ?></h3>
                    <p>Total Orders</p>
                </div>
            </div>
            
            <div class="dashboard-menu">
                <a href="view_books.php" class="menu-item">
                    <h3>📚 Browse Books</h3>
                    <p>View and search available books</p>
                </a>
                <a href="cart.php" class="menu-item">
                    <h3>🛒 My Cart</h3>
                    <p>View cart and place orders</p>
                </a>
                <a href="order_history.php" class="menu-item">
                    <h3>📋 Order History</h3>
                    <p>View your past orders</p>
                </a>
                <a href="edit_profile.php" class="menu-item">
                    <h3>👤 Edit Profile</h3>
                    <p>Update your account information</p>
                </a>
            </div>
        </div>
    </div>
</body>
</html>