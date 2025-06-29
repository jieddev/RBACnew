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

// Get user info
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Get user's name
try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :user_id");
    $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Use username if no name is available
    $displayName = $username;
    if (isset($userData['full_name']) && !empty($userData['full_name'])) {
        $displayName = $userData['full_name'];
    } else if (isset($userData['first_name']) && isset($userData['last_name'])) {
        $displayName = $userData['first_name'] . ' ' . $userData['last_name'];
    }
} catch (PDOException $e) {
    $displayName = $username;
}

// Check if admin is accessing this page and redirect to admin dashboard
if ($username === 'admin') {
    header("Location: dashboard.php");
    exit;
}

// Get product count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM products");
    $productCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $productCount = 0;
}

// Get sales count
try {
    $stmt = $pdo->query("SELECT COUNT(*) FROM sales");
    $salesCount = $stmt->fetchColumn();
} catch (PDOException $e) {
    $salesCount = 0;
}

// Get total sales amount
try {
    // First check if the total_price column exists in the sales table
    $salesColumns = [];
    try {
        $columnQuery = $pdo->query("SHOW COLUMNS FROM sales");
        while ($column = $columnQuery->fetch(PDO::FETCH_ASSOC)) {
            $salesColumns[] = $column['Field'];
        }
    } catch (PDOException $e) {
        // Table might not exist
        $salesColumns = [];
    }
    
    // Determine the price column name
    $priceColumn = in_array('total_price', $salesColumns) ? 'total_price' : 
                  (in_array('price', $salesColumns) ? 'price' : 
                  (in_array('amount', $salesColumns) ? 'amount' : null));
    
    if ($priceColumn) {
        $stmt = $pdo->query("SELECT SUM($priceColumn) FROM sales");
        $totalSales = $stmt->fetchColumn();
        if ($totalSales === null) $totalSales = 0;
    } else {
        $totalSales = 0;
    }
} catch (PDOException $e) {
    $totalSales = 0;
}

// Get recent transactions
try {
    $stmt = $pdo->query("SELECT * FROM transactions ORDER BY transaction_date DESC LIMIT 5");
    $recentTransactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $recentTransactions = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management - Palengke Plus 3.0</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
            margin: 0;
            padding: 0;
        }
        
        .container {
            max-width: 1200px;
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
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info span {
            margin-right: 15px;
        }
        
        .btn-logout {
            background-color: #dc3545;
            color: white;
            padding: 8px 15px;
            border-radius: 4px;
            text-decoration: none;
        }
        
        .dashboard-stats {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            margin-top: 0;
            color: #555;
            font-size: 16px;
        }
        
        .stat-card .number {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin: 10px 0;
        }
        
        .tabs {
            display: flex;
            border-bottom: 1px solid #ddd;
            margin-bottom: 20px;
        }
        
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-bottom: none;
            border-radius: 5px 5px 0 0;
            margin-right: 5px;
            transition: background-color 0.2s;
        }
        
        .tab:hover:not(.disabled) {
            background-color: #e9ecef;
        }
        
        .tab.active {
            background-color: #fff;
            border-bottom: 2px solid #fff;
            font-weight: bold;
            position: relative;
            z-index: 1;
        }
        
        .tab.disabled {
            opacity: 0.6;
            cursor: not-allowed;
            color: #6c757d;
            background-color: #f1f1f1;
        }
        
        .permission-message {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            background-color: #f8f9fa;
            border-radius: 5px;
            margin: 20px 0;
        }
        
        .permission-message p {
            font-size: 18px;
            color: #6c757d;
            text-align: center;
        }
        
        .tab-content {
            display: none;
            background-color: #fff;
            padding: 20px;
            border-radius: 0 0 8px 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .tab-content.active {
            display: block;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
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
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
            border: none;
        }
        
        .btn-view {
            background-color: #17a2b8;
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
        
        .btn-add {
            background-color: #28a745;
            color: white;
            padding: 8px 15px;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        /* Shop Tab Styles */
        .shop-container {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }
        
        .products-section {
            flex: 3;
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        
        .cart-section {
            flex: 1;
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 20px;
            height: fit-content;
            max-height: calc(100vh - 40px);
            overflow-y: auto;
        }
        
        .filter-section {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .search-input, .category-filter {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .search-input {
            flex: 2;
        }
        
        .category-filter {
            flex: 1;
        }
        
        .products-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .product-card {
            background: #fff;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
            display: flex;
            flex-direction: column;
        }
        
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .product-card h3 {
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 16px;
            color: #333;
        }
        
        .product-category {
            color: #666;
            font-size: 12px;
            margin: 0 0 10px;
        }
        
        .product-price {
            font-weight: bold;
            color: #e63946;
            font-size: 18px;
            margin: 5px 0;
        }
        
        .product-stock {
            color: #666;
            font-size: 12px;
            margin: 0 0 15px;
        }
        
        .add-to-cart-btn {
            background-color: #4caf50;
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 4px;
            cursor: pointer;
            margin-top: auto;
            transition: background-color 0.2s;
        }
        
        .add-to-cart-btn:hover {
            background-color: #45a049;
        }
        
        .cart-items {
            margin-bottom: 20px;
            max-height: 300px;
            overflow-y: auto;
        }
        
        .empty-cart-message {
            color: #666;
            text-align: center;
            font-style: italic;
        }
        
        .cart-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #eee;
        }
        
        .item-details h4 {
            margin: 0 0 5px;
            font-size: 14px;
        }
        
        .item-details p {
            margin: 0;
            color: #666;
            font-size: 12px;
        }
        
        .item-actions {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 5px;
        }
        
        .item-total {
            font-weight: bold;
            color: #e63946;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .quantity-btn {
            width: 24px;
            height: 24px;
            border: 1px solid #ddd;
            background: #f5f5f5;
            border-radius: 4px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        
        .quantity {
            font-size: 14px;
        }
        
        .remove-btn {
            background: none;
            border: none;
            color: #e63946;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
        }
        
        .cart-summary {
            margin-bottom: 20px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 18px;
            border-top: 1px solid #eee;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .checkout-btn, .clear-cart-btn {
            width: 100%;
            padding: 10px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            margin-bottom: 10px;
            transition: background-color 0.2s;
        }
        
        .checkout-btn {
            background-color: #4caf50;
            color: white;
        }
        
        .checkout-btn:hover:not(:disabled) {
            background-color: #45a049;
        }
        
        .clear-cart-btn {
            background-color: #f44336;
            color: white;
        }
        
        .clear-cart-btn:hover:not(:disabled) {
            background-color: #d32f2f;
        }
        
        .checkout-btn:disabled, .clear-cart-btn:disabled {
            background-color: #cccccc;
            color: #666;
            cursor: not-allowed;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            width: 80%;
            max-width: 600px;
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .checkout-details {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .checkout-items, .payment-section {
            flex: 1;
        }
        
        .checkout-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .payment-methods {
            margin-bottom: 15px;
        }
        
        .payment-method {
            margin-bottom: 10px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .checkout-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .process-order-btn, .cancel-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: bold;
            flex: 1;
        }
        
        .process-order-btn {
            background-color: #4caf50;
            color: white;
        }
        
        .process-order-btn:hover:not(:disabled) {
            background-color: #45a049;
        }
        
        .process-order-btn:disabled {
            background-color: #cccccc;
            color: #666;
            cursor: not-allowed;
        }
        
        .cancel-btn {
            background-color: #f44336;
            color: white;
        }
        
        .cancel-btn:hover {
            background-color: #d32f2f;
        }
        
        /* Receipt Styles */
        .modal-content.receipt {
            max-width: 400px;
        }
        
        .receipt-content {
            text-align: center;
        }
        
        .receipt-header {
            margin-bottom: 20px;
        }
        
        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .receipt-table th, .receipt-table td {
            padding: 8px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        .receipt-summary {
            text-align: right;
            margin-bottom: 20px;
        }
        
        .receipt-footer {
            margin-bottom: 20px;
            font-style: italic;
        }
        
        .receipt-actions {
            display: flex;
            justify-content: space-between;
            gap: 10px;
        }
        
        .receipt-actions .btn {
            padding: 8px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            background-color: #4caf50;
            color: white;
            flex: 1;
        }
        
        .receipt-actions .btn:hover {
            background-color: #45a049;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px 15px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        
        .search-form {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .search-form input {
            flex-grow: 1;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .search-form button {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 20px;
            border-radius: 8px;
            width: 50%;
            max-width: 500px;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            font-size: 20px;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        
        .form-group input, .form-group textarea, .form-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 20px;
        }
        
        /* Cashier styles */
        .cashier-container {
            display: flex;
            gap: 20px;
            height: calc(100vh - 300px);
            min-height: 500px;
        }
        
        .product-selection {
            flex: 1;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 15px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        .product-selection h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .search-container {
            margin-bottom: 15px;
        }
        
        .product-search {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .product-list {
            flex: 1;
            overflow-y: auto;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 15px;
            padding-right: 5px;
        }
        
        .product-item {
            background-color: #f8f9fa;
            border: 1px solid #eee;
            border-radius: 6px;
            padding: 12px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .product-item:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }
        
        .product-info {
            margin-bottom: 10px;
        }
        
        .product-info h3 {
            margin: 0 0 5px 0;
            font-size: 16px;
            color: #333;
        }
        
        .product-category {
            color: #666;
            font-size: 12px;
            margin: 0 0 5px 0;
        }
        
        .product-price {
            font-weight: bold;
            color: #2c3e50;
            margin: 0 0 5px 0;
            font-size: 15px;
        }
        
        .product-stock {
            color: #666;
            font-size: 12px;
            margin: 0;
        }
        
        .add-to-cart-btn {
            background-color: #3498db;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 8px 12px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s;
        }
        
        .add-to-cart-btn:hover {
            background-color: #2980b9;
        }
        
        .cart-section {
            width: 400px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            padding: 15px;
            display: flex;
            flex-direction: column;
        }
        
        .cart-section h2 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .cart-items {
            flex: 1;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        
        .cart-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cart-table th {
            background-color: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-size: 14px;
            color: #333;
        }
        
        .cart-table td {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-size: 14px;
        }
        
        .cart-table .empty-cart-message td {
            text-align: center;
            padding: 20px;
            color: #666;
        }
        
        .cart-item-quantity {
            display: flex;
            align-items: center;
        }
        
        .quantity-btn {
            width: 24px;
            height: 24px;
            background-color: #f1f1f1;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
        }
        
        .quantity-input {
            width: 40px;
            text-align: center;
            margin: 0 5px;
            padding: 3px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .remove-item-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            border-radius: 4px;
            padding: 5px 8px;
            cursor: pointer;
            font-size: 12px;
        }
        
        .cart-summary {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .summary-row.total {
            font-weight: bold;
            font-size: 16px;
            color: #2c3e50;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .checkout-actions {
            display: flex;
            justify-content: space-between;
        }
        
        .btn-primary {
            background-color: #2ecc71;
            color: white;
        }
        
        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #27ae60;
        }
        
        .btn-secondary:hover {
            background-color: #7f8c8d;
        }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            overflow: auto;
        }
        
        .modal-content {
            background-color: #fff;
            margin: 50px auto;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
            width: 600px;
            max-width: 90%;
            animation: modalFadeIn 0.3s;
        }
        
        @keyframes modalFadeIn {
            from {opacity: 0; transform: translateY(-50px);}
            to {opacity: 1; transform: translateY(0);}
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-header h2 {
            margin: 0;
            color: #2c3e50;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
            color: #666;
        }
        
        .close:hover {
            color: #000;
        }
        
        .modal-body {
            padding: 20px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        .modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        /* Order summary styles */
        .order-summary h3, .payment-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: #2c3e50;
        }
        
        .order-items {
            margin-bottom: 20px;
        }
        
        .order-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        
        .item-details {
            flex: 1;
        }
        
        .item-name {
            font-weight: 500;
        }
        
        .item-price, .item-quantity {
            font-size: 14px;
            color: #666;
        }
        
        .item-total {
            font-weight: 500;
        }
        
        .order-totals {
            background-color: #f8f9fa;
            border-radius: 6px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
        }
        
        .total-row.grand-total {
            font-weight: bold;
            font-size: 16px;
            color: #2c3e50;
            border-top: 1px solid #ddd;
            padding-top: 10px;
            margin-top: 10px;
        }
        
        .payment-section {
            margin-top: 30px;
        }
        
        /* Receipt styles */
        #receipt-content {
            font-family: 'Courier New', monospace;
            padding: 20px;
            background-color: #fff;
        }
        
        .receipt-header {
            text-align: center;
            margin-bottom: 20px;
        }
        
        .receipt-header h2 {
            margin: 0 0 10px 0;
        }
        
        .receipt-header p {
            margin: 5px 0;
        }
        
        .receipt-items table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .receipt-items th {
            border-bottom: 1px dashed #000;
            padding: 5px;
            text-align: left;
        }
        
        .receipt-items td {
            padding: 5px;
        }
        
        .receipt-totals {
            margin-top: 20px;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        
        .receipt-total-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        
        .receipt-footer {
            margin-top: 30px;
            text-align: center;
            border-top: 1px dashed #000;
            padding-top: 10px;
        }
        
        @media print {
            body * {
                visibility: hidden;
            }
            
            #receipt-modal, #receipt-modal * {
                visibility: visible;
            }
            
            #receipt-modal {
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                height: 100%;
                background: none;
                box-shadow: none;
            }
            
            .modal-header, .modal-footer {
                display: none;
            }
            
            .modal-content {
                box-shadow: none;
                width: 100%;
                margin: 0;
                padding: 0;
            }
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .dashboard-stats {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                width: 90%;
            }
        }
    </style>
    <script>
        // Wait for DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Get all tab elements
            const tabs = document.querySelectorAll('.tab');
            const tabContents = document.querySelectorAll('.tab-content');
            
            // Add click event to each tab
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Check if tab is disabled
                    if (this.classList.contains('disabled')) {
                        // Show a message that the user doesn't have permission
                        alert('You do not have permission to access this tab.');
                        return; // Stop execution
                    }
                    
                    // Remove active class from all tabs
                    tabs.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked tab
                    this.classList.add('active');
                    
                    // Hide all tab contents
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Show the corresponding tab content
                    const tabId = this.getAttribute('data-tab');
                    document.getElementById(tabId).classList.add('active');
                    
                    // Update URL with tab parameter
                    const url = new URL(window.location.href);
                    url.searchParams.set('tab', tabId);
                    window.history.replaceState({}, '', url);
                });
            });
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Palengke Plus 3.0</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($displayName); ?></span>
                <a href="logout.php" class="btn-logout">Logout</a>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="success-message">
                <?php 
                    echo htmlspecialchars($_SESSION['success']); 
                    unset($_SESSION['success']);
                ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="error-message">
                <?php 
                    echo htmlspecialchars($_SESSION['error']); 
                    unset($_SESSION['error']);
                ?>
            </div>
        <?php endif; ?>
        
        <div class="dashboard-stats">
            <div class="stat-card">
                <h3>Total Products</h3>
                <div class="number"><?php echo number_format($productCount); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Total Sales</h3>
                <div class="number"><?php echo number_format($salesCount); ?></div>
            </div>
            
            <div class="stat-card">
                <h3>Revenue</h3>
                <div class="number">₱<?php echo number_format($totalSales, 2); ?></div>
            </div>
        </div>
        
        <?php
        // Check if user_permissions table exists
        $hasPermissionsTable = false;
        $userPermissions = [];
        
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'user_permissions'")->rowCount() > 0;
            
            if ($tableExists) {
                $hasPermissionsTable = true;
                // Get user permissions
                $stmt = $pdo->prepare("SELECT permission_name FROM user_permissions WHERE user_id = ?");
                $stmt->execute([$userId]);
                $userPermissions = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (PDOException $e) {
            // If there's an error, we'll assume no permissions table exists
            $hasPermissionsTable = false;
        }
        
        // Include functions file which contains hasPermission function
        require_once 'includes/functions.php';
        
        // Determine which tab should be active
        $activeTab = 'products'; // Default active tab
        
        // Check if a tab is specified in URL and user has permission for it
        if (isset($_GET['tab'])) {
            $requestedTab = $_GET['tab'];
            $permissionMap = [
                'products' => 'view_products',
                'sales' => 'view_sales',
                'transactions' => 'view_transactions',
                'cashier' => 'add_orders'
            ];
            
            if (isset($permissionMap[$requestedTab]) && 
                hasPermission($permissionMap[$requestedTab], $userPermissions, $hasPermissionsTable)) {
                $activeTab = $requestedTab;
            }
        }
        
        // If user doesn't have permission for the default tab, find the first one they do have permission for
        if (!hasPermission('view_products', $userPermissions, $hasPermissionsTable)) {
            if (hasPermission('view_sales', $userPermissions, $hasPermissionsTable)) {
                $activeTab = 'sales';
            } elseif (hasPermission('view_transactions', $userPermissions, $hasPermissionsTable)) {
                $activeTab = 'transactions';
            } elseif (hasPermission('add_orders', $userPermissions, $hasPermissionsTable)) {
                $activeTab = 'cashier';
            }
        } else {
            // If user has permission for products tab and no specific tab is requested, make products the active tab
            if (!isset($_GET['tab'])) {
                $activeTab = 'products';
            }
        }
        ?>
        <div class="tabs">
            <div class="tab <?php echo $activeTab === 'products' ? 'active' : ''; ?> <?php echo !hasPermission('view_products', $userPermissions, $hasPermissionsTable) ? 'disabled' : ''; ?>" data-tab="products" <?php echo !hasPermission('view_products', $userPermissions, $hasPermissionsTable) ? 'title="You do not have permission to access this tab"' : ''; ?>>Products</div>
            
            <div class="tab <?php echo $activeTab === 'sales' ? 'active' : ''; ?> <?php echo !hasPermission('view_sales', $userPermissions, $hasPermissionsTable) ? 'disabled' : ''; ?>" data-tab="sales" <?php echo !hasPermission('view_sales', $userPermissions, $hasPermissionsTable) ? 'title="You do not have permission to access this tab"' : ''; ?>>Sales</div>
            
            <div class="tab <?php echo $activeTab === 'transactions' ? 'active' : ''; ?> <?php echo !hasPermission('view_transactions', $userPermissions, $hasPermissionsTable) ? 'disabled' : ''; ?>" data-tab="transactions" <?php echo !hasPermission('view_transactions', $userPermissions, $hasPermissionsTable) ? 'title="You do not have permission to access this tab"' : ''; ?>>Transactions</div>
            
            <div class="tab <?php echo $activeTab === 'shop' ? 'active' : ''; ?> <?php echo !hasPermission('view_products', $userPermissions, $hasPermissionsTable) ? 'disabled' : ''; ?>" data-tab="shop" <?php echo !hasPermission('view_products', $userPermissions, $hasPermissionsTable) ? 'title="You do not have permission to access this tab"' : ''; ?>>Shop</div>
        </div>
        
        <!-- Products Tab -->
        <div id="products" class="tab-content <?php echo $activeTab === 'products' ? 'active' : ''; ?>">
            <?php if (!hasPermission('view_products', $userPermissions, $hasPermissionsTable)): ?>
                <div class="permission-message">
                    <p>You do not have permission to view this content.</p>
                </div>
            <?php else: ?>
            <div class="tab-header">
                <?php if (hasPermission('add_products', $userPermissions, $hasPermissionsTable)): ?>
                <a href="add_product.php" class="btn btn-add">Add New Product</a>
                <?php endif; ?>
                
                <form class="search-form" action="" method="get">
                    <input type="hidden" name="tab" value="products">
                    <input type="text" name="search" placeholder="Search products..." value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Category</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    try {
                        // Check if products table exists and has required columns
                        $checkTable = $pdo->query("SHOW TABLES LIKE 'products'");
                        if ($checkTable->rowCount() == 0) {
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
                            
                            // Insert sample data
                            $sampleProducts = [
                                ['Rice', '5kg premium white rice', 250.00, 100, 'Grains'],
                                ['Cooking Oil', '1L vegetable cooking oil', 120.00, 50, 'Oils'],
                                ['Sugar', '1kg refined white sugar', 65.00, 80, 'Baking']
                            ];
                            
                            $stmt = $pdo->prepare("INSERT INTO products (name, description, price, stock_quantity, category) VALUES (?, ?, ?, ?, ?)");
                            foreach ($sampleProducts as $product) {
                                $stmt->execute($product);
                            }
                        } else {
                            // Check if stock_quantity column exists
                            $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'stock_quantity'");
                            if ($columns->rowCount() == 0) {
                                // Add stock_quantity column if it doesn't exist
                                $pdo->exec("ALTER TABLE products ADD COLUMN stock_quantity INT NOT NULL DEFAULT 0");
                            }
                            
                            // Check if category column exists
                            $columns = $pdo->query("SHOW COLUMNS FROM products LIKE 'category'");
                            if ($columns->rowCount() == 0) {
                                // Add category column if it doesn't exist
                                $pdo->exec("ALTER TABLE products ADD COLUMN category VARCHAR(50)");
                            }
                        }
                        
                        // Prepare query with search functionality
                        $search = isset($_GET['search']) ? '%' . $_GET['search'] . '%' : '';
                        
                        if (!empty($search)) {
                            $stmt = $pdo->prepare("SELECT * FROM products WHERE name LIKE :search OR category LIKE :search ORDER BY id DESC");
                            $stmt->bindParam(':search', $search, PDO::PARAM_STR);
                        } else {
                            $stmt = $pdo->query("SELECT * FROM products ORDER BY id DESC");
                        }
                        
                        $stmt->execute();
                        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($products) > 0) {
                            foreach ($products as $product) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($product['id']) . "</td>";
                                echo "<td>" . htmlspecialchars($product['name']) . "</td>";
                                echo "<td>₱" . number_format($product['price'], 2) . "</td>";
                                echo "<td>" . htmlspecialchars(isset($product['stock_quantity']) ? $product['stock_quantity'] : '0') . "</td>";
                                echo "<td>" . htmlspecialchars(isset($product['category']) ? $product['category'] : 'Uncategorized') . "</td>";
                                echo "<td class='action-buttons'>";
                                echo "<a href='view_product.php?id=" . $product['id'] . "' class='btn btn-view'>View</a>";
                                if (hasPermission('edit_products', $userPermissions, $hasPermissionsTable)) {
                                    echo "<a href='edit_product.php?id=" . $product['id'] . "' class='btn btn-edit'>Edit</a>";
                                }
                                if (hasPermission('delete_products', $userPermissions, $hasPermissionsTable)) {
                                    echo "<a href='delete_product.php?id=" . $product['id'] . "' class='btn btn-delete' onclick='return confirm(\"Are you sure you want to delete this product?\");'>Delete</a>";
                                }
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No products found.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='6'>Error loading products: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        
        <!-- Sales Tab -->
        <div id="sales" class="tab-content <?php echo $activeTab === 'sales' ? 'active' : ''; ?>">
            <?php if (!hasPermission('view_sales', $userPermissions, $hasPermissionsTable)): ?>
                <div class="permission-message">
                    <p>You do not have permission to view this content.</p>
                </div>
            <?php else: ?>
            <div class="tab-header">
                <?php if (hasPermission('add_transactions', $userPermissions, $hasPermissionsTable)): ?>
                <a href="add_sale.php" class="btn btn-add">Record New Sale</a>
                <?php endif; ?>
                
                <form class="search-form" action="" method="get">
                    <input type="hidden" name="tab" value="sales">
                    <input type="text" name="search_sale" placeholder="Search sales..." value="<?php echo isset($_GET['search_sale']) ? htmlspecialchars($_GET['search_sale']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Product</th>
                        <th>Quantity</th>
                        <th>Total Price</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // First, let's check the structure of the sales table
                        try {
                            // Get the column names from the sales table
                            $columnsResult = $pdo->query("SHOW COLUMNS FROM sales");
                            $salesColumns = [];
                            while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
                                $salesColumns[] = $column['Field'];
                            }
                            
                            // Determine the product ID column name
                            $productIdColumn = in_array('product_id', $salesColumns) ? 'product_id' : 
                                             (in_array('productID', $salesColumns) ? 'productID' : 
                                             (in_array('product', $salesColumns) ? 'product' : null));
                            
                            // Determine the sale date column name
                            $saleDateColumn = in_array('sale_date', $salesColumns) ? 'sale_date' : 
                                           (in_array('date', $salesColumns) ? 'date' : 
                                           (in_array('created_at', $salesColumns) ? 'created_at' : 'id'));
                            
                            // If we couldn't find a product ID column, we need to create the table
                            if (!$productIdColumn) {
                                // Drop the existing sales table if it exists but has wrong structure
                                $pdo->exec("DROP TABLE IF EXISTS sales");
                                
                                // Create sales table with the correct structure
                                $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    product_id INT NOT NULL,
                                    quantity INT NOT NULL,
                                    price_per_unit DECIMAL(10,2) NOT NULL,
                                    total_price DECIMAL(10,2) NOT NULL,
                                    sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                )");
                                
                                // Add sample sales data if products exist
                                $productsExist = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                                if ($productsExist > 0) {
                                    // Get first few products for sample data
                                    $products = $pdo->query("SELECT id, price FROM products LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($products) > 0) {
                                        $sampleSales = [];
                                        foreach ($products as $product) {
                                            $quantity = rand(1, 5);
                                            $price = $product['price'];
                                            $total = $quantity * $price;
                                            $sampleSales[] = [
                                                $product['id'],
                                                $quantity,
                                                $price,
                                                $total
                                            ];
                                        }
                                        
                                        $stmt = $pdo->prepare("INSERT INTO sales (product_id, quantity, price_per_unit, total_price) VALUES (?, ?, ?, ?)");
                                        foreach ($sampleSales as $sale) {
                                            $stmt->execute($sale);
                                        }
                                    }
                                }
                                
                                // Set the column names for the new table
                                $productIdColumn = 'product_id';
                                $saleDateColumn = 'sale_date';
                            }
                            
                            // Get search parameter if exists
                            $search = isset($_GET['search_sale']) ? '%' . $_GET['search_sale'] . '%' : '';
                            
                            // Build the query based on the actual table structure
                            $query = "SELECT s.*, p.name as product_name 
                                    FROM sales s 
                                    JOIN products p ON s.{$productIdColumn} = p.id";
                            
                            if (!empty($search)) {
                                $query .= " WHERE p.name LIKE :search";
                                $query .= " ORDER BY s.{$saleDateColumn} DESC";
                                $stmt = $pdo->prepare($query);
                                $stmt->bindParam(':search', $search, PDO::PARAM_STR);
                                $stmt->execute();
                            } else {
                                $query .= " ORDER BY s.{$saleDateColumn} DESC LIMIT 20";
                                $stmt = $pdo->query($query);
                            }
                        } catch (PDOException $structureError) {
                            // If we can't determine the structure, recreate the table
                            $pdo->exec("DROP TABLE IF EXISTS sales");
                            
                            // Create sales table with the correct structure
                            $pdo->exec("CREATE TABLE IF NOT EXISTS sales (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                product_id INT NOT NULL,
                                quantity INT NOT NULL,
                                price_per_unit DECIMAL(10,2) NOT NULL,
                                total_price DECIMAL(10,2) NOT NULL,
                                sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )");
                            
                            // Set default column names
                            $productIdColumn = 'product_id';
                            $saleDateColumn = 'sale_date';
                            
                            // Create a simple query to get all sales
                            $productIdColumn = 'product_id';
                            $saleDateColumn = 'sale_date';
                            
                            // Build a simple query to get all sales
                            $query = "SELECT s.*, p.name as product_name 
                                    FROM sales s 
                                    JOIN products p ON s.{$productIdColumn} = p.id 
                                    ORDER BY s.{$saleDateColumn} DESC LIMIT 20";
                            $stmt = $pdo->query($query);
                        }
                        
                        // Make sure $stmt is defined before trying to fetch data
                        if (isset($stmt)) {
                            $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        } else {
                            $sales = [];
                        }
                        
                        if (count($sales) > 0) {
                            foreach ($sales as $sale) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($sale['id']) . "</td>";
                                echo "<td>" . htmlspecialchars(isset($sale['product_name']) ? $sale['product_name'] : 'Unknown Product') . "</td>";
                                echo "<td>" . htmlspecialchars(isset($sale['quantity']) ? $sale['quantity'] : 0) . "</td>";
                                echo "<td>₱" . number_format(isset($sale['total_price']) ? $sale['total_price'] : 0, 2) . "</td>";
                                
                                // Handle date with proper checks
                                $dateColumn = isset($sale['sale_date']) ? 'sale_date' : 
                                            (isset($sale['date']) ? 'date' : 
                                            (isset($sale['created_at']) ? 'created_at' : null));
                                
                                if ($dateColumn && isset($sale[$dateColumn])) {
                                    echo "<td>" . date('M d, Y H:i', strtotime($sale[$dateColumn])) . "</td>";
                                } else {
                                    echo "<td>Unknown date</td>";
                                }
                                echo "<td class='action-buttons'>";
                                echo "<a href='view_sale.php?id=" . $sale['id'] . "' class='btn btn-view'>View</a>";
                                echo "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='6'>No sales found.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='6'>Error loading sales: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- Transactions Tab -->
        <div id="transactions" class="tab-content <?php echo $activeTab === 'transactions' ? 'active' : ''; ?>">
            <?php if (!hasPermission('view_transactions', $userPermissions, $hasPermissionsTable)): ?>
                <div class="permission-message">
                    <p>You do not have permission to view this content.</p>
                </div>
            <?php else: ?>
            <div class="tab-header">
                <?php if (hasPermission('add_transactions', $userPermissions, $hasPermissionsTable)): ?>
                <a href="add_transaction.php" class="btn btn-add">Add Transaction</a>
                <?php endif; ?>
                
                <form class="search-form" action="" method="get">
                    <input type="hidden" name="tab" value="transactions">
                    <input type="text" name="search_transaction" placeholder="Search transactions..." value="<?php echo isset($_GET['search_transaction']) ? htmlspecialchars($_GET['search_transaction']) : ''; ?>">
                    <button type="submit">Search</button>
                </form>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Description</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    try {
                        // First, check the structure of the transactions table
                        try {
                            // Check if transactions table exists
                            $checkTable = $pdo->query("SHOW TABLES LIKE 'transactions'");
                            if ($checkTable->rowCount() == 0) {
                                // Create transactions table if it doesn't exist
                                $pdo->exec("CREATE TABLE IF NOT EXISTS transactions (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    type VARCHAR(50) NOT NULL,
                                    amount INT NOT NULL,
                                    description TEXT,
                                    product_id INT,
                                    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                )");
                                
                                // Add sample transaction data if products exist
                                $productsExist = $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
                                if ($productsExist > 0) {
                                    // Get first few products for sample data
                                    $products = $pdo->query("SELECT id, name FROM products LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (count($products) > 0) {
                                        $sampleTransactions = [];
                                        $types = ['stock_in', 'stock_out', 'adjustment'];
                                        
                                        foreach ($products as $product) {
                                            $type = $types[array_rand($types)];
                                            $amount = ($type == 'stock_in') ? rand(5, 20) : rand(1, 5);
                                            $description = ucfirst($type) . ' for ' . $product['name'];
                                            
                                            $sampleTransactions[] = [
                                                $type,
                                                $amount,
                                                $description,
                                                $product['id']
                                            ];
                                        }
                                        
                                        $stmt = $pdo->prepare("INSERT INTO transactions (type, amount, description, product_id) VALUES (?, ?, ?, ?)");
                                        foreach ($sampleTransactions as $transaction) {
                                            $stmt->execute($transaction);
                                        }
                                    }
                                }
                            } else {
                                // Check if the table has the required columns
                                $columnsResult = $pdo->query("SHOW COLUMNS FROM transactions");
                                $transactionColumns = [];
                                while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
                                    $transactionColumns[] = $column['Field'];
                                }
                                
                                // Determine the product ID column name
                                $productIdColumn = in_array('product_id', $transactionColumns) ? 'product_id' : 
                                                 (in_array('productID', $transactionColumns) ? 'productID' : 
                                                 (in_array('product', $transactionColumns) ? 'product' : null));
                                
                                // Determine the transaction date column name
                                $transactionDateColumn = in_array('transaction_date', $transactionColumns) ? 'transaction_date' : 
                                                      (in_array('date', $transactionColumns) ? 'date' : 
                                                      (in_array('created_at', $transactionColumns) ? 'created_at' : 'id'));
                                
                                // If we couldn't find essential columns, recreate the table
                                if (!in_array('type', $transactionColumns) || !in_array('amount', $transactionColumns) || !$productIdColumn) {
                                    // Drop and recreate the table
                                    $pdo->exec("DROP TABLE transactions");
                                    $pdo->exec("CREATE TABLE transactions (
                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                        type VARCHAR(50) NOT NULL,
                                        amount INT NOT NULL,
                                        description TEXT,
                                        product_id INT,
                                        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                                    )");
                                    
                                    // Set the column names for the new table
                                    $productIdColumn = 'product_id';
                                    $transactionDateColumn = 'transaction_date';
                                }
                            }
                            
                            // Get search parameter if exists
                            $search = isset($_GET['search_transaction']) ? '%' . $_GET['search_transaction'] . '%' : '';
                            
                            // Determine the product ID column name if not set
                            if (!isset($productIdColumn)) {
                                $columnsResult = $pdo->query("SHOW COLUMNS FROM transactions");
                                $transactionColumns = [];
                                while ($column = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
                                    $transactionColumns[] = $column['Field'];
                                }
                                
                                $productIdColumn = in_array('product_id', $transactionColumns) ? 'product_id' : 
                                                 (in_array('productID', $transactionColumns) ? 'productID' : 
                                                 (in_array('product', $transactionColumns) ? 'product' : 'id'));
                                
                                $transactionDateColumn = in_array('transaction_date', $transactionColumns) ? 'transaction_date' : 
                                                      (in_array('date', $transactionColumns) ? 'date' : 
                                                      (in_array('created_at', $transactionColumns) ? 'created_at' : 'id'));
                            }
                            
                            // Build the query based on the actual table structure
                            $query = "SELECT t.*, p.name as product_name 
                                    FROM transactions t 
                                    LEFT JOIN products p ON t.{$productIdColumn} = p.id";
                            
                            if (!empty($search)) {
                                $query .= " WHERE t.type LIKE :search";
                                if (in_array('description', $transactionColumns)) {
                                    $query .= " OR t.description LIKE :search";
                                }
                                $query .= " OR p.name LIKE :search";
                                $query .= " ORDER BY t.{$transactionDateColumn} DESC";
                                $stmt = $pdo->prepare($query);
                                $stmt->bindParam(':search', $search, PDO::PARAM_STR);
                                $stmt->execute();
                            } else {
                                $query .= " ORDER BY t.{$transactionDateColumn} DESC LIMIT 20";
                                $stmt = $pdo->query($query);
                            }
                        } catch (PDOException $structureError) {
                            // If we encounter any errors, recreate the table
                            $pdo->exec("DROP TABLE IF EXISTS transactions");
                            $pdo->exec("CREATE TABLE transactions (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                type VARCHAR(50) NOT NULL,
                                amount INT NOT NULL,
                                description TEXT,
                                product_id INT,
                                transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                            )");
                            
                            // Use a simple query for now
                            $stmt = $pdo->query("SELECT * FROM transactions LIMIT 0");
                        }
                        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        if (count($transactions) > 0) {
                            foreach ($transactions as $transaction) {
                                echo "<tr>";
                                echo "<td>" . htmlspecialchars($transaction['id']) . "</td>";
                                
                                // Check which column name is used for transaction type
                                $typeColumn = isset($transaction['type']) ? 'type' : 
                                            (isset($transaction['transaction_type']) ? 'transaction_type' : 'type');
                                echo "<td>" . ucfirst(htmlspecialchars(isset($transaction[$typeColumn]) ? $transaction[$typeColumn] : 'Unknown')) . "</td>";
                                
                                // Format amount with proper handling
                                echo "<td>₱" . number_format(isset($transaction['amount']) ? $transaction['amount'] : 0, 2) . "</td>";
                                
                                // Handle description with proper checks
                                echo "<td>" . htmlspecialchars(isset($transaction['description']) ? $transaction['description'] : '') . "</td>";
                                
                                // Handle date with proper checks
                                $dateColumn = isset($transaction['transaction_date']) ? 'transaction_date' : 
                                            (isset($transaction['date']) ? 'date' : 
                                            (isset($transaction['created_at']) ? 'created_at' : null));
                                
                                if ($dateColumn && isset($transaction[$dateColumn])) {
                                    echo "<td>" . date('M d, Y H:i', strtotime($transaction[$dateColumn])) . "</td>";
                                } else {
                                    echo "<td>Unknown date</td>";
                                }

                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5'>No transactions found.</td></tr>";
                        }
                    } catch (PDOException $e) {
                        echo "<tr><td colspan='5'>Error loading transactions: " . $e->getMessage() . "</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
            <?php endif; ?>
        </div>
        
        <!-- Shop Tab -->
        <div id="shop" class="tab-content <?php echo $activeTab === 'shop' ? 'active' : ''; ?>">
            <?php if (!hasPermission('view_products', $userPermissions, $hasPermissionsTable)): ?>
                <div class="permission-message">
                    <p>You do not have permission to view this content.</p>
                </div>
            <?php else: ?>
            <div class="shop-container">
                <div class="products-section">
                    <h2>Available Products</h2>
                    <div class="filter-section">
                        <input type="text" id="product-search" placeholder="Search products..." class="search-input">
                        <select id="category-filter" class="category-filter">
                            <option value="">All Categories</option>
                            <?php
                            try {
                                $stmt = $pdo->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category != '' ORDER BY category");
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    echo "<option value=\"" . htmlspecialchars($row['category']) . "\">" . htmlspecialchars($row['category']) . "</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=\"\">Error loading categories</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="products-grid" id="products-grid">
                        <?php
                        try {
                            $stmt = $pdo->query("SELECT * FROM products WHERE stock_quantity > 0 ORDER BY name");
                            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            if (count($products) > 0) {
                                foreach ($products as $product) {
                                    echo "<div class=\"product-card\" data-id=\"" . $product['id'] . "\" data-category=\"" . htmlspecialchars($product['category']) . "\" data-name=\"" . htmlspecialchars($product['name']) . "\">";
                                    echo "<h3>" . htmlspecialchars($product['name']) . "</h3>";
                                    echo "<p class=\"product-category\">" . htmlspecialchars($product['category']) . "</p>";
                                    echo "<p class=\"product-price\">₱" . number_format($product['price'], 2) . "</p>";
                                    echo "<p class=\"product-stock\">Stock: " . $product['stock_quantity'] . "</p>";
                                    echo "<button class=\"add-to-cart-btn\" style=\"margin-top: 50px\" data-id=\"" . $product['id'] . "\" data-name=\"" . htmlspecialchars($product['name']) . "\" data-price=\"" . $product['price'] . "\">Add to Cart</button>";
                                    echo "</div>";
                                }
                            } else {
                                echo "<p class=\"no-products\">No products available.</p>";
                            }
                        } catch (PDOException $e) {
                            echo "<p class=\"error\">Error loading products: " . $e->getMessage() . "</p>";
                        }
                        ?>
                    </div>
                </div>
                
                <div class="cart-section">
                    <h2>Shopping Cart</h2>
                    <div class="cart-items" id="cart-items">
                        <p class="empty-cart-message">Your cart is empty</p>
                    </div>
                    
                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Subtotal:</span>
                            <span id="cart-subtotal">₱0.00</span>
                        </div>
                        <div class="summary-row">
                            <span>Tax (12%):</span>
                            <span id="cart-tax">₱0.00</span>
                        </div>
                        <div class="summary-row total">
                            <span>Total:</span>
                            <span id="cart-total">₱0.00</span>
                        </div>
                    </div>
                    
                    <button id="checkout-btn" class="checkout-btn" disabled>Checkout</button>
                    <button id="clear-cart-btn" class="clear-cart-btn" disabled>Clear Cart</button>
                </div>
            </div>
            
            <!-- Checkout Modal -->
            <div id="checkout-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Checkout</h2>
                    
                    <div class="checkout-details">
                        <div class="checkout-items">
                            <h3>Order Summary</h3>
                            <div id="checkout-items-list"></div>
                            
                            <div class="checkout-summary">
                                <div class="summary-row">
                                    <span>Subtotal:</span>
                                    <span id="checkout-subtotal">₱0.00</span>
                                </div>
                                <div class="summary-row">
                                    <span>Tax (12%):</span>
                                    <span id="checkout-tax">₱0.00</span>
                                </div>
                                <div class="summary-row total">
                                    <span>Total:</span>
                                    <span id="checkout-total">₱0.00</span>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-section">
                            <h3>Payment</h3>
                            <div class="payment-methods">
                                <div class="payment-method">
                                    <input type="radio" id="payment-cash" name="payment-method" value="cash" checked>
                                    <label for="payment-cash">Cash</label>
                                </div>
                            </div>
                            
                            <div id="cash-payment-details" class="payment-details">
                                <div class="form-group">
                                    <label for="cash-amount">Cash Amount:</label>
                                    <input type="number" id="cash-amount" min="0" step="0.01" placeholder="Enter cash amount">
                                </div>
                                <div class="form-group">
                                    <label for="cash-change">Change:</label>
                                    <input type="text" id="cash-change" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkout-actions">
                        <button id="process-order-btn" class="process-order-btn" disabled>Process Order</button>
                        <button id="cancel-checkout-btn" class="cancel-btn">Cancel</button>
                    </div>
                </div>
            </div>
            
            <!-- Receipt Modal -->
            <div id="receipt-modal" class="modal">
                <div class="modal-content receipt">
                    <span class="close">&times;</span>
                    <div class="receipt-content">
                        <h2>Receipt</h2>
                        <div class="receipt-header">
                            <p><strong>Order #:</strong> <span id="receipt-number"></span></p>
                            <p><strong>Date:</strong> <span id="receipt-date"></span></p>
                        </div>
                        
                        <table class="receipt-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Qty</th>
                                    <th>Price</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody id="receipt-items-body">
                            </tbody>
                        </table>
                        
                        <div class="receipt-summary">
                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span id="receipt-subtotal">₱0.00</span>
                            </div>
                            <div class="summary-row">
                                <span>Tax (12%):</span>
                                <span id="receipt-tax">₱0.00</span>
                            </div>
                            <div class="summary-row total">
                                <span>Total:</span>
                                <span id="receipt-total">₱0.00</span>
                            </div>
                            <div class="summary-row" id="receipt-payment-row">
                                <span>Payment:</span>
                                <span id="receipt-payment">₱0.00</span>
                            </div>
                            <div class="summary-row" id="receipt-change-row">
                                <span>Change:</span>
                                <span id="receipt-change">₱0.00</span>
                            </div>
                        </div>
                        
                        <div class="receipt-footer">
                            <p>Thank you for your purchase!</p>
                        </div>
                        
                        <div class="receipt-actions">
                            <button id="print-receipt-btn" class="btn">Print Receipt</button>
                            <button id="new-order-btn" class="btn">New Order</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab switching
        const tabs = document.querySelectorAll('.tab');
        const tabContents = document.querySelectorAll('.tab-content');
        
        tabs.forEach(tab => {
            tab.addEventListener('click', function(e) {
                if (this.classList.contains('disabled')) {
                    e.preventDefault();
                    alert('You do not have permission to access this tab.');
                }
                
                const tabId = this.getAttribute('data-tab');
                    
                // Update URL with tab parameter
                const url = new URL(window.location.href);
                url.searchParams.set('tab', tabId);
                window.history.pushState({}, '', url);
                
                // Hide all tab contents
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                
                // Remove active class from all tabs
                document.querySelectorAll('.tab').forEach(t => {
                    t.classList.remove('active');
                });
                
                // Show the selected tab content
                document.getElementById(tabId).classList.add('active');
                
                // Add active class to the clicked tab
                this.classList.add('active');
            });
        });
        
        // Auto-hide success message after 5 seconds
        const successMessage = document.querySelector('.success-message');
        if (successMessage) {
            setTimeout(function() {
                successMessage.style.display = 'none';
            }, 5000);
        }
        
        // Check if URL has tab parameter and activate that tab
        const urlParams = new URLSearchParams(window.location.search);
        const tabParam = urlParams.get('tab');
        if (tabParam) {
            const tabToActivate = document.querySelector(`.tab[data-tab="${tabParam}"]`);
            if (tabToActivate) {
                tabToActivate.click();
            }
        }
        
        // Shop tab functionality
        if (document.getElementById('shop')) {
            // Initialize variables
            let cart = [];
            let shopInitialized = false;
            
            // Get DOM elements
            const productSearch = document.getElementById('product-search');
            const categoryFilter = document.getElementById('category-filter');
            const productsGrid = document.getElementById('products-grid');
            const cartItems = document.getElementById('cart-items');
            const cartSubtotal = document.getElementById('cart-subtotal');
            const cartTax = document.getElementById('cart-tax');
            const cartTotal = document.getElementById('cart-total');
            const checkoutBtn = document.getElementById('checkout-btn');
            const clearCartBtn = document.getElementById('clear-cart-btn');
            
            // Get modal elements
            const checkoutModal = document.getElementById('checkout-modal');
            const receiptModal = document.getElementById('receipt-modal');
            const checkoutClose = checkoutModal.querySelector('.close');
            const receiptClose = receiptModal.querySelector('.close');
            const cancelCheckoutBtn = document.getElementById('cancel-checkout-btn');
            const processOrderBtn = document.getElementById('process-order-btn');
            const cashAmount = document.getElementById('cash-amount');
            const cashChange = document.getElementById('cash-change');
            const printReceiptBtn = document.getElementById('print-receipt-btn');
            const newOrderBtn = document.getElementById('new-order-btn');
            
            // Initialize shop functionality when the shop tab is activated
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    if (this.getAttribute('data-tab') === 'shop' && !shopInitialized) {
                        initShopFunctionality();
                        shopInitialized = true;
                    }
                });
            });
            
            // Initialize shop if shop tab is active on page load
            if (tabParam === 'shop') {
                initShopFunctionality();
                shopInitialized = true;
            }
            
            // Function to initialize shop functionality
            function initShopFunctionality() {
                // Add event listeners for product filtering
                productSearch.addEventListener('input', filterProducts);
                categoryFilter.addEventListener('change', filterProducts);
                
                // Add event listeners for add to cart buttons
                const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');
                addToCartButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = parseInt(this.getAttribute('data-id'));
                        const productName = this.getAttribute('data-name');
                        const productPrice = parseFloat(this.getAttribute('data-price'));
                        
                        addToCart(productId, productName, productPrice);
                    });
                });
                
                // Add event listeners for checkout and clear cart buttons
                checkoutBtn.addEventListener('click', openCheckoutModal);
                clearCartBtn.addEventListener('click', clearCart);
                
                // Add event listeners for modal close buttons
                checkoutClose.addEventListener('click', () => checkoutModal.style.display = 'none');
                receiptClose.addEventListener('click', () => receiptModal.style.display = 'none');
                cancelCheckoutBtn.addEventListener('click', () => checkoutModal.style.display = 'none');
                
                // Add event listener for cash amount input
                cashAmount.addEventListener('input', calculateChange);
                
                // Add event listeners for order processing
                processOrderBtn.addEventListener('click', processOrder);
                
                // Add event listeners for receipt actions
                printReceiptBtn.addEventListener('click', () => window.print());
                newOrderBtn.addEventListener('click', () => {
                    receiptModal.style.display = 'none';
                    clearCart();
                });
                
                // Close modals when clicking outside
                window.addEventListener('click', function(event) {
                    if (event.target === checkoutModal) {
                        checkoutModal.style.display = 'none';
                    } else if (event.target === receiptModal) {
                        receiptModal.style.display = 'none';
                    }
                });
            }
            
            // Function to filter products
            function filterProducts() {
                const searchTerm = productSearch.value.toLowerCase();
                const category = categoryFilter.value.toLowerCase();
                
                const productCards = productsGrid.querySelectorAll('.product-card');
                
                productCards.forEach(card => {
                    const productName = card.getAttribute('data-name').toLowerCase();
                    const productCategory = card.getAttribute('data-category').toLowerCase();
                    
                    const matchesSearch = productName.includes(searchTerm);
                    const matchesCategory = category === '' || productCategory === category;
                    
                    if (matchesSearch && matchesCategory) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            }
            
            // Function to add item to cart
            function addToCart(productId, productName, productPrice) {
                // Check if product is already in cart
                const existingItem = cart.find(item => item.id === productId);
                
                if (existingItem) {
                    existingItem.quantity += 1;
                    existingItem.total = existingItem.quantity * existingItem.price;
                } else {
                    cart.push({
                        id: productId,
                        name: productName,
                        price: productPrice,
                        quantity: 1,
                        total: productPrice
                    });
                }
                
                updateCartDisplay();
                updateCartTotals();
            }
            
            // Function to update cart display
            function updateCartDisplay() {
                if (cart.length === 0) {
                    cartItems.innerHTML = '<p class="empty-cart-message">Your cart is empty</p>';
                    checkoutBtn.disabled = true;
                    clearCartBtn.disabled = true;
                    return;
                }
                
                cartItems.innerHTML = '';
                
                cart.forEach(item => {
                    const cartItem = document.createElement('div');
                    cartItem.className = 'cart-item';
                    
                    cartItem.innerHTML = `
                        <div class="item-details">
                            <h4>${item.name}</h4>
                            <p>₱${item.price.toFixed(2)} x ${item.quantity}</p>
                        </div>
                        <div class="item-actions">
                            <span class="item-total">₱${item.total.toFixed(2)}</span>
                            <div class="quantity-controls">
                                <button class="quantity-btn minus" data-id="${item.id}">-</button>
                                <span class="quantity">${item.quantity}</span>
                                <button class="quantity-btn plus" data-id="${item.id}">+</button>
                            </div>
                            <button class="remove-btn" data-id="${item.id}">×</button>
                        </div>
                    `;
                    
                    cartItems.appendChild(cartItem);
                });
                
                // Add event listeners for quantity buttons
                const minusButtons = cartItems.querySelectorAll('.minus');
                const plusButtons = cartItems.querySelectorAll('.plus');
                const removeButtons = cartItems.querySelectorAll('.remove-btn');
                
                minusButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = parseInt(this.getAttribute('data-id'));
                        updateQuantity(productId, -1);
                    });
                });
                
                plusButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = parseInt(this.getAttribute('data-id'));
                        updateQuantity(productId, 1);
                    });
                });
                
                removeButtons.forEach(button => {
                    button.addEventListener('click', function() {
                        const productId = parseInt(this.getAttribute('data-id'));
                        removeFromCart(productId);
                    });
                });
                
                checkoutBtn.disabled = false;
                clearCartBtn.disabled = false;
            }
            
            // Function to update item quantity
            function updateQuantity(productId, change) {
                const item = cart.find(item => item.id === productId);
                
                if (item) {
                    item.quantity += change;
                    
                    if (item.quantity <= 0) {
                        removeFromCart(productId);
                    } else {
                        item.total = item.quantity * item.price;
                        updateCartDisplay();
                        updateCartTotals();
                    }
                }
            }
            
            // Function to remove item from cart
            function removeFromCart(productId) {
                cart = cart.filter(item => item.id !== productId);
                updateCartDisplay();
                updateCartTotals();
            }
            
            // Function to update cart totals
            function updateCartTotals() {
                const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
                const tax = subtotal * 0.12;
                const total = subtotal + tax;
                
                cartSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
                cartTax.textContent = `₱${tax.toFixed(2)}`;
                cartTotal.textContent = `₱${total.toFixed(2)}`;
            }
            
            // Function to clear cart
            function clearCart() {
                cart = [];
                updateCartDisplay();
                updateCartTotals();
            }
            
            // Function to open checkout modal
            function openCheckoutModal() {
                if (cart.length === 0) return;
                
                const checkoutItemsList = document.getElementById('checkout-items-list');
                const checkoutSubtotal = document.getElementById('checkout-subtotal');
                const checkoutTax = document.getElementById('checkout-tax');
                const checkoutTotal = document.getElementById('checkout-total');
                
                // Populate checkout items
                checkoutItemsList.innerHTML = '';
                
                cart.forEach(item => {
                    const itemRow = document.createElement('div');
                    itemRow.className = 'checkout-item';
                    
                    itemRow.innerHTML = `
                        <div class="item-name">${item.name} x ${item.quantity}</div>
                        <div class="item-price">₱${item.total.toFixed(2)}</div>
                    `;
                    
                    checkoutItemsList.appendChild(itemRow);
                });
                
                // Set checkout totals
                const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
                const tax = subtotal * 0.12;
                const total = subtotal + tax;
                
                checkoutSubtotal.textContent = `₱${subtotal.toFixed(2)}`;
                checkoutTax.textContent = `₱${tax.toFixed(2)}`;
                checkoutTotal.textContent = `₱${total.toFixed(2)}`;
                
                // Reset payment fields
                cashAmount.value = '';
                cashChange.value = '';
                processOrderBtn.disabled = true;
                
                // Show checkout modal
                checkoutModal.style.display = 'block';
            }
            
            // Function to calculate change
            function calculateChange() {
                const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
                const tax = subtotal * 0.12;
                const total = subtotal + tax;
                
                const amount = parseFloat(cashAmount.value) || 0;
                const change = amount - total;
                
                if (amount >= total) {
                    cashChange.value = `₱${change.toFixed(2)}`;
                    processOrderBtn.disabled = false;
                } else {
                    cashChange.value = 'Insufficient amount';
                    processOrderBtn.disabled = true;
                }
            }
            
            // Function to process order
            function processOrder() {
                const subtotal = cart.reduce((sum, item) => sum + item.total, 0);
                const tax = subtotal * 0.12;
                const total = subtotal + tax;
                const paymentMethod = 'cash';
                const paymentAmount = parseFloat(cashAmount.value) || 0;
                
                // Prepare order data
                const orderData = {
                    items: cart.map(item => ({
                        product_id: item.id,
                        name: item.name,
                        price: item.price,
                        quantity: item.quantity,
                        total: item.total
                    })),
                    subtotal: subtotal,
                    tax: tax,
                    total: total,
                    payment_method: paymentMethod,
                    payment_amount: paymentAmount
                };
                
                // Send order to server
                fetch('process_order.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(orderData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Close checkout modal
                        checkoutModal.style.display = 'none';
                        
                        // Show receipt
                        showReceipt(orderData, data.sale_id);
                    } else {
                        alert('Error processing order: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error processing order. Please try again.');
                });
            }
            
            // Function to show receipt
            function showReceipt(orderData, saleId) {
                // Close checkout modal
                checkoutModal.style.display = 'none';
                
                // Set receipt details
                document.getElementById('receipt-number').textContent = saleId;
                document.getElementById('receipt-date').textContent = new Date().toLocaleString();
                
                // Populate receipt items
                const receiptItemsBody = document.getElementById('receipt-items-body');
                receiptItemsBody.innerHTML = '';
                
                cart.forEach(item => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${item.name}</td>
                        <td>${item.quantity}</td>
                        <td>₱${item.price.toFixed(2)}</td>
                        <td>₱${item.total.toFixed(2)}</td>
                    `;
                    receiptItemsBody.appendChild(row);
                });
                
                // Set receipt totals
                document.getElementById('receipt-subtotal').textContent = `₱${orderData.subtotal.toFixed(2)}`;
                document.getElementById('receipt-tax').textContent = `₱${orderData.tax.toFixed(2)}`;
                document.getElementById('receipt-total').textContent = `₱${orderData.total.toFixed(2)}`;
                
                // Show payment details for cash payments
                if (orderData.payment_method === 'cash') {
                    document.getElementById('receipt-payment-row').style.display = 'flex';
                    document.getElementById('receipt-change-row').style.display = 'flex';
                    document.getElementById('receipt-payment').textContent = `₱${orderData.payment_amount.toFixed(2)}`;
                    document.getElementById('receipt-change').textContent = `₱${(orderData.payment_amount - orderData.total).toFixed(2)}`;
                } else {
                    document.getElementById('receipt-payment-row').style.display = 'none';
                    document.getElementById('receipt-change-row').style.display = 'none';
                }
                
                // Show receipt modal
                receiptModal.style.display = 'block';
            }
        }
    });
    </script>
</body>
</html>