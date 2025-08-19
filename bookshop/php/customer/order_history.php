<?php
session_start();

// Include the auth check
$customer_id = $_SESSION['customer_id'] ?? null;
if (!$customer_id) {
    header('Location: ../../customer_login.php');
    exit();
}

// Check for auto logout (if customer was deleted)
$logout_file = '../temp/logout_customer_' . $customer_id . '.flag';
if (file_exists($logout_file)) {
    unlink($logout_file);
    session_destroy();
    header('Location: ../../customer_login.php?message=account_deleted');
    exit();
}

include '../config/db.php';

// Verify customer still exists
$stmt = $pdo->prepare("SELECT id FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
if (!$stmt->fetch()) {
    session_destroy();
    header('Location: ../../customer_login.php?message=account_deleted');
    exit();
}

// Get customer orders with better handling of deleted books
$stmt = $pdo->prepare("
    SELECT o.*, 
           b.title, 
           b.author,
           CASE 
               WHEN b.id IS NULL THEN 'DELETED'
               ELSE 'ACTIVE'
           END as book_status
    FROM orders o 
    LEFT JOIN books b ON o.book_id = b.id 
    WHERE o.customer_id = ? 
    ORDER BY o.order_date DESC
");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll();

// Also get deleted order records for this customer from deleted_orders table
$stmt = $pdo->prepare("
    SELECT do.original_order_id as id,
           do.book_title as title,
           do.book_author as author,
           do.quantity,
           do.total_price,
           do.status,
           do.order_date,
           'DELETED' as book_status,
           do.deletion_date,
           do.deletion_reason
    FROM deleted_orders do
    WHERE do.customer_id = ?
    ORDER BY do.order_date DESC
");
$stmt->execute([$customer_id]);
$deleted_orders = $stmt->fetchAll();

// Merge and sort orders
$all_orders = array_merge($orders, $deleted_orders);
usort($all_orders, function($a, $b) {
    return strtotime($b['order_date']) - strtotime($a['order_date']);
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order History - Customer</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .order-row.deleted-book {
            background-color: rgba(231, 76, 60, 0.05);
            border-left: 4px solid #e74c3c;
        }
        
        .order-row.deleted-order {
            background-color: rgba(149, 165, 166, 0.1);
            border-left: 4px solid #95a5a6;
        }
        
        .book-status-badge {
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 0.8em;
            font-weight: bold;
            margin-left: 8px;
        }
        
        .book-status-deleted {
            background: #e74c3c;
            color: white;
        }
        
        .book-status-active {
            background: #27ae60;
            color: white;
        }
        
        .deleted-book-info {
            color: #e74c3c;
            font-style: italic;
        }
        
        .status-badge {
            padding: 5px 10px;
            border-radius: 3px;
            color: white;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 0.8em;
        }
        
        .status-confirmed {
            background-color: #27ae60;
        }
        
        .status-pending {
            background-color: #f39c12;
        }
        
        .status-cancelled {
            background-color: #e74c3c;
        }
        
        .deletion-info {
            background: rgba(149, 165, 166, 0.1);
            padding: 8px;
            border-radius: 4px;
            margin-top: 8px;
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .table-container {
            overflow-x: auto;
            margin-top: 20px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
            overflow: hidden;
        }
        
        th, td {
            padding: 15px 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #ffffffff;
        }
        
        tr:hover {
            background-color: #f5f5f5;
        }
        
        .no-orders {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }
        
        .no-orders h3 {
            color: #2c3e50;
            margin-bottom: 15px;
        }
        
        .stats-summary {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .stat-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>My Order History</h1>
            
            <?php if (empty($all_orders)): ?>
                <div class="no-orders">
                    <h3>No Orders Yet</h3>
                    <p>You haven't placed any orders yet.</p>
                    <a href="view_books.php" class="btn btn-customer">Start Shopping!</a>
                </div>
            <?php else: ?>
                <?php
                // Calculate statistics
                $total_orders = count($all_orders);
                $total_spent = 0;
                $confirmed_orders = 0;
                $pending_orders = 0;
                $cancelled_orders = 0;
                $deleted_book_count = 0;
                $deleted_order_count = 0;
                
                foreach ($all_orders as $order) {
                    if ($order['status'] !== 'cancelled') {
                        $total_spent += $order['total_price'];
                    }
                    
                    switch ($order['status']) {
                        case 'confirmed':
                            $confirmed_orders++;
                            break;
                        case 'pending':
                            $pending_orders++;
                            break;
                        case 'cancelled':
                            $cancelled_orders++;
                            break;
                    }
                    
                    if ($order['book_status'] === 'DELETED') {
                        if (isset($order['deletion_date'])) {
                            $deleted_order_count++;
                        } else {
                            $deleted_book_count++;
                        }
                    }
                }
                ?>
                
                <div class="stats-summary">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $total_orders; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number">Rs <?php echo number_format($total_spent, 2); ?></div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $confirmed_orders; ?></div>
                        <div class="stat-label">Confirmed</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $pending_orders; ?></div>
                        <div class="stat-label">Pending</div>
                    </div>
                    <?php if ($deleted_book_count > 0 || $deleted_order_count > 0): ?>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $deleted_book_count + $deleted_order_count; ?></div>
                        <div class="stat-label">Historical Orders</div>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Book Details</th>
                                <th>Quantity</th>
                                <th>Total Price</th>
                                <th>Status</th>
                                <th>Order Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_orders as $order): ?>
                            <tr class="order-row <?php echo $order['book_status'] === 'DELETED' ? (isset($order['deletion_date']) ? 'deleted-order' : 'deleted-book') : ''; ?>">
                                <td>
                                    <strong>#<?php echo $order['id']; ?></strong>
                                </td>
                                <td>
                                    <?php if ($order['book_status'] === 'ACTIVE'): ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($order['title']); ?></strong>
                                            <span class="book-status-badge book-status-active">AVAILABLE</span>
                                        </div>
                                        <div style="color: #666; font-size: 14px;">
                                            by <?php echo htmlspecialchars($order['author']); ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="deleted-book-info">
                                            <strong>
                                                <?php echo htmlspecialchars($order['title'] ?? 'Unknown Book'); ?>
                                            </strong>
                                            <span class="book-status-badge book-status-deleted">
                                                <?php echo isset($order['deletion_date']) ? 'ORDER ARCHIVED' : 'BOOK REMOVED'; ?>
                                            </span>
                                        </div>
                                        <div style="color: #e74c3c; font-size: 14px; font-style: italic;">
                                            by <?php echo htmlspecialchars($order['author'] ?? 'Unknown Author'); ?>
                                        </div>
                                        <?php if (isset($order['deletion_date'])): ?>
                                            <div class="deletion-info">
                                                <strong>Order Record:</strong> This order was archived on <?php echo date('M j, Y', strtotime($order['deletion_date'])); ?>
                                                <?php if (!empty($order['deletion_reason'])): ?>
                                                    <br><em><?php echo htmlspecialchars($order['deletion_reason']); ?></em>
                                                <?php endif; ?>
                                            </div>
                                        <?php else: ?>
                                            <div class="deletion-info">
                                                <strong>Note:</strong> This book is no longer available in our catalog, but your order record is preserved.
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo $order['quantity']; ?></td>
                                <td>Rs <?php echo number_format($order['total_price'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $order['status']; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($order['order_date'])); ?></div>
                                    <div style="font-size: 12px; color: #666;">
                                        <?php echo date('H:i', strtotime($order['order_date'])); ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if ($deleted_book_count > 0 || $deleted_order_count > 0): ?>
                <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-top: 20px; border-left: 4px solid #ffc107;">
                    <h4 style="color: #856404; margin-bottom: 10px;">📋 Historical Order Information</h4>
                    <p style="color: #856404; margin: 0; line-height: 1.6;">
                        Some orders show books that are no longer available or have been archived. This is normal and 
                        your order history is preserved for your records. Orders marked as "BOOK REMOVED" indicate 
                        that the book has been discontinued from our catalog, while "ORDER ARCHIVED" indicates 
                        historical order records maintained for reference.
                    </p>
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div style="margin-top: 30px; display: flex; gap: 15px; flex-wrap: wrap;">
                <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white;">Back to Dashboard</a>
                <?php if (!empty($all_orders)): ?>
                    <a href="view_books.php" class="btn btn-customer">Continue Shopping</a>
                    <a href="cart.php" class="btn" style="background: #f39c12; color: white;">View Cart</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>