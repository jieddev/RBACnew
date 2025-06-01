<?php
/**
 * Helper functions for the RBAC system
 */

/**
 * Authenticate a user with username and password
 * 
 * @param string $username The username
 * @param string $password The password
 * @return array Result with success status and user data if successful
 */
function authenticateUser($username, $password) {
    global $pdo;
    
    // Hardcoded admin account for testing
    if ($username === 'admin' && $password === 'admin123') {
        return [
            'success' => true,
            'user_id' => 1, // Default admin ID
            'role' => 1     // Administrator role
        ];
    }
    
    try {
        // Prepare a select statement
        $sql = "SELECT id, username, password, role FROM users WHERE username = :username";
        $stmt = $pdo->prepare($sql);
        
        // Bind parameters
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        
        // Execute the statement
        $stmt->execute();
        
        // Check if username exists
        if ($stmt->rowCount() == 1) {
            $row = $stmt->fetch();
            
            // Verify the password
            if (password_verify($password, $row['password'])) {
                // Password is correct
                return [
                    'success' => true,
                    'user_id' => $row['id'],
                    'role' => $row['role']
                ];
            }
        }
        
        // Authentication failed
        return ['success' => false];
    } catch (PDOException $e) {
        // Log the error
        error_log("Authentication error: " . $e->getMessage());
        return ['success' => false];
    }
}

/**
 * Check if user has permission for a specific action
 * 
 * @param string $permissionName The permission to check
 * @param array $userPermissions Array of user permissions
 * @param bool $hasPermissionsTable Whether the permissions table exists
 * @return bool True if user has permission, false otherwise
 */
function hasPermission($permissionName, $userPermissions, $hasPermissionsTable) {
    // If no permissions table exists, allow all access
    if (!$hasPermissionsTable) {
        return true;
    }
    
    // Admin always has all permissions
    if (isset($_SESSION['username']) && $_SESSION['username'] === 'admin') {
        return true;
    }
    
    // Check if user has this specific permission
    return in_array($permissionName, $userPermissions);
}

/**
 * Sanitize and validate input data
 * 
 * @param string $data The input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if user is logged in
 * 
 * @return bool True if user is logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Redirect to login page if user is not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: index.php");
        exit;
    }
}
