<?php
require_once 'config/database.php';

echo "<h2>Sample Booklets Table Test</h2>";

try {
    // Check if table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'sample_booklets'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table 'sample_booklets' exists</p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE sample_booklets");
        $columns = $stmt->fetchAll();
        echo "<h3>Table Structure:</h3><ul>";
        foreach ($columns as $column) {
            echo "<li>{$column['Field']} - {$column['Type']}</li>";
        }
        echo "</ul>";
        
        // Get record count
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM sample_booklets");
        $count = $stmt->fetch()['count'];
        echo "<p><strong>Total Records:</strong> $count</p>";
        
        // Show last 5 records
        $stmt = $pdo->query("SELECT * FROM sample_booklets ORDER BY created_at DESC LIMIT 5");
        $records = $stmt->fetchAll();
        
        if (!empty($records)) {
            echo "<h3>Last 5 Records:</h3>";
            echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            echo "<tr><th>ID</th><th>Order #</th><th>Customer</th><th>Product</th><th>Status</th><th>Date Ordered</th><th>Created At</th></tr>";
            foreach ($records as $record) {
                echo "<tr>";
                echo "<td>{$record['id']}</td>";
                echo "<td>{$record['order_number']}</td>";
                echo "<td>{$record['customer_name']}</td>";
                echo "<td>{$record['product_type']}</td>";
                echo "<td>{$record['status']}</td>";
                echo "<td>{$record['date_ordered']}</td>";
                echo "<td>{$record['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No records found in the table.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Table 'sample_booklets' does not exist</p>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>
