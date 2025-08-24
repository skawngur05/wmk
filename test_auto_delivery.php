<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/USPSAPIClient.php';

echo "Automatic Delivery Detection Test\n";
echo "=================================\n";

try {
    // 1. Create a test order with a test tracking number
    echo "1. Creating test order with TEST_DELIVERED_001 tracking number...\n";
    
    $stmt = $pdo->prepare("INSERT INTO sample_booklets (order_number, customer_name, address, email, phone, product_type, tracking_number, status, date_ordered, date_shipped) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $test_order_number = 'TEST_' . date('YmdHis');
    $stmt->execute([
        $test_order_number,
        'Test Customer',
        '123 Test St, Test City, TS 12345',
        'test@example.com',
        '555-0123',
        'Demo Kit',
        'TEST_DELIVERED_001',
        'Shipped',
        date('Y-m-d'),
        date('Y-m-d')
    ]);
    
    echo "✓ Test order {$test_order_number} created with tracking TEST_DELIVERED_001\n";
    
    // 2. Check the tracking status
    echo "2. Checking tracking status...\n";
    $usps_client = new USPSAPIClient();
    $result = $usps_client->getTrackingStatus('TEST_DELIVERED_001');
    
    if ($result && $result['status'] === 'delivered') {
        echo "✓ Tracking shows as delivered: {$result['message']}\n";
        
        // 3. Update the order status
        echo "3. Updating order status to delivered...\n";
        $stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered' WHERE order_number = ?");
        $stmt->execute([$test_order_number]);
        
        echo "✓ Order {$test_order_number} updated to Delivered status\n";
        
        // 4. Verify the update
        echo "4. Verifying update...\n";
        $stmt = $pdo->prepare("SELECT status FROM sample_booklets WHERE order_number = ?");
        $stmt->execute([$test_order_number]);
        $order = $stmt->fetch();
        
        if ($order && $order['status'] === 'Delivered') {
            echo "✅ SUCCESS! Automatic delivery detection working correctly!\n";
        } else {
            echo "❌ Failed to update order status\n";
        }
    } else {
        echo "❌ Tracking did not show as delivered\n";
        var_dump($result);
    }
    
    // 5. Clean up test order
    echo "5. Cleaning up test order...\n";
    $stmt = $pdo->prepare("DELETE FROM sample_booklets WHERE order_number = ?");
    $stmt->execute([$test_order_number]);
    echo "✓ Test order cleaned up\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTest completed!\n";
echo "\nTo test with real orders:\n";
echo "1. Go to your website at: http://localhost/wmk/sample_booklets.php\n";
echo "2. Click 'Check Delivery Status' button\n";
echo "3. The system will automatically check all 'Shipped' orders\n";
echo "4. Any delivered packages will be updated to 'Delivered' status\n";
