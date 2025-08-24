<?php
/**
 * Enhanced Automatic Delivery Checker
 * 
 * This script checks all "Shipped" orders and updates delivered ones
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/USPSAPIClient.php';

function checkAndUpdateDeliveries() {
    global $pdo;
    
    $updated_orders = [];
    $checked_orders = 0;
    
    try {
        // Get all shipped orders
        $stmt = $pdo->query("SELECT id, order_number, customer_name, tracking_number FROM sample_booklets WHERE status = 'Shipped' AND tracking_number IS NOT NULL AND tracking_number != ''");
        $shipped_orders = $stmt->fetchAll();
        
        echo "Found " . count($shipped_orders) . " shipped orders to check\n";
        
        $usps_client = new USPSAPIClient();
        
        foreach ($shipped_orders as $order) {
            $checked_orders++;
            echo "Checking order {$order['order_number']} (tracking: {$order['tracking_number']})... ";
            
            try {
                $result = $usps_client->getTrackingStatus($order['tracking_number']);
                
                if ($result && isset($result['status'])) {
                    if ($result['status'] === 'delivered') {
                        // Update to delivered
                        $update_stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered' WHERE id = ?");
                        $update_stmt->execute([$order['id']]);
                        
                        $updated_orders[] = $order['order_number'];
                        echo "✅ DELIVERED (updated)\n";
                    } else {
                        echo "Still " . $result['status'] . "\n";
                    }
                } else {
                    echo "No status returned\n";
                }
                
                // Small delay to be respectful to USPS servers
                usleep(500000); // 0.5 seconds
                
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
        
    } catch (Exception $e) {
        echo "Database error: " . $e->getMessage() . "\n";
        return false;
    }
    
    return [
        'checked' => $checked_orders,
        'updated' => $updated_orders
    ];
}

// Run the check
echo "Enhanced Automatic Delivery Checker\n";
echo "===================================\n";

$result = checkAndUpdateDeliveries();

if ($result) {
    echo "\nSummary:\n";
    echo "--------\n";
    echo "Orders checked: {$result['checked']}\n";
    echo "Orders updated to delivered: " . count($result['updated']) . "\n";
    
    if (!empty($result['updated'])) {
        echo "Updated orders: " . implode(', ', $result['updated']) . "\n";
        echo "\n✅ Refresh your web page to see the updates!\n";
    }
} else {
    echo "Check failed!\n";
}

echo "\nDone!\n";
