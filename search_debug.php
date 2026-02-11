<?php
require_once 'config.php';

$q = 'smkn 6';
echo "Searching for: $q\n\n";

echo "1. Testing searchAddressGoogle...\n";
$google = searchAddressGoogle($q);
echo "   Results: " . count($google) . "\n";
if (!empty($google)) {
    echo "   First Result: " . $google[0]['display_name'] . "\n";
}

echo "\n2. Testing searchAddressNominatim directly...\n";
$nominatim = searchAddressNominatim($q);
echo "   Results: " . count($nominatim) . "\n";
if (!empty($nominatim)) {
    echo "   First Result: " . $nominatim[0]['display_name'] . "\n";
}

echo "\n3. Deep debug of httpRequest for Nominatim...\n";
$url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=id&q=' . urlencode($q);
$headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];

echo "   URL: $url\n";

// Test cURL
if (function_exists('curl_init')) {
    echo "   Testing cURL...\n";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    curl_close($ch);
    echo "   cURL HTTP Code: " . $info['http_code'] . "\n";
    if ($err) echo "   cURL Error: $err\n";
    echo "   cURL Response Length: " . strlen($resp) . "\n";
}

// Test file_get_contents
if (ini_get('allow_url_fopen')) {
    echo "   Testing file_get_contents...\n";
    $opts = [
        "http" => [
            "method" => "GET",
            "header" => implode("\r\n", $headers) . "\r\n",
            "timeout" => 10
        ],
        "ssl" => [
            "verify_peer" => false,
            "verify_peer_name" => false
        ]
    ];
    $context = stream_context_create($opts);
    $resp = @file_get_contents($url, false, $context);
    echo "   file_get_contents Response Length: " . (is_string($resp) ? strlen($resp) : "FAILED") . "\n";
    if ($resp === false) {
        $error = error_get_last();
        echo "   file_get_contents Error: " . ($error['message'] ?? 'Unknown error') . "\n";
    }
}
