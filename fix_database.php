<?php
// Database fix script for attendance table
// Run this script once to fix the database structure

// Database configuration
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

try {
    // Connect to database
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    echo "Connected to database successfully.\n";
    
    // Check if attendance table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'attendance'");
    if ($stmt->rowCount() == 0) {
        echo "Attendance table does not exist. Creating it...\n";
        
        $pdo->exec(
            "CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                jam_masuk VARCHAR(20) NULL,
                jam_masuk_iso DATETIME NULL,
                ekspresi_masuk VARCHAR(50) NULL,
                screenshot_masuk LONGTEXT NULL,
                jam_pulang VARCHAR(20) NULL,
                jam_pulang_iso DATETIME NULL,
                ekspresi_pulang VARCHAR(50) NULL,
                screenshot_pulang LONGTEXT NULL,
                status ENUM('ontime','terlambat') DEFAULT 'ontime',
                ket ENUM('hadir','izin','sakit','alpha','wfh') DEFAULT 'hadir',
                daily_report_id INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX(user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
        
        echo "Attendance table created successfully.\n";
    } else {
        echo "Attendance table exists. Checking columns...\n";
        
        // Get current columns
        $stmt = $pdo->query("DESCRIBE attendance");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo "Current columns: " . implode(', ', $columns) . "\n";
        
        // Add missing columns
        $requiredColumns = [
            'ekspresi_masuk' => "ALTER TABLE attendance ADD COLUMN ekspresi_masuk VARCHAR(50) NULL AFTER jam_masuk_iso",
            'screenshot_masuk' => "ALTER TABLE attendance ADD COLUMN screenshot_masuk LONGTEXT NULL AFTER ekspresi_masuk",
            'ekspresi_pulang' => "ALTER TABLE attendance ADD COLUMN ekspresi_pulang VARCHAR(50) NULL AFTER jam_pulang_iso",
            'screenshot_pulang' => "ALTER TABLE attendance ADD COLUMN screenshot_pulang LONGTEXT NULL AFTER ekspresi_pulang",
            'status' => "ALTER TABLE attendance ADD COLUMN status ENUM('ontime','terlambat') DEFAULT 'ontime' AFTER ekspresi_pulang",
            'ket' => "ALTER TABLE attendance ADD COLUMN ket ENUM('hadir','izin','sakit','alpha','wfh') DEFAULT 'hadir' AFTER status",
            'daily_report_id' => "ALTER TABLE attendance ADD COLUMN daily_report_id INT NULL AFTER ket"
        ];
        
        foreach ($requiredColumns as $column => $sql) {
            if (!in_array($column, $columns)) {
                echo "Adding missing column: $column\n";
                try {
                    $pdo->exec($sql);
                    echo "Column $column added successfully.\n";
                } catch (PDOException $e) {
                    echo "Error adding column $column: " . $e->getMessage() . "\n";
                }
            } else {
                echo "Column $column already exists.\n";
            }
        }
        
        // Update ket column enum if needed
        try {
            $pdo->exec("ALTER TABLE attendance MODIFY ket ENUM('hadir', 'izin', 'sakit', 'alpha', 'wfh') DEFAULT 'hadir'");
            echo "Updated ket column enum successfully.\n";
        } catch (PDOException $e) {
            echo "Note: ket column enum update: " . $e->getMessage() . "\n";
        }
    }
    
    // Final verification
    $stmt = $pdo->query("DESCRIBE attendance");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\nFinal attendance table columns: " . implode(', ', $finalColumns) . "\n";
    
    // Check for required columns
    $requiredColumns = ['id', 'user_id', 'jam_masuk', 'jam_masuk_iso', 'ekspresi_masuk', 'screenshot_masuk', 'jam_pulang', 'jam_pulang_iso', 'ekspresi_pulang', 'screenshot_pulang', 'status', 'ket'];
    $missingColumns = array_diff($requiredColumns, $finalColumns);
    
    if (empty($missingColumns)) {
        echo "All required columns are present!\n";
    } else {
        echo "Missing columns: " . implode(', ', $missingColumns) . "\n";
    }
    
    echo "\nDatabase fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
