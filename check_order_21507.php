<?php
require_once 'config/database.php';

// Load PHPMailer if needed
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    require_once 'includes/phpmailer/Exception.php';
    require_once 'includes/phpmailer/PHPMailer.php';
    require_once 'includes/phpmailer/SMTP.php';
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

echo "<h2>Order 21507 Investigation</h2>";
echo "<style>body { font-family: Arial, sans-serif; margin: 20px; } table { border-collapse: collapse; width: 100%; } th, td { border: 1px solid #ddd; padding: 8px; text-align: left; } th { background-color: #f2f2f2; }</style>";

try {
    // Check specific order 21507
    $stmt = $pdo->prepare('SELECT * FROM sample_booklets WHERE order_number = ?');
    $stmt->execute(['21507']);
    $order = $stmt->fetch();
    
    if ($order) {
        echo "<h3>Order #21507 Current Details:</h3>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Value</th></tr>";
        echo "<tr><td>Customer Name</td><td>" . htmlspecialchars($order['customer_name']) . "</td></tr>";
        echo "<tr><td>Email</td><td>" . htmlspecialchars($order['email']) . "</td></tr>";
        echo "<tr><td>Status</td><td><strong style='color: " . ($order['status'] == 'Delivered' ? 'green' : ($order['status'] == 'Shipped' ? 'blue' : 'orange')) . ";'>" . $order['status'] . "</strong></td></tr>";
        echo "<tr><td>Tracking Number</td><td>" . ($order['tracking_number'] ?: '<em>No tracking number</em>') . "</td></tr>";
        echo "<tr><td>Date Ordered</td><td>" . $order['date_ordered'] . "</td></tr>";
        echo "<tr><td>Date Shipped</td><td>" . ($order['date_shipped'] ?: '<em>Not shipped</em>') . "</td></tr>";
        echo "<tr><td>Product Type</td><td>" . $order['product_type'] . "</td></tr>";
        echo "<tr><td>Created</td><td>" . $order['created_at'] . "</td></tr>";
        echo "<tr><td>Updated</td><td>" . $order['updated_at'] . "</td></tr>";
        echo "</table>";
        
        if ($order['status'] != 'Delivered') {
            echo "<h3>Actions Available:</h3>";
            
            if ($order['status'] == 'Shipped' && $order['tracking_number']) {
                echo "<p><strong>This order is shipped with tracking number: " . htmlspecialchars($order['tracking_number']) . "</strong></p>";
                echo "<p>You can:</p>";
                echo "<ol>";
                echo "<li><a href='handlers/auto_delivery_check.php?manual_test=1' target='_blank'>Run automatic delivery check</a> - This will check USPS and update if delivered</li>";
                echo "<li>Manually update to delivered using the form below</li>";
                echo "</ol>";
                
                // Manual update form
                echo "<h4>Manual Update to Delivered:</h4>";
                echo "<form method='POST' action='check_order_21507.php' style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
                echo "<input type='hidden' name='order_id' value='" . $order['id'] . "'>";
                echo "<input type='hidden' name='action' value='mark_delivered'>";
                echo "<p><label>Delivery Date: <input type='date' name='delivery_date' value='" . date('Y-m-d') . "' required></label></p>";
                echo "<p><label><input type='checkbox' name='send_email' checked> Send delivery confirmation email to customer</label></p>";
                echo "<button type='submit' style='background: #28a745; color: white; padding: 8px 16px; border: none; border-radius: 4px;'>Mark as Delivered</button>";
                echo "</form>";
                
            } elseif ($order['status'] == 'Pending') {
                echo "<p><strong>This order is still pending.</strong> You need to ship it first before it can be marked as delivered.</p>";
                echo "<p><a href='sample_booklets.php'>Go to Sample Booklets page to ship this order</a></p>";
            } else {
                echo "<p>Order status: " . $order['status'] . "</p>";
            }
        } else {
            echo "<p style='color: green; font-weight: bold;'>✅ This order is already marked as delivered!</p>";
        }
        
    } else {
        echo "<p style='color: red;'>❌ Order #21507 not found in the database.</p>";
        echo "<p>Please check if the order number is correct.</p>";
    }
    
    // Show all orders for reference
    echo "<hr>";
    echo "<h3>All Orders in System:</h3>";
    $stmt = $pdo->query('SELECT order_number, customer_name, status, tracking_number, date_shipped FROM sample_booklets ORDER BY id DESC');
    $all_orders = $stmt->fetchAll();
    
    if ($all_orders) {
        echo "<table>";
        echo "<tr><th>Order #</th><th>Customer</th><th>Status</th><th>Tracking</th><th>Date Shipped</th></tr>";
        foreach ($all_orders as $ord) {
            $highlight = ($ord['order_number'] == '21507') ? 'background-color: #fff3cd;' : '';
            echo "<tr style='$highlight'>";
            echo "<td><strong>" . htmlspecialchars($ord['order_number']) . "</strong></td>";
            echo "<td>" . htmlspecialchars($ord['customer_name']) . "</td>";
            echo "<td>" . $ord['status'] . "</td>";
            echo "<td>" . ($ord['tracking_number'] ?: '-') . "</td>";
            echo "<td>" . ($ord['date_shipped'] ?: '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No orders found in the system.</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}

// Handle manual delivery update
if ($_POST && isset($_POST['action']) && $_POST['action'] == 'mark_delivered') {
    try {
        $order_id = $_POST['order_id'];
        $delivery_date = $_POST['delivery_date'];
        $send_email = isset($_POST['send_email']);
        
        // Update order to delivered
        $update_stmt = $pdo->prepare("UPDATE sample_booklets SET status = 'Delivered', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $update_stmt->execute([$order_id]);
        
        if ($send_email) {
            // Get order details for email
            $order_stmt = $pdo->prepare("SELECT * FROM sample_booklets WHERE id = ?");
            $order_stmt->execute([$order_id]);
            $order_data = $order_stmt->fetch();
            
            if ($order_data) {
                // Include email config and send delivery email
                require_once 'config/email_config.php';
                
                $mail = new PHPMailer(true);
                
                try {
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
                    $mail->addAddress($order_data['email'], $order_data['customer_name']);
                    $mail->addBCC($email_config['bcc_email']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Package Delivered - Order #' . $order_data['order_number'];
                    
                    $delivery_date_formatted = date('F j, Y', strtotime($delivery_date));
                    
                    $mail->Body = '
                    <div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;">
                        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; color: white; border-radius: 10px 10px 0 0;">
                            <h1 style="margin: 0; font-size: 28px;">📦 Package Delivered!</h1>
                        </div>
                        
                        <div style="background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px;">
                            <p style="font-size: 18px; margin-bottom: 20px;">Dear ' . htmlspecialchars($order_data['customer_name']) . ',</p>
                            
                            <p style="font-size: 16px; line-height: 1.6; margin-bottom: 20px;">
                                Great news! Your <strong>' . htmlspecialchars($order_data['product_type']) . '</strong> from Wrap My Kitchen has been successfully delivered on <strong>' . $delivery_date_formatted . '</strong>.
                            </p>
                            
                            <div style="background: white; padding: 20px; border-radius: 8px; border-left: 4px solid #28a745; margin: 20px 0;">
                                <h3 style="margin: 0 0 15px 0; color: #333;">📋 Delivery Details</h3>
                                <p style="margin: 5px 0;"><strong>Order Number:</strong> #' . htmlspecialchars($order_data['order_number']) . '</p>
                                <p style="margin: 5px 0;"><strong>Tracking Number:</strong> ' . htmlspecialchars($order_data['tracking_number']) . '</p>
                                <p style="margin: 5px 0;"><strong>Product:</strong> ' . htmlspecialchars($order_data['product_type']) . '</p>
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
                    
                    $mail->send();
                    echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                    echo "✅ <strong>Success!</strong> Order #21507 has been marked as delivered and delivery confirmation email sent to " . htmlspecialchars($order_data['customer_name']) . " at " . htmlspecialchars($order_data['email']);
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
                    echo "⚠️ Order updated to delivered, but email failed to send: " . $e->getMessage();
                    echo "</div>";
                }
            }
        } else {
            echo "<div style='background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
            echo "✅ <strong>Success!</strong> Order #21507 has been marked as delivered (no email sent)";
            echo "</div>";
        }
        
        echo "<p><a href='sample_booklets.php'>← Back to Sample Booklets</a> | <a href='check_order_21507.php'>Refresh this page</a></p>";
        
    } catch (Exception $e) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "❌ <strong>Error:</strong> " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }
}
?>

<hr>
<p><a href="sample_booklets.php" style="background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;">← Back to Sample Booklets</a></p>
