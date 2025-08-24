<?php
require_once __DIR__ . '/config/database.php';

echo "Quick Delivery Status Update\n";
echo "============================\n";

try {
    // Get order details
    $stmt = $pdo->prepare('SELECT id, order_number, customer_name, status, tracking_number FROM sample_booklets WHERE id = 5');
    $stmt->execute();
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "Order ID 5 not found!\n";
        exit(1);
    }
    
    echo "Order: {$order['order_number']} - {$order['customer_name']}\n";
    echo "Current Status: {$order['status']}\n";
    echo "Tracking: {$order['tracking_number']}\n";
    echo "\n";
    
    if ($order['status'] === 'Delivered') {
        echo "✅ Order is already marked as delivered!\n";
        exit(0);
    }
    
    // Since you confirmed it's delivered on USPS website, update it
    echo "Updating order to 'Delivered' status...\n";
    
    $stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered' WHERE id = 5");
    $result = $stmt->execute();
    
    if ($result) {
        echo "✅ Order {$order['order_number']} successfully updated to 'Delivered' status!\n";
        
        // Verify the update
        $stmt = $pdo->prepare('SELECT status FROM sample_booklets WHERE id = 5');
        $stmt->execute();
        $updated_order = $stmt->fetch();
        echo "✓ Verified: Current status is now '{$updated_order['status']}'\n";
        
        echo "\nNow refresh your web page at http://localhost/wmk/sample_booklets.php\n";
        echo "Order 21507 should now show as 'Delivered'!\n";
    } else {
        echo "❌ Failed to update order status\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\nDone!\n";
