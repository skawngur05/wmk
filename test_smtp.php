<?php
require_once 'config/database.php';
require_once 'config/email_config.php';
require_once 'includes/auth.php';

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader if available, otherwise load PHPMailer manually
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    // Manual include of PHPMailer files
    require_once 'includes/phpmailer/Exception.php';
    require_once 'includes/phpmailer/PHPMailer.php';
    require_once 'includes/phpmailer/SMTP.php';
}

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

$page_title = 'SMTP Test - Wrap My Kitchen';

$test_result = '';
$test_status = '';

// Handle form submission
if ($_POST && isset($_POST['send_test'])) {
    $test_email = trim($_POST['test_email']);
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_result = 'Please enter a valid email address.';
        $test_status = 'error';
    } else {
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
            
            // Enable verbose debug output (optional)
            if (isset($_POST['debug_mode']) && $_POST['debug_mode'] == '1') {
                $mail->SMTPDebug = SMTP::DEBUG_SERVER;
                $mail->Debugoutput = 'html';
            }
            
            // Recipients
            $mail->setFrom(FROM_EMAIL, FROM_NAME);
            $mail->addAddress($test_email);
            $mail->addBCC('infofloridawmk@gmail.com', 'Wrap My Kitchen - Florida'); // BCC copy
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = 'SMTP Test Email - Wrap My Kitchen System';
            
            $mail->Body = "
                <html>
                <head>
                    <style>
                        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                        .header { background-color: #28a745; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                        .content { background-color: #f8f9fa; padding: 20px; border-radius: 0 0 5px 5px; }
                        .success-box { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 5px; }
                        .footer { text-align: center; margin-top: 20px; font-size: 12px; color: #666; }
                    </style>
                </head>
                <body>
                    <div class='container'>
                        <div class='header'>
                            <h1>✅ SMTP Test Successful!</h1>
                        </div>
                        <div class='content'>
                            <div class='success-box'>
                                <h3>🎉 Email Configuration Working!</h3>
                                <p>If you're reading this email, your SMTP configuration is working correctly.</p>
                            </div>
                            
                            <h3>Test Details:</h3>
                            <ul>
                                <li><strong>SMTP Host:</strong> " . SMTP_HOST . "</li>
                                <li><strong>Port:</strong> " . SMTP_PORT . "</li>
                                <li><strong>Security:</strong> " . strtoupper(SMTP_SECURE) . "</li>
                                <li><strong>From Email:</strong> " . FROM_EMAIL . "</li>
                                <li><strong>Test Date:</strong> " . date('Y-m-d H:i:s') . "</li>
                            </ul>
                            
                            <h3>Next Steps:</h3>
                            <p>Your email system is ready for:</p>
                            <ul>
                                <li>Sample booklet tracking notifications</li>
                                <li>Customer communications</li>
                                <li>Order confirmations</li>
                            </ul>
                            
                            <p><strong>Note:</strong> This email was also BCC'd to infofloridawmk@gmail.com for your records.</p>
                        </div>
                        <div class='footer'>
                            <p>This is an automated test email from the Wrap My Kitchen CRM System</p>
                        </div>
                    </div>
                </body>
                </html>
            ";
            
            $mail->AltBody = "SMTP Test Successful! Your email configuration is working correctly. SMTP Host: " . SMTP_HOST . ", Port: " . SMTP_PORT . ", Security: " . strtoupper(SMTP_SECURE) . ". Test Date: " . date('Y-m-d H:i:s');
            
            $mail->send();
            $test_result = "Test email sent successfully to: {$test_email}";
            $test_status = 'success';
            
        } catch (Exception $e) {
            $test_result = "Email could not be sent. Error: {$mail->ErrorInfo}";
            $test_status = 'error';
        }
    }
}

include 'includes/header.php';
?>

<div class="row">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-envelope-open-text me-2"></i>SMTP Configuration Test</h1>
            <a href="sample_booklets.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Sample Booklets
            </a>
        </div>
    </div>
</div>

<!-- Current Configuration Display -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-cogs me-2"></i>Current SMTP Configuration</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>SMTP Host:</strong></td>
                                <td><?php echo SMTP_HOST; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Port:</strong></td>
                                <td><?php echo SMTP_PORT; ?></td>
                            </tr>
                            <tr>
                                <td><strong>Security:</strong></td>
                                <td><?php echo strtoupper(SMTP_SECURE); ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <td><strong>Username:</strong></td>
                                <td><?php echo SMTP_USERNAME; ?></td>
                            </tr>
                            <tr>
                                <td><strong>From Email:</strong></td>
                                <td><?php echo FROM_EMAIL; ?></td>
                            </tr>
                            <tr>
                                <td><strong>From Name:</strong></td>
                                <td><?php echo FROM_NAME; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
                <div class="alert alert-info mb-0">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>BCC Setting:</strong> All test emails will also be sent to infofloridawmk@gmail.com
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Test Results -->
<?php if (!empty($test_result)): ?>
<div class="row mb-4">
    <div class="col-12">
        <div class="alert <?php echo $test_status === 'success' ? 'alert-success' : 'alert-danger'; ?>" role="alert">
            <h4 class="alert-heading">
                <i class="fas <?php echo $test_status === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle'; ?> me-2"></i>
                Test Result
            </h4>
            <p class="mb-0"><?php echo htmlspecialchars($test_result); ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Test Form -->
<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Test Email</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-3">
                        <label for="test_email" class="form-label">Test Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="test_email" name="test_email" 
                               value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>" 
                               placeholder="Enter email address to test SMTP" required>
                        <div class="form-text">Enter your email address to receive a test email</div>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="debug_mode" name="debug_mode" value="1">
                            <label class="form-check-label" for="debug_mode">
                                Enable Debug Mode
                            </label>
                            <div class="form-text">Shows detailed SMTP connection information (useful for troubleshooting)</div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" name="send_test" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i>Send Test Email
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Troubleshooting Guide -->
<div class="row mt-4">
    <div class="col-12">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-question-circle me-2"></i>Troubleshooting Guide</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Common Issues:</h6>
                        <ul>
                            <li><strong>Connection timeout:</strong> Check SMTP host and port</li>
                            <li><strong>Authentication failed:</strong> Verify email and password</li>
                            <li><strong>SSL/TLS errors:</strong> Try different port/security combinations</li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <h6>Namecheap Alternatives:</h6>
                        <ul>
                            <li><strong>Port 587 with TLS:</strong> Most common</li>
                            <li><strong>Port 465 with SSL:</strong> Alternative secure option</li>
                            <li><strong>Port 25:</strong> Unencrypted (not recommended)</li>
                        </ul>
                    </div>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>Need Help?</strong> If tests fail, check your Namecheap cPanel email settings or contact Namecheap support for specific SMTP configuration details.
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
