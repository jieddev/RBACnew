<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

// Include database connection
require_once 'includes/config.php';

// Get JSON data from request
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Check if data is valid
if (!$data || !isset($data['items']) || empty($data['items'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid order data']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert sale record
    $stmt = $pdo->prepare("INSERT INTO sales_header (user_id, subtotal, tax, total, payment_method, payment_amount, sale_date) 
                          VALUES (:user_id, :subtotal, :tax, :total, :payment_method, :payment_amount, NOW())");
    
    $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $stmt->bindParam(':subtotal', $data['subtotal'], PDO::PARAM_STR);
    $stmt->bindParam(':tax', $data['tax'], PDO::PARAM_STR);
    $stmt->bindParam(':total', $data['total'], PDO::PARAM_STR);
    $stmt->bindParam(':payment_method', $data['payment_method'], PDO::PARAM_STR);
    $stmt->bindParam(':payment_amount', $data['payment_amount'], PDO::PARAM_STR);
    
    $stmt->execute();
    
    // Get the sale ID
    $saleId = $pdo->lastInsertId();
    
    // Insert sale items
    $insertItemStmt = $pdo->prepare("INSERT INTO sales (sale_id, product_id, quantity, price_per_unit, total_price, sale_date) 
                                    VALUES (:sale_id, :product_id, :quantity, :price, :total_price, NOW())");
    
    // Update product stock
    $updateStockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id");
    
    foreach ($data['items'] as $item) {
        // Insert sale item
        $insertItemStmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $insertItemStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $insertItemStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $insertItemStmt->bindParam(':price', $item['price'], PDO::PARAM_STR);
        $insertItemStmt->bindParam(':total_price', $item['total'], PDO::PARAM_STR);
        $insertItemStmt->execute();
        
        // Update product stock
        $updateStockStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $updateStockStmt->bindParam(':product_id', $item['product_id'], PDO::PARAM_INT);
        $updateStockStmt->execute();
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'sale_id' => $saleId]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
