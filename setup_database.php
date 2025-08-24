<?php
// Simple database and table setup checker
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';

echo "<h2>Database Setup Checker</h2>";

try {
    // Get PDO from global scope
    global $pdo;
    if (!isset($pdo)) {
        $pdo = $GLOBALS['pdo'] ?? null;
    }
    
    if (!$pdo) {
        die("❌ PDO connection not available");
    }
    
    echo "<p>✅ Database connection successful</p>";
    
    // Check for users table
    $stmt = $pdo->query("SHOW TABLES LIKE 'users'");
    if ($stmt->rowCount() > 0) {
        echo "<p>✅ Users table exists</p>";
        
        // Check users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $result = $stmt->fetch();
        $userCount = $result['count'];
        echo "<p>User count: $userCount</p>";
        
        if ($userCount == 0) {
            echo "<p>⚠️ No users found. Creating default users...</p>";
            
            // Create default users
            $defaultUsers = [
                ['kim', 'password', 'Kim Smith'],
                ['patrick', 'password', 'Patrick Johnson'], 
                ['lina', 'password', 'Lina Brown']
            ];
            
            foreach ($defaultUsers as $user) {
                $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
                $stmt->execute([$user[0], $hashedPassword, $user[2]]);
                echo "<p>✅ Created user: {$user[0]}</p>";
            }
        } else {
            echo "<p>✅ Users found in database</p>";
            
            // List users
            $stmt = $pdo->query("SELECT username, full_name FROM users");
            $users = $stmt->fetchAll();
            echo "<ul>";
            foreach ($users as $user) {
                echo "<li>" . htmlspecialchars($user['username']) . " - " . htmlspecialchars($user['full_name']) . "</li>";
            }
            echo "</ul>";
        }
        
    } else {
        echo "<p>❌ Users table does not exist. Creating table...</p>";
        
        // Create users table
        $createTable = "
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(50) UNIQUE NOT NULL,
                password VARCHAR(255) NOT NULL,
                full_name VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ";
        
        $pdo->exec($createTable);
        echo "<p>✅ Users table created</p>";
        
        // Create default users
        $defaultUsers = [
            ['kim', 'password', 'Kim Smith'],
            ['patrick', 'password', 'Patrick Johnson'], 
            ['lina', 'password', 'Lina Brown']
        ];
        
        foreach ($defaultUsers as $user) {
            $hashedPassword = password_hash($user[1], PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name) VALUES (?, ?, ?)");
            $stmt->execute([$user[0], $hashedPassword, $user[2]]);
            echo "<p>✅ Created user: {$user[0]}</p>";
        }
    }
    
    echo "<h3>✅ Setup Complete!</h3>";
    echo "<p><a href='login.php'>Go to Login Page</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h2, h3 { color: #333; }
p { margin: 5px 0; }
ul { margin: 10px 0 10px 20px; }
a { color: #007bff; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
