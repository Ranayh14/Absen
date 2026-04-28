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
 * @param string $type Backup type: 'standard' or 'laravel'
 * @return array Result array with success status and message
 */
function createDatabaseBackupPHP(?PDO $pdo = null, string $type = 'standard'): array {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    
    // Increase limits for large database backups
    @set_time_limit(0);
    @ini_set('memory_limit', '1024M');
    
    $backupDir = __DIR__ . '/database_backup';
    if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);
    
    $suffix = ($type === 'laravel') ? '_laravel' : '';
    $backupFile = $backupDir . '/absen_db_backup' . $suffix . '.sql';
    
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

        fwrite($fp, "-- Database Backup ({$type})\n");
        fwrite($fp, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($fp, "-- Database: " . $DB_NAME . "\n");
        fwrite($fp, "-- Host: " . $DB_HOST . "\n");
        fwrite($fp, "-- Backup Method: PHP/PDO (Chunked)\n");
        fwrite($fp, "-- phpMyAdmin compatible format\n\n");
        fwrite($fp, "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n");
        fwrite($fp, "SET time_zone = '+07:00';\n\n");
        fwrite($fp, "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;\n");
        fwrite($fp, "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;\n");
        fwrite($fp, "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;\n");
        fwrite($fp, "/*!40101 SET NAMES utf8mb4 */;\n");
        fwrite($fp, "/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;\n");
        fwrite($fp, "/*!40103 SET TIME_ZONE='+07:00' */;\n");
        fwrite($fp, "/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;\n");
        fwrite($fp, "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;\n");
        fwrite($fp, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;\n");
        fwrite($fp, "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;\n\n");
        
        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        // If Laravel type, ensure we have Laravel specific tables (or at least their schema if missing)
        $laravelTables = [];
        if ($type === 'laravel') {
            // Laravel 11 standard tables (schema only — added if not already in the DB)
            $laravelTables = [
                'cache' => "CREATE TABLE IF NOT EXISTS `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'cache_locks' => "CREATE TABLE IF NOT EXISTS `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'failed_jobs' => "CREATE TABLE IF NOT EXISTS `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'jobs' => "CREATE TABLE IF NOT EXISTS `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'job_batches' => "CREATE TABLE IF NOT EXISTS `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'password_reset_tokens' => "CREATE TABLE IF NOT EXISTS `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
                'sessions' => "CREATE TABLE IF NOT EXISTS `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;",
            ];
        }
        
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
            
            // Remove from laravelTables if it was already dumped
            if ($type === 'laravel' && isset($laravelTables[$table])) {
                unset($laravelTables[$table]);
            }
        }
        
        // Add remaining Laravel tables (schema only) if they weren't in the DB
        if ($type === 'laravel' && !empty($laravelTables)) {
            fwrite($fp, "-- Laravel Specific Tables (Schema Only)\n");
            foreach ($laravelTables as $tableName => $schema) {
                fwrite($fp, "-- Table structure for `{$tableName}`\n");
                fwrite($fp, "DROP TABLE IF EXISTS `{$tableName}`;\n");
                fwrite($fp, $schema . "\n\n");
            }
        }
        
        fwrite($fp, "\n-- Restore session variables\n");
        fwrite($fp, "/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;\n");
        fwrite($fp, "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;\n");
        fwrite($fp, "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;\n");
        fwrite($fp, "/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;\n");
        fwrite($fp, "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;\n");
        fwrite($fp, "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;\n");
        fwrite($fp, "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;\n");
        fwrite($fp, "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;\n");
        fwrite($fp, "\n-- End of backup\n");
        fclose($fp);
        
        $fileSize = filesize($backupFile);
        
        return [
            'ok' => true, 
            'success' => true,
            'message' => 'Backup ' . ucfirst($type) . ' berhasil dibuat dan disimpan ke file (Chunked)',
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
 * @param string $type Backup type: 'standard' or 'laravel'
 * @return array Result array with success status and message
 */
function createDatabaseBackup(string $type = 'standard'): array {
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
        
        $result = createDatabaseBackupPHP($pdo, $type);
        
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
 * @param string $type Backup type: 'standard' or 'laravel'
 * @return array Backup info array
 */
function getBackupInfo(string $type = 'standard'): array {
    $suffix = ($type === 'laravel') ? '_laravel' : '';
    $backupFile = __DIR__ . '/database_backup/absen_db_backup' . $suffix . '.sql';
    
    if (!file_exists($backupFile)) {
        return [
            'exists' => false,
            'file' => basename($backupFile),
            'size' => 0,
            'created' => null,
            'type' => $type
        ];
    }
    
    return [
        'exists' => true,
        'file' => basename($backupFile),
        'size' => filesize($backupFile),
        'created' => date('Y-m-d H:i:s', filemtime($backupFile)),
        'size_formatted' => formatBytes(filesize($backupFile)),
        'type' => $type
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
    if ($bytes === 0) return '0 B';
    $pow = floor(log($bytes) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= (1 << (10 * $pow));
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// If called directly from command line
if (php_sapi_name() === 'cli') {
    $type = $argv[1] ?? 'standard';
    if (!in_array($type, ['standard', 'laravel'])) {
        echo "Usage: php database_backup.php [standard|laravel]\n";
        exit(1);
    }
    
    echo "Creating database backup ({$type})...\n";
    $result = createDatabaseBackup($type);
    
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
