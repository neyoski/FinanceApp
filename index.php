<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? 'admin/dashboard.php' : 'resident/dashboard.php'));
    exit;
}

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    $conn = $db->getConnection();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $full_name = $_POST['full_name'] ?? '';
    $email = $_POST['email'] ?? '';
    $role = $_POST['role'] ?? 'resident';
    
    if (empty($username) || empty($password) || empty($confirm_password) || empty($full_name) || empty($email)) {
        $message = 'Error: All fields are required.';
    } elseif ($password !== $confirm_password) {
        $message = 'Error: Passwords do not match.';
    } else {
        // Check if username or email already exists
        $stmt = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        if ($stmt->rowCount() > 0) {
            $message = 'Error: Username or email already exists.';
        } else {
            // Create new user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                INSERT INTO users (username, password, full_name, email, role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            
            if ($stmt->execute([$username, $hashed_password, $full_name, $email, $role])) {
                $message = 'Registration successful! You can now login.';
                header("refresh:2;url=login.php");
            } else {
                $message = 'Error: Registration failed.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estate Finance Management - Registration</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>
    <div class="container">
        <header>
            <h1>Welcome to Estate Finance Management</h1>
            <p>Register to manage your estate financial obligations efficiently</p>
        </header>
        
        <main>
            <?php if ($message): ?>
                <div class="alert <?php echo strpos($message, 'Error:') === 0 ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="auth-container">
                <div class="auth-box">
                    <h2>Register</h2>
                    <form method="POST" action="" class="registration-form">
                        <div class="form-group">
                            <label for="full_name">Full Name</label>
                            <input type="text" id="full_name" name="full_name" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" name="email" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="username">Username</label>
                            <input type="text" id="username" name="username" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password</label>
                            <input type="password" id="password" name="password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="confirm_password">Confirm Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="role">Register as</label>
                            <select id="role" name="role" required>
                                <option value="resident">Resident</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Register</button>
                    </form>
                    <p class="auth-links">
                        Already have an account? <a href="login.php">Login here</a>
                    </p>
                </div>
            </div>
        </main>
    </div>
</body>
</html>