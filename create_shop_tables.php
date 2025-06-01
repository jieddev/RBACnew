<?php
// Include database connection
require_once 'includes/config.php';

try {
    // Create sales_header table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales_header (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        subtotal DECIMAL(10,2) NOT NULL,
        tax DECIMAL(10,2) NOT NULL,
        total DECIMAL(10,2) NOT NULL,
        payment_method VARCHAR(50) NOT NULL,
        payment_amount DECIMAL(10,2) NOT NULL,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    echo "sales_header table created successfully.<br>";
    
    // Check if the sales table exists and has the required structure
    $salesTableExists = false;
    try {
        $stmt = $pdo->query("SHOW TABLES LIKE 'sales'");
        $salesTableExists = $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Table doesn't exist
    }
    
    // If sales table doesn't exist or needs to be modified
    if (!$salesTableExists) {
        // Create sales table with the correct structure for order items
        $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sale_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            total_price DECIMAL(10,2) NOT NULL,
            sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (sale_id) REFERENCES sales_header(id) ON DELETE CASCADE
        )");
        
        echo "sales table created successfully.<br>";
    } else {
        // Check if the sales table has the sale_id column
        $stmt = $pdo->query("SHOW COLUMNS FROM sales LIKE 'sale_id'");
        $saleIdExists = $stmt->rowCount() > 0;
        
        if (!$saleIdExists) {
            // Add the sale_id column to the existing sales table
            $pdo->exec("ALTER TABLE sales ADD COLUMN sale_id INT NOT NULL AFTER id");
            echo "Added sale_id column to existing sales table.<br>";
        }
    }
    
    // Fix the stock column name in process_order.php
    $processOrderContent = file_get_contents('process_order.php');
    $processOrderContent = str_replace(
        "UPDATE products SET stock = stock - :quantity WHERE id = :product_id",
        "UPDATE products SET stock_quantity = stock_quantity - :quantity WHERE id = :product_id",
        $processOrderContent
    );
    file_put_contents('process_order.php', $processOrderContent);
    
    echo "Updated process_order.php to use correct column name.<br>";
    
    echo "<p>All tables for the shop functionality have been created successfully.</p>";
    echo "<p><a href='user_dashboard.php?tab=shop'>Return to Shop</a></p>";
    
} catch (PDOException $e) {
    echo "Error creating tables: " . $e->getMessage();
}
?>
