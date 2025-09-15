<?php
/**
 * Database Backup System
 * Fungsi untuk menyimpan backup database secara otomatis setiap ada update
 */

// Database configuration
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

// Path untuk menyimpan backup
$BACKUP_DIR = __DIR__ . '/database_backup/';
$BACKUP_FILENAME = 'absen_db_backup.sql';

/**
 * Membuat backup database ke file SQL
 * @return bool|string True jika berhasil, error message jika gagal
 */
function createDatabaseBackup() {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $BACKUP_DIR, $BACKUP_FILENAME;
    
    try {
        // Pastikan folder backup ada
        if (!is_dir($BACKUP_DIR)) {
            if (!mkdir($BACKUP_DIR, 0755, true)) {
                return "Gagal membuat folder backup";
            }
        }
        
        // Hapus file backup lama jika ada
        $backupPath = $BACKUP_DIR . $BACKUP_FILENAME;
        if (file_exists($backupPath)) {
            if (!unlink($backupPath)) {
                return "Gagal menghapus file backup lama";
            }
        }
        
        // Koneksi ke database
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        
        // Mulai membuat file SQL
        $sql = "-- Database Backup untuk $DB_NAME\n";
        $sql .= "-- Dibuat pada: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- ================================================\n\n";
        
        // Set charset
        $sql .= "SET NAMES utf8mb4;\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
        
        // Ambil semua tabel
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $sql .= "-- Struktur tabel `$table`\n";
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            
            // Ambil struktur tabel
            $createTable = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= $createTable['Create Table'] . ";\n\n";
            
            // Ambil data dari tabel
            $sql .= "-- Data untuk tabel `$table`\n";
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll();
            
            if (!empty($rows)) {
                // Ambil nama kolom
                $columns = array_keys($rows[0]);
                $columnList = '`' . implode('`, `', $columns) . '`';
                
                $sql .= "INSERT INTO `$table` ($columnList) VALUES\n";
                
                $values = [];
                foreach ($rows as $row) {
                    $rowValues = [];
                    foreach ($row as $value) {
                        if ($value === null) {
                            $rowValues[] = 'NULL';
                        } else {
                            // Escape nilai untuk SQL
                            $rowValues[] = "'" . addslashes($value) . "'";
                        }
                    }
                    $values[] = '(' . implode(', ', $rowValues) . ')';
                }
                
                $sql .= implode(",\n", $values) . ";\n\n";
            } else {
                $sql .= "-- Tidak ada data untuk tabel `$table`\n\n";
            }
        }
        
        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";
        $sql .= "-- Backup selesai\n";
        
        // Tulis ke file
        if (file_put_contents($backupPath, $sql) === false) {
            return "Gagal menulis file backup";
        }
        
        // Log backup
        error_log("Database backup berhasil dibuat: $backupPath");
        
        return true;
        
    } catch (PDOException $e) {
        error_log("Error backup database: " . $e->getMessage());
        return "Error database: " . $e->getMessage();
    } catch (Exception $e) {
        error_log("Error backup: " . $e->getMessage());
        return "Error: " . $e->getMessage();
    }
}

/**
 * Menghapus file backup lama (jika ada lebih dari 1 file)
 * @return bool True jika berhasil
 */
function cleanupOldBackups() {
    global $BACKUP_DIR, $BACKUP_FILENAME;
    
    try {
        if (!is_dir($BACKUP_DIR)) {
            return true;
        }
        
        $files = glob($BACKUP_DIR . '*.sql');
        $backupPath = $BACKUP_DIR . $BACKUP_FILENAME;
        
        // Hapus semua file backup kecuali yang utama
        foreach ($files as $file) {
            if ($file !== $backupPath && file_exists($file)) {
                unlink($file);
            }
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error cleanup backup: " . $e->getMessage());
        return false;
    }
}

/**
 * Fungsi utama untuk backup database
 * Dipanggil setiap ada update data
 * @return array Status backup
 */
function backupDatabase() {
    $result = [
        'success' => false,
        'message' => '',
        'backup_path' => '',
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    // Cleanup file lama terlebih dahulu
    cleanupOldBackups();
    
    // Buat backup baru
    $backupResult = createDatabaseBackup();
    
    if ($backupResult === true) {
        $result['success'] = true;
        $result['message'] = 'Backup database berhasil dibuat';
        $result['backup_path'] = __DIR__ . '/database_backup/absen_db_backup.sql';
    } else {
        $result['message'] = $backupResult;
    }
    
    return $result;
}

/**
 * Cek status backup terakhir
 * @return array Info backup
 */
function getBackupInfo() {
    global $BACKUP_DIR, $BACKUP_FILENAME;
    
    $backupPath = $BACKUP_DIR . $BACKUP_FILENAME;
    
    $info = [
        'exists' => false,
        'file_size' => 0,
        'last_modified' => null,
        'file_path' => $backupPath
    ];
    
    if (file_exists($backupPath)) {
        $info['exists'] = true;
        $info['file_size'] = filesize($backupPath);
        $info['last_modified'] = date('Y-m-d H:i:s', filemtime($backupPath));
    }
    
    return $info;
}

// Jika file ini dipanggil langsung (untuk testing)
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "=== Database Backup System ===\n";
    echo "Memulai backup database...\n\n";
    
    $result = backupDatabase();
    
    if ($result['success']) {
        echo "✓ Backup berhasil!\n";
        echo "File: " . $result['backup_path'] . "\n";
        echo "Waktu: " . $result['timestamp'] . "\n";
        
        $info = getBackupInfo();
        if ($info['exists']) {
            echo "Ukuran file: " . number_format($info['file_size'] / 1024, 2) . " KB\n";
        }
    } else {
        echo "✗ Backup gagal: " . $result['message'] . "\n";
    }
    
    echo "\n=== Info Backup ===\n";
    $info = getBackupInfo();
    if ($info['exists']) {
        echo "File backup ada: Ya\n";
        echo "Ukuran: " . number_format($info['file_size'] / 1024, 2) . " KB\n";
        echo "Terakhir diubah: " . $info['last_modified'] . "\n";
    } else {
        echo "File backup ada: Tidak\n";
    }
}
?>
