<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/email_config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/USPSAPIClient.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader if available, otherwise load PHPMailer manually
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    // Manual include of PHPMailer files
    require_once __DIR__ . '/../includes/phpmailer/Exception.php';
    require_once __DIR__ . '/../includes/phpmailer/PHPMailer.php';
    require_once __DIR__ . '/../includes/phpmailer/SMTP.php';
}

// Handle different request types
$is_manual_test = isset($_GET['manual_test']);
$is_manual_check = false;
$input = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $is_manual_check = isset($input['manual_check']) && $input['manual_check'];
}

// Set appropriate response header
if ($is_manual_test) {
    header('Content-Type: text/html');
    echo "<h2>Auto Delivery Check Test</h2>";
    echo "<p>Testing automatic delivery detection with USPS API...</p>";
    echo "<style>body { font-family: Arial, sans-serif; margin: 20px; }</style>";
} else {
    header('Content-Type: application/json');
}

// Check authentication for manual check (optional, can be removed for cron job)
if (!isset($_GET['cron']) && !isset($_GET['manual_test']) && !isLoggedIn()) {
    if ($is_manual_test) {
        echo "<p style='color: red;'>Unauthorized access</p>";
    } else {
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    }
    exit;
}

// Initialize USPS API client
$usps_client = new USPSAPIClient();

/**
 * Check USPS tracking status using official API with fallback to web scraping
 */
function checkUSPSTrackingStatus($tracking_number) {
    global $usps_client, $is_manual_test;
    
    try {
        if ($is_manual_test) {
            echo "<p>→ Using USPS API client to check tracking status...</p>";
        }
        
        $result = $usps_client->getTrackingStatus($tracking_number);
        
        if ($is_manual_test) {
            echo "<p>→ API Response: " . htmlspecialchars(json_encode($result)) . "</p>";
        }
        
        error_log("USPS tracking check for {$tracking_number}: " . json_encode($result));
        
        return $result;
        
    } catch (Exception $e) {
        $error_msg = "USPS tracking check failed for {$tracking_number}: " . $e->getMessage();
        error_log($error_msg);
        
        if ($is_manual_test) {
            echo "<p>→ <span style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</span></p>";
        }
        
        return [
            'status' => 'error',
            'message' => 'Unable to check tracking status: ' . $e->getMessage()
        ];
    }
}

/**
 * Send delivery confirmation email
 */
function sendDeliveryConfirmationEmail($customer_name, $customer_email, $order_number, $tracking_number, $product_type, $delivery_date) {
    global $email_config;
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $email_config['smtp_host'];
        $mail->SMTPAuth = true;
        $mail->Username = $email_config['smtp_username'];
        $mail->Password = $email_config['smtp_password'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $email_config['smtp_port'];
        
        // Recipients
        $mail->setFrom($email_config['from_email'], $email_config['from_name']);
        $mail->addAddress($customer_email, $customer_name);
        $mail->addBCC($email_config['bcc_email']); // BCC to company email
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Package Delivered - Order #' . $order_number;
        
        $delivery_date_formatted = date('F j, Y', strtotime($delivery_date));
        
        $mail->Body = '
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0; font-size: 28px;">📦 Package Delivered!</h1>
            </div>
            
            <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
                <p style="font-size: 18px; margin-bottom: 20px;">Dear ' . htmlspecialchars($customer_name) . ',</p>
                
                <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                    Great news! Your <strong>' . htmlspecialchars($product_type) . '</strong> from Wrap My Kitchen has been successfully delivered on <strong>' . $delivery_date_formatted . '</strong>.
                </p>
                
                <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;">
                    <h3 style="margin: 0 0 15px 0; color: #333;">📋 Delivery Details</h3>
                    <p style="margin: 5px 0;"><strong>Order Number:</strong> #' . htmlspecialchars($order_number) . '</p>
                    <p style="margin: 5px 0;"><strong>Tracking Number:</strong> ' . htmlspecialchars($tracking_number) . '</p>
                    <p style="margin: 5px 0;"><strong>Product:</strong> ' . htmlspecialchars($product_type) . '</p>
                    <p style="margin: 5px 0;"><strong>Delivery Date:</strong> ' . $delivery_date_formatted . '</p>
                </div>
                
                <p style="font-size: 16px; line-height: 1.6; margin: 20px 0;">
                    We hope you enjoy your new kitchen wraps! If you have any questions or need assistance with installation, please don\'t hesitate to contact us.
                </p>
                
                <div style="text-align: center; margin: 30px 0;">
                    <a href="tel:' . $email_config['phone'] . '" style="background: #007bff; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;">📞 Call Us</a>
                    <a href="mailto:' . $email_config['support_email'] . '" style="background: #28a745; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; display: inline-block; margin: 5px;">✉️ Email Support</a>
                </div>
                
                <div style="border-top: 1px solid #ddd; padding-top: 20px; margin-top: 30px; text-align: center; color: #666;">
                    <p style="margin: 5px 0;">Thank you for choosing Wrap My Kitchen!</p>
                    <p style="margin: 5px 0; font-size: 14px;">
                        ' . $email_config['company_address'] . '<br>
                        Phone: ' . $email_config['phone'] . ' | Email: ' . $email_config['support_email'] . '
                    </p>
                </div>
            </div>
        </div>';
        
        $mail->AltBody = 'Dear ' . $customer_name . ',

Your ' . $product_type . ' from Wrap My Kitchen has been delivered on ' . $delivery_date_formatted . '.

Order Details:
- Order Number: #' . $order_number . '
- Tracking Number: ' . $tracking_number . '
- Product: ' . $product_type . '
- Delivery Date: ' . $delivery_date_formatted . '

Thank you for choosing Wrap My Kitchen!

Contact us:
Phone: ' . $email_config['phone'] . '
Email: ' . $email_config['support_email'] . '
' . $email_config['company_address'];
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Failed to send delivery confirmation email: " . $e->getMessage());
        return false;
    }
}

// Main execution
try {
    $updated_orders = 0;
    $total_checked = 0;
    $errors = [];
    
    if ($is_manual_test) {
        echo "<h3>Checking for shipped orders...</h3>";
        
        // Show API configuration status
        require_once '../config/usps_api_config.php';
        $api_status = getUSPSAPIStatus();
        
        echo "<div style='background: " . ($api_status === 'configured' ? '#d4edda' : '#fff3cd') . "; padding: 10px; margin: 10px 0; border-radius: 5px;'>";
        echo "<h4>USPS API Status:</h4>";
        if ($api_status === 'configured') {
            echo "<p style='color: #155724;'>✅ <strong>USPS API is configured</strong> - Using official USPS Tracking API</p>";
        } else {
            echo "<p style='color: #856404;'>⚠️ <strong>USPS API not configured</strong> - Using web scraping fallback</p>";
            echo "<p><small>To use the official USPS API:</small></p>";
            echo "<ol><small>";
            echo "<li>Register at <a href='https://developer.usps.com/' target='_blank'>https://developer.usps.com/</a></li>";
            echo "<li>Create an application to get Client ID and Secret</li>";
            echo "<li>Add credentials to <code>config/usps_api_config.php</code></li>";
            echo "</small></ol>";
        }
        echo "</div>";
    }
    
    // Get all shipped orders that haven't been delivered yet
    $stmt = $pdo->query("SELECT * FROM sample_booklets WHERE status = 'Shipped' AND tracking_number IS NOT NULL AND tracking_number != ''");
    $shipped_orders = $stmt->fetchAll();
    
    if ($is_manual_test) {
        echo "<p>Found " . count($shipped_orders) . " shipped orders to check.</p>";
        if (count($shipped_orders) > 0) {
            echo "<h4>Checking each order:</h4>";
        }
    }
    
    foreach ($shipped_orders as $order) {
        $total_checked++;
        
        if ($is_manual_test) {
            echo "<p><strong>Checking Order #{$order['order_number']}</strong> - Customer: {$order['customer_name']} (Tracking: {$order['tracking_number']})...</p>";
        }
        
        error_log("Checking tracking for order #{$order['order_number']}: {$order['tracking_number']}");
        
        // Check USPS tracking status
        $tracking_result = checkUSPSTrackingStatus($order['tracking_number']);
        
        if ($is_manual_test) {
            echo "<p>→ Result: " . htmlspecialchars($tracking_result['message']) . " (Status: {$tracking_result['status']})</p>";
        }
        
        if ($tracking_result['status'] === 'delivered') {
            // Update order status to delivered
            $delivery_date = $tracking_result['delivery_date'];
            
            $update_sql = "UPDATE sample_booklets SET 
                            status = 'Delivered', 
                            updated_at = CURRENT_TIMESTAMP 
                          WHERE id = ?";
            
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$order['id']]);
            
            // Send delivery confirmation email
            $email_sent = sendDeliveryConfirmationEmail(
                $order['customer_name'],
                $order['email'],
                $order['order_number'],
                $order['tracking_number'],
                $order['product_type'],
                $delivery_date
            );
            
            $updated_orders++;
            
            if ($is_manual_test) {
                echo "<p>→ ✅ <strong>Order #{$order['order_number']} updated to DELIVERED</strong> on {$delivery_date}. Email " . ($email_sent ? 'sent successfully' : 'failed to send') . ".</p>";
            }
            
            error_log("Order #{$order['order_number']} marked as delivered on {$delivery_date}. Email " . ($email_sent ? 'sent' : 'failed'));
            
        } elseif ($tracking_result['status'] === 'error') {
            $errors[] = "Order #{$order['order_number']}: " . $tracking_result['message'];
            error_log("Tracking check failed for order #{$order['order_number']}: " . $tracking_result['message']);
            
            if ($is_manual_test) {
                echo "<p>→ ❌ <span style='color: red;'>Error: " . htmlspecialchars($tracking_result['message']) . "</span></p>";
            }
        }
        
        // Add small delay between requests 
        if (!$is_manual_test || count($shipped_orders) > 1) {
            sleep(1);
        }
    }
    
    $message = "Tracking check completed. Checked: {$total_checked}, Updated to delivered: {$updated_orders}";
    if (!empty($errors)) {
        $message .= ". Errors: " . implode(', ', $errors);
    }
    
    error_log("Auto-delivery check completed: " . $message);
    
    if ($is_manual_test) {
        echo "<h3>Final Results:</h3>";
        echo "<p><strong>Summary:</strong> " . htmlspecialchars($message) . "</p>";
        echo "<p><strong>Orders Checked:</strong> {$total_checked}</p>";
        echo "<p><strong>Orders Updated to Delivered:</strong> {$updated_orders}</p>";
        
        if (!empty($errors)) {
            echo "<h4>Errors Encountered:</h4><ul>";
            foreach ($errors as $error) {
                echo "<li>" . htmlspecialchars($error) . "</li>";
            }
            echo "</ul>";
        }
        
        if ($updated_orders > 0) {
            echo "<p style='color: green; font-weight: bold;'>✅ {$updated_orders} order(s) were successfully updated to delivered status!</p>";
        } else {
            echo "<p style='color: orange;'>📦 No orders were found that needed to be updated to delivered status.</p>";
            echo "<p><em>Note: Orders are only updated if their tracking shows delivery status. For testing purposes, certain tracking numbers are simulated as delivered.</em></p>";
        }
        
        echo "<hr><p><a href='../test_delivery_system.php'>← Back to Test Page</a> | <a href='../sample_booklets.php'>Go to Sample Booklets</a></p>";
    } else {
        echo json_encode([
            'success' => true,
            'message' => $message,
            'checked' => $total_checked,
            'updated' => $updated_orders,
            'errors' => $errors
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Auto-delivery check database error: " . $e->getMessage());
    if ($is_manual_test) {
        echo "<p style='color: red;'><strong>Database Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='../test_delivery_system.php'>← Back to Test Page</a></p>";
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} catch (Exception $e) {
    error_log("Auto-delivery check error: " . $e->getMessage());
    if ($is_manual_test) {
        echo "<p style='color: red;'><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><a href='../test_delivery_system.php'>← Back to Test Page</a></p>";
    } else {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
