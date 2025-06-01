<?php
// Include database connection
require_once 'includes/config.php';

try {
    // Check if the users table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() == 0) {
        echo "The users table does not exist. Creating it now...<br>";
        
        // Create the users table with minimal fields
        $sql = "CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            email VARCHAR(100) NOT NULL UNIQUE,
            role INT NOT NULL
        )";
        $pdo->exec($sql);
        echo "Users table created successfully.<br>";
    } else {
        echo "The users table exists.<br>";
    }
    
    // Get the column information for the users table
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Current columns in the users table:</h3>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
    }
    echo "</ul>";
    
    // Check if we need to add a full_name column
    $hasFullName = false;
    foreach ($columns as $column) {
        if ($column['Field'] == 'full_name') {
            $hasFullName = true;
            break;
        }
    }
    
    if (!$hasFullName) {
        echo "Adding full_name column to users table...<br>";
        $pdo->exec("ALTER TABLE users ADD COLUMN full_name VARCHAR(100) NOT NULL DEFAULT ''");
        echo "Full_name column added successfully.<br>";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
