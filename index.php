<?php
// Start sessiqu
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard or home page
    header("Location: dashboard.php");
    exit;
}

// Include database connection
require_once 'includes/config.php';
require_once 'includes/functions.php';

$error = '';

// Process login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get username and password from form
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Attempt to authenticate user
        $result = authenticateUser($username, $password);
        
        if ($result['success']) {
            // Set session variables
            $_SESSION['user_id'] = $result['user_id'];
            $_SESSION['username'] = $username;
            $_SESSION['role'] = $result['role'];
            
            // Redirect to dashboard
            header("Location: dashboard.php");
            exit;
        } else {
            $error = "Invalid username or password";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Palengke Plus 3.0</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <div class="login-form">
            <h2>Palengke Plus 3.0 Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="error-message"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="username" style="font-size: 12px;">Username</label>
                    <input type="text" id="username" name="username" required>
                </div>
                
                <div class="form-group">
                    <label for="password" style="font-size: 12px;">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn-login">Login</button>
                </div>
                
                <div class="form-footer">
                    <p>Don't have an account?</p>
                    <a href="register_simple.php" class="btn-register">Create Account</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
