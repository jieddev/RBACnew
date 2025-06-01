<?php
// Include database connection
require_once 'includes/config.php';

try {
    // Check if users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        // Get column information
        $stmt = $pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Users table exists with the following columns:<br>";
        foreach ($columns as $column) {
            echo "- " . $column . "<br>";
        }
    } else {
        echo "Users table does not exist.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
