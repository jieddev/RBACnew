# RBAC Login System

A Role-Based Access Control (RBAC) system with login functionality.

## Features

- User authentication (login/logout)
- User registration
- Role-based access control
- Dashboard with permission-based feature access
- Secure password hashing

## Setup Instructions

1. **Database Setup**
   - Import the `database.sql` file into your MySQL database using phpMyAdmin or MySQL command line
   - The SQL file will create the necessary database, tables, and default data

2. **Configuration**
   - The database connection is configured in `includes/config.php`
   - Default settings use:
     - Host: localhost
     - Database: rbac_db
     - Username: root
     - Password: (empty)
   - Update these settings if your environment differs

3. **Default Admin Account**
   - Username: admin
   - Password: admin123
   - This account has full system access

## Directory Structure

- `/` - Root directory with main pages
- `/css` - Stylesheets
- `/includes` - Configuration and helper functions

## Pages

- `index.php` - Login page
- `register.php` - User registration
- `dashboard.php` - Main dashboard after login
- `logout.php` - Handles user logout

## Security Features

- Password hashing using PHP's password_hash() function
- Input sanitization
- Session-based authentication
- PDO for database interactions with prepared statements

## Requirements

- PHP 7.0 or higher
- MySQL 5.6 or higher
- Web server (Apache/Nginx)
