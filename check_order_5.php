<?php
require_once __DIR__ . '/config/database.php';

echo "Checking Order ID 5:\n";
echo "===================\n";

try {
    $stmt = $pdo->prepare('SELECT id, order_number, customer_name, status, tracking_number FROM sample_booklets WHERE id = 5');
    $stmt->execute();
    $order = $stmt->fetch();
    
    if ($order) {
        echo "Order ID 5: {$order['order_number']}\n";
        echo "Customer: {$order['customer_name']}\n";
        echo "Status: {$order['status']}\n";
        echo "Tracking: " . ($order['tracking_number'] ?: 'None') . "\n";
    } else {
        echo "Order ID 5 not found\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
