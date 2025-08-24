<?php
// Email Configuration for Sample Booklets System
// Update these settings with your actual email server configuration

// SMTP Settings - Namecheap Hosting
define('SMTP_HOST', 'wrapmykitchen.info');
define('SMTP_PORT', 465);
define('SMTP_SECURE', 'ssl'); // 'tls' or 'ssl'
define('SMTP_AUTH', true);

// Email Credentials - Namecheap Email Account
define('SMTP_USERNAME', 'info@wrapmykitchen.info'); // Your email address
define('SMTP_PASSWORD', 'w)n;yudE^6*C');    // Your email password

// From Email Settings
define('FROM_EMAIL', 'info@wrapmykitchen.info');
define('FROM_NAME', 'Wrap My Kitchen');

// Company Information
define('COMPANY_NAME', 'Wrap My Kitchen');
define('COMPANY_PHONE', '(954) 799-6844');
define('COMPANY_EMAIL', 'info@wrapmykitchen.com');
define('COMPANY_WEBSITE', 'www.wrapmykitchen.com');

/*
 * SETUP INSTRUCTIONS FOR NAMECHEAP HOSTING:
 * 
 * 1. SMTP_HOST should be: mail.yourdomain.com (in this case: mail.wrapmykitchen.info)
 * 2. Use your full email address as SMTP_USERNAME: info@wrapmykitchen.info
 * 3. Use your email account password as SMTP_PASSWORD
 * 4. Port 587 with TLS is recommended for Namecheap
 * 
 * Alternative Namecheap SMTP settings if the above doesn't work:
 * - Port 465 with SSL (change SMTP_PORT to 465 and SMTP_SECURE to 'ssl')
 * - Port 25 with no encryption (not recommended for security)
 * 
 * If you encounter issues:
 * - Verify your email account exists in Namecheap cPanel
 * - Check that the email password is correct
 * - Try port 465 with SSL instead of 587 with TLS
 * - Contact Namecheap support for specific SMTP settings
 * 
 * 5. Test email functionality after configuration
 */
?>
