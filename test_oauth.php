<?php
require_once __DIR__ . '/config/usps_api_config.php';

echo "USPS OAuth Test\n";
echo "===============\n";

global $usps_api_config;

echo "Client ID: " . substr($usps_api_config['client_id'], 0, 10) . "...\n";
echo "Client Secret: " . substr($usps_api_config['client_secret'], 0, 10) . "...\n";
echo "OAuth URL: " . $usps_api_config['oauth_url'] . "\n";
echo "\n";

echo "Testing OAuth request...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $usps_api_config['oauth_url']);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded',
    'Authorization: Basic ' . base64_encode($usps_api_config['client_id'] . ':' . $usps_api_config['client_secret'])
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $http_code\n";
echo "cURL Error: " . ($curl_error ?: 'None') . "\n";
echo "Response: $response\n";

if ($http_code === 200) {
    $token_data = json_decode($response, true);
    if ($token_data && isset($token_data['access_token'])) {
        echo "✅ OAuth success! Got access token: " . substr($token_data['access_token'], 0, 20) . "...\n";
    } else {
        echo "❌ OAuth failed: Invalid response format\n";
    }
} else {
    echo "❌ OAuth failed with HTTP $http_code\n";
    
    // Try alternative OAuth approach
    echo "\nTrying alternative OAuth approach...\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $usps_api_config['oauth_url']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded'
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'client_credentials',
        'client_id' => $usps_api_config['client_id'],
        'client_secret' => $usps_api_config['client_secret']
    ]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    $response2 = curl_exec($ch);
    $http_code2 = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error2 = curl_error($ch);
    curl_close($ch);
    
    echo "Alternative HTTP Code: $http_code2\n";
    echo "Alternative Response: $response2\n";
}

echo "\nDone!\n";
