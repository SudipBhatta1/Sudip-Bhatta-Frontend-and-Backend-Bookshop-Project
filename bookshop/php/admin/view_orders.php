<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$customer_type = $_GET['customer_type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for ACTIVE orders only
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "o.status = ?";
    $params[] = $status_filter;
}

if ($customer_type === 'active') {
    $where_conditions[] = "c.id IS NOT NULL";
} elseif ($customer_type === 'historical') {
    $where_conditions[] = "c.id IS NULL";
}

if ($date_from) {
    $where_conditions[] = "DATE(o.order_date) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(o.order_date) <= ?";
    $params[] = $date_to;
}

if ($search) {
    $where_conditions[] = "(c.name LIKE ? OR c.email LIKE ? OR b.title LIKE ? OR b.author LIKE ? OR o.id = ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = is_numeric($search) ? $search : 0;
}

$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get ACTIVE orders
$stmt = $pdo->prepare("
    SELECT o.*, 
           c.name as customer_name, 
           c.email as customer_email,
           b.title as book_title, 
           b.author as book_author,
           b.price as book_price,
           CASE 
               WHEN c.id IS NULL THEN 'DELETED'
               ELSE 'ACTIVE'
           END as customer_status,
           CASE 
               WHEN b.id IS NULL THEN 'DELETED'
               ELSE 'ACTIVE'
           END as book_status
    FROM orders o 
    LEFT JOIN customers c ON o.customer_id = c.id 
    LEFT JOIN books b ON o.book_id = b.id 
    $where_clause
    ORDER BY o.order_date DESC
");
$stmt->execute($params);
$active_orders = $stmt->fetchAll();

// Build query for DELETED orders (with search if provided)
$deleted_where_conditions = [];
$deleted_params = [];

if ($search) {
    $deleted_where_conditions[] = "(do.customer_name LIKE ? OR do.customer_email LIKE ? OR do.book_title LIKE ? OR do.book_author LIKE ? OR do.original_order_id = ?)";
    $deleted_params[] = "%$search%";
    $deleted_params[] = "%$search%";
    $deleted_params[] = "%$search%";
    $deleted_params[] = "%$search%";
    $deleted_params[] = is_numeric($search) ? $search : 0;
}

$deleted_where_clause = '';
if (!empty($deleted_where_conditions)) {
    $deleted_where_clause = 'WHERE ' . implode(' AND ', $deleted_where_conditions);
}

// Get DELETED orders
$stmt = $pdo->prepare("
    SELECT do.original_order_id as id,
           do.customer_id,
           do.customer_name,
           do.customer_email,
           do.book_id,
           do.book_title,
           do.book_author,
           do.quantity,
           do.total_price,
           do.status,
           do.order_date,
           do.deletion_date,
           do.deletion_reason
    FROM deleted_orders do
    $deleted_where_clause
    ORDER BY do.order_date DESC
");
$stmt->execute($deleted_params);
$deleted_orders = $stmt->fetchAll();

// Get statistics
$stmt = $pdo->query("SELECT COUNT(*) FROM orders");
$total_active_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM deleted_orders");
$total_deleted_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
$pending_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'confirmed'");
$confirmed_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'cancelled'");
$cancelled_orders = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status != 'cancelled'");
$active_revenue = $stmt->fetchColumn() ?: 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Management</title>
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
        
        /* Statistics Grid */
        .stats {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 10px 8px;
            border-radius: 4px;
            text-align: center;
            transition: transform 0.2s;
            cursor: pointer;
            border: 1px solid #e9ecef;
        }
        
        .stat-item:hover { 
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-item.revenue { background: #27ae60; color: white; border-color: #27ae60; }
        .stat-item.active { background: #3498db; color: white; border-color: #3498db; }
        .stat-item.deleted { background: #e74c3c; color: white; border-color: #e74c3c; }
        .stat-item.pending { background: #f39c12; color: white; border-color: #f39c12; }
        .stat-item.confirmed { background: #27ae60; color: white; border-color: #27ae60; }
        .stat-item.cancelled { background: #95a5a6; color: white; border-color: #95a5a6; }
        
        .stat-number {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .stat-label {
            font-size: 10px;
            opacity: 0.9;
            line-height: 1.2;
        }
        
        /* Filters Section */
        .filters-section {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 15px;
            border: 1px solid #e9ecef;
        }
        
        .filters-section h3 {
            margin-bottom: 10px;
            font-size: 14px;
            color: #2c3e50;
        }
        
        .filters-form {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 8px;
            align-items: end;
        }
        
        .form-group {
            display: flex;
            flex-direction: column;
        }
        
        .form-group label {
            font-weight: 500;
            font-size: 11px;
            margin-bottom: 3px;
            color: #495057;
        }
        
        .filters-form select, 
        .filters-form input {
            padding: 6px 8px;
            border: 1px solid #ced4da;
            border-radius: 3px;
            font-size: 12px;
            background: white;
        }
        
        .filters-form select:focus,
        .filters-form input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        /* Section Headers */
        .section {
            margin-bottom: 25px;
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
            padding-bottom: 6px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #2c3e50;
        }
        
        .section-count {
            background: #3498db;
            color: white;
            padding: 4px 8px;
            border-radius: 10px;
            font-weight: 500;
            font-size: 10px;
        }
        
        .deleted-section .section-count {
            background: #e74c3c;
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
            background: #34495e;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 500;
            font-size: 10px;
            white-space: nowrap;
            border-right: 1px solid #2c3e50;
        }
        
        .deleted-section th {
            background: #e74c3c;
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
        
        /* Table Content Styling */
        .customer-info, .book-info {
            line-height: 1.3;
            min-width: 120px;
        }
        
        .customer-name, .book-title {
            font-weight: 500;
            color: #2c3e50;
            font-size: 11px;
            margin-bottom: 2px;
        }
        
        .customer-email, .book-author {
            color: #6c757d;
            font-size: 10px;
        }
        
        .deleted-item {
            color: #e74c3c;
            font-style: italic;
            font-weight: 500;
            font-size: 10px;
        }
        
        /* Badges */
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
        
        .deleted-badge {
            background: #e74c3c;
            color: white;
            padding: 1px 4px;
            border-radius: 2px;
            font-size: 8px;
            font-weight: 500;
            margin-left: 4px;
        }
        
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
        
        .btn-admin {
            background: #3498db;
            color: white;
        }
        
        .btn-admin:hover {
            background: #2980b9;
        }
        
        .no-results {
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
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .stats {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .filters-form {
                grid-template-columns: repeat(3, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 5px;
            }
            
            .dashboard {
                padding: 15px;
            }
            
            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 5px;
            }
        }
        
        @media (max-width: 480px) {
            .stats {
                grid-template-columns: 1fr;
            }
            
            .stat-number {
                font-size: 14px;
            }
            
            .stat-label {
                font-size: 9px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>📊 Order Management</h1>
            
            <!-- Statistics -->
            <div class="stats">
                <div class="stat-item revenue">
                    <div class="stat-number">Rs <?php echo number_format($active_revenue, 0); ?></div>
                    <div class="stat-label">Active Revenue</div>
                </div>
                <div class="stat-item active" onclick="clearFilters()">
                    <div class="stat-number"><?php echo $total_active_orders; ?></div>
                    <div class="stat-label">Active Orders</div>
                </div>
                <div class="stat-item deleted">
                    <div class="stat-number"><?php echo $total_deleted_orders; ?></div>
                    <div class="stat-label">Deleted Orders</div>
                </div>
                <div class="stat-item confirmed" onclick="filterByStatus('confirmed')">
                    <div class="stat-number"><?php echo $confirmed_orders; ?></div>
                    <div class="stat-label">Confirmed</div>
                </div>
                <div class="stat-item pending" onclick="filterByStatus('pending')">
                    <div class="stat-number"><?php echo $pending_orders; ?></div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-item cancelled" onclick="filterByStatus('cancelled')">
                    <div class="stat-number"><?php echo $cancelled_orders; ?></div>
                    <div class="stat-label">Cancelled</div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filters-section">
                <h3>🔍 Filter Active Orders</h3>
                <form method="GET" class="filters-form">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Customer Type</label>
                        <select name="customer_type">
                            <option value="">All Orders</option>
                            <option value="active" <?php echo $customer_type === 'active' ? 'selected' : ''; ?>>Active Customers</option>
                            <option value="historical" <?php echo $customer_type === 'historical' ? 'selected' : ''; ?>>Historical Orders</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>From Date</label>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>To Date</label>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label>Search</label>
                        <input type="text" name="search" placeholder="Order ID, customer, book..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    
                    <div class="form-group">
                        <button type="submit" class="btn btn-admin">Apply</button>
                        <?php if ($status_filter || $customer_type || $date_from || $date_to || $search): ?>
                            <a href="view_orders.php" class="btn" style="background: #6c757d; color: white;">Clear</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            
            <!-- ACTIVE ORDERS SECTION -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">✅ Active Orders</h2>
                    <span class="section-count"><?php echo count($active_orders); ?> orders</span>
                </div>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 140px;">Customer</th>
                                <th style="width: 140px;">Book</th>
                                <th style="width: 40px;">Qty</th>
                                <th style="width: 70px;">Unit Rs</th>
                                <th style="width: 70px;">Total</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 90px;">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($active_orders)): ?>
                                <tr>
                                    <td colspan="8" class="no-results">
                                        <?php echo $search ? 'No active orders found matching your search.' : 'No active orders found.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($active_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td>
                                            <div class="customer-info">
                                                <?php if ($order['customer_status'] === 'ACTIVE'): ?>
                                                    <div class="customer-name"><?php echo htmlspecialchars(substr($order['customer_name'], 0, 25)); ?><?php echo strlen($order['customer_name']) > 25 ? '...' : ''; ?></div>
                                                    <div class="customer-email"><?php echo htmlspecialchars(substr($order['customer_email'], 0, 20)); ?><?php echo strlen($order['customer_email']) > 20 ? '...' : ''; ?></div>
                                                <?php else: ?>
                                                    <div class="deleted-item">Customer Deleted</div>
                                                    <span class="deleted-badge">DEL</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="book-info">
                                                <?php if ($order['book_status'] === 'ACTIVE'): ?>
                                                    <div class="book-title"><?php echo htmlspecialchars(substr($order['book_title'], 0, 25)); ?><?php echo strlen($order['book_title']) > 25 ? '...' : ''; ?></div>
                                                    <div class="book-author"><?php echo htmlspecialchars(substr($order['book_author'], 0, 20)); ?><?php echo strlen($order['book_author']) > 20 ? '...' : ''; ?></div>
                                                <?php else: ?>
                                                    <div class="deleted-item">Book Deleted</div>
                                                    <span class="deleted-badge">DEL</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td><strong><?php echo $order['quantity']; ?></strong></td>
                                        <td>
                                            <?php if ($order['book_status'] === 'ACTIVE'): ?>
                                                Rs <?php echo number_format($order['book_price'], 2); ?>
                                            <?php else: ?>
                                                <span class="deleted-item">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>Rs <?php echo number_format($order['total_price'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px;"><?php echo date('M j, Y', strtotime($order['order_date'])); ?></div>
                                            <div style="font-size: 9px; color: #6c757d;"><?php echo date('H:i', strtotime($order['order_date'])); ?></div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- DELETED ORDERS SECTION -->
            <?php if (!empty($deleted_orders) || $search): ?>
            <div class="section deleted-section">
                <div class="section-header">
                    <h2 class="section-title">🗑️ Deleted Order Records</h2>
                    <span class="section-count"><?php echo count($deleted_orders); ?> records</span>
                </div>
                
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th style="width: 60px;">ID</th>
                                <th style="width: 130px;">Customer</th>
                                <th style="width: 130px;">Book</th>
                                <th style="width: 40px;">Qty</th>
                                <th style="width: 70px;">Total</th>
                                <th style="width: 80px;">Status</th>
                                <th style="width: 90px;">Order Date</th>
                                <th style="width: 100px;">Deleted</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($deleted_orders)): ?>
                                <tr>
                                    <td colspan="8" class="no-results">
                                        <?php echo $search ? 'No deleted order records found matching your search.' : 'No deleted order records found.'; ?>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($deleted_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['id']; ?></strong></td>
                                        <td>
                                            <div class="customer-info">
                                                <div class="customer-name"><?php echo htmlspecialchars(substr($order['customer_name'], 0, 20)); ?><?php echo strlen($order['customer_name']) > 20 ? '...' : ''; ?></div>
                                                <div class="customer-email"><?php echo htmlspecialchars(substr($order['customer_email'], 0, 18)); ?><?php echo strlen($order['customer_email']) > 18 ? '...' : ''; ?></div>
                                                <span class="deleted-badge">DEL</span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="book-info">
                                                <div class="book-title"><?php echo htmlspecialchars(substr($order['book_title'], 0, 20)); ?><?php echo strlen($order['book_title']) > 20 ? '...' : ''; ?></div>
                                                <div class="book-author"><?php echo htmlspecialchars(substr($order['book_author'], 0, 18)); ?><?php echo strlen($order['book_author']) > 18 ? '...' : ''; ?></div>
                                                <span class="deleted-badge">DEL</span>
                                            </div>
                                        </td>
                                        <td><strong><?php echo $order['quantity']; ?></strong></td>
                                        <td><strong>$<?php echo number_format($order['total_price'], 2); ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div style="font-size: 10px;"><?php echo date('M j, Y', strtotime($order['order_date'])); ?></div>
                                            <div style="font-size: 9px; color: #6c757d;"><?php echo date('H:i', strtotime($order['order_date'])); ?></div>
                                        </td>
                                        <td>
                                            <div style="color: #e74c3c; font-size: 10px;">
                                                <?php echo date('M j, Y', strtotime($order['deletion_date'])); ?>
                                            </div>
                                            <div style="font-size: 9px; color: #6c757d;">
                                                <?php echo date('H:i', strtotime($order['deletion_date'])); ?>
                                            </div>
                                            <?php if ($order['deletion_reason']): ?>
                                                <div style="font-size: 8px; color: #6c757d; margin-top: 1px;" title="<?php echo htmlspecialchars($order['deletion_reason']); ?>">
                                                    <?php echo htmlspecialchars(substr($order['deletion_reason'], 0, 15)); ?><?php echo strlen($order['deletion_reason']) > 15 ? '...' : ''; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Navigation -->
            <div class="navigation">
                <a href="dashboard.php" class="btn" style="background: #6c757d; color: white;">← Dashboard</a>
                <a href="manage_orders.php" class="btn btn-admin">Manage Orders</a>
                <a href="manage_customers.php" class="btn" style="background: #9b59b6; color: white;">Customers</a>
                <a href="view_deleted_records.php" class="btn" style="background: #e74c3c; color: white;">Deleted Records</a>
            </div>
        </div>
    </div>

    <script>
        function filterByStatus(status) {
            const url = new URL(window.location);
            url.searchParams.set('status', status);
            window.location = url;
        }
        
        function clearFilters() {
            window.location = 'view_orders.php';
        }
    </script>
</body>
</html>