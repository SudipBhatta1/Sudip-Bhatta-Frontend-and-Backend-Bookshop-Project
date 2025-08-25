<?php
session_start();
include '../config/db.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: ../../customer_login.php');
    exit();
}

$customer_id = $_SESSION['customer_id'];
$message = '';

// Handle remove from cart
if ($_POST && isset($_POST['remove_item'])) {
    $cart_id = $_POST['cart_id'];
    $stmt = $pdo->prepare("DELETE FROM cart WHERE id = ? AND customer_id = ?");
    $stmt->execute([$cart_id, $customer_id]);
    $message = 'Item removed from cart!';
}

// Handle update quantity
if ($_POST && isset($_POST['update_quantity'])) {
    $cart_id = $_POST['cart_id'];
    $quantity = max(1, intval($_POST['quantity']));
    $stmt = $pdo->prepare("UPDATE cart SET quantity = ? WHERE id = ? AND customer_id = ?");
    $stmt->execute([$quantity, $cart_id, $customer_id]);
    $message = 'Quantity updated!';
}

// Get cart items
$stmt = $pdo->prepare("
    SELECT c.*, b.title, b.author, b.price, b.stock 
    FROM cart c 
    JOIN books b ON c.book_id = b.id 
    WHERE c.customer_id = ?
");
$stmt->execute([$customer_id]);
$cart_items = $stmt->fetchAll();

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
    <title>My Cart - Customer</title>
    <link rel="stylesheet" href="../../css/styles.css">
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>My Shopping Cart</h1>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <?php if (empty($cart_items)): ?>
                <p>Your cart is empty. <a href="view_books.php">Browse books</a> to add items.</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>Book</th>
                                <th>Price</th>
                                <th>Quantity</th>
                                <th>Subtotal</th>
                                <th>Actions</th>
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
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" 
                                               min="1" max="<?php echo $item['stock']; ?>" style="width: 60px;">
                                        <button type="submit" name="update_quantity" class="btn btn-small" style="background: #3498db; color: white;">Update</button>
                                    </form>
                                </td>
                                <td>Rs <?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                <td>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="cart_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="remove_item" class="btn btn-small btn-delete" 
                                                onclick="return confirm('Remove this item from cart?')">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr style="font-weight: bold; background: #f8f9fa;">
                                <td colspan="3">Total:</td>
                                <td>Rs <?php echo number_format($total, 2); ?></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            
                <div style="margin-top: 20px;">
                    <a href="place_order.php" class="btn btn-customer">Place Order</a>
                    <a href="view_books.php" class="btn" style="background: #95a5a6; color: white; margin-left: 10px;">Continue Shopping</a>
                </div>
            <?php endif; ?>
            
            <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white; margin-top: 20px;">Back to Dashboard</a>
        </div>
    </div>
</body>

</html>
