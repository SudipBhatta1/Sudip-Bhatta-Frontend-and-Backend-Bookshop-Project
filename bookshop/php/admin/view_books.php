<?php
session_start();
include '../config/db.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: ../../admin_login.php');
    exit();
}

// Handle search
$search = $_GET['search'] ?? '';
$where_clause = '';
$params = [];

if (!empty($search)) {
    $where_clause = "WHERE title LIKE ? OR author LIKE ? OR id LIKE ?";
    $search_param = '%' . $search . '%';
    $params = [$search_param, $search_param, $search_param];
}

// Get books with optional search
$sql = "SELECT * FROM books $where_clause ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Books - Admin</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/scripts.js"></script>
    <style>
        /* Ensure proper centering for admin pages */
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
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Search Section */
        .search-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
        }
        
        .search-form {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .search-input {
            flex: 1;
            min-width: 200px;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.3s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }
        
        .btn-search {
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white;
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .btn-search:hover {
            background: linear-gradient(135deg, #2980b9, #21618c);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(52, 152, 219, 0.3);
        }
        
        .btn-clear {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: bold;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            font-size: 16px;
        }
        
        .btn-clear:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
        }
        
        .search-results {
            margin-top: 15px;
            color: #666;
            font-style: italic;
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
            min-width: 700px;
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
        
        .btn-admin {
            background: linear-gradient(135deg, #27ae60, #229954);
            color: white;
            margin-bottom: 20px;
        }
        
        .btn-admin:hover {
            background: linear-gradient(135deg, #229954, #1e8449);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(39, 174, 96, 0.3);
        }
        
        .btn-back {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
            margin-bottom: 20px;
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
            text-decoration: none;
            display: inline-block;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #f39c12, #e67e22);
            color: white;
        }
        
        .btn-edit:hover {
            background: linear-gradient(135deg, #e67e22, #d68910);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .btn-delete:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
        }
        
        /* Top button row styling */
        .top-button-row {
            margin-bottom: 25px;
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            justify-content: flex-start;
            align-items: center;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background-color: white;
            margin: 15% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal h2 {
            color: #e74c3c;
            margin-bottom: 20px;
            font-size: 1.5em;
        }
        
        .modal p {
            color: #555;
            margin-bottom: 30px;
            font-size: 16px;
            line-height: 1.5;
        }
        
        .modal-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .modal-btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        
        .modal-btn-delete {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
        }
        
        .modal-btn-delete:hover {
            background: linear-gradient(135deg, #c0392b, #a93226);
            transform: translateY(-2px);
        }
        
        .modal-btn-cancel {
            background: linear-gradient(135deg, #95a5a6, #7f8c8d);
            color: white;
        }
        
        .modal-btn-cancel:hover {
            background: linear-gradient(135deg, #7f8c8d, #6c7b7d);
            transform: translateY(-2px);
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
            
            table {
                min-width: 600px;
            }
            
            th, td {
                padding: 10px 8px;
                font-size: 0.9em;
            }
            
            .top-button-row {
                justify-content: center;
            }
            
            .search-form {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-input {
                min-width: auto;
                margin-bottom: 10px;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 20px;
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
                min-width: 550px;
            }
            
            .top-button-row {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-back,
            .btn-admin {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Manage Books</h1>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="top-button-row">
                <a href="dashboard.php" class="btn btn-back">Back to Dashboard</a>
                <a href="add_book.php" class="btn btn-admin">Add New Book</a>
            </div>
            
            <!-- Search Section -->
            <div class="search-container">
                <form method="GET" class="search-form">
                    <input type="text" 
                           name="search" 
                           class="search-input" 
                           placeholder="Search by title, author, or ID..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn-search">Search</button>
                    <?php if (!empty($search)): ?>
                        <a href="view_books.php" class="btn-clear">Clear</a>
                    <?php endif; ?>
                </form>
                
                <?php if (!empty($search)): ?>
                    <div class="search-results">
                        <?php 
                        $total_results = count($books);
                        echo "Found {$total_results} book(s) matching '{$search}'";
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Author</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($books)): ?>
                        <tr>
                            <td colspan="6" style="text-align: center; padding: 40px;">
                                <p style="margin: 0; color: #666; font-size: 16px;">
                                    <?php if (!empty($search)): ?>
                                        No books found matching "<?php echo htmlspecialchars($search); ?>". 
                                        <a href="view_books.php" style="color: #3498db;">View all books</a>
                                    <?php else: ?>
                                        No books found. <a href="add_book.php" style="color: #3498db;">Add your first book</a>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($books as $book): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($book['id']); ?></td>
                                <td><?php echo htmlspecialchars($book['title']); ?></td>
                                <td><?php echo htmlspecialchars($book['author']); ?></td>
                                <td>Rs <?php echo number_format((float)$book['price'], 2); ?></td>
                                <td><?php echo htmlspecialchars($book['stock']); ?></td>
                                <td>
                                    <a href="edit_book.php?id=<?php echo $book['id']; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" class="btn-small btn-edit">Edit</a>
                                    <button onclick="showDeleteModal(<?php echo $book['id']; ?>, '<?php echo addslashes(htmlspecialchars($book['title'])); ?>')" 
                                            class="btn-small btn-delete">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <h2>⚠️ Confirm Deletion</h2>
            <p id="deleteMessage">Are you sure you want to delete this book?</p>
            <div class="modal-buttons">
                <a id="confirmDeleteBtn" href="#" class="modal-btn modal-btn-delete">Yes, Delete</a>
                <button onclick="hideDeleteModal()" class="modal-btn modal-btn-cancel">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        let currentBookId = null;
        let currentBookTitle = '';

        function showDeleteModal(bookId, bookTitle) {
            currentBookId = bookId;
            currentBookTitle = bookTitle;
            
            // Update modal content
            document.getElementById('deleteMessage').innerHTML = 
                `Are you sure you want to delete the book:<br><strong>"${bookTitle}"</strong>?<br><br>This action cannot be undone.`;
            
            // Update delete button link with search parameter if exists
            const urlParams = new URLSearchParams(window.location.search);
            const search = urlParams.get('search');
            let deleteUrl = `delete_book.php?id=${bookId}`;
            if (search) {
                deleteUrl += `&search=${encodeURIComponent(search)}`;
            }
            
            document.getElementById('confirmDeleteBtn').href = deleteUrl;
            
            // Show modal
            document.getElementById('deleteModal').style.display = 'block';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            currentBookId = null;
            currentBookTitle = '';
        }

        // Close modal when clicking outside of it
        window.onclick = function(event) {
            const modal = document.getElementById('deleteModal');
            if (event.target === modal) {
                hideDeleteModal();
            }
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                hideDeleteModal();
            }
        });

        // Auto-focus search input when page loads
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.querySelector('.search-input');
            if (searchInput && !searchInput.value) {
                searchInput.focus();
            }
        });
    </script>
</body>
</html>