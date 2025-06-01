<?php
// Database credentials
$host = 'localhost';
$dbname = 'rbac_db';
$username = 'root';
$password = '';

try {
    // Create connection to MySQL server (without database)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create database if it doesn't exist
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
    echo "Database created or already exists.<br>";
    
    // Connect to the database
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table
    $sql = "CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        role INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Users table created successfully.<br>";
    
    // Create roles table
    $sql = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Roles table created successfully.<br>";
    
    // Create permissions table
    $sql = "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(50) NOT NULL UNIQUE,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    $pdo->exec($sql);
    echo "Permissions table created successfully.<br>";
    
    // Create role_permissions table
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id INT NOT NULL,
        permission VARCHAR(50) NOT NULL,
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        UNIQUE KEY role_permission (role_id, permission)
    )";
    $pdo->exec($sql);
    echo "Role_permissions table created successfully.<br>";
    
    // Check if roles already exist
    $stmt = $pdo->query("SELECT COUNT(*) FROM roles");
    $roleCount = $stmt->fetchColumn();
    
    if ($roleCount == 0) {
        // Insert default roles
        $sql = "INSERT INTO roles (name, description) VALUES
            ('Administrator', 'Full system access'),
            ('Manager', 'Can manage most aspects of the system'),
            ('User', 'Regular user with limited access')";
        $pdo->exec($sql);
        echo "Default roles inserted successfully.<br>";
        
        // Insert default permissions
        $sql = "INSERT INTO permissions (name, description) VALUES
            ('view_users', 'Can view user list'),
            ('add_user', 'Can add new users'),
            ('edit_user', 'Can edit user information'),
            ('delete_user', 'Can delete users'),
            ('view_roles', 'Can view role list'),
            ('add_role', 'Can add new roles'),
            ('edit_role', 'Can edit role information'),
            ('delete_role', 'Can delete roles'),
            ('view_permissions', 'Can view permissions list'),
            ('assign_permissions', 'Can assign permissions to roles')";
        $pdo->exec($sql);
        echo "Default permissions inserted successfully.<br>";
        
        // Assign permissions to roles
        $sql = "INSERT INTO role_permissions (role_id, permission) VALUES
            (1, 'view_users'), (1, 'add_user'), (1, 'edit_user'), (1, 'delete_user'),
            (1, 'view_roles'), (1, 'add_role'), (1, 'edit_role'), (1, 'delete_role'),
            (1, 'view_permissions'), (1, 'assign_permissions'),
            (2, 'view_users'), (2, 'add_user'), (2, 'edit_user'),
            (2, 'view_roles'), (2, 'view_permissions'),
            (3, 'view_users')";
        $pdo->exec($sql);
        echo "Role permissions assigned successfully.<br>";
    } else {
        echo "Roles already exist, skipping default data insertion.<br>";
    }
    
    // Check if admin user exists
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :username");
    $stmt->bindValue(':username', 'admin');
    $stmt->execute();
    $adminExists = $stmt->fetchColumn();
    
    if (!$adminExists) {
        // Insert admin user
        $hashedPassword = password_hash('admin123', PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, first_name, last_name, role) VALUES (:username, :password, :email, :first_name, :last_name, :role)");
        $stmt->bindValue(':username', 'admin');
        $stmt->bindValue(':password', $hashedPassword);
        $stmt->bindValue(':email', 'admin@example.com');
        $stmt->bindValue(':first_name', 'System');
        $stmt->bindValue(':last_name', 'Administrator');
        $stmt->bindValue(':role', 1);
        $stmt->execute();
        echo "Admin user created successfully.<br>";
    } else {
        echo "Admin user already exists.<br>";
    }
    
    echo "<br>Database setup completed successfully!";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
