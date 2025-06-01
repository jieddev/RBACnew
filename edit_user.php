<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Include database connection and functions
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Check if user has permission to edit users
if ($_SESSION['username'] !== 'admin' && !hasPermission($_SESSION['user_id'], 'edit_users')) {
    // Redirect to dashboard with error message
    $_SESSION['error'] = "You don't have permission to edit users.";
    header("Location: dashboard.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to dashboard with error message
    $_SESSION['error'] = "No user specified for editing.";
    header("Location: dashboard.php");
    exit;
}

$user_id = (int)$_GET['id'];
$error = '';
$success = '';
$user = null;

// Get user data
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // Redirect to dashboard with error message
        $_SESSION['error'] = "User not found.";
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $username = sanitizeInput($_POST['username']);
    $email = sanitizeInput($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($username) || empty($email)) {
        $error = "Username and email are required fields.";
    } else {
        try {
            // Check if username already exists (for a different user)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = "Username already exists.";
            } else {
                // Check if email already exists (for a different user)
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $error = "Email already exists.";
                } else {
                    // Update user
                    if (!empty($password)) {
                        // Update with new password
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email, password = :password WHERE id = :user_id");
                        $stmt->bindParam(':password', $hashed_password, PDO::PARAM_STR);
                    } else {
                        // Update without changing password
                        $stmt = $pdo->prepare("UPDATE users SET username = :username, email = :email WHERE id = :user_id");
                    }
                    
                    $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                    $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                    
                    if ($stmt->execute()) {
                        $success = "User updated successfully.";
                        
                        // Refresh user data
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
                        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
                        $stmt->execute();
                        $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = "Failed to update user.";
                    }
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Palengke Plus 3.0</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .header h1 {
            margin: 0;
            color: #333;
        }
        
        .btn-back {
            background-color: #6c757d;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #c3e6cb;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
            border: 1px solid #f5c6cb;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            font-size: 12px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .btn-save {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        
        .btn-cancel {
            background-color: #6c757d;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            text-decoration: none;
            font-size: 16px;
        }
        
        .password-note {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit User</h1>
            <a href="dashboard.php" class="btn-back">Back to Dashboard</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $user_id); ?>" method="post">
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($user['username']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password">
                <p class="password-note">Leave blank to keep current password</p>
            </div>
            
            <div class="form-actions">
                <a href="dashboard.php" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save">Save Changes</button>
            </div>
        </form>
    </div>
</body>
</html>
