<?php
/**
 * USPS API Configuration
 * 
 * To use the official USPS API, you need to:
 * 1. Register at https://developer.usps.com/
 * 2. Create an application to get your Client ID and Secret
 * 3. Add your credentials below
 * 4. Set up OAuth2 authentication
 */

$usps_api_config = [
    // USPS API Credentials - Get these from https://developer.usps.com/
    'client_id' => 'QT7UQEilM6Zc6jJIn7q6S3UeHvfhivnHjGleTRV2AInrfSkI', // Your USPS API Client ID
    'client_secret' => 'FqHxCldsZLEX4yIZNGJ0ktF0oYGtUVPjEkt5IHs9yogFGvx1zR8i3hynardbrGlQ', // Your USPS API Client Secret
    
    // API Endpoints
    'base_url' => 'https://api.usps.com',
    'oauth_url' => 'https://api.usps.com/oauth2/v3/token',
    'tracking_url' => 'https://api.usps.com/tracking/v3/tracking',
    
    // API Settings
    'timeout' => 30,
    'max_retries' => 3,
    
    // Cache settings for OAuth tokens
    'token_cache_file' => 'temp/usps_oauth_token.json',
    'token_expiry_buffer' => 300, // Refresh token 5 minutes before expiry
    
    // Fallback options
    'enable_fallback_scraping' => true, // Use web scraping if API fails
    'enable_test_mode' => true, // Enable test tracking numbers for development
    
    // Test tracking numbers (for development/testing)
    'test_tracking_numbers' => [
        'delivered' => [
            '9405511899560974109312',
            '9405511899560974109313',
            'TEST_DELIVERED_001',
            'TEST_DELIVERED_002'
        ],
        'out_for_delivery' => [
            'TEST_OUT_FOR_DELIVERY_001'
        ],
        'in_transit' => [
            'TEST_IN_TRANSIT_001'
        ]
    ]
];

// Function to check if USPS API is properly configured
function isUSPSAPIConfigured() {
    global $usps_api_config;
    return !empty($usps_api_config['client_id']) && !empty($usps_api_config['client_secret']);
}

// Function to get USPS API status
function getUSPSAPIStatus() {
    if (isUSPSAPIConfigured()) {
        return 'configured';
    } else {
        return 'not_configured';
    }
}
?>
