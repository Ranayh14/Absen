<?php
require_once 'config.php';
require_once 'pages/ajax_handler.php';

$query = 'Jakarta';
echo "Searching for: $query\n";

// Mocking searchAddressGoogle with debug output
function searchAddressGoogleDebug(string $query): array {
    $apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($query) . "&key={$apiKey}&language=id&region=id";
    
    echo "Request URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode\n";
    if ($err) echo "Curl Error: $err\n";
    echo "Raw Response: " . substr($resp, 0, 500) . "...\n";
    
    $data = json_decode($resp, true);
    if (!isset($data['status'])) return [];
    echo "API Status: " . $data['status'] . "\n";
    if (isset($data['error_message'])) echo "Error Message: " . $data['error_message'] . "\n";
    
    return [];
}

// Nominatim Debug
function searchAddressNominatimDebug(string $query): array {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=id&q=' . urlencode($query);
    echo "Nominatim URL: $url\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AbsenApp/1.0 (XAMPP PHP/Test)'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    
    echo "Nominatim HTTP Code: $httpCode\n";
    if ($err) echo "Nominatim Curl Error: $err\n";
    echo "Nominatim Response: " . substr($resp, 0, 200) . "...\n";
    
    return [];
}

echo "Testing file_get_contents (Google API)...\n";
$apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode('Jakarta') . "&key={$apiKey}";
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "User-Agent: PHP\r\n",
        "timeout" => 5
    ],
    "ssl" => [
        "verify_peer" => false,
        "verify_peer_name" => false
    ]
];
$context = stream_context_create($opts);
$resp = @file_get_contents($url, false, $context);
if ($resp) {
    echo "file_get_contents Success! length: " . strlen($resp) . "\n";
    echo "Response: " . substr($resp, 0, 100) . "...\n";
} else {
    echo "file_get_contents Failed.\n";
}



