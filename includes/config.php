<?php
/**
 * Database Configuration
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'rbac_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Attempt to connect to the database
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode to associative array
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("ERROR: Could not connect to the database. " . $e->getMessage());
}
