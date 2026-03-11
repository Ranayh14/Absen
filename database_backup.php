<?php
/**
 * Database Backup System
 * Sistem backup database otomatis untuk aplikasi absensi
 */

// Database configuration
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

/**
 * Create database backup using PHP/PDO (works on hosting without mysqldump)
 * @param PDO|null $pdo Optional PDO connection, will create if not provided
 * @return array Result array with success status and message
 */
function createDatabaseBackupPHP(?PDO $pdo = null): array {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    // Increase limits for large database backups
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');
    
    $backupDir = __DIR__ . '/database_backup';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
    $backupFile = $backupDir . '/absen_db_backup.sql';
    
    try {
        // Create PDO connection if not provided
        if (!$pdo) {
            $pdo = new PDO(
                "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
                $DB_USER,
                $DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        }
        
        $fp = @fopen($backupFile, 'w');
        if (!$fp) {
            return ['ok' => false, 'success' => false, 'message' => 'Gagal membuka file backup untuk penulisan'];
        }

        fwrite($fp, "-- Database Backup\n");
        fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Database: " . $DB_NAME . "\n");
        fwrite($fp, "-- Host: " . $DB_HOST . "\n");
        fwrite($fp, "-- Backup Method: PHP/PDO (Chunked)\n\n");
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            fwrite($fp, "-- Table structure for `{$table}`\n");
            fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;\n");
            
            // Get table structure
            $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
            fwrite($fp, $createTable['Create Table'] . ";\n\n");
            
            // Get table data
            $stmt = $pdo->query("SELECT * FROM `{$table}`");
            $hasData = false;
            
            while ($row = $stmt->fetch()) {
                if (!$hasData) {
                    fwrite($fp, "-- Data for table `{$table}`\n");
                    $columns = array_keys($row);
                    $columnList = '`' . implode('`, `', $columns) . '`';
                    $hasData = true;
                }
                
                $values = [];
                foreach ($row as $value) {
                    $values[] = ($value === null) ? 'NULL' : $pdo->quote($value);
                }
                fwrite($fp, "INSERT INTO `{$table}` ({$columnList}) VALUES (" . implode(', ', $values) . ");\n");
            }
            if ($hasData) fwrite($fp, "\n");
        }
        
        fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fp);
        
        $fileSize = filesize($backupFile);
        
        return [
            'ok' => true, 
            'success' => true,
            'message' => 'Backup berhasil dibuat dan disimpan ke file (Chunked)',
            'sql_content' => '(SQL content stripped to save memory. Use file path: ' . basename($backupFile) . ')',
            'size' => $fileSize,
            'file_saved' => true,
            'file' => $backupFile
        ];
        
    } catch (Exception $e) {
        return ['ok' => false, 'success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Create database backup using mysqldump (fallback for local development)
 * @return array Result array with success status and message
 */
function createDatabaseBackup(): array {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    try {
        // Try PHP-based backup first (works on hosting)
        $pdo = new PDO(
            "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
            $DB_USER,
            $DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
        
        $result = createDatabaseBackupPHP($pdo);
        
        // If PHP backup succeeded, return it
        if ($result['ok'] || ($result['success'] ?? false)) {
            return $result;
        }
        
        // Fallback to mysqldump for local development
        // Ensure backup directory exists
        $backupDir = __DIR__ . '/database_backup';
        if (!is_dir($backupDir)) {
            if (!mkdir($backupDir, 0755, true)) {
                return ['ok' => false, 'success' => false, 'message' => 'Gagal membuat direktori backup'];
            }
        }
        
        // Check if directory is writable
        if (!is_writable($backupDir)) {
            return ['ok' => false, 'success' => false, 'message' => 'Direktori backup tidak dapat ditulis'];
        }
        
        // Backup file path
        $backupFile = $backupDir . '/absen_db_backup.sql';
        
        // Remove old backup if exists
        if (file_exists($backupFile)) {
            if (!unlink($backupFile)) {
                error_log("Warning: Gagal menghapus file backup lama");
            }
        }
        
        // Find mysqldump executable
        $mysqldumpPaths = [
            'D:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysqldump.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysqldump.exe',
            'mysqldump' // fallback to PATH
        ];
        
        $mysqldump = null;
        foreach ($mysqldumpPaths as $path) {
            if ($path === 'mysqldump' || file_exists($path)) {
                $mysqldump = $path;
                break;
            }
        }
        
        if (!$mysqldump) {
            return ['ok' => false, 'success' => false, 'message' => 'mysqldump tidak ditemukan. Pastikan MySQL/XAMPP terinstall dengan benar.'];
        }
        
        // Create mysqldump command
        $command = sprintf(
            '"%s" --host=%s --user=%s --password=%s --single-transaction --routines --triggers %s > %s',
            $mysqldump,
            escapeshellarg($DB_HOST),
            escapeshellarg($DB_USER),
            escapeshellarg($DB_PASS),
            escapeshellarg($DB_NAME),
            escapeshellarg($backupFile)
        );
        
        // Execute backup command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = 'mysqldump gagal dengan kode: ' . $returnCode;
            if (!empty($output)) {
                $errorMsg .= '. Output: ' . implode(' ', $output);
            }
            return ['ok' => false, 'success' => false, 'message' => $errorMsg];
        }
        
        // Verify backup file was created and has content
        if (!file_exists($backupFile)) {
            return ['ok' => false, 'success' => false, 'message' => 'File backup tidak dibuat'];
        }
        
        if (filesize($backupFile) === 0) {
            unlink($backupFile);
            return ['ok' => false, 'success' => false, 'message' => 'File backup kosong'];
        }
        
        // Add backup info header
        $backupInfo = "-- Database Backup\n";
        $backupInfo .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $backupInfo .= "-- Database: " . $DB_NAME . "\n";
        $backupInfo .= "-- Host: " . $DB_HOST . "\n\n";
        
        $content = file_get_contents($backupFile);
        file_put_contents($backupFile, $backupInfo . $content);
        
        return [
            'ok' => true, 
            'success' => true,
            'message' => 'Backup berhasil dibuat',
            'file' => $backupFile,
            'size' => filesize($backupFile)
        ];
        
    } catch (Exception $e) {
        return ['ok' => false, 'success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Restore database from backup file
 * @param string $backupFile Path to backup file
 * @return array Result array with success status and message
 */
function restoreDatabaseFromBackup(string $backupFile): array {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    try {
        if (!file_exists($backupFile)) {
            return ['ok' => false, 'success' => false, 'message' => 'File backup tidak ditemukan'];
        }
        
        // Find mysql executable
        $mysqlPaths = [
            'D:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 8.0\\bin\\mysql.exe',
            'C:\\Program Files\\MySQL\\MySQL Server 5.7\\bin\\mysql.exe',
            'mysql' // fallback to PATH
        ];
        
        $mysql = null;
        foreach ($mysqlPaths as $path) {
            if ($path === 'mysql' || file_exists($path)) {
                $mysql = $path;
                break;
            }
        }
        
        if (!$mysql) {
            return ['ok' => false, 'success' => false, 'message' => 'mysql tidak ditemukan. Pastikan MySQL/XAMPP terinstall dengan benar.'];
        }
        
        // Create mysql command for restore
        $command = sprintf(
            '"%s" --host=%s --user=%s --password=%s %s < %s',
            $mysql,
            escapeshellarg($DB_HOST),
            escapeshellarg($DB_USER),
            escapeshellarg($DB_PASS),
            escapeshellarg($DB_NAME),
            escapeshellarg($backupFile)
        );
        
        // Execute restore command
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            $errorMsg = 'mysql restore gagal dengan kode: ' . $returnCode;
            if (!empty($output)) {
                $errorMsg .= '. Output: ' . implode(' ', $output);
            }
            return ['ok' => false, 'success' => false, 'message' => $errorMsg];
        }
        
        return ['ok' => true, 'success' => true, 'message' => 'Database berhasil di-restore'];
        
    } catch (Exception $e) {
        return ['ok' => false, 'success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

/**
 * Get backup file information
 * @return array Backup info array
 */
function getBackupInfo(): array {
    $backupFile = __DIR__ . '/database_backup/absen_db_backup.sql';
    
    if (!file_exists($backupFile)) {
        return [
            'exists' => false,
            'file' => $backupFile,
            'size' => 0,
            'created' => null
        ];
    }
    
    return [
        'exists' => true,
        'file' => $backupFile,
        'size' => filesize($backupFile),
        'created' => date('Y-m-d H:i:s', filemtime($backupFile)),
        'size_formatted' => formatBytes(filesize($backupFile))
    ];
}

/**
 * Format bytes to human readable format
 * @param int $bytes Number of bytes
 * @return string Formatted string
 */
function formatBytes(int $bytes): string {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// If called directly from command line
if (php_sapi_name() === 'cli') {
    echo "Creating database backup...\n";
    $result = createDatabaseBackup();
    
    if ($result['ok']) {
        echo "✅ " . $result['message'] . "\n";
        echo "File: " . $result['file'] . "\n";
        echo "Size: " . formatBytes($result['size']) . "\n";
    } else {
        echo "❌ " . $result['message'] . "\n";
        exit(1);
    }
}
?>
