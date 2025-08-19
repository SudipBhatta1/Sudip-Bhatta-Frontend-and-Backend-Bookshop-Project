<?php
session_start();
include 'php/config/db.php';

// Check if customer is already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: php/customer/dashboard.php');
    exit();
}

$errors = [];
$success = '';

if ($_POST) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Server-side validation with specific messages
    if (empty($name)) {
        $errors['name'] = 'Please enter your full name';
    } elseif (strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters';
    }
    
    if (empty($email)) {
        $errors['email'] = 'Please enter an email';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($phone)) {
        $errors['phone'] = 'Please enter a phone number';
    } elseif (!preg_match('/^9\d{9}$/', $phone)) {
        $errors['phone'] = 'Phone must be 10 digits starting with 9';
    }
    
    if (empty($password)) {
        $errors['password'] = 'Please enter a password';
    } elseif (!preg_match('/^(?=.*[0-9])(?=.*[!@#$%^&*])[a-zA-Z0-9!@#$%^&*]{6,}$/', $password)) {
        $errors['password'] = 'Password must be 6+ characters with 1 number and 1 special character';
    }
    
    if (empty($confirm_password)) {
        $errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Check if email already exists (only if email is valid)
    if (empty($errors['email'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $errors['email'] = 'Email already exists';
            }
        } catch (PDOException $e) {
            error_log("Database error checking email: " . $e->getMessage());
            $errors['general'] = 'Registration system temporarily unavailable';
        }
    }
    
    // Check if phone already exists (only if phone is valid)
    if (empty($errors['phone'])) {
        try {
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE phone = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $errors['phone'] = 'Phone number already exists';
            }
        } catch (PDOException $e) {
            error_log("Database error checking phone: " . $e->getMessage());
            $errors['general'] = 'Registration system temporarily unavailable';
        }
    }
    
    // If no errors, insert customer
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, password) VALUES (?, ?, ?, ?)");
            
            if ($stmt->execute([$name, $email, $phone, $hashed_password])) {
                $success = 'Registration successful! You can now login.';
                $_POST = array(); // Clear form data
            } else {
                $errors['general'] = 'Registration failed. Please try again';
            }
        } catch (PDOException $e) {
            error_log("Database error during registration: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again later';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Register - Bookshop</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .container {
            max-width: 500px;
            margin: 20px auto;
            padding: 0 15px;
        }
        
        .login-box {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            padding: 25px;
        }
        
        .login-box h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            font-size: 24px;
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
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .password-container {
            position: relative;
            display: flex;
            align-items: center;
        }
        .password-container input {
            flex: 1;
            padding-right: 35px;
        }
        .password-toggle {
            position: absolute;
            right: 8px;
            background: none;
            border: none;
            cursor: pointer;
            color: #666;
            font-size: 12px;
            padding: 2px 4px;
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
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
            text-align: center;
            font-size: 14px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 6px 10px;
            border-radius: 4px;
            margin-top: 3px;
            font-size: 12px;
            line-height: 1.3;
        }
        
        .error.general {
            margin-bottom: 15px;
            text-align: center;
            margin-top: 0;
            font-size: 14px;
            padding: 10px;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            margin-right: 8px;
            margin-bottom: 8px;
            width: 100%;
            text-align: center;
            box-sizing: border-box;
        }
        
        .btn-customer {
            background: #2980b9;
            color: white;
        }
        
        .btn-customer:hover {
            background: #3498db;
        }
        
        .navigation-links {
            margin-top: 15px;
            text-align: center;
            font-size: 14px;
        }
        
        .navigation-links p {
            margin: 8px 0;
        }
        
        .navigation-links a {
            color: #3498db;
            text-decoration: none;
        }
        
        .navigation-links a:hover {
            text-decoration: underline;
        }
        
        /* Responsive adjustments */
        @media (max-width: 600px) {
            .container {
                margin: 10px auto;
            }
            .login-box {
                padding: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="login-box">
            <h2>📝 Customer Registration</h2>
            
            <?php if ($success): ?>
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
                <p><a href="customer_login.php">Click here to login</a></p>
            <?php else: ?>
                <?php if (isset($errors['general'])): ?>
                    <div class="error general"><?php echo htmlspecialchars($errors['general']); ?></div>
                <?php endif; ?>
                
                <form method="POST" novalidate>
                    <div class="form-group">
                        <label for="name">Full Name:</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" 
                               placeholder="Enter your full name" required>
                        <?php if (isset($errors['name'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['name']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email:</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                               placeholder="Enter your email" required>
                        <?php if (isset($errors['email'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['email']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number:</label>
                        <input type="text" id="phone" name="phone" 
                               value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" 
                               placeholder="Enter 10-digit phone number" required>
                        <?php if (isset($errors['phone'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['phone']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <div class="password-container">
                            <input type="password" id="password" name="password" 
                                   placeholder="Enter password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('password')" title="Show/Hide Password">👁️</button>
                        </div>
                        <?php if (isset($errors['password'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password:</label>
                        <div class="password-container">
                            <input type="password" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm your password" required>
                            <button type="button" class="password-toggle" onclick="togglePassword('confirm_password')" title="Show/Hide Password">👁️</button>
                        </div>
                        <?php if (isset($errors['confirm_password'])): ?>
                            <div class="error"><?php echo htmlspecialchars($errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <button type="submit" class="btn btn-customer">Register</button>
                </form>
            <?php endif; ?>
            
            <div class="navigation-links">
                <p>Already have an account? <a href="customer_login.php">Login here</a></p>
                <p><a href="index.html">← Back to Home</a></p>
            </div>
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
    </script>
</body>
</html>