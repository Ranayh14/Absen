<?php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected successfully.\n";
    
    // Check if columns exist
    $stmt = $pdo->query("DESCRIBE admin_help_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('attendance_type', $columns)) {
        echo "Adding attendance_type column...\n";
        $pdo->exec("ALTER TABLE admin_help_requests ADD COLUMN attendance_type ENUM('wfo', 'wfa', 'overtime') DEFAULT 'wfo' AFTER request_type");
    } else {
        echo "attendance_type column already exists.\n";
    }
    
    if (!in_array('attendance_reason', $columns)) {
        echo "Adding attendance_reason column...\n";
        $pdo->exec("ALTER TABLE admin_help_requests ADD COLUMN attendance_reason TEXT NULL AFTER attendance_type");
    } else {
        echo "attendance_reason column already exists.\n";
    }
    
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
