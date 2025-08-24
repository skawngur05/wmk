<?php
/**
 * Command Line Test for USPS API
 * Usage: php test_tracking_cli.php [tracking_number]
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/USPSAPIClient.php';

// Get tracking number from command line argument
$tracking_number = $argv[1] ?? '';

if (empty($tracking_number)) {
    echo "Usage: php test_tracking_cli.php [tracking_number]\n";
    echo "Example: php test_tracking_cli.php TEST_DELIVERED_001\n";
    exit(1);
}

echo "Testing USPS API with tracking number: {$tracking_number}\n";
echo "========================================\n";

try {
    $usps_client = new USPSAPIClient();
    
    // Test the USPS API configuration
    echo "✓ USPS API Client initialized\n";
    
    // Check if API is configured
    if (isUSPSAPIConfigured()) {
        echo "✓ USPS API credentials are configured\n";
    } else {
        echo "⚠️ USPS API not configured, will use fallback method\n";
    }
    
    // Get tracking status
    $result = $usps_client->getTrackingStatus($tracking_number);
    
    echo "\nTracking Results:\n";
    echo "================\n";
    
    if ($result) {
        echo "Status: " . ($result['status'] ?? 'Unknown') . "\n";
        echo "Details: " . ($result['details'] ?? 'No details') . "\n";
        echo "Location: " . ($result['location'] ?? 'Unknown') . "\n";
        echo "Date: " . ($result['date'] ?? $result['delivery_date'] ?? 'Unknown') . "\n";
        echo "Message: " . ($result['message'] ?? 'No message') . "\n";
        
        if (isset($result['status']) && $result['status'] === 'delivered') {
            echo "✅ PACKAGE IS DELIVERED!\n";
        } else {
            echo "📦 Package is not yet delivered (Status: " . ($result['status'] ?? 'Unknown') . ")\n";
        }
    } else {
        echo "❌ Failed to get tracking information\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\nTest completed.\n";
