<?php
require_once 'config.php';
$query = 'Bandung';
echo "Testing Nominatim directly for: $query\n";
$results = searchAddressNominatim($query);
if (empty($results)) {
    echo "Nominatim FAILED.\n";
} else {
    echo "Nominatim SUCCESS! Found " . count($results) . " results.\n";
    foreach ($results as $res) {
        echo "- " . $res['display_name'] . "\n";
    }
}
