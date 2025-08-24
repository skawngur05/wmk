# Sample Booklet System - Live Deployment Checklist

## Files to Upload to Live Server

### 📄 Main Application Files
- `sample_booklets.php` ✅ **UPDATED** - Main sample booklet management page
- `config/database.php` ✅ **REQUIRED** - Database configuration
- `config/email_config.php` ✅ **REQUIRED** - Email settings for notifications
- `config/usps_api_config.php` ✅ **NEW** - USPS API configuration with your credentials

### 🔧 Handler Files (Backend Logic)
- `handlers/sample_booklets_handler.php` ✅ **UPDATED** - CRUD operations for orders
- `handlers/shipping_handler.php` ✅ **REQUIRED** - Shipping and email notifications
- `handlers/delivery_check.php` ✅ **NEW** - USPS delivery status checking

### 🎨 Frontend Assets
- `js/sample_booklets.js` ✅ **UPDATED** - JavaScript for modals and interactions
- `css/style.css` ✅ **REQUIRED** - Existing styles
- `images/wmk-logo.jpg` ✅ **REQUIRED** - Company logo

### 🔐 Authentication & Security
- `includes/auth.php` ✅ **REQUIRED** - Authentication functions
- `includes/header.php` ✅ **REQUIRED** - Page header with navigation
- `includes/footer.php` ✅ **REQUIRED** - Page footer

### 📧 Email System (if using local PHPMailer)
- `includes/phpmailer/` ✅ **REQUIRED** - PHPMailer library folder
  - `Exception.php`
  - `PHPMailer.php`
  - `SMTP.php`

### 🆕 New API Integration Files
- `includes/USPSAPIClient.php` ✅ **NEW** - USPS API client class

### 📁 Directory Structure to Create
```
/wmk/
├── sample_booklets.php
├── config/
│   ├── database.php
│   ├── email_config.php
│   └── usps_api_config.php
├── handlers/
│   ├── sample_booklets_handler.php
│   ├── shipping_handler.php
│   └── delivery_check.php
├── includes/
│   ├── auth.php
│   ├── header.php
│   ├── footer.php
│   ├── USPSAPIClient.php
│   └── phpmailer/
│       ├── Exception.php
│       ├── PHPMailer.php
│       └── SMTP.php
├── js/
│   └── sample_booklets.js
├── css/
│   └── style.css
├── images/
│   └── wmk-logo.jpg
└── temp/
    └── (auto-created for delivery check cache)
```

## 📋 Pre-Deployment Checklist

### 1. Database Setup
- [ ] Ensure MySQL database exists on live server
- [ ] Update `config/database.php` with live database credentials
- [ ] The `sample_booklets` table will be auto-created on first access

### 2. Email Configuration
- [ ] Update `config/email_config.php` with live SMTP settings
- [ ] Test email sending functionality
- [ ] Verify company email (infofloridawmk@gmail.com) receives BCC copies

### 3. USPS API Configuration
- [ ] Ensure `config/usps_api_config.php` has your approved API credentials:
  - Consumer Key: `QT7UQEilM6...`
  - Consumer Secret: `FqHxCldsZL...`
- [ ] API credentials should work in production environment

### 4. File Permissions
- [ ] Set proper permissions on `temp/` directory (755 or 777)
- [ ] Ensure web server can write to `temp/` for delivery check cache

### 5. Security
- [ ] Remove any development/debug files
- [ ] Ensure `.htaccess` files are properly configured
- [ ] Verify authentication is working

## 🚀 Deployment Steps

1. **Upload Files**: Upload all files listed above to your live server
2. **Test Database**: Visit `sample_booklets.php` - table will auto-create
3. **Test Email**: Try shipping an order to verify email notifications
4. **Test USPS API**: Use "Check Delivery Status" button to test API integration
5. **Verify Authentication**: Ensure login system works properly

## ⚡ Key Features Available After Deployment

✅ **Order Management**: Add, edit, delete sample booklet orders
✅ **Product Types**: Support for all 4 product types:
   - Demo Kit & Sample Booklet
   - Sample Booklet Only
   - Trial Kit  
   - Demo Kit Only
✅ **Shipping System**: Ship orders with USPS tracking
✅ **Email Notifications**: Automatic customer emails with tracking info
✅ **Delivery Tracking**: Automatic USPS delivery status updates
✅ **Status Management**: Pending → Shipped → Delivered workflow

## 🔍 Post-Deployment Testing

1. **Add New Order**: Test order creation with all product types
2. **Edit Order**: Verify edit modal works correctly
3. **Ship Order**: Test shipping process and email notifications
4. **Check Delivery**: Test USPS API delivery status checking
5. **Delete Order**: Verify delete functionality with confirmation

## 📞 Support

If you encounter issues after deployment:
- Check server error logs for PHP errors
- Verify database connection in `config/database.php`
- Ensure USPS API credentials are correct
- Test email settings in `config/email_config.php`

## 💾 Database Auto-Migration

The system includes automatic database migration:
- Creates `sample_booklets` table if it doesn't exist
- Updates existing table structure for new product types
- Migrates old product type data to new format
- No manual database changes required!
