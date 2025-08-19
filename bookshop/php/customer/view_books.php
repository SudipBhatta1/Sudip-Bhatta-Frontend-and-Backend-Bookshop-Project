<?php
session_start();
include '../config/db.php';

// Check if customer is logged in
if (!isset($_SESSION['customer_id'])) {
    header('Location: ../../customer_login.php');
    exit();
}

$search = $_GET['search'] ?? '';
$message = '';

// Handle add to cart
if ($_POST && isset($_POST['add_to_cart'])) {
    $book_id = $_POST['book_id'];
    $customer_id = $_SESSION['customer_id'];
    
    // Get book details for the message
    $stmt = $pdo->prepare("SELECT title FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    // Check if book already in cart
    $stmt = $pdo->prepare("SELECT * FROM cart WHERE customer_id = ? AND book_id = ?");
    $stmt->execute([$customer_id, $book_id]);
    
    if ($stmt->fetch()) {
        // Update quantity
        $stmt = $pdo->prepare("UPDATE cart SET quantity = quantity + 1 WHERE customer_id = ? AND book_id = ?");
        $stmt->execute([$customer_id, $book_id]);
        $message = '"' . htmlspecialchars($book['title']) . '" quantity updated in cart!';
    } else {
        // Add new item
        $stmt = $pdo->prepare("INSERT INTO cart (customer_id, book_id, quantity) VALUES (?, ?, 1)");
        $stmt->execute([$customer_id, $book_id]);
        $message = '"' . htmlspecialchars($book['title']) . '" added to cart successfully!';
    }
}

// Get books with search
if ($search) {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE title LIKE ? OR author LIKE ? ORDER BY title");
    $stmt->execute(["%$search%", "%$search%"]);
} else {
    $stmt = $pdo->query("SELECT * FROM books ORDER BY title");
}
$books = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Books - Customer</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        /* Ensure proper centering for customer pages */
        body {
            display: flex !important;
            align-items: flex-start !important;
            justify-content: center !important;
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            background-attachment: fixed;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px 10px;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            margin: 0;
            padding: 0;
        }
        
        .dashboard {
            background: white;
            border-radius: 15px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            padding: 30px;
            width: 100%;
            position: relative;
            margin: 0;
        }
        
        .dashboard h1 {
            color: #333;
            margin-bottom: 25px;
            font-size: 2em;
            font-weight: 700;
        }
        
        .success {
            color: #27ae60;
            font-size: 1em;
            margin-bottom: 20px;
            font-weight: 500;
            background: rgba(39, 174, 96, 0.1);
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #27ae60;
        }
        
        .form-group {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .form-group input[type="text"] {
            flex: 1;
            margin: 0;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 1em;
            transition: border-color 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .table-container {
            width: 100%;
            overflow-x: auto;
            margin: 20px 0;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }
        
        table {
            width: 100%;
            min-width: 600px;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            font-weight: 600;
            color: white;
            font-size: 0.95em;
            white-space: nowrap;
        }
        
        tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .btn {
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 8px;
            font-weight: bold;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            display: inline-block;
            text-align: center;
            font-size: 0.95em;
        }
        
        .btn-customer {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
        }
        
        .btn-customer:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            margin-right: 15px;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(149, 165, 166, 0.3);
        }
        
        .btn-small {
            padding: 8px 15px;
            margin: 3px;
            font-size: 0.9em;
            border-radius: 6px;
        }
        
        /* Out of stock styling */
        .out-of-stock {
            color: #e74c3c;
            font-weight: bold;
            font-style: italic;
        }
        
        /* Top navigation button row styling */
        .top-nav-row {
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
            align-items: center;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 15px 8px;
            }
            
            .container {
                max-width: 100%;
            }
            
            .dashboard {
                padding: 20px;
            }
            
            .dashboard h1 {
                font-size: 1.8em;
            }
            
            .form-group {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .form-group input[type="text"] {
                width: 100%;
            }
            
            table {
                min-width: 500px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.9em;
            }
            
            .top-nav-row {
                justify-content: center;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px 5px;
            }
            
            .dashboard {
                padding: 15px;
            }
            
            .dashboard h1 {
                font-size: 1.6em;
                margin-bottom: 20px;
            }
            
            th, td {
                padding: 8px 6px;
                font-size: 0.85em;
            }
            
            .btn {
                padding: 10px 16px;
                font-size: 0.9em;
            }
            
            table {
                min-width: 450px;
            }
            
            .top-nav-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-back,
            .btn-customer {
                margin-right: 0;
                margin-bottom: 10px;
            }
        }
    </style>
    <script>
        function confirmAddToCart(bookTitle) {
            return confirm('Are you sure you want to add "' + bookTitle + '" to your cart?');
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Browse Books</h1>
            
            <!-- Top Navigation Buttons -->
            <div class="top-nav-row">
                <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
                <a href="cart.php" class="btn btn-customer">View Cart</a>
            </div>
            
            <?php if ($message): ?>
                <div class="success"><?php echo $message; ?></div>
            <?php endif; ?>
            
            <!-- Search Form -->
            <form method="GET">
                <div class="form-group">
                    <input type="text" name="search" placeholder="Search by title or author..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-customer">Search</button>
                    <?php if ($search): ?>
                        <a href="view_books.php" class="btn" style="background: linear-gradient(135deg, #95a5a6, #7f8c8d); color: white;">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="5" style="text-align: center; padding: 40px;">
                                <p style="margin: 0; color: #666; font-size: 16px;">
                                    <?php if ($search): ?>
                                        No books found matching "<?php echo htmlspecialchars($search); ?>". 
                                        <a href="view_books.php" style="color: #3498db;">View all books</a>
                                    <?php else: ?>
                                        No books available at the moment.
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td>Rs <?php echo number_format((float)$book['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($book['stock']); ?></td>
                                <td>
                                    <?php if ($book['stock'] > 0): ?>
                                        <form method="POST" style="display: inline;" onsubmit="return confirmAddToCart('<?php echo htmlspecialchars($book['title'], ENT_QUOTES); ?>')">
                                            <input type="hidden" name="book_id" value="<?php echo $book['id']; ?>">
                                            <button type="submit" name="add_to_cart" class="btn btn-small btn-customer">Add to Cart</button>
                                        </form>
                                    <?php else: ?>
                                        <span class="out-of-stock">Out of Stock</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>