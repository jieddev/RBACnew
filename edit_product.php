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

// Check if ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = "Invalid product ID";
    header("Location: user_dashboard.php?tab=products");
    exit;
}

$productId = intval($_GET['id']);

// Initialize variables
$error = '';
$success = '';
$product = null;

// Check if description column exists and add it if missing
try {
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'description'");
    if ($columns->rowCount() == 0) {
        // Add description column if it doesn't exist
        $pdo->exec("ALTER TABLE products ADD COLUMN description TEXT");
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error checking columns: " . $e->getMessage();
    header("Location: user_dashboard.php?tab=products");
    exit;
}

// Get product details
try {
    // First check if the stock_quantity column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'stock_quantity'");
    $hasStockQuantity = ($columns->rowCount() > 0);
    
    // Check if the category column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
    $hasCategory = ($columns->rowCount() > 0);
    
    // Check if the description column exists
    $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'description'");
    $hasDescription = ($columns->rowCount() > 0);
    
    // Build the query based on which columns exist
    $query = "SELECT id, name, price";
    if ($hasDescription) {
        $query .= ", description";
    }
    if ($hasStockQuantity) {
        $query .= ", stock_quantity";
    }
    if ($hasCategory) {
        $query .= ", category";
    }
    $query .= " FROM products WHERE id = :id";
    
    // Prepare and execute the query
    $stmt = $pdo->prepare($query);
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header("Location: user_dashboard.php?tab=products");
        exit;
    }
    
    // Set default values for potentially missing columns
    if (!isset($product['description'])) $product['description'] = '';
    if (!isset($product['stock_quantity'])) $product['stock_quantity'] = 0;
    if (!isset($product['category'])) $product['category'] = 'Uncategorized';
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: user_dashboard.php?tab=products");
    exit;
}

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = sanitizeInput($_POST['name']);
    $description = sanitizeInput($_POST['description']);
    $price = floatval($_POST['price']);
    $stock_quantity = intval($_POST['stock_quantity']);
    $category = sanitizeInput($_POST['category']);
    
    // Validate input
    if (empty($name)) {
        $error = "Product name is required";
    } elseif ($price <= 0) {
        $error = "Price must be greater than zero";
    } elseif ($stock_quantity < 0) {
        $error = "Stock quantity cannot be negative";
    } else {
        try {
            // Check if stock quantity has changed
            $oldStockQuantity = isset($product['stock_quantity']) ? $product['stock_quantity'] : 0;
            $stockDifference = $stock_quantity - $oldStockQuantity;
            
            // Check which columns exist in the products table
            $tableColumns = [];
            $columnsResult = $pdo->query("SHOW COLUMNS FROM products");
            while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
                $tableColumns[] = $column['Field'];
            }
            
            // Build the update query dynamically based on existing columns
            $updateFields = [];
            $updateFields[] = "name = :name";
            $updateFields[] = "price = :price";
            
            if (in_array('description', $tableColumns)) {
                $updateFields[] = "description = :description";
            } else {
                // Add description column if it doesn't exist
                $pdo->exec("ALTER TABLE products ADD COLUMN description TEXT");
                $updateFields[] = "description = :description";
            }
            
            if (in_array('stock_quantity', $tableColumns)) {
                $updateFields[] = "stock_quantity = :stock_quantity";
            } else {
                // Add stock_quantity column if it doesn't exist
                $pdo->exec("ALTER TABLE products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0");
                $updateFields[] = "stock_quantity = :stock_quantity";
            }
            
            if (in_array('category', $tableColumns)) {
                $updateFields[] = "category = :category";
            } else {
                // Add category column if it doesn't exist
                $pdo->exec("ALTER TABLE products ADD COLUMN category VARCHAR(50)");
                $updateFields[] = "category = :category";
            }
            
            // Create the SQL query
            $sql = "UPDATE products SET " . implode(", ", $updateFields) . " WHERE id = :id";
            
            // Prepare and execute the query
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                // If stock quantity changed, record a transaction
                if ($stockDifference != 0) {
                    // First check the structure of the transactions table
                    try {
                        $columnsResult = $pdo->query("SHOW COLUMNS FROM transactions");
                        $transactionColumns = [];
                        while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
                            $transactionColumns[] = $column['Field'];
                        }
                        
                        // Determine the correct column names
                        $typeColumn = in_array('type', $transactionColumns) ? 'type' : 
                                    (in_array('transaction_type', $transactionColumns) ? 'transaction_type' : 'type');
                        
                        $productIdColumn = in_array('product_id', $transactionColumns) ? 'product_id' : 
                                        (in_array('productID', $transactionColumns) ? 'productID' : 
                                        (in_array('product', $transactionColumns) ? 'product' : null));
                        
                        $userIdColumn = in_array('user_id', $transactionColumns) ? 'user_id' : null;
                        
                        // Set transaction data
                        $transactionType = $stockDifference > 0 ? 'stock_in' : 'stock_out';
                        $transactionAmount = abs($stockDifference);
                        $transactionDescription = $stockDifference > 0 
                            ? "Added $stockDifference units to inventory for product: $name" 
                            : "Removed " . abs($stockDifference) . " units from inventory for product: $name";
                        
                        // Build the query dynamically based on available columns
                        $insertFields = [$typeColumn, 'amount', 'description'];
                        $insertValues = [':type', ':amount', ':description'];
                        
                        if ($productIdColumn) {
                            $insertFields[] = $productIdColumn;
                            $insertValues[] = ':product_id';
                        }
                        
                        if ($userIdColumn && isset($_SESSION['user_id'])) {
                            $insertFields[] = $userIdColumn;
                            $insertValues[] = ':user_id';
                            $userId = $_SESSION['user_id'];
                        }
                        
                        // Create and execute the SQL query
                        $sql = "INSERT INTO transactions (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $insertValues) . ")";
                        $stmt = $pdo->prepare($sql);
                        $stmt->bindParam(':type', $transactionType, PDO::PARAM_STR);
                        $stmt->bindParam(':amount', $transactionAmount);
                        $stmt->bindParam(':description', $transactionDescription, PDO::PARAM_STR);
                        
                        if ($productIdColumn) {
                            $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
                        }
                        
                        if ($userIdColumn && isset($_SESSION['user_id'])) {
                            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                        }
                        
                        $stmt->execute();
                    } catch (PDOException $transactionError) {
                        // If there's an error with the transaction, log it but don't stop the product update
                        error_log("Transaction recording error: " . $transactionError->getMessage());
                    }
                }
                
                // Set success message and redirect
                $_SESSION['success'] = "Product updated successfully!";
                header("Location: user_dashboard.php?tab=products");
                exit;
            } else {
                $error = "Failed to update product";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}

// Get list of categories for dropdown
try {
    $stmt = $pdo->query("SELECT DISTINCT category FROM products ORDER BY category");
    $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Palengke Plus 3.0</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
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
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        .btn-save {
            background-color: #007bff;
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
            <h1>Edit Product</h1>
            <a href="user_dashboard.php?tab=products" class="btn-back">Back to Products</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . "?id=" . $productId); ?>" method="post">
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($product['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"><?php echo htmlspecialchars(isset($product['description']) ? $product['description'] : ''); ?></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (₱)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" value="<?php echo htmlspecialchars($product['price']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" value="<?php echo htmlspecialchars($product['stock_quantity']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <?php 
                    $productCategory = $product['category'];
                    $categoryExists = false;
                    
                    foreach ($categories as $cat): 
                        $selected = ($cat === $productCategory) ? 'selected' : '';
                        if ($cat === $productCategory) $categoryExists = true;
                    ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $selected; ?>><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; 
                    
                    // If the product's category isn't in the list, add it
                    if (!$categoryExists && !empty($productCategory)):
                    ?>
                        <option value="<?php echo htmlspecialchars($productCategory); ?>" selected><?php echo htmlspecialchars($productCategory); ?></option>
                    <?php endif; ?>
                    <option value="new">Add new category...</option>
                </select>
            </div>
            
            <div id="new-category-group" class="form-group" style="display: none;">
                <label for="new_category">New Category Name</label>
                <input type="text" id="new_category" name="new_category">
            </div>
            
            <div class="form-actions">
                <a href="user_dashboard.php?tab=products" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save">Update Product</button>
            </div>
        </form>
    </div>
    
    <script>
        // Show/hide new category field based on selection
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category');
            const newCategoryGroup = document.getElementById('new-category-group');
            const newCategoryInput = document.getElementById('new_category');
            
            // Auto-hide success messages after 5 seconds
            const successMessage = document.querySelector('.success-message');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.transition = 'opacity 1s';
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 1000);
                }, 5000);
            }
            
            categorySelect.addEventListener('change', function() {
                if (this.value === 'new') {
                    newCategoryGroup.style.display = 'block';
                    newCategoryInput.setAttribute('required', 'required');
                } else {
                    newCategoryGroup.style.display = 'none';
                    newCategoryInput.removeAttribute('required');
                }
            });
            
            document.querySelector('form').addEventListener('submit', function(e) {
                if (categorySelect.value === 'new' && newCategoryInput.value.trim() !== '') {
                    categorySelect.value = newCategoryInput.value.trim();
                }
            });
        });
    </script>
</body>
</html>
