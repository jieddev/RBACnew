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

// Initialize variables
$error = '';
$success = '';

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
            // Insert new product
            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category) VALUES (:name, :description, :price, :stock_quantity, :category)");
            $stmt->bindParam(':name', $name, PDO::PARAM_STR);
            $stmt->bindParam(':description', $description, PDO::PARAM_STR);
            $stmt->bindParam(':price', $price);
            $stmt->bindParam(':stock_quantity', $stock_quantity, PDO::PARAM_INT);
            $stmt->bindParam(':category', $category, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                // Get the new product ID
                $productId = $pdo->lastInsertId();
                
                // Record transaction
                $transactionAmount = $price * $stock_quantity;
                $transactionDescription = "Initial inventory for product: $name";
                $userId = $_SESSION['user_id'];
                
                $stmt = $pdo->prepare("INSERT INTO transactions (transaction_type, amount, description, user_id) VALUES ('purchase', :amount, :description, :user_id)");
                $stmt->bindParam(':amount', $transactionAmount);
                $stmt->bindParam(':description', $transactionDescription, PDO::PARAM_STR);
                $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
                $stmt->execute();
                
                // Set success message and redirect
                $_SESSION['success'] = "Product added successfully!";
                header("Location: user_dashboard.php?tab=products");
                exit;
            } else {
                $error = "Failed to add product";
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
    <title>Add Product - Palengke Plus 3.0</title>
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
            <h1>Add New Product</h1>
            <a href="user_dashboard.php?tab=products" class="btn-back">Back to Products</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group">
                <label for="name">Product Name</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description"></textarea>
            </div>
            
            <div class="form-group">
                <label for="price">Price (₱)</label>
                <input type="number" id="price" name="price" step="0.01" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="stock_quantity">Stock Quantity</label>
                <input type="number" id="stock_quantity" name="stock_quantity" min="0" required>
            </div>
            
            <div class="form-group">
                <label for="category">Category</label>
                <select id="category" name="category">
                    <option value="">Select a category</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                    <option value="new">Add new category...</option>
                </select>
            </div>
            
            <div id="new-category-group" class="form-group" style="display: none;">
                <label for="new_category">New Category Name</label>
                <input type="text" id="new_category" name="new_category">
            </div>
            
            <div class="form-actions">
                <a href="user_dashboard.php?tab=products" class="btn-cancel">Cancel</a>
                <button type="submit" class="btn-save">Add Product</button>
            </div>
        </form>
    </div>
    
    <script>
        // Show/hide new category field based on selection
        document.addEventListener('DOMContentLoaded', function() {
            const categorySelect = document.getElementById('category');
            const newCategoryGroup = document.getElementById('new-category-group');
            const newCategoryInput = document.getElementById('new_category');
            
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
