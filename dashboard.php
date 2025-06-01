<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Include required files
require_once 'includes/config.php';
require_once 'includes/functions.php';

// Get user info
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Redirect regular users to user dashboard
if ($username !== 'admin') {
    header("Location: user_dashboard.php");
    exit;
}

// Get user's name if not using the hardcoded admin account
if ($userId != 1 || $username != 'admin') {
    try {
        $stmt = $pdo->prepare("SELECT full_name FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        $userData = $stmt->fetch();
        $fullName = $userData['full_name'];
    } catch (PDOException $e) {
        $fullName = $username;
    }
} else {
    // Default value for hardcoded admin
    $fullName = 'System Administrator';
}

// Get role name
try {
    $stmt = $pdo->prepare("SELECT name FROM roles WHERE id = :role_id");
    $stmt->bindParam(':role_id', $role, PDO::PARAM_INT);
    $stmt->execute();
    $roleName = $stmt->fetchColumn();
} catch (PDOException $e) {
    $roleName = "Unknown";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - RBAC System</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Additional dashboard styles */
        .dashboard {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
        }
        
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-info {
            margin-bottom: 20px;
        }
        
        .user-info p {
            margin-bottom: 5px;
            color: #555;
        }
        
        .user-info strong {
            color: #333;
        }
        
        .logout-btn {
            background-color: #f44336;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 15px;
            cursor: pointer;
            transition: background-color 0.3s;
            text-decoration: none;
            display: inline-block;
        }
        
        .logout-btn:hover {
            background-color: #d32f2f;
        }
        
        .feature-section {
            margin-top: 20px;
        }
        
        .feature-section h3 {
            margin-bottom: 15px;
            color: #333;
        }
        
        .feature-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .feature-item {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            text-align: center;
        }
        
        .feature-item a {
            color: #4a90e2;
            text-decoration: none;
            display: block;
            padding: 10px;
        }
        
        .feature-item a:hover {
            background-color: #eee;
            border-radius: 4px;
        }
        
        /* User list styles */
        .user-list-section {
            margin-top: 30px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            padding: 20px;
        }
        
        .user-list-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        
        .user-list-table th,
        .user-list-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .user-list-table th {
            background-color: #f5f5f5;
            font-weight: 600;
            color: #333;
        }
        
        .user-list-table tr:hover {
            background-color: #f9f9f9;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn-edit, .btn-delete, .btn-access {
            padding: 5px 10px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            margin-right: 5px;
        }
        
        .btn-edit {
            background-color: #4a90e2;
            color: white;
        }
        
        .btn-access {
            background-color: #27ae60;
            color: white;
        }
        
        .btn-delete {
            background-color: #e74c3c;
            color: white;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="dashboard">
            <div class="dashboard-header">
                <h2>Admin</h2>
                <a href="logout.php" class="logout-btn">Logout</a>
            </div>
            
            <div class="content">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <?php 
                        echo htmlspecialchars($_SESSION['success']); 
                        unset($_SESSION['success']); // Clear the message after displaying
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="error-message">
                    <?php 
                        echo htmlspecialchars($_SESSION['error']); 
                        unset($_SESSION['error']); // Clear the message after displaying
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="welcome-section">
                <h2>Palengke Plus 3.0 Admin Page</h2>
                <p>You are logged in as: <strong><?php echo htmlspecialchars($username); ?></strong></p>
            
            <?php if ($username === 'admin' || hasPermission($userId, 'view_users')): ?>
            <div class="user-list-section">
                <h3>User Management</h3>
                <p>Below is a list of all registered users in the system:</p>
                
                <table class="user-list-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            // Get all users from the database - using only basic columns
                            $stmt = $pdo->query("SELECT id, username, email 
                                                FROM users 
                                                ORDER BY id ASC");
                            
                            while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($user['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                                echo "<td>" . htmlspecialchars($user['email']) . "</td>";
                                echo "<td>" . htmlspecialchars($user['username']) . "</td>";
                                echo "<td class='user-actions'>";
                                echo "<a href='edit_user.php?id=" . $user['id'] . "' class='btn-edit'>Edit</a>";
                                echo "<a href='edit_access.php?id=" . $user['id'] . "' class='btn-access'>Edit Permission</a>";
                                if ($user['username'] !== 'admin') { // Prevent deleting admin
                                    echo "<a href='delete_user.php?id=" . $user['id'] . "' class='btn-delete' onclick='return confirm(\"Are you sure you want to delete this user?\");'>Delete</a>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='7'>Error loading users: " . $e->getMessage() . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
