-- Create database
CREATE DATABASE IF NOT EXISTS rbac_db;
USE rbac_db;

-- Create users table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create roles table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create permissions table
CREATE TABLE IF NOT EXISTS permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create role_permissions table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_id INT NOT NULL,
    permission VARCHAR(50) NOT NULL,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    UNIQUE KEY role_permission (role_id, permission)
);

-- Insert default roles
INSERT INTO roles (name, description) VALUES
('Administrator', 'Full system access'),
('Manager', 'Can manage most aspects of the system'),
('User', 'Regular user with limited access');

-- Insert default permissions
INSERT INTO permissions (name, description) VALUES
('view_users', 'Can view user list'),
('add_user', 'Can add new users'),
('edit_user', 'Can edit user information'),
('delete_user', 'Can delete users'),
('view_roles', 'Can view role list'),
('add_role', 'Can add new roles'),
('edit_role', 'Can edit role information'),
('delete_role', 'Can delete roles'),
('view_permissions', 'Can view permissions list'),
('assign_permissions', 'Can assign permissions to roles');

-- Assign permissions to roles
-- Administrator role (all permissions)
INSERT INTO role_permissions (role_id, permission) VALUES
(1, 'view_users'), (1, 'add_user'), (1, 'edit_user'), (1, 'delete_user'),
(1, 'view_roles'), (1, 'add_role'), (1, 'edit_role'), (1, 'delete_role'),
(1, 'view_permissions'), (1, 'assign_permissions');

-- Manager role
INSERT INTO role_permissions (role_id, permission) VALUES
(2, 'view_users'), (2, 'add_user'), (2, 'edit_user'),
(2, 'view_roles'), (2, 'view_permissions');

-- User role
INSERT INTO role_permissions (role_id, permission) VALUES
(3, 'view_users');

-- Insert a default admin user (password: admin123)
INSERT INTO users (username, password, email, first_name, last_name, role) VALUES
('admin', '$2y$10$KgpLEkSVEHO7nNNZEPAqAu8EGV4Jv4ciOGSJMZ0hd3LCMgP3VnUoS', 'admin@example.com', 'System', 'Administrator', 1);
