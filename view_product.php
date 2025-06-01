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
$product = null;
$sales = [];

// Get product details
try {
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        $_SESSION['error'] = "Product not found";
        header("Location: user_dashboard.php?tab=products");
        exit;
    }
    
    // Get sales history for this product
    $stmt = $pdo->prepare("SELECT * FROM sales WHERE product_id = :product_id ORDER BY sale_date DESC LIMIT 10");
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total sales and revenue
    $stmt = $pdo->prepare("SELECT SUM(quantity) as total_quantity, SUM(total_price) as total_revenue 
                          FROM sales WHERE product_id = :product_id");
    $stmt->bindParam(':product_id', $productId, PDO::PARAM_INT);
    $stmt->execute();
    $salesSummary = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $totalQuantitySold = $salesSummary['total_quantity'] ?: 0;
    $totalRevenue = $salesSummary['total_revenue'] ?: 0;
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Database error: " . $e->getMessage();
    header("Location: user_dashboard.php?tab=products");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Product - Palengke Plus 3.0</title>
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
        
        .product-details {
            margin-bottom: 30px;
        }
        
        .product-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .product-title {
            font-size: 24px;
            margin: 0;
        }
        
        .product-actions {
            display: flex;
            gap: 10px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
        }
        
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        .product-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-item {
            margin-bottom: 15px;
        }
        
        .info-label {
            font-weight: bold;
            color: #555;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .info-value {
            font-size: 16px;
        }
        
        .description-section {
            margin-bottom: 30px;
        }
        
        .description-section h2 {
            font-size: 18px;
            margin-bottom: 10px;
            color: #333;
        }
        
        .description-content {
            line-height: 1.6;
            color: #555;
        }
        
        .sales-section h2 {
            font-size: 18px;
            margin-bottom: 15px;
            color: #333;
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
        }
        
        .stat-label {
            font-size: 14px;
            color: #555;
            margin-bottom: 5px;
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        table th, table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }
        
        .no-sales {
            text-align: center;
            padding: 20px;
            color: #6c757d;
            font-style: italic;
        }
        
        @media (max-width: 768px) {
            .product-info {
                grid-template-columns: 1fr;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Product Details</h1>
            <a href="user_dashboard.php?tab=products" class="btn-back">Back to Products</a>
        </div>
        
        <div class="product-details">
            <div class="product-header">
                <h2 class="product-title"><?php echo htmlspecialchars($product['name']); ?></h2>
                <div class="product-actions">
                    <a href="edit_product.php?id=<?php echo $product['id']; ?>" class="btn btn-edit">Edit</a>
                    <a href="delete_product.php?id=<?php echo $product['id']; ?>" class="btn btn-delete" onclick="return confirm('Are you sure you want to delete this product?');">Delete</a>
                </div>
            </div>
            
            <div class="product-info">
                <div class="info-item">
                    <div class="info-label">ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($product['id']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Category</div>
                    <div class="info-value"><?php echo htmlspecialchars($product['category']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Price</div>
                    <div class="info-value">₱<?php echo number_format($product['price'], 2); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Stock Quantity</div>
                    <div class="info-value"><?php echo number_format($product['stock_quantity']); ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?php echo isset($product['created_at']) ? date('M d, Y H:i', strtotime($product['created_at'])) : 'Not available'; ?></div>
                </div>
                
                <div class="info-item">
                    <div class="info-label">Last Updated</div>
                    <div class="info-value"><?php echo isset($product['updated_at']) ? date('M d, Y H:i', strtotime($product['updated_at'])) : 'Not available'; ?></div>
                </div>
            </div>
            
            <div class="description-section">
                <h2>Description</h2>
                <div class="description-content">
                    <?php 
                    if (!empty($product['description'])) {
                        echo nl2br(htmlspecialchars($product['description']));
                    } else {
                        echo "<em>No description available</em>";
                    }
                    ?>
                </div>
            </div>
        </div>
        
        <div class="sales-section">
            <h2>Sales History</h2>
            
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-label">Total Quantity Sold</div>
                    <div class="stat-value"><?php echo number_format($totalQuantitySold); ?></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">₱<?php echo number_format($totalRevenue, 2); ?></div>
                </div>
            </div>
            
            <?php if (count($sales) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Sale ID</th>
                            <th>Quantity</th>
                            <th>Total Price</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sales as $sale): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($sale['id']); ?></td>
                                <td><?php echo htmlspecialchars($sale['quantity']); ?></td>
                                <td>₱<?php echo number_format($sale['total_price'], 2); ?></td>
                                <td><?php echo date('M d, Y H:i', strtotime($sale['sale_date'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-sales">No sales records found for this product.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
