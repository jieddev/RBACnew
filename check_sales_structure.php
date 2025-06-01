<?php
// Include database connection
require_once 'includes/config.php';

try {
    // Check sales table structure
    $stmt = $pdo->query("SHOW COLUMNS FROM sales");
    echo "<h3>Sales Table Structure:</h3>";
    echo "<pre>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        print_r($row);
    }
    echo "</pre>";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
