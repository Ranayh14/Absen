<?php
require_once 'pages/layout_header.php';

try {
    $pdo = getPdo();
    $sql = "CREATE TABLE IF NOT EXISTS admin_help_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        request_type ENUM('past_attendance', 'late_attendance', 'bug_report') NOT NULL,
        alasan_izin TEXT NULL,
        jenis_izin ENUM('izin', 'sakit') NULL,
        bukti_izin LONGTEXT NULL,
        tanggal DATE NULL,
        jam_masuk TIME NULL,
        jam_pulang TIME NULL,
        bukti_presensi LONGTEXT NULL,
        lokasi_presensi TEXT NULL,
        bug_description TEXT NULL,
        bug_proof LONGTEXT NULL,
        status ENUM('pending', 'approved', 'disapproved') DEFAULT 'pending',
        admin_note TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        CONSTRAINT fk_help_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    
    $pdo->exec($sql);
    echo "Table 'admin_help_requests' created successfully.\n";
} catch (Exception $e) {
    echo "Error creating table: " . $e->getMessage() . "\n";
}
