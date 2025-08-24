<?php
// Database configuration using MySQL
$host = 'localhost';
$dbname = 'wrap_my_kitchen_crm';
$username = 'root';
$password = '';
$port = 3306;

// For production, you can use environment variables
if (isset($_ENV['DB_HOST'])) {
    $host = $_ENV['DB_HOST'];
}
if (isset($_ENV['DB_NAME'])) {
    $dbname = $_ENV['DB_NAME'];
}
if (isset($_ENV['DB_USER'])) {
    $username = $_ENV['DB_USER'];
}
if (isset($_ENV['DB_PASS'])) {
    $password = $_ENV['DB_PASS'];
}
if (isset($_ENV['DB_PORT'])) {
    $port = $_ENV['DB_PORT'];
}

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    
    // Make PDO available globally
    $GLOBALS['pdo'] = $pdo;
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>