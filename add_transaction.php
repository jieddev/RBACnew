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

// Get user info
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Check if admin is accessing this page and redirect to admin dashboard
if ($username === 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Initialize variables
$error = '';
$success = '';
$products = [];

// Get products for dropdown
try {
    $stmt = $pdo->query("SELECT id, name, stock_quantity FROM products ORDER BY name");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "Error loading products: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $productId = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $type = isset($_POST['type']) ? trim($_POST['type']) : '';
    $amount = isset($_POST['amount']) ? intval($_POST['amount']) : 0;
    $description = isset($_POST['description']) ? trim($_POST['description']) : '';
    
    // Validate required fields
    if ($productId <= 0 || empty($type) || $amount <= 0) {
        $error = "Please fill all required fields with valid values.";
    } else {
        try {
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
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Get product details
            $productStmt = $pdo->prepare("SELECT name, stock_quantity FROM products WHERE id = ?");
            $productStmt->execute([$productId]);
            $product = $productStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$product) {
                throw new Exception("Product not found.");
            }
            
            // Update product stock based on transaction type
            $newStock = $product['stock_quantity'];
            
            if ($type === 'stock_in') {
                $newStock += $amount;
            } else if ($type === 'stock_out') {
                if ($product['stock_quantity'] < $amount) {
                    throw new Exception("Not enough stock available. Current stock: " . $product['stock_quantity']);
                }
                $newStock -= $amount;
            }
            
            // Update product stock
            $updateStmt = $pdo->prepare("UPDATE products SET stock_quantity = ? WHERE id = ?");
            $updateStmt->execute([$newStock, $productId]);
            
            // Prepare transaction insert statement with dynamic column names
            $transactionSql = "INSERT INTO transactions (";
            $transactionSql .= "$transactionTypeColumn, amount, description, $transactionProductIdColumn, transaction_date";
            $transactionSql .= ") VALUES (?, ?, ?, ?, NOW())";
            $insertTransactionStmt = $pdo->prepare($transactionSql);
            $insertTransactionStmt->execute([$type, $amount, $description, $productId]);
            
            // Commit transaction
            $pdo->commit();
            
            $success = "Transaction added successfully!";
            
            // Set success message in session and redirect back to dashboard
            $_SESSION['success'] = $success;
            header("Location: user_dashboard.php?tab=transactions");
            exit;
            
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transaction - Palengke Plus 3.0</title>
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
            margin: 0 auto;
            padding: 20px;
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
        
        .form-container {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-group textarea {
            height: 100px;
            resize: vertical;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            border: none;
            display: inline-block;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Add Transaction</h1>
            <a href="user_dashboard.php?tab=transactions" class="btn btn-secondary">Back to Dashboard</a>
        </div>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="success-message">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="form-container">
            <form method="post" action="">
                <div class="form-group">
                    <label for="product_id">Product *</label>
                    <select name="product_id" id="product_id" required>
                        <option value="">Select Product</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?php echo $product['id']; ?>" <?php echo isset($_POST['product_id']) && $_POST['product_id'] == $product['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($product['name']); ?> (Stock: <?php echo $product['stock_quantity']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type">Transaction Type *</label>
                    <select name="type" id="type" required>
                        <option value="">Select Type</option>
                        <option value="stock_in" <?php echo isset($_POST['type']) && $_POST['type'] == 'stock_in' ? 'selected' : ''; ?>>Stock In</option>
                        <option value="stock_out" <?php echo isset($_POST['type']) && $_POST['type'] == 'stock_out' ? 'selected' : ''; ?>>Stock Out</option>
                        <option value="adjustment" <?php echo isset($_POST['type']) && $_POST['type'] == 'adjustment' ? 'selected' : ''; ?>>Adjustment</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="amount">Amount *</label>
                    <input type="number" name="amount" id="amount" min="1" value="<?php echo isset($_POST['amount']) ? htmlspecialchars($_POST['amount']) : ''; ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea name="description" id="description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                </div>
                
                <div class="form-actions">
                    <a href="user_dashboard.php?tab=transactions" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Add Transaction</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-hide success message after 5 seconds
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            setTimeout(function() {
                successMessage.style.display = 'none';
            }, 5000);
        }
        
        // Add event listener to transaction type dropdown
        document.getElementById('type').addEventListener('change', function() {
            const typeValue = this.value;
            const amountField = document.getElementById('amount');
            
            if (typeValue === 'stock_out') {
                const productSelect = document.getElementById('product_id');
                const selectedOption = productSelect.options[productSelect.selectedIndex];
                
                if (selectedOption.value) {
                    const stockText = selectedOption.text.match(/Stock: (\d+)/);
                    if (stockText && stockText[1]) {
                        const maxStock = parseInt(stockText[1]);
                        amountField.max = maxStock;
                        amountField.setAttribute('max', maxStock);
                    }
                }
            } else {
                amountField.removeAttribute('max');
            }
        });
        
        // Add event listener to product dropdown
        document.getElementById('product_id').addEventListener('change', function() {
            const typeSelect = document.getElementById('type');
            if (typeSelect.value === 'stock_out') {
                typeSelect.dispatchEvent(new Event('change'));
            }
        });
    </script>
</body>
</html>
