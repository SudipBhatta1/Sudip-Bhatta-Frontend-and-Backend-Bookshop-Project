<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

$success = '';
$errors = []; // Changed from $error to $errors array

if ($_POST) {
    $title = trim($_POST['title']);
    $author = trim($_POST['author']);
    $price = $_POST['price'];
    $stock = $_POST['stock'];
    
    // Specific validation for each field
    if (empty($title)) {
        $errors['title'] = 'Please enter a book title';
    }
    
    if (empty($author)) {
        $errors['author'] = 'Please enter an author name';
    } elseif (is_numeric($author)) {
        $errors['author'] = 'Author name cannot be only numbers';
    } elseif (!preg_match('/[a-zA-Z]/', $author)) {
        $errors['author'] = 'Author name must contain at least one letter';
    }
    
    if (empty($price)) {
        $errors['price'] = 'Please enter a price';
    } elseif (!is_numeric($price) || floatval($price) <= 0) {
        $errors['price'] = 'Price must be a valid number greater than 0';
    }
    
    if (empty($stock)) {
        $errors['stock'] = 'Please enter stock quantity';
    } elseif (!is_numeric($stock) || intval($stock) < 0) {
        $errors['stock'] = 'Stock must be a valid number (0 or greater)';
    }
    
    // If no validation errors, proceed with database operations
    if (empty($errors)) {
        $price = floatval($price);
        $stock = intval($stock);
        
        try {
            // Check if book with same title and author already exists (case-insensitive)
            $stmt = $pdo->prepare("SELECT id, title, author FROM books WHERE LOWER(TRIM(title)) = LOWER(TRIM(?)) AND LOWER(TRIM(author)) = LOWER(TRIM(?))");
            $stmt->execute([$title, $author]);
            $existing_book = $stmt->fetch();
            
            if ($existing_book) {
                $errors['general'] = 'A book with this title and author already exists';
            } else {
                $stmt = $pdo->prepare("INSERT INTO books (title, author, price, stock) VALUES (?, ?, ?, ?)");
                if ($stmt->execute([$title, $author, $price, $stock])) {
                    $success = 'Book added successfully!';
                    $_POST = array(); // Clear form data
                } else {
                    $errors['general'] = 'Failed to add book. Please try again';
                }
            }
        } catch (PDOException $e) {
            error_log("Database error during book addition: " . $e->getMessage());
            $errors['general'] = 'Database error. Please try again later';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Book - Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <style>
        .container {
            max-width: 600px;
            margin: 30px auto;
            padding: 0 15px;
        }
        
        .dashboard {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .dashboard h1 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
            text-align: center;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
            color: #34495e;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 8px 12px;
            border-radius: 4px;
            margin-top: 5px;
            font-size: 13px;
        }
        
        .error.general {
            margin-bottom: 15px;
            text-align: center;
            margin-top: 0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 16px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            margin-right: 8px;
            margin-bottom: 8px;
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
        
        .button-group {
            margin-top: 20px;
            text-align: center;
        }
    </style>
    <script>
        // Optional: You can add client-side validation here if needed
        // Currently relying on server-side validation for better UX
    </script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>📚 Add New Book</h1>
            
            <?php if ($success): ?>
                <div class="success">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error general">
                    <?php echo htmlspecialchars($errors['general']); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" id="bookForm" novalidate>
                <div class="form-group">
                    <label for="title">Book Title *</label>
                    <input type="text" id="title" name="title" 
                           value="<?php echo isset($_POST['title']) ? htmlspecialchars($_POST['title']) : ''; ?>" 
                           placeholder="Enter book title" required>
                    <?php if (isset($errors['title'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['title']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="author">Author *</label>
                    <input type="text" id="author" name="author" 
                           value="<?php echo isset($_POST['author']) ? htmlspecialchars($_POST['author']) : ''; ?>" 
                           placeholder="Enter author name" required>
                    <?php if (isset($errors['author'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['author']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="price">Price (Rs) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" 
                           value="<?php echo isset($_POST['price']) ? htmlspecialchars($_POST['price']) : ''; ?>" 
                           placeholder="0.00" required>
                    <?php if (isset($errors['price'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['price']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="stock">Stock Quantity *</label>
                    <input type="number" id="stock" name="stock" min="0" 
                           value="<?php echo isset($_POST['stock']) ? htmlspecialchars($_POST['stock']) : ''; ?>" 
                           placeholder="0" required>
                    <?php if (isset($errors['stock'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['stock']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="button-group">
                    <button type="submit" class="btn btn-admin">Add Book</button>
                    <a href="dashboard.php" class="btn btn-default">Back</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>