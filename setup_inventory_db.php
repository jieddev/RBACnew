<?php
// Include database connection
require_once 'includes/config.php';

try {
    // Create products table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        price DECIMAL(10,2) NOT NULL,
        stock_quantity INT NOT NULL DEFAULT 0,
        category VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )");
    
    echo "Products table created successfully.<br>";
    
    // Create sales table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        quantity INT NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_id INT NOT NULL,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "Sales table created successfully.<br>";
    
    // Create transactions table if it doesn't exist
    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        transaction_type ENUM('purchase', 'sale', 'adjustment') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        description TEXT,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        user_id INT NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    echo "Transactions table created successfully.<br>";
    
    // Insert sample products if the products table is empty
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $productCount = $stmt->fetchColumn();
    
    if ($productCount == 0) {
        // Sample products
        $sampleProducts = [
            ['Rice', '5kg premium white rice', 250.00, 100, 'Grains'],
            ['Cooking Oil', '1L vegetable cooking oil', 120.00, 50, 'Oils'],
            ['Sugar', '1kg refined white sugar', 65.00, 80, 'Baking'],
            ['Eggs', 'Tray of 30 fresh eggs', 180.00, 40, 'Dairy'],
            ['Milk', '1L fresh milk', 90.00, 30, 'Dairy'],
            ['Chicken', 'Whole chicken, approximately 1.5kg', 180.00, 25, 'Meat'],
            ['Tomatoes', '1kg fresh tomatoes', 60.00, 45, 'Vegetables'],
            ['Onions', '1kg red onions', 80.00, 60, 'Vegetables'],
            ['Garlic', '250g garlic', 40.00, 70, 'Vegetables'],
            ['Bread', 'Loaf of white bread', 50.00, 35, 'Bakery']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($sampleProducts as $product) {
            $stmt->execute($product);
        }
        
        echo "Sample products inserted successfully.<br>";
    }
    
    echo "Inventory database setup complete!";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
