<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$message = '';
$error = '';

// Handle order status updates
if ($_POST && isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['status'];
    $old_status = $_POST['old_status'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if ($order) {
            // Handle stock changes
            if ($old_status !== 'cancelled' && $new_status === 'cancelled') {
                // Restore stock
                $stmt = $pdo->prepare("UPDATE books SET stock = stock + ? WHERE id = ?");
                $stmt->execute([$order['quantity'], $order['book_id']]);
            } elseif ($old_status === 'cancelled' && $new_status !== 'cancelled') {
                // Reduce stock
                $stmt = $pdo->prepare("SELECT stock FROM books WHERE id = ?");
                $stmt->execute([$order['book_id']]);
                $current_stock = $stmt->fetchColumn();
                
                if ($current_stock >= $order['quantity']) {
                    $stmt = $pdo->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
                    $stmt->execute([$order['quantity'], $order['book_id']]);
                } else {
                    throw new Exception("Not enough stock available");
                }
            }
            
            // Update order status
            $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $order_id]);
            
            $pdo->commit();
            $message = 'Order status updated successfully!';
        } else {
            $error = 'Order not found';
        }
    } catch (Exception $e) {
        $pdo->rollback();
        $error = 'Failed to update order: ' . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR b.title LIKE ? OR b.author LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get orders
$stmt = $pdo->prepare("
    SELECT o.*, c.name as customer_name, c.email as customer_email, 
           b.title as book_title, b.author as book_author
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN books b ON o.book_id = b.id 
    $where_clause
    ORDER BY o.order_date DESC
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$pending_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'");
$confirmed_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");
$cancelled_orders = $stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Manage Orders</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .container {
            max-width: 1400px;
            margin: 60px auto 20px auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        .dashboard {
            padding-top: 40px;
        }
        .stats {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        .stat-item {
            background: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            text-align: center;
            min-width: 120px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .stat-item:hover { transform: translateY(-2px); }
        .stat-item.pending { border-left: 4px solid #f39c12; }
        .stat-item.confirmed { border-left: 4px solid #27ae60; }
        .stat-item.cancelled { border-left: 4px solid #e74c3c; }
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        .filters-section {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .filters-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filters-form select, .filters-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .status-select {
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-confirmed { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .historical-order {
            background-color: #f8f9fa;
            border-left: 3px solid #6c757d;
        }
        .historical-badge {
            background: #6c757d;
            color: white;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .table-container {
                overflow-x: auto;
            }
            .filters-form {
                flex-direction: column;
                align-items: stretch;
            }
            .filters-form select, .filters-form input {
                margin-bottom: 10px;
            }
            .stats {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Manage Orders</h1>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Order Statistics -->
            <div class="stats">
                <div class="stat-item" onclick="filterByStatus('')">
                    <div class="stat-number"><?php echo $total_orders; ?></div>
                    <div>Total Orders</div>
                </div>
                <div class="stat-item pending" onclick="filterByStatus('pending')">
                    <div class="stat-number"><?php echo $pending_orders; ?></div>
                    <div>Pending</div>
                </div>
                <div class="stat-item confirmed" onclick="filterByStatus('confirmed')">
                    <div class="stat-number"><?php echo $confirmed_orders; ?></div>
                    <div>Confirmed</div>
                </div>
                <div class="stat-item cancelled" onclick="filterByStatus('cancelled')">
                    <div class="stat-number"><?php echo $cancelled_orders; ?></div>
                    <div>Cancelled</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <h3>Filters</h3>
                <form method="GET" class="filters-form">
                    <select name="status">
                        <option value="">All Statuses</option>
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                        <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                    
                    <input type="text" name="search" placeholder="Search customer or book..." 
                           value="<?php echo htmlspecialchars($search); ?>" style="flex: 1; min-width: 200px;">
                    
                    <button type="submit" class="btn btn-admin">Filter</button>
                    
                    <?php if ($status_filter || $search): ?>
                        <a href="manage_orders.php" class="btn" style="background: #95a5a6; color: white;">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Customer</th>
                            <th>Book</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Status</th>
                            <th>Order Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 40px;">
                                No orders found
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($orders as $order): ?>
                            <tr class="<?php echo !$order['customer_name'] ? 'historical-order' : ''; ?>">
                                <td>
                                    <strong>#<?php echo $order['id']; ?></strong>
                                    <?php if (!$order['customer_name']): ?>
                                        <br><span class="historical-badge">HISTORICAL</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['customer_name']): ?>
                                        <strong><?php echo htmlspecialchars($order['customer_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                    <?php else: ?>
                                        <span class="historical-badge">DELETED CUSTOMER</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($order['book_title']): ?>
                                        <strong><?php echo htmlspecialchars($order['book_title']); ?></strong><br>
                                        <small>by <?php echo htmlspecialchars($order['book_author']); ?></small>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Book deleted</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td>Rs <?php echo number_format($order['total_price'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('Y-m-d H:i', strtotime($order['order_date'])); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                        <input type="hidden" name="old_status" value="<?php echo $order['status']; ?>">
                                        
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $order['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                        
                                        <input type="hidden" name="update_status" value="1">
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <div style="margin-top: 20px;">
                <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white;">Back to Dashboard</a>
                <a href="view_orders.php" class="btn" style="background: #6c757d; color: white; margin-left: 10px;">Order History</a>
            </div>
        </div>
    </div>

    <script>
        function filterByStatus(status) {
            const url = new URL(window.location);
            if (status) {
                url.searchParams.set('status', status);
            } else {
                url.searchParams.delete('status');
            }
            window.location = url;
        }
    </script>
</body>
</html>