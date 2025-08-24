<?php
require_once 'config/database.php';
require_once 'includes/auth.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$phone = trim($input['phone'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['error' => 'Invalid email address']);
    exit;
}

$response = [
    'found' => false,
    'source' => '',
    'data' => []
];

try {
    // 1. Check internal database first (comprehensive check with email and phone)
    $stmt = $pdo->prepare("
        SELECT name, phone, email, lead_origin, assigned_to, remarks, notes, date_created
        FROM leads 
        WHERE email = ? OR (phone IS NOT NULL AND phone != '' AND phone = ?)
        ORDER BY date_created DESC 
        LIMIT 1
    ");
    $stmt->execute([$email, $phone]);
    $existingLead = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existingLead) {
        $response['found'] = true;
        $response['source'] = 'internal_database';
        $response['data'] = [
            'name' => $existingLead['name'],
            'phone' => $existingLead['phone'],
            'email' => $existingLead['email'],
            'lead_origin' => $existingLead['lead_origin'],
            'assigned_to' => $existingLead['assigned_to'],
            'previous_status' => $existingLead['remarks'],
            'previous_notes' => $existingLead['notes'],
            'date_created' => $existingLead['date_created']
        ];
        echo json_encode($response);
        exit;
    }
    
    // 2. Try email domain enrichment (extract company from email domain)
    $domain = substr(strrchr($email, "@"), 1);
    $commonDomains = ['gmail.com', 'yahoo.com', 'hotmail.com', 'outlook.com', 'aol.com'];
    
    if (!in_array($domain, $commonDomains)) {
        // It's likely a business email - extract potential company name
        $companyName = str_replace(['.com', '.org', '.net', '.ca'], '', $domain);
        $companyName = ucwords(str_replace(['-', '_', '.'], ' ', $companyName));
        
        $response['found'] = true;
        $response['source'] = 'domain_analysis';
        $response['data'] = [
            'potential_company' => $companyName,
            'business_email' => true
        ];
    }
    
    // 3. Try to extract name from email prefix (common patterns)
    $emailPrefix = substr($email, 0, strpos($email, '@'));
    $nameGuess = '';
    
    // Common email patterns
    if (strpos($emailPrefix, '.') !== false) {
        // john.doe@example.com
        $parts = explode('.', $emailPrefix);
        $nameGuess = ucfirst($parts[0]) . ' ' . ucfirst($parts[1]);
    } elseif (strpos($emailPrefix, '_') !== false) {
        // john_doe@example.com
        $parts = explode('_', $emailPrefix);
        $nameGuess = ucfirst($parts[0]) . ' ' . ucfirst($parts[1]);
    } elseif (preg_match('/([a-z]+)([0-9]+)/', $emailPrefix, $matches)) {
        // john123@example.com
        $nameGuess = ucfirst($matches[1]);
    } else {
        // Simple case - just the prefix
        $nameGuess = ucfirst($emailPrefix);
    }
    
    if ($nameGuess && !$response['found']) {
        $response['found'] = true;
        $response['source'] = 'email_pattern';
        $response['data'] = [
            'suggested_name' => $nameGuess,
            'confidence' => 'low'
        ];
    }
    
    // 4. Placeholder for external API enrichment
    // Uncomment and configure when you have API keys
    
    /*
    // Example: Clearbit Enrichment API
    if (!$response['found'] && defined('CLEARBIT_API_KEY')) {
        $clearbitUrl = "https://person.clearbit.com/v1/people/email/" . urlencode($email);
        $context = stream_context_create([
            'http' => [
                'header' => "Authorization: Bearer " . CLEARBIT_API_KEY,
                'timeout' => 5
            ]
        ]);
        
        $clearbitResponse = @file_get_contents($clearbitUrl, false, $context);
        if ($clearbitResponse) {
            $clearbitData = json_decode($clearbitResponse, true);
            if (isset($clearbitData['name']['fullName'])) {
                $response['found'] = true;
                $response['source'] = 'clearbit';
                $response['data'] = [
                    'name' => $clearbitData['name']['fullName'],
                    'phone' => $clearbitData['phone'] ?? '',
                    'company' => $clearbitData['employment']['name'] ?? '',
                    'title' => $clearbitData['employment']['title'] ?? ''
                ];
            }
        }
    }
    
    // Example: Hunter.io Email Finder
    if (!$response['found'] && defined('HUNTER_API_KEY')) {
        $hunterUrl = "https://api.hunter.io/v2/email-verifier?email=" . urlencode($email) . "&api_key=" . HUNTER_API_KEY;
        $hunterResponse = @file_get_contents($hunterUrl);
        if ($hunterResponse) {
            $hunterData = json_decode($hunterResponse, true);
            if (isset($hunterData['data']['sources'])) {
                // Process Hunter.io response
                $response['found'] = true;
                $response['source'] = 'hunter';
                $response['data'] = [
                    'email_verified' => $hunterData['data']['result'] === 'deliverable',
                    'sources' => count($hunterData['data']['sources'])
                ];
            }
        }
    }
    */
    
} catch (Exception $e) {
    error_log("Email enrichment error: " . $e->getMessage());
    $response['error'] = 'Enrichment service temporarily unavailable';
}

echo json_encode($response);
?>
