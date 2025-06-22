<?php
// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Angel One SmartAPI credentials
$api_key = "p1li5p3Z"; // Your API key
$client_code = "S60834692"; // Your client ID
$redirect_url = "https://brijesh.free.nf/api/callback.php"; // Registered redirect URL

// Function to fetch symbol token for RELIANCE 1430 CE
function getSymbolToken($symbol_name = "RELIANCE", $strike_price = 1430, $option_type = "CE") {
    $url = "https://margincalculator.angelbroking.com/OpenAPI_File/files/OpenAPIScripMaster.json";
    $response = file_get_contents($url);
    if ($response === false) {
        return ['error' => 'Failed to fetch Scrip Master JSON.'];
    }
    
    $data = json_decode($response, true);
    if (!$data) {
        return ['error' => 'Invalid JSON response from Scrip Master.'];
    }
    
    // Filter for RELIANCE options (NFO, OPTSTK, nearest expiry)
    $current_date = date('Y-m-d');
    $matching_tokens = [];
    foreach ($data as $item) {
        if ($item['exch_seg'] === 'NFO' && 
            $item['instrumenttype'] === 'OPTSTK' && 
            $item['name'] === $symbol_name && 
            strpos($item['symbol'], $option_type) !== false && 
            floatval($item['strike']) === floatval($strike_price * 100)) {
            $expiry = date('Y-m-d', strtotime($item['expiry']));
            if ($expiry >= $current_date) {
                $matching_tokens[] = [
                    'token' => $item['token'],
                    'symbol' => $item['symbol'],
                    'expiry' => $expiry
                ];
            }
        }
    }
    
    // Sort by expiry and pick the nearest
    usort($matching_tokens, function($a, $b) {
        return strcmp($a['expiry'], $b['expiry']);
    });
    
    if (!empty($matching_tokens)) {
        return [
            'token' => $matching_tokens[0]['token'],
            'symbol' => $matching_tokens[0]['symbol']
        ];
    }
    
    return ['error' => 'No matching option found for RELIANCE 1430 CE.'];
}

// Function to make HTTP POST request using cURL
function makePostRequest($url, $data, $headers) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For InfinityFree
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    return ['response' => json_decode($response, true), 'error' => $error];
}

// Fetch symbol token for RELIANCE 1430 CE
$option_details = getSymbolToken("RELIANCE", 1430, "CE");
$output = null;
$symbol_token = null;
$trading_symbol = null;

if (isset($option_details['error'])) {
    $output = "Error: " . $option_details['error'];
} else {
    $symbol_token = $option_details['token'];
    $trading_symbol = $option_details['symbol'];
    
    // Check for cached token
    $token_file = 'token.txt';
    $auth_token = null;
    
    if (file_exists($token_file)) {
        $auth_token = trim(file_get_contents($token_file));
        
        // Test token validity by fetching LTP
        $headers = [
            "Authorization: Bearer $auth_token",
            "X-ClientLocalIP: 192.168.0.1",
            "X-ClientPublicIP: 122.172.1.1",
            "X-MACAddress: 00:11:22:33:44:55",
            "X-UserType: USER",
            "X-SourceID: WEB",
            "X-PrivateKey: $api_key",
            "Accept: application/json",
            "Content-Type: application/json"
        ];
        $payload = [
            "mode" => "LTP",
            "exchangeTokens" => [
                "NFO" => [$symbol_token]
            ]
        ];
        
        $result = makePostRequest("https://apiconnect.angelbroking.com/rest/secure/angelbroking/market/v1/quote", $payload, $headers);
        
        if ($result['error']) {
            $output = "cURL Error: " . $result['error'];
            $auth_token = null; // Invalidate token
        } elseif ($result['response']['status'] === true && isset($result['response']['data']['fetched'][0]['ltp'])) {
            $ltp = $result['response']['data']['fetched'][0]['ltp'];
            $output = "Current Option Price ($trading_symbol): ₹" . number_format($ltp, 2);
        } else {
            $output = "Error: Token invalid or expired. Please login again.";
            $auth_token = null; // Invalidate token
        }
    }
}

// If no valid token, redirect to login
if (!$auth_token) {
    $login_url = "https://smartapi.angelbroking.com/publisher-login?api_key=$api_key&redirect_uri=" . urlencode($redirect_url);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reliance Option Price Demo - Login</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        a { font-size: 18px; color: #007bff; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .price { font-size: 24px; color: green; }
        .error { font-size: 18px; color: red; }
    </style>
</head>
<body>
    <h2>Reliance Industries 1430 CE Option Price</h2>
    <?php if ($auth_token && $output): ?>
        <p class="<?php echo (strpos($output, 'Error') === false) ? 'price' : 'error'; ?>">
            <?php echo htmlspecialchars($output); ?>
        </p>
        <p><a href="index.php">Refresh Price</a></p>
    <?php else: ?>
        <p>Click below to login via Angel One SmartAPI</p>
        <a href="<?php echo htmlspecialchars($login_url); ?>">Login with Angel One</a>
    <?php endif; ?>
    <p>Powered by Angel One SmartAPI</p>
</body>
</html>
