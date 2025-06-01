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

// Initialize variables
$error = '';
$success = '';
$products = [];

// Get all products for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, price, stock_quantity FROM products WHERE stock_quantity > 0 ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading products: " . $e->getMessage();
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
    $userId = $_SESSION['user_id'];
    
    // Validate input
    if ($productId <= 0) {
        $error = "Please select a valid product";
    } elseif ($quantity <= 0) {
        $error = "Quantity must be greater than zero";
    } else {
        try {
            // Get product details
            $stmt = $pdo->prepare("SELECT name, price, stock_quantity FROM products WHERE id = :id");
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                $error = "Selected product not found";
            } elseif ($product['stock_quantity'] < $quantity) {
                $error = "Not enough stock available. Only " . $product['stock_quantity'] . " units in stock.";
            } else {
                // Calculate total price
                $totalPrice = $product['price'] * $quantity;
                
                // Start transaction
                $pdo->beginTransaction();
                
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
                
                // Insert sale record
                $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, price_per_unit, total_price, sale_date) 
                                      VALUES (:product_id, :quantity, :price, :total_price, NOW())");
                $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
                $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
                $stmt->bindParam(':price', $product['price']);
                $stmt->bindParam(':total_price', $totalPrice);
                $stmt->execute();
                
                // Update product stock
                $newStock = $product['stock_quantity'] - $quantity;
                $stmt = $pdo->prepare("UPDATE products SET stock_quantity = :stock WHERE id = :id");
                $stmt->bindParam(':stock', $newStock, PDO::PARAM_INT);
                $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
                $stmt->execute();
                
                // Check if transactions table exists and get column names
                $transactionColumns = [];
                try {
                    $columnQuery = $pdo->query("SHOW COLUMNS FROM transactions");
                    while ($column = $columnQuery->fetch(PDO::FETCH_ASSOC)) {
                        $transactionColumns[] = $column['Field'];
                    }
                } catch (PDOException $e) {
                    // If transactions table doesn't exist, create it
                    $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        type VARCHAR(50) NOT NULL,
                        amount INT NOT NULL,
                        description TEXT,
                        product_id INT,
                        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                    $transactionColumns = ['id', 'type', 'amount', 'description', 'product_id', 'transaction_date'];
                }
                
                // Determine the transaction type column name
                $typeColumn = in_array('transaction_type', $transactionColumns) ? 'transaction_type' : 
                             (in_array('type', $transactionColumns) ? 'type' : 'type');
                
                // Determine the product ID column name
                $productIdColumn = in_array('product_id', $transactionColumns) ? 'product_id' : 
                                 (in_array('productID', $transactionColumns) ? 'productID' : 
                                 (in_array('product', $transactionColumns) ? 'product' : 'product_id'));
                
                // Record transaction
                $transactionDescription = "Sale of $quantity x " . $product['name'];
                
                // Build dynamic SQL based on actual table structure
                $transactionSql = "INSERT INTO transactions ($typeColumn, amount, description, $productIdColumn) 
                                  VALUES ('sale', :amount, :description, :product_id)";
                $stmt = $pdo->prepare($transactionSql);
                $stmt->bindParam(':amount', $totalPrice);
                $stmt->bindParam(':description', $transactionDescription, PDO::PARAM_STR);
                $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
                $stmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                // Set success message and redirect
                $_SESSION['success'] = "Sale recorded successfully!";
                header("Location: user_dashboard.php?tab=sales");
                exit;
            }
        } catch (PDOException $e) {
            // Rollback transaction
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
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
    <title>Record Sale - Palengke Plus 3.0</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 600px;
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
        
        .form-group select, .form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .product-info {
            margin-top: 10px;
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            display: none;
        }
        
        .product-info p {
            margin: 5px 0;
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
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Record New Sale</h1>
            <a href="user_dashboard.php?tab=sales" class="btn-back">Back to Sales</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (empty($products)): ?>
            <div class="error-message">No products available for sale. Please add products first.</div>
        <?php else: ?>
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
                <div class="form-group">
                    <label for="product_id">Select Product</label>
                    <select id="product_id" name="product_id" required>
                        <option value="">-- Select a product --</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" 
                                    data-price="<?php echo $product['price']; ?>"
                                    data-stock="<?php echo $product['stock_quantity']; ?>">
                                <?php echo htmlspecialchars($product['name']); ?> - ₱<?php echo number_format($product['price'], 2); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div id="product-info" class="product-info">
                    <p>Price: <span id="product-price">₱0.00</span></p>
                    <p>Available Stock: <span id="product-stock">0</span></p>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" id="quantity" name="quantity" min="1" required>
                </div>
                
                <div class="form-group">
                    <label for="total-price">Total Price</label>
                    <input type="text" id="total-price" value="₱0.00" readonly>
                </div>
                
                <div class="form-actions">
                    <a href="user_dashboard.php?tab=sales" class="btn-cancel">Cancel</a>
                    <button type="submit" class="btn-save">Record Sale</button>
                </div>
            </form>
        <?php endif; ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productSelect = document.getElementById('product_id');
            const quantityInput = document.getElementById('quantity');
            const productInfo = document.getElementById('product-info');
            const productPrice = document.getElementById('product-price');
            const productStock = document.getElementById('product-stock');
            const totalPrice = document.getElementById('total-price');
            
            // Show product info when a product is selected
            productSelect.addEventListener('change', function() {
                if (this.value) {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = parseFloat(selectedOption.dataset.price);
                    const stock = parseInt(selectedOption.dataset.stock);
                    
                    productPrice.textContent = '₱' + price.toFixed(2);
                    productStock.textContent = stock;
                    productInfo.style.display = 'block';
                    
                    // Update max quantity based on stock
                    quantityInput.max = stock;
                    
                    // Reset quantity
                    quantityInput.value = '';
                    totalPrice.value = '₱0.00';
                } else {
                    productInfo.style.display = 'none';
                }
            });
            
            // Calculate total price when quantity changes
            quantityInput.addEventListener('input', function() {
                if (productSelect.value && this.value) {
                    const selectedOption = productSelect.options[productSelect.selectedIndex];
                    const price = parseFloat(selectedOption.dataset.price);
                    const quantity = parseInt(this.value);
                    
                    if (quantity > 0) {
                        const total = price * quantity;
                        totalPrice.value = '₱' + total.toFixed(2);
                    } else {
                        totalPrice.value = '₱0.00';
                    }
                } else {
                    totalPrice.value = '₱0.00';
                }
            });
        });
    </script>
</body>
</html>
