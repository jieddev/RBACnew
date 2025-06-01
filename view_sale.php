<?php
// Initialize the session
session_start();

// Check if the user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    // Redirect to login page
    header("Location: index.php");
    exit;
}

// Include config file
require_once "includes/config.php";

// Check if ID parameter is set
if (!isset($_GET["id"]) || empty($_GET["id"])) {
    $_SESSION["error"] = "No sale ID provided";
    header("location: user_dashboard.php?tab=sales");
    exit;
}

$saleId = $_GET["id"];

try {
    // First, check if the sales table exists
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'sales'");
    if ($tableCheck->rowCount() == 0) {
        $_SESSION["error"] = "Sales table not found";
        header("location: user_dashboard.php?tab=sales");
        exit;
    }
    
    // Check the structure of the sales table
    $columnsResult = $pdo->query("SHOW COLUMNS FROM sales");
    $salesColumns = [];
    while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
        $salesColumns[] = $column['Field'];
    }
    
    // Determine the product ID column name
    $productIdColumn = in_array('product_id', $salesColumns) ? 'product_id' : 
                     (in_array('productID', $salesColumns) ? 'productID' : 
                     (in_array('product', $salesColumns) ? 'product' : 'id'));
    
    // Determine the sale date column name
    $saleDateColumn = in_array('sale_date', $salesColumns) ? 'sale_date' : 
                    (in_array('date', $salesColumns) ? 'date' : 
                    (in_array('created_at', $salesColumns) ? 'created_at' : 'id'));
    
    // First try a simple query to get the sale
    $simpleQuery = "SELECT * FROM sales WHERE id = :id";
    $stmt = $pdo->prepare($simpleQuery);
    $stmt->bindParam(":id", $saleId, PDO::PARAM_INT);
    $stmt->execute();
    
    $sale = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sale) {
        $_SESSION["error"] = "Sale not found";
        header("location: user_dashboard.php?tab=sales");
        exit;
    }
    
    // Now try to get product information if possible
    try {
        $productQuery = "SELECT name, description, category FROM products WHERE id = :product_id";
        $productStmt = $pdo->prepare($productQuery);
        $productStmt->bindParam(":product_id", $sale[$productIdColumn], PDO::PARAM_INT);
        $productStmt->execute();
        
        $productInfo = $productStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($productInfo) {
            $sale['product_name'] = $productInfo['name'];
            $sale['description'] = $productInfo['description'];
            $sale['category'] = $productInfo['category'];
        } else {
            $sale['product_name'] = "Unknown Product";
        }
    } catch (PDOException $productError) {
        // If we can't get product info, just continue with the sale data
        $sale['product_name'] = "Unknown Product";
    }
    
} catch (PDOException $e) {
    $_SESSION["error"] = "Error retrieving sale: " . $e->getMessage();
    header("location: user_dashboard.php?tab=sales");
    exit;
}

// Page title
$pageTitle = "View Sale #" . $saleId;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Palengke Plus 3.0</title>
    <style>
        /* Base styles */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f5f5f5;
            padding-bottom: 20px;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 15px;
        }
        
        header {
            background-color: #2c3e50;
            color: #fff;
            padding: 15px 0;
            margin-bottom: 20px;
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .logo h1 {
            font-size: 24px;
            margin: 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
        }
        
        .btn {
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
            cursor: pointer;
            border: none;
            transition: background-color 0.3s;
        }
        
        .btn-logout {
            background-color: #e74c3c;
            color: white;
        }
        
        .content {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-top: 20px;
        }
        
        h1 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 4px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background-color: #d4edda;
            border-color: #28a745;
            color: #155724;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
        }
        
        /* Sale details specific styles */
        .sale-details {
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .sale-details h2 {
            color: #333;
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 15px;
        }
        
        .detail-label {
            font-weight: bold;
            width: 150px;
            color: #555;
        }
        
        .detail-value {
            flex: 1;
        }
        
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
        }
        
        .actions a {
            padding: 8px 15px;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
        }
        
        .btn-back {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-edit {
            background-color: #007bff;
            color: white;
        }
        
        .btn-delete {
            background-color: #dc3545;
            color: white;
        }
        
        @media print {
            .actions, header {
                display: none;
            }
            
            body {
                background-color: white;
            }
            
            .sale-details {
                box-shadow: none;
                padding: 0;
            }
            
            .container {
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <div class="header-content">
                <div class="logo">
                    <h1>Palengke Plus 3.0</h1>
                </div>
                <div class="user-info">
                    <span>Welcome, <?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : 'User'; ?></span>
                    <a href="logout.php" class="btn btn-logout">Logout</a>
                </div>
            </div>
        </div>
    </header>
    
    <div class="container">
        <div class="content">
            <h1><?php echo $pageTitle; ?></h1>
            
            <?php if (isset($_SESSION["success"])): ?>
                <div class="alert alert-success">
                    <?php 
                    echo $_SESSION["success"]; 
                    unset($_SESSION["success"]);
                    ?>
                </div>
                <script>
                    setTimeout(function() {
                        document.querySelector('.alert-success').style.opacity = '0';
                        setTimeout(function() {
                            document.querySelector('.alert-success').style.display = 'none';
                        }, 500);
                    }, 5000);
                </script>
            <?php endif; ?>
            
            <?php if (isset($_SESSION["error"])): ?>
                <div class="alert alert-danger">
                    <?php 
                    echo $_SESSION["error"]; 
                    unset($_SESSION["error"]);
                    ?>
                </div>
            <?php endif; ?>
            
            <div class="sale-details">
                <h2>Sale Information</h2>
                
                <div class="detail-row">
                    <div class="detail-label">Sale ID:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['id']); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Product:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['product_name']); ?></div>
                </div>
                
                <?php if (isset($sale['description']) && !empty($sale['description'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Description:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['description']); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (isset($sale['category']) && !empty($sale['category'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Category:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['category']); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <div class="detail-label">Quantity:</div>
                    <div class="detail-value"><?php echo htmlspecialchars($sale['quantity']); ?> units</div>
                </div>
                
                <?php if (isset($sale['price_per_unit'])): ?>
                <div class="detail-row">
                    <div class="detail-label">Price Per Unit:</div>
                    <div class="detail-value">₱<?php echo number_format($sale['price_per_unit'], 2); ?></div>
                </div>
                <?php endif; ?>
                
                <div class="detail-row">
                    <div class="detail-label">Total Price:</div>
                    <div class="detail-value">₱<?php echo number_format($sale['total_price'], 2); ?></div>
                </div>
                
                <div class="detail-row">
                    <div class="detail-label">Sale Date:</div>
                    <div class="detail-value">
                        <?php 
                        $dateColumn = isset($sale[$saleDateColumn]) ? $saleDateColumn : 
                                    (isset($sale['date']) ? 'date' : 
                                    (isset($sale['created_at']) ? 'created_at' : 'id'));
                        
                        if (isset($sale[$dateColumn])) {
                            echo date('F d, Y h:i A', strtotime($sale[$dateColumn]));
                        } else {
                            echo "Unknown date";
                        }
                        ?>
                    </div>
                </div>
            </div>
            
            <div class="actions">
                <a href="user_dashboard.php?tab=sales" class="btn-back">Back to Sales</a>
                <a href="javascript:window.print()" class="btn-edit">Print Receipt</a>
                <?php if ($_SESSION["role"] === "admin"): ?>
                <a href="edit_sale.php?id=<?php echo $sale['id']; ?>" class="btn-edit">Edit Sale</a>
                <a href="delete_sale.php?id=<?php echo $sale['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this sale?');">Delete Sale</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-hide success messages after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const successAlert = document.querySelector('.alert-success');
            if (successAlert) {
                setTimeout(function() {
                    successAlert.style.opacity = '0';
                    setTimeout(function() {
                        successAlert.style.display = 'none';
                    }, 500);
                }, 5000);
            }
        });
    </script>
</body>
</html>
