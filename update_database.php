<?php
// Include database connection
require_once 'includes/config.php';

// Check if the full_name column exists and if first_name and last_name don't exist
try {
    // Check if full_name column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'full_name'");
    $stmt->execute();
    $fullNameExists = $stmt->rowCount() > 0;
    
    // Check if first_name column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'first_name'");
    $stmt->execute();
    $firstNameExists = $stmt->rowCount() > 0;
    
    // Check if last_name column exists
    $stmt = $pdo->prepare("SHOW COLUMNS FROM users LIKE 'last_name'");
    $stmt->execute();
    $lastNameExists = $stmt->rowCount() > 0;
    
    // If full_name exists but first_name and last_name don't, perform the migration
    if ($fullNameExists && !$firstNameExists && !$lastNameExists) {
        // Add first_name and last_name columns
        $pdo->exec("ALTER TABLE users ADD COLUMN first_name VARCHAR(50) NOT NULL DEFAULT '' AFTER email, ADD COLUMN last_name VARCHAR(50) NOT NULL DEFAULT '' AFTER first_name");
        
        // Update existing records to split full_name into first_name and last_name
        $stmt = $pdo->prepare("SELECT id, full_name FROM users");
        $stmt->execute();
        $users = $stmt->fetchAll();
        
        foreach ($users as $user) {
            $nameParts = explode(' ', $user['full_name'], 2);
            $firstName = $nameParts[0];
            $lastName = isset($nameParts[1]) ? $nameParts[1] : '';
            
            $updateStmt = $pdo->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name WHERE id = :id");
            $updateStmt->bindParam(':first_name', $firstName, PDO::PARAM_STR);
            $updateStmt->bindParam(':last_name', $lastName, PDO::PARAM_STR);
            $updateStmt->bindParam(':id', $user['id'], PDO::PARAM_INT);
            $updateStmt->execute();
        }
        
        // Drop the full_name column
        $pdo->exec("ALTER TABLE users DROP COLUMN full_name");
        
        echo "Database updated successfully. The full_name column has been replaced with first_name and last_name columns.";
    } elseif ($firstNameExists && $lastNameExists) {
        echo "Database is already up to date. No changes needed.";
    } else {
        echo "Unexpected database structure. Please check your database manually.";
    }
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
