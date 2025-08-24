<?php
/**
 * Test Single Tracking Number
 * 
 * This script tests a single USPS tracking number to check delivery status
 * Used by the test system to verify tracking functionality
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/USPSAPIClient.php';

// Set response header
header('Content-Type: application/json');

// Get tracking number from URL parameter
$tracking_number = $_GET['tracking'] ?? '';

if (empty($tracking_number)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'No tracking number provided'
    ]);
    exit;
}

// Test the tracking number using the USPS API client
try {
    error_log("Testing single tracking number: {$tracking_number}");
    
    $usps_client = new USPSAPIClient();
    $result = $usps_client->getTrackingStatus($tracking_number);
    
    // Return the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Test failed: ' . $e->getMessage()
    ]);
}
?>
