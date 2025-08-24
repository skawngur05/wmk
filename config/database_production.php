<?php
// Production Database Configuration
// This file handles different hosting environments automatically

// Enable error logging instead of displaying errors in production
if (!defined('WMK_DEBUG')) {
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    error_reporting(E_ALL);
}

// Default local development settings
$host = 'localhost';
$dbname = 'wrap_my_kitchen_crm';
$username = 'root';
$password = '';
$port = 3306;

// Auto-detect common hosting environments and adjust settings
$server_name = $_SERVER['SERVER_NAME'] ?? '';
$script_path = $_SERVER['SCRIPT_NAME'] ?? '';

// Check if we're on a live hosting environment
if (strpos($server_name, 'pawprintssanctuary.com') !== false || 
    strpos($server_name, 'localhost') === false) {
    
    // Production environment settings
    // You'll need to update these with your actual hosting database credentials
    
    // Common hosting database settings - UPDATE THESE WITH YOUR ACTUAL VALUES
    $host = 'localhost';  // Usually localhost on shared hosting
    $dbname = 'your_database_name';  // Your actual database name from hosting panel
    $username = 'your_db_username';  // Your database username from hosting panel
    $password = 'your_db_password';  // Your database password from hosting panel
    $port = 3306;
    
    // Alternative: Check for cPanel or other hosting environment variables
    if (isset($_SERVER['DB_HOST'])) $host = $_SERVER['DB_HOST'];
    if (isset($_SERVER['DB_NAME'])) $dbname = $_SERVER['DB_NAME'];
    if (isset($_SERVER['DB_USER'])) $username = $_SERVER['DB_USER'];
    if (isset($_SERVER['DB_PASS'])) $password = $_SERVER['DB_PASS'];
    if (isset($_SERVER['DB_PORT'])) $port = $_SERVER['DB_PORT'];
}

// Environment variables (for more advanced hosting)
if (isset($_ENV['DB_HOST'])) $host = $_ENV['DB_HOST'];
if (isset($_ENV['DB_NAME'])) $dbname = $_ENV['DB_NAME'];
if (isset($_ENV['DB_USER'])) $username = $_ENV['DB_USER'];
if (isset($_ENV['DB_PASS'])) $password = $_ENV['DB_PASS'];
if (isset($_ENV['DB_PORT'])) $port = $_ENV['DB_PORT'];

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_TIMEOUT => 30, // 30 second timeout
    ];
    
    $pdo = new PDO($dsn, $username, $password, $options);
    
} catch(PDOException $e) {
    // Log the error instead of displaying it in production
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show a generic error message to users
    if (defined('WMK_DEBUG') && WMK_DEBUG) {
        die("Database connection failed: " . $e->getMessage());
    } else {
        die("Sorry, we're experiencing technical difficulties. Please try again later.");
    }
}
?>
