<?php
session_start();
include 'includes/config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'You must be logged in to process sales']);
    exit;
}

// Get the raw POST data and decode it
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Validate the data
if (!$data || !isset($data['items']) || empty($data['items'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid sale data']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Generate a unique sale reference number for grouping related sales
    $saleReference = 'SALE-' . date('YmdHis') . '-' . $_SESSION['user_id'];
    $firstSaleId = null;
    
    // Update product stock and record transactions
    $updateStockStmt = $pdo->prepare("UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?");
    
    // Check if transactions table exists and get column names
    $transactionTypeColumn = 'type'; // Default column name
    $transactionProductIdColumn = 'product_id'; // Default column name
    
    try {
        $transactionColumns = $pdo->query("SHOW COLUMNS FROM transactions")->fetchAll(PDO::FETCH_COLUMN);
        
        if (in_array('transaction_type', $transactionColumns)) {
            $transactionTypeColumn = 'transaction_type';
        }
        
        if (in_array('product_id', $transactionColumns)) {
            $transactionProductIdColumn = 'product_id';
        } else if (in_array('productid', $transactionColumns)) {
            $transactionProductIdColumn = 'productid';
        }
    } catch (PDOException $e) {
        // If transactions table doesn't exist, create it
        $pdo->exec("CREATE TABLE transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            amount INT NOT NULL,
            description TEXT,
            product_id INT,
            transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    }
    
    // Prepare transaction insert statement with dynamic column names
    $transactionSql = "INSERT INTO transactions (";
    $transactionSql .= "$transactionTypeColumn, amount, description, $transactionProductIdColumn, transaction_date";
    $transactionSql .= ") VALUES (?, ?, ?, ?, NOW())";
    $insertTransactionStmt = $pdo->prepare($transactionSql);
    
    // Check if sales table exists and get column names
    $salesColumns = [];
    try {
        $columnQuery = $pdo->query("SHOW COLUMNS FROM sales");
        while ($column = $columnQuery->fetch(PDO::FETCH_ASSOC)) {
            $salesColumns[] = $column['Field'];
        }
    } catch (PDOException $e) {
        // If sales table doesn't exist, create it with the correct structure
        $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price_per_unit DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $salesColumns = ['id', 'product_id', 'quantity', 'price_per_unit', 'total_price', 'sale_date'];
    }
    
    // Process each item
    foreach ($data['items'] as $item) {
        // Insert into sales table (one row per product)
        $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, price_per_unit, total_price, sale_date) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['total']
        ]);
        
        // Store the first sale ID for receipt reference
        $currentSaleId = $pdo->lastInsertId();
        if ($firstSaleId === null) {
            $firstSaleId = $currentSaleId;
        }
        
        // Update product stock
        $updateStockStmt->execute([$item['quantity'], $item['product_id']]);
        
        // Get product name for transaction description
        $productStmt = $pdo->prepare("SELECT name FROM products WHERE id = ?");
        $productStmt->execute([$item['product_id']]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);
        $productName = $product ? $product['name'] : "Product #" . $item['product_id'];
        
        // Record transaction
        $description = "Sold " . $item['quantity'] . " units of " . $productName . " (Sale #" . $currentSaleId . ")";
        $insertTransactionStmt->execute(['sale', $item['quantity'], $description, $item['product_id']]);
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response with the first sale ID (for receipt reference)
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true, 
        'sale_id' => $firstSaleId
    ]);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    $pdo->rollBack();
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>
