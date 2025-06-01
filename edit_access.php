<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Include database connection
require_once 'includes/config.php';

// Get admin info
$adminId = $_SESSION['user_id'];
$adminUsername = $_SESSION['username'];

// Redirect non-admin users to user dashboard
if ($adminUsername !== 'admin') {
    header("Location: user_dashboard.php");
    exit;
}

// Get user ID from URL parameter
$userId = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Check if user exists
if ($userId <= 0) {
    $_SESSION['error'] = "Invalid user ID.";
    header("Location: dashboard.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$userData = [];
$permissions = [];

// Define available permissions
$availablePermissions = [
    'view_products' => 'Access Products Tab',
    'view_sales' => 'Access Sales Tab',
    'view_transactions' => 'Access Transactions Tab',
    'add_products' => 'Add New Products',
    'edit_products' => 'Edit Products',
    'delete_products' => 'Delete Products',
    'add_transactions' => 'Add Transactions',
    'view_reports' => 'View Reports'
];

// Get user data
try {
    $stmt = $pdo->prepare("SELECT id, username, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$userData) {
        $_SESSION['error'] = "User not found.";
        header("Location: dashboard.php");
        exit;
    }
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
}

// Check if user_permissions table exists
try {
    $tableExists = $pdo->query("SHOW TABLES LIKE 'user_permissions'")->rowCount() > 0;
    
    if (!$tableExists) {
        // Create user_permissions table
        $pdo->exec("CREATE TABLE user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_name VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_permission (user_id, permission_name)
        )");
    }
} catch (PDOException $e) {
    $error = "Error checking permissions table: " . $e->getMessage();
}

// Get current user permissions
try {
    $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
    $stmt->execute([$userId]);
    $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Initialize permissions array with current values
    foreach ($availablePermissions as $permName => $permLabel) {
        $permissions[$permName] = in_array($permName, $userPermissions);
    }
    
    // Default permissions for new users
    if (empty($userPermissions)) {
        $permissions['view_products'] = true;
    }
} catch (PDOException $e) {
    $error = "Error loading permissions: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Delete all existing permissions for this user
        $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $stmt->execute([$userId]);
        
        // Insert new permissions
        $insertStmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_name) VALUES (?, ?)");
        
        foreach ($availablePermissions as $permName => $permLabel) {
            if (isset($_POST['permissions'][$permName])) {
                $insertStmt->execute([$userId, $permName]);
                $permissions[$permName] = true;
            } else {
                $permissions[$permName] = false;
            }
        }
        
        // Commit transaction
        $pdo->commit();
        
        $success = "Permissions updated successfully for " . htmlspecialchars($userData['username']) . ".";
    } catch (PDOException $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        $error = "Error updating permissions: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User Permissions - Palengke Plus 3.0</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
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
        
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .user-info {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-info p {
            margin: 5px 0;
        }
        
        .user-info strong {
            font-weight: 600;
            color: #333;
        }
        
        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .permission-item {
            padding: 10px;
            border-radius: 4px;
            background-color: #f9f9f9;
        }
        
        .permission-item label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }
        
        .permission-item input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            border: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #4a90e2;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .permission-group {
            margin-bottom: 20px;
        }
        
        .permission-group h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Edit User Permissions</h1>
            <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <div class="user-info">
                <h2>User: <?php echo htmlspecialchars($userData['username']); ?></h2>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($userData['email']); ?></p>
                <p><strong>ID:</strong> <?php echo htmlspecialchars($userData['id']); ?></p>
            </div>
            
            <form method="post" action="">
                <div class="permission-group">
                    <h3>Tab Access</h3>
                    <div class="permissions-grid">
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[view_products]" <?php echo $permissions['view_products'] ? 'checked' : ''; ?>>
                                Access Products Tab
                            </label>
                        </div>
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[view_sales]" <?php echo $permissions['view_sales'] ? 'checked' : ''; ?>>
                                Access Sales Tab
                            </label>
                        </div>
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[view_transactions]" <?php echo $permissions['view_transactions'] ? 'checked' : ''; ?>>
                                Access Transactions Tab
                            </label>
                        </div>

                    </div>
                </div>
                
                <div class="permission-group">
                    <h3>Product Permissions</h3>
                    <div class="permissions-grid">
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[add_products]" <?php echo $permissions['add_products'] ? 'checked' : ''; ?>>
                                Add New Products
                            </label>
                        </div>
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[edit_products]" <?php echo $permissions['edit_products'] ? 'checked' : ''; ?>>
                                Edit Products
                            </label>
                        </div>
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[delete_products]" <?php echo $permissions['delete_products'] ? 'checked' : ''; ?>>
                                Delete Products
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="permission-group">
                    <h3>Other Permissions</h3>
                    <div class="permissions-grid">
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[add_transactions]" <?php echo $permissions['add_transactions'] ? 'checked' : ''; ?>>
                                Add Transactions
                            </label>
                        </div>
                        <div class="permission-item">
                            <label>
                                <input type="checkbox" name="permissions[view_reports]" <?php echo $permissions['view_reports'] ? 'checked' : ''; ?>>
                                View Reports
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
