<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/USPSAPIClient.php';

echo "Manual Delivery Check and Update for Order ID 5\n";
echo "===============================================\n";

try {
    // Get order details
    $stmt = $pdo->prepare('SELECT * FROM sample_booklets WHERE id = 5');
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
    
    if (empty($order['tracking_number'])) {
        echo "❌ No tracking number found for this order\n";
        exit(1);
    }
    
    // Test the tracking
    echo "Checking tracking status with USPS...\n";
    $usps_client = new USPSAPIClient();
    $result = $usps_client->getTrackingStatus($order['tracking_number']);
    
    echo "Tracking result:\n";
    var_dump($result);
    
    // Since you mentioned it shows as delivered on USPS website,
    // let's provide an option to manually mark it as delivered
    echo "\n";
    echo "Based on your confirmation that this package shows as delivered on USPS website,\n";
    echo "would you like to manually update this order to 'Delivered' status? (y/n): ";
    
    $handle = fopen("php://stdin", "r");
    $line = trim(fgets($handle));
    fclose($handle);
    
    if (strtolower($line) === 'y' || strtolower($line) === 'yes') {
        // Update the order status
        $stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered' WHERE id = 5");
        $result = $stmt->execute();
        
        if ($result) {
            echo "✅ Order {$order['order_number']} successfully updated to 'Delivered' status!\n";
            
            // Verify the update
            $stmt = $pdo->prepare('SELECT status FROM sample_booklets WHERE id = 5');
            $stmt->execute();
            $updated_order = $stmt->fetch();
            echo "✓ Verified: Current status is now '{$updated_order['status']}'\n";
        } else {
            echo "❌ Failed to update order status\n";
        }
    } else {
        echo "Order status not changed.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nDone!\n";
