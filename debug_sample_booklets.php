<?php
// Direct database test for sample_booklets
require_once 'config/database.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Check current database
    $stmt = $pdo->query("SELECT DATABASE() as current_db");
    $current_db = $stmt->fetch()['current_db'];
    echo "<p><strong>Connected to database:</strong> " . htmlspecialchars($current_db) . "</p>";
    
    // Show all tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll();
    echo "<h3>Tables in database:</h3><ul>";
    foreach ($tables as $table) {
        $table_name = array_values($table)[0];
        echo "<li>" . htmlspecialchars($table_name) . "</li>";
    }
    echo "</ul>";
    
    // Check specifically for sample_booklets table
    $stmt = $pdo->query("SHOW TABLES LIKE 'sample_booklets'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ sample_booklets table EXISTS</p>";
        
        // Get count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sample_booklets");
        $count = $stmt->fetch()['count'];
        echo "<p><strong>Record count:</strong> " . $count . "</p>";
        
        // Show all records
        $stmt = $pdo->query("SELECT * FROM sample_booklets ORDER BY id");
        $records = $stmt->fetchAll();
        
        if (!empty($records)) {
            echo "<h3>All Records:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr>";
            foreach (array_keys($records[0]) as $column) {
                echo "<th>" . htmlspecialchars($column) . "</th>";
            }
            echo "</tr>";
            
            foreach ($records as $record) {
                echo "<tr>";
                foreach ($record as $value) {
                    echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
                }
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>Table exists but no records found.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ sample_booklets table does NOT exist</p>";
        
        // Try to create the table
        echo "<p>Attempting to create table...</p>";
        $create_sql = "
            CREATE TABLE sample_booklets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_number VARCHAR(100) NOT NULL UNIQUE,
                customer_name VARCHAR(255) NOT NULL,
                address TEXT NOT NULL,
                email VARCHAR(255) NOT NULL,
                phone VARCHAR(20) NOT NULL,
                product_type ENUM('Demo Kit', 'Demo Kit & Sample Booklet', 'Trial Kit') NOT NULL,
                tracking_number VARCHAR(100) NULL,
                status ENUM('Pending', 'Shipped', 'Delivered') DEFAULT 'Pending',
                date_ordered DATE NOT NULL,
                date_shipped DATE NULL,
                notes TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ";
        
        $pdo->exec($create_sql);
        echo "<p style='color: green;'>✓ Table created successfully!</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><a href='sample_booklets.php'>← Back to Sample Booklets</a></p>";
?>
