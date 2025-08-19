<?php
session_start();
include '../config/db.php';

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$message = '';
$error = '';

// Handle customer actions
if ($_POST) {
    if (isset($_POST['update_customer'])) {
        $customer_id = $_POST['customer_id'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        
        if (strlen($name) < 2) {
            $error = 'Name must be at least 2 characters';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Invalid email address';
        } elseif (!preg_match('/^9\d{9}$/', $phone)) {
            $error = 'Phone must be 10 digits starting with 9';
        } else {
            // Check if email already exists for another customer
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
            $stmt->execute([$email, $customer_id]);
            if ($stmt->fetch()) {
                $error = 'Email already exists';
            } else {
                // Check if phone already exists for another customer
                $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ? AND id != ?");
                $stmt->execute([$phone, $customer_id]);
                if ($stmt->fetch()) {
                    $error = 'Phone number already exists';
                } else {
                    $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?");
                    if ($stmt->execute([$name, $email, $phone, $customer_id])) {
                        $message = 'Customer updated successfully!';
                    } else {
                        $error = 'Failed to update customer';
                    }
                }
            }
        }
    } elseif (isset($_POST['reset_password'])) {
        $customer_id = $_POST['customer_id'];
        $new_password = $_POST['new_password'];
        
        if (strlen($new_password) < 6) {
            $error = 'Password must be at least 6 characters';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE id = ?");
            if ($stmt->execute([$hashed_password, $customer_id])) {
                $message = 'Password reset successfully!';
            } else {
                $error = 'Failed to reset password';
            }
        }
    } elseif (isset($_POST['delete_customer'])) {
        $customer_id = $_POST['customer_id'];
        
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("SELECT name FROM customers WHERE id = ?");
            $stmt->execute([$customer_id]);
            $customer_name = $stmt->fetchColumn();
            
            if ($customer_name) {
                $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
                $stmt->execute([$customer_id]);
                
                $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
                $stmt->execute([$customer_id]);
                
                $pdo->commit();
                $message = "Customer '$customer_name' deleted successfully!";
            } else {
                $error = 'Customer not found';
            }
        } catch (Exception $e) {
            $pdo->rollback();
            $error = 'Failed to delete customer: ' . $e->getMessage();
        }
    }
}

$search = $_GET['search'] ?? '';

// Get customers with search and order statistics
if ($search) {
    $stmt = $pdo->prepare("
        SELECT c.*, 
               COUNT(o.id) as total_orders,
               COALESCE(SUM(o.total_price), 0) as total_spent,
               MAX(o.order_date) as last_order_date
        FROM customers c 
        LEFT JOIN orders o ON c.id = o.customer_id 
        WHERE c.name LIKE ? OR c.email LIKE ? OR c.phone LIKE ?
        GROUP BY c.id 
        ORDER BY c.created_at DESC
    ");
    $stmt->execute(["%$search%", "%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("
        SELECT c.*, 
               COUNT(o.id) as total_orders,
               COALESCE(SUM(o.total_price), 0) as total_spent,
               MAX(o.order_date) as last_order_date
        FROM customers c 
        LEFT JOIN orders o ON c.id = o.customer_id 
        GROUP BY c.id 
        ORDER BY c.created_at DESC
    ");
}
$customers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Manage Customers</title>
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
        
        .search-form {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            align-items: center;
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
        }
        
        .search-form input {
            flex: 1;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .stats-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            margin: 2px;
            font-weight: 500;
        }
        
        .stats-orders { background: #3498db; color: white; }
        .stats-spent { background: #27ae60; color: white; }
        
        .customer-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .customer-actions .btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        th {
            background: #34495e;
            color: white;
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
        }
        
        td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            vertical-align: top;
        }
        
        tr:hover {
            background-color: #f8f9fa;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 5% auto;
            padding: 25px;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }
        
        .close:hover { color: #333; }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .navigation {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Customer Management</h1>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <!-- Search Form -->
            <form method="GET" class="search-form">
                <input type="text" name="search" placeholder="Search by name, email, or phone..." 
                       value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit" class="btn btn-admin">Search</button>
                <?php if ($search): ?>
                    <a href="manage_customers.php" class="btn" style="background: #95a5a6; color: white;">Clear</a>
                <?php endif; ?>
            </form>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer Details</th>
                        <th>Contact</th>
                        <th>Statistics</th>
                        <th>Registered</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($customers as $customer): ?>
                    <tr>
                        <td><?php echo $customer['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($customer['name']); ?></strong><br>
                            <small style="color: #666;"><?php echo htmlspecialchars($customer['email']); ?></small>
                        </td>
                        <td><?php echo htmlspecialchars($customer['phone']); ?></td>
                        <td>
                            <span class="stats-badge stats-orders"><?php echo $customer['total_orders']; ?> orders</span><br>
                            <span class="stats-badge stats-spent">Rs <?php echo number_format($customer['total_spent'], 2); ?></span><br>
                            <?php if ($customer['last_order_date']): ?>
                                <small style="color: #666;">Last: <?php echo date('M d, Y', strtotime($customer['last_order_date'])); ?></small>
                            <?php else: ?>
                                <small style="color: #999;">No orders</small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($customer['created_at'])); ?></td>
                        <td>
                            <div class="customer-actions">
                                <button onclick="editCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($customer['email'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($customer['phone'], ENT_QUOTES); ?>')" 
                                        class="btn btn-small btn-edit">Edit</button>
                                <button onclick="resetPassword(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>')" 
                                        class="btn btn-small" style="background: #f39c12; color: white;">Reset Pass</button>
                                <button onclick="deleteCustomer(<?php echo $customer['id']; ?>, '<?php echo htmlspecialchars($customer['name'], ENT_QUOTES); ?>')" 
                                        class="btn btn-small btn-delete">Delete</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($customers)): ?>
                    <tr>
                        <td colspan="6" style="text-align: center; padding: 40px; color: #666;">
                            <?php echo $search ? 'No customers found matching your search.' : 'No customers registered yet.'; ?>
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <div class="navigation">
                <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white;">← Back to Dashboard</a>
                <a href="view_orders.php" class="btn" style="background: #3498db; color: white; margin-left: 15px;">View Orders</a>
            </div>
        </div>
    </div>

    <!-- Edit Customer Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h2>Edit Customer</h2>
            <form method="POST">
                <input type="hidden" id="edit_customer_id" name="customer_id">
                
                <div class="form-group">
                    <label for="edit_name">Name:</label>
                    <input type="text" id="edit_name" name="name" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_email">Email:</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                
                <div class="form-group">
                    <label for="edit_phone">Phone:</label>
                    <input type="text" id="edit_phone" name="phone" required>
                </div>
                
                <button type="submit" name="update_customer" class="btn btn-admin">Update Customer</button>
                <button type="button" onclick="closeModal('editModal')" class="btn" style="background: #95a5a6; color: white; margin-left: 10px;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Reset Password Modal -->
    <div id="passwordModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('passwordModal')">&times;</span>
            <h2>Reset Password</h2>
            <p>Reset password for: <strong id="password_customer_name"></strong></p>
            <form method="POST">
                <input type="hidden" id="password_customer_id" name="customer_id">
                
                <div class="form-group">
                    <label for="new_password">New Password:</label>
                    <input type="password" id="new_password" name="new_password" minlength="6" required>
                </div>
                
                <button type="submit" name="reset_password" class="btn" style="background: #f39c12; color: white;">Reset Password</button>
                <button type="button" onclick="closeModal('passwordModal')" class="btn" style="background: #95a5a6; color: white; margin-left: 10px;">Cancel</button>
            </form>
        </div>
    </div>

    <!-- Delete Customer Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            <h2 style="color: #e74c3c;">Delete Customer</h2>
            <p>Are you sure you want to delete customer: <strong id="delete_customer_name"></strong>?</p>
            <p style="color: #666; font-size: 14px;">This action cannot be undone.</p>
            
            <form method="POST">
                <input type="hidden" id="delete_customer_id" name="customer_id">
                
                <button type="submit" name="delete_customer" class="btn btn-delete" 
                        onclick="return confirm('Are you absolutely sure?')">Delete Customer</button>
                <button type="button" onclick="closeModal('deleteModal')" class="btn" 
                        style="background: #95a5a6; color: white; margin-left: 10px;">Cancel</button>
            </form>
        </div>
    </div>

    <script>
        function editCustomer(id, name, email, phone) {
            document.getElementById('edit_customer_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_email').value = email;
            document.getElementById('edit_phone').value = phone;
            document.getElementById('editModal').style.display = 'block';
        }

        function resetPassword(id, name) {
            document.getElementById('password_customer_id').value = id;
            document.getElementById('password_customer_name').textContent = name;
            document.getElementById('new_password').value = '';
            document.getElementById('passwordModal').style.display = 'block';
        }

        function deleteCustomer(id, name) {
            document.getElementById('delete_customer_id').value = id;
            document.getElementById('delete_customer_name').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modals = ['editModal', 'passwordModal', 'deleteModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target == modal) {
                    modal.style.display = 'none';
                }
            });
        }
    </script>
</body>
</html>