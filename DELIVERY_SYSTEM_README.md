# Automatic Delivery Detection System

## Overview
The Wrap My Kitchen sample booklets system now includes automatic delivery detection that monitors USPS tracking numbers and automatically updates order status from "Shipped" to "Delivered" when packages are delivered.

## How It Works

### 1. Automatic Background Checking
- The system automatically checks for deliveries every hour when the sample booklets page is loaded
- Uses intelligent background processing to avoid slowing down the user interface
- Checks all orders with status "Shipped" that have valid tracking numbers

### 2. USPS Integration
- Connects directly to USPS tracking website to check delivery status
- Uses advanced pattern matching to detect delivery confirmations
- Extracts delivery dates when available
- Handles various delivery status messages (delivered to door, mailbox, recipient, etc.)

### 3. Automatic Updates
- Orders are automatically updated from "Shipped" to "Delivered" when delivery is confirmed
- Customers receive automatic email notifications when delivery is detected
- System maintains audit trail of all status changes

## Features

### Manual Testing
- **Test Page**: Access via `test_delivery_system.php`
- **Individual Order Check**: Test specific tracking numbers
- **Bulk Check**: Check all shipped orders at once
- **System Status**: View current system status and statistics

### Automatic Scheduling
- **Hourly Auto-Check**: Runs automatically when users visit the sample booklets page
- **Background Processing**: Non-blocking execution that doesn't slow down the interface
- **Scheduled Script**: Can be run via cron job or Windows Task Scheduler for more reliability

### Email Notifications
- **Delivery Confirmation**: Customers receive professional delivery confirmation emails
- **Order Details**: Includes order number, tracking info, and delivery date
- **Company Branding**: Emails match company branding and include contact information
- **BCC Copy**: Company receives copy of all delivery notifications

## Setup Instructions

### 1. Test the System
1. Go to `sample_booklets.php` and add a test order
2. Ship the order with a real USPS tracking number
3. Click "Check Delivery Status" to manually test
4. Or visit `test_delivery_system.php` for comprehensive testing

### 2. Configure Email Settings
Ensure your email configuration is properly set up in `config/email_config.php`:
```php
$email_config = [
    'smtp_host' => 'your-smtp-server.com',
    'smtp_username' => 'your-email@domain.com',
    'smtp_password' => 'your-password',
    'smtp_port' => 587,
    'from_email' => 'orders@wrapmykitchen.com',
    'from_name' => 'Wrap My Kitchen',
    'bcc_email' => 'admin@wrapmykitchen.com',
    'support_email' => 'support@wrapmykitchen.com',
    'phone' => '(555) 123-4567',
    'company_address' => 'Your Company Address'
];
```

### 3. Schedule Regular Checks (Optional but Recommended)
For maximum reliability, set up automatic checking:

#### Windows Task Scheduler:
1. Open Task Scheduler
2. Create Basic Task
3. Name: "WMK Delivery Check"
4. Trigger: Daily, repeat every 1 hour
5. Action: Start a program
6. Program: `C:\xampp\htdocs\wmk\run_delivery_check.bat`

#### Linux/Unix Cron Job:
```bash
# Run every hour
0 * * * * /usr/bin/php /path/to/wmk/schedule_delivery_check.php
```

## Files Overview

### Core System Files
- `sample_booklets.php` - Main management interface with auto-check integration
- `handlers/auto_delivery_check.php` - Core delivery checking functionality
- `schedule_delivery_check.php` - Scheduled task script
- `js/sample_booklets.js` - Frontend JavaScript with manual check function

### Testing & Utilities
- `test_delivery_system.php` - Comprehensive testing interface
- `handlers/test_single_tracking.php` - Individual tracking number testing
- `run_delivery_check.bat` - Windows batch file for scheduling

### Configuration
- `config/email_config.php` - Email settings for notifications
- `config/database.php` - Database connection

## Usage

### For Regular Operations
1. Add orders as normal in the sample booklets system
2. Ship orders and add USPS tracking numbers
3. The system will automatically detect deliveries and update status
4. Customers will receive automatic delivery confirmation emails

### For Testing
1. Visit `test_delivery_system.php`
2. Use "Run Manual Delivery Check" to check all shipped orders
3. Use "Test Specific Tracking" to test individual tracking numbers
4. Use "View System Status" to see system information

### Manual Override
- The "Check Delivery Status" button in the main interface triggers immediate checking
- You can still manually edit order status if needed
- System respects manual status changes and won't override them

## Troubleshooting

### Common Issues
1. **No orders being updated**: Check that orders have valid USPS tracking numbers
2. **Email notifications not sending**: Verify email configuration in `config/email_config.php`
3. **Tracking check failing**: Check internet connection and USPS website availability

### Debug Information
- Check PHP error logs for detailed information
- Use the test system to verify individual tracking numbers
- Manual test mode provides detailed output for troubleshooting

### Log Files
The system logs important events to the PHP error log:
- Auto-check triggers
- Successful delivery detections
- Email send status
- Error conditions

## Security Notes
- System uses secure HTTPS connections to USPS
- No sensitive data is stored in tracking checks
- Email notifications are sent via secure SMTP
- Background checks are rate-limited to be respectful to USPS servers

## Performance
- Background checks are non-blocking and won't slow down the interface
- Checks are limited to orders with "Shipped" status and valid tracking numbers
- System includes intelligent delays between requests to avoid overloading USPS
- Auto-check frequency is limited to once per hour to balance automation with performance

This system ensures that customers are promptly notified when their orders are delivered while reducing manual administrative work for tracking order fulfillment status.
