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

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID";
    header("Location: user_dashboard.php?tab=products");
    exit;
}

$productId = intval($_GET['id']);
$userId = $_SESSION['user_id'];

try {
    // Get product details before deletion (for transaction record)
    $stmt = $pdo->prepare("SELECT name, price FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header("Location: user_dashboard.php?tab=products");
        exit;
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Delete the product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    
    if ($stmt->execute()) {
        // Record transaction for the deletion
        $transactionDescription = "Removed product: " . $product['name'];
        
        $stmt = $pdo->prepare("INSERT INTO transactions (transaction_type, amount, description, user_id) 
                              VALUES ('adjustment', :amount, :description, :user_id)");
        $stmt->bindParam(':amount', $product['price']);
        $stmt->bindParam(':description', $transactionDescription, PDO::PARAM_STR);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        
        // Commit transaction
        $pdo->commit();
        
        $_SESSION['success'] = "Product deleted successfully";
    } else {
        // Rollback transaction
        $pdo->rollBack();
        $_SESSION['error'] = "Failed to delete product";
    }
} catch (PDOException $e) {
    // Rollback transaction
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "Database error: " . $e->getMessage();
}

// Redirect back to products page
header("Location: user_dashboard.php?tab=products");
exit;
?>
