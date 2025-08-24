<?php
// Email Enrichment Configuration
// Copy this file to config/enrichment_config.php and add your API keys

// Clearbit API Configuration
// Sign up at: https://clearbit.com/
// define('CLEARBIT_API_KEY', 'your_clearbit_api_key_here');

// Hunter.io API Configuration  
// Sign up at: https://hunter.io/
// define('HUNTER_API_KEY', 'your_hunter_api_key_here');

// FullContact API Configuration
// Sign up at: https://www.fullcontact.com/
// define('FULLCONTACT_API_KEY', 'your_fullcontact_api_key_here');

// Pipl API Configuration
// Sign up at: https://pipl.com/
// define('PIPL_API_KEY', 'your_pipl_api_key_here');

// Email Enrichment Settings
define('ENRICHMENT_TIMEOUT', 5); // Timeout in seconds for API calls
define('ENRICHMENT_CACHE_TTL', 86400); // Cache results for 24 hours

// Available enrichment services (in order of preference)
$enrichment_services = [
    'internal_database',  // Always check internal first
    'email_pattern',      // Basic pattern matching (always available)
    'domain_analysis',    // Business email detection (always available)
    // 'clearbit',        // Uncomment when you have API key
    // 'hunter',          // Uncomment when you have API key
    // 'fullcontact',     // Uncomment when you have API key
];

// Cache configuration (optional - for storing API results)
define('ENRICHMENT_CACHE_ENABLED', true);
define('ENRICHMENT_CACHE_DIR', __DIR__ . '/../cache/enrichment/');

// Create cache directory if it doesn't exist
if (ENRICHMENT_CACHE_ENABLED && !is_dir(ENRICHMENT_CACHE_DIR)) {
    mkdir(ENRICHMENT_CACHE_DIR, 0755, true);
}

/*
SETUP INSTRUCTIONS:

1. Choose an enrichment service:
   - Clearbit: Best overall data quality, moderate pricing
   - Hunter.io: Good for email verification and basic info
   - FullContact: Good social media data
   - Pipl: Deep people search

2. Sign up for an account and get API key

3. Uncomment the define() line for your chosen service

4. Uncomment the service name in $enrichment_services array

5. Test the integration by entering an email in the add lead form

PRICING REFERENCE (as of 2024):
- Clearbit: $99/month for 1,000 lookups
- Hunter.io: $49/month for 1,000 requests  
- FullContact: $99/month for 1,000 lookups
- Most services offer free trials with limited requests

FREE ALTERNATIVES:
- The system already includes email pattern analysis
- Domain-based company detection
- Internal database lookup
- These work without any API keys
*/
?>
