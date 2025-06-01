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

// Check if user has permission to delete users
if ($_SESSION['username'] !== 'admin' && !hasPermission($_SESSION['user_id'], 'delete_users')) {
    // Redirect to dashboard with error message
    $_SESSION['error'] = "You don't have permission to delete users.";
    header("Location: dashboard.php");
    exit;
}

// Check if user ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Redirect to dashboard with error message
    $_SESSION['error'] = "No user specified for deletion.";
    header("Location: dashboard.php");
    exit;
}

$user_id = (int)$_GET['id'];

// Prevent deletion of admin user
$stmt = $pdo->prepare("SELECT username FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if ($user && $user['username'] === 'admin') {
    // Redirect to dashboard with error message
    $_SESSION['error'] = "The admin user cannot be deleted.";
    header("Location: dashboard.php");
    exit;
}

// Process deletion
try {
    // Delete user
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Set success message
        $_SESSION['success'] = "User deleted successfully.";
    } else {
        // Set error message
        $_SESSION['error'] = "Failed to delete user.";
    }
} catch (PDOException $e) {
    // Set error message
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to dashboard
header("Location: dashboard.php");
exit;
?>
