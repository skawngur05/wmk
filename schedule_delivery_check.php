<?php
/**
 * Scheduled Delivery Check Script
 * 
 * This script should be run periodically (e.g., every hour) via cron job or Windows Task Scheduler
 * to automatically check for delivered packages and update order status.
 * 
 * Cron job example (runs every hour):
 * 0 * * * * php /path/to/wmk/schedule_delivery_check.php
 * 
 * Windows Task Scheduler example:
 * Program: php.exe
 * Arguments: C:\xampp\htdocs\wmk\schedule_delivery_check.php
 * Trigger: Daily, repeat every 1 hour
 */

// Set script timeout to prevent hanging
set_time_limit(300); // 5 minutes max

// Include required files
require_once 'config/database.php';

// Log start of scheduled check
error_log("=== SCHEDULED DELIVERY CHECK STARTED ===");

try {
    // Get current timestamp
    $start_time = microtime(true);
    
    // Check how many shipped orders we have
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM sample_booklets WHERE status = 'Shipped' AND tracking_number IS NOT NULL AND tracking_number != ''");
    $shipped_count = $stmt->fetchColumn();
    
    error_log("Found {$shipped_count} shipped orders to check");
    
    if ($shipped_count == 0) {
        error_log("No shipped orders found. Exiting.");
        exit(0);
    }
    
    // Call the auto delivery check handler
    $handler_url = __DIR__ . '/handlers/auto_delivery_check.php';
    
    if (!file_exists($handler_url)) {
        error_log("Auto delivery check handler not found at: {$handler_url}");
        exit(1);
    }
    
    // Set environment variable to indicate this is a cron run
    $_GET['cron'] = '1';
    
    // Capture output
    ob_start();
    include $handler_url;
    $output = ob_get_clean();
    
    // Try to parse JSON response
    $result = json_decode($output, true);
    
    if ($result && isset($result['success']) && $result['success']) {
        $end_time = microtime(true);
        $execution_time = round($end_time - $start_time, 2);
        
        $message = "Delivery check completed successfully in {$execution_time}s. ";
        $message .= "Checked: {$result['checked']}, Updated: {$result['updated']}";
        
        if (!empty($result['errors'])) {
            $message .= ", Errors: " . count($result['errors']);
        }
        
        error_log($message);
        
        // If orders were updated, log details
        if ($result['updated'] > 0) {
            error_log("✅ {$result['updated']} order(s) were automatically updated to delivered status!");
        }
        
    } else {
        error_log("Delivery check failed or returned invalid response: " . $output);
        exit(1);
    }
    
} catch (Exception $e) {
    error_log("Scheduled delivery check error: " . $e->getMessage());
    exit(1);
}

error_log("=== SCHEDULED DELIVERY CHECK COMPLETED ===");
exit(0);
?>
