<?php
/**
 * Web Interface Delivery Check Handler
 * 
 * This handles delivery status checks from the web interface
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/USPSAPIClient.php';

// Set JSON response header
header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    // Check if sample_booklets table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'sample_booklets'");
    if ($stmt->rowCount() == 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Sample booklets table does not exist. Please visit the main page first to create it.'
        ]);
        exit;
    }
    
    // Get all shipped orders
    $stmt = $pdo->query("SELECT id, order_number, customer_name, tracking_number FROM sample_booklets WHERE status = 'Shipped' AND tracking_number IS NOT NULL AND tracking_number != ''");
    $shipped_orders = $stmt->fetchAll();
    
    $updated_orders = [];
    $checked_count = 0;
    
    if (empty($shipped_orders)) {
        echo json_encode([
            'success' => true,
            'message' => 'No shipped orders found to check',
            'details' => ['checked' => 0, 'updated' => 0]
        ]);
        exit;
    }
    
    $usps_client = new USPSAPIClient();
    
    foreach ($shipped_orders as $order) {
        $checked_count++;
        
        try {
            $result = $usps_client->getTrackingStatus($order['tracking_number']);
            
            if ($result && isset($result['status']) && $result['status'] === 'delivered') {
                // Update to delivered
                $update_stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered' WHERE id = ?");
                $update_stmt->execute([$order['id']]);
                
                $updated_orders[] = $order['order_number'];
                error_log("Order {$order['order_number']} updated to Delivered status");
            }
            
        } catch (Exception $e) {
            error_log("Error checking tracking for order {$order['order_number']}: " . $e->getMessage());
        }
    }
    
    $message = "Checked $checked_count orders. ";
    if (count($updated_orders) > 0) {
        $message .= count($updated_orders) . " orders updated to delivered: " . implode(', ', $updated_orders);
    } else {
        $message .= "No deliveries detected.";
    }
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'details' => [
            'checked' => $checked_count,
            'updated' => count($updated_orders),
            'updated_orders' => $updated_orders
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Delivery check error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    echo json_encode([
        'success' => false,
        'message' => 'Error checking delivery status: ' . $e->getMessage(),
        'debug' => [
            'error' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]
    ]);
}
