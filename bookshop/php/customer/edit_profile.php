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
$errors = [];

// Get current customer data
$stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if ($_POST) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $current_password = trim($_POST['current_password']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate current password
    if (!password_verify($current_password, $customer['password'])) {
        $errors['current_password'] = 'Current password is incorrect';
    }
    
    // Server-side validation
    if (strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters';
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address';
    }
    
    if (!preg_match('/^9\d{9}$/', $phone)) {
        $errors['phone'] = 'Phone must be 10 digits starting with 9';
    }
    
    // Check if email already exists (except current user)
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND id != ?");
        $stmt->execute([$email, $customer_id]);
        if ($stmt->fetch()) {
            $errors['email'] = 'Email already exists';
        }
    }
    
    // Password validation (if changing password)
    if (!empty($new_password)) {
        if (!preg_match('/^(?=.*[0-9])(?=.*[!@#$%^&*])[a-zA-Z0-9!@#$%^&*]{6,}$/', $new_password)) {
            $errors['new_password'] = 'Password must be 6+ characters with 1 number and 1 special character';
        }
        
        if ($new_password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        if (!empty($new_password)) {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $hashed_password, $customer_id]);
        } else {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, email = ?, phone = ? WHERE id = ?");
            $stmt->execute([$name, $email, $phone, $customer_id]);
        }
        
        // Update session data
        $_SESSION['customer_name'] = $name;
        $_SESSION['customer_email'] = $email;
        
        $success = 'Profile updated successfully!';
        
        // Refresh customer data
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$customer_id]);
        $customer = $stmt->fetch();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Customer</title>
    <link rel="stylesheet" href="../../css/styles.css">
    <script src="../../js/scripts.js"></script>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <h1>Edit Profile</h1>
            
            <?php if ($success): ?>
                <div class="success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label for="name">Full Name:</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required>
                    <?php if (isset($errors['name'])): ?>
                        <div class="error"><?php echo $errors['name']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($customer['email']); ?>" required>
                    <?php if (isset($errors['email'])): ?>
                        <div class="error"><?php echo $errors['email']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number:</label>
                    <input type="text" id="phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="error"><?php echo $errors['phone']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="current_password">Current Password:</label>
                    <input type="password" id="current_password" name="current_password" required>
                    <?php if (isset($errors['current_password'])): ?>
                        <div class="error"><?php echo $errors['current_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password (leave blank to keep current):</label>
                    <input type="password" id="new_password" name="new_password">
                    <?php if (isset($errors['new_password'])): ?>
                        <div class="error"><?php echo $errors['new_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password">
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="error"><?php echo $errors['confirm_password']; ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn btn-customer">Update Profile</button>
                <a href="dashboard.php" class="btn" style="background: #95a5a6; color: white; margin-left: 10px;">Cancel</a>
            </form>
        </div>
    </div>
</body>
</html>