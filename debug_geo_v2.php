<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock PDO
class MockPDO {
    public function prepare($sql) { return new MockStmt(); }
    public function query($sql) { return new MockStmt(); }
    public function exec($sql) { return 1; }
    public function lastInsertId() { return 1; }
}
class MockStmt {
    public function execute($params = []) { return true; }
    public function fetch() { return false; }
    public function fetchAll() { return []; }
    public function rowCount() { return 0; }
}
$pdo = new MockPDO();

// Define other globals if needed
$base_url = "http://localhost/magang/Absen/";

session_start();
$_SESSION['user'] = ['id' => 1, 'role' => 'pegawai'];

echo "Including layout_header.php...\n";
ob_start(); // buffer output to swallow HTML
try {
    require_once 'pages/layout_header.php';
} catch (Throwable $e) {
    echo "Error including file: " . $e->getMessage() . "\n";
}
ob_end_clean();
echo "Include successful.\n";

if (function_exists('reverseGeocodeAddress')) {
    echo "Testing reverseGeocodeAddress...\n";
    try {
        $res = reverseGeocodeAddress(-6.97, 107.63);
        echo "Result: " . $res . "\n";
    } catch (Throwable $e) {
        echo "Error calling function: " . $e->getMessage() . "\n";
    }
} else {
    echo "Function reverseGeocodeAddress NOT FOUND.\n";
}
?>
