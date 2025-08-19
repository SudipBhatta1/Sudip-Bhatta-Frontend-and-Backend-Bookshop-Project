<?php
session_start();
include '../config/db.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: ../../customer_login.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];
$success = '';
$error = '';

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.*, b.title, b.author, b.price, b.stock 
    FROM cart c 
    JOIN books b ON c.book_id = b.id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll();

if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// Handle place order
if ($_POST && isset($_POST['place_order'])) {
    try {
        $pdo->beginTransaction();
        
        $order_success = true;
        
        foreach ($cart_items as $item) {
            // Check stock availability
            if ($item['stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for " . $item['title']);
            }
            
            // Create order
            $total_price = $item['price'] * $item['quantity'];
            $stmt = $pdo->prepare("INSERT INTO orders (customer_id, book_id, quantity, total_price) VALUES (?, ?, ?, ?)");
            $stmt->execute([$customer_id, $item['book_id'], $item['quantity'], $total_price]);
            
            // Update book stock
            $stmt = $pdo->prepare("UPDATE books SET stock = stock - ? WHERE id = ?");
            $stmt->execute([$item['quantity'], $item['book_id']]);
        }
        
        // Clear cart
        $stmt = $pdo->prepare("DELETE FROM cart WHERE customer_id = ?");
        $stmt->execute([$customer_id]);
        
        $pdo->commit();
        $success = 'Order placed successfully!';
        $cart_items = []; // Clear items for display
        
    } catch (Exception $e) {
        $pdo->rollback();
        $error = $e->getMessage();
    }
}

$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Place Order - Customer</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Place Order</h1>
            
            <?php if ($success): ?>
                <div class="success">
                    <?php echo $success; ?>
                    <p><a href="order_history.php">View your orders</a> | <a href="view_books.php">Continue shopping</a></p>
                </div>
            <?php elseif ($error): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($cart_items)): ?>
                <h3>Order Summary</h3>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <?php echo htmlspecialchars($item['title']); ?><br>
                                    <small>by <?php echo htmlspecialchars($item['author']); ?></small>
                                </td>
                                <td>Rs <?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>Rs <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background: #f8f9fa;">
                                <td colspan="3">Total Amount:</td>
                                <td>Rs <?php echo number_format($total, 2); ?></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <form method="POST" style="margin-top: 20px;">
                    <button type="submit" name="place_order" class="btn btn-customer" 
                            onclick="return confirm('Confirm your order?')">Confirm Order</button>
                    <a href="cart.php" class="btn" style="background: #95a5a6; color: white; margin-left: 10px;">Back to Cart</a>
                </form>
            <?php endif; ?>
            
            <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white; margin-top: 20px;">Back to Dashboard</a>
        </div>
    </div>
</body>
</html>