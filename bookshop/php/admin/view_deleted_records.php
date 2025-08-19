<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$tab = $_GET['tab'] ?? 'customers';

// Get deleted customers
$stmt = $pdo->query("SELECT * FROM deleted_customers ORDER BY deletion_date DESC");
$deleted_customers = $stmt->fetchAll();

// Get deleted books
$stmt = $pdo->query("SELECT * FROM deleted_books ORDER BY deletion_date DESC");
$deleted_books = $stmt->fetchAll();

// Get deleted orders
$stmt = $pdo->query("SELECT * FROM deleted_orders ORDER BY deletion_date DESC");
$deleted_orders = $stmt->fetchAll();

// Get statistics
$stats = [
    'customers' => count($deleted_customers),
    'books' => count($deleted_books),
    'orders' => count($deleted_orders)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deleted Records Archive</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f8f9fa;
            font-size: 13px;
            line-height: 1.4;
            color: #2c3e50;
        }
        
        .container {
            max-width: 100%;
            padding: 10px;
            margin: 0 auto;
        }
        
        .dashboard {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        h1 {
            font-size: 20px;
            margin-bottom: 15px;
            color: #2c3e50;
            font-weight: 600;
        }
        
        h2 {
            font-size: 16px;
            color: #2c3e50;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        /* Statistics Grid */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: #e74c3c;
            color: white;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
            border: 1px solid #c0392b;
        }
        
        .stat-number {
            font-size: 20px;
            font-weight: bold;
            margin-bottom: 4px;
        }
        
        .stat-card div:last-child {
            font-size: 11px;
            opacity: 0.9;
        }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 6px;
            margin-bottom: 15px;
            border-bottom: 2px solid #e9ecef;
            flex-wrap: wrap;
        }
        
        .tab {
            padding: 8px 14px;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px 4px 0 0;
            cursor: pointer;
            font-weight: 500;
            font-size: 11px;
            transition: all 0.3s;
            text-decoration: none;
            color: #495057;
            white-space: nowrap;
        }
        
        .tab.active {
            background: #e74c3c;
            color: white;
            border-color: #c0392b;
        }
        
        .tab:hover:not(.active) {
            background: #e9ecef;
            color: #2c3e50;
        }
        
        /* Tables */
        .table-wrapper {
            overflow-x: auto;
            border-radius: 4px;
            border: 1px solid #e9ecef;
            margin-bottom: 15px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            font-size: 11px;
        }
        
        th {
            background: #e74c3c;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 500;
            font-size: 10px;
            white-space: nowrap;
            border-right: 1px solid #c0392b;
        }
        
        th:last-child {
            border-right: none;
        }
        
        td {
            padding: 6px;
            border-bottom: 1px solid #f8f9fa;
            border-right: 1px solid #f8f9fa;
            vertical-align: top;
            font-size: 11px;
        }
        
        td:last-child {
            border-right: none;
        }
        
        tr:hover td {
            background-color: #f8f9fa;
        }
        
        /* Badges */
        .deletion-badge {
            background: #e74c3c;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
            font-weight: 500;
            margin-left: 4px;
        }
        
        .status-badge {
            padding: 2px 6px;
            border-radius: 8px;
            font-size: 9px;
            font-weight: 500;
            text-transform: uppercase;
            white-space: nowrap;
        }
        
        .status-pending { background: #ffeaa7; color: #d63031; }
        .status-confirmed { background: #d1f2eb; color: #00b894; }
        .status-cancelled { background: #fab1a0; color: #e17055; }
        
        /* Buttons */
        .btn {
            padding: 6px 12px;
            text-decoration: none;
            border-radius: 3px;
            font-weight: 500;
            font-size: 11px;
            transition: all 0.2s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            margin: 2px;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .no-data {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
            font-size: 12px;
        }
        
        .navigation {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #e9ecef;
        }
        
        /* Column Widths for Different Tables */
        .customers-table th:nth-child(1), .customers-table td:nth-child(1) { width: 50px; }
        .customers-table th:nth-child(2), .customers-table td:nth-child(2) { width: 150px; }
        .customers-table th:nth-child(3), .customers-table td:nth-child(3) { width: 180px; }
        .customers-table th:nth-child(4), .customers-table td:nth-child(4) { width: 120px; }
        .customers-table th:nth-child(5), .customers-table td:nth-child(5) { width: 100px; }
        .customers-table th:nth-child(6), .customers-table td:nth-child(6) { width: 130px; }
        
        .books-table th:nth-child(1), .books-table td:nth-child(1) { width: 50px; }
        .books-table th:nth-child(2), .books-table td:nth-child(2) { width: 200px; }
        .books-table th:nth-child(3), .books-table td:nth-child(3) { width: 150px; }
        .books-table th:nth-child(4), .books-table td:nth-child(4) { width: 80px; }
        .books-table th:nth-child(5), .books-table td:nth-child(5) { width: 60px; }
        .books-table th:nth-child(6), .books-table td:nth-child(6) { width: 130px; }
        
        .orders-table th:nth-child(1), .orders-table td:nth-child(1) { width: 60px; }
        .orders-table th:nth-child(2), .orders-table td:nth-child(2) { width: 130px; }
        .orders-table th:nth-child(3), .orders-table td:nth-child(3) { width: 130px; }
        .orders-table th:nth-child(4), .orders-table td:nth-child(4) { width: 40px; }
        .orders-table th:nth-child(5), .orders-table td:nth-child(5) { width: 70px; }
        .orders-table th:nth-child(6), .orders-table td:nth-child(6) { width: 80px; }
        .orders-table th:nth-child(7), .orders-table td:nth-child(7) { width: 90px; }
        .orders-table th:nth-child(8), .orders-table td:nth-child(8) { width: 110px; }
        
        /* Text Truncation for Long Content */
        .truncate {
            max-width: 100%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 5px;
            }
            
            .dashboard {
                padding: 15px;
            }
            
            .stats-row {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .tabs {
                justify-content: center;
            }
            
            .tab {
                padding: 6px 10px;
                font-size: 10px;
            }
        }
        
        @media (max-width: 480px) {
            .stat-number {
                font-size: 16px;
            }
            
            .stat-card div:last-child {
                font-size: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>🗂️ Deleted Records Archive</h1>
            
            <div class="stats-row">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['customers']; ?></div>
                    <div>Deleted Customers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['books']; ?></div>
                    <div>Deleted Books</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['orders']; ?></div>
                    <div>Deleted Order Records</div>
                </div>
            </div>
            
            <div class="tabs">
                <a href="?tab=customers" class="tab <?php echo $tab === 'customers' ? 'active' : ''; ?>">
                    👥 Customers (<?php echo $stats['customers']; ?>)
                </a>
                <a href="?tab=books" class="tab <?php echo $tab === 'books' ? 'active' : ''; ?>">
                    📚 Books (<?php echo $stats['books']; ?>)
                </a>
                <a href="?tab=orders" class="tab <?php echo $tab === 'orders' ? 'active' : ''; ?>">
                    📋 Orders (<?php echo $stats['orders']; ?>)
                </a>
            </div>
            
            <?php if ($tab === 'customers'): ?>
                <h2>👥 Deleted Customers</h2>
                <div class="table-wrapper">
                    <table class="customers-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Registered</th>
                                <th>Deleted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deleted_customers)): ?>
                                <tr><td colspan="6" class="no-data">No deleted customers found</td></tr>
                            <?php else: ?>
                                <?php foreach ($deleted_customers as $customer): ?>
                                    <tr>
                                        <td><?php echo $customer['original_customer_id']; ?></td>
                                        <td>
                                            <div class="truncate" title="<?php echo htmlspecialchars($customer['name']); ?>">
                                                <?php echo htmlspecialchars(substr($customer['name'], 0, 20)); ?><?php echo strlen($customer['name']) > 20 ? '...' : ''; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="truncate" title="<?php echo htmlspecialchars($customer['email']); ?>">
                                                <?php echo htmlspecialchars(substr($customer['email'], 0, 25)); ?><?php echo strlen($customer['email']) > 25 ? '...' : ''; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="truncate">
                                                <?php echo htmlspecialchars($customer['phone']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px;">
                                                <?php echo date('M d, Y', strtotime($customer['registration_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px;">
                                                <?php echo date('M d, Y', strtotime($customer['deletion_date'])); ?>
                                            </div>
                                            <div style="font-size: 9px; color: #6c757d;">
                                                <?php echo date('H:i', strtotime($customer['deletion_date'])); ?>
                                            </div>
                                            <span class="deletion-badge">DEL</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            
            <?php elseif ($tab === 'books'): ?>
                <h2>📚 Deleted Books</h2>
                <div class="table-wrapper">
                    <table class="books-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Title</th>
                                <th>Author</th>
                                <th>Price</th>
                                <th>Stock</th>
                                <th>Deleted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deleted_books)): ?>
                                <tr><td colspan="6" class="no-data">No deleted books found</td></tr>
                            <?php else: ?>
                                <?php foreach ($deleted_books as $book): ?>
                                    <tr>
                                        <td><?php echo $book['original_book_id']; ?></td>
                                        <td>
                                            <div class="truncate" title="<?php echo htmlspecialchars($book['title']); ?>">
                                                <?php echo htmlspecialchars(substr($book['title'], 0, 30)); ?><?php echo strlen($book['title']) > 30 ? '...' : ''; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="truncate" title="<?php echo htmlspecialchars($book['author']); ?>">
                                                <?php echo htmlspecialchars(substr($book['author'], 0, 20)); ?><?php echo strlen($book['author']) > 20 ? '...' : ''; ?>
                                            </div>
                                        </td>
                                        <td><strong>Rs <?php echo number_format($book['price'], 2); ?></strong></td>
                                        <td><?php echo $book['stock_at_deletion']; ?></td>
                                        <td>
                                            <div style="font-size: 10px;">
                                                <?php echo date('M d, Y', strtotime($book['deletion_date'])); ?>
                                            </div>
                                            <div style="font-size: 9px; color: #6c757d;">
                                                <?php echo date('H:i', strtotime($book['deletion_date'])); ?>
                                            </div>
                                            <span class="deletion-badge">DEL</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
            <?php else: ?>
                <h2>📋 Deleted Order Records</h2>
                <div class="table-wrapper">
                    <table class="orders-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer</th>
                                <th>Book</th>
                                <th>Qty</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Order Date</th>
                                <th>Deleted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deleted_orders)): ?>
                                <tr><td colspan="8" class="no-data">No deleted order records found</td></tr>
                            <?php else: ?>
                                <?php foreach ($deleted_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['original_order_id']; ?></strong></td>
                                        <td>
                                            <div style="line-height: 1.3;">
                                                <div class="truncate" title="<?php echo htmlspecialchars($order['customer_name'] ?? 'Unknown'); ?>">
                                                    <strong><?php echo htmlspecialchars(substr($order['customer_name'] ?? 'Unknown', 0, 15)); ?><?php echo strlen($order['customer_name'] ?? 'Unknown') > 15 ? '...' : ''; ?></strong>
                                                </div>
                                                <div class="truncate" style="font-size: 10px; color: #6c757d;" title="<?php echo htmlspecialchars($order['customer_email'] ?? 'Unknown'); ?>">
                                                    <?php echo htmlspecialchars(substr($order['customer_email'] ?? 'Unknown', 0, 18)); ?><?php echo strlen($order['customer_email'] ?? 'Unknown') > 18 ? '...' : ''; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="line-height: 1.3;">
                                                <div class="truncate" title="<?php echo htmlspecialchars($order['book_title'] ?? 'Unknown'); ?>">
                                                    <strong><?php echo htmlspecialchars(substr($order['book_title'] ?? 'Unknown', 0, 15)); ?><?php echo strlen($order['book_title'] ?? 'Unknown') > 15 ? '...' : ''; ?></strong>
                                                </div>
                                                <div class="truncate" style="font-size: 10px; color: #6c757d;" title="<?php echo htmlspecialchars($order['book_author'] ?? 'Unknown'); ?>">
                                                    <?php echo htmlspecialchars(substr($order['book_author'] ?? 'Unknown', 0, 15)); ?><?php echo strlen($order['book_author'] ?? 'Unknown') > 15 ? '...' : ''; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong><?php echo $order['quantity']; ?></strong></td>
                                        <td><strong>Rs <?php echo number_format($order['total_price'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px;">
                                                <?php echo date('M j, Y', strtotime($order['order_date'])); ?>
                                            </div>
                                            <div style="font-size: 9px; color: #6c757d;">
                                                <?php echo date('H:i', strtotime($order['order_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px; color: #e74c3c;">
                                                <?php echo date('M j, Y', strtotime($order['deletion_date'])); ?>
                                            </div>
                                            <div style="font-size: 9px; color: #6c757d;">
                                                <?php echo date('H:i', strtotime($order['deletion_date'])); ?>
                                            </div>
                                            <span class="deletion-badge">DEL</span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            
            <div class="navigation">
                <a href="dashboard.php" class="btn" style="background: #6c757d; color: white;">← Dashboard</a>
                <a href="view_orders.php" class="btn" style="background: #3498db; color: white;">View Orders</a>
            </div>
        </div>
    </div>
</body>
</html>