<?php
require_once __DIR__ . '/config/database.php';

echo "Current Orders in Database:\n";
echo "==========================\n";

try {
    $stmt = $pdo->query('SELECT id, order_number, customer_name, status, tracking_number FROM sample_booklets ORDER BY id DESC LIMIT 10');
    $orders = $stmt->fetchAll();
    
    if (empty($orders)) {
        echo "No orders found in database.\n";
    } else {
        foreach ($orders as $row) {
            echo "Order {$row['order_number']}: {$row['customer_name']} - Status: {$row['status']} - Tracking: " . ($row['tracking_number'] ?: 'None') . "\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
