<?php
require_once '../config/database.php';
require_once '../config/email_config.php';
require_once '../includes/auth.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader if available, otherwise load PHPMailer manually
if (file_exists('../vendor/autoload.php')) {
    require '../vendor/autoload.php';
} else {
    // Manual include of PHPMailer files
    require_once '../includes/phpmailer/Exception.php';
    require_once '../includes/phpmailer/PHPMailer.php';
    require_once '../includes/phpmailer/SMTP.php';
}

// Set JSON response header
header('Content-Type: application/json');

// Check authentication
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

function sendTrackingEmail($customer_name, $customer_email, $order_number, $tracking_number, $product_type, $order_data) {
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = SMTP_AUTH;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($customer_email, $customer_name);
        $mail->addBCC('infofloridawmk@gmail.com', 'Wrap My Kitchen - Florida'); // BCC copy to company Gmail
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = "Your Wrap My Kitchen Order Has Shipped! - Order #{$order_number}";
        
        $tracking_url = "https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=2&text28777=&tLabels=" . urlencode($tracking_number);
        
        // Define company variables for email template
        $company_email = COMPANY_EMAIL;
        $company_phone = COMPANY_PHONE;
        $company_website = COMPANY_WEBSITE;
        
        // Define company variables for email template
        $company_email = COMPANY_EMAIL;
        $company_phone = COMPANY_PHONE;
        $company_website = COMPANY_WEBSITE;
        
        $mail->Body = '
<html>
<head>
    <style>
        body { 
            font-family: \'Segoe UI\', Tahoma, Geneva, Verdana, sans-serif; 
            line-height: 1.6; 
            color: #2c3e50; 
            margin: 0; 
            padding: 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }
        .email-wrapper {
            padding: 40px 20px;
        }
        .container { 
            max-width: 650px; 
            margin: 0 auto; 
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }
        .logo-header { 
            text-align: center;
            padding: 40px 30px 30px;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-bottom: 1px solid #e9ecef;
        }
        .logo-header h1 {
            margin: 0;
            font-size: 42px;
            font-weight: 700;
            color: #2c3e50;
            letter-spacing: -1px;
        }
        .logo-header .kitchen {
            color: #28a745;
            font-weight: 800;
        }
        .logo-header .tagline {
            margin: 8px 0 0;
            font-size: 14px;
            color: #6c757d;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .green-banner { 
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white; 
            padding: 25px 30px; 
            text-align: center;
            font-size: 20px;
            font-weight: 600;
            box-shadow: inset 0 -2px 4px rgba(0, 0, 0, 0.1);
        }
        .content { 
            padding: 40px 30px;
        }
        .notification-text {
            font-size: 16px;
            color: #495057;
            margin-bottom: 25px;
            line-height: 1.7;
        }
        .order-header {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 20px;
            border-radius: 8px;
            margin: 25px 0;
            border-left: 4px solid #28a745;
        }
        .order-header h3 {
            margin: 0 0 5px;
            color: #2c3e50;
            font-size: 18px;
            font-weight: 600;
        }
        .order-header .order-date {
            color: #6c757d;
            font-size: 14px;
        }
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
        }
        .order-table thead tr {
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: white;
        }
        .order-table th,
        .order-table td {
            padding: 15px 18px;
            text-align: left;
            border-bottom: 1px solid #f1f3f4;
        }
        .order-table th {
            font-weight: 600;
            font-size: 14px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .order-table td {
            color: #495057;
        }
        .order-table td:last-child,
        .order-table th:last-child {
            text-align: right;
        }
        .order-table .total-row {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            font-weight: 600;
            color: #2c3e50;
            font-size: 16px;
        }
        .tracking-section {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 12px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 8px 25px rgba(40, 167, 69, 0.25);
        }
        .tracking-section h3 {
            margin: 0 0 15px;
            font-size: 20px;
            font-weight: 600;
        }
        .tracking-number { 
            font-size: 24px; 
            font-weight: 700; 
            background-color: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 15px 25px;
            border-radius: 8px;
            margin: 20px 0;
            letter-spacing: 2px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        .button { 
            display: inline-block; 
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            color: #28a745; 
            padding: 15px 35px; 
            text-decoration: none; 
            font-weight: 600;
            margin: 20px 0;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            transition: all 0.3s ease;
            font-size: 16px;
            box-shadow: 0 4px 15px rgba(255, 255, 255, 0.2);
        }
        .button:hover {
            background: white;
            color: #28a745;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 255, 255, 0.3);
        }
        .address-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin: 40px 0;
        }
        .address-column {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 25px;
            border-radius: 12px;
            border-left: 4px solid #28a745;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        .address-column h3 {
            color: #2c3e50;
            margin: 0 0 15px;
            font-size: 16px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .address-column p {
            margin: 5px 0;
            line-height: 1.5;
            color: #495057;
        }
        .address-column a {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        .app-section {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            color: white;
            padding: 25px;
            border-radius: 12px;
            text-align: center;
            margin: 30px 0;
        }
        .app-section a {
            color: #28a745;
            font-weight: 600;
            text-decoration: none;
        }
        .footer { 
            background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);
            color: #ecf0f1;
            text-align: center; 
            padding: 35px 30px;
        }
        .footer h4 {
            margin: 0 0 15px;
            color: white;
            font-size: 18px;
            font-weight: 600;
        }
        .footer p {
            margin: 8px 0;
            opacity: 0.9;
        }
        .footer a {
            color: #28a745;
            text-decoration: none;
            font-weight: 500;
        }
        @media (max-width: 650px) {
            .email-wrapper {
                padding: 20px 10px;
            }
            .container {
                border-radius: 0;
            }
            .logo-header {
                padding: 30px 20px 20px;
            }
            .logo-header h1 {
                font-size: 32px;
            }
            .content {
                padding: 30px 20px;
            }
            .address-section {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            .order-table th,
            .order-table td {
                padding: 12px 8px;
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="container">
            <div class="logo-header">
                <h1>WrapMy<span class="kitchen">Kitchen</span></h1>
                <div class="tagline">Kitchen Transformation Specialists</div>
            </div>
            
            <div class="green-banner">
                📦 Your Order Has Been Shipped!
            </div>
            
            <div class="content">
                <div class="notification-text">
                    Great news! Your order has been processed and shipped. We\'re excited for you to start your kitchen transformation journey.
                </div>
                
                <div class="order-header">
                    <h3>Order #' . htmlspecialchars($order_number) . '</h3>
                    <div class="order-date">Shipped on ' . date('F j, Y') . '</div>
                </div>
                
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Description</th>
                            <th>Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>' . htmlspecialchars($product_type) . '</strong></td>
                            <td>Sample Booklet</td>
                            <td>1</td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="tracking-section">
                    <h3> Track Your Shipment</h3>
                    <p>Your package is on its way! Use the tracking number below to monitor your shipment\'s progress.</p>
                    <div class="tracking-number">' . htmlspecialchars($tracking_number) . '</div>
                    <a href="' . $tracking_url . '" class="button" target="_blank">🔍 Track Package</a>
                    <p style="margin: 15px 0 0; font-size: 14px; opacity: 0.9;">
                        Estimated delivery: 2-5 business days via USPS
                    </p>
                </div>
                
                <div class="address-section">
                    <div class="address-column">
                        <h3>Billing Address</h3>
                        <p><strong>' . htmlspecialchars($customer_name) . '</strong></p>
                        <p>' . nl2br(htmlspecialchars($order_data['address'])) . '</p>
                        <p><a href="mailto:' . htmlspecialchars($customer_email) . '">' . htmlspecialchars($customer_email) . '</a></p>
                    </div>
                    
                    <div class="address-column">
                        <h3>Shipping Address</h3>
                        <p><strong>' . htmlspecialchars($customer_name) . '</strong></p>
                        <p>' . nl2br(htmlspecialchars($order_data['address'])) . '</p>
                        <p><a href="mailto:' . htmlspecialchars($customer_email) . '">' . htmlspecialchars($customer_email) . '</a></p>
                    </div>
                </div>
                
                <div class="app-section">
                    <p>Need help with installation? <a href="' . $company_website . '/support">Visit our support center</a> for guides and tutorials.</p>
                </div>
            </div>
            
            <div class="footer">
                <h4>Wrap My Kitchen Support</h4>
                <p>Questions about your order? We\'re here to help!</p>
                <p><strong> Phone:</strong> ' . $company_phone . '</p>
                <p><strong> Email:</strong> <a href="mailto:' . $company_email . '">' . $company_email . '</a></p>
                <p><strong> Website:</strong> <a href="' . $company_website . '">' . $company_website . '</a></p>
                <p style="margin-top: 20px; font-size: 13px; opacity: 0.8;">
                    © 2025 Wrap My Kitchen USA. All rights reserved.<br>
                    You received this email because you placed an order with us.
                </p>
            </div>
        </div>
    </div>
</body>
</html>
        ';
        
        // Plain text version
        $mail->AltBody = "
WRAP MY KITCHEN - Kitchen Transformation Specialists

 YOUR ORDER HAS BEEN SHIPPED!

Great news! Your Wrap My Kitchen order has been processed and shipped. We're excited for you to start your kitchen transformation journey.

ORDER #{$order_number}
Shipped on " . date('F j, Y') . "

PRODUCT DETAILS:
- {$product_type}
- Sample Booklet - Premium Collection
- Quantity: 1

 TRACK YOUR SHIPMENT:
Your package is on its way! Use the tracking number below to monitor your shipment's progress.

Tracking Number: {$tracking_number}
Track your package: {$tracking_url}

Estimated delivery: 2-5 business days via USPS

BILLING & SHIPPING ADDRESS:
{$customer_name}
" . $order_data['address'] . "
{$customer_email}

 PRO TIP: Take before photos of your kitchen to see the amazing transformation after installation!

WRAP MY KITCHEN SUPPORT:
Questions about your order? We're here to help!
 Phone: " . COMPANY_PHONE . "
 Email: " . COMPANY_EMAIL . "
 Website: " . COMPANY_WEBSITE . "

© 2025 Wrap My Kitchen USA. All rights reserved.
You received this email because you placed an order with us.
        ";
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }
    
    // Get and validate input data
    $order_id = $_POST['order_id'] ?? '';
    $tracking_number = trim($_POST['tracking_number'] ?? '');
    $date_shipped = $_POST['date_shipped'] ?? date('Y-m-d');
    
    // Validation
    if (empty($order_id)) {
        throw new Exception('Order ID is required');
    }
    
    if (empty($tracking_number)) {
        throw new Exception('Tracking number is required');
    }
    
    if (!DateTime::createFromFormat('Y-m-d', $date_shipped)) {
        throw new Exception('Invalid ship date format');
    }
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM sample_booklets WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        throw new Exception('Order not found');
    }
    
    if ($order['status'] !== 'Pending') {
        throw new Exception('Order has already been shipped');
    }
    
    // Update order with tracking information
    $sql = "UPDATE sample_booklets SET 
                tracking_number = ?, 
                status = 'Shipped', 
                date_shipped = ?
            WHERE id = ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$tracking_number, $date_shipped, $order_id]);
    
    // Send tracking email to customer
    $email_sent = sendTrackingEmail(
        $order['customer_name'],
        $order['email'],
        $order['order_number'],
        $tracking_number,
        $order['product_type'],
        $order
    );
    
    $message = 'Order shipped successfully';
    if ($email_sent) {
        $message .= ' and customer has been notified via email';
    } else {
        $message .= ' but email notification failed';
        error_log("Failed to send tracking email for order #{$order['order_number']}");
    }
    
    error_log("Sample booklet shipped: Order #{$order['order_number']}, Tracking: $tracking_number");
    
    echo json_encode(['success' => true, 'message' => $message]);
    
} catch (PDOException $e) {
    error_log("Shipping handler database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
} catch (Exception $e) {
    error_log("Shipping handler error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
