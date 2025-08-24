<?php
/**
 * USPS API Client
 * 
 * Handles OAuth2 authentication and tracking requests to the official USPS API
 * Falls back to web scraping if API is not configured or fails
 */

require_once __DIR__ . '/../config/usps_api_config.php';

class USPSAPIClient {
    private $config;
    private $access_token = null;
    private $token_expires_at = 0;
    
    public function __construct() {
        global $usps_api_config;
        $this->config = $usps_api_config;
    }
    
    /**
     * Get OAuth2 access token
     */
    private function getAccessToken() {
        // Check if we have a valid cached token
        if ($this->access_token && time() < ($this->token_expires_at - $this->config['token_expiry_buffer'])) {
            return $this->access_token;
        }
        
        // Try to load token from cache file
        if (file_exists($this->config['token_cache_file'])) {
            $cached_token = json_decode(file_get_contents($this->config['token_cache_file']), true);
            if ($cached_token && time() < ($cached_token['expires_at'] - $this->config['token_expiry_buffer'])) {
                $this->access_token = $cached_token['access_token'];
                $this->token_expires_at = $cached_token['expires_at'];
                return $this->access_token;
            }
        }
        
        // Request new token
        return $this->requestNewToken();
    }
    
    /**
     * Request new OAuth2 token from USPS
     */
    private function requestNewToken() {
        if (!isUSPSAPIConfigured()) {
            throw new Exception('USPS API not configured. Please add your Client ID and Secret to config/usps_api_config.php');
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->config['oauth_url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode($this->config['client_id'] . ':' . $this->config['client_secret'])
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("OAuth request failed: " . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception("OAuth request failed with HTTP {$http_code}: " . $response);
        }
        
        $token_data = json_decode($response, true);
        if (!$token_data || !isset($token_data['access_token'])) {
            throw new Exception("Invalid OAuth response: " . $response);
        }
        
        // Cache the token
        $this->access_token = $token_data['access_token'];
        $this->token_expires_at = time() + $token_data['expires_in'];
        
        // Save to cache file
        $cache_data = [
            'access_token' => $this->access_token,
            'expires_at' => $this->token_expires_at,
            'created_at' => time()
        ];
        
        // Create temp directory if it doesn't exist
        if (!is_dir('temp')) {
            mkdir('temp', 0755, true);
        }
        
        file_put_contents($this->config['token_cache_file'], json_encode($cache_data));
        
        return $this->access_token;
    }
    
    /**
     * Check tracking status using USPS API
     */
    public function getTrackingStatus($tracking_number) {
        // Check if this is a test tracking number first
        if ($this->config['enable_test_mode'] && $this->isTestTrackingNumber($tracking_number)) {
            return $this->getTestTrackingStatus($tracking_number);
        }
        
        try {
            // Try USPS API first
            if (isUSPSAPIConfigured()) {
                return $this->getTrackingStatusFromAPI($tracking_number);
            }
        } catch (Exception $e) {
            error_log("USPS API failed for {$tracking_number}: " . $e->getMessage());
            
            // Fall back to web scraping if enabled
            if ($this->config['enable_fallback_scraping']) {
                error_log("Falling back to web scraping for {$tracking_number}");
                return $this->getTrackingStatusFromScraping($tracking_number);
            }
            
            throw $e;
        }
        
        // If API not configured, use fallback method
        if ($this->config['enable_fallback_scraping']) {
            return $this->getTrackingStatusFromScraping($tracking_number);
        }
        
        throw new Exception('USPS API not configured and fallback scraping disabled');
    }
    
    /**
     * Get tracking status from USPS API
     */
    private function getTrackingStatusFromAPI($tracking_number) {
        $access_token = $this->getAccessToken();
        
        $url = $this->config['tracking_url'] . '/' . urlencode($tracking_number) . '?expand=DETAIL';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->config['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("API request failed: " . $curl_error);
        }
        
        if ($http_code === 401) {
            // Token might be expired, try to get a new one
            $this->access_token = null;
            $this->token_expires_at = 0;
            throw new Exception("Authentication failed, token may be expired");
        }
        
        if ($http_code !== 200) {
            throw new Exception("API request failed with HTTP {$http_code}: " . $response);
        }
        
        $data = json_decode($response, true);
        if (!$data) {
            throw new Exception("Invalid API response: " . $response);
        }
        
        return $this->parseAPIResponse($data);
    }
    
    /**
     * Parse USPS API response
     */
    private function parseAPIResponse($data) {
        // Check event summaries for delivery status
        if (isset($data['eventSummaries']) && is_array($data['eventSummaries'])) {
            foreach ($data['eventSummaries'] as $event) {
                $event_lower = strtolower($event);
                
                // Check for delivery indicators
                if (preg_match('/delivered|delivery complete|package delivered|item delivered/i', $event)) {
                    // Try to extract delivery date
                    $delivery_date = $this->extractDateFromEvent($event);
                    if (!$delivery_date && isset($data['expectedDeliveryDate'])) {
                        $delivery_date = $data['expectedDeliveryDate'];
                    }
                    if (!$delivery_date) {
                        $delivery_date = date('Y-m-d');
                    }
                    
                    return [
                        'status' => 'delivered',
                        'delivery_date' => $delivery_date,
                        'message' => 'Package delivered (confirmed via USPS API)',
                        'details' => $event
                    ];
                }
                
                // Check for out for delivery
                if (preg_match('/out for delivery|on vehicle for delivery/i', $event)) {
                    return [
                        'status' => 'out_for_delivery',
                        'message' => 'Package is out for delivery',
                        'details' => $event
                    ];
                }
            }
        }
        
        // If no delivery detected, package is still in transit
        return [
            'status' => 'shipped',
            'message' => 'Package still in transit',
            'details' => isset($data['eventSummaries'][0]) ? $data['eventSummaries'][0] : 'No tracking events available'
        ];
    }
    
    /**
     * Extract date from tracking event text
     */
    private function extractDateFromEvent($event) {
        // Look for common date patterns in tracking events
        $date_patterns = [
            '/(\d{1,2}\/\d{1,2}\/\d{4})/',
            '/(\d{4}-\d{2}-\d{2})/',
            '/(January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4}/i'
        ];
        
        foreach ($date_patterns as $pattern) {
            if (preg_match($pattern, $event, $matches)) {
                try {
                    $date = new DateTime($matches[1]);
                    return $date->format('Y-m-d');
                } catch (Exception $e) {
                    continue;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Check if tracking number is a test number
     */
    private function isTestTrackingNumber($tracking_number) {
        foreach ($this->config['test_tracking_numbers'] as $status => $test_numbers) {
            if (in_array($tracking_number, $test_numbers)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Get test tracking status for development
     */
    private function getTestTrackingStatus($tracking_number) {
        foreach ($this->config['test_tracking_numbers'] as $status => $test_numbers) {
            if (in_array($tracking_number, $test_numbers)) {
                switch ($status) {
                    case 'delivered':
                        return [
                            'status' => 'delivered',
                            'delivery_date' => date('Y-m-d'),
                            'message' => 'Package delivered (TEST MODE)',
                            'details' => 'Test tracking number - simulated delivery'
                        ];
                    case 'out_for_delivery':
                        return [
                            'status' => 'out_for_delivery',
                            'message' => 'Package is out for delivery (TEST MODE)',
                            'details' => 'Test tracking number - simulated out for delivery'
                        ];
                    case 'in_transit':
                        return [
                            'status' => 'shipped',
                            'message' => 'Package still in transit (TEST MODE)',
                            'details' => 'Test tracking number - simulated in transit'
                        ];
                }
            }
        }
        
        return [
            'status' => 'error',
            'message' => 'Test tracking number not recognized'
        ];
    }
    
    /**
     * Fallback web scraping method (existing implementation)
     */
    private function getTrackingStatusFromScraping($tracking_number) {
        try {
            $url = "https://tools.usps.com/go/TrackConfirmAction?tRef=fullpage&tLc=2&text28777=&tLabels=" . urlencode($tracking_number);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Connection: keep-alive',
                'Cache-Control: no-cache'
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("Scraping request failed: " . $curl_error);
            }
            
            if ($http_code !== 200) {
                throw new Exception("Scraping request failed with HTTP {$http_code}");
            }
            
            if (empty($response)) {
                throw new Exception("Empty response from USPS website");
            }
            
            // Parse the scraped response for delivery status
            return $this->parseScrapedResponse($response, $tracking_number);
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Unable to check tracking status: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Parse scraped HTML response
     */
    private function parseScrapedResponse($response, $tracking_number) {
        // Check for various delivery status indicators
        $delivery_patterns = [
            '/delivered[^a-z]*(\d{1,2}\/\d{1,2}\/\d{2,4})/i',
            '/delivery[^a-z]*complete/i',
            '/package[^a-z]*delivered/i',
            '/item[^a-z]*delivered/i',
            '/successfully[^a-z]*delivered/i',
            '/delivered[^a-z]*to[^a-z]*recipient/i',
            '/left[^a-z]*with[^a-z]*individual/i',
            '/delivered[^a-z]*to[^a-z]*mailbox/i',
            '/delivered[^a-z]*to[^a-z]*front[^a-z]*door/i'
        ];
        
        $delivered = false;
        $delivery_date = null;
        
        foreach ($delivery_patterns as $pattern) {
            if (preg_match($pattern, $response, $matches)) {
                $delivered = true;
                
                // Try to extract delivery date if found in the match
                if (isset($matches[1]) && preg_match('/\d{1,2}\/\d{1,2}\/\d{2,4}/', $matches[1])) {
                    try {
                        $date_obj = DateTime::createFromFormat('m/d/Y', $matches[1]);
                        if (!$date_obj) {
                            $date_obj = DateTime::createFromFormat('m/d/y', $matches[1]);
                        }
                        if ($date_obj) {
                            $delivery_date = $date_obj->format('Y-m-d');
                        }
                    } catch (Exception $e) {
                        $delivery_date = date('Y-m-d');
                    }
                }
                break;
            }
        }
        
        if (!$delivered && !$delivery_date) {
            $delivery_date = date('Y-m-d');
        }
        
        // Check for "out for delivery" status
        $out_for_delivery_patterns = [
            '/out[^a-z]*for[^a-z]*delivery/i',
            '/on[^a-z]*vehicle[^a-z]*for[^a-z]*delivery/i',
            '/loaded[^a-z]*on[^a-z]*delivery[^a-z]*vehicle/i'
        ];
        
        $out_for_delivery = false;
        foreach ($out_for_delivery_patterns as $pattern) {
            if (preg_match($pattern, $response)) {
                $out_for_delivery = true;
                break;
            }
        }
        
        if ($delivered) {
            return [
                'status' => 'delivered',
                'delivery_date' => $delivery_date,
                'message' => 'Package delivered (confirmed via USPS website scraping)',
                'details' => 'Detected via web scraping fallback method'
            ];
        } elseif ($out_for_delivery) {
            return [
                'status' => 'out_for_delivery',
                'message' => 'Package is out for delivery (detected via scraping)',
                'details' => 'Detected via web scraping fallback method'
            ];
        } else {
            // Check if tracking number is invalid or not found
            if (preg_match('/not[^a-z]*found|invalid[^a-z]*tracking|no[^a-z]*record/i', $response)) {
                return [
                    'status' => 'error',
                    'message' => 'Tracking number not found or invalid'
                ];
            }
            
            return [
                'status' => 'shipped',
                'message' => 'Package still in transit (no delivery detected via scraping)',
                'details' => 'Checked via web scraping fallback method'
            ];
        }
    }
}
?>
