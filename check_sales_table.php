<?php
include 'includes/config.php';

try {
    // Check if sales table exists
    $checkTable = $pdo->query("SHOW TABLES LIKE 'sales'");
    if ($checkTable->rowCount() > 0) {
        // Get column information
        $columns = $pdo->query("SHOW COLUMNS FROM sales")->fetchAll(PDO::FETCH_ASSOC);
        echo "<h2>Sales Table Structure:</h2>";
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
    } else {
        echo "Sales table does not exist.";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
