<?php
// Debug script to test reverseGeocodeAddress
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Loading config...\n";
// Minimal mock of config/db if layout_header needs it, but layout_header typically has functions.
// If layout_header includes other files, we might need to be careful.
// Let's assume we can include it.

// Mock session if needed
session_start();
$_SESSION['user'] = ['id' => 1, 'role' => 'pegawai'];

try {
    echo "Including layout_header.php...\n";
    require_once 'pages/layout_header.php';
    echo "Include successful.\n";
    
    echo "Testing reverseGeocodeAddress...\n";
    if (function_exists('reverseGeocodeAddress')) {
        echo "Function exists.\n";
        $result = reverseGeocodeAddress(-6.97327666, 107.63213179);
        echo "Result: " . var_export($result, true) . "\n";
    } else {
        echo "Function reverseGeocodeAddress NOT found!\n";
    }
    
} catch (Throwable $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>
