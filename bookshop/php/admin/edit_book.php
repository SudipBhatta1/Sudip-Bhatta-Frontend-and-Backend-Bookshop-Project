<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$success = '';
$error = '';
$book = null;

// Get book ID from URL parameter
$book_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($book_id <= 0) {
    header('Location: dashboard.php');
    exit();
}

// Fetch book details
try {
    $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
    $stmt->execute([$book_id]);
    $book = $stmt->fetch();
    
    if (!$book) {
        $error = 'Book not found.';
    }
} catch (PDOException $e) {
    $error = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_POST && $book) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $price = floatval($_POST['price']);
    $stock = intval($_POST['stock']);
    
    // Validate author name - should not be only numbers
    if (is_numeric($author)) {
        $error = 'Author name cannot be only numbers.';
    } elseif (!empty($title) && !empty($author) && $price > 0 && $stock >= 0) {
        // Additional validation for author name - should contain at least one letter
        if (!preg_match('/[a-zA-Z]/', $author)) {
            $error = 'Author name must contain at least one letter.';
        } else {
            // Check if another book with same title and author already exists (excluding current book)
            $stmt = $pdo->prepare("SELECT id FROM books WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) AND LOWER(TRIM(author)) = LOWER(TRIM(?)) AND id != ?");
            $stmt->execute([$title, $author, $book_id]);
            $existing_book = $stmt->fetch();
            
            if ($existing_book) {
                $error = 'A book with this title and author already exists.';
            } else {
                // Update the book
                try {
                    $stmt = $pdo->prepare("UPDATE books SET title = ?, author = ?, price = ?, stock = ? WHERE id = ?");
                    if ($stmt->execute([$title, $author, $price, $stock, $book_id])) {
                        $success = 'Book updated successfully!';
                        // Refresh book data
                        $stmt = $pdo->prepare("SELECT * FROM books WHERE id = ?");
                        $stmt->execute([$book_id]);
                        $book = $stmt->fetch();
                    } else {
                        $error = 'Failed to update book. Please try again.';
                    }
                } catch (PDOException $e) {
                    $error = 'Database error: ' . $e->getMessage();
                }
            }
        }
    } else {
        $error = 'Please fill all fields with valid data.';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Book - Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .container {
            max-width: 500px;
            margin: 15px auto;
            padding: 0 10px;
        }
        
        .dashboard {
            background: white;
            border-radius: 6px;
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
            padding: 20px;
        }
        
        .dashboard h1 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 20px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 12px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 4px;
            font-weight: bold;
            color: #34495e;
            font-size: 12px;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 12px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 8px;
            border-radius: 3px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 12px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 8px;
            border-radius: 3px;
            margin-bottom: 12px;
            text-align: center;
            font-size: 12px;
        }
        
        .btn {
            display: inline-block;
            padding: 6px 10px;
            border: none;
            border-radius: 3px;
            text-decoration: none;
            font-size: 11px;
            cursor: pointer;
            margin-right: 6px;
            margin-bottom: 6px;
        }
        
        .btn-admin {
            background: #e74c3c;
            color: white;
        }
        
        .btn-secondary {
            background: #f39c12;
            color: white;
        }
        
        .btn-default {
            background: #95a5a6;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.9;
        }
        
        .button-group {
            margin-top: 15px;
            text-align: center;
        }
        
        .book-info {
            background: #f8f9fa;
            padding: 12px;
            border-radius: 3px;
            margin-bottom: 15px;
            border-left: 3px solid #3498db;
        }
        
        .book-info h3 {
            margin: 0 0 8px 0;
            color: #2c3e50;
            font-size: 14px;
        }
        
        .book-info p {
            margin: 4px 0;
            color: #6c757d;
            font-size: 11px;
        }
    </style>
    <script>
        function validateForm() {
            const title = document.getElementById('title').value.trim();
            const author = document.getElementById('author').value.trim();
            const price = parseFloat(document.getElementById('price').value);
            const stock = parseInt(document.getElementById('stock').value);
            
            if (title === '') {
                alert('Please enter a book title');
                return false;
            }
            
            if (author === '') {
                alert('Please enter an author name');
                return false;
            }
            
            if (/^\d+$/.test(author)) {
                alert('Author name cannot be only numbers.');
                return false;
            }
            
            if (!/[a-zA-Z]/.test(author)) {
                alert('Author name must contain at least one letter.');
                return false;
            }
            
            if (isNaN(price) || price <= 0) {
                alert('Please enter a valid price greater than 0');
                return false;
            }
            
            if (isNaN(stock) || stock < 0) {
                alert('Please enter a valid stock quantity');
                return false;
            }
            
            return true;
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Edit Book</h1>
            
            <?php if ($error && !$book): ?>
                <div class="error">
                    <?php echo htmlspecialchars($error); ?>
                </div>
                <div class="button-group">
                    <a href="dashboard.php" class="btn btn-default">Back to Dashboard</a>
                </div>
            <?php elseif ($book): ?>
                
                <?php if ($success): ?>
                    <div class="success">
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($error): ?>
                    <div class="error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <div class="book-info">
                    <h3>Editing Book ID: <?php echo htmlspecialchars($book['id']); ?></h3>
                    <p><strong>Original Title:</strong> <?php echo htmlspecialchars($book['title']); ?></p>
                    <p><strong>Original Author:</strong> <?php echo htmlspecialchars($book['author']); ?></p>
                </div>
                
                <div class="form-single-column">
                    <form method="POST" id="bookForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label for="title">Book Title *</label>
                            <input type="text" id="title" name="title" 
                                   value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : htmlspecialchars($book['title']); ?>" 
                                   placeholder="Enter book title" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="author">Author *</label>
                            <input type="text" id="author" name="author" 
                                   value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : htmlspecialchars($book['author']); ?>" 
                                   placeholder="Enter author name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="price">Price (Rs) *</label>
                            <input type="number" id="price" name="price" step="0.01" min="0.01" 
                                   value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : htmlspecialchars($book['price']); ?>" 
                                   placeholder="0.00" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="stock">Stock Quantity *</label>
                            <input type="number" id="stock" name="stock" min="0" 
                                   value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : htmlspecialchars($book['stock']); ?>" 
                                   placeholder="0" required>
                        </div>
                        
                        <div class="button-group">
                            <button type="submit" class="btn btn-admin">Update Book</button>
                            <a href="dashboard.php" class="btn btn-default">Cancel</a>
                            <a href="view_books.php" class="btn btn-secondary">View All Books</a>
                        </div>
                    </form>
                </div>
                
            <?php endif; ?>
        </div>
    </div>
</body>
</html>