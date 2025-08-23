<?php
session_start();
include 'php/config/db.php';

// Check if admin is already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: php/admin/dashboard.php');
    exit();
}
$errors = []; // Changed from $error to $errors array


if ($_POST) {
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Specific validation for each field
    if (empty($email)) {
        $errors['email'] = 'Please enter an email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Please enter a password';
    }
    
    // Only check credentials if both fields are filled
    if (empty($errors)) {
        try {
            // Check admin credentials from database (SIMPLE - NO HASHING)
            $stmt = $pdo->prepare("SELECT id, email, password, name FROM admins WHERE email = ? AND password = ? AND is_active = 1");
            $stmt->execute([$email, $password]);
            $admin = $stmt->fetch();
            
            if ($admin) {
                // Login successful
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_name'] = $admin['name'];
                
                // Update last login time
                $update_stmt = $pdo->prepare("UPDATE admins SET last_login = CURRENT_TIMESTAMP WHERE id = ?");
                $update_stmt->execute([$admin['id']]);
                
                header('Location: php/admin/dashboard.php');
                exit();
            } else {
                $errors['general'] = 'Invalid admin credentials';
            }
        } catch (PDOException $e) {
            error_log("Database error during admin login: " . $e->getMessage());
            $errors['general'] = 'Login system temporarily unavailable. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Bookshop</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-container input {
            flex: 1;
            padding-right: 40px;
        }
        .password-toggle {
            position: absolute;
            right: 10px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            padding: 2px 6px;
            border-radius: 3px;
            transition: background-color 0.2s;
        }
        .password-toggle:hover {
            background-color: #f0f0f0;
            color: #333;
        }
        .password-toggle:focus {
            outline: 2px solid #3498db;
            outline-offset: 1px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h2>🔐 Admin Login</h2>
            
            <?php if (isset($errors['general'])): ?>
                <div class="error"><?php echo htmlspecialchars($errors['general']); ?></div>
            <?php endif; ?>
            
            <form method="POST" novalidate>
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                           autocomplete="username">
                    <?php if (isset($errors['email'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['email']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password:</label>
                    <div class="password-container">
                        <input type="password" id="password" name="password" required autocomplete="current-password">
                        <button type="button" class="password-toggle" onclick="togglePassword('password')" title="Show/Hide Password">👁️</button>
                    </div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="error"><?php echo htmlspecialchars($errors['password']); ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-admin">Login</button>
            </form>
            
            <p style="margin-top: 20px;">
                <a href="index.html">← Back to Home</a>
            </p>
        </div>
    </div>


    <script>
        function togglePassword(fieldId) {
            const passwordField = document.getElementById(fieldId);
            const toggleButton = passwordField.parentNode.querySelector('.password-toggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleButton.textContent = '🙈';
                toggleButton.title = 'Hide Password';
            } else {
                passwordField.type = 'password';
                toggleButton.textContent = '👁️';
                toggleButton.title = 'Show Password';
            }
        }

        // Clear password field if there was an error
        <?php if (!empty($errors)): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('password').value = '';
        });
        <?php endif; ?>
    </script>
</body>
</html>

