<?php
require_once __DIR__ . '/config/database.php';

echo "Resetting Order 21507 to 'Shipped' for Testing\n";
echo "==============================================\n";

try {
    // Reset order 21507 to Shipped status
    $stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Shipped' WHERE order_number = '21507'");
    $result = $stmt->execute();
    
    if ($result) {
        echo "✅ Order 21507 reset to 'Shipped' status\n";
        
        // Verify the reset
        $stmt = $pdo->prepare('SELECT order_number, customer_name, status, tracking_number FROM sample_booklets WHERE order_number = "21507"');
        $stmt->execute();
        $order = $stmt->fetch();
        
        if ($order) {
            echo "✓ Verified: Order {$order['order_number']} is now '{$order['status']}'\n";
            echo "Customer: {$order['customer_name']}\n";
            echo "Tracking: {$order['tracking_number']}\n";
            echo "\nNow you can test the 'Check Delivery Status' button on the web page!\n";
        }
    } else {
        echo "❌ Failed to reset order status\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
