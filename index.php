<?php
session_start();

ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'php-error.log');
error_reporting(E_ALL);
// Optional: keep errors off the screen in prod
ini_set('display_errors', '0');

error_log('bootstrap: index.php started');

// ----- CONFIG -----
// Change if needed for your XAMPP/MySQL setup
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

// Include database backup functions (if exists)
if (file_exists('database_backup.php')) {
    require_once 'database_backup.php';
}

// Default admin (seeded if not exists)
$DEFAULT_ADMIN_EMAIL = 'admin@example.com';
$DEFAULT_ADMIN_PASSWORD = 'admin123';

// ----- DB SETUP -----
function getPdo(): PDO {
    global $DB_HOST, $DB_USER, $DB_PASS, $DB_NAME;
    try {
        // Try direct connect (db may already exist)
        $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    } catch (PDOException $e) {
        // Create database if missing, then connect again
        try {
            $pdoRoot = new PDO("mysql:host=$DB_HOST;charset=utf8mb4", $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]);
            $pdoRoot->exec("CREATE DATABASE IF NOT EXISTS `$DB_NAME` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdoRoot = null;
            $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            return $pdo;
        } catch (PDOException $e2) {
            // If we can't even connect to MySQL, return a proper error
            error_log("Database connection failed: " . $e2->getMessage());
            throw new Exception("Database connection failed");
        }
    }
}

function ensureSchema(PDO $pdo): void {
    // users: role admin/pegawai, foto disimpan base64 data URL
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            role ENUM('admin','pegawai') NOT NULL DEFAULT 'pegawai',
            email VARCHAR(255) NOT NULL UNIQUE,
            nim VARCHAR(100) NULL UNIQUE,
            nama VARCHAR(255) NOT NULL,
            prodi VARCHAR(255) NULL,
            startup VARCHAR(255) NULL,
                    foto_base64 LONGTEXT NULL,
                    face_embedding LONGTEXT NULL,
                    face_embedding_updated TIMESTAMP NULL,
                    advanced_features LONGTEXT NULL,
                    facial_geometry LONGTEXT NULL,
                    feature_vector LONGTEXT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // attendance
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            jam_masuk VARCHAR(20) NULL,
            jam_masuk_iso DATETIME NULL,
            ekspresi_masuk VARCHAR(50) NULL,
            screenshot_masuk LONGTEXT NULL,
            lokasi_masuk VARCHAR(255) NULL,
            lat_masuk DECIMAL(10,7) NULL,
            lng_masuk DECIMAL(10,7) NULL,
            jam_pulang VARCHAR(20) NULL,
            jam_pulang_iso DATETIME NULL,
            ekspresi_pulang VARCHAR(50) NULL,
            screenshot_pulang LONGTEXT NULL,
            lokasi_pulang VARCHAR(255) NULL,
            lat_pulang DECIMAL(10,7) NULL,
            lng_pulang DECIMAL(10,7) NULL,
            status ENUM('ontime','terlambat') DEFAULT 'ontime',
            ket ENUM('wfo','izin','sakit','alpha','wfa') DEFAULT 'wfo',
            alasan_wfa TEXT NULL,
            alasan_izin_sakit TEXT NULL,
            bukti_izin_sakit LONGTEXT NULL,
            daily_report_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // settings table for admin configuration
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            description TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // manual_holidays table for admin-defined off days (e.g., demo/disaster)
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS manual_holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            date DATE NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            created_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(date),
            CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // employee_work_schedule table for individual work schedules
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS employee_work_schedule (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            day_of_week ENUM('monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday') NOT NULL,
            is_working_day BOOLEAN DEFAULT TRUE,
            start_time TIME DEFAULT '08:00:00',
            end_time TIME DEFAULT '17:00:00',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX(user_id),
            CONSTRAINT fk_schedule_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            UNIQUE KEY unique_user_day (user_id, day_of_week)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // Add missing columns if they don't exist (for existing databases)
    $requiredColumns = [
        'ekspresi_masuk' => "ALTER TABLE attendance ADD COLUMN ekspresi_masuk VARCHAR(50) NULL AFTER jam_masuk_iso",
        'ekspresi_pulang' => "ALTER TABLE attendance ADD COLUMN ekspresi_pulang VARCHAR(50) NULL AFTER jam_pulang_iso",
        'screenshot_masuk' => "ALTER TABLE attendance ADD COLUMN screenshot_masuk LONGTEXT NULL AFTER ekspresi_masuk",
        'screenshot_pulang' => "ALTER TABLE attendance ADD COLUMN screenshot_pulang LONGTEXT NULL AFTER ekspresi_pulang",
        'status' => "ALTER TABLE attendance ADD COLUMN status ENUM('ontime','terlambat') DEFAULT 'ontime' AFTER ekspresi_pulang",
        'ket' => "ALTER TABLE attendance ADD COLUMN ket ENUM('wfo','izin','sakit','alpha','wfa','overtime') DEFAULT 'wfo' AFTER status",
        'lokasi_masuk' => "ALTER TABLE attendance ADD COLUMN lokasi_masuk VARCHAR(255) NULL AFTER screenshot_masuk",
        'lat_masuk' => "ALTER TABLE attendance ADD COLUMN lat_masuk DECIMAL(10,7) NULL AFTER lokasi_masuk",
        'lng_masuk' => "ALTER TABLE attendance ADD COLUMN lng_masuk DECIMAL(10,7) NULL AFTER lat_masuk",
        'lokasi_pulang' => "ALTER TABLE attendance ADD COLUMN lokasi_pulang VARCHAR(255) NULL AFTER screenshot_pulang",
        'lat_pulang' => "ALTER TABLE attendance ADD COLUMN lat_pulang DECIMAL(10,7) NULL AFTER lokasi_pulang",
        'lng_pulang' => "ALTER TABLE attendance ADD COLUMN lng_pulang DECIMAL(10,7) NULL AFTER lat_pulang",
        'alasan_wfa' => "ALTER TABLE attendance ADD COLUMN alasan_wfa TEXT NULL AFTER ket",
        'alasan_izin_sakit' => "ALTER TABLE attendance ADD COLUMN alasan_izin_sakit TEXT NULL AFTER alasan_wfa",
        'bukti_izin_sakit' => "ALTER TABLE attendance ADD COLUMN bukti_izin_sakit LONGTEXT NULL AFTER alasan_izin_sakit",
        'daily_report_id' => "ALTER TABLE attendance ADD COLUMN daily_report_id INT NULL AFTER ket"
    ];
    
            // Add FaceNet embedding columns to users table
            $userColumns = [
                'face_embedding' => "ALTER TABLE users ADD COLUMN face_embedding LONGTEXT NULL AFTER foto_base64",
                'face_embedding_updated' => "ALTER TABLE users ADD COLUMN face_embedding_updated TIMESTAMP NULL AFTER face_embedding",
                'advanced_features' => "ALTER TABLE users ADD COLUMN advanced_features LONGTEXT NULL AFTER face_embedding_updated",
                'facial_geometry' => "ALTER TABLE users ADD COLUMN facial_geometry LONGTEXT NULL AFTER advanced_features",
                'feature_vector' => "ALTER TABLE users ADD COLUMN feature_vector LONGTEXT NULL AFTER facial_geometry"
            ];
    
    foreach ($requiredColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }
    
    // Add FaceNet embedding columns to users table
    foreach ($userColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }
    
    // Update ket column enum to include 'overtime'
    try { 
        $pdo->exec("ALTER TABLE attendance MODIFY ket ENUM('wfo','izin','sakit','alpha','wfa','overtime') DEFAULT 'wfo'"); 
    } catch (PDOException $e) {
        // Ignore error if column doesn't exist or enum is already correct
    }

    // Fix manual_holidays table structure if needed
    try {
        // Check if table exists
        $checkTable = $pdo->query("SHOW TABLES LIKE 'manual_holidays'");
        if ($checkTable->rowCount() > 0) {
            // Check if created_by column exists
            $checkColumn = $pdo->query("SHOW COLUMNS FROM manual_holidays LIKE 'created_by'");
            if ($checkColumn->rowCount() == 0) {
                // Add created_by column if it doesn't exist
                $pdo->exec("ALTER TABLE manual_holidays ADD COLUMN created_by INT NULL AFTER name");
                $pdo->exec("ALTER TABLE manual_holidays ADD CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
            }
        } else {
            // Table doesn't exist, create it
            $pdo->exec("
                CREATE TABLE manual_holidays (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    date DATE NOT NULL UNIQUE,
                    name VARCHAR(255) NOT NULL,
                    created_by INT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX(date),
                    CONSTRAINT fk_manual_holidays_creator FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        }
    } catch (PDOException $e) {
        error_log("Error fixing manual_holidays table: " . $e->getMessage());
    }

    // Daily reports table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS daily_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            report_date DATE NOT NULL,
            content TEXT NULL,
            status ENUM('pending','approved','disapproved') DEFAULT 'pending',
            evaluation TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_date (user_id, report_date),
            CONSTRAINT fk_dr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Attendance notes table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS attendance_notes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            date DATE NOT NULL,
            type ENUM('izin','sakit') NOT NULL,
            keterangan TEXT NOT NULL,
            bukti LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            UNIQUE KEY unique_user_date (user_id, date),
            CONSTRAINT fk_an_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Monthly reports table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS monthly_reports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            year INT NOT NULL,
            month INT NOT NULL,
            summary TEXT NULL,
            achievements JSON NULL,
            obstacles JSON NULL,
            status ENUM('draft','belum di approve','approved','disapproved') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_month (user_id, year, month),
            CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // Update existing monthly_reports table to use new ENUM values
    try {
        $pdo->exec("ALTER TABLE monthly_reports MODIFY COLUMN status ENUM('draft','belum di approve','approved','disapproved') DEFAULT 'draft'");
        // Update any existing 'submitted' status to 'belum di approve'
        $pdo->exec("UPDATE monthly_reports SET status = 'belum di approve' WHERE status = 'submitted'");
    } catch (PDOException $e) {
        // Ignore if column doesn't exist or already updated
        error_log("Monthly reports table update: " . $e->getMessage());
    }
}

function verifyAttendanceTable(PDO $pdo): bool {
    try {
        // Check if attendance table exists and has required columns
        $stmt = $pdo->query("DESCRIBE attendance");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $requiredColumns = ['id', 'user_id', 'jam_masuk', 'jam_masuk_iso', 'ekspresi_masuk', 'screenshot_masuk', 'jam_pulang', 'jam_pulang_iso', 'ekspresi_pulang', 'screenshot_pulang', 'status', 'ket'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            error_log("Missing columns in attendance table: " . implode(', ', $missingColumns));
            return false;
        }
        
        return true;
    } catch (PDOException $e) {
        error_log("Error verifying attendance table: " . $e->getMessage());
        return false;
    }
}

function seedAdmin(PDO $pdo, string $email, string $password): void {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE role='admin' LIMIT 1");
    $stmt->execute();
    $existing = $stmt->fetch();
    if (!$existing) {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password_hash) VALUES ('admin', :email, NULL, 'Administrator', NULL, NULL, NULL, :hash)");
        $stmt->execute([':email' => $email, ':hash' => $hash]);
    }
}

function seedDefaultSettings(PDO $pdo): void {
    $defaultSettings = [
        ['max_ontime_hour', '08', 'Jam maksimal untuk dianggap ontime (format 24 jam)'],
        ['min_checkout_hour', '17', 'Jam minimal untuk bisa presensi pulang (format 24 jam)'],
        ['wfo_address', 'Fakultas Ilmu Terapan, Jl. Telekomunikasi, Bandung', 'Nama alamat pusat WFO (akan di-geocode)'],
        ['wfo_lat', '-6.9738', 'Latitude pusat WFO'],
        ['wfo_lng', '107.6300', 'Longitude pusat WFO'],
        ['wfo_radius_m', '1200', 'Radius wilayah WFO dalam meter'],
        // WFO detection via IP API settings
        ['wfo_mode', 'api', 'Mode deteksi WFO: api atau coordinate'],
        ['wfo_api_provider', 'ipinfo', 'Provider IP API: ipinfo | ipapi | ip-api'],
        ['wfo_api_token', '', 'Token API (opsional tergantung provider)'],
        ['wfo_api_org_keywords', 'Telkom University, Yayasan Pendidikan Telkom, Telkom University Bandung', 'Daftar kata kunci organisasi yang dianggap WFO (dipisah koma)'],
        ['wfo_api_asn_list', '', 'Daftar ASN yang dianggap WFO (contoh: AS7713), dipisah koma'],
        ['wfo_api_cidr_list', '', 'Daftar CIDR yang dianggap WFO (contoh: 103.23.44.0/22), dipisah koma'],
        ['attendance_period_end', date('Y-12-31'), 'Tanggal akhir periode perhitungan absen (YYYY-MM-DD)'],
        ['kpi_late_penalty_per_minute', '1', 'Pengurangan KPI per menit terlambat (%)'],
        ['kpi_izin_sakit_score', '85', 'Nilai KPI untuk izin/sakit (%)'],
        ['kpi_alpha_score', '0', 'Nilai KPI untuk alpha (%)'],
        ['kpi_overtime_bonus', '5', 'Bonus KPI untuk overtime (%)']
    ];
    
    foreach ($defaultSettings as $setting) {
        $stmt = $pdo->prepare("SELECT id FROM settings WHERE setting_key = :key LIMIT 1");
        $stmt->execute([':key' => $setting[0]]);
        $existing = $stmt->fetch();
        
        if (!$existing) {
            $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value, description) VALUES (:key, :value, :desc)");
            $stmt->execute([':key' => $setting[0], ':value' => $setting[1], ':desc' => $setting[2]]);
        }
    }
}

/**
 * Geocode a free-form address string to [lat, lng] using OpenStreetMap Nominatim.
 * Returns ['lat' => float, 'lng' => float] or null on failure.
 */
function geocodeAddress(string $address): ?array {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&q=' . urlencode($address);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 4);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AbsenApp/1.0 (XAMPP PHP)'
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) return null;
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) return null;
    return ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon']];
}

/**
 * Reverse geocode coordinates to address using OpenStreetMap Nominatim.
 * Returns readable address string or null on failure.
 */
function reverseGeocodeAddress(float $lat, float $lng): ?string {
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . $lat . '&lon=' . $lng . '&addressdetails=1&accept-language=id';
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AbsenApp/1.0 (XAMPP PHP)'
    ]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200 || !$resp) return null;
    
    $data = json_decode($resp, true);
    if (!is_array($data) || !isset($data['address'])) return null;
    
    $address = $data['address'];
    $displayName = $data['display_name'] ?? '';
    
    // Build readable address from components
    $parts = [];
    
    // Try to get specific location names
    if (isset($address['building']) && $address['building']) {
        $parts[] = $address['building'];
    } elseif (isset($address['house_name']) && $address['house_name']) {
        $parts[] = $address['house_name'];
    }
    
    if (isset($address['road']) && $address['road']) {
        $parts[] = $address['road'];
    } elseif (isset($address['pedestrian']) && $address['pedestrian']) {
        $parts[] = $address['pedestrian'];
    } elseif (isset($address['footway']) && $address['footway']) {
        $parts[] = $address['footway'];
    }
    
    if (isset($address['suburb']) && $address['suburb']) {
        $parts[] = $address['suburb'];
    } elseif (isset($address['neighbourhood']) && $address['neighbourhood']) {
        $parts[] = $address['neighbourhood'];
    }
    
    if (isset($address['city']) && $address['city']) {
        $parts[] = $address['city'];
    } elseif (isset($address['town']) && $address['town']) {
        $parts[] = $address['town'];
    } elseif (isset($address['village']) && $address['village']) {
        $parts[] = $address['village'];
    }
    
    if (isset($address['state']) && $address['state']) {
        $parts[] = $address['state'];
    }
    
    // If we have good parts, join them
    if (!empty($parts)) {
        return implode(', ', $parts);
    }
    
    // Fallback to display_name if available
    if ($displayName) {
        // Clean up the display name
        $cleanName = preg_replace('/,\s*Indonesia$/', '', $displayName);
        return $cleanName;
    }
    
    return null;
}

/** Check if IP within CIDR */
function ipInCidr(string $ip, string $cidr): bool {
    if (!str_contains($cidr, '/')) return false;
    [$subnet, $mask] = explode('/', $cidr, 2);
    $mask = (int)$mask;
    if (!filter_var($ip, FILTER_VALIDATE_IP) || !filter_var($subnet, FILTER_VALIDATE_IP)) return false;
    $ipLong = ip2long($ip);
    $subnetLong = ip2long($subnet);
    $maskLong = -1 << (32 - $mask);
    $subnetBase = $subnetLong & $maskLong;
    return ($ipLong & $maskLong) === $subnetBase;
}

/**
 * Fetch public IP info from provider
 */
function fetchPublicIpInfo(string $ip, string $provider, string $token = ''): array {
    $url = '';
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];
    if ($provider === 'ipinfo') {
        $url = 'https://ipinfo.io/' . urlencode($ip) . '/json' . ($token ? ('?token=' . urlencode($token)) : '');
    } elseif ($provider === 'ipapi') {
        $url = 'https://ipapi.co/' . urlencode($ip) . '/json/';
        if ($token) $headers[] = 'Authorization: Bearer ' . $token;
    } else { // ip-api
        $url = 'http://ip-api.com/json/' . urlencode($ip) . '?fields=status,message,org,as,asname,query';
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) return [];
    $data = json_decode($resp, true);
    if (!is_array($data)) return [];

    // Normalize fields
    $org = '';
    $asn = '';
    if ($provider === 'ipinfo') {
        $org = $data['company']['name'] ?? ($data['org'] ?? '');
        $asn = isset($data['org']) ? strtoupper(strtok($data['org'], ' ')) : '';
    } elseif ($provider === 'ipapi') {
        $org = $data['org'] ?? ($data['company'] ?? '');
        $asn = strtoupper($data['asn'] ?? ($data['as'] ?? ''));
    } else { // ip-api
        $org = $data['org'] ?? ($data['asname'] ?? '');
        $asn = strtoupper($data['as'] ?? '');
        if ($asn && !str_starts_with($asn, 'AS')) {
            $asn = strtoupper(strtok($asn, ' '));
        }
    }

    return [
        'org' => (string)$org,
        'asn' => (string)$asn,
        'raw' => $data,
    ];
}

/**
 * Detect WFO by external IP information API
 * Returns true if public IP belongs to allowed org/ASN/CIDR list
 */
function isWfoByApi(PDO $pdo, ?string $publicIp = null): bool {
    $provider = strtolower(trim(getSetting($pdo, 'wfo_api_provider', 'ipinfo')));
    $token = trim(getSetting($pdo, 'wfo_api_token', ''));
    $orgKeywords = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_org_keywords', 'Telkom University'))));
    $asnList = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_asn_list', ''))));
    $cidrList = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_api_cidr_list', ''))));

    // Determine client public IP if not provided
    if (!$publicIp) {
        $publicIp = $_POST['public_ip'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if ($publicIp && strpos($publicIp, ',') !== false) {
            $parts = explode(',', $publicIp);
            $publicIp = trim($parts[0]);
        }
    }
    if (!$publicIp || !filter_var($publicIp, FILTER_VALIDATE_IP)) {
        return false; // cannot determine
    }

    $info = fetchPublicIpInfo($publicIp, $provider, $token);
    $org = strtolower($info['org'] ?? '');
    $asn = strtoupper($info['asn'] ?? '');

    // Match org keywords
    foreach ($orgKeywords as $kw) {
        if ($kw !== '' && str_contains($org, strtolower($kw))) return true;
    }

    // Match ASN
    foreach ($asnList as $a) {
        if ($a !== '' && strtoupper(trim($a)) === $asn) return true;
    }

    // Match CIDR ranges
    foreach ($cidrList as $cidr) {
        if ($cidr !== '' && ipInCidr($publicIp, $cidr)) return true;
    }

    return false;
}

function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = :key LIMIT 1");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetch();
    return $result ? $result['setting_value'] : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value) ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = CURRENT_TIMESTAMP");
    $stmt->execute([':key' => $key, ':value' => $value]);
}

/**
 * Helper function untuk memanggil backup database setelah operasi yang mengubah data
 */
function triggerDatabaseBackup(): void {
    try {
        // Check if backup functions are available
        if (!function_exists('createDatabaseBackup')) {
            error_log("Backup functions not available");
            return;
        }
        
        // Create backup
        $backupResult = createDatabaseBackup();
        
        if (!$backupResult['success']) {
            error_log("Backup gagal: " . $backupResult['message']);
        } else {
            error_log("Backup berhasil: " . $backupResult['message'] . " (Size: " . formatBytes($backupResult['size']) . ")");
        }
    } catch (Exception $e) {
        error_log("Error dalam backup: " . $e->getMessage());
    }
}

try {
    $pdo = getPdo();
    ensureSchema($pdo);
    
    // Verify that the attendance table has all required columns
    if (!verifyAttendanceTable($pdo)) {
        error_log("Attendance table verification failed - attempting to fix schema");
        ensureSchema($pdo); // Try to fix the schema again
        if (!verifyAttendanceTable($pdo)) {
            throw new Exception("Failed to create proper attendance table schema");
        }
    }
    
    seedAdmin($pdo, $DEFAULT_ADMIN_EMAIL, $DEFAULT_ADMIN_PASSWORD);
    seedDefaultSettings($pdo);
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    if (isset($_GET['ajax'])) {
        jsonResponse(['error' => 'Database connection failed'], 500);
    }
    // For non-AJAX requests, we'll let the page load but show an error
}

// ----- HELPERS -----
function jsonResponse($data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function requireAuth(): void {
    if (!isset($_SESSION['user'])) {
        header('Location: ?page=login');
        exit;
    }
}

function isAdmin(): bool { return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'admin'; }
function isPegawai(): bool { return isset($_SESSION['user']) && $_SESSION['user']['role'] === 'pegawai'; }

// Function to check if base64 image data is too large
function checkImageSize($dataUrl, $maxSizeMB = 5) {
    if (!$dataUrl || strpos($dataUrl, 'data:image/') !== 0) {
        return ['valid' => true, 'message' => '']; // Not a valid image data URL, skip check
    }
    
    // Extract base64 data from data URL
    $data = explode(',', $dataUrl, 2);
    if (count($data) !== 2) {
        return ['valid' => true, 'message' => '']; // Invalid format, skip check
    }
    
    $imageData = base64_decode($data[1]);
    if ($imageData === false) {
        return ['valid' => true, 'message' => '']; // Failed to decode, skip check
    }
    
    $sizeInBytes = strlen($imageData);
    $sizeInMB = $sizeInBytes / (1024 * 1024);
    
    if ($sizeInMB > $maxSizeMB) {
        return [
            'valid' => false, 
            'message' => "Ukuran file terlalu besar. Maksimal {$maxSizeMB}MB. Ukuran saat ini: " . number_format($sizeInMB, 2) . "MB"
        ];
    }
    
    return ['valid' => true, 'message' => ''];
}

// Function to get first name (first word) from full name
function getFirstName($fullName) {
    if (empty($fullName)) return '';
    $nameParts = explode(' ', trim($fullName));
    return $nameParts[0];
}

// FaceNet Integration Functions
function generateFaceEmbedding($base64Image) {
    try {
        $data = [
            'action' => 'generate_embedding',
            'image' => $base64Image
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data']['embedding'];
            }
        }
        
        error_log("FaceNet embedding generation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error generating face embedding: " . $e->getMessage());
        return null;
    }
}

function recognizeFace($base64Image, $threshold = 1.0) {
    try {
        $data = [
            'action' => 'recognize_face',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("FaceNet recognition failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error recognizing face: " . $e->getMessage());
        return null;
    }
}

function saveFaceEmbedding($userId, $embedding) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("UPDATE users SET face_embedding = ?, face_embedding_updated = NOW() WHERE id = ?");
        $stmt->execute([json_encode($embedding), $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Error saving face embedding: " . $e->getMessage());
        return false;
    }
}

function getFaceEmbeddings() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT id, nim, nama, face_embedding FROM users WHERE role='pegawai' AND face_embedding IS NOT NULL");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $embeddings = [];
        foreach ($users as $user) {
            $embedding = json_decode($user['face_embedding'], true);
            if ($embedding) {
                $embeddings[$user['nim']] = $embedding;
            }
        }
        
        return $embeddings;
    } catch (Exception $e) {
        error_log("Error getting face embeddings: " . $e->getMessage());
        return [];
    }
}

function processAttendanceWithFaceNet($base64Image) {
    try {
        $data = [
            'action' => 'process_attendance',
            'image' => $base64Image,
            'threshold' => 1.0
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("FaceNet attendance processing failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error processing attendance with FaceNet: " . $e->getMessage());
        return null;
    }
}

// Enhanced FaceNet Functions
function generateEnhancedFaceEmbedding($base64Image) {
    try {
        $data = [
            'action' => 'generate_enhanced_embedding',
            'image' => $base64Image
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Enhanced FaceNet embedding generation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error generating enhanced face embedding: " . $e->getMessage());
        return null;
    }
}

// High Accuracy FaceNet Functions
function processHighAccuracyAttendance($base64Image, $userId = null) {
    try {
        $data = [
            'action' => 'process_high_accuracy_attendance',
            'image' => $base64Image
        ];
        
        if ($userId !== null) {
            $data['user_id'] = $userId;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("High accuracy attendance processing failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error processing high accuracy attendance: " . $e->getMessage());
        return null;
    }
}

// Optimized FaceNet Functions - iPhone-like Performance
function processOptimizedAttendance($base64Image, $threshold = 0.5) {
    try {
        $data = [
            'action' => 'process_attendance_optimized',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10); // Faster timeout for optimized service
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Optimized attendance processing failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error processing optimized attendance: " . $e->getMessage());
        return null;
    }
}

function recognizeFaceOptimized($base64Image, $threshold = 0.5) {
    try {
        $data = [
            'action' => 'recognize_face_optimized',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Optimized face recognition failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error in optimized face recognition: " . $e->getMessage());
        return null;
    }
}

function generateOptimizedEmbedding($base64Image) {
    try {
        $data = [
            'action' => 'generate_embedding_optimized',
            'image' => $base64Image
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Optimized embedding generation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error generating optimized embedding: " . $e->getMessage());
        return null;
    }
}

function getOptimizedPerformanceStats() {
    try {
        $data = ['action' => 'get_performance_stats'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_optimized_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Failed to get optimized performance stats: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error getting optimized performance stats: " . $e->getMessage());
        return null;
    }
}

// Ultra Accurate FaceNet Functions - Maximum Accuracy with Ultra-Fast Response
function processUltraAccurateAttendance($base64Image, $validationLevel = 'normal') {
    try {
        $data = [
            'action' => 'process_attendance_ultra_accurate',
            'image' => $base64Image,
            'validation_level' => $validationLevel
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_ultra_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Ultra-fast timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Ultra accurate attendance processing failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error processing ultra accurate attendance: " . $e->getMessage());
        return null;
    }
}

function getUltraAccuratePerformanceStats() {
    try {
        $data = ['action' => 'get_performance_stats'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_ultra_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Failed to get ultra accurate performance stats: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error getting ultra accurate performance stats: " . $e->getMessage());
        return null;
    }
}

// Direct iPhone-Level Accurate FaceNet Functions - Maximum Accuracy with Direct Processing
function processIPhoneLevelAttendance($base64Image) {
    try {
        // Direct Python execution without API
        $command = "python facenet_iphone_accurate_service.py recognize_face " . escapeshellarg($base64Image);
        
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        $executionTime = microtime(true) - $startTime;
        
        if ($returnCode === 0 && !empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result && $result['success']) {
                // Add execution time to result
                $result['execution_time'] = $executionTime;
                return $result;
            }
        }
        
        error_log("Direct iPhone-level processing failed: " . implode("\n", $output));
        return null;
    } catch (Exception $e) {
        error_log("Error in direct iPhone-level processing: " . $e->getMessage());
        return null;
    }
}

function getIPhoneLevelPerformanceStats() {
    try {
        $data = ['action' => 'get_performance_stats'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_iphone_accurate_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Failed to get iPhone-level performance stats: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error getting iPhone-level performance stats: " . $e->getMessage());
        return null;
    }
}

// Ultra Detailed FaceNet Functions - iPhone Face ID Level Accuracy with Super Detailed Features
function processUltraDetailedAttendance($base64Image) {
    try {
        // Direct Python execution without API for maximum speed
        $command = "python facenet_ultra_detailed_service.py process_attendance_ultra_detailed " . escapeshellarg($base64Image);
        
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        $executionTime = microtime(true) - $startTime;
        
        if ($returnCode === 0 && !empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result && $result['success']) {
                // Add execution time to result
                $result['execution_time'] = $executionTime;
                return $result;
            }
        }
        
        error_log("Ultra detailed attendance processing failed: " . implode("\n", $output));
        return null;
    } catch (Exception $e) {
        error_log("Error processing ultra detailed attendance: " . $e->getMessage());
        return null;
    }
}

function getUltraDetailedPerformanceStats() {
    try {
        $command = "python facenet_ultra_detailed_service.py get_performance_stats";
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result) {
                return $result;
            }
        }
        
        error_log("Failed to get ultra detailed performance stats: " . implode("\n", $output));
        return null;
    } catch (Exception $e) {
        error_log("Error getting ultra detailed performance stats: " . $e->getMessage());
        return null;
    }
}

function generateHighAccuracyEmbedding($base64Image, $userId) {
    try {
        $data = [
            'action' => 'generate_high_accuracy_embedding',
            'image' => $base64Image,
            'user_id' => $userId
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("High accuracy embedding generation failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error generating high accuracy embedding: " . $e->getMessage());
        return null;
    }
}

function getHighAccuracyPerformanceStats() {
    try {
        $data = ['action' => 'get_performance_stats'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Failed to get high accuracy performance stats: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error getting high accuracy performance stats: " . $e->getMessage());
        return null;
    }
}

function recognizeEnhancedFace($base64Image, $threshold = 1.0) {
    try {
        $data = [
            'action' => 'recognize_enhanced_face',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Enhanced FaceNet recognition failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error recognizing enhanced face: " . $e->getMessage());
        return null;
    }
}

function saveEnhancedFaceEmbedding($userId, $enhancedEmbedding) {
    global $pdo;
    try {
        $baseEmbedding = json_encode($enhancedEmbedding['base_embedding'] ?? []);
        $advancedFeatures = json_encode($enhancedEmbedding['advanced_features'] ?? []);
        $facialGeometry = json_encode($enhancedEmbedding['advanced_features']['geometry'] ?? []);
        $featureVector = json_encode($enhancedEmbedding['advanced_features']['feature_vector'] ?? []);
        
        $stmt = $pdo->prepare("
            UPDATE users SET 
                face_embedding = ?, 
                advanced_features = ?,
                facial_geometry = ?,
                feature_vector = ?,
                face_embedding_updated = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$baseEmbedding, $advancedFeatures, $facialGeometry, $featureVector, $userId]);
        return true;
    } catch (Exception $e) {
        error_log("Error saving enhanced face embedding: " . $e->getMessage());
        return false;
    }
}

function processEnhancedAttendance($base64Image) {
    try {
        $data = [
            'action' => 'process_enhanced_attendance',
            'image' => $base64Image,
            'threshold' => 1.0
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        error_log("Enhanced FaceNet attendance processing failed: " . $response);
        return null;
    } catch (Exception $e) {
        error_log("Error processing enhanced attendance: " . $e->getMessage());
        return null;
    }
}

// KPI Calculation Functions
function calculateKPIForEmployee(PDO $pdo, $userId, $periodStart = null, $periodEnd = null) {
    try {
        // Get KPI settings
        $latePenaltyPerMinute = (float)getSetting($pdo, 'kpi_late_penalty_per_minute', '1');
        $izinSakitScore = (float)getSetting($pdo, 'kpi_izin_sakit_score', '85');
        $alphaScore = (float)getSetting($pdo, 'kpi_alpha_score', '0');
        $overtimeBonus = (float)getSetting($pdo, 'kpi_overtime_bonus', '5');
        
        // Get employee data
        $stmt = $pdo->prepare("SELECT nama, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $employee = $stmt->fetch();
        if (!$employee) return null;
        
        // Get employee registration date
        $employeeRegDate = $employee['created_at'];
        
        // Determine KPI start: use per-employee start setting if available, else registration date
        if (!$periodStart) {
            // Try settings override
            try{
                $k = 'work_start_date_user_'.$userId;
                $st = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=:k LIMIT 1");
                $st->execute([':k'=>$k]);
                $val = $st->fetchColumn();
                if($val){ $periodStart = $val; } else { $periodStart = $employeeRegDate; }
            }catch(Exception $e){ $periodStart = $employeeRegDate; }
        }
        if (!$periodEnd) {
            $periodEnd = date('Y-m-d');
        }
        
        // Debug logging for period
        error_log("KPI Debug - User $userId: Period start: $periodStart, Period end: $periodEnd");
        
        // Get employee registration date only
        $employeeRegDateOnly = date('Y-m-d', strtotime($employeeRegDate));
        
        // Get attendance records for the period (WFO, WFA, Overtime only)
        $stmt = $pdo->prepare("
            SELECT 
                DATE(jam_masuk_iso) as attendance_date,
                jam_masuk_iso,
                status,
                ket,
                CASE 
                    WHEN status = 'terlambat' THEN 
                        GREATEST(0, TIMESTAMPDIFF(MINUTE, 
                            CONCAT(DATE(jam_masuk_iso), ' 08:00:00'), 
                            jam_masuk_iso
                        ))
                    ELSE 0 
                END as late_minutes
            FROM attendance 
            WHERE user_id = :user_id 
            AND jam_masuk_iso IS NOT NULL 
            AND DATE(jam_masuk_iso) BETWEEN :period_start AND :period_end
            AND ket IN ('wfo', 'wfa', 'overtime')
            ORDER BY attendance_date
        ");
        $stmt->execute([
            'user_id' => $userId, 
            'period_start' => $periodStart, 
            'period_end' => $periodEnd
        ]);
        $attendanceRecords = $stmt->fetchAll();
        
        // Get izin/sakit records from attendance_notes table
        $stmt = $pdo->prepare("
            SELECT date as izin_date, type as status
            FROM attendance_notes 
            WHERE user_id = :user_id 
            AND type IN ('izin', 'sakit')
            AND date BETWEEN :period_start AND :period_end
            ORDER BY izin_date
        ");
        $stmt->execute([
            'user_id' => $userId, 
            'period_start' => $periodStart, 
            'period_end' => $periodEnd
        ]);
        $izinNotesRecords = $stmt->fetchAll();
        
        // Debug logging for izin/sakit records
        error_log("KPI Debug - User $userId: Found " . count($izinNotesRecords) . " izin/sakit records in period $periodStart to $periodEnd");
        error_log("KPI Debug - Employee registration date: $employeeRegDateOnly");
        
        // Get overtime records (attendance marked as 'overtime')
        $stmt = $pdo->prepare("
            SELECT DATE(jam_masuk_iso) as overtime_date, status, jam_masuk_iso, jam_masuk
            FROM attendance 
            WHERE user_id = :user_id 
            AND DATE(jam_masuk_iso) BETWEEN :period_start AND :period_end
            AND ket = 'overtime'
            ORDER BY jam_masuk_iso ASC
        ");
        $stmt->execute([
            'user_id' => $userId, 
            'period_start' => $periodStart, 
            'period_end' => $periodEnd
        ]);
        $overtimeRecords = $stmt->fetchAll();
        
        // Create maps for quick lookup
        $attendanceMap = [];
        foreach ($attendanceRecords as $record) {
            $attendanceMap[$record['attendance_date']] = $record;
        }
        
        $izinDates = [];
        foreach ($izinNotesRecords as $record) {
            // Only add if date is after or on registration date AND within the period
            if ($record['izin_date'] >= $employeeRegDateOnly && $record['izin_date'] >= $periodStart && $record['izin_date'] <= $periodEnd) {
                $izinDates[$record['izin_date']] = $record['status'];
            }
        }
        
        error_log("KPI Debug - User $userId: Total izin/sakit dates in map: " . count($izinDates));
        
        $overtimeDates = [];
        foreach ($overtimeRecords as $record) {
            $overtimeDates[$record['overtime_date']] = $record;
        }
        
        // Generate working days for this specific employee in the period
        $workingDays = getEmployeeWorkingDaysInPeriod($pdo, $userId, $periodStart, $periodEnd);
        
        // Get current date for comparison
        $currentDate = date('Y-m-d');
        
        $ontimeCount = 0;
        $lateCount = 0;
        $izinSakitCount = 0;
        $alphaCount = 0;
        $overtimeCount = 0;
        $totalLateMinutes = 0;
        $actualWorkingDays = 0; // Count actual working days for this employee (only past dates)
        $totalWorkingDaysInPeriod = 0; // Count all working days in period for this employee
        
        // Process each working day
        foreach ($workingDays as $date) {
            $dateStr = $date->format('Y-m-d');
            
            // Skip dates before employee registration
            if ($dateStr < $employeeRegDateOnly) {
                continue;
            }
            
            // Count this as a working day for this employee (regardless of whether it's past or future)
            $totalWorkingDaysInPeriod++;
            
            // Only count as actual working day if the date has already passed
            if ($dateStr <= $currentDate) {
                $actualWorkingDays++;
            }
            
            // Check if there's an attendance record for this date
            $attendanceRecord = null;
            foreach ($attendanceRecords as $record) {
                if ($record['attendance_date'] === $dateStr) {
                    $attendanceRecord = $record;
                    break;
                }
            }
            
            // Only process dates that have already passed for KPI calculation
            if ($dateStr <= $currentDate) {
                // Check if it's izin/sakit first (from attendance_notes table)
                if (isset($izinDates[$dateStr])) {
                    $izinSakitCount++;
                    error_log("KPI Debug - User $userId: Found izin/sakit on $dateStr, count now: $izinSakitCount");
                } else if ($attendanceRecord) {
                    // Check attendance status (only WFO, WFA, Overtime)
                    if ($attendanceRecord['status'] === 'ontime') {
                        $ontimeCount++;
                    } else {
                        $lateCount++;
                        $totalLateMinutes += $attendanceRecord['late_minutes'];
                    }
                } else {
                    // No attendance and no izin/sakit = alpha (only for past dates)
                    // If this date is a manual holiday, do not penalize as alpha
                    if (!isManualHoliday($pdo, $dateStr)) {
                        $alphaCount++;
                    }
                }
            }
        }
        
        // Count overtime days (including weekends and holidays)
        foreach ($overtimeDates as $overtimeDate => $overtimeRecord) {
            $overtimeCount++;
        }
        
        // Count izin/sakit directly from the records (more reliable)
        $currentDate = date('Y-m-d');
        $directIzinSakitCount = 0;
        foreach ($izinNotesRecords as $record) {
            if ($record['izin_date'] >= $employeeRegDateOnly && 
                $record['izin_date'] >= $periodStart && 
                $record['izin_date'] <= $periodEnd &&
                $record['izin_date'] <= $currentDate) {
                $directIzinSakitCount++;
            }
        }
        
        // Use the direct count if it's different from the loop count
        if ($directIzinSakitCount != $izinSakitCount) {
            error_log("KPI Debug - User $userId: Correcting izin/sakit count from $izinSakitCount to $directIzinSakitCount");
            $izinSakitCount = $directIzinSakitCount;
        }
        
        // Debug logging for final counts
        error_log("KPI Debug - User $userId: Final counts - Ontime: $ontimeCount, Late: $lateCount, Izin/Sakit: $izinSakitCount, Alpha: $alphaCount, Overtime: $overtimeCount");
        
        // Calculate KPI score
        $kpiScore = 0;
        if ($actualWorkingDays > 0) {
            // Base score from ontime attendance
            $kpiScore += ($ontimeCount * 100);
            
            // Deduct for late attendance
            $kpiScore -= ($totalLateMinutes * $latePenaltyPerMinute);
            
            // Add score for izin/sakit
            $kpiScore += ($izinSakitCount * $izinSakitScore);
            
            // Add score for alpha (usually 0)
            $kpiScore += ($alphaCount * $alphaScore);
            
            // Add overtime bonus
                $kpiScore += ($overtimeCount * $overtimeBonus);

            // Calculate average based on actual working days that have passed
            $kpiScore = $kpiScore / $actualWorkingDays;
            
            // Ensure score is between 0 and 100
            $kpiScore = max(0, min(100, $kpiScore));
        }
        
        // Determine KPI status
        $status = 'Very Poor';
        if ($kpiScore >= 90) $status = 'Excellent';
        elseif ($kpiScore >= 80) $status = 'Good';
        elseif ($kpiScore >= 70) $status = 'Fair';
        elseif ($kpiScore >= 60) $status = 'Poor';
        
        return [
            'user_id' => $userId,
            'nama' => $employee['nama'],
            'total_working_days' => $totalWorkingDaysInPeriod, // Total working days in period
            'actual_working_days' => $actualWorkingDays, // Days that have passed for KPI calculation
            'ontime_count' => $ontimeCount,
            'late_count' => $lateCount,
            'izin_sakit_count' => $izinSakitCount,
            'alpha_count' => $alphaCount,
            'overtime_count' => $overtimeCount,
            'total_late_minutes' => $totalLateMinutes,
            'kpi_score' => round($kpiScore, 2),
            'status' => $status,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'employee_registration_date' => $employeeRegDate
        ];
        
    } catch (Exception $e) {
        error_log("KPI calculation error: " . $e->getMessage());
        return null;
    }
}

// Function to get Indonesian national holidays for a given year
function getIndonesianNationalHolidays($year) {
    $holidays = [];
    
    // Fixed holidays (same date every year)
    $fixedHolidays = [
        '01-01' => 'Tahun Baru',
        '02-14' => 'Valentine Day',
        '03-22' => 'Hari Raya Nyepi',
        '04-18' => 'Wafat Isa Almasih',
        '05-01' => 'Hari Buruh Internasional',
        '05-09' => 'Kenaikan Isa Almasih',
        '05-20' => 'Hari Kebangkitan Nasional',
        '06-01' => 'Hari Lahir Pancasila',
        '06-17' => 'Hari Raya Idul Adha',
        '08-17' => 'Hari Kemerdekaan RI',
        '09-16' => 'Maulid Nabi Muhammad SAW',
        '10-02' => 'Hari Batik Nasional',
        '11-10' => 'Hari Pahlawan',
        '12-25' => 'Hari Raya Natal'
    ];
    
    // Islamic holidays (calculated based on Islamic calendar - simplified)
    // Note: These dates are approximate and should be updated yearly
    $islamicHolidays = [
        // Idul Fitri (2 days) - dates vary each year
        // Idul Adha - dates vary each year
        // Islamic New Year - dates vary each year
        // Maulid Nabi - dates vary each year
    ];
    
    // Add fixed holidays
    foreach ($fixedHolidays as $date => $name) {
        $holidays[] = [
            'date' => $year . '-' . $date,
            'name' => $name,
            'type' => 'fixed'
        ];
    }
    
    // Add Islamic holidays for specific years (2024-2025)
    if ($year == 2024) {
        $islamicHolidays2024 = [
            '2024-04-10' => 'Hari Raya Idul Fitri 1445 H',
            '2024-04-11' => 'Hari Raya Idul Fitri 1445 H (Hari Kedua)',
            '2024-06-16' => 'Hari Raya Idul Adha 1445 H',
            '2024-07-07' => 'Tahun Baru Islam 1446 H',
            '2024-09-15' => 'Maulid Nabi Muhammad SAW 1446 H'
        ];
        foreach ($islamicHolidays2024 as $date => $name) {
            $holidays[] = [
                'date' => $date,
                'name' => $name,
                'type' => 'islamic'
            ];
        }
    } elseif ($year == 2025) {
        $islamicHolidays2025 = [
            '2025-03-30' => 'Hari Raya Idul Fitri 1446 H',
            '2025-03-31' => 'Hari Raya Idul Fitri 1446 H (Hari Kedua)',
            '2025-06-06' => 'Hari Raya Idul Adha 1446 H',
            '2025-06-26' => 'Tahun Baru Islam 1447 H',
            '2025-09-05' => 'Maulid Nabi Muhammad SAW 1447 H'
        ];
        foreach ($islamicHolidays2025 as $date => $name) {
            $holidays[] = [
                'date' => $date,
                'name' => $name,
                'type' => 'islamic'
            ];
        }
    }
    
    return $holidays;
}

// Function to check if a date is a national holiday
function isNationalHoliday($date) {
    $year = date('Y', strtotime($date));
    $holidays = getIndonesianNationalHolidays($year);
    
    foreach ($holidays as $holiday) {
        if ($holiday['date'] === $date) {
            return true;
        }
    }
    
    return false;
}

// Manual holiday helpers
function isManualHoliday(PDO $pdo, $date){
    try{
        $stmt = $pdo->prepare("SELECT 1 FROM manual_holidays WHERE date = :d LIMIT 1");
        $stmt->execute([':d'=>$date]);
        return (bool)$stmt->fetchColumn();
    }catch(PDOException $e){
        error_log('isManualHoliday error: '.$e->getMessage());
        return false;
    }
}

function getManualHolidaysInRange(PDO $pdo, $startDate, $endDate){
    try{
        $stmt = $pdo->prepare("SELECT * FROM manual_holidays WHERE date BETWEEN :s AND :e ORDER BY date");
        $stmt->execute([':s'=>$startDate, ':e'=>$endDate]);
        return $stmt->fetchAll();
    }catch(PDOException $e){
        error_log('getManualHolidaysInRange error: '.$e->getMessage());
        return [];
    }
}

// Function to get employee's work schedule
function getEmployeeWorkSchedule(PDO $pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM employee_work_schedule WHERE user_id = :user_id");
        $stmt->execute([':user_id' => $userId]);
        $schedules = $stmt->fetchAll();
        
        $scheduleMap = [];
        foreach ($schedules as $schedule) {
            $scheduleMap[$schedule['day_of_week']] = [
                'is_working_day' => (bool)$schedule['is_working_day'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time']
            ];
        }
        
        return $scheduleMap;
    } catch (PDOException $e) {
        error_log("Error getting employee work schedule: " . $e->getMessage());
        return [];
    }
}

// Function to check if a specific date is a working day for an employee
function isEmployeeWorkingDay(PDO $pdo, $userId, $date) {
    $dateObj = new DateTime($date);
    $dayOfWeek = strtolower($dateObj->format('l')); // monday, tuesday, etc.
    
    $schedule = getEmployeeWorkSchedule($pdo, $userId);
    
    // If no specific schedule found, use default (Monday-Friday)
    if (empty($schedule)) {
        $dayNumber = $dateObj->format('N');
        return $dayNumber < 6 && !isNationalHoliday($date) && !isManualHoliday($pdo, $date);
    }
    
    // Check if employee works on this day
    if (isset($schedule[$dayOfWeek])) {
        return $schedule[$dayOfWeek]['is_working_day'] && !isNationalHoliday($date) && !isManualHoliday($pdo, $date);
    }
    
    return false;
}

// Function to get working days for a specific employee in a period
function getEmployeeWorkingDaysInPeriod(PDO $pdo, $userId, $startDate, $endDate) {
    $workingDays = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        
        if (isEmployeeWorkingDay($pdo, $userId, $dateStr)) {
            $workingDays[] = clone $start;
        }
        
        $start->add(new DateInterval('P1D'));
    }
    
    return $workingDays;
}

function getWorkingDaysInPeriod($startDate, $endDate) {
    $workingDays = [];
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    
    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');
        
        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (!isNationalHoliday($dateStr) && !(isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays[] = clone $start;
            }
        }
        $start->add(new DateInterval('P1D'));
    }
    
    return $workingDays;
}

function getWorkingDaysInMonth($year, $month) {
    $workingDays = 0;
    $start = new DateTime("$year-$month-01");
    $end = new DateTime("$year-$month-" . $start->format('t')); // Last day of month
    
    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');
        
        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (!isNationalHoliday($dateStr) && !(isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays++;
            }
        }
        $start->add(new DateInterval('P1D'));
    }
    
    return $workingDays;
}

function getWorkingDaysInMonthUpToDate($year, $month, $day) {
    $workingDays = 0;
    $start = new DateTime("$year-$month-01");
    $end = new DateTime("$year-$month-" . str_pad($day, 2, '0', STR_PAD_LEFT));
    
    // Subtract 1 day from end to exclude today (don't count today for alpha calculation)
    $end->sub(new DateInterval('P1D'));
    
    while ($start <= $end) {
        $dateStr = $start->format('Y-m-d');
        $dayOfWeek = $start->format('N');
        
        // Skip weekends (Saturday = 6, Sunday = 0)
        if ($dayOfWeek < 6) {
            // Check if it's not a national or manual holiday
            if (!isNationalHoliday($dateStr) && !(isset($GLOBALS['pdo']) ? isManualHoliday($GLOBALS['pdo'], $dateStr) : false)) {
                $workingDays++;
            }
        }
        $start->add(new DateInterval('P1D'));
    }
    
    return $workingDays;
}

function getEarliestEmployeeRegistrationDate(PDO $pdo) {
    try {
        $stmt = $pdo->prepare("SELECT MIN(created_at) as earliest_date FROM users WHERE role = 'pegawai'");
        $stmt->execute();
        $result = $stmt->fetch();
        return $result ? $result['earliest_date'] : date('Y-01-01');
    } catch (PDOException $e) {
        error_log("Error getting earliest employee registration date: " . $e->getMessage());
        return date('Y-01-01');
    }
}

function getEmployeeRegistrationDate(PDO $pdo, $userId) {
    try {
        $stmt = $pdo->prepare("SELECT created_at FROM users WHERE id = :user_id AND role = 'pegawai'");
        $stmt->execute([':user_id' => $userId]);
        $result = $stmt->fetch();
        return $result ? $result['created_at'] : null;
    } catch (PDOException $e) {
        error_log("Error getting employee registration date: " . $e->getMessage());
        return null;
    }
}

function getAllKPIData(PDO $pdo, $customPeriodStart = null, $customPeriodEnd = null) {
    try {
        $periodStart = $customPeriodStart ?? getEarliestEmployeeRegistrationDate($pdo);
        $periodEnd = $customPeriodEnd ?? date('Y-m-d'); // Use current date instead of period end
        
        // Get all employees
        $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE role = 'pegawai' ORDER BY nama");
        $stmt->execute();
        $employees = $stmt->fetchAll();
        
        // If no employees, return empty data
        if (empty($employees)) {
            return [
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'kpi_data' => []
            ];
        }
        
        $kpiData = [];
        foreach ($employees as $employee) {
            $kpi = calculateKPIForEmployee($pdo, $employee['id'], $periodStart, $periodEnd);
            if ($kpi) {
                $kpiData[] = $kpi;
            }
        }
        
        // Sort by KPI score descending
        usort($kpiData, function($a, $b) {
            return $b['kpi_score'] <=> $a['kpi_score'];
        });
        
        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'kpi_data' => $kpiData
        ];
        
    } catch (Exception $e) {
        error_log("Get all KPI data error: " . $e->getMessage());
        return null;
    }
}

// ----- AJAX ENDPOINTS -----
if (isset($_GET['ajax'])) {
    $action = $_GET['ajax'];

    // Check if database is available
    if (!isset($pdo)) {
        error_log("Database connection failed in AJAX handler");
        jsonResponse(['error' => 'Database connection failed'], 500);
    }

    // Must be authenticated for all endpoints except auth-related and public landing scan
    if (!in_array($action, ['login', 'register', 'get_members', 'save_attendance', 'get_today_attendance'], true)) {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    }
    // Admin manual holidays CRUD
    if ($action === 'admin_get_manual_holidays') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $start = $_GET['start'] ?? ($_POST['start'] ?? date('Y-01-01'));
        $end = $_GET['end'] ?? ($_POST['end'] ?? date('Y-12-31'));
        $rows = getManualHolidaysInRange($pdo, $start, $end);
        jsonResponse(['ok'=>true,'data'=>$rows]);
    }
    if ($action === 'admin_add_manual_holiday' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $date = $_POST['date'] ?? '';
        $name = trim($_POST['name'] ?? 'Libur Manual');
        if (!$date) jsonResponse(['ok'=>false,'message'=>'Tanggal wajib diisi'],400);
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            jsonResponse(['ok'=>false,'message'=>'Format tanggal tidak valid. Gunakan YYYY-MM-DD'],400);
        }
        
        try{
            // Check if table exists and has correct structure
            $checkTable = $pdo->query("SHOW TABLES LIKE 'manual_holidays'");
            if ($checkTable->rowCount() == 0) {
                error_log('manual_holidays table does not exist');
                jsonResponse(['ok'=>false,'message'=>'Tabel manual_holidays tidak ditemukan'],500);
            }
            
            // Check table structure
            $checkColumns = $pdo->query("DESCRIBE manual_holidays");
            $columns = $checkColumns->fetchAll(PDO::FETCH_COLUMN);
            error_log('manual_holidays columns: ' . implode(', ', $columns));
            
            // Validate user session
            $userId = $_SESSION['user']['id'] ?? null;
            if (!$userId) {
                error_log('No user ID in session');
                jsonResponse(['ok'=>false,'message'=>'Session tidak valid'],400);
            }
            
            // Check if date already exists
            $checkDate = $pdo->prepare("SELECT id FROM manual_holidays WHERE date = :d LIMIT 1");
            $checkDate->execute([':d' => $date]);
            $existingId = $checkDate->fetchColumn();
            
            if ($existingId) {
                // Update existing record
                $stmt = $pdo->prepare("UPDATE manual_holidays SET name = :n, created_by = :u WHERE id = :id");
                $result = $stmt->execute([':n' => $name, ':u' => $userId, ':id' => $existingId]);
                $message = 'Hari libur manual diperbarui';
            } else {
                // Insert new record
                $stmt = $pdo->prepare("INSERT INTO manual_holidays(date,name,created_by) VALUES(:d,:n,:u)");
                $result = $stmt->execute([':d' => $date, ':n' => $name, ':u' => $userId]);
                $message = 'Hari libur manual disimpan';
            }
            
            if ($result) {
            triggerDatabaseBackup();
                jsonResponse(['ok'=>true,'message'=>$message]);
            } else {
                error_log('Failed to execute manual holiday insert/update');
                jsonResponse(['ok'=>false,'message'=>'Gagal menyimpan hari libur'],500);
            }
        }catch(PDOException $e){
            error_log('add manual holiday error: '.$e->getMessage());
            error_log('SQL State: ' . $e->getCode());
            error_log('Error Info: ' . print_r($e->errorInfo, true));
            jsonResponse(['ok'=>false,'message'=>'Gagal menyimpan hari libur: ' . $e->getMessage()],500);
        }
    }
    if ($action === 'admin_delete_manual_holiday' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $id=(int)($_POST['id']??0);
        if(!$id) jsonResponse(['ok'=>false,'message'=>'ID tidak valid'],400);
        $pdo->prepare("DELETE FROM manual_holidays WHERE id=:id")->execute([':id'=>$id]);
        triggerDatabaseBackup();
        jsonResponse(['ok'=>true]);
    }

    if ($action === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email=:email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int)$user['id'],
                'role' => $user['role'],
                'email' => $user['email'],
                'nim' => $user['nim'],
                'nama' => $user['nama'],
            ];
            jsonResponse(['ok' => true, 'role' => $user['role']]);
        }
        jsonResponse(['ok' => false, 'message' => 'Email atau password salah'], 400);
    }

    if ($action === 'register' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        $nim = trim($_POST['nim'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $prodi = trim($_POST['prodi'] ?? '');
        $startup = trim($_POST['startup'] ?? '');
        $foto = $_POST['foto'] ?? null; // data URL
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';

        if ($password !== $password2) jsonResponse(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], 400);
        if (!$email || !$nim || !$nama || !$prodi || !$password || !$foto) jsonResponse(['ok' => false, 'message' => 'Semua field wajib diisi (termasuk foto)'], 400);
        
        // Check image size (max 1MB)
        if (!checkImageSize($foto, 1)) {
            jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
        }
        // Disallow duplicate email or nim
        $check = $pdo->prepare("SELECT id FROM users WHERE email=:email OR nim=:nim LIMIT 1");
        $check->execute([':email' => $email, ':nim' => $nim]);
        if ($check->fetch()) jsonResponse(['ok' => false, 'message' => 'Email atau NIM sudah terdaftar'], 400);

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password_hash) VALUES ('pegawai', :email, :nim, :nama, :prodi, :startup, :foto, :hash)");
        $stmt->execute([
            ':email' => $email,
            ':nim' => $nim,
            ':nama' => $nama,
            ':prodi' => $prodi,
            ':startup' => $startup ?: null,
            ':foto' => $foto,
            ':hash' => $hash,
        ]);
        
        // Trigger backup setelah menambah user baru
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true]);
    }

    if ($action === 'logout') {
        session_destroy();
        jsonResponse(['ok' => true]);
    }

    if ($action === 'get_members') {
        // Admin can see all; Pegawai only themselves (but for face recognition we need all for presensi). We'll return all but only safe fields
        $stmt = $pdo->query("SELECT id, role, email, nim, nama, prodi, startup, foto_base64 FROM users WHERE role='pegawai'");
        $rows = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'get_startups') {
        $stmt = $pdo->query("SELECT DISTINCT startup FROM users WHERE role='pegawai' AND startup IS NOT NULL AND startup != '' ORDER BY startup");
        $rows = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'data' => array_column($rows, 'startup')]);
    }

    if ($action === 'get_today_attendance') {
        $type = $_POST['type'] ?? 'masuk';
        $today = date('Y-m-d');
        
        if ($type === 'masuk') {
            $stmt = $pdo->prepare("
                SELECT a.jam_masuk, a.jam_masuk_iso, a.screenshot_masuk, a.lokasi_masuk, u.nama, u.startup 
                FROM attendance a 
                JOIN users u ON u.id = a.user_id 
                WHERE DATE(a.jam_masuk_iso) = :today 
                AND a.jam_masuk IS NOT NULL 
                AND a.jam_masuk != ''
                ORDER BY a.jam_masuk_iso DESC
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT a.jam_pulang, a.jam_pulang_iso, a.screenshot_pulang, a.lokasi_pulang, u.nama, u.startup 
                FROM attendance a 
                JOIN users u ON u.id = a.user_id 
                WHERE DATE(a.jam_pulang_iso) = :today 
                AND a.jam_pulang IS NOT NULL 
                AND a.jam_pulang != ''
                ORDER BY a.jam_pulang_iso DESC
            ");
        }
        
        $stmt->execute([':today' => $today]);
        $rows = $stmt->fetchAll();
        
        // Debug log
        error_log("get_today_attendance: type=$type, today=$today, count=" . count($rows));
        
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'save_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = $_POST['id'] ?? '';
        $nim = trim($_POST['nim'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $prodi = trim($_POST['prodi'] ?? '');
        $startup = trim($_POST['startup'] ?? '');
        $foto = $_POST['foto'] ?? null;

        if ($id) {
            // Update existing by id
            $user = $pdo->prepare("SELECT id FROM users WHERE id=:id AND role='pegawai'");
            $user->execute([':id' => $id]);
            if (!$user->fetch()) jsonResponse(['ok' => false, 'message' => 'Member tidak ditemukan'], 404);
            
            // Check image size if updating photo (max 1MB)
            if ($foto && !checkImageSize($foto, 1)) {
                jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
            }
            
            $params = [':nama' => $nama, ':prodi' => $prodi, ':startup' => $startup ?: null, ':id' => $id];
            $sql = "UPDATE users SET nama=:nama, prodi=:prodi, startup=:startup" . ($foto ? ", foto_base64=:foto" : "") . " WHERE id=:id";
            if ($foto) $params[':foto'] = $foto;
            $pdo->prepare($sql)->execute($params);
            
            // Trigger backup setelah update user
            triggerDatabaseBackup();
            
            jsonResponse(['ok' => true]);
        } else {
            // Create new
            if (!$nim || !$nama || !$prodi || !$foto) jsonResponse(['ok' => false, 'message' => 'Field wajib belum lengkap'], 400);
            
            // Check image size (max 1MB)
            if (!checkImageSize($foto, 1)) {
                jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
            }
            $check = $pdo->prepare("SELECT id FROM users WHERE email=:email OR nim=:nim LIMIT 1");
            $email = trim($_POST['email'] ?? '');
            $check->execute([':email' => $email, ':nim' => $nim]);
            if ($check->fetch()) jsonResponse(['ok' => false, 'message' => 'Email atau NIM sudah terdaftar'], 400);
            $password = $_POST['password'] ?? '';
            if (!$email || !$password) jsonResponse(['ok' => false, 'message' => 'Email dan password wajib untuk member baru'], 400);
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("INSERT INTO users (role, email, nim, nama, prodi, startup, foto_base64, password_hash) VALUES ('pegawai', :email, :nim, :nama, :prodi, :startup, :foto, :hash)");
            $stmt->execute([
                ':email' => $email,
                ':nim' => $nim,
                ':nama' => $nama,
                ':prodi' => $prodi,
                ':startup' => $startup ?: null,
                ':foto' => $foto,
                ':hash' => $hash,
            ]);
            
            // Trigger backup setelah menambah user baru
            triggerDatabaseBackup();
            
            jsonResponse(['ok' => true]);
        }
    }

    if ($action === 'delete_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id=:id AND role='pegawai'")->execute([':id' => $id]);
        
        // Trigger backup setelah menghapus user
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true]);
    }

    if ($action === 'get_attendance') {
        // Admin: all; Pegawai: only their records
        if (isAdmin()) {
            // Get regular attendance records
            $stmt = $pdo->query("SELECT a.*, u.nim, u.nama, u.startup,
                (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                FROM attendance a JOIN users u ON u.id=a.user_id ORDER BY a.jam_masuk_iso DESC");
            $attendanceData = $stmt->fetchAll();
            
            // Get izin/sakit records from attendance_notes
            $notesStmt = $pdo->query("SELECT an.*, u.nim, u.nama, u.startup,
                (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                FROM attendance_notes an JOIN users u ON u.id=an.user_id ORDER BY an.date DESC");
            $notesData = $notesStmt->fetchAll();
            
            // Convert notes data to attendance format
            foreach ($notesData as $note) {
                $attendanceData[] = [
                    'id' => 'note_' . $note['id'],
                    'user_id' => $note['user_id'],
                    'nim' => $note['nim'],
                    'nama' => $note['nama'],
                    'startup' => $note['startup'],
                    'jam_masuk' => '08:00', // Default time for izin/sakit
                    'jam_masuk_iso' => $note['date'] . ' 08:00:00',
                    'ekspresi_masuk' => null,
                    'screenshot_masuk' => null,
                    'lokasi_masuk' => null,
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'jam_pulang' => '17:00', // Default time for izin/sakit
                    'jam_pulang_iso' => $note['date'] . ' 17:00:00',
                    'ekspresi_pulang' => null,
                    'screenshot_pulang' => null,
                    'lokasi_pulang' => null,
                    'lat_pulang' => null,
                    'lng_pulang' => null,
                    'status' => 'ontime',
                    'ket' => $note['type'],
                    'alasan_wfa' => null,
                    'alasan_izin_sakit' => $note['keterangan'],
                    'bukti_izin_sakit' => $note['bukti'],
                    'daily_report_id' => null,
                    'created_at' => $note['created_at'],
                    'daily_report_status' => $note['daily_report_status'],
                    'is_note' => true // Flag to identify this is from attendance_notes
                ];
            }
            
            // Sort by date descending
            usort($attendanceData, function($a, $b) {
                return strtotime($b['jam_masuk_iso']) - strtotime($a['jam_masuk_iso']);
            });
            
        } else {
            $uid = (int)$_SESSION['user']['id'];
            // Get regular attendance records
            $stmt = $pdo->prepare("SELECT a.*, u.nim, u.nama, u.startup,
                (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.user_id=:uid ORDER BY a.jam_masuk_iso DESC");
            $stmt->execute([':uid' => $uid]);
            $attendanceData = $stmt->fetchAll();
            
            // Get izin/sakit records from attendance_notes for this user
            $notesStmt = $pdo->prepare("SELECT an.*, u.nim, u.nama, u.startup,
                (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                FROM attendance_notes an JOIN users u ON u.id=an.user_id WHERE an.user_id=:uid ORDER BY an.date DESC");
            $notesStmt->execute([':uid' => $uid]);
            $notesData = $notesStmt->fetchAll();
            
            // Convert notes data to attendance format
            foreach ($notesData as $note) {
                $attendanceData[] = [
                    'id' => 'note_' . $note['id'],
                    'user_id' => $note['user_id'],
                    'nim' => $note['nim'],
                    'nama' => $note['nama'],
                    'startup' => $note['startup'],
                    'jam_masuk' => '08:00', // Default time for izin/sakit
                    'jam_masuk_iso' => $note['date'] . ' 08:00:00',
                    'ekspresi_masuk' => null,
                    'screenshot_masuk' => null,
                    'lokasi_masuk' => null,
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'jam_pulang' => '17:00', // Default time for izin/sakit
                    'jam_pulang_iso' => $note['date'] . ' 17:00:00',
                    'ekspresi_pulang' => null,
                    'screenshot_pulang' => null,
                    'lokasi_pulang' => null,
                    'lat_pulang' => null,
                    'lng_pulang' => null,
                    'status' => 'ontime',
                    'ket' => $note['type'],
                    'alasan_wfa' => null,
                    'alasan_izin_sakit' => $note['keterangan'],
                    'bukti_izin_sakit' => $note['bukti'],
                    'daily_report_id' => null,
                    'created_at' => $note['created_at'],
                    'daily_report_status' => $note['daily_report_status'],
                    'is_note' => true // Flag to identify this is from attendance_notes
                ];
            }
            
            // Sort by date descending
            usort($attendanceData, function($a, $b) {
                return strtotime($b['jam_masuk_iso']) - strtotime($a['jam_masuk_iso']);
            });
        }
        jsonResponse(['ok' => true, 'data' => $attendanceData]);
    }
    
    if ($action === 'get_kpi_data') {
        try {
            // Check if this is for admin dashboard (filter_type parameter)
            $filterType = $_GET['filter_type'] ?? '';
            $isAdminDashboard = isAdmin() && $filterType !== '';
            
            if ($isAdminDashboard) {
                // Admin dashboard - get all KPI data with optional monthly filter
                $customPeriodStart = null;
                $customPeriodEnd = null;
                
                if ($filterType === 'monthly') {
                    $month = (int)($_GET['month'] ?? date('n'));
                    $year = (int)($_GET['year'] ?? date('Y'));
                    $customPeriodStart = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
                    $customPeriodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
                    error_log("get_kpi_data - Monthly filter: $month/$year ($customPeriodStart to $customPeriodEnd)");
                }
                
                $kpiData = getAllKPIData($pdo, $customPeriodStart, $customPeriodEnd);
                error_log("get_kpi_data - Admin dashboard, returning all KPI data");
                jsonResponse(['ok' => true, 'data' => $kpiData]);
            } else {
                // Individual employee KPI - get specific user
                $userId = isAdmin() ? (int)($_GET['user_id'] ?? 0) : (int)$_SESSION['user']['id'];
                
                error_log("get_kpi_data - User ID: $userId, Is Admin: " . (isAdmin() ? 'Yes' : 'No'));
                error_log("get_kpi_data - Session user: " . print_r($_SESSION['user'] ?? 'No session', true));
                error_log("get_kpi_data - GET user_id: " . ($_GET['user_id'] ?? 'Not set'));
                
                if (!$userId && !isAdmin()) {
                    error_log("get_kpi_data - No user ID found");
                    jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 400);
                }
                
                // If admin but no user_id specified, use logged-in user
                if (!$userId) {
                    $userId = (int)$_SESSION['user']['id'];
                    error_log("get_kpi_data - Using logged-in user ID: $userId");
                }
                
                // Get period start and end
                $periodStart = $_GET['period_start'] ?? date('Y-m-01');
                $periodEnd = $_GET['period_end'] ?? date('Y-m-t');
                
                error_log("get_kpi_data - Period: $periodStart to $periodEnd");
                error_log("get_kpi_data - Individual employee KPI for user: $userId");
                
                // Calculate KPI for individual employee
                $kpiData = calculateKPIForEmployee($pdo, $userId, $periodStart, $periodEnd);
                
                error_log("get_kpi_data - Individual KPI calculation result: " . print_r($kpiData, true));
                
                if ($kpiData) {
                    jsonResponse(['ok' => true, 'data' => $kpiData]);
                } else {
                    error_log("get_kpi_data - Individual KPI calculation returned null/empty");
                    jsonResponse(['ok' => false, 'message' => 'Gagal menghitung KPI'], 500);
                }
            }
        } catch (Exception $e) {
            error_log("get_kpi_data - Exception: " . $e->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Error: ' . $e->getMessage()], 500);
        }
    }

    if ($action === 'save_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nim = trim($_POST['nim'] ?? '');
        $mode = $_POST['mode'] ?? ''; // masuk/pulang
        $ekspresi = $_POST['ekspresi'] ?? null;
        $screenshot = $_POST['screenshot'] ?? null; // base64 screenshot data
        
        // ULTRA-FAST: Minimal validation for maximum speed
        if (!$screenshot || empty($screenshot)) {
            jsonResponse(['ok' => false, 'message' => 'Screenshot tidak berhasil diambil. Silakan coba lagi dengan posisi yang lebih baik.'], 400);
        }
        // Skip size check for ultra-fast processing
        // if ($screenshot) {
        //     $sizeCheck = checkImageSize($screenshot, 2);
        //     if (!$sizeCheck['valid']) {
        //         jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
        //     }
        // }
        
        // Ultra-fast processing - minimal logging
        // error_log("Attendance request: NIM=$nim, Mode=$mode, Expression=$ekspresi, Screenshot=" . ($screenshot ? 'YES' : 'NO'));
        // error_log("POST data: " . print_r($_POST, true));
        
        // Ultra-fast processing - skip table verification for speed
        // if (!verifyAttendanceTable($pdo)) {
        //     error_log("Attendance table structure verification failed during save_attendance");
        //     jsonResponse(['ok' => false, 'message' => 'Database structure error. Please contact administrator.'], 500);
        // }
        
        if (!$nim || !in_array($mode, ['masuk', 'pulang'], true)) {
            jsonResponse(['ok' => false, 'message' => 'Bad request: NIM atau mode tidak valid'], 400);
        }
        // ULTRA-FAST: Optimized database query with minimal fields and no error logging
        try {
            $stmt = $pdo->prepare("SELECT id, nama FROM users WHERE nim=:nim LIMIT 1");
            $stmt->execute([':nim' => $nim]);
            $u = $stmt->fetch();
            if (!$u) {
                jsonResponse(['ok' => false, 'message' => 'NIM tidak ditemukan'], 404);
            }
        } catch (PDOException $e) {
            jsonResponse(['ok' => false, 'message' => 'Database error'], 500);
        }
    
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $jamSekarang = $now->format('H:i:s'); // Tetap simpan dengan detik untuk database
        $iso = $now->format('Y-m-d H:i:s');
        $today = $now->format('Y-m-d');
        
        // Ultra-fast processing - minimal logging
        // error_log("Current date: $today, User ID: " . $u['id']);
        // error_log("User data: " . print_r($u, true));
        // error_log("Mode: $mode, Expression: $ekspresi");
        // error_log("Screenshot size: " . strlen($screenshot));
        // error_log("Screenshot preview: " . substr($screenshot, 0, 100) . "...");
        // error_log("Screenshot starts with: " . substr($screenshot, 0, 20));
        // error_log("Screenshot ends with: " . substr($screenshot, -20));
        // error_log("Screenshot contains data:image: " . (strpos($screenshot, 'data:image') !== false ? 'YES' : 'NO'));
        $currentHour = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');
        $todayStart = $today . ' 00:00:00';
        $todayEnd   = $today . ' 23:59:59';
    
        if ($mode === 'masuk') {
            // Check if within check-in time window (5 AM - 8 PM) - More flexible hours
            if ($currentHour < 5 || $currentHour >= 20) {
                $statusText = "Presensi masuk tersedia dari jam 05:00 sampai 20:00.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700'], 400);
            }
    
            // Ultra-fast query - check for any attendance record today (including izin/sakit)
            $todayCheck = $pdo->prepare("
                SELECT id, jam_masuk_iso, jam_pulang_iso, ket FROM attendance 
                WHERE user_id = :uid 
                AND DATE(jam_masuk_iso) = :today 
                AND jam_masuk_iso IS NOT NULL
                ORDER BY jam_masuk_iso DESC 
                LIMIT 1
            ");
            $todayCheck->execute([
                ':uid' => $u['id'],
                ':today' => $today
            ]);
            $todayRow = $todayCheck->fetch();
            
            // Ultra-fast processing - minimal logging
            // if ($todayRow) {
            //     error_log("Found existing attendance record: ID=" . $todayRow['id'] . ", jam_masuk_iso=" . $todayRow['jam_masuk_iso'] . ", jam_pulang_iso=" . $todayRow['jam_pulang_iso']);
            // } else {
            //     error_log("No existing attendance record found for user " . $u['id'] . " on date " . $today);
            // }
            
            if (!$todayRow) {
                // Calculate if late using settings
                $maxOntimeHour = (int)getSetting($pdo, 'max_ontime_hour', '8');
                $isLate = false;
                $lateMessage = '';
                $status = 'ontime';
                
                if ($currentHour > $maxOntimeHour || ($currentHour === $maxOntimeHour && $currentMinute > 0)) {
                    $isLate = true;
                    $status = 'terlambat';
                    
                    // Calculate delay time
                    $deadline = new DateTime($today . ' ' . sprintf('%02d:00:00', $maxOntimeHour), new DateTimeZone('Asia/Jakarta'));
                    $delay = $now->getTimestamp() - $deadline->getTimestamp();
                    
                    if ($delay >= 3600) { // More than 1 hour
                        $hours = floor($delay / 3600);
                        $minutes = floor(($delay % 3600) / 60);
                        $lateMessage = " (Telat {$hours} jam {$minutes} menit)";
                    } elseif ($delay >= 60) { // More than 1 minute
                        $minutes = floor($delay / 60);
                        $lateMessage = " (Telat {$minutes} menit)";
                    } else {
                        $lateMessage = " (Telat {$delay} detik)";
                    }
                }
                
                // Location and geofence handling for WFO/WFA
                $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
                $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
                $lokasi = $_POST['lokasi'] ?? null;
                $alasanWfa = null;
                
                // Convert coordinates to readable address if we have coordinates but no location name
                if ($lat !== null && $lng !== null && (!$lokasi || strpos($lokasi, 'Lokasi:') === 0)) {
                    $reverseGeocoded = reverseGeocodeAddress($lat, $lng);
                    if ($reverseGeocoded) {
                        $lokasi = $reverseGeocoded;
                    }
                }

                // Determine WFO via API or coordinate fallback
                $wfoMode = strtolower(getSetting($pdo, 'wfo_mode', 'api'));
                $publicIp = $_POST['public_ip'] ?? '';
                $isInsideTelu = false;
                
                if ($wfoMode === 'api') {
                    // Try API-based detection first
                    $isInsideTelu = isWfoByApi($pdo, $publicIp);
                    // Fallback to coordinate if API cannot confirm
                    if (!$isInsideTelu) {
                        $wfoLat = (float)getSetting($pdo, 'wfo_lat', '-6.9738');
                        $wfoLng = (float)getSetting($pdo, 'wfo_lng', '107.6300');
                        $wfoRadius = (int)getSetting($pdo, 'wfo_radius_m', '1200'); // meters
                        if ($lat !== null && $lng !== null) {
                            $earth = 6371000; // meters
                            $dLat = deg2rad($wfoLat - $lat);
                            $dLng = deg2rad($wfoLng - $lng);
                            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat)) * cos(deg2rad($wfoLat)) * sin($dLng/2) * sin($dLng/2);
                            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                            $distance = $earth * $c;
                            $isInsideTelu = ($distance <= max(0, $wfoRadius));
                        }
                    }
                } else {
                    // Coordinate-only mode
                    $wfoLat = (float)getSetting($pdo, 'wfo_lat', '-6.9738');
                    $wfoLng = (float)getSetting($pdo, 'wfo_lng', '107.6300');
                    $wfoRadius = (int)getSetting($pdo, 'wfo_radius_m', '1200'); // meters
                    if ($lat !== null && $lng !== null) {
                        $earth = 6371000; // meters
                        $dLat = deg2rad($wfoLat - $lat);
                        $dLng = deg2rad($wfoLng - $lng);
                        $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat)) * cos(deg2rad($wfoLat)) * sin($dLng/2) * sin($dLng/2);
                        $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                        $distance = $earth * $c;
                        $isInsideTelu = ($distance <= max(0, $wfoRadius));
                    }
                }

                $ketVal = $isInsideTelu ? 'wfo' : 'wfa';
                if (!$isInsideTelu) {
                    $alasanWfa = $_POST['wfa_reason'] ?? $_POST['alasan_wfa'] ?? null;
                    if (!$alasanWfa) {
                        jsonResponse(['ok' => false, 'need_reason' => true, 'message' => 'Di luar wilayah kantor. Harap isi alasan kerja di luar (WFA).'], 400);
                    }
                }

                // ULTRA-FAST: Minimal insert for maximum speed
                $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, screenshot_masuk, lokasi_masuk, lat_masuk, lng_masuk, status, ket, alasan_wfa) VALUES (:uid, :jam, :iso, :exp, :screenshot, :lokasi, :lat, :lng, :status, :ket, :alasan)");
                $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':status' => $status, ':ket' => $ketVal, ':alasan' => $alasanWfa]);
                
                // Trigger backup setelah presensi masuk
                triggerDatabaseBackup();
                
                // ULTRA-FAST: Ultra-minimal response for maximum speed
                $jamMasukFormat = substr($jamSekarang, 0, 5);
                $firstName = getFirstName($u['nama']);
                if ($isLate) {
                    $statusText = "Selamat datang {$firstName}, anda masuk {$jamMasukFormat}. terlambat!";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
                } else {
                    $statusText = "Selamat datang {$firstName}, anda masuk {$jamMasukFormat}. OnTime!";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-green-100 text-green-700']);
                }
            } else {
                // Check if user has izin/sakit today
                if ($todayRow['ket'] === 'izin' || $todayRow['ket'] === 'sakit') {
                    $statusText = "Anda sudah mengajukan {$todayRow['ket']} hari ini. Tidak bisa melakukan presensi.";
                    jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700']);
            } else {
                $masukTime = new DateTime($todayRow['jam_masuk_iso']);
                $statusText = "Anda sudah presensi masuk pukul " . $masukTime->format('H:i') . " dan belum pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
                }
            }
        } else {
            // Check if within check-out time window using settings
            $minCheckoutHour = (int)getSetting($pdo, 'min_checkout_hour', '17');
            if ($currentHour < $minCheckoutHour) {
                $firstName = getFirstName($u['nama']);
                $statusText = "Hei {$firstName}, Jangan kabur! ini masih jam kerja";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700']);
            }
    
            // Check if checked in today and not yet checked out
            $todayCheck = $pdo->prepare("SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today AND jam_pulang_iso IS NULL ORDER BY jam_masuk_iso DESC LIMIT 1");
            $todayCheck->execute([':uid' => $u['id'], ':today' => $today]);
            $todayRow = $todayCheck->fetch();
            
            if (!$todayRow) {
                $statusText = "Anda belum melakukan presensi masuk hari ini atau sudah pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
            } else {
                $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
                $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
                $lokasi = $_POST['lokasi'] ?? null;
                
                // Convert coordinates to readable address if we have coordinates but no location name
                if ($lat !== null && $lng !== null && (!$lokasi || strpos($lokasi, 'Lokasi:') === 0)) {
                    $reverseGeocoded = reverseGeocodeAddress($lat, $lng);
                    if ($reverseGeocoded) {
                        $lokasi = $reverseGeocoded;
                    }
                }
                
                $upd = $pdo->prepare("UPDATE attendance SET jam_pulang=:jam, jam_pulang_iso=:iso, ekspresi_pulang=:exp, screenshot_pulang=:screenshot, lokasi_pulang=:lokasi, lat_pulang=:lat, lng_pulang=:lng WHERE id=:id");
                $upd->execute([':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':id' => $todayRow['id']]);
                
                // Trigger backup setelah presensi pulang
                triggerDatabaseBackup();
                $jamPulangFormat = substr($jamSekarang, 0, 5); // Ambil hanya jam:menit
                $firstName = getFirstName($u['nama']);
                $statusText = "Selamat jalan, {$firstName}! Anda terlihat {$ekspresi}. Jam pulang tercatat pukul {$jamPulangFormat}.";
                jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamPulangFormat, 'statusClass' => 'bg-green-100 text-green-700']);
            }
        }
    }

    if ($action === 'delete_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = $_POST['id'] ?? '';
        
        // Check if this is an attendance_notes record (starts with 'note_')
        if (strpos($id, 'note_') === 0) {
            // Extract the actual ID from 'note_123' format
            $actualId = (int)substr($id, 5);
            
            // Get the attendance_notes record to get user_id and date
            $stmt = $pdo->prepare("SELECT user_id, date FROM attendance_notes WHERE id=:id");
            $stmt->execute([':id' => $actualId]);
            $note = $stmt->fetch();
            
            if ($note) {
                // Delete related daily report
                $pdo->prepare("DELETE FROM daily_reports WHERE user_id=:user_id AND report_date=:date")->execute([
                    ':user_id' => $note['user_id'],
                    ':date' => $note['date']
                ]);
            }
            
            $pdo->prepare("DELETE FROM attendance_notes WHERE id=:id")->execute([':id' => $actualId]);
        } else {
            // Regular attendance record
            $actualId = (int)$id;
            
            // Get the attendance record to get user_id and date
            $stmt = $pdo->prepare("SELECT user_id, DATE(jam_masuk_iso) as report_date FROM attendance WHERE id=:id");
            $stmt->execute([':id' => $actualId]);
            $attendance = $stmt->fetch();
            
            if ($attendance) {
                // Delete related daily report
                $pdo->prepare("DELETE FROM daily_reports WHERE user_id=:user_id AND report_date=:date")->execute([
                    ':user_id' => $attendance['user_id'],
                    ':date' => $attendance['report_date']
                ]);
            }
            
            $pdo->prepare("DELETE FROM attendance WHERE id=:id")->execute([':id' => $actualId]);
        }
        
        // Trigger backup setelah menghapus attendance/notes
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true]);
    }

    // Update bukti izin/sakit
    // FaceNet endpoints
    if ($action === 'generate_face_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
        
        $base64Image = $_POST['image'] ?? '';
        if (empty($base64Image)) {
            jsonResponse(['error' => 'Image is required'], 400);
        }
        
        // Use the new save_embedding endpoint
        $data = [
            'action' => 'save_embedding',
            'image' => $base64Image,
            'user_id' => $_SESSION['user']['id']
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                jsonResponse(['ok' => true, 'message' => 'Face embedding generated and saved successfully']);
            } else {
                jsonResponse(['error' => $result['error'] ?? 'Failed to generate face embedding'], 500);
            }
        } else {
            jsonResponse(['error' => 'Failed to generate face embedding'], 500);
        }
    }

    if ($action === 'recognize_face' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $base64Image = $_POST['image'] ?? '';
        if (empty($base64Image)) {
            jsonResponse(['error' => 'Image is required'], 400);
        }
        
        // Use the new recognize_face endpoint
        $data = [
            'action' => 'recognize_face',
            'image' => $base64Image,
            'threshold' => 1.0
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1); // ULTRA-FAST: 1 second timeout
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                jsonResponse(['ok' => true, 'data' => $result['data']]);
            } else {
                jsonResponse(['error' => $result['error'] ?? 'Face recognition failed'], 500);
            }
        } else {
            jsonResponse(['error' => 'Face recognition failed'], 500);
        }
    }

if ($action === 'process_attendance_facenet' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    // Use the new process_attendance endpoint
    $data = [
        'action' => 'process_attendance',
        'image' => $base64Image,
        'threshold' => 1.0
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Attendance processing failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Attendance processing failed'], 500);
    }
}

    // Settings helpers for client usage
    if ($action === 'get_setting') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $key = $_GET['key'] ?? ($_POST['key'] ?? '');
        if(!$key) jsonResponse(['ok'=>false,'message'=>'key kosong'],400);
        $stmt=$pdo->prepare("SELECT setting_value FROM settings WHERE setting_key=:k LIMIT 1");
        $stmt->execute([':k'=>$key]);
        $val = $stmt->fetchColumn();
        jsonResponse(['ok'=>true,'value'=>$val]);
    }
    if ($action === 'save_setting' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $key = $_POST['key'] ?? '';
        $value = $_POST['value'] ?? '';
        if(!$key) jsonResponse(['ok'=>false,'message'=>'key kosong'],400);
        $stmt=$pdo->prepare("INSERT INTO settings(setting_key,setting_value) VALUES(:k,:v) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
        $stmt->execute([':k'=>$key, ':v'=>$value]);
        triggerDatabaseBackup();
        jsonResponse(['ok'=>true,'message'=>'Pengaturan disimpan']);
    }

// Enhanced FaceNet AJAX Endpoints
if ($action === 'generate_enhanced_face_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    // Use the enhanced save_embedding endpoint
    $data = [
        'action' => 'save_enhanced_embedding',
        'image' => $base64Image,
        'user_id' => $_SESSION['user']['id']
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'message' => 'Enhanced face embedding generated and saved successfully']);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Failed to generate enhanced face embedding'], 500);
        }
    } else {
        jsonResponse(['error' => 'Failed to generate enhanced face embedding'], 500);
    }
}

if ($action === 'recognize_enhanced_face' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    // Use the enhanced recognize_face endpoint
    $data = [
        'action' => 'recognize_enhanced_face',
        'image' => $base64Image,
        'threshold' => 1.0
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Enhanced face recognition failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Enhanced face recognition failed'], 500);
    }
}

if ($action === 'process_enhanced_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    // Use the enhanced process_attendance endpoint
    $data = [
        'action' => 'process_enhanced_attendance',
        'image' => $base64Image,
        'threshold' => 1.0
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $response) {
        $result = json_decode($response, true);
        if ($result && $result['success']) {
            jsonResponse(['ok' => true, 'data' => $result['data']]);
        } else {
            jsonResponse(['error' => $result['error'] ?? 'Enhanced attendance processing failed'], 500);
        }
    } else {
        jsonResponse(['error' => 'Enhanced attendance processing failed'], 500);
    }
}

// High Accuracy FaceNet Endpoints
if ($action === 'process_high_accuracy_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $userId = $_SESSION['user']['id'] ?? null;
    $result = processHighAccuracyAttendance($base64Image, $userId);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'High accuracy attendance processing failed'], 500);
    }
}

if ($action === 'generate_high_accuracy_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $userId = $_SESSION['user']['id'];
    $result = generateHighAccuracyEmbedding($base64Image, $userId);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'High accuracy embedding generation failed'], 500);
    }
}

if ($action === 'get_high_accuracy_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
    
    $stats = getHighAccuracyPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get high accuracy performance stats'], 500);
    }
}

// Optimized FaceNet Endpoints - iPhone-like Performance
if ($action === 'process_optimized_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    $threshold = floatval($_POST['threshold'] ?? 0.5);
    
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = processOptimizedAttendance($base64Image, $threshold);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized attendance processing failed'], 500);
    }
}

if ($action === 'recognize_face_optimized' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    $threshold = floatval($_POST['threshold'] ?? 0.5);
    
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = recognizeFaceOptimized($base64Image, $threshold);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized face recognition failed'], 500);
    }
}

if ($action === 'generate_optimized_embedding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = generateOptimizedEmbedding($base64Image);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Optimized embedding generation failed'], 500);
    }
}

if ($action === 'get_optimized_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
    
    $stats = getOptimizedPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get optimized performance stats'], 500);
    }
}

// Ultra Accurate FaceNet Endpoints - Maximum Accuracy with Ultra-Fast Response
if ($action === 'process_ultra_accurate_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    $validationLevel = $_POST['validation_level'] ?? 'normal';
    
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = processUltraAccurateAttendance($base64Image, $validationLevel);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Ultra accurate attendance processing failed'], 500);
    }
}

if ($action === 'get_ultra_accurate_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
    
    $stats = getUltraAccuratePerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get ultra accurate performance stats'], 500);
    }
}

// iPhone-Level Accurate FaceNet Endpoints - Maximum Accuracy with Unique Feature Analysis
if ($action === 'process_iphone_level_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = processIPhoneLevelAttendance($base64Image);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'iPhone-level attendance processing failed'], 500);
    }
}

if ($action === 'get_iphone_level_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
    
    $stats = getIPhoneLevelPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get iPhone-level performance stats'], 500);
    }
}

// Ultra Detailed FaceNet Endpoints - iPhone Face ID Level Accuracy with Super Detailed Features
if ($action === 'process_ultra_detailed_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    
    $base64Image = $_POST['image'] ?? '';
    
    if (empty($base64Image)) {
        jsonResponse(['error' => 'Image is required'], 400);
    }
    
    $result = processUltraDetailedAttendance($base64Image);
    
    if ($result) {
        jsonResponse(['ok' => true, 'data' => $result]);
    } else {
        jsonResponse(['error' => 'Ultra detailed attendance processing failed'], 500);
    }
}

if ($action === 'get_ultra_detailed_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
    
    $stats = getUltraDetailedPerformanceStats();
    if ($stats) {
        jsonResponse(['ok' => true, 'data' => $stats]);
    } else {
        jsonResponse(['error' => 'Failed to get ultra detailed performance stats'], 500);
    }
}

    if ($action === 'update_bukti_izin_sakit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
        
        $user_id = (int)$_SESSION['user']['id'];
        $date = $_POST['date'] ?? '';
        $bukti = $_POST['bukti'] ?? null;
        $action_type = $_POST['action_type'] ?? ''; // 'update' or 'delete'
        
        if (!$date) jsonResponse(['ok' => false, 'message' => 'Tanggal diperlukan'], 400);
        
        if ($action_type === 'delete') {
            // Delete bukti (set to null)
            $stmt = $pdo->prepare("UPDATE attendance_notes SET bukti = NULL WHERE user_id = :user_id AND `date` = :date");
            $stmt->execute([':user_id' => $user_id, ':date' => $date]);
        } else if ($action_type === 'update' && $bukti) {
            // Validate image data URL and size (<= 5MB)
            if (strpos($bukti, 'data:image/') !== 0) {
                jsonResponse(['ok' => false, 'message' => 'Format bukti tidak valid. Harus berupa gambar.'], 400);
            }
            $sizeCheck = checkImageSize($bukti, 5);
            if (!$sizeCheck['valid']) {
                jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
            }
            
            // Update bukti
            $stmt = $pdo->prepare("UPDATE attendance_notes SET bukti = :bukti WHERE user_id = :user_id AND `date` = :date");
            $stmt->execute([':bukti' => $bukti, ':user_id' => $user_id, ':date' => $date]);
        } else {
            jsonResponse(['ok' => false, 'message' => 'Data tidak valid'], 400);
        }
        
        // Trigger backup setelah update
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true, 'message' => 'Bukti berhasil diperbarui']);
    }

    // Admin: add izin/sakit record
    if ($action === 'admin_add_absence' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $user_id = (int)($_POST['user_id'] ?? 0);
        $date = $_POST['date'] ?? date('Y-m-d');
        $type = $_POST['type'] ?? 'izin'; // izin/sakit/wfa
        $jam_masuk = $_POST['jam_masuk'] ?? null;
        $jam_pulang = $_POST['jam_pulang'] ?? null;

        if(!$user_id) jsonResponse(['ok'=>false,'message'=>'Pilih pegawai'],400);
        if(!in_array($type, ['izin','sakit','wfa','overtime'], true)) jsonResponse(['ok'=>false,'message'=>'Tipe tidak valid'],400);

        // Logic for setting time based on type
        $jam_masuk_iso = null;
        $jam_pulang_iso = null;
        $status = 'ontime';

        if ($type === 'wfa' || $type === 'overtime') {
            if (!$jam_masuk || !$jam_pulang) {
                jsonResponse(['ok' => false, 'message' => 'Jam masuk dan pulang wajib diisi untuk tipe ini'], 400);
            }
            $jam_masuk_iso = $date . ' ' . $jam_masuk . ':00';
            $jam_pulang_iso = $date . ' ' . $jam_pulang . ':00';
        } else {
            // For Izin/Sakit, use the selected date with default times
            $jam_masuk_iso = $date . ' 08:00:00';  // Default 08:00 for masuk
            $jam_pulang_iso = $date . ' 17:00:00'; // Default 17:00 for pulang
            $jam_masuk = '08:00';
            $jam_pulang = '17:00';
        }

        // Avoid duplicates for day
        $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id=:u AND DATE(jam_masuk_iso)=:d");
        $check->execute([':u' => $user_id, ':d' => $date]);
        if($check->fetch()) jsonResponse(['ok' => false, 'message' => 'Data hari tersebut sudah ada'], 400);

        $alasan_izin_sakit = $_POST['alasan_izin_sakit'] ?? null;
        $bukti_izin_sakit = $_POST['bukti_izin_sakit'] ?? null;
        
        // Check if type is izin or sakit - if so, insert to attendance_notes instead
        if (in_array($type, ['izin', 'sakit'])) {
            try {
                // Insert to attendance_notes table
                $sql = "INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti, created_at) VALUES (:u, :date, :type, :keterangan, :bukti, NOW())";
                $ins = $pdo->prepare($sql);
                $result = $ins->execute([
                    ':u' => $user_id,
                    ':date' => $date,
                    ':type' => $type,
                    ':keterangan' => $alasan_izin_sakit ?: 'Tidak ada keterangan',
                    ':bukti' => $bukti_izin_sakit
                ]);
                
                if ($result) {
                    error_log("Admin add absence - Successfully inserted $type record to attendance_notes for user $user_id on date $date");
                } else {
                    error_log("Admin add absence - Failed to insert $type record to attendance_notes. Error: " . print_r($ins->errorInfo(), true));
                    jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan data izin/sakit']);
                }
            } catch (PDOException $e) {
                error_log("Admin add absence - PDO Error inserting $type record to attendance_notes: " . $e->getMessage());
                jsonResponse(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        } else {
            try {
                // Insert to attendance table for wfa/overtime
                $sql = "INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_izin_sakit, bukti_izin_sakit) VALUES (:u, :jm, :jmiso, :jp, :jpiso, :s, :ket, :alasan, :bukti)";
                $ins = $pdo->prepare($sql);
                $result = $ins->execute([
                    ':u' => $user_id,
                    ':jm' => $jam_masuk,
                    ':jmiso' => $jam_masuk_iso,
                    ':jp' => $jam_pulang,
                    ':jpiso' => $jam_pulang_iso,
                    ':s' => $status,
                    ':ket' => $type,
                    ':alasan' => $alasan_izin_sakit,
                    ':bukti' => $bukti_izin_sakit
                ]);
                
                if ($result) {
                    error_log("Admin add absence - Successfully inserted $type record to attendance table for user $user_id on date $date");
                } else {
                    error_log("Admin add absence - Failed to insert $type record to attendance table. Error: " . print_r($ins->errorInfo(), true));
                    jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan data presensi']);
                }
            } catch (PDOException $e) {
                error_log("Admin add absence - PDO Error inserting $type record to attendance table: " . $e->getMessage());
                jsonResponse(['ok' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
        }
        
        // Trigger backup setelah menambah data absence
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true]);
    }

    // Admin: update attendance row
    if ($action === 'admin_update_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        if(!$id) jsonResponse(['ok'=>false,'message'=>'ID tidak valid'],400);
        
        // Get current attendance record to check if ket is being changed to izin/sakit
        $currentStmt = $pdo->prepare("SELECT user_id, DATE(jam_masuk_iso) as attendance_date, ket FROM attendance WHERE id = :id");
        $currentStmt->execute([':id' => $id]);
        $currentRecord = $currentStmt->fetch();
        
        // Debug logging for current record
        error_log("Admin update attendance - Current record query result: " . print_r($currentRecord, true));
        
        $fields = ['jam_masuk','jam_pulang','ekspresi_masuk','ekspresi_pulang','status','ket','screenshot_masuk','screenshot_pulang','alasan_wfa','alasan_izin_sakit','bukti_izin_sakit'];
        $set=[]; $params=[':id'=>$id];
        foreach($fields as $f){ if(isset($_POST[$f])){ $set[] = "$f = :$f"; $params[":$f"] = $_POST[$f]!==''? $_POST[$f] : null; } }
        if(isset($_POST['jam_masuk_iso'])){ $set[]='jam_masuk_iso=:jmiso'; $params[':jmiso']= $_POST['jam_masuk_iso'] ?: null; }
        if(isset($_POST['jam_pulang_iso'])){ $set[]='jam_pulang_iso=:jpiso'; $params[':jpiso']= $_POST['jam_pulang_iso'] ?: null; }
        if(!$set) jsonResponse(['ok'=>false,'message'=>'Tidak ada perubahan'],400);
        
        // Check if ket is being changed to izin or sakit
        $newKet = $_POST['ket'] ?? '';
        $isChangingToIzinSakit = in_array($newKet, ['izin', 'sakit']) && $currentRecord;
        
        // Debug logging
        error_log("Admin update attendance - ID: $id, New ket: '$newKet', Current ket: '{$currentRecord['ket']}', Is changing to izin/sakit: " . ($isChangingToIzinSakit ? 'YES' : 'NO'));
        error_log("Admin update attendance - POST data: " . print_r($_POST, true));
        error_log("Admin update attendance - Current record: " . print_r($currentRecord, true));
        
        if ($isChangingToIzinSakit) {
            // Check if record already exists in attendance_notes
            $checkStmt = $pdo->prepare("SELECT id FROM attendance_notes WHERE user_id = :user_id AND date = :date");
            $checkStmt->execute([
                ':user_id' => $currentRecord['user_id'],
                ':date' => $currentRecord['attendance_date']
            ]);
            $existingNote = $checkStmt->fetch();
            
            if ($existingNote) {
                // Update existing record in attendance_notes
                $updateStmt = $pdo->prepare("
                    UPDATE attendance_notes 
                    SET type = :type, keterangan = :keterangan, bukti = :bukti, created_at = NOW()
                    WHERE id = :id
                ");
                $result = $updateStmt->execute([
                    ':id' => $existingNote['id'],
                    ':type' => $newKet,
                    ':keterangan' => $_POST['alasan_izin_sakit'] ?: 'Tidak ada keterangan',
                    ':bukti' => $_POST['bukti_izin_sakit'] ?? ''
                ]);
                
                if ($result) {
                    // Delete from attendance table
                    $deleteStmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
                    $deleteStmt->execute([':id' => $id]);
                    
                    error_log("Admin successfully updated attendance_notes record {$existingNote['id']} as $newKet for user {$currentRecord['user_id']} on date {$currentRecord['attendance_date']}");
                } else {
                    error_log("Admin failed to update attendance_notes record. Error: " . print_r($updateStmt->errorInfo(), true));
                }
            } else {
                // Insert new record to attendance_notes
                $notesStmt = $pdo->prepare("
                    INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti, created_at) 
                    VALUES (:user_id, :date, :type, :keterangan, :bukti, NOW())
                ");
                $result = $notesStmt->execute([
                    ':user_id' => $currentRecord['user_id'],
                    ':date' => $currentRecord['attendance_date'],
                    ':type' => $newKet,
                    ':keterangan' => $_POST['alasan_izin_sakit'] ?: 'Tidak ada keterangan',
                    ':bukti' => $_POST['bukti_izin_sakit'] ?? ''
                ]);
                
                if ($result) {
                    // Delete from attendance table
                    $deleteStmt = $pdo->prepare("DELETE FROM attendance WHERE id = :id");
                    $deleteStmt->execute([':id' => $id]);
                    
                    error_log("Admin successfully moved attendance record $id to attendance_notes as $newKet for user {$currentRecord['user_id']} on date {$currentRecord['attendance_date']}");
                } else {
                    error_log("Admin failed to move attendance record $id to attendance_notes. Error: " . print_r($notesStmt->errorInfo(), true));
                }
            }
        } else {
            // Normal update in attendance table
            error_log("Admin update attendance - Performing normal update in attendance table for ID: $id");
            $sql="UPDATE attendance SET ".implode(',', $set)." WHERE id=:id";
            $pdo->prepare($sql)->execute($params);
            error_log("Admin update attendance - Normal update completed for ID: $id");
        }
        
        // Trigger backup setelah update attendance
        triggerDatabaseBackup();
        
        jsonResponse(['ok'=>true]);
    }

    // Admin: update WFA location data to use readable addresses
    if ($action === 'admin_update_wfa_locations' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        // Get all WFA records with coordinate-based locations
        $stmt = $pdo->prepare("SELECT id, lat_masuk, lng_masuk, lokasi_masuk, lat_pulang, lng_pulang, lokasi_pulang FROM attendance WHERE ket = 'wfa' AND (lokasi_masuk LIKE 'Lokasi:%' OR lokasi_pulang LIKE 'Lokasi:%')");
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $updated = 0;
        foreach ($records as $record) {
            $updates = [];
            $params = [':id' => $record['id']];
            
            // Update masuk location if needed
            if ($record['lat_masuk'] && $record['lng_masuk'] && strpos($record['lokasi_masuk'], 'Lokasi:') === 0) {
                $newLocation = reverseGeocodeAddress($record['lat_masuk'], $record['lng_masuk']);
                if ($newLocation) {
                    $updates[] = 'lokasi_masuk = :lokasi_masuk';
                    $params[':lokasi_masuk'] = $newLocation;
                }
            }
            
            // Update pulang location if needed
            if ($record['lat_pulang'] && $record['lng_pulang'] && strpos($record['lokasi_pulang'], 'Lokasi:') === 0) {
                $newLocation = reverseGeocodeAddress($record['lat_pulang'], $record['lng_pulang']);
                if ($newLocation) {
                    $updates[] = 'lokasi_pulang = :lokasi_pulang';
                    $params[':lokasi_pulang'] = $newLocation;
                }
            }
            
            if (!empty($updates)) {
                $sql = "UPDATE attendance SET " . implode(', ', $updates) . " WHERE id = :id";
                $upd = $pdo->prepare($sql);
                $upd->execute($params);
                $updated++;
            }
        }
        
        jsonResponse(['ok' => true, 'message' => "Berhasil memperbarui {$updated} lokasi WFA menjadi nama jalan"]);
    }

    // Admin: get backup status
    if ($action === 'get_backup_status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        if (!function_exists('getBackupInfo')) {
            jsonResponse(['ok' => false, 'message' => 'Backup functions not available']);
        }
        
        $backupInfo = getBackupInfo();
        jsonResponse(['ok' => true, 'data' => $backupInfo]);
    }

    // Admin: create manual backup
    if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        if (!function_exists('createDatabaseBackup')) {
            jsonResponse(['ok' => false, 'message' => 'Backup functions not available']);
        }
        
        $result = createDatabaseBackup();
        jsonResponse($result);
    }

    // Admin: get settings
    if ($action === 'get_settings' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $stmt = $pdo->prepare("SELECT setting_key, setting_value, description FROM settings ORDER BY setting_key");
        $stmt->execute();
        $settings = [];
        while ($row = $stmt->fetch()) {
            $settings[$row['setting_key']] = [
                'value' => $row['setting_value'],
                'description' => $row['description']
            ];
        }
        jsonResponse(['ok' => true, 'data' => $settings]);
    }

    // Employee: submit izin/sakit for today
    if ($action === 'submit_izin_sakit' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        // Ensure authenticated session
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
            jsonResponse(['ok' => false, 'message' => 'Unauthorized'], 401);
        }
        
        // Debug logging
        error_log("submit_izin_sakit: Starting process");
        error_log("submit_izin_sakit: POST data: " . print_r($_POST, true));
        
        // Test database connection
        try {
            $pdo->query("SELECT 1");
            error_log("submit_izin_sakit: Database connection OK");
        } catch (PDOException $e) {
            error_log("submit_izin_sakit: Database connection failed: " . $e->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Database connection failed'], 500);
        }
        
        $user_id = (int)$_SESSION['user']['id'];
        $type = $_POST['type'] ?? ''; // izin/sakit
        $alasan = trim($_POST['alasan'] ?? '');
        $bukti = $_POST['bukti'] ?? null; // base64 image
        
        error_log("submit_izin_sakit: Parsed data - user_id: $user_id, type: $type, alasan: $alasan, bukti length: " . (is_string($bukti) ? strlen($bukti) : 'null'));
        
        if (!in_array($type, ['izin', 'sakit'], true)) {
            jsonResponse(['ok' => false, 'message' => 'Tipe tidak valid'], 400);
        }
        
        if (!$alasan) {
            jsonResponse(['ok' => false, 'message' => 'Alasan harus diisi'], 400);
        }
        
        if (!$bukti || empty($bukti)) {
            jsonResponse(['ok' => false, 'message' => 'Bukti harus diupload'], 400);
        }
        // Validate image data URL and size (<= 5MB)
        if (strpos($bukti, 'data:image/') !== 0) {
            jsonResponse(['ok' => false, 'message' => 'Format bukti tidak valid. Harus berupa gambar.'], 400);
        }
        $sizeCheck = checkImageSize($bukti, 5);
        if (!$sizeCheck['valid']) {
            jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
        }

        // Validate user exists to avoid foreign key error
        try {
            $chkUser = $pdo->prepare("SELECT id FROM users WHERE id=:id LIMIT 1");
            $chkUser->execute([':id' => $user_id]);
            if (!$chkUser->fetch()) {
                jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 401);
            }
        } catch (PDOException $_) {
            jsonResponse(['ok' => false, 'message' => 'Database error saat validasi user'], 500);
        }
        
        // Check if already has attendance or notes for today
        $today = date('Y-m-d');
        error_log("submit_izin_sakit: Checking for existing records for user $user_id on $today");
        
        $checkAttendance = $pdo->prepare("SELECT id FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today");
        $checkAttendance->execute([':uid' => $user_id, ':today' => $today]);
        $existingAttendance = $checkAttendance->fetch();
        error_log("submit_izin_sakit: Existing attendance: " . ($existingAttendance ? 'found' : 'none'));
        
        // Optional: check notes existence (for logging only)
        try {
            $checkNotes = $pdo->prepare("SELECT id FROM attendance_notes WHERE user_id=:uid AND `date`=:today");
            $checkNotes->execute([':uid' => $user_id, ':today' => $today]);
            $hasNotesRow = $checkNotes->fetch();
            error_log("submit_izin_sakit: Existing notes: " . ($hasNotesRow ? 'found' : 'none'));
        } catch (PDOException $e) {
            // Table doesn't exist yet, continue
            error_log("Attendance notes table not found when checking existence: " . $e->getMessage());
        }
        
        // Block ONLY if there is already attendance today
        if ($existingAttendance) {
            error_log("submit_izin_sakit: Blocked - already has attendance today");
            jsonResponse(['ok' => false, 'message' => 'Sudah ada presensi untuk hari ini'], 400);
        }
        
        // Ensure attendance_notes table has correct structure
        try {
            // Check and add missing columns
            $requiredColumns = [
                'type' => "ENUM('izin','sakit') NOT NULL AFTER `date`",
                'keterangan' => "TEXT NOT NULL AFTER type",
                'bukti' => "LONGTEXT NULL AFTER keterangan"
            ];
            
            foreach ($requiredColumns as $columnName => $columnDef) {
                $checkColumn = $pdo->query("SHOW COLUMNS FROM attendance_notes LIKE '$columnName'");
                if ($checkColumn->rowCount() == 0) {
                    error_log("submit_izin_sakit: Adding missing '$columnName' column to attendance_notes table");
                    $pdo->exec("ALTER TABLE attendance_notes ADD COLUMN $columnName $columnDef");
                }
            }
        } catch (PDOException $e) {
            error_log("submit_izin_sakit: Error checking/adding columns: " . $e->getMessage());
        }

        // Insert/Update izin/sakit record to attendance_notes (idempotent)
        error_log("submit_izin_sakit: Attempting to insert record");
        try {
            $sql = "INSERT INTO attendance_notes (user_id, `date`, type, keterangan, bukti) 
                    VALUES (:uid, :date, :type, :keterangan, :bukti)
                    ON DUPLICATE KEY UPDATE type = VALUES(type), keterangan = VALUES(keterangan), bukti = VALUES(bukti)";
            $ins = $pdo->prepare($sql);
            $result = $ins->execute([
                ':uid' => $user_id,
                ':date' => $today,
                ':type' => $type,
                ':keterangan' => $alasan,
                ':bukti' => $bukti
            ]);
            
            error_log("submit_izin_sakit: Insert result: " . ($result ? 'success' : 'failed'));
            error_log("submit_izin_sakit: Inserted ID: " . $pdo->lastInsertId());
            
            triggerDatabaseBackup();
            error_log("submit_izin_sakit: Process completed successfully");
            jsonResponse(['ok' => true, 'message' => 'Data izin/sakit berhasil disimpan']);
        } catch (PDOException $e) {
            error_log("Error inserting attendance notes: " . $e->getMessage());
            error_log("Error details: " . print_r($e, true));
            
            // If table doesn't exist, try to create it and retry
            if (strpos($e->getMessage(), "doesn't exist") !== false || strpos($e->getMessage(), "Unknown table") !== false) {
                error_log("submit_izin_sakit: Table doesn't exist, attempting to create");
                try {
                    // Create the attendance_notes table
                    $pdo->exec(
                        "CREATE TABLE IF NOT EXISTS attendance_notes (
                            id INT AUTO_INCREMENT PRIMARY KEY,
                            user_id INT NOT NULL,
                            `date` DATE NOT NULL,
                            type ENUM('izin','sakit') NOT NULL,
                            keterangan TEXT NOT NULL,
                            bukti LONGTEXT NULL,
                            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                            INDEX(user_id),
                            UNIQUE KEY unique_user_date (user_id, `date`),
                            CONSTRAINT fk_an_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                    );
                    // Best-effort ensure unique key exists
                    try { $pdo->exec("ALTER TABLE attendance_notes ADD UNIQUE KEY unique_user_date (user_id, `date`)"); } catch (PDOException $_) {}
                    
                    error_log("submit_izin_sakit: Table created, retrying insert");
                    
                    // Retry the insert
                    $ins = $pdo->prepare($sql);
                    $result = $ins->execute([
                        ':uid' => $user_id,
                        ':date' => $today,
                        ':type' => $type,
                        ':keterangan' => $alasan,
                        ':bukti' => $bukti
                    ]);
                    
                    error_log("submit_izin_sakit: Retry insert result: " . ($result ? 'success' : 'failed'));
                    
                    triggerDatabaseBackup();
                    jsonResponse(['ok' => true, 'message' => 'Data izin/sakit berhasil disimpan']);
                } catch (PDOException $e2) {
                    error_log("Error creating table and retrying: " . $e2->getMessage());
                    error_log("Error details: " . print_r($e2, true));
                    jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan data. Silakan coba lagi.'], 500);
                }
            } else if (strpos($e->getMessage(), '1062') !== false || stripos($e->getMessage(), 'Duplicate') !== false) {
                // Duplicate key: update existing row to be idempotent
                error_log("submit_izin_sakit: Duplicate detected, performing update");
                try {
                    $upd = $pdo->prepare("UPDATE attendance_notes SET type=:type, keterangan=:keterangan, bukti=:bukti WHERE user_id=:uid AND `date`=:date");
                    $upd->execute([
                        ':type' => $type,
                        ':keterangan' => $alasan,
                        ':bukti' => $bukti,
                        ':uid' => $user_id,
                        ':date' => $today
                    ]);
                    triggerDatabaseBackup();
                    jsonResponse(['ok' => true, 'message' => 'Data izin/sakit berhasil diperbarui']);
                } catch (PDOException $e3) {
                    error_log("submit_izin_sakit: Update after duplicate failed: " . $e3->getMessage());
                    jsonResponse(['ok' => false, 'message' => 'Gagal memperbarui data. Silakan coba lagi.'], 500);
                }
            } else {
                error_log("submit_izin_sakit: Other database error occurred");
                jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan data. Silakan coba lagi.'], 500);
            }
        }
    }

    // Admin: update settings
    if ($action === 'update_settings' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $maxOntimeHour = trim($_POST['max_ontime_hour'] ?? '');
        $minCheckoutHour = trim($_POST['min_checkout_hour'] ?? '');
        $wfoAddress = trim($_POST['wfo_address'] ?? '');
        $wfoLat = trim($_POST['wfo_lat'] ?? '');
        $wfoLng = trim($_POST['wfo_lng'] ?? '');
        $wfoRadius = trim($_POST['wfo_radius_m'] ?? '');
        $periodEnd = trim($_POST['attendance_period_end'] ?? '');
        $kpiLatePenalty = trim($_POST['kpi_late_penalty'] ?? '');
        $kpiIzinSakit = trim($_POST['kpi_izin_sakit'] ?? '');
        $kpiAlpha = trim($_POST['kpi_alpha'] ?? '');
        $kpiOvertimeBonus = trim($_POST['kpi_overtime_bonus'] ?? '');
        
        // WFO API settings
        $wfoMode = trim($_POST['wfo_mode'] ?? '');
        $wfoApiProvider = trim($_POST['wfo_api_provider'] ?? '');
        $wfoApiToken = trim($_POST['wfo_api_token'] ?? '');
        $wfoApiOrgKeywords = trim($_POST['wfo_api_org_keywords'] ?? '');
        $wfoApiAsnList = trim($_POST['wfo_api_asn_list'] ?? '');
        $wfoApiCidrList = trim($_POST['wfo_api_cidr_list'] ?? '');
        
        if (!is_numeric($maxOntimeHour) || $maxOntimeHour < 0 || $maxOntimeHour > 23) {
            jsonResponse(['ok' => false, 'message' => 'Jam maksimal ontime harus berupa angka 0-23'], 400);
        }
        if (!is_numeric($minCheckoutHour) || $minCheckoutHour < 0 || $minCheckoutHour > 23) {
            jsonResponse(['ok' => false, 'message' => 'Jam minimal checkout harus berupa angka 0-23'], 400);
        }
        if ($kpiLatePenalty !== '' && (!is_numeric($kpiLatePenalty) || $kpiLatePenalty < 0 || $kpiLatePenalty > 100)) {
            jsonResponse(['ok' => false, 'message' => 'Pengurangan KPI per menit terlambat harus berupa angka 0-100'], 400);
        }
        if ($kpiIzinSakit !== '' && (!is_numeric($kpiIzinSakit) || $kpiIzinSakit < 0 || $kpiIzinSakit > 100)) {
            jsonResponse(['ok' => false, 'message' => 'Nilai KPI izin/sakit harus berupa angka 0-100'], 400);
        }
        if ($kpiAlpha !== '' && (!is_numeric($kpiAlpha) || $kpiAlpha < 0 || $kpiAlpha > 100)) {
            jsonResponse(['ok' => false, 'message' => 'Nilai KPI alpha harus berupa angka 0-100'], 400);
        }
        if ($kpiOvertimeBonus !== '' && (!is_numeric($kpiOvertimeBonus) || $kpiOvertimeBonus < 0 || $kpiOvertimeBonus > 100)) {
            jsonResponse(['ok' => false, 'message' => 'Bonus KPI untuk overtime harus berupa angka 0-100'], 400);
        }
        
        setSetting($pdo, 'max_ontime_hour', $maxOntimeHour);
        setSetting($pdo, 'min_checkout_hour', $minCheckoutHour);
        if ($wfoAddress !== '') {
            setSetting($pdo, 'wfo_address', $wfoAddress);
            // Best-effort geocode; don't fail settings if geocode fails
            $geo = geocodeAddress($wfoAddress);
            if ($geo) {
                setSetting($pdo, 'wfo_lat', (string)$geo['lat']);
                setSetting($pdo, 'wfo_lng', (string)$geo['lng']);
            }
        }
        if ($wfoLat !== '' && is_numeric($wfoLat)) setSetting($pdo, 'wfo_lat', $wfoLat);
        if ($wfoLng !== '' && is_numeric($wfoLng)) setSetting($pdo, 'wfo_lng', $wfoLng);
        if ($wfoRadius !== '' && is_numeric($wfoRadius)) setSetting($pdo, 'wfo_radius_m', $wfoRadius);
        if ($periodEnd !== '') setSetting($pdo, 'attendance_period_end', $periodEnd);
        if ($kpiLatePenalty !== '') setSetting($pdo, 'kpi_late_penalty_per_minute', $kpiLatePenalty);
        if ($kpiIzinSakit !== '') setSetting($pdo, 'kpi_izin_sakit_score', $kpiIzinSakit);
        if ($kpiAlpha !== '') setSetting($pdo, 'kpi_alpha_score', $kpiAlpha);
        if ($kpiOvertimeBonus !== '') setSetting($pdo, 'kpi_overtime_bonus', $kpiOvertimeBonus);
        
        // Save WFO API settings
        if ($wfoMode !== '') setSetting($pdo, 'wfo_mode', $wfoMode);
        if ($wfoApiProvider !== '') setSetting($pdo, 'wfo_api_provider', $wfoApiProvider);
        if ($wfoApiToken !== '') setSetting($pdo, 'wfo_api_token', $wfoApiToken);
        if ($wfoApiOrgKeywords !== '') setSetting($pdo, 'wfo_api_org_keywords', $wfoApiOrgKeywords);
        if ($wfoApiAsnList !== '') setSetting($pdo, 'wfo_api_asn_list', $wfoApiAsnList);
        if ($wfoApiCidrList !== '') setSetting($pdo, 'wfo_api_cidr_list', $wfoApiCidrList);
        
        // Trigger backup setelah update settings
        triggerDatabaseBackup();
        
        jsonResponse(['ok' => true, 'message' => 'Settings berhasil disimpan']);
    }

    // Admin: auto-detect WFO from current IP
    if ($action === 'auto_detect_wfo' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $provider = trim($_POST['provider'] ?? 'ipinfo');
        $token = trim($_POST['token'] ?? '');
        
        // Get current IP
        $publicIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        if ($publicIp && strpos($publicIp, ',') !== false) {
            $parts = explode(',', $publicIp);
            $publicIp = trim($parts[0]);
        }
        
        if (!$publicIp || !filter_var($publicIp, FILTER_VALIDATE_IP)) {
            jsonResponse(['ok' => false, 'message' => 'Tidak dapat menentukan IP publik'], 400);
        }
        
        $info = fetchPublicIpInfo($publicIp, $provider, $token);
        $org = $info['org'] ?? '';
        $asn = $info['asn'] ?? '';
        
        jsonResponse([
            'ok' => true, 
            'data' => [
                'ip' => $publicIp,
                'org' => $org,
                'asn' => $asn,
                'raw' => $info['raw'] ?? []
            ]
        ]);
    }

    // Admin: daily report detail and approval
    if ($action === 'get_daily_report_detail' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $uid=(int)($_POST['user_id']??0); $date=$_POST['date']??''; $id=(int)($_POST['id']??0);
        if($id){ $stmt=$pdo->prepare("SELECT dr.*, u.nama FROM daily_reports dr JOIN users u ON u.id=dr.user_id WHERE dr.id=:id"); $stmt->execute([':id'=>$id]); jsonResponse(['ok'=>true,'data'=>$stmt->fetch()]); }
        if(!$uid || !$date) jsonResponse(['ok'=>false,'message'=>'Param tidak lengkap'],400);
        $stmt=$pdo->prepare("SELECT dr.*, u.nama FROM daily_reports dr JOIN users u ON u.id=dr.user_id WHERE dr.user_id=:u AND dr.report_date=:d");
        $stmt->execute([':u'=>$uid, ':d'=>$date]);
        jsonResponse(['ok'=>true,'data'=>$stmt->fetch()]);
    }
    if ($action === 'admin_set_daily_status' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $id=(int)($_POST['id']??0); $status=$_POST['status']??''; $evaluation=$_POST['evaluation']??null;
        if(!$id || !in_array($status, ['approved','disapproved'], true)) jsonResponse(['ok'=>false,'message'=>'Param tidak valid'],400);
        $upd=$pdo->prepare("UPDATE daily_reports SET status=:s, evaluation=:e, updated_at=NOW() WHERE id=:id");
        $upd->execute([':s'=>$status, ':e'=>$evaluation, ':id'=>$id]);
        
        // Trigger backup setelah update daily report status
        triggerDatabaseBackup();
        
        jsonResponse(['ok'=>true]);
    }

    // Admin: save daily report for employee
    if ($action === 'admin_save_daily_report' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $user_id=(int)($_POST['user_id']??0); $date=$_POST['date']??''; $content=$_POST['content']??'';
        if(!$user_id || !$date || !$content) jsonResponse(['ok'=>false,'message'=>'Param tidak lengkap'],400);
        
        // Check if report already exists
        $stmt = $pdo->prepare("SELECT id, status FROM daily_reports WHERE user_id=:u AND report_date=:d");
        $stmt->execute([':u'=>$user_id, ':d'=>$date]);
        $row = $stmt->fetch();
        
        if($row) {
            // Update existing report
            $upd = $pdo->prepare("UPDATE daily_reports SET content=:c, updated_at=NOW() WHERE id=:id");
            $upd->execute([':c'=>$content, ':id'=>$row['id']]);
            
            // Trigger backup setelah update daily report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true, 'id'=>$row['id']]);
        } else {
            // Create new report
            $ins = $pdo->prepare("INSERT INTO daily_reports (user_id, report_date, content) VALUES (:u, :d, :c)");
            $ins->execute([':u'=>$user_id, ':d'=>$date, ':c'=>$content]);
            
            // Trigger backup setelah insert daily report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true, 'id'=>$pdo->lastInsertId()]);
        }
    }

    // Admin: monthly reports list and approval
    if ($action === 'admin_get_monthly_reports') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $term = strtolower(trim($_POST['term'] ?? ''));
        $startup = trim($_POST['startup'] ?? '');
        $year = (int)($_POST['year'] ?? 0);
        $month = (int)($_POST['month'] ?? 0);
        $sql = "SELECT mr.*, u.nim, u.nama, u.startup FROM monthly_reports mr JOIN users u ON u.id=mr.user_id WHERE 1=1";
        $params = [];
        if($term){ $sql.=" AND (LOWER(u.nama) LIKE :t OR LOWER(u.nim) LIKE :t)"; $params[':t']='%'.$term.'%'; }
        if($startup){ $sql.=" AND u.startup=:s"; $params[':s']=$startup; }
        if($year){ $sql.=" AND mr.year=:y"; $params[':y']=$year; }
        if($month){ $sql.=" AND mr.month=:m"; $params[':m']=$month; }
        $sql .= " ORDER BY mr.year DESC, mr.month DESC";
        $stmt=$pdo->prepare($sql); $stmt->execute($params);
        jsonResponse(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }
    
    // Admin: get monthly report detail by ID
    if ($action === 'get_monthly_report_detail' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $id = (int)($_POST['id'] ?? 0);
        if(!$id) jsonResponse(['ok'=>false,'message'=>'ID tidak valid'],400);
        $stmt = $pdo->prepare("SELECT mr.*, u.nim, u.nama FROM monthly_reports mr JOIN users u ON u.id=mr.user_id WHERE mr.id=:id");
        $stmt->execute([':id'=>$id]);
        $data = $stmt->fetch();
        if(!$data) jsonResponse(['ok'=>false,'message'=>'Laporan tidak ditemukan'],404);
        jsonResponse(['ok'=>true,'data'=>$data]);
    }
    if ($action === 'admin_set_monthly_status' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $id=(int)($_POST['id']??0); $status=$_POST['status']??'';
        if(!$id || !in_array($status, ['approved','disapproved'], true)) jsonResponse(['ok'=>false,'message'=>'Param tidak valid'],400);
        $pdo->prepare("UPDATE monthly_reports SET status=:s, updated_at=NOW() WHERE id=:id")->execute([':s'=>$status, ':id'=>$id]);
        
        // Trigger backup setelah update monthly report status
        triggerDatabaseBackup();
        
        jsonResponse(['ok'=>true]);
    }

    // Admin: get employee work schedule
    if ($action === 'admin_get_work_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $userId = (int)($_POST['user_id'] ?? 0);
        if (!$userId) jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
        
        $schedule = getEmployeeWorkSchedule($pdo, $userId);
        
        // Default schedule if none exists
        if (empty($schedule)) {
            $defaultDays = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            foreach ($defaultDays as $day) {
                $schedule[$day] = [
                    'is_working_day' => in_array($day, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']),
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00'
                ];
            }
        }
        
        jsonResponse(['ok' => true, 'data' => $schedule]);
    }

    // Admin: save employee work schedule
    if ($action === 'admin_save_work_schedule' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $userId = (int)($_POST['user_id'] ?? 0);
        $scheduleData = $_POST['schedule'] ?? [];
        
        if (!$userId) jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
        
        try {
            // Delete existing schedule
            $pdo->prepare("DELETE FROM employee_work_schedule WHERE user_id = :user_id")
                ->execute([':user_id' => $userId]);
            
            // Insert new schedule
            $stmt = $pdo->prepare("
                INSERT INTO employee_work_schedule (user_id, day_of_week, is_working_day, start_time, end_time) 
                VALUES (:user_id, :day_of_week, :is_working_day, :start_time, :end_time)
            ");
            
            foreach ($scheduleData as $day => $data) {
                $stmt->execute([
                    ':user_id' => $userId,
                    ':day_of_week' => $day,
                    ':is_working_day' => $data['is_working_day'] ? 1 : 0,
                    ':start_time' => $data['start_time'],
                    ':end_time' => $data['end_time']
                ]);
            }
            
            // Trigger backup
            triggerDatabaseBackup();
            
            jsonResponse(['ok' => true, 'message' => 'Jadwal kerja berhasil disimpan']);
            
        } catch (PDOException $e) {
            error_log("Error saving work schedule: " . $e->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Gagal menyimpan jadwal kerja'], 500);
        }
    }

    // Dashboard endpoints
    if ($action === 'get_dashboard_data') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        
        $today = date('Y-m-d');
        // Use earliest employee registration date as period start
        $periodStart = getEarliestEmployeeRegistrationDate($pdo);
        $periodEnd = getSetting($pdo, 'attendance_period_end', '');
        if ($periodStart && $periodEnd) {
            $monthStart = $periodStart;
            $monthEnd = $periodEnd;
        } else {
            $monthStart = date('Y-m-01');
            $monthEnd = date('Y-m-t');
        }
        
        // Get today's late employees
        $todayLateStmt = $pdo->prepare("
            SELECT u.nama, u.foto_base64, a.jam_masuk, a.status
            FROM attendance a 
            JOIN users u ON u.id = a.user_id 
            WHERE DATE(a.jam_masuk_iso) = :today 
            AND a.status = 'terlambat'
            ORDER BY a.jam_masuk_iso DESC
        ");
        $todayLateStmt->execute([':today' => $today]);
        $todayLate = $todayLateStmt->fetchAll();
        
        // Get monthly attendance statistics
        $monthlyStatsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                u.foto_base64,
                COUNT(CASE WHEN a.status = 'terlambat' THEN 1 END) as late_count,
                COUNT(CASE WHEN a.status = 'ontime' THEN 1 END) as ontime_count,
                COUNT(CASE WHEN a.ket = 'wfo' OR a.ket = 'wfa' THEN 1 END) as present_count,
                COUNT(*) as total_days
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id 
                AND DATE(a.jam_masuk_iso) BETWEEN :month_start AND :month_end
            WHERE u.role = 'pegawai'
            GROUP BY u.id, u.nama, u.foto_base64
            HAVING total_days > 0
            ORDER BY late_count DESC, ontime_count ASC
        ");
        $monthlyStatsStmt->execute([':month_start' => $monthStart, ':month_end' => $monthEnd]);
        $monthlyStats = $monthlyStatsStmt->fetchAll();
        
        // Get summary statistics
        $totalEmployeesStmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'pegawai'");
        $totalEmployeesStmt->execute();
        $totalEmployees = $totalEmployeesStmt->fetch()['total'];
        
        $presentTodayStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as present 
            FROM attendance 
            WHERE DATE(jam_masuk_iso) = :today 
            AND (ket = 'wfo' OR ket = 'wfa')
        ");
        $presentTodayStmt->execute([':today' => $today]);
        $presentToday = $presentTodayStmt->fetch()['present'];
        
        $lateTodayStmt = $pdo->prepare("
            SELECT COUNT(DISTINCT user_id) as late 
            FROM attendance 
            WHERE DATE(jam_masuk_iso) = :today 
            AND status = 'terlambat'
        ");
        $lateTodayStmt->execute([':today' => $today]);
        $lateToday = $lateTodayStmt->fetch()['late'];
        
        $absentToday = $totalEmployees - $presentToday;
        
        // Get attendance trend based on configured period
        $trendData = [];
        
        // Use earliest employee registration date for trend data
        $trendStart = getEarliestEmployeeRegistrationDate($pdo);
        $trendEnd = getSetting($pdo, 'attendance_period_end', '');
        
        if ($trendStart && $trendEnd) {
            $startDate = $trendStart;
            $endDate = $trendEnd;
        } else {
            // Fallback to current year if no period configured
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
        }
        
        $currentDate = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);
        
        while ($currentDate <= $endDateTime) {
            $year = $currentDate->format('Y');
            $month = $currentDate->format('m');
            $monthName = $currentDate->format('M Y');
            
            // Skip future months (months that haven't started yet)
            $currentMonth = date('Y-m');
            $currentMonthDate = $currentDate->format('Y-m');
            if ($currentMonthDate > $currentMonth) {
                $currentDate->add(new DateInterval('P1M'));
                continue;
            }
            
            // Count ontime occurrences (not distinct users)
            $ontimeStmt = $pdo->prepare("
                SELECT COUNT(*) as ontime 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND status = 'ontime'
            ");
            $ontimeStmt->execute([':year' => $year, ':month' => $month]);
            $ontime = $ontimeStmt->fetch()['ontime'];
            
            // Count late occurrences (not distinct users)
            $lateStmt = $pdo->prepare("
                SELECT COUNT(*) as late 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND status = 'terlambat'
            ");
            $lateStmt->execute([':year' => $year, ':month' => $month]);
            $late = $lateStmt->fetch()['late'];
            
            // Count izin and sakit occurrences from both tables
            // First from attendance table
            $izinSakitStmt = $pdo->prepare("
                SELECT COUNT(*) as izin_sakit 
                FROM attendance 
                WHERE YEAR(jam_masuk_iso) = :year 
                AND MONTH(jam_masuk_iso) = :month 
                AND ket IN ('izin', 'sakit')
            ");
            $izinSakitStmt->execute([':year' => $year, ':month' => $month]);
            $izinSakitFromAttendance = $izinSakitStmt->fetch()['izin_sakit'];
            
            // Then from attendance_notes table
            $izinSakitNotesStmt = $pdo->prepare("
                SELECT COUNT(*) as izin_sakit 
                FROM attendance_notes 
                WHERE YEAR(date) = :year 
                AND MONTH(date) = :month 
                AND type IN ('izin', 'sakit')
            ");
            $izinSakitNotesStmt->execute([':year' => $year, ':month' => $month]);
            $izinSakitFromNotes = $izinSakitNotesStmt->fetch()['izin_sakit'];
            
            // Total izin/sakit (from both tables)
            $izinSakit = $izinSakitFromAttendance + $izinSakitFromNotes;
            
            // Calculate alpha occurrences
            // For current month, only count working days up to today
            // For past months, count all working days in the month
            if ($currentMonthDate == $currentMonth) {
                // Current month: only count working days up to today
                $today = new DateTime();
                $totalWorkingDaysInMonth = getWorkingDaysInMonthUpToDate($year, $month, $today->format('d'));
                
                // Debug for October 2025
                if ($month == 10 && $year == 2025) {
                    error_log("Trend Debug - October 2025 working days calculation:");
                    error_log("- Today: " . $today->format('Y-m-d'));
                    error_log("- Today day: " . $today->format('d'));
                    error_log("- Working days up to yesterday: $totalWorkingDaysInMonth");
                    
                    // Manual calculation for verification
                    $manualCount = 0;
                    $start = new DateTime("2025-10-01");
                    $end = new DateTime("2025-10-15"); // Yesterday (16-1=15)
                    while ($start <= $end) {
                        if ($start->format('N') < 6) { // Skip weekends
                            $manualCount++;
                        }
                        $start->add(new DateInterval('P1D'));
                    }
                    error_log("- Manual count (Oct 1-15): $manualCount");
                }
            } else {
                // Past months: count all working days in the month
                $totalWorkingDaysInMonth = getWorkingDaysInMonth($year, $month);
            }
            
            // Get total employees who were registered during this month
            $monthEnd = sprintf('%04d-%02d-%02d', $year, $month, date('t', strtotime(sprintf('%04d-%02d-01', $year, $month))));
            
            // For current month, use current date as end date
            $todayDate = date('Y-m-d');
            if ($monthEnd > $todayDate) {
                $monthEnd = $todayDate;
            }
            
            $employeesStmt = $pdo->prepare("
                SELECT COUNT(*) as total_employees_in_month
                FROM users 
                WHERE role = 'pegawai' 
                AND created_at <= :month_end
                AND DATE(created_at) < :month_start
            ");
            $monthStart = sprintf('%04d-%02d-01', $year, $month);
            $employeesStmt->execute([':month_end' => $monthEnd, ':month_start' => $monthStart]);
            $totalEmployeesInMonth = $employeesStmt->fetch()['total_employees_in_month'];
            
            // Debug: Check individual employee registration dates for October
            if ($month == 10 && $year == 2025) {
                $debugStmt = $pdo->prepare("
                    SELECT id, nama, created_at 
                    FROM users 
                    WHERE role = 'pegawai' 
                    AND created_at <= :month_end
                    ORDER BY created_at
                ");
                $debugStmt->execute([':month_end' => $monthEnd]);
                $allEmployees = $debugStmt->fetchAll();
                error_log("Trend Debug - All employees in October: " . count($allEmployees));
                foreach ($allEmployees as $emp) {
                    error_log("- Employee: " . $emp['nama'] . " (ID: " . $emp['id'] . ") registered: " . $emp['created_at']);
                }
            }
            
            // Calculate total possible attendance for this month
            $totalPossibleAttendance = $totalWorkingDaysInMonth * $totalEmployeesInMonth;
            
            // Calculate alpha: total possible - (ontime + late + izin/sakit)
            $alpha = $totalPossibleAttendance - ($ontime + $late + $izinSakit);
            
            // Debug logging for October
            if ($month == 10 && $year == 2025) {
                error_log("Trend Debug October 2025:");
                error_log("- Total working days: $totalWorkingDaysInMonth");
                error_log("- Total employees: $totalEmployeesInMonth");
                error_log("- Total possible attendance: $totalPossibleAttendance");
                error_log("- OnTime: $ontime");
                error_log("- Late: $late");
                error_log("- Izin/Sakit from attendance: $izinSakitFromAttendance");
                error_log("- Izin/Sakit from notes: $izinSakitFromNotes");
                error_log("- Total Izin/Sakit: $izinSakit");
                error_log("- Alpha: $alpha");
                error_log("- Total absent: " . ($izinSakit + max(0, $alpha)));
                error_log("- Expected calculation: 16 employees × 11 days = 176, 176 - 44 - 21 = 111, +1 = 112");
            }
            
            // Total absent = izin + sakit + alpha
            $absent = $izinSakit + max(0, $alpha);
            
            $trendData[] = [
                'date' => $currentDate->format('Y-m'),
                'day' => $monthName,
                'present' => $ontime,
                'late' => $late,
                'absent' => $absent
            ];
            
            $currentDate->add(new DateInterval('P1M'));
        }
        
        jsonResponse([
            'ok' => true,
            'data' => [
                'today_late' => $todayLate,
                'monthly_stats' => $monthlyStats,
                'attendance_trend' => $trendData,
                'summary' => [
                    'total_employees' => $totalEmployees,
                    'present_today' => $presentToday,
                    'late_today' => $lateToday,
                    'absent_today' => $absentToday
                ]
            ]
        ]);
    }

    // Admin: get KPI data (moved to main get_kpi_data endpoint above)

    // --- Pegawai Daily Reports API ---
    if ($action === 'get_user_info') {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid = (int)$_SESSION['user']['id'];
        $stmt = $pdo->prepare("SELECT id, nim, nama, prodi, startup FROM users WHERE id=:id");
        $stmt->execute([':id'=>$uid]);
        jsonResponse(['ok'=>true,'data'=>$stmt->fetch()]);
    }

    if ($action === 'get_rekap' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid = (int)$_SESSION['user']['id'];
        $year = (int)($_POST['year'] ?? date('Y'));
        $month = (int)($_POST['month'] ?? date('n'));
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        // Fetch attendance and reports for month
        $attStmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id=:uid AND jam_masuk_iso BETWEEN :s AND :e");
        $attStmt->execute([':uid'=>$uid, ':s'=>$start.' 00:00:00', ':e'=>$end.' 23:59:59']);
        $attRows = $attStmt->fetchAll();
        $attByDate = [];
        foreach($attRows as $r){ $d = substr($r['jam_masuk_iso']??$r['jam_pulang_iso'],0,10); $attByDate[$d] = $r; }

        // Fetch attendance notes for month (check if table exists first)
        $notesByDate = [];
        try {
            $notesStmt = $pdo->prepare("SELECT * FROM attendance_notes WHERE user_id=:uid AND date BETWEEN :s AND :e");
            $notesStmt->execute([':uid'=>$uid, ':s'=>$start, ':e'=>$end]);
            foreach($notesStmt->fetchAll() as $r){ $notesByDate[$r['date']]=$r; }
        } catch (PDOException $e) {
            // Table doesn't exist yet, continue with empty array
            error_log("Attendance notes table not found: " . $e->getMessage());
        }

        $drStmt = $pdo->prepare("SELECT * FROM daily_reports WHERE user_id=:uid AND report_date BETWEEN :s AND :e");
        $drStmt->execute([':uid'=>$uid, ':s'=>$start, ':e'=>$end]);
        $drByDate = [];
        foreach($drStmt->fetchAll() as $r){ $drByDate[$r['report_date']]=$r; }

        // Build working days Mon-Fri
        $out = [];
        $cur = new DateTime($start);
        $endDt = new DateTime($end);
        while($cur <= $endDt){
            $dow = (int)$cur->format('N'); // 1 Mon .. 7 Sun
            if($dow>=1 && $dow<=5){
                $dstr = $cur->format('Y-m-d');
                $att = $attByDate[$dstr] ?? null;
                $notes = $notesByDate[$dstr] ?? null;
                $dr = $drByDate[$dstr] ?? null;
                
                // Determine ket value
                $ket = null;
                if ($att && $att['ket']) {
                    $ket = $att['ket'];
                } elseif ($notes && $notes['type']) {
                    $ket = $notes['type'];
                }
                
                // For daily report content, use attendance_notes if available
                $reportContent = null;
                if ($dr) {
                    $reportContent = [
                        'id'=>$dr['id'], 
                        'status'=>$dr['status'], 
                        'has_content'=>!!$dr['content'], 
                        'content'=>$dr['content'], 
                        'evaluation'=>$dr['evaluation']
                    ];
                } elseif ($notes && $notes['keterangan']) {
                    // Use attendance_notes content for daily report
                    $reportContent = [
                        'id'=>null, 
                        'status'=>'auto', 
                        'has_content'=>true, 
                        'content'=>$notes['keterangan'], 
                        'evaluation'=>null
                    ];
                }
                
                $out[] = [
                    'date'=>$dstr,
                    'day'=>$cur->format('l'),
                    'jam_masuk'=>$att['jam_masuk']??null,
                    'jam_pulang'=>$att['jam_pulang']??null,
                    'status_presensi'=>$att['status']??null,
                    'ket'=>$ket,
                    'daily_report'=> $reportContent
                ];
            }
            $cur->modify('+1 day');
        }
        jsonResponse(['ok'=>true,'data'=>$out]);
    }

    if ($action === 'save_daily_report' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid = (int)$_SESSION['user']['id'];
        $date = $_POST['date'] ?? '';
        $content = $_POST['content'] ?? '';
        if(!$date) jsonResponse(['ok'=>false,'message'=>'Tanggal diperlukan'],400);
        // Upsert
        $stmt = $pdo->prepare("SELECT id, status FROM daily_reports WHERE user_id=:u AND report_date=:d");
        $stmt->execute([':u'=>$uid, ':d'=>$date]);
        $row = $stmt->fetch();
        if($row && $row['status']==='approved') jsonResponse(['ok'=>false,'message'=>'Sudah di-approve, tidak bisa diedit'],400);
        if($row){
            $upd=$pdo->prepare("UPDATE daily_reports SET content=:c, updated_at=NOW() WHERE id=:id");
            $upd->execute([':c'=>$content, ':id'=>$row['id']]);
            
            // Trigger backup setelah update daily report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true,'id'=>$row['id']]);
        } else {
            $ins=$pdo->prepare("INSERT INTO daily_reports (user_id, report_date, content) VALUES (:u,:d,:c)");
            $ins->execute([':u'=>$uid, ':d'=>$date, ':c'=>$content]);
            
            // Trigger backup setelah insert daily report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true,'id'=>$pdo->lastInsertId()]);
        }
    }

    // --- Pegawai Monthly Reports API ---
    if ($action === 'get_monthly_reports') {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid=(int)$_SESSION['user']['id'];
        $stmt=$pdo->prepare("SELECT * FROM monthly_reports WHERE user_id=:u ORDER BY year DESC, month DESC");
        $stmt->execute([':u'=>$uid]);
        jsonResponse(['ok'=>true,'data'=>$stmt->fetchAll()]);
    }

    // Fix existing data with year=0 and month=0 (one-time fix)
    if ($action === 'fix_monthly_reports' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $stmt = $pdo->prepare("UPDATE monthly_reports SET year=2025, month=8 WHERE year=0 OR month=0");
        $stmt->execute();
        
        // Trigger backup setelah fix monthly reports
        triggerDatabaseBackup();
        
        jsonResponse(['ok'=>true,'message'=>'Data berhasil diperbaiki']);
    }

    if ($action === 'save_monthly_report' && $_SERVER['REQUEST_METHOD']==='POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid=(int)$_SESSION['user']['id'];
        $year=(int)($_POST['year']??date('Y'));
        $month=(int)($_POST['month']??date('n'));
        $summary=$_POST['summary']??'';
        $achievements=$_POST['achievements']??'[]';
        $obstacles=$_POST['obstacles']??'[]';
        $submit = isset($_POST['submit']) ? filter_var($_POST['submit'], FILTER_VALIDATE_BOOLEAN) : false;
        
        // Debug logging for submit parameter
        error_log("Raw POST submit: " . ($_POST['submit'] ?? 'not set'));
        error_log("Filtered submit: " . ($submit ? 'true' : 'false'));
        
        // Validate year and month
        if($year <= 0 || $month <= 0 || $month > 12) {
            jsonResponse(['ok'=>false,'message'=>'Tahun atau bulan tidak valid'],400);
        }
        
        $stmt=$pdo->prepare("SELECT * FROM monthly_reports WHERE user_id=:u AND year=:y AND month=:m");
        $stmt->execute([':u'=>$uid, ':y'=>$year, ':m'=>$month]);
        $row=$stmt->fetch();
        if($row && in_array($row['status'], ['approved','disapproved'], true)) jsonResponse(['ok'=>false,'message'=>'Sudah final, tidak bisa diedit'],400);
        $newStatus=$submit?'belum di approve':'draft';
        
        // Debug logging
        error_log("Monthly Report Save - User: $uid, Year: $year, Month: $month, Submit: " . ($submit ? 'true' : 'false') . ", New Status: $newStatus");
        error_log("POST data submit value: " . ($_POST['submit'] ?? 'not set'));
        error_log("Boolean submit value: " . ($submit ? 'true' : 'false'));
        
        if($row){
            $upd=$pdo->prepare("UPDATE monthly_reports SET summary=:s, achievements=:a, obstacles=:o, status=:st, updated_at=NOW() WHERE id=:id");
            $result = $upd->execute([':s'=>$summary, ':a'=>$achievements, ':o'=>$obstacles, ':st'=>$newStatus, ':id'=>$row['id']]);
            error_log("Monthly Report Update - Result: " . ($result ? 'success' : 'failed') . ", Rows affected: " . $upd->rowCount());
            
            // Trigger backup setelah update monthly report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true,'id'=>$row['id']]);
        }else{
            $ins=$pdo->prepare("INSERT INTO monthly_reports (user_id, year, month, summary, achievements, obstacles, status) VALUES (:u,:y,:m,:s,:a,:o,:st)");
            $result = $ins->execute([':u'=>$uid, ':y'=>$year, ':m'=>$month, ':s'=>$summary, ':a'=>$achievements, ':o'=>$obstacles, ':st'=>$newStatus]);
            $newId = $pdo->lastInsertId();
            error_log("Monthly Report Insert - Result: " . ($result ? 'success' : 'failed') . ", New ID: $newId");
            
            // Trigger backup setelah insert monthly report
            triggerDatabaseBackup();
            
            jsonResponse(['ok'=>true,'id'=>$newId]);
        }
    }

    jsonResponse(['error' => 'Unknown endpoint'], 404);
}

// ----- PAGE ROUTING -----
$page = $_GET['page'] ?? '';
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=landing');
    exit;
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Presensi Wajah</title>
    <script src="assets/js/tailwind.js"></script>

    <script src="assets/js/face-api.min.js"></script>
    <script>
        // Expose model URL for optimizers
        window.FACEAPI_MODEL_URL = 'assets/js/face-api-models';
    </script>
    <script src="assets/js/performance-optimizer.js"></script>
    <script src="assets/js/chart.min.js"></script>
    <link rel="stylesheet" href="assets/css/inter.css">
    <link rel='stylesheet' href='assets/css/uicons-solid-rounded.css'>
    <link rel='stylesheet' href='assets/css/uicons-solid-straight.css'>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#6366f1">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="Presensi App">
    <style>
        body { font-family: 'Inter', sans-serif; }
        .loader {
            border-top-color: #3498db;
            -webkit-animation: spin 1s linear infinite;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        #video-container { position: relative; width: 100%; max-width: 720px; margin: auto; }
        #video, #canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
        /* Header account button - keep avatar inside and tidy on mobile */
        #btn-profile { max-width: 160px; }
        #btn-profile .avatar { width: 32px; height: 32px; border-radius: 9999px; object-fit: cover; flex-shrink: 0; }
        @media (max-width: 400px) {
            #btn-profile { max-width: 140px; padding-left: 0.5rem; padding-right: 0.5rem; gap: 0.5rem; }
            #btn-profile span { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 80px; display: inline-block; }
        }
        /* Mirror hanya video agar UI & teks tidak terbalik */
        .mirror-video { transform: scaleX(-1); }
        /* Ensure video crops from center on tall mobile cameras */
        #video { object-fit: cover; object-position: center center; }
        /* Bordered tables */
        table.bordered { border-collapse: collapse; width: 100%; table-layout: auto; }
        table.bordered th, table.bordered td { border: 1px solid #e5e7eb; padding: 0.5rem; text-align: center; vertical-align: middle; }
        /* Status badges */
        .badge { padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; display: inline-block; }
        .badge-gray { background: #f3f4f6; color: #374151; }
        .badge-green { background: #d1fae5; color: #065f46; }
        .badge-red { background: #fee2e2; color: #991b1b; }
        .badge-blue { background: #dbeafe; color: #1e40af; }
        .badge-yellow { background: #fde68a; color: #854d0e; }
        .btn-pill { border-radius: 9999px; padding: 0.25rem 0.75rem; font-weight: 600; }
        .z-60 { z-index: 60; }
        .z-70 { z-index: 70; }
        .max-w-7xl { max-width: 80rem; }
        
        /* Address search suggestions */
        .suggestion-item {
            transition: background-color 0.2s ease;
        }
        .suggestion-item:hover {
            background-color: #f3f4f6;
        }
        .suggestion-item.active {
            background-color: #dbeafe;
        }
        
        /* Landing page custom styles */
        .landing-panel {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 50%, #f1f5f9 100%);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .feature-check {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            box-shadow: 0 2px 4px rgba(100, 116, 139, 0.2);
        }
        
        .attendance-section {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border: 1px solid #cbd5e1;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
        }
        
        .illustration-panel {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .camera-overlay {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border: 2px solid #94a3b8;
        }
        
        .btn-attendance {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        
        .btn-attendance:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }
        
        .btn-attendance.blue {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
        }
        
        .btn-attendance.red {
            background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%);
        }
        
        /* Full height image adjustments */
        .full-height-image {
            height: calc(100vh - 80px); /* Subtract header height */
            min-height: 600px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .image-container {
            width: auto;
            height: 100%;
            max-width: 100%;
        }
        
        .image-container img {
            width: auto;
            height: 100%;
            object-fit: contain;
        }
        
        .text-panel-middle {
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 60vh; /* Reduced height to match the red box */
            min-height: 400px;
            max-height: 500px;
            margin-top: 8vh; /* Move section down to match red box position */
        }
        
        .text-panel-container {
            margin-left: 2rem; /* Same margin as right panel */
        }
        
        .image-panel-container {
            margin-right: 2rem; /* Same margin as left panel */
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .landing-panel, .illustration-panel {
                padding: 1.5rem;
            }
            
            .landing-panel h2 {
                font-size: 2rem;
            }
            
            .full-height-image {
                height: calc(100vh - 70px);
            }
            
            .text-panel-middle {
                height: 50vh;
                min-height: 350px;
                max-height: 450px;
                margin-top: 6vh; /* Adjust for tablet */
            }
            
            .text-panel-container {
                margin-left: 1rem;
            }
            
            .image-panel-container {
                margin-right: 1rem;
            }
        }
        
        @media (max-width: 768px) {
            .landing-panel h2 {
                font-size: 1.75rem;
            }
            
            .btn-attendance {
                padding: 0.75rem 1.5rem;
                font-size: 1rem;
            }
            
            .full-height-image {
                height: calc(100vh - 60px);
            }
            
            .text-panel-middle {
                height: auto;
                min-height: 300px;
                max-height: none;
                margin-top: 4vh; /* Adjust for mobile */
            }
            
            .text-panel-container {
                margin-left: 0.5rem;
            }
            
            .image-panel-container {
                margin-right: 0.5rem;
            }
        }
    </style>
</head>
<?php
    // Embed WFO settings in DOM for client-side helpers
    $embedWfoLat = htmlspecialchars(getSetting($pdo, 'wfo_lat', '-6.9738'));
    $embedWfoLng = htmlspecialchars(getSetting($pdo, 'wfo_lng', '107.6300'));
    $embedWfoAddress = htmlspecialchars(getSetting($pdo, 'wfo_address', 'Pusat WFO'));
?>
<body class="bg-gradient-to-br from-slate-50 via-gray-50 to-slate-100 text-gray-800" data-wfo-lat="<?php echo $embedWfoLat; ?>" data-wfo-lng="<?php echo $embedWfoLng; ?>" data-wfo-address="<?php echo $embedWfoAddress; ?>">

<?php 
// Public landing page: presensi can be accessed without login
if (!isset($_SESSION['user']) && (!in_array($page, ['register','login','landing'], true))) { 
    $page = 'landing'; 
}
?>

<?php if ($page === 'landing'): ?>
    <header class="bg-white shadow-sm border-b border-gray-200">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-800">Sistem Presensi Berbasis Wajah</h1>
            <div class="relative">
                <button id="btn-profile" class="flex items-center gap-3 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors overflow-hidden">
                    <span class="text-sm text-gray-700">Akun</span>
                    <img src="generate-avatar.php?background=64748b&color=ffffff&name=A&size=32" class="avatar" alt="profile">
                </button>
                <div id="dropdown-profile" class="absolute right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 hidden min-w-max">
                    <a href="?page=login" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">Login</a>
                    <a href="?page=register" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">Register</a>
                </div>
            </div>
        </div>
    </header>
    <main class="mx-auto p-4">
        <div id="page-presensi" class="">
            <!-- Video Container (hidden by default) -->
            <div id="video-container" class="bg-gray-900 rounded-lg overflow-hidden aspect-video mt-4 max-w-4xl mx-auto hidden">
                <video id="video" autoplay muted playsinline></video>
                <canvas id="canvas"></canvas>
                <div class="absolute top-3 left-3 flex gap-2">
                    <button id="btn-back-scan" class="bg-white/90 hover:bg-white text-gray-800 font-semibold py-1.5 px-3 rounded-lg hidden">Kembali</button>
                    <button id="btn-stop-detection" class="bg-red-500/90 hover:bg-red-600 text-white font-semibold py-1.5 px-3 rounded-lg hidden">Stop Deteksi</button>
                </div>
            </div>
            <div id="presensi-status" class="mt-4 text-center font-medium text-lg p-3 rounded-md hidden"></div>
                
            <!-- Log Table untuk Presensi Masuk -->
            <div id="log-masuk-container" class="mt-6 hidden">
                <h3 class="text-lg font-semibold mb-3 text-center">Log Presensi Masuk Hari Ini</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-blue-100">
                            <tr>
                                <th class="py-2 px-4">No</th>
                                <th class="py-2 px-4">Tanggal</th>
                                <th class="py-2 px-4">Nama</th>
                                <th class="py-2 px-4">Startup</th>
                                <th class="py-2 px-4">Jam Masuk</th>
                                <th class="py-2 px-4">Lokasi Masuk</th>
                                <th class="py-2 px-4">Screenshot</th>
                            </tr>
                        </thead>
                        <tbody id="log-masuk-body"></tbody>
                    </table>
                </div>
            </div>
            
            <!-- Log Table untuk Presensi Pulang -->
            <div id="log-pulang-container" class="mt-6 hidden">
                <h3 class="text-lg font-semibold mb-3 text-center">Log Presensi Pulang Hari Ini</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-red-100">
                            <tr>
                                <th class="py-2 px-4">No</th>
                                <th class="py-2 px-4">Tanggal</th>
                                <th class="py-2 px-4">Nama</th>
                                <th class="py-2 px-4">Startup</th>
                                <th class="py-2 px-4">Jam Keluar</th>
                                <th class="py-2 px-4">Screenshot</th>
                            </tr>
                        </thead>
                        <tbody id="log-pulang-body"></tbody>
                    </table>
                </div>
            </div>
            
            <!-- Two Panel Layout -->
            <div id="two-panel-layout" class="grid grid-cols-1 lg:grid-cols-3 gap-8 w-full px-4">
                <!-- Left Panel - Text Content -->
                <div class="landing-panel p-8 rounded-2xl shadow-lg lg:col-span-1 mt-12 mx-auto max-w-md md:mt-16 md:max-w-lg lg:mt-24 lg:mx-0">
                    <div class="mb-6">
                        <h2 class="text-2xl font-bold text-gray-800 mb-3">PRESENSI MUDAH, CEPAT & AKURAT.</h2>
                        <p class="text-base text-gray-600 mb-4">Selamat datang di solusi presensi wajah terdepan.</p>
                        
                        <!-- Feature List -->
                        <div class="space-y-2 mb-6">
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 feature-check rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="text-gray-700 text-sm">Anti-curang</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 feature-check rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="text-gray-700 text-sm">Integrasi mudah</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 feature-check rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="text-gray-700 text-sm">Laporan otomatis</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="w-4 h-4 feature-check rounded-full flex items-center justify-center">
                                    <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <span class="text-gray-700 text-sm">Akses real-time</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Selection moved here -->
                    <div class="attendance-section p-4 rounded-xl">
                        <h3 class="text-lg font-bold mb-3 text-center text-gray-800">Pilih Jenis Presensi</h3>
                        <div id="scan-buttons-container" class="flex flex-col gap-2">
                            <button id="btn-scan-masuk" class="btn-attendance blue text-white font-semibold py-2 px-4 rounded-lg text-base">Presensi Masuk</button>
                            <button id="btn-scan-pulang" class="btn-attendance red text-white font-semibold py-2 px-4 rounded-lg text-base">Presensi Pulang</button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Panel - Illustration -->
                <div class="full-height-image lg:col-span-2 image-panel-container">
                    <div class="image-container">
                        <img src="assets/photo/craiyon_110731_image.png" alt="Facial Recognition Illustration">
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-gray-300 py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Company Info -->
                <div>
                    <h3 class="text-xl font-bold mb-4 text-gray-200">Sistem Presensi Berbasis Wajah</h3>
                    <p class="text-gray-400 mb-4">Solusi presensi modern dengan teknologi pengenalan wajah untuk kemudahan dan keakuratan yang optimal.</p>
                    <div class="flex space-x-4">
                        <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"></path>
                                <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"></path>
                            </svg>
                        </div>
                        <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M3 5a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2h-2.22l.123.666.804.804A1 1 0 0113 18H7a1 1 0 01-.707-1.707l.804-.804L7.22 15H5a2 2 0 01-2-2V5zm5.771 7H5V5h10v7H8.771z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div class="w-8 h-8 bg-gray-700 rounded-full flex items-center justify-center hover:bg-gray-600 transition-colors">
                            <svg class="w-4 h-4 text-gray-300" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-6-3a2 2 0 11-4 0 2 2 0 014 0zm-2 4a5 5 0 00-4.546 2.916A5.986 5.986 0 0010 16a5.986 5.986 0 004.546-2.084A5 5 0 0010 11z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Links -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-200">Menu Cepat</h3>
                    <ul class="space-y-2">
                        <li><a href="?page=login" class="text-gray-400 hover:text-gray-200 transition-colors">Login</a></li>
                        <li><a href="?page=register" class="text-gray-400 hover:text-gray-200 transition-colors">Daftar</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-gray-200 transition-colors">Tentang Kami</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-gray-200 transition-colors">Bantuan</a></li>
                    </ul>
                </div>
                
                <!-- Contact Info -->
                <div>
                    <h3 class="text-lg font-semibold mb-4 text-gray-200">Kontak</h3>
                    <div class="space-y-2 text-gray-400">
                        <p>📧 hr.kolab@gmail.com</p>
                        <p>📞 +62 878 9000 4465</p>
                        <p>📍 Bandung, Indonesia</p>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-700 mt-8 pt-8 text-center">
                <p class="text-gray-500">&copy; 2024 Sistem Presensi Berbasis Wajah. All rights reserved.</p>
            </div>
        </div>
    </footer>
<?php elseif ($page === 'login'): ?>
    <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-sky-50 to-indigo-50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Masuk</h1>
            <p class="text-gray-500 mb-6">Silakan login untuk melanjutkan</p>
            <form id="form-login" class="space-y-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Email</label>
                    <input name="email" type="email" class="w-full p-3 border rounded-lg focus:ring focus:border-indigo-400" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Password</label>
                    <input name="password" type="password" class="w-full p-3 border rounded-lg focus:ring focus:border-indigo-400" required>
                </div>
                <button class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-3 rounded-lg transition">Login</button>
            </form>
            <p class="text-center text-sm text-gray-600 mt-4">Belum punya akun? <a class="text-indigo-600 hover:underline" href="?page=register">Daftar</a></p>
            <div id="login-msg" class="text-center text-sm mt-4"></div>
            <a href="?page=landing" class="bg-indigo-600 hover:bg-indigo-700 text-white hover:text-gray-800 rounded-full p-2 mb-4 pr-3"><i class="fi fi-sr-angle-left"></i></a>
        </div>
    </div>
<?php elseif ($page === 'register'): ?>
    <div class="min-h-screen flex items-center justify-center p-4 bg-gradient-to-br from-green-50 to-emerald-50">
        <div class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl p-8">
            <h1 class="text-2xl font-bold text-gray-800 mb-2">Registrasi Pegawai</h1>
            <p class="text-gray-500 mb-6">Isi data lengkap di bawah ini</p>
            <form id="form-register" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Email</label>
                    <input name="email" type="email" class="w-full p-3 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">NIM</label>
                    <input name="nim" type="text" class="w-full p-3 border rounded-lg" required>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600 mb-1">Nama Lengkap</label>
                    <input name="nama" type="text" class="w-full p-3 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Program Studi</label>
                    <input name="prodi" type="text" class="w-full p-3 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Nama Startup</label>
                    <input name="startup" type="text" class="w-full p-3 border rounded-lg">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm text-gray-600 mb-2">Foto Wajah</label>
                    <div id="reg-video-container" class="relative bg-gray-200 rounded-lg w-full aspect-video mb-2 hidden">
                        <video id="reg-video" autoplay playsinline class="w-full h-full object-cover rounded-lg"></video>
                    </div>
                    <canvas id="reg-canvas" class="hidden"></canvas>
                    <img id="reg-foto-preview" class="mt-2 mb-2 h-32 w-32 object-cover rounded-lg hidden mx-auto">
                    <input type="hidden" name="foto" id="reg-foto-data">
                    <input type="file" id="reg-photo-file-input" accept="image/*" class="hidden">
                    <div class="flex gap-2 mb-2">
                        <button type="button" id="reg-start-camera" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2 rounded-lg">Buka Kamera</button>
                        <button type="button" id="reg-upload-photo" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-semibold py-2 rounded-lg">Upload Foto</button>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" id="reg-take-photo" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 rounded-lg hidden">Ambil Foto</button>
                        <button type="button" id="reg-remove-photo" class="flex-1 bg-red-500 hover:bg-red-600 text-white font-semibold py-2 rounded-lg hidden">Hapus Foto</button>
                    </div>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Password</label>
                    <input name="password" type="password" class="w-full p-3 border rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Konfirmasi Password</label>
                    <input name="password2" type="password" class="w-full p-3 border rounded-lg" required>
                </div>
                <div class="md:col-span-2 mt-2">
                    <button class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg">Daftar</button>
                </div>
            </form>
            <p class="text-center text-sm text-gray-600 mt-4">Sudah punya akun? <a class="text-emerald-600 hover:underline" href="?page=login">Login</a></p>
            <div id="register-msg" class="text-center text-sm mt-4"></div>
            <a href="?page=landing" class="bg-gray-500 hover:bg-gray-600 text-white hover:text-gray-800 rounded-full p-2 mb-4 pr-3 "><i class="fi fi-sr-angle-left"></i></a>
        </div>
    </div>
<?php else: ?>
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-700">Sistem Presensi Berbasis Wajah</h1>
            <div class="relative">
                <button id="btn-profile" class="flex items-center gap-3 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg overflow-hidden">
                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($_SESSION['user']['nama'] ?? 'Akun'); ?></span>
                    <img src="generate-avatar.php?background=6366f1&color=fff&name=<?php echo urlencode($_SESSION['user']['nama'] ?? 'A'); ?>&size=32" class="avatar" alt="profile">
                </button>
                <div id="dropdown-profile" class="absolute right-0 mt-2 bg-white rounded-lg shadow-lg border hidden min-w-max">
                    <?php if(isset($_SESSION['user'])): ?>
                        <div class="px-4 py-2 text-sm text-gray-600 border-b whitespace-nowrap"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></div>
                        <a href="?page=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 whitespace-nowrap">Logout</a>
                    <?php else: ?>
                        <a href="?page=login" class="block px-4 py-2 text-sm text-indigo-600 hover:bg-indigo-50 whitespace-nowrap">Login</a>
                        <a href="?page=register" class="block px-4 py-2 text-sm text-emerald-600 hover:bg-emerald-50 whitespace-nowrap">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <nav class="bg-indigo-600 text-white">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-center space-x-4">
                <?php if (!isAdmin()): ?>
                    <button data-tab="rekap" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Rekap Hadir</button>
                    <button data-tab="laporan-bulanan" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Laporan Bulanan</button>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <button data-tab="dashboard" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Dashboard</button>
                    <button data-tab="members" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Kelola Member</button>
                    <button data-tab="laporan" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Data Presensi</button>
                    <button data-tab="admin-monthly" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Laporan Bulanan</button>
                    <button data-tab="settings" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Settings</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main class="max-w-8xl mx-auto px-4 py-4">
        <!-- Pegawai: Rekap Hadir -->
        <div id="page-rekap" class="<?php echo isAdmin() ? 'hidden' : '';?>">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-4">Rekap Daftar Hadir</h2>
                <div id="pegawai-info" class="text-sm text-gray-700 mb-4"></div>
                
                <!-- KPI Chart Section -->
                <div id="kpi-chart-section" class="mb-6">
                    <h3 class="text-lg font-semibold mb-3">Penilaian KPI Absen</h3>
                    <!-- <div class="bg-gray-50 p-4 rounded-lg">
                        <canvas id="kpi-chart" style="max-height: 400px;"></canvas>
                    </div> -->
                    <div id="kpi-summary" class="mt-4 grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <!-- KPI Summary will be populated here -->
                    </div>
                </div>
                
                <div id="rekap-controls" class="flex flex-wrap items-center gap-2 mb-4">
                    <select id="rekap-month" class="p-2 border rounded-lg"></select>
                    <select id="rekap-year" class="p-2 border rounded-lg"></select>
                    <select id="rekap-week" class="p-2 border rounded-lg hidden"></select>
                    <button id="btn-load-rekap" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold px-4 py-2 rounded-lg">Tampilkan</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4">Hari</th>
                                <th class="py-2 px-4">Tanggal</th>
                                <th class="py-2 px-4">Jam Masuk</th>
                                <th class="py-2 px-4">Jam Keluar</th>
                                <th class="py-2 px-4">Keterangan</th>
                                <th class="py-2 px-4">Laporan Harian</th>
                                <th class="py-2 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="table-rekap-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Pegawai: Laporan Bulanan -->
        <div id="page-laporan-bulanan" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-4">Laporan Bulanan</h2>
                <div id="pegawai-info-monthly" class="text-sm text-gray-700 mb-4"></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4">Bulan</th>
                                <th class="py-2 px-4">Laporan</th>
                                <th class="py-2 px-4">Status</th>
                            </tr>
                        </thead>
                        <tbody id="table-monthly-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="page-monthly-form" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg mt-4">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold" id="monthly-form-title">Buat Laporan Bulanan</h2>
                    <button id="btn-back-to-monthly-list" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Kembali</button>
                </div>
                
                <div id="pegawai-info-monthly-form" class="text-sm text-gray-700 mb-4 p-4 bg-indigo-50 rounded-lg"></div>

                <form id="form-monthly-report" class="space-y-6">
                    <input type="hidden" id="monthly-report-year">
                    <input type="hidden" id="monthly-report-month">

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">1. Ringkasan Pekerjaan</label>
                        <textarea id="monthly-summary" rows="5" class="w-full p-2 border rounded-lg" placeholder="Jelaskan ringkasan pekerjaan Anda selama sebulan..."></textarea>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">2. Pencapaian dan Hasil Kerja</label>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white bordered">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="py-2 px-4 w-2/5">Pencapaian</th>
                                        <th class="py-2 px-4 w-2/5">Detail</th>
                                        <th class="py-2 px-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="table-achievements-body">
                                    </tbody>
                            </table>
                        </div>
                        <button type="button" id="btn-add-achievement" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-lg text-sm">Tambah Pencapaian</button>
                    </div>

                    <div>
                        <label class="block text-gray-700 font-semibold mb-2">3. Kendala</label>
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white bordered">
                                <thead class="bg-gray-200">
                                    <tr>
                                        <th class="py-2 px-4 w-1/3">Kendala</th>
                                        <th class="py-2 px-4 w-1/3">Solusi</th>
                                        <th class="py-2 px-4 w-1/3">Catatan</th>
                                        <th class="py-2 px-4">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="table-obstacles-body">
                                    </tbody>
                            </table>
                        </div>
                        <button type="button" id="btn-add-obstacle" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white font-bold py-1 px-3 rounded-lg text-sm">Tambah Kendala</button>
                    </div>

                    <div class="flex justify-end space-x-4 mt-6">
                        <button type="button" id="btn-save-draft" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Simpan sebagai Draft</button>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white font-bold py-2 px-4 rounded-lg">Submit Laporan</button>
                    </div>
                </form>
            </div>
        </div>

        <?php if (isAdmin()): ?>
        <div id="page-members" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">Daftar Member</h2>
                    <button id="btn-add-member" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg transition">Tambah Member</button>
                </div>
                <input type="text" id="search-member" placeholder="Cari member berdasarkan nama atau NIM..." class="w-full p-2 border rounded-lg mb-4">
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4">Foto</th>
                                <th class="py-2 px-4">NIM</th>
                                <th class="py-2 px-4">Nama</th>
                                <th class="py-2 px-4">Program Studi</th>
                                <th class="py-2 px-4">Nama Startup</th>
                                <th class="py-2 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="table-members-body"></tbody>
                    </table>
                </div>
            </div>
        </div>

            <div id="page-laporan" class="hidden">
                <div class="bg-white p-6 rounded-lg shadow-lg">
                    <h2 class="text-xl font-bold mb-4">Laporan Kehadiran</h2>
                    <div class="grid md:grid-cols-5 gap-4 mb-4">
                        <label class="text-sm text-gray-600 flex flex-col">
                            <span class="mb-1">Cari (Nama/NIM)</span>
                            <input type="text" id="search-laporan" placeholder="Cari..." class="p-2 border rounded-lg">
                        </label>
                        <label class="text-sm text-gray-600 flex flex-col">
                            <span class="mb-1">Startup</span>
                            <select id="filter-startup" class="p-2 border rounded-lg">
                                <option value="">Semua Startup</option>
                            </select>
                        </label>
                        <label class="text-sm text-gray-600 flex flex-col">
                            <span class="mb-1">Tanggal Mulai</span>
                            <input type="date" id="filter-tanggal-mulai" class="p-2 border rounded-lg">
                        </label>
                        <label class="text-sm text-gray-600 flex flex-col">
                            <span class="mb-1">Tanggal Selesai</span>
                            <input type="date" id="filter-tanggal-selesai" class="p-2 border rounded-lg">
                        </label>
                        <div class="flex items-end"><button id="btn-show-all" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition">Reset</button></div>
                    </div>
                    <div class="grid md:grid-cols-4 gap-4 mb-4">
                        <label class="text-sm text-gray-600 flex flex-col">
                            <span class="mb-1">Sorting</span>
                            <select id="sort-presensi" class="p-2 border rounded-lg">
                                <option value="tanggal-desc">Tanggal (Terbaru)</option>
                                <option value="tanggal-asc">Tanggal (Terlama)</option>
                                <option value="jam-masuk-desc">Jam Masuk (Terlambat)</option>
                                <option value="jam-masuk-asc">Jam Masuk (Tepat Waktu)</option>
                                <option value="nama-asc">Nama (A-Z)</option>
                                <option value="nama-desc">Nama (Z-A)</option>
                            </select>
                        </label>
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <div class="mb-4 flex gap-2 flex-wrap">
                        <button id="btn-open-absence" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-lg">Input Keterangan Manual</button>
                        <button id="btn-manual-holidays" class="bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-2 px-4 rounded-lg">Kelola Hari Libur Manual</button>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white bordered">
                            <thead class="bg-gray-200">
                                <tr>
                                    <th class="py-2 px-4">Tanggal</th>
                                    <th class="py-2 px-4">NIM</th>
                                    <th class="py-2 px-4">Nama</th>
                                    <th class="py-2 px-4">Startup</th>
                                    <th class="py-2 px-4">Jam Masuk</th>
                                    <th class="py-2 px-4">Bukti Masuk</th>
                                    <th class="py-2 px-4">Status</th>
                                    <th class="py-2 px-4">Ket</th>
                                    <th class="py-2 px-4">Jam Pulang</th>
                                    <th class="py-2 px-4">Bukti Pulang</th>
                                    <th class="py-2 px-4">Status Laporan</th>
                                    <th class="py-2 px-4">Aksi</th>
                                </tr>
                            </thead>
                            <tbody id="table-laporan-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php if (isAdmin()): ?>
        <!-- Admin Monthly Reports -->
        <div id="page-admin-monthly" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-4">Laporan Bulanan (Admin)</h2>
                <div class="grid md:grid-cols-6 gap-3 mb-4">
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Cari (Nama/NIM)</span>
                        <input type="text" id="am-search" class="p-2 border rounded-lg" placeholder="Cari...">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Startup</span>
                        <select id="am-startup" class="p-2 border rounded-lg"><option value="">Semua Startup</option></select>
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Bulan</span>
                        <select id="am-month" class="p-2 border rounded-lg"><option value="">Semua Bulan</option></select>
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Tahun</span>
                        <select id="am-year" class="p-2 border rounded-lg"><option value="">Semua Tahun</option></select>
                    </label>
                    <div class="flex items-end"><button id="am-reset" class="bg-green-500 hover:bg-green-600 text-white px-3 rounded-lg py-2">Reset</button></div>
                    <div></div>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white bordered">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4">Bulan</th>
                                <th class="py-2 px-4">Nama</th>
                                <th class="py-2 px-4">Startup</th>
                                <th class="py-2 px-4">Detail</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="am-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Admin Settings -->
        <?php if (isAdmin()): ?>
        <div id="page-settings" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-6">Pengaturan Sistem</h2>
                
                <form id="settings-form" class="space-y-6">
                    <div class="grid md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Pengaturan Jam Presensi</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Jam Maksimal On Time
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" id="max-ontime-hour" min="0" max="23" 
                                               class="w-20 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <span class="text-sm text-gray-600">:00 (24 jam format)</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Pegawai yang masuk setelah jam ini akan dianggap terlambat
                                    </p>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        Jam Minimal Check Out
                                    </label>
                                    <div class="flex items-center space-x-2">
                                        <input type="number" id="min-checkout-hour" min="0" max="23" 
                                               class="w-20 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <span class="text-sm text-gray-600">:00 (24 jam format)</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">
                                        Pegawai baru bisa presensi pulang setelah jam ini
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Pengaturan Wilayah WFO & Periode</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Mode Deteksi WFO</label>
                                    <select id="wfo-mode" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="api">API (Deteksi via IP/ASN/Organisasi)</option>
                                        <option value="coordinate">Koordinat (Geofencing)</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">API: Deteksi berdasarkan IP publik. Koordinat: Deteksi berdasarkan GPS.</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Alamat Pusat WFO</label>
                                    <div class="relative">
                                        <input type="text" id="wfo-address" placeholder="Ketik alamat untuk mencari..." 
                                               class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                               autocomplete="off">
                                        <div id="address-suggestions" class="absolute z-10 w-full bg-white border border-gray-300 rounded-lg shadow-lg hidden max-h-60 overflow-y-auto">
                                            <!-- Suggestions will be populated here -->
                                        </div>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Mulai ketik untuk mencari alamat. Pilih dari saran yang muncul.</p>
                                    <div id="selected-address-info" class="mt-2 p-2 bg-green-50 border border-green-200 rounded-lg hidden">
                                        <div class="text-sm text-green-700">
                                            <strong>Alamat terpilih:</strong> <span id="selected-address-text"></span>
                                        </div>
                                        <div class="text-xs text-green-600 mt-1">
                                            Koordinat: <span id="selected-coordinates"></span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Radius WFO (meter)</label>
                                    <input type="number" min="0" id="wfo-radius" class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Periode Selesai</label>
                                        <input type="date" id="attendance-period-end" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Periode mulai otomatis berdasarkan tanggal registrasi pegawai pertama</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Pengaturan WFO API</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Provider IP API</label>
                                    <select id="wfo-api-provider" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="ipinfo">IPInfo.io</option>
                                        <option value="ipapi">IP-API.co</option>
                                        <option value="ip-api">IP-API.com</option>
                                    </select>
                                    <p class="text-xs text-gray-500 mt-1">Pilih provider untuk mendapatkan informasi IP publik</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Token API (Opsional)</label>
                                    <input type="text" id="wfo-api-token" placeholder="Masukkan token API jika diperlukan" 
                                           class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Beberapa provider memerlukan token untuk akses yang lebih baik</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Kata Kunci Organisasi WFO</label>
                                    <textarea id="wfo-api-org-keywords" rows="3" placeholder="Telkom University, Yayasan Pendidikan Telkom" 
                                              class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Pisahkan dengan koma. IP yang memiliki organisasi ini akan dianggap WFO</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Daftar ASN WFO</label>
                                    <textarea id="wfo-api-asn-list" rows="2" placeholder="AS7713, AS12345" 
                                              class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Pisahkan dengan koma. Contoh: AS7713 (ASN Telkom University)</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Daftar CIDR WFO</label>
                                    <textarea id="wfo-api-cidr-list" rows="2" placeholder="103.23.44.0/22, 192.168.1.0/24" 
                                              class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Pisahkan dengan koma. Contoh: 103.23.44.0/22 (rentang IP Telkom University)</p>
                                </div>
                                <div class="bg-blue-50 p-3 rounded-lg">
                                    <button type="button" id="auto-detect-wfo" class="w-full bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors">
                                        Auto-Detect WFO dari IP Admin Saat Ini
                                    </button>
                                    <p class="text-xs text-blue-600 mt-2">Klik untuk mendeteksi organisasi/ASN dari IP admin saat ini</p>
                                    <div id="auto-detect-result" class="mt-2 p-2 bg-white border border-blue-200 rounded hidden">
                                        <div class="text-sm text-gray-700">
                                            <strong>Hasil Deteksi:</strong>
                                            <div id="detect-org" class="mt-1"></div>
                                            <div id="detect-asn" class="mt-1"></div>
                                            <div id="detect-ip" class="mt-1"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800">Pengaturan KPI Absen</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Pengurangan KPI per Menit Terlambat (%)</label>
                                    <input type="number" min="0" max="100" step="0.1" id="kpi-late-penalty" 
                                           class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: 1% per menit terlambat</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nilai KPI untuk Izin/Sakit (%)</label>
                                    <input type="number" min="0" max="100" step="0.1" id="kpi-izin-sakit" 
                                           class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: 85% per izin/sakit</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Nilai KPI untuk Alpha (%)</label>
                                    <input type="number" min="0" max="100" step="0.1" id="kpi-alpha" 
                                           class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: 0% per alpha</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Bonus KPI untuk Overtime (%)</label>
                                    <input type="number" min="0" max="100" step="0.1" id="kpi-overtime-bonus" 
                                           class="w-32 p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                    <p class="text-xs text-gray-500 mt-1">Default: 5% per overtime</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <h3 class="text-lg font-semibold mb-4 text-blue-800">Informasi</h3>
                            <div class="space-y-3 text-sm text-blue-700">
                                <div class="flex items-start space-x-2">
                                    <span class="text-blue-500 mt-1">•</span>
                                    <span>Pengaturan ini akan mempengaruhi semua presensi yang akan datang</span>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <span class="text-blue-500 mt-1">•</span>
                                    <span>Data presensi yang sudah ada tidak akan berubah</span>
                                </div>
                                <div class="flex items-start space-x-2">
                                    <span class="text-blue-500 mt-1">•</span>
                                    <span>Format jam menggunakan 24 jam (0-23)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-end space-x-4 pt-4 border-t">
                        <button type="button" id="reset-settings" 
                                class="px-4 py-2 text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Reset ke Default
                        </button>
                        <button type="submit" 
                                class="px-6 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg transition">
                            Simpan Pengaturan
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Admin Dashboard -->
        <?php if (isAdmin()): ?>
        <div id="page-dashboard" class="hidden">
            <div class="bg-white p-6 rounded-lg shadow-lg">
                <h2 class="text-xl font-bold mb-6">Dashboard Presensi</h2>

                <!-- Summary Cards -->
                <div class="grid md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-blue-600" id="totalEmployees">0</div>
                        <div class="text-sm text-blue-700">Total Pegawai</div>
                    </div>
                    <div class="bg-green-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-green-600" id="presentToday">0</div>
                        <div class="text-sm text-green-700">Hadir Hari Ini</div>
                    </div>
                    <div class="bg-red-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-red-600" id="lateToday">0</div>
                        <div class="text-sm text-red-700">Terlambat Hari Ini</div>
                    </div>
                    <div class="bg-yellow-50 p-4 rounded-lg text-center">
                        <div class="text-2xl font-bold text-yellow-600" id="absentToday">0</div>
                        <div class="text-sm text-yellow-700">Tidak Hadir</div>
                    </div>
                </div>
                
                <!-- Today's Late Employees -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Pegawai Terlambat Hari Ini</h3>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <canvas id="todayLateChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- KPI Absen Section -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Penilaian KPI Absen</h3>
                    <div class="bg-white p-4 rounded-lg shadow-sm">
                        <div class="mb-4">
                            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                                <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                                    <span class="text-sm text-gray-600">Periode: <span id="kpi-period-range"></span></span>
                                    <div class="flex items-center gap-2">
                                        <label class="text-sm text-gray-600">Filter:</label>
                                        <select id="kpi-filter-type" class="px-3 py-1 border border-gray-300 rounded text-sm">
                                            <option value="period">Periode Lengkap</option>
                                            <option value="monthly">Per Bulan</option>
                                        </select>
                                        <select id="kpi-filter-month" class="px-3 py-1 border border-gray-300 rounded text-sm hidden">
                                            <option value="">Pilih Bulan</option>
                                        </select>
                                        <select id="kpi-filter-year" class="px-3 py-1 border border-gray-300 rounded text-sm hidden">
                                            <option value="">Pilih Tahun</option>
                                        </select>
                                    </div>
                                </div>
                                <button id="refresh-kpi" class="px-3 py-1 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition">
                                    Refresh
                                </button>
                            </div>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-medium text-gray-700">No</th>
                                        <th class="px-4 py-3 text-left font-medium text-gray-700">Nama Pegawai</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Total Hari Kerja</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Ontime</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Terlambat</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Izin/Sakit</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Alpha</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Overtime</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">KPI Score</th>
                                        <th class="px-4 py-3 text-center font-medium text-gray-700">Status</th>
                                    </tr>
                                </thead>
                                <tbody id="kpi-table-body" class="divide-y divide-gray-200">
                                    <!-- Data will be populated here -->
                                </tbody>
                            </table>
                        </div>
                        <div id="kpi-loading" class="text-center py-8 text-gray-500">
                            <div class="inline-block animate-spin rounded-full h-6 w-6 border-b-2 border-indigo-600"></div>
                            <p class="mt-2">Memuat data KPI...</p>
                        </div>
                        <div id="kpi-empty" class="text-center py-8 text-gray-500 hidden">
                            <p>Tidak ada data KPI untuk ditampilkan</p>
                        </div>
                    </div>
                </div>

                <!-- Attendance Trend Chart -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Tren Kejadian Kehadiran 1 Periode</h3>
                    <div class="bg-white p-4 rounded-lg shadow-sm">
                        <canvas id="attendanceTrendChart" width="400" height="200"></canvas>
                    </div>
                </div>                

                <!-- Monthly Attendance Performance -->
                <div class="mb-8">
                    <h3 class="text-lg font-semibold mb-4 text-gray-700">Performa Kehadiran Bulan Ini</h3>
                    <div class="grid md:grid-cols-2 gap-6">
                        <!-- Most Frequently Late -->
                        <div class="bg-red-50 p-4 rounded-lg">
                            <h4 class="text-md font-semibold mb-3 text-red-700">Paling Sering Terlambat</h4>
                            <canvas id="mostLateChart" width="300" height="200"></canvas>
                        </div>
                        
                        <!-- Most Attentive -->
                        <div class="bg-green-50 p-4 rounded-lg">
                            <h4 class="text-md font-semibold mb-3 text-green-700">Paling Rajin</h4>
                            <canvas id="mostAttentiveChart" width="300" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Modal Tambah/Edit Member -->
    <div id="member-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-40 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-md">
            <h2 id="modal-title" class="text-2xl font-bold mb-6">Tambah Member Baru</h2>
            <form id="member-form">
                <input type="hidden" id="member-id">
                <input type="hidden" id="foto-data-url">
                <div class="mb-4">
                    <label class="block text-gray-700">Email</label>
                    <input type="email" id="email" class="w-full p-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">NIM</label>
                    <input type="text" id="nim" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Nama Lengkap</label>
                    <input type="text" id="nama" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Program Studi</label>
                    <input type="text" id="prodi" class="w-full p-2 border rounded-lg" required>
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700">Nama Startup</label>
                    <input type="text" id="startup" class="w-full p-2 border rounded-lg">
                </div>
                <div class="mb-4">
                    <label class="block text-gray-700 mb-2">Foto Wajah</label>
                    <div id="modal-video-container" class="relative bg-gray-200 rounded-lg w-full aspect-video mb-2 hidden">
                        <video id="modal-video" autoplay playsinline class="w-full h-full object-cover rounded-lg"></video>
                    </div>
                    <canvas id="modal-canvas" class="hidden"></canvas>
                    <img id="foto-preview" class="mt-2 h-32 w-32 object-cover rounded-lg hidden mx-auto mb-2">
                    <div class="grid grid-cols-2 gap-2 mb-2">
                        <button type="button" id="btn-start-camera" class="bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg transition">Buka Kamera</button>
                        <button type="button" id="btn-upload-photo" class="bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded-lg transition">Upload Foto</button>
                    </div>
                    <input type="file" id="photo-file-input" accept="image/*" class="hidden">
                    <button type="button" id="btn-take-photo" class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg hidden transition">Ambil Foto</button>
                </div>
                <div id="password-admin-wrapper" class="grid grid-cols-2 gap-2 hidden">
                    <input type="password" id="password-new" placeholder="Password" class="p-2 border rounded-lg">
                    <input type="password" id="password-confirm" placeholder="Konfirmasi" class="p-2 border rounded-lg">
                </div>
                <div class="flex justify-end space-x-4 mt-6">
                    <button type="button" id="btn-cancel-modal" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-4 rounded-lg">Batal</button>
                    <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded-lg">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Edit Kehadiran -->
    <div id="edit-att-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-sm">
            <h3 class="text-xl font-bold mb-4">Edit Data Kehadiran</h3>
            <form id="edit-att-form">
                <input type="hidden" id="edit-att-id">
                <input type="hidden" id="edit-att-user-id">
                <input type="hidden" id="edit-att-screenshot-masuk-data">
                <input type="hidden" id="edit-att-screenshot-pulang-data">
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Tanggal</label>
                    <input type="date" id="edit-att-date" class="w-full p-2 border rounded-lg" disabled>
                </div>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Nama</label>
                    <input type="text" id="edit-att-nama" class="w-full p-2 border rounded-lg" disabled>
                </div>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Jam Masuk</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-masuk" class="flex-1 p-2 border rounded-lg">
                        <button type="button" id="edit-att-upload-masuk" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm">Upload Bukti</button>
                    </div>
                    <div id="edit-att-screenshot-masuk-preview" class="mt-2 hidden">
                        <img id="edit-att-screenshot-masuk-img" src="" alt="Screenshot Masuk" class="w-full h-32 object-cover rounded border">
                        <button type="button" id="edit-att-remove-masuk" class="mt-1 text-red-600 text-sm hover:underline">Hapus</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Jam Pulang</label>
                    <div class="flex gap-2">
                        <input type="time" id="edit-att-jam-pulang" class="flex-1 p-2 border rounded-lg">
                        <button type="button" id="edit-att-upload-pulang" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded text-sm">Upload Bukti</button>
                    </div>
                    <div id="edit-att-screenshot-pulang-preview" class="mt-2 hidden">
                        <img id="edit-att-screenshot-pulang-img" src="" alt="Screenshot Pulang" class="w-full h-32 object-cover rounded border">
                        <button type="button" id="edit-att-remove-pulang" class="mt-1 text-red-600 text-sm hover:underline">Hapus</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Keterangan</label>
                    <select id="edit-att-ket" class="w-full p-2 border rounded-lg">
                        <option value="wfo">WFO</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="alpha">Alpha</option>
                        <option value="wfa">WFA</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Status</label>
                    <select id="edit-att-status" class="w-full p-2 border rounded-lg">
                        <option value="ontime">On Time</option>
                        <option value="terlambat">Terlambat</option>
                    </select>
                </div>
                <div class="mb-3">
                    <button type="button" id="edit-att-add-report" class="w-full bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded">Tambahkan Laporan</button>
                </div>
                <div class="flex justify-end gap-2 mt-4">
                    <button type="button" id="edit-att-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button type="submit" id="edit-att-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Laporan Harian Admin -->
    <div id="admin-daily-report-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
            <h3 class="text-xl font-bold mb-4">Laporan Harian Pegawai</h3>
            <div class="mb-4">
                <p class="text-sm text-gray-600">Nama: <span id="admin-dr-nama" class="font-semibold"></span></p>
                <p class="text-sm text-gray-600">Tanggal: <span id="admin-dr-date" class="font-semibold"></span></p>
            </div>
            
            <!-- Bukti Izin/Sakit Section -->
            <div id="admin-dr-bukti-section" class="mb-4 hidden">
                <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
                <div id="admin-dr-bukti-container" class="mb-2">
                    <!-- Bukti image will be inserted here -->
                </div>
                <div class="flex gap-2">
                    <button type="button" id="admin-dr-edit-bukti" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Edit Bukti</button>
                    <button type="button" id="admin-dr-delete-bukti" class="bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded text-sm">Hapus Bukti</button>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm text-gray-600 mb-2">Isi Laporan Harian:</label>
                <textarea id="admin-dr-content" rows="8" class="w-full p-3 border rounded-lg" placeholder="Tulis detail pekerjaan pegawai hari ini..."></textarea>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" id="admin-dr-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button type="button" id="admin-dr-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Confirm Modal -->
    <div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-70 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-sm text-center">
            <p id="confirm-modal-message" class="text-lg mb-6">Apakah Anda yakin?</p>
            <div class="flex justify-center space-x-4">
                <button id="btn-confirm-no" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg">Tidak</button>
                <button id="btn-confirm-yes" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg">Ya</button>
            </div>
        </div>
    </div>

    <!-- WFA Reason Modal -->
    <div id="wfa-reason-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <h3 class="text-xl font-bold mb-3">Alasan Kerja di Luar Kantor</h3>
            <p class="text-sm text-gray-600 mb-3">Anda berada di luar wilayah Telkom University. Silakan isi alasan bekerja di luar kantor untuk melanjutkan presensi (WFA).</p>
            <textarea id="wfa-reason-input" class="w-full p-3 border rounded mb-4" rows="4" placeholder="Tulis alasan Anda..."></textarea>
            <div class="flex justify-end gap-2">
                <button id="wfa-reason-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="wfa-reason-submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Kirim</button>
            </div>
        </div>
    </div>

    <!-- Izin/Sakit Input Modal -->
    <div id="izin-sakit-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-md">
            <h3 class="text-xl font-bold mb-4">Input Keterangan</h3>
            <form id="izin-sakit-form">
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Jenis Keterangan</label>
                    <select id="izin-sakit-type" class="w-full p-2 border rounded-lg" required>
                        <option value="">Pilih jenis...</option>
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Keterangan</label>
                    <textarea id="izin-sakit-alasan" class="w-full p-3 border rounded" rows="4" placeholder="Tulis keterangan izin/sakit..." required></textarea>
                </div>
                <div class="mb-4">
                    <label class="block text-sm text-gray-600 mb-2">Upload Bukti</label>
                    <input type="file" id="izin-sakit-bukti" accept="image/*" class="w-full p-2 border rounded" required>
                    <p class="text-xs text-gray-500 mt-1">Maksimal 5MB. Format: JPG, PNG, GIF</p>
                    <div id="izin-sakit-preview" class="mt-2 hidden">
                        <img id="izin-sakit-preview-img" src="" alt="Preview" class="w-full h-32 object-cover rounded border">
                    </div>
                    <div id="izin-sakit-error" class="mt-2 text-red-600 text-sm hidden"></div>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" id="izin-sakit-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Ket Detail Modal -->
    <div id="ket-detail-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
            <div class="flex justify-between items-center mb-4">
                <h3 id="ket-detail-title" class="text-xl font-bold"></h3>
                <button onclick="qs('#ket-detail-modal').classList.add('hidden'); qs('#ket-detail-modal').classList.remove('flex')" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            <div id="ket-detail-content"></div>
        </div>
    </div>

    <!-- Modal Absence -->
    <div id="absence-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-lg">
            <h3 class="text-xl font-bold mb-4">Input Keterangan Manual</h3>
            <div class="grid gap-3">
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Cari Pegawai</span>
                    <input type="text" id="abs-search" class="p-2 border rounded-lg" placeholder="Cari nama/NIM...">
                </label>
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Pilih Pegawai</span>
                    <select id="abs-user" class="p-2 border rounded-lg"></select>
                </label>
                <label class="text-sm text-gray-600 flex flex-col">
                    <span class="mb-1">Tanggal</span>
                    <input type="date" id="abs-date" class="p-2 border rounded-lg" value="<?php echo date('Y-m-d'); ?>">
                </label>
                <div class="mb-3">
                    <label class="block text-sm text-gray-600 mb-1">Keterangan</label>
                    <select id="abs-type" class="w-full p-2 border rounded-lg">
                        <option value="izin">Izin</option>
                        <option value="sakit">Sakit</option>
                        <option value="wfa">WFA</option>
                        <option value="overtime">Overtime</option>
                    </select>
                </div>
                <div id="abs-wfh-form" class="grid grid-cols-2 gap-2 hidden">
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Masuk</span>
                        <input type="time" id="abs-jam-masuk" class="p-2 border rounded-lg">
                    </label>
                    <label class="text-sm text-gray-600 flex flex-col">
                        <span class="mb-1">Jam Pulang</span>
                        <input type="time" id="abs-jam-pulang" class="p-2 border rounded-lg">
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button id="abs-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button id="abs-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Manual Holidays Modal -->
    <div id="manual-holidays-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-xl">
            <h3 class="text-xl font-bold mb-4">Kelola Hari Libur Manual</h3>
            <div class="flex gap-2 mb-3">
                <input type="date" id="mh-date" class="p-2 border rounded">
                <input type="text" id="mh-name" class="flex-1 p-2 border rounded" placeholder="Nama/Alasan libur (mis. Demo, Bencana)">
                <button id="mh-add" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Tambah</button>
            </div>
            <div class="overflow-x-auto max-h-80 overflow-y-auto">
                <table class="min-w-full bg-white bordered">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-3 text-left">Tanggal</th>
                            <th class="py-2 px-3 text-left">Keterangan</th>
                            <th class="py-2 px-3 text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="mh-body"></tbody>
                </table>
            </div>
            <div class="text-right mt-3">
                <button id="mh-close" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Edit Bukti Izin/Sakit -->
    <div id="edit-bukti-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-70 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-lg">
            <h3 class="text-xl font-bold mb-4">Edit Bukti Izin/Sakit</h3>
            <div class="grid gap-3">
                <div>
                    <label class="block text-sm text-gray-600 mb-1">Upload Bukti Baru</label>
                    <input type="file" id="edit-bukti-file" accept="image/*" class="w-full p-3 border rounded-lg">
                    <p class="text-xs text-gray-500 mt-1">Maksimal 5MB. Format: JPG, PNG, GIF</p>
                </div>
                <div class="mt-2">
                    <video id="edit-bukti-video" autoplay playsinline class="w-full h-48 object-cover rounded-lg hidden"></video>
                    <canvas id="edit-bukti-canvas" class="hidden"></canvas>
                    <img id="edit-bukti-preview" class="mt-2 h-32 w-32 object-cover rounded-lg hidden">
                    <button type="button" id="edit-bukti-capture" class="mt-2 bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm hidden">Ambil Foto</button>
                </div>
                <div id="edit-bukti-current" class="hidden">
                    <label class="block text-sm text-gray-600 mb-1">Bukti Saat Ini:</label>
                    <img id="edit-bukti-current-img" class="w-full max-w-md h-48 object-cover rounded border">
                </div>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="edit-bukti-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                <button type="button" id="edit-bukti-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
            </div>
        </div>
    </div>

    <!-- Modal Screenshot -->
    <div id="screenshot-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 id="screenshot-modal-title" class="text-xl font-bold"></h3>
                <button onclick="closeScreenshotModal()" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            <div class="text-center">
                <img id="screenshot-modal-image" src="" alt="Screenshot" class="max-w-full max-h-[70vh] object-contain mx-auto rounded-lg shadow-lg">
            </div>
        </div>
    </div>

    <!-- Modal Daily Report Review -->
    <div id="dr-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl relative">
            <button id="dr-close" class="absolute top-3 right-3 text-gray-500">✕</button>
            <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
            <div id="dr-content" class="whitespace-pre-wrap border p-3 rounded mb-3 text-sm"></div>
            <textarea id="dr-evaluation" class="w-full border rounded p-2" rows="4" placeholder="Evaluasi admin..."></textarea>
            <div class="flex justify-end gap-2 mt-4">
                <button id="dr-disapprove" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded">Disapprove</button>
                <button id="dr-approve" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Approve</button>
            </div>
        </div>
    </div>

    <!-- Modal Jadwal Kerja -->
    <div id="work-schedule-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-xl font-bold">Kelola Jadwal Kerja</h3>
                <button id="work-schedule-close" class="text-gray-500 hover:text-gray-700 text-2xl">✕</button>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-2">Pilih Pegawai</label>
                <select id="work-schedule-user" class="w-full p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="">Pilih pegawai...</option>
                </select>
            </div>
            
            <div id="work-schedule-form" class="hidden">
                <div class="mb-4">
                    <h4 class="text-lg font-semibold mb-3">Jadwal Kerja Mingguan</h4>
                    <div class="space-y-3">
                        <div class="grid grid-cols-7 gap-2 text-sm font-medium text-gray-700">
                            <div>Hari</div>
                            <div>Bekerja</div>
                            <div>Jam Masuk</div>
                            <div>Jam Pulang</div>
                            <div>Durasi</div>
                            <div>Status</div>
                            <div>Aksi</div>
                        </div>
                        
                        <div id="work-schedule-days" class="space-y-2">
                            <!-- Days will be populated by JavaScript -->
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Tanggal Mulai Bekerja</label>
                    <input id="work-start-date" type="date" class="p-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                    <p class="text-xs text-gray-500 mt-1">Digunakan sebagai tanggal awal perhitungan KPI pegawai.</p>
                </div>

                <div class="flex justify-end gap-2 mt-6">
                    <button id="work-schedule-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
                    <button id="work-schedule-save" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Simpan Jadwal</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Loading Overlay for model -->
<div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-75 flex flex-col items-center justify-center z-60 hidden">
    <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-24 w-24 mb-4"></div>
    <h2 class="text-center text-white text-xl font-semibold">Memuat Sistem Presensi...</h2>
    <p class="w-1/3 text-center text-white text-sm">Memuat model AI dan database wajah. Mohon tunggu sebentar.</p>
    <div class="mt-4 text-white text-xs opacity-75">
        <div id="loading-progress">Memulai...</div>
    </div>
</div>

<div id="notif-bar" class="fixed top-4 left-1/2 transform -translate-x-1/2 bg-indigo-600 text-white px-6 py-3 rounded-lg shadow-lg z-70 hidden"></div>

<!-- Global Notification Modal -->
<div id="global-modal" class="fixed inset-0 bg-black/50 flex items-center justify-center z-[60] hidden">
    <div class="bg-white w-full max-w-md rounded-lg shadow-2xl p-6">
        <div id="global-modal-title" class="text-lg font-semibold mb-2">Notifikasi</div>
        <div id="global-modal-message" class="text-gray-700 mb-4"></div>
        <div class="text-right">
            <button id="global-modal-close" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 py-2 rounded">Tutup</button>
        </div>
    </div>
    </div>

<script>
function showNotif(msg, success=true){
    const bar = qs('#notif-bar');
    bar.textContent = msg;
    bar.className = `fixed top-4 left-1/2 transform -translate-x-1/2 px-6 py-3 rounded-lg shadow-lg z-70 ${success?'bg-emerald-600':'bg-red-600'} text-white`;
    bar.classList.remove('hidden');
    setTimeout(()=> bar.classList.add('hidden'), 1500); // Faster notification dismissal
}
function showModalNotif(message, success=true, title='Notifikasi'){
    const m = qs('#global-modal');
    const t = qs('#global-modal-title');
    const c = qs('#global-modal-message');
    if(!m||!t||!c) return showNotif(message, success);
    t.textContent = title;
    c.textContent = message;
    m.classList.remove('hidden');
}
document.addEventListener('click', (e)=>{
    if(e.target.id==='global-modal-close' || e.target.id==='global-modal'){
        qs('#global-modal').classList.add('hidden');
    }
});
function qs(sel){ return document.querySelector(sel); }
function qsa(sel){ return Array.from(document.querySelectorAll(sel)); }

// Screenshot modal functions
function showScreenshotModal(imageSrc, title) {
    const modal = qs('#screenshot-modal');
    const modalTitle = qs('#screenshot-modal-title');
    const modalImage = qs('#screenshot-modal-image');
    
    if (modal && modalTitle && modalImage) {
        modalTitle.textContent = title;
        modalImage.src = imageSrc;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
}

function closeScreenshotModal() {
    const modal = qs('#screenshot-modal');
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
}

// Close screenshot modal when clicking outside
document.addEventListener('click', (e) => {
    const modal = qs('#screenshot-modal');
    if (modal && !modal.contains(e.target) && !e.target.closest('img[onclick*="showScreenshotModal"]')) {
        closeScreenshotModal();
    }
});
// Add global variables to manage speech synthesis
let currentSpeech = null;
let speechQueue = [];
let isSpeaking = false;
let speechInterval = null;

function speak(text) {
    try {
        // Check if speech synthesis is available
        if (!('speechSynthesis' in window)) {
            console.warn('Speech synthesis not supported');
            return;
        }

        // Add to queue instead of canceling immediately
        if (text && text.trim() && text !== lastSpokenMessage) {
            speechQueue.push(text);
            lastSpokenMessage = text;
        }

        // Start speech processing if not already running
        if (!isSpeaking) {
            processSpeechQueue();
        }
        return;

    } catch (e) {
        console.error('Speech synthesis error:', e);
        isSpeaking = false;
        speechQueue = [];
    }
}

function processSpeechQueue() {
    if (isSpeaking || speechQueue.length === 0) return;
    
    isSpeaking = true;
    const text = speechQueue.shift();
    
    try {
        // Cancel any ongoing speech
        speechSynthesis.cancel();
        
        // Wait for voices to be loaded
        const speakWithVoice = () => {
            const u = new SpeechSynthesisUtterance(text);
            u.lang = 'id-ID';
            u.rate = 0.9; // Faster rate for speed
            u.pitch = 1.0;
            u.volume = 1.0;

            // Try to use a local voice if available
            const voices = speechSynthesis.getVoices();
            const indonesianVoice = voices.find(voice => 
                voice.lang.startsWith('id') || 
                voice.lang.includes('Indonesian') ||
                voice.name.includes('Indonesian')
            );
            
            if (indonesianVoice) {
                u.voice = indonesianVoice;
            } else if (voices.length > 0) {
                // Use any available voice as fallback
                u.voice = voices[0];
            }

            u.onstart = () => {
                console.log('Speech started:', text);
            };

            u.onend = () => {
                console.log('Speech ended:', text);
                isSpeaking = false;
                
                // Process next in queue after a short delay
                setTimeout(() => {
                    if (speechQueue.length > 0) {
                        processSpeechQueue();
                    } else if (isCameraActive && !videoInterval) {
                        startVideoInterval();
                    }
                }, 200); // 200ms interval between speeches
            };

            u.onerror = (e) => {
                console.error('Speech error:', e);
                isSpeaking = false;
                
                // Skip this speech and continue with queue
                setTimeout(() => {
                    if (speechQueue.length > 0) {
                        processSpeechQueue();
                    } else if (isCameraActive && !videoInterval) {
                        startVideoInterval();
                    }
                }, 100);
            };

            speechSynthesis.speak(u);
            currentSpeech = u;
        };

        // If voices are already loaded, speak immediately
        if (speechSynthesis.getVoices().length > 0) {
            speakWithVoice();
        } else {
            // Wait for voices to load
            speechSynthesis.addEventListener('voiceschanged', speakWithVoice, { once: true });
            
            // Fallback if no voices
            if (speechSynthesis.getVoices().length === 0) {
                console.warn('No voices available, speaking with default settings');
                speakWithVoice();
            }
        }

    } catch (e) {
        console.error('Speech processing error:', e);
        isSpeaking = false;
        
        // Continue with queue
        setTimeout(() => {
            if (speechQueue.length > 0) {
                processSpeechQueue();
            }
        }, 100);
    }
}

// Modify the `statusMessage` function to use the improved `speak` function
let notifLockUntil = 0;
function statusMessage(text, cls) {
    if (!presensiStatus) return;
    
    // Show the text notification
    presensiStatus.textContent = text;
    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md ' + cls;
    presensiStatus.classList.remove('hidden');

    // Hindari interupsi suara untuk pesan non-kritis
    const now = Date.now();
    const isCritical = /bg-(green|yellow|red)-100/.test(cls || '');
    if (isCritical || now > notifLockUntil) {
        // Hitung durasi lock berdasarkan panjang teks agar tidak terpotong
        const dur = Math.max(2500, Math.min(7000, text.length * 60));
        notifLockUntil = now + dur;
        speak(text);
    }
}



// ===== IndexedDB caching for face descriptors =====
function simpleHash(str){
    let h = 5381; for (let i=0;i<str.length;i++){ h = ((h<<5)+h) + str.charCodeAt(i); h |= 0; }
    return 'v' + (h >>> 0).toString(16);
}

async function computeMembersVersionKey(membersList){
    try{
        const basis = membersList.map(m=>[m.nim, m.foto||m.photo||m.image||'', m.nama||'']).sort((a,b)=>String(a[0]).localeCompare(String(b[0])));
        return simpleHash(JSON.stringify(basis));
    }catch(e){ return 'v-default'; }
}

function idbOpen(){
    return new Promise((resolve,reject)=>{
        const req = indexedDB.open('presensi-cache', 1);
        req.onupgradeneeded = (e)=>{
            const db = e.target.result;
            if (!db.objectStoreNames.contains('descriptors')) {
                db.createObjectStore('descriptors');
            }
        };
        req.onsuccess = ()=> resolve(req.result);
        req.onerror = ()=> reject(req.error);
    });
}

async function idbGetDescriptors(versionKey){
    try{
        const db = await idbOpen();
        return await new Promise((resolve,reject)=>{
            const tx = db.transaction('descriptors','readonly');
            const store = tx.objectStore('descriptors');
            const getReq = store.get(versionKey);
            getReq.onsuccess = ()=> resolve(getReq.result||null);
            getReq.onerror = ()=> resolve(null);
        });
    }catch(e){ return null; }
}

async function idbSetDescriptors(versionKey, data){
    try{
        const db = await idbOpen();
        return await new Promise((resolve,reject)=>{
            const tx = db.transaction('descriptors','readwrite');
            const store = tx.objectStore('descriptors');
            const putReq = store.put(data, versionKey);
            putReq.onsuccess = ()=> resolve(true);
            putReq.onerror = ()=> resolve(false);
        });
    }catch(e){ return false; }
}

async function api(url, data, opts){
    const options = opts || {};
    try {
        // Log the data being sent (but not the full screenshot to avoid console spam)
        const logData = { ...data };
        if (logData.screenshot) {
            logData.screenshot = logData.screenshot.substring(0, 50) + '... (truncated)';
        }
        // ULTRA-FAST: Skip logging for maximum speed
        
        // Ensure URL is correct - use relative URL to avoid port issues
        if (url.startsWith('http')) {
            // If it's already a full URL, use it as is
        } else if (url.startsWith('/')) {
            // If it starts with /, it's already a proper relative URL
        } else if (url.startsWith('?')) {
            // If it starts with ?, it's a query string, use current page
            url = window.location.pathname + url;
        } else {
            // If it's a relative URL, make it start with /
            url = '/' + url;
        }
        
        // Fallback: if URL contains localhost:3000, replace with current host
        if (url.includes('localhost:3000')) {
            url = url.replace('localhost:3000', window.location.host);
        }
        
        // Additional fallback: if URL still contains localhost:3000, force use current origin
        if (url.includes('localhost:3000')) {
            url = window.location.origin + url.replace(/^https?:\/\/[^\/]+/, '');
        }
        
        // ULTRA-FAST: Skip all logging for maximum speed
        
        const res = await fetch(url, { 
            method: 'POST', 
            body: data instanceof FormData ? data : new URLSearchParams(data),
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            // ULTRA-FAST: No timeout, let browser handle it for maximum speed
        });
        
        // Check if response is ok
        if (!res.ok) {
            const errorText = await res.text();
            console.error('API Error Response:', errorText);
            
            // Try to parse error response as JSON to check for WFA requirement
            try {
                const errorJson = JSON.parse(errorText);
                if (errorJson.need_reason) {
                    // Return the error response directly instead of throwing
                    return errorJson;
                }
            } catch (parseError) {
                // If not JSON, continue with normal error handling
            }
            
            if (!options.suppressModal) {
                showModalNotif(`Terjadi kesalahan (${res.status}).`, false, 'Gagal');
            }
            throw new Error(`HTTP error! status: ${res.status}, response: ${errorText}`);
        }
        
        // Get response text first to check if it's valid JSON
        const responseText = await res.text();
        
        // Try to parse as JSON
        let json;
        try {
            json = JSON.parse(responseText);
        } catch (parseError) {
            console.error('Response is not valid JSON:', responseText);
            throw new Error('Server returned invalid JSON response');
        }
        
        // Return the JSON response regardless of HTTP status code
        // Let the calling function handle the business logic (ok: false, etc.)
        if (!options.suppressModal) {
            if(json && json.ok===true && json.message){
                showModalNotif(json.message, true, 'Berhasil');
            } else if(json && json.ok===false && json.message){
                showModalNotif(json.message, false, 'Gagal');
            }
        }
        return json;
    } catch (error) {
        console.error('API call failed:', error);
        
        // Perbaikan: Handle specific error types
        if (error.name === 'TypeError' && error.message.includes('fetch')) {
            console.error('Network error - check if server is running');
            throw new Error('Koneksi ke server gagal. Pastikan server berjalan.');
        } else if (error.message.includes('ERR_CONNECTION_REFUSED')) {
            console.error('Connection refused - server not responding');
            throw new Error('Server tidak merespons. Silakan coba lagi.');
        }
        
        // Provide more specific error messages
        if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
            throw new Error('Tidak dapat terhubung ke server. Pastikan XAMPP sudah berjalan.');
        } else if (error.message.includes('HTTP error! status: 400')) {
            // Check if it's a time validation error
            if (error.message.includes('Presensi masuk hanya tersedia') || error.message.includes('Presensi masuk tersedia')) {
                throw new Error('Waktu presensi tidak sesuai. Silakan coba pada jam yang tepat.');
            } else {
                throw new Error('Data yang dikirim tidak valid. Silakan coba lagi.');
            }
        } else if (error.message.includes('HTTP error! status: 500')) {
            throw new Error('Server error. Silakan coba lagi.');
        }
        
        throw error;
    }
}

// Port Detection and Fix
(function() {
    // Check if we're on the wrong port
    if (window.location.port === '3000') {
        console.warn('Detected port 3000, redirecting to correct XAMPP port...');
        // Try common XAMPP ports
        const xamppPorts = ['80', '8080', '8000'];
        let redirectAttempted = false;
        
        for (const port of xamppPorts) {
            if (!redirectAttempted) {
                const testUrl = `http://localhost:${port}${window.location.pathname}${window.location.search}`;
                fetch(testUrl, { method: 'HEAD' })
                    .then(response => {
                        if (response.ok && !redirectAttempted) {
                            redirectAttempted = true;
                            console.log(`Redirecting to port ${port}`);
                            window.location.href = testUrl;
                        }
                    })
                    .catch(() => {
                        // Port not available, try next
                    });
            }
        }
    }
})();

// Service Worker Registration
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
                // Force update if there's a new service worker
                if (registration.waiting) {
                    registration.waiting.postMessage({ action: 'skipWaiting' });
                }
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Profile dropdown
(function(){
    const btn = qs('#btn-profile');
    const dd = qs('#dropdown-profile');
    if(btn && dd){
        btn.addEventListener('click', ()=> dd.classList.toggle('hidden'));
        document.addEventListener('click', (e)=>{ if(!btn.contains(e.target) && !dd.contains(e.target)) dd.classList.add('hidden'); });
    }
})();

<?php if ($page === 'login'): ?>
// Login
const loginForm = qs('#form-login');
if (loginForm) {
    loginForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await api('?ajax=login', fd);
        const msg = qs('#login-msg');
        if(r.ok){
            msg.className = 'text-green-600';
            msg.textContent = 'Login berhasil. Mengalihkan...';
            setTimeout(()=> location.href='?', 200); // Faster redirect
        } else {
            msg.className = 'text-red-600';
            msg.textContent = r.message || 'Gagal login';
        }
    });
}
<?php elseif ($page === 'register'): ?>
// Register camera
const regStart = qs('#reg-start-camera');
const regTake = qs('#reg-take-photo');
const regUpload = qs('#reg-upload-photo');
const regRemove = qs('#reg-remove-photo');
const regVideo = qs('#reg-video');
const regCanvas = qs('#reg-canvas');
const regPreview = qs('#reg-foto-preview');
const regVidContainer = qs('#reg-video-container');
const regFotoData = qs('#reg-foto-data');
const regPhotoFileInput = qs('#reg-photo-file-input');
let regStream = null;

if (regStart) {
    regStart.addEventListener('click', async ()=>{
        try{
            regStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } });
            regVideo.srcObject = regStream;
            regVidContainer.classList.remove('hidden');
            regTake.classList.remove('hidden');
            regStart.classList.add('hidden');
        }catch(err){ showNotif('Tidak bisa mengakses kamera'); console.error(err); }
    });
}

if (regTake) {
    regTake.addEventListener('click', ()=>{
        const ctx = regCanvas.getContext('2d');
        regCanvas.width = regVideo.videoWidth;
        regCanvas.height = regVideo.videoHeight;
        ctx.drawImage(regVideo,0,0,regCanvas.width,regCanvas.height);
        const dataUrl = regCanvas.toDataURL('image/jpeg');
        regPreview.src = dataUrl; regPreview.classList.remove('hidden');
        regFotoData.value = dataUrl;
        if(regStream){ regStream.getTracks().forEach(t=>t.stop()); regStream=null; }
        regVidContainer.classList.add('hidden');
        regTake.classList.add('hidden');
        regStart.classList.remove('hidden');
        regStart.textContent = 'Ambil Ulang Foto';
        regRemove.classList.remove('hidden');
    });
}

// Upload photo functionality
if (regUpload) {
    regUpload.addEventListener('click', ()=>{
        regPhotoFileInput.click();
    });
}

if (regPhotoFileInput) {
    regPhotoFileInput.addEventListener('change', (e)=>{
        const file = e.target.files[0];
        if (file) {
            // Validate file type
            if (!file.type.startsWith('image/')) {
                showNotif('File harus berupa gambar', false);
                return;
            }
            
            // Validate file size (max 5MB)
            if (file.size > 5 * 1024 * 1024) {
                showNotif('Ukuran file maksimal 5MB', false);
                return;
            }
            
            const reader = new FileReader();
            reader.onload = (e) => {
                const dataUrl = e.target.result;
                regPreview.src = dataUrl;
                regPreview.classList.remove('hidden');
                regFotoData.value = dataUrl;
                regRemove.classList.remove('hidden');
                regStart.textContent = 'Buka Kamera';
            };
            reader.readAsDataURL(file);
        }
    });
}

// Remove photo functionality
if (regRemove) {
    regRemove.addEventListener('click', ()=>{
        regPreview.src = '';
        regPreview.classList.add('hidden');
        regFotoData.value = '';
        regRemove.classList.add('hidden');
        regPhotoFileInput.value = '';
        regStart.textContent = 'Buka Kamera';
        
        // Stop camera if running
        if(regStream){ 
            regStream.getTracks().forEach(t=>t.stop()); 
            regStream=null; 
        }
        regVidContainer.classList.add('hidden');
        regTake.classList.add('hidden');
    });
}

const registerForm = qs('#form-register');
if (registerForm) {
    registerForm.addEventListener('submit', async (e)=>{
        e.preventDefault();
        const fd = new FormData(e.target);
        const r = await api('?ajax=register', fd);
        const msg = qs('#register-msg');
        if(r.ok){ msg.className='text-green-600'; msg.textContent='Registrasi berhasil. Silakan login.'; setTimeout(()=>location.href='?page=login', 300); } // Faster redirect
        else { msg.className='text-red-600'; msg.textContent=r.message||'Gagal registrasi'; }
    });
}
<?php elseif ($page === 'landing'): ?>
// Landing page - Face recognition attendance
const videoContainer = qs('#video-container');
const video = qs('#video');
const canvas = qs('#canvas');
const presensiStatus = qs('#presensi-status');
const scanButtonsContainer = qs('#scan-buttons-container');
const btnScanMasuk = qs('#btn-scan-masuk');
const btnScanPulang = qs('#btn-scan-pulang');
const btnBackScan = qs('#btn-back-scan');
const loadingOverlay = qs('#loading-overlay');

let labeledFaceDescriptors = [];
let isCameraActive = false;
let videoInterval = null;
let scanMode = '';
let lastSpokenMessage = '';
let videoPlayListenerAdded = false;

// Optimasi: Performance monitoring variables
let performanceStats = {
    detectionCount: 0,
    totalDetectionTime: 0,
    averageDetectionTime: 0,
    lastDetectionTime: 0
};

// ULTRA-FAST: Detection config optimized for maximum speed with good accuracy
let detectionConfig = {
    faceMatcherThreshold: 0.4, // Balanced threshold for speed and accuracy
    recognitionThreshold: 0.4, // Balanced threshold for speed and accuracy
    inputSize: 320, // Lower resolution for maximum speed (was 416)
    scoreThreshold: 0.3, // Lower threshold for faster detection (was 0.4)
    minFaceSize: 60, // Smaller minimum face size for faster detection (was 80)
    maxFaces: 1, // Limit to 1 face for faster processing
    confidenceThreshold: 0.7, // Balanced confidence requirement (was 0.8)
    detectionThrottle: 1, // Ultra-fast detection (1000 FPS) for <1 second processing
    qualityThreshold: 0.6, // Balanced quality threshold for speed (was 0.75)
    landmarkThreshold: 0.6, // Balanced landmark threshold for speed (was 0.75)
    expressionThreshold: 0.5, // Balanced expression threshold (was 0.6)
    landmarkWeight: 0.4, // Reduced weight for speed (was 0.5)
    descriptorWeight: 0.6, // Increased weight for face descriptor for speed (was 0.5)
    genderValidation: false, // Disable gender validation for maximum speed
    multiAttemptValidation: false, // Disable multi-attempt validation for maximum speed
    strictMode: false // Disable strict mode for maximum speed
};
let logMasukData = [];
let logPulangData = [];
let members = []; // Global members array for gender validation

// WFA Modal functions for landing page

function showWFAModal(message) {
    // Create WFA modal if it doesn't exist
    let wfaModal = document.getElementById('wfaModal');
    if (!wfaModal) {
        wfaModal = document.createElement('div');
        wfaModal.id = 'wfaModal';
        wfaModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
        wfaModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Work From Anywhere (WFA)</h3>
                <p class="text-gray-600 mb-4">${message}</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan WFA:</label>
                    <textarea id="wfaReason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Masukkan alasan kerja di luar kantor..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button id="wfaSubmit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Submit
                    </button>
                    <button id="wfaCancel" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(wfaModal);
        
        // Add event listeners
        document.getElementById('wfaSubmit').addEventListener('click', () => {
            const reason = document.getElementById('wfaReason').value.trim();
            if (reason) {
                wfaModal.classList.add('hidden');
                // Store WFA reason for next attendance submission
                window.pendingWFAReson = reason;
                // Retry attendance submission
                if (window.pendingAttendanceData) {
                    submitAttendanceWithWFA(window.pendingAttendanceData, reason);
                }
            } else {
                showNotif('Harap isi alasan WFA terlebih dahulu.', false);
            }
        });
        
        document.getElementById('wfaCancel').addEventListener('click', () => {
            wfaModal.classList.add('hidden');
            isProcessingRecognition = false;
            // Clear pending data
            window.pendingWFAReson = null;
            window.pendingAttendanceData = null;
        });
    }
    
    // Show modal
    wfaModal.classList.remove('hidden');
    document.getElementById('wfaReason').focus();
}

function submitAttendanceWithWFA(attendanceData, wfaReason) {
    // Add WFA reason to attendance data
    const dataWithWFA = {
        ...attendanceData,
        wfa_reason: wfaReason,
        is_wfa: true
    };
    
    // Submit attendance with WFA reason
    api('?ajax=save_attendance', dataWithWFA, { suppressModal: true })
        .then(response => {
            if (response.ok) {
                statusMessage('Presensi berhasil dengan alasan WFA!', 'bg-green-100 text-green-700');
                // Clear pending data
                window.pendingWFAReson = null;
                window.pendingAttendanceData = null;
                isProcessingRecognition = false;
            } else {
                statusMessage('Gagal menyimpan presensi: ' + (response.message || 'Unknown error'), 'bg-red-100 text-red-700');
                isProcessingRecognition = false;
            }
        })
        .catch(error => {
            console.error('Error submitting attendance with WFA:', error);
            statusMessage('Terjadi kesalahan saat menyimpan presensi.', 'bg-red-100 text-red-700');
            isProcessingRecognition = false;
        });
}

// Enhanced location detection with reverse geocoding
async function getStreetNameFromCoordinates(lat, lng) {
    // Read configured WFO center from embedded settings
    const wfoLat = parseFloat(document.body.getAttribute('data-wfo-lat')||'-6.9738');
    const wfoLng = parseFloat(document.body.getAttribute('data-wfo-lng')||'107.6300');
    const wfoName = document.body.getAttribute('data-wfo-address')||'Pusat WFO';
    const distance = calculateDistance(lat, lng, wfoLat, wfoLng);
    
    if (distance < 1.0) { // Within 1 km of WFO center
        return wfoName;
    } else if (distance < 5.0) { // Within 5 km
        return `${wfoName} (${distance.toFixed(1)}km dari pusat WFO)`;
    } else {
        // Try reverse geocoding for better location names
        try {
            const response = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&addressdetails=1&accept-language=id&zoom=18`);
            const data = await response.json();
            
            if (data && data.address) {
                const address = data.address;
                const parts = [];
                
                // Build readable address from components
                if (address.building) parts.push(address.building);
                else if (address.house_name) parts.push(address.house_name);
                
                if (address.road) parts.push(address.road);
                else if (address.pedestrian) parts.push(address.pedestrian);
                else if (address.footway) parts.push(address.footway);
                
                if (address.suburb) parts.push(address.suburb);
                else if (address.neighbourhood) parts.push(address.neighbourhood);
                
                if (address.city) parts.push(address.city);
                else if (address.town) parts.push(address.town);
                else if (address.village) parts.push(address.village);
                
                if (parts.length > 0) {
                    return parts.join(', ');
                }
                
                // Fallback to display_name
                if (data.display_name) {
                    return data.display_name.replace(/, Indonesia$/, '');
                }
            }
        } catch (error) {
            console.warn('Reverse geocoding failed:', error);
        }
        
        // Final fallback: coordinates with distance info
        return `Lokasi: ${lat.toFixed(6)}, ${lng.toFixed(6)} (${distance.toFixed(1)}km dari kantor)`;
    }
}

// Helper function to calculate distance between two coordinates
function calculateDistance(lat1, lng1, lat2, lng2) {
    const R = 6371; // Earth's radius in kilometers
    const dLat = (lat2 - lat1) * Math.PI / 180;
    const dLng = (lng2 - lng1) * Math.PI / 180;
    const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
              Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
              Math.sin(dLng/2) * Math.sin(dLng/2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    return R * c;
}

// Helper functions for image variations to improve recognition accuracy
function createRotatedImage(img, degrees) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        // Set canvas size to accommodate rotation
        const size = Math.max(img.width, img.height) * 1.5;
        canvas.width = size;
        canvas.height = size;
        
        // Center the image
        ctx.translate(size / 2, size / 2);
        ctx.rotate((degrees * Math.PI) / 180);
        ctx.drawImage(img, -img.width / 2, -img.height / 2);
        
        // Convert back to image
        const rotatedImg = new Image();
        rotatedImg.onload = () => resolve(rotatedImg);
        rotatedImg.src = canvas.toDataURL();
    });
}

function createScaledImage(img, scale) {
    return new Promise((resolve) => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');
        
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;
        
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
        
        const scaledImg = new Image();
        scaledImg.onload = () => resolve(scaledImg);
        scaledImg.src = canvas.toDataURL();
    });
}

// Initialize speech synthesis for offline use
function initializeSpeechSynthesis() {
    try {
        if ('speechSynthesis' in window) {
            // Pre-load voices for offline use
            const loadVoices = () => {
                const voices = speechSynthesis.getVoices();
                console.log('Available voices:', voices.length);
                
                // Log available Indonesian voices
                const indonesianVoices = voices.filter(voice => 
                    voice.lang.startsWith('id') || 
                    voice.lang.includes('Indonesian') ||
                    voice.name.includes('Indonesian')
                );
                
                if (indonesianVoices.length > 0) {
                    console.log('Indonesian voices found:', indonesianVoices.map(v => v.name));
                } else {
                    console.log('No Indonesian voices found, will use default voice');
                }
            };

            // Load voices immediately if available
            if (speechSynthesis.getVoices().length > 0) {
                loadVoices();
            } else {
                // Wait for voices to load
                speechSynthesis.addEventListener('voiceschanged', loadVoices, { once: true });
            }
            
            console.log('Speech synthesis initialized for offline use');
        } else {
            console.warn('Speech synthesis not supported in this browser');
        }
    } catch (error) {
        console.error('Failed to initialize speech synthesis:', error);
    }
}

// Initialize face recognition system
async function initializeFaceRecognition() {
    try {
        await loadFaceApiModels();
        await loadLabeledFaceDescriptors();
        // ULTRA-FAST: Skip logging for maximum speed
    } catch (error) {
        console.error('❌ Failed to initialize face recognition:', error);
        showNotif('Gagal memuat sistem pengenalan wajah', false);
    }
}


async function loadFaceApiModels(){
    if (window.faceApiModelsLoaded) return; // cache: only load once per session
    if (!loadingOverlay) return;
    
    const loadingProgress = qs('#loading-progress');
    loadingOverlay.classList.remove('hidden');

    const MODEL_URL = 'assets/js/face-api-models';

    try {
        loadingProgress.textContent = 'Memuat model deteksi wajah...';
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODEL_URL);

        loadingProgress.textContent = 'Memuat model landmark wajah...';
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODEL_URL);

        loadingProgress.textContent = 'Memuat model pengenalan wajah...';
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODEL_URL);

        loadingProgress.textContent = 'Memuat model ekspresi wajah...';
        await faceapi.nets.faceExpressionNet.loadFromUri(MODEL_URL);

        loadingProgress.textContent = 'Model AI berhasil dimuat!';
        window.faceApiModelsLoaded = true;
        // INSTANT: No delay for maximum speed
    } catch (error) {
        loadingProgress.textContent = 'Gagal memuat model AI. Silakan refresh halaman.';
        if (presensiStatus) {
            presensiStatus.textContent = 'Gagal memuat model AI. Silakan refresh halaman.';
            presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-red-100 text-red-700';
            presensiStatus.classList.remove('hidden');
        }
        setTimeout(() => { loadingOverlay.classList.add('hidden'); }, 1000); // Faster error display
        throw error;
    } finally {
        loadingOverlay.classList.add('hidden');
    }
}

async function fetchMembers(){
    const res = await fetch('?ajax=get_members');
    const j = await res.json();
    return j.data || [];
}

async function loadLabeledFaceDescriptors(){
    members = await fetchMembers(); // Store globally for gender validation
    // Try load from IndexedDB cache first
    const versionKey = await computeMembersVersionKey(members);
    const cached = await idbGetDescriptors(versionKey);
    if (cached && Array.isArray(cached) && cached.length > 0) {
        labeledFaceDescriptors = cached.map(item => new faceapi.LabeledFaceDescriptors(
            item.label,
            item.descriptors.map(d => new Float32Array(d))
        ));
        console.log('Loaded face descriptors from cache:', labeledFaceDescriptors.length);
        return;
    }
    labeledFaceDescriptors = [];
    // ULTRA-FAST: Skip logging for maximum speed
    let loadedCount = 0;
    let failedCount = 0;
    
    // ULTRA-FAST: larger batch size for maximum speed
    const batchSize = 20;
    for (let i = 0; i < members.length; i += batchSize) {
        const batch = members.slice(i, i + batchSize);
        const batchPromises = batch.map(async (m) => {
            if (!m.foto_base64) {
                console.warn(`No photo for member: ${m.nama} (${m.nim})`);
                return null;
            }
            try {
                const img = await faceapi.fetchImage(m.foto_base64);
                // ENHANCED: Multiple detection attempts for better accuracy
                let det = null;
                
                // ULTRA-FAST: Single optimized detection attempt for maximum speed
                const detectionParams = [
                    { inputSize: 320, scoreThreshold: 0.3 } // Single ultra-fast attempt
                ];
                
                for (const params of detectionParams) {
                    try {
                        det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions(params))
                            .withFaceLandmarks().withFaceDescriptor();
                        if (det) break; // Success, exit loop
                    } catch (err) {
                        console.warn(`Detection attempt failed for ${m.nama} with params:`, params, err);
                    }
                }
                if (det) {
                    loadedCount++;
                    // ULTRA-FAST: Skip logging for maximum speed
                    // Create multiple descriptors for better accuracy
                    const descriptors = [det.descriptor];
                    
                    // OPTIMIZED: Skip multiple descriptors for speed - single high-quality descriptor is sufficient
                    
                    return new faceapi.LabeledFaceDescriptors(m.nim, descriptors);
                } else {
                    failedCount++;
                    console.warn(`✗ Failed to detect face for: ${m.nama} (${m.nim})`);
                }
            } catch (err) {
                console.warn('Deteksi gagal untuk', m.nama, err);
            }
            return null;
        });
        const batchResults = await Promise.all(batchPromises);
        labeledFaceDescriptors.push(...batchResults.filter(Boolean));
        // INSTANT: No delay for maximum speed
    }
    // ULTRA-FAST: Skip logging for maximum speed
    
    if (loadedCount === 0) {
        console.error('⚠️ WARNING: No face descriptors were loaded! Check if members have valid photos.');
    } else if (failedCount > 0) {
        console.warn(`⚠️ WARNING: ${failedCount} members could not be loaded. Check their photos.`);
    }
    // Save to IndexedDB cache
    try {
        const toStore = labeledFaceDescriptors.map(ld => ({
            label: ld.label,
            descriptors: ld.descriptors.map(arr => Array.from(arr))
        }));
        await idbSetDescriptors(versionKey, toStore);
        console.log('Saved face descriptors to cache:', toStore.length);
    } catch(e) { console.warn('Failed saving descriptors to cache', e); }
}

// ULTRA-FAST: Smart threshold adjustment for maximum speed
function adjustDetectionThreshold() {
    // Smart threshold adjustment based on performance for maximum speed
    if (performanceStats.detectionCount > 20 && performanceStats.averageDetectionTime > 200) {
        console.log('🔧 Adjusting thresholds for maximum speed...');
        // Increase thresholds to reduce processing time
        detectionConfig.faceMatcherThreshold = Math.min(0.5, detectionConfig.faceMatcherThreshold + 0.05);
        detectionConfig.recognitionThreshold = Math.min(0.5, detectionConfig.recognitionThreshold + 0.05);
        console.log(`📊 New thresholds: FaceMatcher=${detectionConfig.faceMatcherThreshold}, Recognition=${detectionConfig.recognitionThreshold}`);
    } else if (performanceStats.detectionCount > 30 && performanceStats.averageDetectionTime < 150) {
        // If performance is good, maintain balanced thresholds
        console.log('🔧 Performance is good, maintaining speed-optimized thresholds...');
        // Keep balanced thresholds for speed
        detectionConfig.faceMatcherThreshold = Math.max(0.4, detectionConfig.faceMatcherThreshold);
        detectionConfig.recognitionThreshold = Math.max(0.4, detectionConfig.recognitionThreshold);
        console.log(`📊 Speed-optimized thresholds: FaceMatcher=${detectionConfig.faceMatcherThreshold}, Recognition=${detectionConfig.recognitionThreshold}`);
    }
}

function startScan(mode){
    scanMode = mode;
    recognitionCompleted = false; // Reset recognition completion flag for new scan
    resetRecognitionSystem(); // Reset system for new scan
    scanButtonsContainer.classList.add('hidden');
    videoContainer.classList.remove('hidden');
    btnBackScan.classList.remove('hidden');
    qs('#btn-stop-detection').classList.remove('hidden');
    
    // Hide the two panel layout (text and image sections)
    const twoPanelLayout = qs('#two-panel-layout');
    if (twoPanelLayout) {
        twoPanelLayout.classList.add('hidden');
    }
    
    // Show appropriate log table
    if (mode === 'masuk') {
        qs('#log-masuk-container').classList.remove('hidden');
        qs('#log-pulang-container').classList.add('hidden');
        loadLogMasuk();
    } else {
        qs('#log-pulang-container').classList.remove('hidden');
        qs('#log-masuk-container').classList.add('hidden');
        loadLogPulang();
    }
    
    startVideo();
}

if (btnScanMasuk) {
    btnScanMasuk.addEventListener('click', ()=> startScan('masuk'));
}
if (btnScanPulang) {
    btnScanPulang.addEventListener('click', ()=> startScan('pulang'));
}
if (btnBackScan) {
    btnBackScan.addEventListener('click', ()=>{ resetPresensiPage(); });
}

// Add event listener for stop detection button
const btnStopDetection = qs('#btn-stop-detection');
if (btnStopDetection) {
    btnStopDetection.addEventListener('click', ()=>{ 
        stopDetection();
        statusMessage('Deteksi dihentikan. Klik "Kembali" untuk keluar.', 'bg-yellow-100 text-yellow-700');
    });
}

function resetPresensiPage(){
    stopVideo();
    resetRecognitionSystem(); // Reset recognition system
    scanButtonsContainer.classList.remove('hidden');
    videoContainer.classList.add('hidden');
    btnBackScan.classList.add('hidden');
    qs('#btn-stop-detection').classList.add('hidden');
    
    // Show the two panel layout (text and image sections) again
    const twoPanelLayout = qs('#two-panel-layout');
    if (twoPanelLayout) {
        twoPanelLayout.classList.remove('hidden');
    }
    
    qs('#log-masuk-container').classList.add('hidden');
    qs('#log-pulang-container').classList.add('hidden');
    if (presensiStatus) {
        presensiStatus.classList.add('hidden');
        presensiStatus.textContent='';
    }
    videoPlayListenerAdded = false;
    if (window.presensiTimeout) {
        clearTimeout(window.presensiTimeout);
        window.presensiTimeout = null;
    }
    if (window.speechTimeout) {
        clearTimeout(window.speechTimeout);
        window.speechTimeout = null;
    }
    speechSynthesis.cancel();
    speechQueue = [];
    isSpeaking = false;
    
    // Advanced: Reset detection history for fresh start
    detectionHistory = [];
    lastSuccessfulDetection = null;
    detectionAttempts = 0;
    recognitionCompleted = false; // Reset recognition completion flag
}

function startVideo(){
    if (!video) return;
    
    if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia){
        const constraints = {
            video: {
                width: { ideal: 640, max: 1280 },
                height: { ideal: 480, max: 720 },
                frameRate: { ideal: 15, max: 30 },
                facingMode: 'user'
            }
        };
        navigator.mediaDevices.getUserMedia(constraints).then(stream => {
            video.srcObject = stream;
            isCameraActive = true;
            // Mirror hanya video supaya tombol dan teks tidak terbalik
            if (video) video.classList.add('mirror-video');
            video.addEventListener('loadedmetadata', () => {});
        }).catch(err => {
            console.error('Error camera', err);
            statusMessage('Error: Tidak dapat mengakses kamera.', 'bg-red-100 text-red-700');
        });
    }
}

function stopVideo(){
    if(video && video.srcObject){ video.srcObject.getTracks().forEach(t=>t.stop()); video.srcObject=null; }
    isCameraActive=false; if(videoInterval) clearInterval(videoInterval); 
    
    // Clear speech queue and cancel any ongoing speech
    speechSynthesis.cancel();
    speechQueue = [];
    isSpeaking = false;
    
    if(canvas){ const ctx = canvas.getContext('2d'); ctx.clearRect(0,0,canvas.width,canvas.height); }
}

function startVideoInterval(){
    if(!isCameraActive || videoInterval || !video) return;
    if (!faceapi.nets.tinyFaceDetector.isLoaded) {
        console.error('Face detection models not loaded');
        statusMessage('Model AI belum dimuat. Silakan refresh halaman.', 'bg-red-100 text-red-700');
        return;
    }
    const displaySize = { width: video.clientWidth, height: video.clientHeight };
    faceapi.matchDimensions(canvas, displaySize);
    // Advanced: Optimized interval for maximum performance and accuracy
    let lastDetectionTime = 0;
    let detectionThrottle = detectionConfig.detectionThrottle; // Use config value
    
    videoInterval = setInterval(async ()=>{
        const now = Date.now();
        if (now - lastDetectionTime < detectionThrottle) {
            return; // Skip detection jika terlalu cepat
        }
        
        // Continue detection for multi-person support
        // Only stop if explicitly requested
        lastDetectionTime = now;
        
        try {
            // Optimasi: Performance monitoring
            const detectionStartTime = performance.now();
            
            // ENHANCED: Optimized detection with higher resolution for better accuracy
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: detectionConfig.inputSize,
                scoreThreshold: detectionConfig.scoreThreshold
            })).withFaceLandmarks().withFaceDescriptors();
            
            // BALANCED: Smart filtering for accuracy + speed
            const qualityDetections = detections.filter(detection => {
                const quality = assessFaceQuality(detection);
                const box = detection.detection.box;
                const area = box.width * box.height;
                return quality >= detectionConfig.qualityThreshold && area >= detectionConfig.minFaceSize * detectionConfig.minFaceSize;
            });
            
            // Sort by quality and take best detections
            qualityDetections.sort((a, b) => assessFaceQuality(b) - assessFaceQuality(a));
            const bestDetections = qualityDetections.slice(0, detectionConfig.maxFaces);
            
            // Optimasi: Update performance stats
            const detectionTime = performance.now() - detectionStartTime;
            performanceStats.detectionCount++;
            performanceStats.totalDetectionTime += detectionTime;
            performanceStats.averageDetectionTime = performanceStats.totalDetectionTime / performanceStats.detectionCount;
            performanceStats.lastDetectionTime = detectionTime;
            
            // ULTRA-FAST: Skip performance logging for maximum speed
            if (performanceStats.detectionCount % 50 === 0) {
                // Skip logging for maximum speed
                adjustDetectionThreshold();
                
                // ULTRA-FAST: Dynamic throttle for maximum speed
                if (performanceStats.averageDetectionTime > 100) {
                    detectionThrottle = Math.min(20, detectionThrottle + 2);
                } else if (performanceStats.averageDetectionTime < 50 && detectionThrottle > 1) {
                    detectionThrottle = Math.max(1, detectionThrottle - 1);
                }
            }
            const resized = faceapi.resizeResults(bestDetections, displaySize);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0,0,canvas.width,canvas.height);
            if (resized.length > 0) {
                if (labeledFaceDescriptors && labeledFaceDescriptors.length > 0) {
                    // Advanced: Stricter threshold for better accuracy
                    const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, detectionConfig.faceMatcherThreshold);
                    const results = resized.map(d => faceMatcher.findBestMatch(d.descriptor));
                    const ctx2 = canvas.getContext('2d');
                    ctx2.clearRect(0,0,canvas.width,canvas.height);
                    results.forEach((result, i) => {
                        const box = resized[i].detection.box;
                        const face = resized[i];
                        // Hitung posisi mirror: video di-mirror, canvas tidak, jadi koordinat X dibalik
                        const mirroredX = canvas.width - (box.x + box.width);
                        // Gambar kotak
                        ctx2.strokeStyle = '#22c55e';
                        ctx2.lineWidth = 2;
                        ctx2.strokeRect(mirroredX, box.y, box.width, box.height);
                        // Label hasil (tidak terbalik)
                        const shouldAccept = shouldAcceptDetection(result, face);
                        const label = `${result.toString()} ${shouldAccept ? '✓' : '?'}`;
                        ctx2.font = '14px Inter, sans-serif';
                        ctx2.fillStyle = 'rgba(37, 99, 235, 0.9)';
                        const padding = 4;
                        const textWidth = ctx2.measureText(label).width;
                        ctx2.fillRect(mirroredX, Math.max(0, box.y - 20), textWidth + padding*2, 20);
                        ctx2.fillStyle = '#fff';
                        ctx2.fillText(label, mirroredX + padding, Math.max(12, box.y - 6));
                        // Proses pengenalan
                        if (shouldAccept) {
                            // Recognition handled instantly in shouldAcceptDetection -> handleRecognition
                        }
                    });
                } else {
                    statusMessage('Database wajah kosong. Silakan tambah member.', 'bg-gray-200 text-gray-600');
                    console.warn('⚠️ No face descriptors available for recognition');
                }
            } else {
                if (presensiStatus && presensiStatus.textContent !== 'Arahkan wajah ke kamera') {
                    presensiStatus.textContent = 'Arahkan wajah ke kamera';
                    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-blue-100 text-blue-700';
                    presensiStatus.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Face detection error:', error);
            if (presensiStatus && presensiStatus.textContent !== 'Error deteksi wajah') {
                statusMessage('Error deteksi wajah. Coba refresh halaman.', 'bg-red-100 text-red-700');
            }
        }
    }, 10); // ULTRA-FAST interval for <2 second processing
}

if (video) {
    video.addEventListener('play', ()=>{
        if (!videoPlayListenerAdded) {
            startVideoInterval();
            videoPlayListenerAdded = true;
        }
    });
}

function getTopExpression(expressions){
    const map = { happy:'Senang', sad:'Sedih', neutral:'Biasa', angry:'Marah', disgusted:'Capek', surprised:'Ngantuk', fearful:'Laper' };
    let top='neutral', max=0; for(const [k,v] of Object.entries(expressions||{})){ if(v>max){ max=v; top=k; } }
    return map[top] || 'Biasa';
}

// Advanced: Enhanced face quality assessment with detailed analysis
function assessFaceQuality(face) {
    if (!face || !face.detection) return 0;
    
    const box = face.detection.box;
    const area = box.width * box.height;
    const aspectRatio = box.width / box.height;
    
    // Quality factors with detailed analysis
    let quality = 1.0;
    
    // 1. Size factor (prefer larger faces for better detail) - stricter requirements
    if (area < 20000) quality *= 0.4; // Too small - stricter
    else if (area < 30000) quality *= 0.7; // Small but acceptable
    else if (area > 100000) quality *= 1.4; // Large and detailed - bonus
    else if (area > 60000) quality *= 1.2; // Good size - bonus
    
    // 2. Aspect ratio factor (prefer natural face proportions)
    if (aspectRatio < 0.6 || aspectRatio > 1.6) quality *= 0.5; // Too distorted
    else if (aspectRatio < 0.7 || aspectRatio > 1.4) quality *= 0.8; // Slightly distorted
    else if (aspectRatio >= 0.8 && aspectRatio <= 1.2) quality *= 1.2; // Good proportions
    
    // 3. Position factor (prefer centered faces) - stricter centering
    const centerX = box.x + box.width / 2;
    const centerY = box.y + box.height / 2;
    const canvasCenterX = 320; // Assuming 640px width
    const canvasCenterY = 240; // Assuming 480px height
    const distanceFromCenter = Math.sqrt(
        Math.pow(centerX - canvasCenterX, 2) + Math.pow(centerY - canvasCenterY, 2)
    );
    if (distanceFromCenter > 120) quality *= 0.5; // Too far from center - stricter
    else if (distanceFromCenter > 80) quality *= 0.8; // Slightly off-center
    else if (distanceFromCenter < 40) quality *= 1.3; // Well centered - bonus
    
    // 4. Enhanced landmark quality factor (if available)
    if (face.landmarks) {
        const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
        quality *= (0.7 + landmarkScore * 0.3); // Weight landmark quality heavily
    }
    
    // 5. Expression quality factor (if available)
    if (face.expressions) {
        const expressions = face.expressions;
        const maxExpression = Math.max(...Object.values(expressions));
        if (maxExpression > 0.8) quality *= 1.1; // Clear expression
        else if (maxExpression < 0.3) quality *= 0.9; // Unclear expression
    }
    
    // 6. Detection confidence factor - stricter requirements
    if (face.detection.score) {
        if (face.detection.score > 0.95) quality *= 1.4; // Very high confidence - bonus
        else if (face.detection.score > 0.9) quality *= 1.2; // High confidence
        else if (face.detection.score > 0.8) quality *= 1.1; // Good confidence
        else if (face.detection.score < 0.7) quality *= 0.6; // Low confidence - stricter
    }
    
    // 7. Face angle and symmetry factor (if landmarks available)
    if (face.landmarks && face.landmarks.positions) {
        const landmarks = face.landmarks.positions;
        
        // Check eye symmetry
        if (landmarks[36] && landmarks[45]) {
            const leftEyeX = landmarks[36].x;
            const rightEyeX = landmarks[45].x;
            const eyeSymmetry = Math.abs(leftEyeX - rightEyeX);
            if (eyeSymmetry > 20) quality *= 0.7; // Face is tilted
            else if (eyeSymmetry < 10) quality *= 1.2; // Good symmetry - bonus
        }
        
        // Check nose position
        if (landmarks[30] && landmarks[36] && landmarks[45]) {
            const noseX = landmarks[30].x;
            const faceCenterX = (landmarks[36].x + landmarks[45].x) / 2;
            const noseOffset = Math.abs(noseX - faceCenterX);
            if (noseOffset > 15) quality *= 0.8; // Nose off-center
            else if (noseOffset < 5) quality *= 1.1; // Well centered nose - bonus
        }
    }
    
    return Math.max(0, Math.min(1.5, quality)); // Allow quality > 1 for excellent faces
}

// ENHANCED: Detailed facial feature assessment for better accuracy
function assessEnhancedLandmarkQuality(landmarks) {
    if (!landmarks || !landmarks.positions || landmarks.positions.length < 68) return 0;
    
    const positions = landmarks.positions;
    let featureScore = 0;
    
    // 1. Eye region analysis (points 36-47 for left eye, 42-47 for right eye)
    const leftEyePoints = positions.slice(36, 42);
    const rightEyePoints = positions.slice(42, 48);
    const eyeScore = assessEyeQuality(leftEyePoints, rightEyePoints);
    featureScore += eyeScore * 0.3; // 30% weight for eyes
    
    // 2. Nose analysis (points 27-35)
    const nosePoints = positions.slice(27, 36);
    const noseScore = assessNoseQuality(nosePoints);
    featureScore += noseScore * 0.25; // 25% weight for nose
    
    // 3. Eyebrow analysis (points 17-26)
    const leftEyebrow = positions.slice(17, 22);
    const rightEyebrow = positions.slice(22, 27);
    const eyebrowScore = assessEyebrowQuality(leftEyebrow, rightEyebrow);
    featureScore += eyebrowScore * 0.2; // 20% weight for eyebrows
    
    // 4. Mouth analysis (points 48-67)
    const mouthPoints = positions.slice(48, 68);
    const mouthScore = assessMouthQuality(mouthPoints);
    featureScore += mouthScore * 0.15; // 15% weight for mouth
    
    // 5. Face contour analysis (points 0-16)
    const contourPoints = positions.slice(0, 17);
    const contourScore = assessContourQuality(contourPoints);
    featureScore += contourScore * 0.1; // 10% weight for face shape
    
    return Math.min(1, featureScore);
}

function assessEyeQuality(leftEye, rightEye) {
    if (!leftEye || !rightEye || leftEye.length !== 6 || rightEye.length !== 6) return 0;
    
    let score = 1.0;
    
    // Check eye symmetry
    const leftEyeCenter = getCenterPoint(leftEye);
    const rightEyeCenter = getCenterPoint(rightEye);
    const eyeDistance = Math.abs(leftEyeCenter.x - rightEyeCenter.x);
    const eyeHeightDiff = Math.abs(leftEyeCenter.y - rightEyeCenter.y);
    
    // Good symmetry bonus
    if (eyeHeightDiff < eyeDistance * 0.05) score *= 1.2;
    else if (eyeHeightDiff > eyeDistance * 0.15) score *= 0.8;
    
    // Check eye shape consistency
    const leftEyeShape = getEyeShape(leftEye);
    const rightEyeShape = getEyeShape(rightEye);
    const shapeConsistency = 1 - Math.abs(leftEyeShape - rightEyeShape);
    score *= (0.5 + shapeConsistency * 0.5);
    
    return Math.min(1, score);
}

function assessNoseQuality(nosePoints) {
    if (!nosePoints || nosePoints.length !== 9) return 0;
    
    let score = 1.0;
    
    // Check nose alignment (should be roughly vertical)
    const noseTop = nosePoints[0];
    const noseBottom = nosePoints[6];
    const noseSlope = Math.abs((noseBottom.x - noseTop.x) / (noseBottom.y - noseTop.y));
    
    if (noseSlope < 0.1) score *= 1.2; // Very straight
    else if (noseSlope > 0.3) score *= 0.8; // Too tilted
    
    // Check nose width consistency
    const noseWidth = Math.abs(nosePoints[4].x - nosePoints[8].x);
    const noseHeight = Math.abs(noseBottom.y - noseTop.y);
    const noseRatio = noseWidth / noseHeight;
    
    if (noseRatio > 0.3 && noseRatio < 0.6) score *= 1.1; // Good proportions
    else if (noseRatio > 0.8 || noseRatio < 0.2) score *= 0.9; // Unusual proportions
    
    return Math.min(1, score);
}

function assessEyebrowQuality(leftEyebrow, rightEyebrow) {
    if (!leftEyebrow || !rightEyebrow || leftEyebrow.length !== 5 || rightEyebrow.length !== 5) return 0;
    
    let score = 1.0;
    
    // Check eyebrow symmetry
    const leftEyebrowCenter = getCenterPoint(leftEyebrow);
    const rightEyebrowCenter = getCenterPoint(rightEyebrow);
    const eyebrowHeightDiff = Math.abs(leftEyebrowCenter.y - rightEyebrowCenter.y);
    const eyebrowDistance = Math.abs(leftEyebrowCenter.x - rightEyebrowCenter.x);
    
    if (eyebrowHeightDiff < eyebrowDistance * 0.05) score *= 1.1;
    else if (eyebrowHeightDiff > eyebrowDistance * 0.15) score *= 0.9;
    
    // Check eyebrow shape consistency
    const leftShape = getEyebrowShape(leftEyebrow);
    const rightShape = getEyebrowShape(rightEyebrow);
    const shapeConsistency = 1 - Math.abs(leftShape - rightShape);
    score *= (0.7 + shapeConsistency * 0.3);
    
    return Math.min(1, score);
}

function assessMouthQuality(mouthPoints) {
    if (!mouthPoints || mouthPoints.length !== 20) return 0;
    
    let score = 1.0;
    
    // Check mouth symmetry
    const leftMouth = mouthPoints[0];
    const rightMouth = mouthPoints[6];
    const mouthCenter = mouthPoints[9];
    
    const leftDistance = Math.abs(leftMouth.x - mouthCenter.x);
    const rightDistance = Math.abs(rightMouth.x - mouthCenter.x);
    const symmetry = 1 - Math.abs(leftDistance - rightDistance) / Math.max(leftDistance, rightDistance);
    
    score *= (0.8 + symmetry * 0.2);
    
    return Math.min(1, score);
}

function assessContourQuality(contourPoints) {
    if (!contourPoints || contourPoints.length !== 17) return 0;
    
    let score = 1.0;
    
    // Check face shape consistency
    const chin = contourPoints[8];
    const leftJaw = contourPoints[4];
    const rightJaw = contourPoints[12];
    
    const jawWidth = Math.abs(rightJaw.x - leftJaw.x);
    const faceHeight = Math.abs(chin.y - contourPoints[0].y);
    const faceRatio = jawWidth / faceHeight;
    
    if (faceRatio > 0.6 && faceRatio < 0.9) score *= 1.1; // Good face proportions
    else if (faceRatio > 1.2 || faceRatio < 0.4) score *= 0.9; // Unusual proportions
    
    return Math.min(1, score);
}

// Helper functions
function getCenterPoint(points) {
    const x = points.reduce((sum, p) => sum + p.x, 0) / points.length;
    const y = points.reduce((sum, p) => sum + p.y, 0) / points.length;
    return { x, y };
}

function getEyeShape(eyePoints) {
    const width = Math.abs(eyePoints[3].x - eyePoints[0].x);
    const height = Math.abs(eyePoints[1].y - eyePoints[4].y);
    return width / height;
}

function getEyebrowShape(eyebrowPoints) {
    const start = eyebrowPoints[0];
    const end = eyebrowPoints[4];
    const middle = eyebrowPoints[2];
    const arch = Math.abs(middle.y - (start.y + end.y) / 2);
    const length = Math.abs(end.x - start.x);
    return arch / length;
}

// Advanced: Multiple detection attempts for better accuracy
let detectionAttempts = 0;
// Multi-person detection queue system
let detectionHistory = [];
let recognitionQueue = [];
let isProcessingQueue = false;
let lastSuccessfulDetection = null;

function shouldAcceptDetection(result, face) {
    if (!result || result.label === 'unknown') return false;
    // Skip if this label recently processed
    const lastTs = processedLabels.get(result.label) || 0;
    if (Date.now() - lastTs < processedCooldownMs) return false;
    
    // ENHANCED: Stricter checks for better accuracy and prevent misdetection
    if (result.distance > detectionConfig.recognitionThreshold) return false;
    
    // Enhanced quality check with facial feature analysis
    const quality = assessFaceQuality(face);
    if (quality < detectionConfig.qualityThreshold) return false;
    
    // ENHANCED: Stricter facial feature consistency check
    if (face.landmarks) {
        const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
        if (landmarkScore < detectionConfig.landmarkThreshold) return false; // Use config threshold
    }
    
    // NEW: Gender validation to prevent cross-gender misdetection
    if (detectionConfig.genderValidation) {
        const genderMatch = validateGenderConsistency(result.label, face);
        if (!genderMatch) {
            console.log(`🚫 Gender validation failed for ${result.label}`);
            return false;
        }
    }
    
    // NEW: Multi-attempt validation for critical decisions
    if (detectionConfig.multiAttemptValidation && detectionConfig.strictMode) {
        const validationScore = performMultiAttemptValidation(result, face);
        if (validationScore < 0.5) { // Reduced from 0.8 to 0.5 for more practical validation
            console.log(`🚫 Multi-attempt validation failed for ${result.label} (score: ${validationScore.toFixed(3)})`);
            return false;
        }
    }
    
    // Check if this person is already being processed
    if (isProcessingRecognition) return false;
    
    // ENHANCED: Log successful detection for debugging
    console.log(`✅ Valid detection: ${result.label} (distance: ${result.distance.toFixed(3)}, quality: ${quality.toFixed(3)})`);
    console.log(`🎯 Processing attendance for: ${result.label}`);
    
    // INSTANT RECOGNITION: Process immediately on first valid detection
    addToRecognitionQueue(result.label, face);
    return true;
}

function addToRecognitionQueue(label, face) {
    // INSTANT PROCESSING: Always process immediately for maximum speed
    // console.log(`🚀 INSTANT PROCESSING for ${label}`);
    handleRecognition(label, 'Biasa'); // Use default expression for speed
}

// NEW: Gender validation function to prevent cross-gender misdetection
function validateGenderConsistency(label, face) {
    try {
        // Check if members array is available
        if (!members || !Array.isArray(members) || members.length === 0) {
            console.log('⚠️ Members array not available for gender validation, allowing detection');
            return true; // Allow detection if no member data
        }
        
        // Get employee data to check gender consistency
        const employee = members.find(m => m.nim === label);
        if (!employee) {
            console.log(`⚠️ Employee data not found for ${label}, allowing detection`);
            return true; // If no employee data, allow detection
        }
        
        // Simple gender detection based on facial features
        if (face.landmarks && face.landmarks.positions) {
            const landmarks = face.landmarks.positions;
            
            // Check if we have enough landmarks
            if (landmarks.length < 68) {
                console.log(`⚠️ Insufficient landmarks for gender validation (${landmarks.length}/68), allowing detection`);
                return true;
            }
            
            // Analyze jawline width (typically wider in males)
            const jawWidth = Math.abs(landmarks[16].x - landmarks[0].x);
            const faceHeight = Math.abs(landmarks[8].y - landmarks[19].y);
            const jawRatio = jawWidth / faceHeight;
            
            // Analyze eyebrow thickness and position
            const leftEyebrowThickness = Math.abs(landmarks[19].y - landmarks[20].y);
            const rightEyebrowThickness = Math.abs(landmarks[24].y - landmarks[25].y);
            const avgEyebrowThickness = (leftEyebrowThickness + rightEyebrowThickness) / 2;
            
            // More lenient heuristic: wider jaw and thicker eyebrows suggest male
            const isLikelyMale = jawRatio > 0.75 && avgEyebrowThickness > 4; // More strict criteria
            const isLikelyFemale = jawRatio < 0.6 && avgEyebrowThickness < 2; // More strict criteria
            
            // Check if employee name suggests gender (simple heuristic)
            const name = employee.nama.toLowerCase();
            const maleNames = ['budi', 'andi', 'joko', 'agus', 'doni', 'riko', 'tono', 'surya', 'rama', 'ahmad', 'muhammad', 'ali', 'umar', 'yusuf'];
            const femaleNames = ['sari', 'dewi', 'maya', 'lina', 'rina', 'siti', 'nina', 'dina', 'lisa', 'ana', 'sarah', 'fatimah', 'aisha', 'zainab'];
            
            const nameSuggestsMale = maleNames.some(maleName => name.includes(maleName));
            const nameSuggestsFemale = femaleNames.some(femaleName => name.includes(femaleName));
            
            // Only reject if we have VERY strong conflicting indicators
            if (isLikelyMale && nameSuggestsFemale && jawRatio > 0.8 && avgEyebrowThickness > 5) {
                console.log(`🚫 Strong gender mismatch: Face strongly suggests male but name suggests female for ${label}`);
                return false;
            }
            if (isLikelyFemale && nameSuggestsMale && jawRatio < 0.55 && avgEyebrowThickness < 1.5) {
                console.log(`🚫 Strong gender mismatch: Face strongly suggests female but name suggests male for ${label}`);
                return false;
            }
            
            console.log(`✅ Gender validation passed for ${label} (jawRatio: ${jawRatio.toFixed(3)}, eyebrowThickness: ${avgEyebrowThickness.toFixed(3)})`);
        }
        
        return true; // Allow detection if no clear gender mismatch
    } catch (error) {
        console.warn('Gender validation error:', error);
        return true; // Allow detection on error
    }
}

// NEW: Multi-attempt validation for critical decisions
function performMultiAttemptValidation(result, face) {
    try {
        let validationScore = 0;
        let attempts = 0;
        
        // Score 1: Distance-based validation (more lenient)
        if (result.distance < 0.25) validationScore += 0.4;
        else if (result.distance < 0.35) validationScore += 0.35;
        else if (result.distance < 0.45) validationScore += 0.3;
        else if (result.distance < 0.55) validationScore += 0.2; // More lenient
        attempts++;
        
        // Score 2: Quality-based validation (more lenient)
        const quality = assessFaceQuality(face);
        if (quality > 0.8) validationScore += 0.3;
        else if (quality > 0.7) validationScore += 0.25;
        else if (quality > 0.6) validationScore += 0.2;
        else if (quality > 0.5) validationScore += 0.15; // More lenient
        attempts++;
        
        // Score 3: Landmark-based validation (more lenient)
        if (face.landmarks) {
            const landmarkScore = assessEnhancedLandmarkQuality(face.landmarks);
            if (landmarkScore > 0.7) validationScore += 0.3;
            else if (landmarkScore > 0.6) validationScore += 0.25;
            else if (landmarkScore > 0.5) validationScore += 0.2;
            else if (landmarkScore > 0.4) validationScore += 0.15; // More lenient
            attempts++;
        }
        
        const finalScore = attempts > 0 ? validationScore / attempts : 0;
        console.log(`Multi-attempt validation score: ${finalScore.toFixed(3)} (distance: ${result.distance.toFixed(3)}, quality: ${quality.toFixed(3)})`);
        return finalScore;
    } catch (error) {
        console.warn('Multi-attempt validation error:', error);
        return 0.6; // More lenient neutral score on error
    }
}

// Queue system removed for instant processing

let isProcessingRecognition = false;
// Track processed labels to prevent duplicate submissions while tetap melanjutkan deteksi
let processedLabels = new Map(); // nim -> timestamp ms
const processedCooldownMs = 30000; // 30 detik

async function handleRecognition(nim, topExpression){
    if(!scanMode || isProcessingRecognition) return;
    isProcessingRecognition = true;
    
        // Ultra-fast processing - minimal logging
        // console.log('Recognition triggered:', { nim, topExpression, scanMode });
    
    // ULTRA-FAST: Take screenshot and get geolocation in parallel for speed
    const [screenshot, position] = await Promise.all([
        // Screenshot - optimized for speed
        new Promise((resolve) => {
            try {
                if (video && canvas && video.videoWidth > 0 && video.videoHeight > 0) {
                    // Ultra-fast processing - minimal logging
                    // console.log('Taking screenshot...', { videoWidth: video.videoWidth, videoHeight: video.videoHeight });
                    const ctx = canvas.getContext('2d');
                    canvas.width = video.videoWidth;
                    canvas.height = video.videoHeight;
                    // Resize to speed up upload while keeping enough detail for verification
                    const targetW = 240; const scale = targetW / canvas.width; const targetH = Math.round(canvas.height * scale);
                    const tmp = document.createElement('canvas'); const tctx = tmp.getContext('2d');
                    tmp.width = targetW; tmp.height = targetH;
                    // Center-crop from the middle to avoid only-forehead issue on tall mobile cameras
                    const srcW = video.videoWidth;
                    const srcH = video.videoHeight;
                    const aspect = targetW / targetH;
                    let cropW = srcW;
                    let cropH = Math.round(cropW / aspect);
                    if (cropH > srcH) { cropH = srcH; cropW = Math.round(cropH * aspect); }
                    const sx = Math.max(0, Math.floor((srcW - cropW) / 2));
                    const sy = Math.max(0, Math.floor((srcH - cropH) / 2));
                    tctx.drawImage(video, sx, sy, cropW, cropH, 0, 0, targetW, targetH);
                    const screenshot = tmp.toDataURL('image/jpeg', 0.5); // Faster upload, sufficient for bukti
                    // console.log('Screenshot taken successfully, size:', screenshot.length);
                    resolve(screenshot);
                } else {
                    console.warn('Video not ready for screenshot');
                    resolve(null);
                }
            } catch (screenshotError) {
                console.warn('Failed to take screenshot:', screenshotError);
                resolve(null);
            }
        }),
        
        // Geolocation - Ultra fast
        new Promise((resolve) => {
            if (!navigator.geolocation) return resolve(null);
            navigator.geolocation.getCurrentPosition(
                pos => resolve(pos), 
                err => resolve(null), 
                { enableHighAccuracy: true, timeout: 5000, maximumAge: 300000 }
            );
        })
    ]);
    
    // Validate screenshot before proceeding
    if (!screenshot || screenshot.length < 1000) {
        statusMessage('Gagal mengambil screenshot. Silakan coba lagi dengan posisi yang lebih baik.', 'bg-red-100 text-red-700');
        isProcessingRecognition = false;
        return;
    }
    
    // Use position from parallel processing
    let lat=null, lng=null, lokasi='';
    if (position) {
        lat = position.coords.latitude;
        lng = position.coords.longitude;
    }
    // Biarkan backend melakukan reverse geocoding jika lokasi kosong

    async function submitAttendance(extra={}){
        return api('?ajax=save_attendance', { 
            nim,
            mode: scanMode,
            ekspresi: topExpression,
            screenshot: screenshot,
            lat: lat ?? '',
            lng: lng ?? '',
            lokasi: lokasi ?? '',
            ...extra
        }, { suppressModal: true });
    }

    try{
        // Fetch public IP for API-based WFO detection (fast, non-blocking)
        // Dapatkan IP publik tanpa memblokir proses (timeout singkat)
        try {
            const ipFetch = fetch('https://api.ipify.org?format=json', { cache: 'no-store' });
            const timeout = new Promise((_,rej)=> setTimeout(()=>rej(new Error('ip-timeout')), 800));
            const ipResp = await Promise.race([ipFetch, timeout]);
            if (ipResp && ipResp.ok) {
                const ipJson = await ipResp.json();
                window.__publicIp = ipJson?.ip || '';
            }
        } catch {}
        // Store attendance data for potential WFA retry
        const attendanceData = { 
            nim,
            mode: scanMode,
            ekspresi: topExpression,
            screenshot: screenshot,
            lat: lat ?? '',
            lng: lng ?? '',
            lokasi: lokasi ?? '',
            public_ip: (window.__publicIp || '')
        };
        window.pendingAttendanceData = attendanceData;
        
        let r = await submitAttendance();
        if(!r.ok && r.need_reason){
            // Show WFA modal using new system
            showWFAModal(r.message || 'Di luar wilayah kantor. Harap isi alasan kerja di luar (WFA).');
            isProcessingRecognition = false;
            return; // Exit early, WFA modal will handle retry
        }
        // ULTRA-FAST: Skip logging for maximum speed
        
        if(r.ok){
            statusMessage(r.message, r.statusClass || 'bg-green-100 text-green-700');
            // Update log after successful attendance
            updateLogAfterAttendance(nim, scanMode);
            // Tandai label sudah diproses agar tidak dobel, lanjutkan deteksi untuk orang lain
            processedLabels.set(nim, Date.now());
        } else {
            statusMessage(r.message || 'Gagal menyimpan presensi', r.statusClass || 'bg-yellow-100 text-yellow-700');
            // Jika sudah presensi sebelumnya, hentikan deteksi dan berikan notifikasi jelas
            const msg = (r.message||'').toLowerCase();
            if (msg.includes('sudah presensi')) {
                processedLabels.set(nim, Date.now());
            }
        }
    }catch(err){
        console.error('Error in handleRecognition:', err);
        let errorMessage = 'Terjadi kesalahan server';
        if (err.message.includes('invalid JSON')) {
            errorMessage = 'Server mengalami masalah teknis. Silakan coba lagi.';
        } else if (err.message.includes('HTTP error')) {
            errorMessage = 'Koneksi ke server bermasalah. Silakan coba lagi.';
        } else if (err.message.includes('Data yang dikirim tidak valid')) {
            errorMessage = 'Data yang dikirim tidak valid. Silakan coba lagi.';
        } else if (err.message.includes('Server error')) {
            errorMessage = 'Server error. Silakan coba lagi.';
        } else if (err.message.includes('Presensi masuk hanya tersedia') || err.message.includes('Presensi masuk tersedia')) {
            errorMessage = 'Waktu presensi tidak sesuai. Silakan coba pada jam yang tepat.';
        } else if (err.message.includes('Waktu presensi tidak sesuai')) {
            errorMessage = 'Waktu presensi tidak sesuai. Silakan coba pada jam yang tepat.';
        } else if (err.message.includes('NIM tidak ditemukan')) {
            errorMessage = 'NIM tidak ditemukan. Silakan hubungi administrator.';
        } else if (err.message.includes('Database error')) {
            errorMessage = 'Database error. Silakan hubungi administrator.';
        } else if (err.message.includes('Screenshot tidak berhasil diambil')) {
            errorMessage = 'Screenshot tidak berhasil diambil. Silakan coba lagi dengan posisi yang lebih baik.';
        } else if (err.message.includes('Ukuran screenshot terlalu besar')) {
            errorMessage = 'Ukuran screenshot terlalu besar. Silakan coba lagi.';
        } else if (err.message.includes('Database structure error')) {
            errorMessage = 'Database structure error. Silakan hubungi administrator.';
        } else if (err.message.includes('Bad request')) {
            errorMessage = 'Bad request. Silakan coba lagi.';
        } else if (err.message.includes('Unauthorized')) {
            errorMessage = 'Unauthorized. Silakan login kembali.';
        } else if (err.message.includes('Forbidden')) {
            errorMessage = 'Forbidden. Silakan hubungi administrator.';
        } else if (err.message.includes('Tidak dapat terhubung ke server')) {
            errorMessage = 'Tidak dapat terhubung ke server. Pastikan XAMPP sudah berjalan.';
        } else if (err.message.includes('Server tidak merespons')) {
            errorMessage = 'Server tidak merespons. Silakan coba lagi.';
        } else if (err.message.includes('Network error')) {
            errorMessage = 'Network error. Silakan coba lagi.';
        } else if (err.message.includes('Connection refused')) {
            errorMessage = 'Connection refused. Silakan coba lagi.';
        }
        statusMessage(errorMessage, 'bg-red-100 text-red-700');
    } finally {
        // INSTANT: Immediate reset for maximum speed
        isProcessingRecognition = false;
    }
}

function stopVideoAfterRecognition(){
    if(videoInterval) {
        clearInterval(videoInterval);
        videoInterval = null;
    }
    // INSTANT: Much faster reset for better user experience
    let delayDuration = 3000; // Reduced from 10000 to 3000
    if (presensiStatus && presensiStatus.textContent) {
        const currentText = presensiStatus.textContent;
        const wordCount = currentText.split(' ').length;
        delayDuration = Math.max(2000, wordCount * 200 + 1000); // Much faster calculation
    }
    setTimeout(()=>{
        if(isCameraActive) resetPresensiPage();
    }, delayDuration);
}

// Function to reset recognition system for multi-person support
function resetRecognitionSystem() {
    // Clear detection history
    detectionHistory = [];
    
    // Clear recognition queue
    recognitionQueue = [];
    
    // Reset processing flags
    isProcessingRecognition = false;
    isProcessingQueue = false;
    recognitionCompleted = false;
    
    // Reset last successful detection
    lastSuccessfulDetection = null;
    
    console.log('Recognition system reset for multi-person support');
}

// Function to manually stop detection (for admin use)
function stopDetection() {
    if(videoInterval) {
        clearInterval(videoInterval);
        videoInterval = null;
    }
    resetRecognitionSystem();
    console.log('Face detection stopped manually');
}

// Initialize face recognition when page loads
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 Initializing face recognition system...');
    initializeSpeechSynthesis();
    initializeFaceRecognition();
    // Preload models once
    if (!window.faceApiModelsLoaded) {
        loadFaceApiModels().catch(()=>{});
    }
    
    // INSTANT: Immediate debug info display
    console.log('🔧 Face Recognition Debug Info:');
    console.log(`  - Face Matcher Threshold: ${detectionConfig.faceMatcherThreshold}`);
    console.log(`  - Recognition Threshold: ${detectionConfig.recognitionThreshold}`);
    console.log(`  - Quality Threshold: ${detectionConfig.qualityThreshold}`);
    console.log(`  - Score Threshold: ${detectionConfig.scoreThreshold}`);
    console.log(`  - Input Size: ${detectionConfig.inputSize}`);
    console.log(`  - Min Face Size: ${detectionConfig.minFaceSize}`);
    // Reset log data daily
    checkAndResetLogDaily();
});

// Load log presensi masuk
async function loadLogMasuk() {
    try {
        const formData = new FormData();
        formData.append('type', 'masuk');
        const response = await fetch('?ajax=get_today_attendance', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            console.error('HTTP Error:', response.status, response.statusText);
            return;
        }
        
        const result = await response.json();
        console.log('Log masuk response:', result);
        
        if (result.ok) {
            logMasukData = result.data || [];
            console.log('Log masuk data:', logMasukData);
            renderLogMasuk();
        } else {
            console.error('API Error:', result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading log masuk:', error);
    }
}

// Load log presensi pulang
async function loadLogPulang() {
    try {
        const formData = new FormData();
        formData.append('type', 'pulang');
        const response = await fetch('?ajax=get_today_attendance', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            console.error('HTTP Error:', response.status, response.statusText);
            return;
        }
        
        const result = await response.json();
        console.log('Log pulang response:', result);
        
        if (result.ok) {
            logPulangData = result.data || [];
            console.log('Log pulang data:', logPulangData);
            renderLogPulang();
        } else {
            console.error('API Error:', result.error || 'Unknown error');
        }
    } catch (error) {
        console.error('Error loading log pulang:', error);
    }
}

// Render log presensi masuk
function renderLogMasuk() {
    const body = qs('#log-masuk-body');
    if (!body) return;
    
    console.log('Rendering log masuk with data:', logMasukData);
    
    body.innerHTML = '';
    if (logMasukData.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Belum ada presensi masuk hari ini</td></tr>';
        return;
    }
    
    logMasukData.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        
        const screenshot = item.screenshot_masuk ? 
            `<img src="${item.screenshot_masuk}" alt="Screenshot" class="w-16 h-12 object-cover rounded cursor-pointer hover:scale-150 transition-transform mx-auto" onclick="showScreenshotModal('${item.screenshot_masuk}', 'Screenshot Masuk')" title="Klik untuk memperbesar">` :
            '<span class="text-gray-400">-</span>';
        
        const jamMasuk = item.jam_masuk ? item.jam_masuk.substring(0, 5) : '-';
        const tanggal = item.jam_masuk_iso ? new Date(item.jam_masuk_iso).toLocaleDateString('id-ID') : '-';
        const lokasi = item.lokasi_masuk || '-';
        
        tr.innerHTML = `
            <td class="py-2 px-4 text-center">${index + 1}</td>
            <td class="py-2 px-4 text-center">${tanggal}</td>
            <td class="py-2 px-4">${item.nama || '-'}</td>
            <td class="py-2 px-4 text-center">${item.startup || '-'}</td>
            <td class="py-2 px-4 text-center">${jamMasuk}</td>
            <td class="py-2 px-4">${lokasi}</td>
            <td class="py-2 px-4 text-center">${screenshot}</td>
        `;
        body.appendChild(tr);
    });
}

// Render log presensi pulang
function renderLogPulang() {
    const body = qs('#log-pulang-body');
    if (!body) return;
    
    console.log('Rendering log pulang with data:', logPulangData);
    
    body.innerHTML = '';
    if (logPulangData.length === 0) {
        body.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-gray-500">Belum ada presensi pulang hari ini</td></tr>';
        return;
    }
    
    logPulangData.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50';
        
        const screenshot = item.screenshot_pulang ? 
            `<img src="${item.screenshot_pulang}" alt="Screenshot" class="w-16 h-12 object-cover rounded cursor-pointer hover:scale-150 transition-transform mx-auto" onclick="showScreenshotModal('${item.screenshot_pulang}', 'Screenshot Pulang')" title="Klik untuk memperbesar">` :
            '<span class="text-gray-400">-</span>';
        
        const jamPulang = item.jam_pulang ? item.jam_pulang.substring(0, 5) : '-';
        const tanggal = item.jam_pulang_iso ? new Date(item.jam_pulang_iso).toLocaleDateString('id-ID') : '-';
        const lokasi = item.lokasi_pulang || '-';
        
        tr.innerHTML = `
            <td class="py-2 px-4 text-center">${index + 1}</td>
            <td class="py-2 px-4 text-center">${tanggal}</td>
            <td class="py-2 px-4">${item.nama || '-'}</td>
            <td class="py-2 px-4 text-center">${item.startup || '-'}</td>
            <td class="py-2 px-4 text-center">${jamPulang}</td>
            <td class="py-2 px-4">${lokasi}</td>
            <td class="py-2 px-4 text-center">${screenshot}</td>
        `;
        body.appendChild(tr);
    });
}

// Update log after successful attendance
function updateLogAfterAttendance(nim, mode) {
    // INSTANT: Immediate update for maximum speed
    if (mode === 'masuk') {
        loadLogMasuk();
    } else {
        loadLogPulang();
    }
}

// Check and reset log daily
function checkAndResetLogDaily() {
    const today = new Date().toDateString();
    const lastReset = localStorage.getItem('lastLogReset');
    
    if (lastReset !== today) {
        logMasukData = [];
        logPulangData = [];
        localStorage.setItem('lastLogReset', today);
    }
}

<?php else: ?>
// App (logged in)
const pages = { rekap: qs('#page-rekap'), 'laporan-bulanan': qs('#page-laporan-bulanan'), members: qs('#page-members'), laporan: qs('#page-laporan'), 'admin-monthly': qs('#page-admin-monthly'), dashboard: qs('#page-dashboard'), settings: qs('#page-settings') };
qsa('.tab-link').forEach(btn=>{
    btn.addEventListener('click', ()=> showPage(btn.dataset.tab));
});
function showPage(name){ Object.values(pages).forEach(p=> p && (p.style.display='none')); if(pages[name]) pages[name].style.display='block'; if(name==='members') renderMembers(); if(name==='laporan') { loadStartupOptions(); renderLaporan(); } if(name==='rekap') initRekapPage(); if(name==='laporan-bulanan') renderMonthly(); if(name==='admin-monthly') renderAdminMonthly(); if(name==='dashboard') renderDashboard(); if(name==='settings') { renderSettings(); initAddressSearch(); } }

// Ensure initial page sets after variables exist
<?php if (isAdmin()): ?>
showPage('dashboard');
<?php else: ?>
showPage('rekap');
<?php endif; ?>

// Initialize month/year selectors for rekap page
document.addEventListener('DOMContentLoaded', () => {
    const monthSel = qs('#rekap-month');
    const yearSel = qs('#rekap-year');
    
    if (monthSel) {
        const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        months.forEach((month, index) => {
            const option = document.createElement('option');
            option.value = String(index + 1);
            option.textContent = month;
            if (index === new Date().getMonth()) {
                option.selected = true;
            }
            monthSel.appendChild(option);
        });
    }
    
    if (yearSel) {
        const currentYear = new Date().getFullYear();
        for (let year = currentYear - 2; year <= currentYear + 1; year++) {
            const option = document.createElement('option');
            option.value = String(year);
            option.textContent = String(year);
            if (year === currentYear) {
                option.selected = true;
            }
            yearSel.appendChild(option);
        }
    }
    
    // Initialize rekap page on load
    if (qs('#page-rekap')) {
        initRekapPage();
    }
});

// Face recognition functions are handled in the landing page section
// The logged-in app focuses on admin/employee dashboard functionality

// Members (Admin)
async function renderMembers(){
    const res = await fetch('?ajax=get_members'); const j = await res.json(); const members = (j.data||[]);
    const term = (qs('#search-member')?.value||'').toLowerCase();
    const filtered = members.filter(m=> (m.nama||'').toLowerCase().includes(term) || (m.nim||'').toLowerCase().includes(term));
    const body = qs('#table-members-body'); if(!body) return; body.innerHTML='';
    if(filtered.length===0){ body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data member.</td></tr>`; return; }
    filtered.forEach(m=>{
        const tr = document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="py-2 px-4"><img src="${m.foto_base64||''}" alt="Foto ${m.nama||''}" class="h-12 w-12 rounded-full" style="object-fit: contain;"></td>
            <td class="py-2 px-4">${m.nim||''}</td>
            <td class="py-2 px-4">${m.nama||''}</td>
            <td class="py-2 px-4">${m.prodi||''}</td>
            <td class="py-2 px-4">${m.startup||'-'}</td>
            <td class="py-2 px-4 text-center">
                <button class="btn-edit-member text-yellow-600 font-bold" data-id="${m.id}" data-json='${JSON.stringify(m).replace(/'/g,"&apos;")}' title="Edit"><i class="fi fi-sr-pen-square"></i></button>
                <button class="btn-work-schedule text-green-600 font-bold ml-2" data-id="${m.id}" data-name="${m.nama}" title="Kelola Jadwal Kerja"><i class="fi fi-sr-calendar"></i></button>
                <button class="btn-delete-member text-red-600 font-bold ml-2" data-id="${m.id}" title="Hapus"><i class="fi fi-ss-trash"></i></button>
            </td>`;
        body.appendChild(tr);
    });
}

qs('#search-member') && qs('#search-member').addEventListener('input', renderMembers);

const memberModal = qs('#member-modal');
const btnAddMember = qs('#btn-add-member');
const btnCancelModal = qs('#btn-cancel-modal');
const memberForm = qs('#member-form');

const modalVideoContainer = qs('#modal-video-container');
const modalVideo = qs('#modal-video');
const modalCanvas = qs('#modal-canvas');
const btnStartCamera = qs('#btn-start-camera');
const btnTakePhoto = qs('#btn-take-photo');
const btnUploadPhoto = qs('#btn-upload-photo');
const photoFileInput = qs('#photo-file-input');
const fotoPreview = qs('#foto-preview');
const fotoDataUrlInput = qs('#foto-data-url');
let modalStream = null;

function resetModalCamera(){ stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); btnStartCamera.textContent='Buka Kamera untuk Foto'; fotoPreview.classList.add('hidden'); fotoDataUrlInput.value=''; }
function stopModalCamera(){ if(modalStream){ modalStream.getTracks().forEach(t=>t.stop()); modalStream=null; } }

btnStartCamera && btnStartCamera.addEventListener('click', async ()=>{
    try{ modalStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } }); modalVideo.srcObject = modalStream; modalVideoContainer.classList.remove('hidden'); btnTakePhoto.classList.remove('hidden'); btnStartCamera.classList.add('hidden'); fotoPreview.classList.add('hidden'); }catch(err){ showNotif('Tidak bisa mengakses kamera.'); console.error(err); }
});

btnTakePhoto && btnTakePhoto.addEventListener('click', ()=>{
    const ctx = modalCanvas.getContext('2d'); modalCanvas.width = modalVideo.videoWidth; modalCanvas.height = modalVideo.videoHeight; ctx.drawImage(modalVideo,0,0,modalCanvas.width,modalCanvas.height);
    const dataUrl = modalCanvas.toDataURL('image/jpeg'); fotoPreview.src = dataUrl; fotoDataUrlInput.value = dataUrl; fotoPreview.classList.remove('hidden'); stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); btnStartCamera.textContent='Ambil Ulang Foto';
});

btnUploadPhoto && btnUploadPhoto.addEventListener('click', ()=>{
    photoFileInput.click();
});

photoFileInput && photoFileInput.addEventListener('change', (e)=>{
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const dataUrl = e.target.result;
            fotoPreview.src = dataUrl;
            fotoDataUrlInput.value = dataUrl;
            fotoPreview.classList.remove('hidden');
            stopModalCamera();
            modalVideoContainer.classList.add('hidden');
            btnTakePhoto.classList.add('hidden');
            btnStartCamera.classList.remove('hidden');
            btnStartCamera.textContent='Ambil Ulang Foto';
        };
        reader.readAsDataURL(file);
    }
});

btnAddMember && btnAddMember.addEventListener('click', ()=>{
    memberForm.reset(); qs('#modal-title').textContent='Tambah Member Baru'; qs('#member-id').value=''; qs('#nim').readOnly=false; resetModalCamera(); btnStartCamera.textContent='Buka Kamera untuk Foto'; memberModal.classList.remove('hidden'); qs('#password-admin-wrapper').classList.remove('hidden');
});

btnCancelModal && btnCancelModal.addEventListener('click', ()=>{ stopModalCamera(); memberModal.classList.add('hidden'); });

document.addEventListener('click', async (e)=>{
    const btnEdit = e.target.closest('.btn-edit-member');
    const btnDelete = e.target.closest('.btn-delete-member');
    const btnWorkSchedule = e.target.closest('.btn-work-schedule');
    const btnViewDr = e.target.closest('.btn-view-dr-admin');
    const btnEditAtt = e.target.closest('.btn-edit-att');
    const btnDeleteLaporan = e.target.closest('.btn-delete-laporan');
    const btnViewMonth = e.target.closest('.btn-view-month');
    const btnAmApprove = e.target.closest('.btn-am-approve');
    const btnAmDisapprove = e.target.closest('.btn-am-disapprove');
    const btnViewMonthDetail = e.target.closest('.btn-view-month-detail');
    const btnViewKet = e.target.closest('.btn-view-ket');

    if(btnEdit){
        const data = JSON.parse(btnEdit.getAttribute('data-json').replace(/&apos;/g, "'"));
        resetModalCamera();
        qs('#modal-title').textContent='Edit Member';
        qs('#member-id').value = data.id;
        qs('#email').value = data.email || '';
        qs('#nim').value = data.nim || '';
        qs('#nim').readOnly = true;
        qs('#nama').value = data.nama || '';
        qs('#prodi').value = data.prodi || '';
        qs('#startup').value = data.startup || '';
        fotoPreview.src = data.foto_base64 || '';
        if(data.foto_base64) fotoPreview.classList.remove('hidden');
        btnStartCamera.textContent='Ambil Ulang Foto';
        qs('#password-admin-wrapper').classList.add('hidden');
        memberModal.classList.remove('hidden');
    }

    if(btnDelete){
        const id = btnDelete.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus member ini?', async ()=>{
            await api('?ajax=delete_member', { id });
            renderMembers(); 
            if (typeof loadLabeledFaceDescriptors === 'function') {
                loadLabeledFaceDescriptors();
            }
        });
    }

    if(btnWorkSchedule){
        const userId = btnWorkSchedule.getAttribute('data-id');
        const userName = btnWorkSchedule.getAttribute('data-name');
        await openWorkScheduleModal(userId, userName);
    }

    if(btnDeleteLaporan){
        const id = btnDeleteLaporan.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus data kehadiran ini?', async ()=>{ await api('?ajax=delete_attendance', { id }); renderLaporan(); });
    }
    
        if(btnEditAtt){
        const att = JSON.parse(btnEditAtt.getAttribute('data-json').replace(/&apos;/g, "'"));
        qs('#edit-att-id').value = att.id;
        qs('#edit-att-user-id').value = att.user_id || '';
        qs('#edit-att-date').value = (att.jam_masuk_iso||'').slice(0,10);
        qs('#edit-att-nama').value = att.nama || '';
        qs('#edit-att-jam-masuk').value = att.jam_masuk ? att.jam_masuk.substring(0, 5) : '';
        qs('#edit-att-jam-pulang').value = att.jam_pulang ? att.jam_pulang.substring(0, 5) : '';
        qs('#edit-att-ket').value = att.ket || 'hadir';
        qs('#edit-att-status').value = att.status || 'ontime';
        
        // Handle existing screenshots
        if (att.screenshot_masuk) {
            editAttScreenshotMasuk = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-data').value = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-img').src = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-preview').classList.remove('hidden');
        } else {
            editAttScreenshotMasuk = null;
            qs('#edit-att-screenshot-masuk-data').value = '';
            qs('#edit-att-screenshot-masuk-preview').classList.add('hidden');
        }
        
        if (att.screenshot_pulang) {
            editAttScreenshotPulang = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-data').value = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-img').src = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-preview').classList.remove('hidden');
        } else {
            editAttScreenshotPulang = null;
            qs('#edit-att-screenshot-pulang-data').value = '';
            qs('#edit-att-screenshot-pulang-preview').classList.add('hidden');
        }
        
        editAttModal.classList.remove('hidden');
    }

    if(btnViewDr){
        const userId = btnViewDr.getAttribute('data-user'); const date = btnViewDr.getAttribute('data-date');
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date });
        const modal = qs('#dr-modal'); const content=qs('#dr-content'); const evalEl=qs('#dr-evaluation');
        modal.dataset.reportId = r?.data?.id || '';
        content.textContent = r?.data?.content || '(Belum ada laporan)';
        evalEl.value = r?.data?.evaluation || '';
        modal.classList.remove('hidden');
    }
    
        if(btnViewMonthDetail){
        const id = btnViewMonthDetail.getAttribute('data-id');
        const r = await api('?ajax=get_monthly_report_detail', { id });
        if(!r.ok) { showNotif(r.message || 'Laporan tidak ditemukan', false); return; }
        const item = r.data;
        if(!item) { showNotif('Laporan tidak ditemukan', false); return; }
        
        // Create modal if it doesn't exist
        let modal = qs('#monthly-detail-modal');
        if(!modal) {
            modal = document.createElement('div');
            modal.id = 'monthly-detail-modal';
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden';
            modal.innerHTML = `
                <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 id="monthly-detail-title" class="text-xl font-bold"></h3>
                        <button onclick="this.closest('#monthly-detail-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <div class="space-y-6">
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Ringkasan Pekerjaan:</h4>
                            <div class="bg-gray-50 p-3 rounded border">
                                <p id="monthly-detail-summary" class="text-gray-600 whitespace-pre-wrap"></p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Pencapaian dan Hasil Kerja:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Pencapaian</th>
                                            <th class="py-2 px-4">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-detail-achievements-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Kendala:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Kendala</th>
                                            <th class="py-2 px-4">Solusi</th>
                                            <th class="py-2 px-4">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-detail-obstacles-table"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const titleElement = qs('#monthly-detail-title');
        const summaryElement = qs('#monthly-detail-summary');
        
        if (titleElement) {
            titleElement.textContent = `Laporan Bulanan ${item.nama} - ${monthName(parseInt(item.month))} ${item.year}`;
        }
        if (summaryElement) {
            summaryElement.textContent = item.summary || '(Tidak ada ringkasan)';
        }
        
        // Parse achievements properly and fill table
        let achievements = [];
        try {
            achievements = JSON.parse(item.achievements || '[]');
        } catch (e) {
            achievements = [];
        }
        
        const achievementsTable = qs('#monthly-detail-achievements-table');
        if (achievementsTable) {
            if (achievements.length > 0) {
                achievementsTable.innerHTML = achievements.map((a, index) => {
                    const achievement = typeof a === 'object' ? (a.achievement || '') : a;
                    const detail = typeof a === 'object' ? (a.detail || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${achievement}</td>
                            <td class="py-2 px-4">${detail}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                achievementsTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="3" class="py-2 px-4 text-center text-gray-500">Tidak ada data pencapaian</td>
                    </tr>
                `;
            }
        }
        
        // Parse obstacles properly and fill table
        let obstacles = [];
        try {
            obstacles = JSON.parse(item.obstacles || '[]');
        } catch (e) {
            obstacles = [];
        }
        
        const obstaclesTable = qs('#monthly-detail-obstacles-table');
        if (obstaclesTable) {
            if (obstacles.length > 0) {
                obstaclesTable.innerHTML = obstacles.map((o, index) => {
                    const obstacle = typeof o === 'object' ? (o.obstacle || '') : o;
                    const solution = typeof o === 'object' ? (o.solution || '') : '';
                    const note = typeof o === 'object' ? (o.note || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${obstacle}</td>
                            <td class="py-2 px-4">${solution}</td>
                            <td class="py-2 px-4">${note}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                obstaclesTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="4" class="py-2 px-4 text-center text-gray-500">Tidak ada data kendala</td>
                </tr>
            `;
            }
        }
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    // Handle view monthly report for pegawai
    if(btnViewMonth){
        const data = JSON.parse(btnViewMonth.getAttribute('data-json').replace(/&apos;/g, "'"));
        if(!data) { showNotif('Data laporan tidak ditemukan', false); return; }
        
        // Create modal if it doesn't exist
        let modal = qs('#monthly-pegawai-view-modal');
        if(!modal) {
            modal = document.createElement('div');
            modal.id = 'monthly-pegawai-view-modal';
            modal.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50 hidden';
            modal.innerHTML = `
                <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-6xl max-h-[90vh] overflow-y-auto">
                    <div class="flex justify-between items-center mb-4">
                        <h3 id="monthly-pegawai-view-title" class="text-xl font-bold"></h3>
                        <button onclick="this.closest('#monthly-pegawai-view-modal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">✕</button>
                    </div>
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2">Status Laporan:</h4>
                                <div id="monthly-pegawai-view-status" class="text-sm"></div>
                            </div>
                            <div>
                                <h4 class="font-semibold text-gray-700 mb-2">Tanggal Dibuat:</h4>
                                <div id="monthly-pegawai-view-created" class="text-sm text-gray-600"></div>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Ringkasan Pekerjaan:</h4>
                            <div class="bg-gray-50 p-3 rounded border">
                                <p id="monthly-pegawai-view-summary" class="text-gray-600 whitespace-pre-wrap"></p>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Pencapaian dan Hasil Kerja:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Pencapaian</th>
                                            <th class="py-2 px-4">Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-pegawai-view-achievements-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div>
                            <h4 class="font-semibold text-gray-700 mb-2">Kendala:</h4>
                            <div class="overflow-x-auto">
                                <table class="min-w-full bg-white bordered">
                                    <thead class="bg-gray-200">
                                        <tr>
                                            <th class="py-2 px-4">No</th>
                                            <th class="py-2 px-4">Kendala</th>
                                            <th class="py-2 px-4">Solusi</th>
                                            <th class="py-2 px-4">Catatan</th>
                                        </tr>
                                    </thead>
                                    <tbody id="monthly-pegawai-view-obstacles-table"></tbody>
                                </table>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 mt-6">
                            <button onclick="this.closest('#monthly-pegawai-view-modal').classList.add('hidden')" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        const monthName = (m) => ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][m-1];
        
        // Fill modal data
        const titleElement = qs('#monthly-pegawai-view-title');
        const statusElement = qs('#monthly-pegawai-view-status');
        const createdElement = qs('#monthly-pegawai-view-created');
        const summaryElement = qs('#monthly-pegawai-view-summary');
        
        if (titleElement) {
            titleElement.textContent = `Laporan Bulanan - ${monthName(parseInt(data.month))} ${data.year}`;
        }
        
        if (statusElement) {
            const statusMap = {
                'draft': '<span class="badge badge-gray">Draft</span>',
                'belum di approve': '<span class="badge badge-blue">Belum di Approve</span>',
                'approved': '<span class="badge badge-green">Di-approve</span>',
                'disapproved': '<span class="badge badge-red">Tidak di-approve</span>'
            };
            statusElement.innerHTML = statusMap[data.status] || '<span class="badge badge-gray">Unknown</span>';
        }
        
        if (createdElement) {
            const createdDate = new Date(data.created_at || data.updated_at);
            createdElement.textContent = createdDate.toLocaleDateString('id-ID', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }
        
        if (summaryElement) {
            summaryElement.textContent = data.summary || '(Tidak ada ringkasan)';
        }
        
        // Parse achievements and fill table
        let achievements = [];
        try {
            achievements = JSON.parse(data.achievements || '[]');
        } catch (e) {
            achievements = [];
        }
        
        const achievementsTable = qs('#monthly-pegawai-view-achievements-table');
        if (achievementsTable) {
            if (achievements.length > 0) {
                achievementsTable.innerHTML = achievements.map((a, index) => {
                    const achievement = typeof a === 'object' ? (a.achievement || '') : a;
                    const detail = typeof a === 'object' ? (a.detail || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${achievement}</td>
                            <td class="py-2 px-4">${detail}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                achievementsTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="3" class="py-2 px-4 text-center text-gray-500">Tidak ada data pencapaian</td>
                    </tr>
                `;
            }
        }
        
        // Parse obstacles and fill table
        let obstacles = [];
        try {
            obstacles = JSON.parse(data.obstacles || '[]');
        } catch (e) {
            obstacles = [];
        }
        
        const obstaclesTable = qs('#monthly-pegawai-view-obstacles-table');
        if (obstaclesTable) {
            if (obstacles.length > 0) {
                obstaclesTable.innerHTML = obstacles.map((o, index) => {
                    const obstacle = typeof o === 'object' ? (o.obstacle || '') : o;
                    const solution = typeof o === 'object' ? (o.solution || '') : '';
                    const note = typeof o === 'object' ? (o.note || '') : '';
                    return `
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-2 px-4 text-center">${index + 1}</td>
                            <td class="py-2 px-4">${obstacle}</td>
                            <td class="py-2 px-4">${solution}</td>
                            <td class="py-2 px-4">${note}</td>
                        </tr>
                    `;
                }).join('');
            } else {
                obstaclesTable.innerHTML = `
                    <tr class="border-b">
                        <td colspan="4" class="py-2 px-4 text-center text-gray-500">Tidak ada data kendala</td>
                    </tr>
                `;
            }
        }
        
        if (modal) {
            modal.classList.remove('hidden');
        }
    }
    
    if(btnAmApprove){
        const id = btnAmApprove.getAttribute('data-id'); const status = 'approved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }

    if(btnAmDisapprove){
        const id = btnAmDisapprove.getAttribute('data-id'); const status = 'disapproved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }

    if(btnViewKet){
        const att = JSON.parse(btnViewKet.getAttribute('data-json').replace(/&apos;/g, "'"));
        const modal = qs('#ket-detail-modal');
        const title = qs('#ket-detail-title');
        const content = qs('#ket-detail-content');
        
        title.textContent = `Detail ${att.ket.toUpperCase()} - ${att.nama}`;
        
        if (att.ket === 'wfo' || att.ket === 'wfa') {
            // Show location map for WFO/WFA
            let mapContent = '';
            if (att.lat_masuk && att.lng_masuk && att.lokasi_masuk) {
                mapContent = `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Lokasi Presensi Masuk:</h4>
                        <p class="text-sm text-gray-600 mb-2">${att.lokasi_masuk}</p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>Koordinat:</strong> ${att.lat_masuk}, ${att.lng_masuk}
                            </div>
                            <a href="https://www.google.com/maps?q=${att.lat_masuk},${att.lng_masuk}" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                Buka di Google Maps
                            </a>
                        </div>
                    </div>
                `;
            }
            if (att.lat_pulang && att.lng_pulang && att.lokasi_pulang) {
                mapContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Lokasi Presensi Pulang:</h4>
                        <p class="text-sm text-gray-600 mb-2">${att.lokasi_pulang}</p>
                        <div class="bg-gray-100 p-4 rounded-lg">
                            <div class="text-sm text-gray-600 mb-2">
                                <strong>Koordinat:</strong> ${att.lat_pulang}, ${att.lng_pulang}
                            </div>
                            <a href="https://www.google.com/maps?q=${att.lat_pulang},${att.lng_pulang}" target="_blank" class="inline-block bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded text-sm">
                                Buka di Google Maps
                            </a>
                        </div>
                    </div>
                `;
            }
            if (att.ket === 'wfa' && att.alasan_wfa) {
                mapContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Alasan WFA:</h4>
                        <p class="text-sm text-gray-600 p-3 bg-gray-50 rounded">${att.alasan_wfa}</p>
                    </div>
                `;
            }
            content.innerHTML = mapContent || '<p class="text-gray-500">Tidak ada data lokasi</p>';
        } else if (att.ket === 'izin' || att.ket === 'sakit') {
            // Show proof and reason for izin/sakit
            let proofContent = '';
            if (att.bukti_izin_sakit) {
                proofContent = `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Bukti ${att.ket}:</h4>
                        <div class="flex justify-center">
                            <img src="${att.bukti_izin_sakit}" alt="Bukti ${att.ket}" class="max-w-full max-h-96 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">
                        </div>
                    </div>
                `;
            }
            if (att.alasan_izin_sakit) {
                proofContent += `
                    <div class="mb-4">
                        <h4 class="font-semibold mb-2">Keterangan:</h4>
                        <p class="text-sm text-gray-600 p-3 bg-gray-50 rounded">${att.alasan_izin_sakit}</p>
                    </div>
                `;
            }
            content.innerHTML = proofContent || '<p class="text-gray-500">Tidak ada data bukti</p>';
        }
        
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
});

memberForm && memberForm.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = qs('#member-id').value;
    const payload = {
        id,
        email: qs('#email').value,
        nim: qs('#nim').value,
        nama: qs('#nama').value,
        prodi: qs('#prodi').value,
        startup: qs('#startup').value,
        foto: fotoDataUrlInput.value,
    };
    if(!id){ payload.password = qs('#password-new').value; const confirm = qs('#password-confirm').value; if(!payload.password || payload.password!==confirm){ showNotif('Password admin untuk member baru wajib dan harus cocok'); return; } }
    const r = await api('?ajax=save_member', payload);
    if(r.ok){ 
        renderMembers(); 
        if (typeof loadLabeledFaceDescriptors === 'function') {
            loadLabeledFaceDescriptors(); 
        }
        stopModalCamera(); 
        memberModal.classList.add('hidden'); 
    } else { 
        showNotif(r.message||'Gagal menyimpan'); 
    }
});

// Load startup options for filter
async function loadStartupOptions() {
    const filterStartup = qs('#filter-startup');
    if (filterStartup && filterStartup.options.length <= 1) {
        const res = await fetch('?ajax=get_startups');
        const j = await res.json();
        if (j.ok && j.data) {
            j.data.forEach(startup => {
                const o = document.createElement('option');
                o.value = startup;
                o.textContent = startup;
                filterStartup.appendChild(o);
            });
        }
    }
}

// Laporan
async function renderLaporan(){
    const res = await fetch('?ajax=get_attendance'); const j = await res.json(); const list = (j.data||[]);
    const term = (qs('#search-laporan')?.value||'').toLowerCase();
    const startupFilter = qs('#filter-startup')?.value || '';
    const tglMulai = qs('#filter-tanggal-mulai')?.value || '';
    const tglSelesai = qs('#filter-tanggal-selesai')?.value || '';
    const sortBy = qs('#sort-presensi')?.value || 'tanggal-desc';
    
    const filtered = list.filter(a=>{
        const nameMatch = (a.nama||'').toLowerCase().includes(term);
        const nimMatch = (a.nim||'').toLowerCase().includes(term);
        const startupMatch = !startupFilter || (a.startup||'') === startupFilter;
        const recordDate = a.jam_masuk_iso ? a.jam_masuk_iso.slice(0,10) : '';
        const dateMatch = (!tglMulai || recordDate>=tglMulai) && (!tglSelesai || recordDate<=tglSelesai);
        return (nameMatch||nimMatch) && startupMatch && dateMatch;
    });
    
    // Sorting
    filtered.sort((a,b) => {
        switch(sortBy) {
            case 'tanggal-asc':
                return new Date(a.jam_masuk_iso||0) - new Date(b.jam_masuk_iso||0);
            case 'tanggal-desc':
                return new Date(b.jam_masuk_iso||0) - new Date(a.jam_masuk_iso||0);
            case 'jam-masuk-asc':
                return (a.jam_masuk||'').localeCompare(b.jam_masuk||'');
            case 'jam-masuk-desc':
                return (b.jam_masuk||'').localeCompare(a.jam_masuk||'');
            case 'nama-asc':
                return (a.nama||'').localeCompare(b.nama||'');
            case 'nama-desc':
                return (b.nama||'').localeCompare(a.nama||'');
            default:
                return new Date(b.jam_masuk_iso||0) - new Date(a.jam_masuk_iso||0);
        }
    });
    
        const body = qs('#table-laporan-body'); if(!body) return; body.innerHTML='';
    if(filtered.length===0){ body.innerHTML = `<tr><td colspan="12" class="text-center py-4">Tidak ada data kehadiran.</td></tr>`; return; }
    filtered.forEach(att=>{
        const d = new Date(att.jam_masuk_iso);
        const tanggal = isNaN(d.getTime()) ? '-' : d.toLocaleDateString('id-ID', { year:'numeric', month:'long', day:'numeric'});
        const statusClass = att.status === 'terlambat' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
        const statusText = att.status === 'terlambat' ? 'Terlambat' : 'On Time';

        let dailyReportStatus = 'Belum ada laporan';
        let dailyReportClass = 'badge-gray';
        if(att.daily_report_status) {
            dailyReportStatus = att.daily_report_status === 'approved' ? 'Sudah di-approve' : (att.daily_report_status === 'disapproved' ? 'Tidak di-approve' : 'Belum di-approve');
            dailyReportClass = att.daily_report_status === 'approved' ? 'badge-green' : (att.daily_report_status === 'disapproved' ? 'badge-red' : 'badge-blue');
        }

        const tr = document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        
        // Format jam untuk tampilan (hanya jam:menit)
        const formatTime = (timeStr) => {
            if (!timeStr || timeStr === '-') return '-';
            if (timeStr === 'izin' || timeStr === 'sakit' || timeStr === 'wfa') return timeStr;
            // Extract only HH:MM from HH:MM:SS
            return timeStr.substring(0, 5);
        };
        
        const jamMasuk = formatTime(att.jam_masuk);
        const jamPulang = formatTime(att.jam_pulang);
        
        // Create screenshot display functions
        const createScreenshotDisplay = (screenshotData, ekspresi, mode) => {
            if (att.ket === 'izin' || att.ket === 'sakit') {
                return '<div class="text-center">-</div>';
            }
            if (screenshotData) {
                const escapedSrc = screenshotData.replace(/'/g, "\\'");
                return `<div class="text-center"><img src="${screenshotData}" alt="Bukti ${mode}" class="w-16 h-12 object-cover rounded cursor-pointer hover:scale-150 transition-transform mx-auto" onclick="showScreenshotModal('${escapedSrc}', 'Bukti ${mode}')" title="Klik untuk memperbesar"></div>`;
            }
            return `<div class="text-center">${ekspresi || '-'}</div>`;
        };
        
        const buktiMasuk = createScreenshotDisplay(att.screenshot_masuk, att.ekspresi_masuk, 'masuk');
        const buktiPulang = createScreenshotDisplay(att.screenshot_pulang, att.ekspresi_pulang, 'pulang');
        
        // Ket button logic with oval styling and colors
        let ketButton = '';
        if (att.ket && (att.ket === 'wfo' || att.ket === 'wfa' || att.ket === 'izin' || att.ket === 'sakit' || att.ket === 'overtime')) {
            const ketColors = {
                'wfo': 'bg-green-500 hover:bg-green-600 text-white',
                'wfa': 'bg-blue-500 hover:bg-blue-600 text-white', 
                'izin': 'bg-yellow-500 hover:bg-yellow-600 text-white',
                'sakit': 'bg-yellow-500 hover:bg-yellow-600 text-white',
                'overtime': 'bg-emerald-600 hover:bg-emerald-700 text-white'
            };
            const colorClass = ketColors[att.ket] || 'bg-gray-500 hover:bg-gray-600 text-white';
            ketButton = `<button class="btn-view-ket ${colorClass} px-2 py-1 rounded-full text-xs font-medium transition-colors duration-200" data-json='${JSON.stringify(att).replace(/'/g,"&apos;")}' title="Lihat Detail ${att.ket.toUpperCase()}">${att.ket.toUpperCase()}</button>`;
        } else {
            ketButton = '<span class="text-gray-400">-</span>';
        }

        tr.innerHTML = `
            <td class="py-2 px-4">${tanggal}</td>
            <td class="py-2 px-4">${att.nim||''}</td>
            <td class="py-2 px-4">${att.nama||''}</td>
            <td class="py-2 px-4">${att.startup||'-'}</td>
            <td class="py-2 px-4">${jamMasuk}</td>
            <td class="py-2 px-4">${buktiMasuk}</td>
            <td class="py-2 px-4"><span class="badge ${statusClass}">${statusText}</span></td>
            <td class="py-2 px-4">${ketButton}</td>
            <td class="py-2 px-4">${jamPulang}</td>
            <td class="py-2 px-4">${buktiPulang}</td>
            <td class="py-2 px-4"><span class="badge ${dailyReportClass}">${dailyReportStatus}</span></td>
            <td class="py-2 px-4">
                <button title="Lihat Laporan" class="btn-view-dr-admin text-blue-600 font-bold" data-user="${att.user_id}" data-date="${(att.jam_masuk_iso||'').slice(0,10)}"><i class="fi fi-ss-eye"></i></button>
                <button title="Edit" class="btn-edit-att text-yellow-600 font-bold ml-1" data-json='${JSON.stringify(att).replace(/'/g,"&apos;")}'><i class="fi fi-sr-pen-square"></i></button>
                <button title="Hapus" class="btn-delete-laporan text-red-600 font-bold ml-1" data-id="${att.id}"><i class="fi fi-ss-trash"></i></button>
            </td>`;
        body.appendChild(tr);
    });
}

[qs('#search-laporan'), qs('#filter-startup'), qs('#filter-tanggal-mulai'), qs('#filter-tanggal-selesai'), qs('#sort-presensi')].forEach(el=>{ if(el) el.addEventListener('input', renderLaporan); });

qs('#btn-show-all') && qs('#btn-show-all').addEventListener('click', ()=>{
    if(qs('#search-laporan')) qs('#search-laporan').value = '';
    if(qs('#filter-startup')) qs('#filter-startup').value = '';
    if(qs('#filter-tanggal-mulai')) qs('#filter-tanggal-mulai').value = '';
    if(qs('#filter-tanggal-selesai')) qs('#filter-tanggal-selesai').value = '';
    if(qs('#sort-presensi')) qs('#sort-presensi').value = 'tanggal-desc';
    renderLaporan();
});

// Absence modal handlers
qs('#btn-open-absence') && qs('#btn-open-absence').addEventListener('click', async ()=>{
    const modal = qs('#absence-modal');
    const select = qs('#abs-user'); const search = qs('#abs-search');
    const r = await fetch('?ajax=get_members'); const j = await r.json(); const members=(j.data||[]);
    const fill = (term='')=>{ select.innerHTML=''; members.filter(m=> (m.nama||'').toLowerCase().includes(term)|| (m.nim||'').toLowerCase().includes(term)).forEach(m=>{ const o=document.createElement('option'); o.value=m.id; o.textContent=`${m.nama} (${m.nim})`; select.appendChild(o); }); };
    search.oninput = ()=> fill(search.value.toLowerCase()); fill('');
    modal.classList.remove('hidden');
});
// Manual holidays handlers
qs('#btn-manual-holidays') && qs('#btn-manual-holidays').addEventListener('click', async ()=>{
    await renderManualHolidays();
    qs('#manual-holidays-modal').classList.remove('hidden');
});
qs('#mh-close') && qs('#mh-close').addEventListener('click', ()=> qs('#manual-holidays-modal').classList.add('hidden'));

async function renderManualHolidays(){
    const start = new Date(new Date().getFullYear(),0,1).toISOString().slice(0,10);
    const end = new Date(new Date().getFullYear(),11,31).toISOString().slice(0,10);
    const r = await fetch(`?ajax=admin_get_manual_holidays&start=${start}&end=${end}`);
    const j = await r.json();
    const list = j.data||[];
    const body = qs('#mh-body'); body.innerHTML='';
    if(list.length===0){ body.innerHTML = '<tr><td colspan="3" class="text-center py-3">Belum ada data.</td></tr>'; return; }
    list.forEach(it=>{
        const tr=document.createElement('tr'); tr.className='border-b';
        tr.innerHTML = `<td class="py-2 px-3">${it.date}</td><td class="py-2 px-3">${it.name}</td><td class="py-2 px-3 text-center"><button class="mh-del bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded" data-id="${it.id}">Hapus</button></td>`;
        body.appendChild(tr);
    });
}

document.addEventListener('click', async (e)=>{
    if(e.target && e.target.id==='mh-add'){
        const date = qs('#mh-date').value; const name = qs('#mh-name').value.trim();
        if(!date || !name){ showNotif('Isi tanggal dan keterangan', false); return; }
        
        try {
        const r = await api('?ajax=admin_add_manual_holiday', { date, name });
            if(r.ok){ 
                await renderManualHolidays(); 
                qs('#mh-name').value='';
                showNotif('Hari libur berhasil ditambahkan', true);
            } else {
                showNotif(r.message || 'Gagal menambahkan hari libur', false);
                console.error('API Error:', r);
            }
        } catch (error) {
            showNotif('Terjadi kesalahan: ' + error.message, false);
            console.error('Error adding manual holiday:', error);
        }
    }
    if(e.target && e.target.classList.contains('mh-del')){
        const id = e.target.getAttribute('data-id');
        showConfirmModal('Hapus hari libur ini?', async ()=>{ await api('?ajax=admin_delete_manual_holiday', { id }); await renderManualHolidays(); });
    }
});
qs('#abs-cancel') && qs('#abs-cancel').addEventListener('click', ()=> qs('#absence-modal').classList.add('hidden'));
// Add event listener for abs-type change
document.addEventListener('change', (e) => {
    if (e.target.id === 'abs-type') {
        const wfhForm = qs('#abs-wfh-form');
        if (e.target.value === 'wfa') {
            wfhForm.classList.remove('hidden');
        } else {
            wfhForm.classList.add('hidden');
        }
    }
});

qs('#abs-save') && qs('#abs-save').addEventListener('click', async ()=>{
    const payload = {
        user_id: qs('#abs-user').value,
        date: qs('#abs-date').value,
        type: qs('#abs-type').value,
        jam_masuk: qs('#abs-jam-masuk')?.value,
        jam_pulang: qs('#abs-jam-pulang')?.value
    };
    const r = await api('?ajax=admin_add_absence', payload);
    if(r.ok){
        qs('#absence-modal').classList.add('hidden');
        renderLaporan();
    } else {
        showNotif(r.message||'Gagal simpan');
    }
});

// Update WFA locations button handler
qs('#btn-update-wfa-locations') && qs('#btn-update-wfa-locations').addEventListener('click', async ()=>{
    showConfirmModal('Apakah Anda yakin ingin memperbarui semua lokasi WFA yang masih dalam bentuk koordinat menjadi nama jalan? Proses ini mungkin memakan waktu beberapa saat.', async () => {
    
    const button = qs('#btn-update-wfa-locations');
    const originalText = button.textContent;
    button.textContent = 'Memproses...';
    button.disabled = true;
    
    try {
        const r = await api('?ajax=admin_update_wfa_locations', {});
        if (r.ok) {
            showNotif(r.message || 'Lokasi WFA berhasil diperbarui', true);
            renderLaporan(); // Refresh the table
        } else {
            showNotif(r.message || 'Gagal memperbarui lokasi WFA', false);
        }
    } catch (error) {
        showNotif('Terjadi kesalahan saat memperbarui lokasi WFA', false);
        console.error('Error updating WFA locations:', error);
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
    });
});

// Backup management handlers
qs('#btn-create-backup') && qs('#btn-create-backup').addEventListener('click', async ()=>{
    showConfirmModal('Apakah Anda yakin ingin membuat backup database? Proses ini mungkin memakan waktu beberapa saat.', async () => {
    
    const button = qs('#btn-create-backup');
    const originalText = button.textContent;
    button.textContent = 'Membuat Backup...';
    button.disabled = true;
    
    try {
        const r = await api('?ajax=create_backup', {});
        if (r.ok) {
            showNotif(r.message || 'Backup berhasil dibuat', true);
        } else {
            showNotif(r.message || 'Gagal membuat backup', false);
        }
    } catch (error) {
        showNotif('Terjadi kesalahan saat membuat backup', false);
        console.error('Error creating backup:', error);
    } finally {
        button.textContent = originalText;
        button.disabled = false;
    }
    });
});

qs('#btn-backup-status') && qs('#btn-backup-status').addEventListener('click', async ()=>{
    try {
        const r = await api('?ajax=get_backup_status', {});
        if (r.ok && r.data) {
            const data = r.data;
            let message = '';
            
            if (data.exists) {
                message = `Backup tersedia:\n`;
                message += `File: ${data.file}\n`;
                message += `Ukuran: ${data.size_formatted}\n`;
                message += `Dibuat: ${data.created}`;
            } else {
                message = 'Tidak ada file backup tersedia';
            }
            
            showNotif(message, false);
        } else {
            showNotif(r.message || 'Gagal mendapatkan status backup', false);
        }
    } catch (error) {
        showNotif('Terjadi kesalahan saat mendapatkan status backup', false);
        console.error('Error getting backup status:', error);
    }
});

// Daily report review modal
qs('#dr-close') && qs('#dr-close').addEventListener('click', ()=> qs('#dr-modal').classList.add('hidden'));
qs('#dr-approve') && qs('#dr-approve').addEventListener('click', ()=> handleDrApproveDisapprove('approved'));
qs('#dr-disapprove') && qs('#dr-disapprove').addEventListener('click', ()=> handleDrApproveDisapprove('disapproved'));
async function handleDrApproveDisapprove(status){
    const id = qs('#dr-modal').dataset.reportId; const evaluation = qs('#dr-evaluation').value;
    if(!id){ showNotif('Tidak ada laporan.'); return; }
    showConfirmModal('Yakin '+(status==='approved'?'approve':'disapprove')+'?', async ()=>{
        const r = await api('?ajax=admin_set_daily_status', { id, status, evaluation });
        if(r.ok){ qs('#dr-modal').classList.add('hidden'); renderLaporan(); } else { showNotif(r.message||'Gagal'); }
    });
}

const editAttModal = qs('#edit-att-modal');
qs('#edit-att-cancel') && qs('#edit-att-cancel').addEventListener('click', ()=> editAttModal.classList.add('hidden'));

// Handle screenshot upload for edit attendance modal
let editAttScreenshotMasuk = null;
let editAttScreenshotPulang = null;

// Upload screenshot masuk
qs('#edit-att-upload-masuk') && qs('#edit-att-upload-masuk').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                editAttScreenshotMasuk = e.target.result;
                qs('#edit-att-screenshot-masuk-data').value = editAttScreenshotMasuk;
                qs('#edit-att-screenshot-masuk-img').src = editAttScreenshotMasuk;
                qs('#edit-att-screenshot-masuk-preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    };
    input.click();
});

// Upload screenshot pulang
qs('#edit-att-upload-pulang') && qs('#edit-att-upload-pulang').addEventListener('click', () => {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/*';
    input.onchange = (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                editAttScreenshotPulang = e.target.result;
                qs('#edit-att-screenshot-pulang-data').value = editAttScreenshotPulang;
                qs('#edit-att-screenshot-pulang-img').src = editAttScreenshotPulang;
                qs('#edit-att-screenshot-pulang-preview').classList.remove('hidden');
            };
            reader.readAsDataURL(file);
        }
    };
    input.click();
});

// Remove screenshot masuk
qs('#edit-att-remove-masuk') && qs('#edit-att-remove-masuk').addEventListener('click', () => {
    editAttScreenshotMasuk = null;
    qs('#edit-att-screenshot-masuk-data').value = '';
    qs('#edit-att-screenshot-masuk-preview').classList.add('hidden');
});

// Remove screenshot pulang
qs('#edit-att-remove-pulang') && qs('#edit-att-remove-pulang').addEventListener('click', () => {
    editAttScreenshotPulang = null;
    qs('#edit-att-screenshot-pulang-data').value = '';
    qs('#edit-att-screenshot-pulang-preview').classList.add('hidden');
});
qs('#edit-att-form') && qs('#edit-att-form').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = qs('#edit-att-id').value;
    const jam_masuk = qs('#edit-att-jam-masuk').value || '';
    const jam_pulang = qs('#edit-att-jam-pulang').value || '';
    const ket = qs('#edit-att-ket').value || '';
    const status = qs('#edit-att-status').value || '';
    const screenshot_masuk = qs('#edit-att-screenshot-masuk-data').value || '';
    const screenshot_pulang = qs('#edit-att-screenshot-pulang-data').value || '';
    
    // Add seconds to time values
    const jam_masuk_with_seconds = jam_masuk ? jam_masuk + ':00' : '';
    const jam_pulang_with_seconds = jam_pulang ? jam_pulang + ':00' : '';
    
    const r = await api('?ajax=admin_update_attendance', { 
        id, 
        jam_masuk: jam_masuk_with_seconds, 
        jam_pulang: jam_pulang_with_seconds, 
        ket, 
        status,
        screenshot_masuk,
        screenshot_pulang
    });
    showNotif(r.ok ? 'Berhasil disimpan.' : (r.message || 'Gagal menyimpan'), r.ok);
    if(r.ok){ editAttModal.classList.add('hidden'); renderLaporan(); }
});

// Event listener untuk tombol "Tambahkan Laporan"
qs('#edit-att-add-report') && qs('#edit-att-add-report').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const nama = qs('#edit-att-nama').value;
    
    if (!userId || !date) {
        showNotif('Data tidak lengkap', false);
        return;
    }
    
    // Set info di modal laporan harian
    qs('#admin-dr-nama').textContent = nama;
    qs('#admin-dr-date').textContent = new Date(date).toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
    
    // Cek apakah sudah ada laporan
    try {
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date: date });
        if (r.ok && r.data && r.data.content) {
            qs('#admin-dr-content').value = r.data.content;
        } else {
            qs('#admin-dr-content').value = '';
        }
    } catch (error) {
        console.error('Error checking daily report:', error);
        qs('#admin-dr-content').value = '';
    }
    
    // Sembunyikan modal edit kehadiran dan tampilkan modal laporan harian
    editAttModal.classList.add('hidden');
    qs('#admin-daily-report-modal').classList.remove('hidden');
});

// Event listener untuk modal laporan harian admin
qs('#admin-dr-cancel') && qs('#admin-dr-cancel').addEventListener('click', ()=>{
    qs('#admin-daily-report-modal').classList.add('hidden');
    editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
});

qs('#admin-dr-save') && qs('#admin-dr-save').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const content = qs('#admin-dr-content').value;
    
    if (!content.trim()) {
        showNotif('Isi laporan tidak boleh kosong', false);
        return;
    }
    
    try {
        const r = await api('?ajax=admin_save_daily_report', { 
            user_id: userId, 
            date: date, 
            content: content 
        });
        
        if (r.ok) {
            showNotif('Laporan harian berhasil disimpan');
            qs('#admin-daily-report-modal').classList.add('hidden');
            editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
        } else {
            showNotif(r.message || 'Gagal menyimpan laporan', false);
        }
    } catch (error) {
        console.error('Error saving daily report:', error);
        showNotif('Terjadi kesalahan saat menyimpan', false);
    }
});

// Event listener untuk tombol "Tambahkan Laporan"
qs('#edit-att-add-report') && qs('#edit-att-add-report').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const nama = qs('#edit-att-nama').value;
    
    if (!userId || !date) {
        showNotif('Data tidak lengkap', false);
        return;
    }
    
    // Set info di modal laporan harian
    qs('#admin-dr-nama').textContent = nama;
    qs('#admin-dr-date').textContent = new Date(date).toLocaleDateString('id-ID', { 
        day: '2-digit', 
        month: 'long', 
        year: 'numeric' 
    });
    
    // Cek apakah sudah ada laporan
    try {
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date: date });
        if (r.ok && r.data && r.data.content) {
            qs('#admin-dr-content').value = r.data.content;
        } else {
            qs('#admin-dr-content').value = '';
        }
    } catch (error) {
        console.error('Error checking daily report:', error);
        qs('#admin-dr-content').value = '';
    }
    
    // Sembunyikan modal edit kehadiran dan tampilkan modal laporan harian
    editAttModal.classList.add('hidden');
    qs('#admin-daily-report-modal').classList.remove('hidden');
});

// Event listener untuk modal laporan harian admin
qs('#admin-dr-cancel') && qs('#admin-dr-cancel').addEventListener('click', ()=>{
    qs('#admin-daily-report-modal').classList.add('hidden');
    editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
});

qs('#admin-dr-save') && qs('#admin-dr-save').addEventListener('click', async ()=>{
    const userId = qs('#edit-att-user-id').value;
    const date = qs('#edit-att-date').value;
    const content = qs('#admin-dr-content').value;
    
    if (!content.trim()) {
        showNotif('Isi laporan tidak boleh kosong', false);
        return;
    }
    
    try {
        const r = await api('?ajax=admin_save_daily_report', { 
            user_id: userId, 
            date: date, 
            content: content 
        });
        
        if (r.ok) {
            showNotif('Laporan harian berhasil disimpan');
            qs('#admin-daily-report-modal').classList.add('hidden');
            editAttModal.classList.remove('hidden'); // Kembali ke modal edit kehadiran
        } else {
            showNotif(r.message || 'Gagal menyimpan laporan', false);
        }
    } catch (error) {
        console.error('Error saving daily report:', error);
        showNotif('Terjadi kesalahan saat menyimpan', false);
    }
});

document.addEventListener('click', async (e)=>{
    if(e.target.classList.contains('btn-delete-laporan')){
        const id = e.target.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus data kehadiran ini?', async ()=>{ await api('?ajax=delete_attendance', { id }); renderLaporan(); });
    }
    if(e.target.classList.contains('btn-edit-att')){
        const att = JSON.parse(e.target.getAttribute('data-json').replace(/&apos;/g, "'"));
        qs('#edit-att-id').value = att.id;
        qs('#edit-att-user-id').value = att.user_id || '';
        qs('#edit-att-date').value = (att.jam_masuk_iso||'').slice(0,10);
        qs('#edit-att-nama').value = att.nama || '';
        qs('#edit-att-jam-masuk').value = att.jam_masuk ? att.jam_masuk.substring(0, 5) : '';
        qs('#edit-att-jam-pulang').value = att.jam_pulang ? att.jam_pulang.substring(0, 5) : '';
        qs('#edit-att-ket').value = att.ket || 'hadir';
        qs('#edit-att-status').value = att.status || 'ontime';
        
        // Handle existing screenshots
        if (att.screenshot_masuk) {
            editAttScreenshotMasuk = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-data').value = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-img').src = att.screenshot_masuk;
            qs('#edit-att-screenshot-masuk-preview').classList.remove('hidden');
        } else {
            editAttScreenshotMasuk = null;
            qs('#edit-att-screenshot-masuk-data').value = '';
            qs('#edit-att-screenshot-masuk-preview').classList.add('hidden');
        }
        
        if (att.screenshot_pulang) {
            editAttScreenshotPulang = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-data').value = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-img').src = att.screenshot_pulang;
            qs('#edit-att-screenshot-pulang-preview').classList.remove('hidden');
        } else {
            editAttScreenshotPulang = null;
            qs('#edit-att-screenshot-pulang-data').value = '';
            qs('#edit-att-screenshot-pulang-preview').classList.add('hidden');
        }
        
        editAttModal.classList.remove('hidden');
    }
    if(e.target.classList.contains('btn-view-dr-admin')){
        const userId = e.target.getAttribute('data-user'); const date = e.target.getAttribute('data-date');
        const r = await api('?ajax=get_daily_report_detail', { user_id: userId, date });
        const modal = qs('#dr-modal'); const content=qs('#dr-content'); const evalEl=qs('#dr-evaluation');
        modal.dataset.reportId = r?.data?.id || '';
        content.textContent = r?.data?.content || '(Belum ada laporan)';
        evalEl.value = r?.data?.evaluation || '';
        modal.classList.remove('hidden');
    }
});

function showWFAModal(message) {
    // Create WFA modal if it doesn't exist
    let wfaModal = document.getElementById('wfaModal');
    if (!wfaModal) {
        wfaModal = document.createElement('div');
        wfaModal.id = 'wfaModal';
        wfaModal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden';
        wfaModal.innerHTML = `
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">Work From Anywhere (WFA)</h3>
                <p class="text-gray-600 mb-4">${message}</p>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Alasan WFA:</label>
                    <textarea id="wfaReason" class="w-full p-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" rows="3" placeholder="Masukkan alasan kerja di luar kantor..."></textarea>
                </div>
                <div class="flex space-x-3">
                    <button id="wfaSubmit" class="flex-1 bg-indigo-600 text-white py-2 px-4 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Submit
                    </button>
                    <button id="wfaCancel" class="flex-1 bg-gray-300 text-gray-700 py-2 px-4 rounded-lg hover:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-gray-500">
                        Batal
                    </button>
                </div>
            </div>
        `;
        document.body.appendChild(wfaModal);
        
        // Add event listeners
        document.getElementById('wfaSubmit').addEventListener('click', () => {
            const reason = document.getElementById('wfaReason').value.trim();
            if (reason) {
                wfaModal.classList.add('hidden');
                // Store WFA reason for next attendance submission
                window.pendingWFAReson = reason;
                // Retry attendance submission
                if (window.pendingAttendanceData) {
                    submitAttendanceWithWFA(window.pendingAttendanceData, reason);
                }
            } else {
                showNotif('Harap isi alasan WFA terlebih dahulu.', false);
            }
        });
        
        document.getElementById('wfaCancel').addEventListener('click', () => {
            wfaModal.classList.add('hidden');
            window.pendingWFAReson = null;
            window.pendingAttendanceData = null;
        });
    }
    
    // Show modal
    wfaModal.classList.remove('hidden');
    document.getElementById('wfaReason').focus();
}

function submitAttendanceWithWFA(attendanceData, wfaReason) {
    // Add WFA reason to attendance data
    const dataWithWFA = {
        ...attendanceData,
        wfa_reason: wfaReason,
        is_wfa: true
    };
    
    // Submit attendance with WFA reason
    api('?ajax=save_attendance', dataWithWFA)
        .then(response => {
            if (response.ok) {
                statusMessage('Presensi berhasil dengan alasan WFA!', 'bg-green-100 text-green-700');
                // Clear pending data
                window.pendingWFAReson = null;
                window.pendingAttendanceData = null;
                isProcessingRecognition = false;
            } else {
                statusMessage('Gagal menyimpan presensi: ' + (response.message || 'Unknown error'), 'bg-red-100 text-red-700');
                isProcessingRecognition = false;
            }
        })
        .catch(error => {
            console.error('Error submitting attendance with WFA:', error);
            statusMessage('Terjadi kesalahan saat menyimpan presensi.', 'bg-red-100 text-red-700');
            isProcessingRecognition = false;
        });
}

function showConfirmModal(message, cb){
    const modal=qs('#confirm-modal');
    qs('#confirm-modal-message').textContent=message;
    onConfirmCallback=cb;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}
qs('#btn-confirm-yes') && qs('#btn-confirm-yes').addEventListener('click', ()=>{
    if(typeof onConfirmCallback==='function') onConfirmCallback();
    qs('#confirm-modal').classList.add('hidden');
    qs('#confirm-modal').classList.remove('flex');
    onConfirmCallback=null;
});
qs('#btn-confirm-no') && qs('#btn-confirm-no').addEventListener('click', ()=>{
    qs('#confirm-modal').classList.add('hidden');
    qs('#confirm-modal').classList.remove('flex');
    onConfirmCallback=null;
});

// Pegawai app: setup Rekap and Monthly pages
const pageMonthlyList = qs('#page-laporan-bulanan');
const pageMonthlyForm = qs('#page-monthly-form');

function addAchievementRow(data = { achievement: '', detail: '' }) {
    const body = qs('#table-achievements-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.achievement}" placeholder="Capaian..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.detail}" placeholder="Detail capaian..."></td>
        <td class="p-1 text-center"><button type="button" class="btn-delete-row text-red-500 font-bold">Hapus</button></td>
    `;
    body.appendChild(tr);
}

function addObstacleRow(data = { obstacle: '', solution: '', note: '' }) {
    const body = qs('#table-obstacles-body');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.obstacle}" placeholder="Kendala..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.solution}" placeholder="Solusi..."></td>
        <td class="p-1"><input type="text" class="w-full p-2 border rounded" value="${data.note}" placeholder="Catatan..."></td>
        <td class="p-1 text-center"><button type="button" class="btn-delete-row text-red-500 font-bold">Hapus</button></td>
    `;
    body.appendChild(tr);
}

// Event listeners untuk tombol tambah baris
qs('#btn-add-achievement').addEventListener('click', () => addAchievementRow());
qs('#btn-add-obstacle').addEventListener('click', () => addObstacleRow());

// Event listener untuk hapus baris (delegation)
pageMonthlyForm.addEventListener('click', e => {
    if (e.target.classList.contains('btn-delete-row')) {
        e.target.closest('tr').remove();
    }
});

// Kembali ke daftar
qs('#btn-back-to-monthly-list').addEventListener('click', () => {
    pageMonthlyForm.classList.add('hidden');
    pageMonthlyList.scrollIntoView({ behavior: 'smooth', block: 'start' });
});

// Fungsi untuk menyimpan laporan (baik draft maupun submit)
async function saveMonthlyReport(isSubmit) {
    const year = qs('#monthly-report-year').value;
    const month = qs('#monthly-report-month').value;
    const summary = qs('#monthly-summary').value;

    const achievements = qsa('#table-achievements-body tr').map(tr => {
        const inputs = tr.querySelectorAll('input');
        return { achievement: inputs[0].value, detail: inputs[1].value };
    }).filter(item => item.achievement || item.detail);

    const obstacles = qsa('#table-obstacles-body tr').map(tr => {
        const inputs = tr.querySelectorAll('input');
        return { obstacle: inputs[0].value, solution: inputs[1].value, note: inputs[2].value };
    }).filter(item => item.obstacle || item.solution || item.note);

    const payload = {
        year: parseInt(year),
        month: parseInt(month),
        summary,
        achievements: JSON.stringify(achievements),
        obstacles: JSON.stringify(obstacles),
        submit: isSubmit
    };
    
    const r = await api('?ajax=save_monthly_report', payload);
    if (r.ok) {
        showNotif(isSubmit ? 'Laporan berhasil disubmit!' : 'Laporan berhasil disimpan sebagai draft.');
        pageMonthlyForm.classList.add('hidden');
        pageMonthlyList.scrollIntoView({ behavior: 'smooth', block: 'start' });
        renderMonthly(); // Refresh list
    } else {
        showNotif(r.message || 'Gagal menyimpan laporan.');
    }
}

qs('#btn-save-draft').addEventListener('click', () => saveMonthlyReport(false));
qs('#form-monthly-report').addEventListener('submit', (e) => {
    e.preventDefault();
    saveMonthlyReport(true);
});
// --- End Monthly Report Form Logic ---

function getWeekNumberInMonth(date) {
    const d = new Date(date);
    d.setHours(0, 0, 0, 0);
    const firstDayOfMonth = new Date(d.getFullYear(), d.getMonth(), 1);
    const firstDayOfWeek = firstDayOfMonth.getDay();
    const offsetDays = firstDayOfWeek === 0 ? 6 : firstDayOfWeek - 1; // Monday = 0, Sunday = 6
    const weekNumber = Math.ceil((d.getDate() + offsetDays) / 7);
    return weekNumber;
}

// Flag to prevent multiple calls
let isInitRekapRunning = false;

async function initRekapPage() {
    if (isInitRekapRunning) {
        console.log('initRekapPage already running, skipping...');
        return;
    }
    
    isInitRekapRunning = true;
    const m = parseInt(qs('#rekap-month')?.value || String(new Date().getMonth() + 1));
    const y = parseInt(qs('#rekap-year')?.value || String(new Date().getFullYear()));
    console.log('Loading rekap for month:', m, 'year:', y);
    const r = await api('?ajax=get_rekap', { month: m, year: y });
    console.log('Rekap data:', r);

    const weekSel = qs('#rekap-week');
    if (weekSel) {
        weekSel.innerHTML = '';
        weekSel.classList.add('hidden');
        if (r.ok && r.data.length > 0) {
            const datesInMonth = r.data.map(d => new Date(d.date));
            const weeks = [...new Set(datesInMonth.map(d => getWeekNumberInMonth(d)))].sort((a, b) => a - b);
            console.log('Available weeks:', weeks);
            if (weeks.length >= 1) {
                // Always show week selector if there's data
                if (weeks.length > 1) {
                    // Add "All Weeks" option only if there are multiple weeks
                    const allOption = document.createElement('option');
                    allOption.value = '0';
                    allOption.textContent = 'Semua Minggu';
                    weekSel.appendChild(allOption);
                }
                
                weeks.forEach(w => {
                    const option = document.createElement('option');
                    option.value = w;
                    option.textContent = `Minggu ke-${w}`;
                    weekSel.appendChild(option);
                });
                weekSel.classList.remove('hidden');
                
                // Set default to current week if we're viewing current month and year
                const currentWeek = getWeekNumberInMonth(new Date());
                const currentMonth = new Date().getMonth() + 1;
                const currentYear = new Date().getFullYear();
                
                // Always set to current week if we're viewing current month and year
                if (m === currentMonth && y === currentYear) {
                    weekSel.value = currentWeek;
                    console.log('Setting default week to:', currentWeek);
                    console.log('Week selector value after setting:', weekSel.value);
                } else {
                    // For other months, set to first available week
                    if (weeks.length > 0) {
                        weekSel.value = weeks[0];
                        console.log('Setting to first available week:', weeks[0]);
                        console.log('Week selector value after setting:', weekSel.value);
                    }
                }
            }
        }
    }

    // Get selected week (use current week as default if no selection)
    const currentWeek = getWeekNumberInMonth(new Date());
    let selectedWeek = parseInt(qs('#rekap-week')?.value || currentWeek);
    
    // If week selector is hidden or no value, show all data
    if (!qs('#rekap-week') || qs('#rekap-week').classList.contains('hidden') || !qs('#rekap-week').value) {
        selectedWeek = 0; // Show all weeks
    }
    
    // Debug logging
    console.log('Current week:', currentWeek);
    console.log('Selected week:', selectedWeek);
    console.log('Week selector value:', qs('#rekap-week')?.value);
    console.log('Current month:', new Date().getMonth() + 1, 'Selected month:', m);
    console.log('Current year:', new Date().getFullYear(), 'Selected year:', y);
    
    const body = qs('#table-rekap-body');
    if (!body) {
        isInitRekapRunning = false;
        return;
    }
    body.innerHTML = '';
    if (!r.ok || !r.data || r.data.length === 0) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data.</td></tr>`;
        isInitRekapRunning = false;
        return;
    }

    // Store current data globally for week filtering
    window.currentRekapData = r.data;
    
    // Render the data
    renderRekapData(r.data, m, y);
    
    // Load KPI data for employee
    loadEmployeeKPIData();
    
    // Reset flag
    isInitRekapRunning = false;
}

// Load KPI data for employee
async function loadEmployeeKPIData() {
    try {
        const response = await fetch('?ajax=get_kpi_data');
        const result = await response.json();
        
        if (result.ok && result.data) {
            renderEmployeeKPIChart(result.data);
        } else {
            console.error('Failed to load KPI data:', result.message);
        }
    } catch (error) {
        console.error('Error loading KPI data:', error);
    }
}

// Render KPI chart for employee
function renderEmployeeKPIChart(kpiData) {
    const ctx = qs('#kpi-chart');
    const summary = qs('#kpi-summary');
    
    if (!ctx || !summary) return;
    
    // Destroy existing chart if it exists
    if (window.employeeKPIChart) {
        try {
            window.employeeKPIChart.destroy();
        } catch (e) {
            console.log('Chart destroy error (ignored):', e);
        }
        window.employeeKPIChart = null;
    }
    
    // Create bar chart data
    const labels = ['Ontime', 'Terlambat', 'Izin/Sakit', 'Alpha', 'Overtime'];
    const data = [
        kpiData.ontime_count || 0,
        kpiData.late_count || 0,
        kpiData.izin_sakit_count || 0,
        kpiData.alpha_count || 0,
        kpiData.overtime_count || 0
    ];
    
    const colors = [
        '#22c55e', // Green for ontime
        '#ef4444', // Red for late
        '#eab308', // Yellow for izin/sakit
        '#6b7280', // Gray for alpha
        '#10b981'  // Emerald for overtime
    ];
    
    window.employeeKPIChart = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Hari',
                data: data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `KPI Score: ${kpiData.kpi_score}% - ${kpiData.status}`
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            }
        }
    });
    
    // Update summary cards
    summary.innerHTML = `
        <div class="bg-green-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-green-600">${kpiData.ontime_count || 0}</div>
            <div class="text-sm text-green-700">Ontime</div>
        </div>
        <div class="bg-red-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-red-600">${kpiData.late_count || 0}</div>
            <div class="text-sm text-red-700">Terlambat</div>
        </div>
        <div class="bg-yellow-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-yellow-600">${kpiData.izin_sakit_count || 0}</div>
            <div class="text-sm text-yellow-700">Izin/Sakit</div>
        </div>
        <div class="bg-gray-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-gray-600">${kpiData.alpha_count || 0}</div>
            <div class="text-sm text-gray-700">Alpha</div>
        </div>
        <div class="bg-emerald-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-emerald-600">${kpiData.overtime_count || 0}</div>
            <div class="text-sm text-emerald-700">Overtime</div>
        </div>
        <div class="bg-indigo-100 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-indigo-600">${kpiData.kpi_score || 0}%</div>
            <div class="text-sm text-indigo-700">KPI Score</div>
        </div>
    `;
}

function renderRekapData(data, m, y) {
    // Get selected week (use current week as default if no selection)
    const currentWeek = getWeekNumberInMonth(new Date());
    let selectedWeek = parseInt(qs('#rekap-week')?.value || currentWeek);
    
    // If week selector is hidden or no value, show all data
    if (!qs('#rekap-week') || qs('#rekap-week').classList.contains('hidden') || !qs('#rekap-week').value) {
        selectedWeek = 0; // Show all weeks
    }
    
    // Debug logging
    console.log('Current week:', currentWeek);
    console.log('Selected week:', selectedWeek);
    console.log('Week selector value:', qs('#rekap-week')?.value);
    console.log('Current month:', new Date().getMonth() + 1, 'Selected month:', m);
    console.log('Current year:', new Date().getFullYear(), 'Selected year:', y);
    
    const body = qs('#table-rekap-body');
    if (!body) {
        return;
    }
    body.innerHTML = '';
    if (!data || data.length === 0) {
        body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data.</td></tr>`;
        return;
    }

    // Show data for selected week, or all data if no week selector
    let dataToShow = data;
    if (selectedWeek > 0) {
        dataToShow = data.filter(row => {
            const rowWeek = getWeekNumberInMonth(new Date(row.date));
            console.log('Row date:', row.date, 'Row week:', rowWeek, 'Selected week:', selectedWeek);
            return rowWeek === selectedWeek;
        });
    } else {
        // Show all data when "Semua Minggu" is selected or no week selector
        dataToShow = data;
    }

    // Calculate past 5 working days (excluding weekends) - only for current month/year
    const today = new Date();
    const currentMonth = today.getMonth() + 1;
    const currentYear = today.getFullYear();
    const past5WorkingDays = [];
    
    // Only calculate past 5 days if we're viewing current month and year
    if (m === currentMonth && y === currentYear) {
        let tempDate = new Date(today);
        let workingDaysFound = 0;
        
        while (workingDaysFound < 5) {
            const dayOfWeek = tempDate.getDay();
            if (dayOfWeek !== 0 && dayOfWeek !== 6) { // Not Sunday (0) and not Saturday (6)
                past5WorkingDays.push(tempDate.toISOString().slice(0, 10));
                workingDaysFound++;
            }
            tempDate.setDate(tempDate.getDate() - 1);
        }
    }
    


    if (dataToShow.length === 0) {
        if (selectedWeek > 0) {
            body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data untuk minggu ke-${selectedWeek}.</td></tr>`;
        } else {
            body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data untuk periode ini.</td></tr>`;
        }
        return;
    }

    dataToShow.forEach(row => {
        const d = new Date(row.date);
        const tanggal = d.toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' });
        const dayMap = { Monday: 'Senin', Tuesday: 'Selasa', Wednesday: 'Rabu', Thursday: 'Kamis', Friday: 'Jumat' };
        const day = dayMap[d.toLocaleDateString('en-US', { weekday: 'long' })] || '';
        const dr = row.daily_report;
        let reportBtns = '';
        
        // Check if attendance is complete (has entry time or is WFH)
        const hasEntryTime = row.jam_masuk && row.jam_masuk !== '-';
        const isWFH = row.ket === 'wfh';
        const isAttendanceComplete = hasEntryTime || isWFH;
        
        // Check if within 5-day timeframe (only for current month/year)
        const isWithinTimeframe = (m === currentMonth && y === currentYear) ? past5WorkingDays.includes(row.date) : true;
        
        // Check if can edit (not approved and within timeframe)
        const canEdit = dr && dr.status !== 'approved' && isWithinTimeframe;
        


        if (dr) {
            if (dr.status === 'approved') {
                // Only view button for approved reports
                reportBtns = `<button title="Lihat" class="btn-view-dr text-blue-600 font-bold" data-date="${row.date}"><i class="fi fi-ss-eye"></i></button>`;
            } else {
                // For disapproved and pending reports, always allow edit and view
                reportBtns = `<button title="Edit" class="btn-edit-dr text-yellow-600 font-bold ml-1" data-date="${row.date}"><i class="fi fi-sr-pen-square"></i></button>
                            <button title="Lihat" class="btn-view-dr text-blue-600 font-bold ml-1" data-date="${row.date}"><i class="fi fi-ss-eye"></i></button>`;
            }
        } else {
            // No report exists
            if (isAttendanceComplete && isWithinTimeframe) {
                reportBtns = `<button class="btn-create-dr bg-emerald-500 hover:bg-emerald-600 text-white btn-pill" data-date="${row.date}">Buat</button>`;
            } else if (!isAttendanceComplete && isWithinTimeframe) {
                reportBtns = `<span class="text-gray-400">Belum presensi</span>`;
            } else if (!isWithinTimeframe) {
                reportBtns = `<span class="text-gray-400">Tidak tersedia</span>`;
            }
        }

        const statusLabel = dr ? (dr.status === 'approved' ? `<span class="badge badge-green">Di-approve</span>` : (dr.status === 'disapproved' ? `<span class="badge badge-red">Tidak di-approve</span>` : `<span class="badge badge-gray">Belum di-approve</span>`)) : '<span class="badge badge-gray">Belum ada laporan</span>';
        // Format time for display (only HH:MM)
        const formatTimeDisplay = (timeStr) => {
            if (!timeStr || timeStr === '-') return '-';
            if (timeStr === 'izin' || timeStr === 'sakit' || timeStr === 'wfh') return timeStr;
            return timeStr.substring(0, 5);
        };
        
        // Keterangan column logic
        let keteranganContent = '';
        const today = new Date().toISOString().slice(0, 10);
        const isToday = row.date === today;
        const isFuture = row.date > today;
        
        if (row.ket && (row.ket === 'wfo' || row.ket === 'wfa' || row.ket === 'izin' || row.ket === 'sakit')) {
            // Show actual keterangan if exists
            let badgeClass = 'badge-gray';
            if (row.ket === 'wfo') badgeClass = 'badge-green';
            else if (row.ket === 'wfa') badgeClass = 'badge-blue';
            else if (row.ket === 'izin') badgeClass = 'badge-yellow';
            else if (row.ket === 'sakit') badgeClass = 'badge-yellow';
            
            keteranganContent = `<span class="badge ${badgeClass}">${row.ket.toUpperCase()}</span>`;
        } else if (!isAttendanceComplete && isToday) {
            // Show input button only for today if no attendance
            keteranganContent = `<button class="btn-input-keterangan bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded text-sm" data-date="${row.date}">Input Keterangan</button>`;
        } else if (!isAttendanceComplete && isFuture) {
            // Show "Tidak Tersedia" for future days
            keteranganContent = '<span class="text-gray-400">Tidak Tersedia</span>';
        } else if (!isAttendanceComplete && !isToday && !isFuture) {
            // Mark past days without attendance as alpha
            keteranganContent = '<span class="badge badge-red">ALPHA</span>';
        } else {
            keteranganContent = '<span class="text-gray-400">-</span>';
        }

        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50 text-center';
        tr.innerHTML = `
            <td class="py-2 px-4">${day}</td>
            <td class="py-2 px-4">${tanggal}</td>
            <td class="py-2 px-4">${formatTimeDisplay(row.jam_masuk)}</td>
            <td class="py-2 px-4">${formatTimeDisplay(row.jam_pulang)}</td>
            <td class="py-2 px-4">${keteranganContent}</td>
            <td class="py-2 px-4">${reportBtns}</td>
            <td class="py-2 px-4">${statusLabel}</td>`;
        body.appendChild(tr);
    });
    
    // Reset flag
    isInitRekapRunning = false;
    
    // Load and display KPI chart
    loadKPIChart(m, y);
}

// Global variable to store chart instance
let kpiChartInstance = null;

// Function to load and display KPI chart
async function loadKPIChart(month, year) {
    try {
        console.log('Loading KPI chart for month:', month, 'year:', year);
        
        // Get period start and end dates
        const periodStart = `${year}-${String(month).padStart(2, '0')}-01`;
        const lastDay = new Date(year, month, 0).getDate();
        const periodEnd = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        
        console.log('KPI period:', periodStart, 'to', periodEnd);
        
        // Fetch KPI data - check if we're viewing a specific user's data
        const urlParams = new URLSearchParams(window.location.search);
        const userId = urlParams.get('user_id') || (window.currentUserId || '2'); // Default to user 2 for testing
        const kpiUrl = userId ? 
            `?ajax=get_kpi_data&period_start=${periodStart}&period_end=${periodEnd}&user_id=${userId}&t=${Date.now()}` :
            `?ajax=get_kpi_data&period_start=${periodStart}&period_end=${periodEnd}&t=${Date.now()}`;
        
        console.log('KPI URL:', kpiUrl);
        console.log('Using user_id:', userId);
        const response = await api(kpiUrl);
        
        console.log('KPI response:', response);
        
        if (response && response.ok && response.data) {
            const kpiData = response.data;
            console.log('KPI data received:', kpiData);
            console.log('Izin/Sakit count:', kpiData.izin_sakit_count);
            
            // Show KPI chart section
            const kpiSection = qs('#kpi-chart-section');
            if (kpiSection) {
                kpiSection.classList.remove('hidden');
                console.log('KPI section shown');
            } else {
                console.error('KPI section element not found');
            }
            
            // Render KPI chart
            renderKPIChart(kpiData);
            console.log('KPI chart rendered');
            
            // Render KPI summary
            renderKPISummary(kpiData);
            console.log('KPI summary rendered');
        } else {
            console.error('No KPI data in response:', response);
            // Hide KPI section if no data
            const kpiSection = qs('#kpi-chart-section');
            if (kpiSection) {
                kpiSection.classList.add('hidden');
            }
        }
    } catch (error) {
        console.error('Error loading KPI chart:', error);
        // Hide KPI section on error
        const kpiSection = qs('#kpi-chart-section');
        if (kpiSection) {
            kpiSection.classList.add('hidden');
        }
    }
}

// Function to render KPI chart
function renderKPIChart(kpiData) {
    const canvas = qs('#kpi-chart');
    if (!canvas) return;
    
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    if (kpiChartInstance) {
        kpiChartInstance.destroy();
    }
    
    // Prepare data
    const labels = ['Hadir Ontime', 'Terlambat', 'Izin/Sakit', 'Alpha'];
    const data = [
        kpiData.ontime_count || 0,
        kpiData.late_count || 0,
        kpiData.izin_sakit_count || 0,
        kpiData.alpha_count || 0
    ];
    const colors = ['#10b981', '#f59e0b', '#3b82f6', '#ef4444'];
    
    console.log('Chart data:', { labels, data, izin_sakit: kpiData.izin_sakit_count });
    
    // Create chart
    kpiChartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Jumlah Hari',
                data: data,
                backgroundColor: colors,
                borderColor: colors,
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        stepSize: 1
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                title: {
                    display: true,
                    text: `KPI Score: ${kpiData.kpi_score || 0} - Status: ${kpiData.status || 'N/A'}`,
                    font: {
                        size: 16,
                        weight: 'bold'
                    }
                }
            }
        }
    });
}

// Function to render KPI summary
function renderKPISummary(kpiData) {
    const summaryContainer = qs('#kpi-summary');
    if (!summaryContainer) {
        console.error('KPI summary container not found');
        return;
    }
    
    console.log('Rendering KPI summary with data:', kpiData);
    console.log('Izin/Sakit count for summary:', kpiData.izin_sakit_count);
    
    const statusColor = kpiData.status === 'Excellent' ? 'text-green-600' : 
                       kpiData.status === 'Good' ? 'text-blue-600' : 
                       kpiData.status === 'Average' ? 'text-yellow-600' : 'text-red-600';
    
    summaryContainer.innerHTML = `
        <div class="bg-green-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-green-600">${kpiData.ontime_count || 0}</div>
            <div class="text-sm text-gray-600">Hadir Ontime</div>
        </div>
        <div class="bg-yellow-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-yellow-600">${kpiData.late_count || 0}</div>
            <div class="text-sm text-gray-600">Terlambat</div>
        </div>
        <div class="bg-blue-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-blue-600">${kpiData.izin_sakit_count || 0}</div>
            <div class="text-sm text-gray-600">Izin/Sakit</div>
        </div>
        <div class="bg-red-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-red-600">${kpiData.alpha_count || 0}</div>
            <div class="text-sm text-gray-600">Alpha</div>
        </div>
        <div class="bg-indigo-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold text-indigo-600">${kpiData.kpi_score || 0}</div>
            <div class="text-sm text-gray-600">KPI Score</div>
        </div>
        <div class="bg-gray-50 p-3 rounded-lg text-center">
            <div class="text-2xl font-bold ${statusColor}">${kpiData.status || 'N/A'}</div>
            <div class="text-sm text-gray-600">Status</div>
        </div>
    `;
}

// Initialize rekap page controls
const rekapControls = qs('#rekap-controls');
if (rekapControls) {
    console.log('Initializing rekap controls...');
    // Add event listeners for month, year, and week selectors
    qs('#rekap-month') && qs('#rekap-month').addEventListener('change', () => {
        console.log('Month changed to:', qs('#rekap-month').value);
        // Don't reset week selector, just reload data
        initRekapPage();
    });
    qs('#rekap-year') && qs('#rekap-year').addEventListener('change', () => {
        console.log('Year changed to:', qs('#rekap-year').value);
        // Don't reset week selector, just reload data
        initRekapPage();
    });
    qs('#rekap-week') && qs('#rekap-week').addEventListener('change', () => {
        console.log('Week selector changed to:', qs('#rekap-week').value);
        // Just reload the current data with new week filter
        const currentData = window.currentRekapData;
        if (currentData) {
            const m = parseInt(qs('#rekap-month')?.value || String(new Date().getMonth() + 1));
            const y = parseInt(qs('#rekap-year')?.value || String(new Date().getFullYear()));
            renderRekapData(currentData, m, y);
        }
    });
    qs('#btn-load-rekap') && qs('#btn-load-rekap').addEventListener('click', () => {
        console.log('Load rekap button clicked');
        initRekapPage();
    });
}

// Modal View Laporan Harian (hanya lihat, tidak bisa edit)
const drUserViewModal = document.createElement('div');
drUserViewModal.id='dr-user-view-modal';
drUserViewModal.className='fixed inset-0 bg-black/50 hidden items-center justify-center z-50';
drUserViewModal.innerHTML = `
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
        <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
        <div class="text-sm text-gray-500 mb-2" id="dr-user-view-date"></div>
        
        <!-- Bukti Izin/Sakit Section (View Only) -->
        <div id="dr-user-view-bukti-section" class="mb-4 hidden">
        <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
            <div id="dr-user-view-bukti-container" class="mb-2">
            <!-- Bukti image will be inserted here -->
        </div>
        </div>
        
        <div id="dr-user-view-content" class="whitespace-pre-wrap border p-3 rounded bg-gray-50 mb-4 min-h-[200px]"></div>
        
        <div id="dr-user-view-evaluation-container" class="mt-4 hidden">
            <h4 class="text-sm font-bold text-gray-700 mb-1">Evaluasi Admin:</h4>
            <p id="dr-user-view-evaluation" class="whitespace-pre-wrap border p-3 rounded bg-gray-100"></p>
    </div>
    
        <div class="flex justify-end gap-2 mt-4">
            <button id="dr-user-view-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Tutup</button>
        </div>
    </div>`;
document.body.appendChild(drUserViewModal);

// Modal Edit Laporan Harian (bisa edit, tanpa tombol hapus bukti)
const drUserEditModal = document.createElement('div');
drUserEditModal.id='dr-user-edit-modal';
drUserEditModal.className='fixed inset-0 bg-black/50 hidden items-center justify-center z-50';
drUserEditModal.innerHTML = `
    <div class="bg-white p-6 rounded-lg shadow-2xl w-full max-w-2xl">
        <h3 class="text-xl font-bold mb-2">Laporan Harian</h3>
        <div class="text-sm text-gray-500 mb-2" id="dr-user-edit-date"></div>
        
        <!-- Bukti Izin/Sakit Section (Edit Mode) -->
        <div id="dr-user-edit-bukti-section" class="mb-4 hidden">
            <label class="block text-sm text-gray-600 mb-2">Bukti Izin/Sakit:</label>
            <div id="dr-user-edit-bukti-container" class="mb-2">
                <!-- Bukti image will be inserted here -->
            </div>
            <div id="dr-user-edit-bukti-actions" class="flex gap-2 hidden">
                <button type="button" id="dr-user-edit-bukti-btn" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-1 rounded text-sm">Ganti Bukti</button>
            </div>
        </div>
        
        <textarea id="dr-user-edit-content" class="w-full border rounded p-2" rows="8" placeholder="Tulis detail pekerjaan hari ini..."></textarea>
        
        <div id="dr-user-edit-evaluation-container" class="mt-4 hidden">
        <h4 class="text-sm font-bold text-gray-700 mb-1">Evaluasi Admin:</h4>
            <p id="dr-user-edit-evaluation" class="whitespace-pre-wrap border p-3 rounded bg-gray-100"></p>
    </div>
        
    <div class="flex justify-end gap-2 mt-4">
            <button id="dr-user-edit-cancel" class="bg-gray-200 hover:bg-gray-300 px-4 py-2 rounded">Batal</button>
            <button id="dr-user-edit-save" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded">Simpan</button>
    </div>
    </div>`;
document.body.appendChild(drUserEditModal);

// Izin/Sakit modal handlers
const izinSakitModal = qs('#izin-sakit-modal');
const izinSakitForm = qs('#izin-sakit-form');
const izinSakitBukti = qs('#izin-sakit-bukti');
const izinSakitPreview = qs('#izin-sakit-preview');
const izinSakitPreviewImg = qs('#izin-sakit-preview-img');

// File upload preview with size validation
izinSakitBukti && izinSakitBukti.addEventListener('change', (e) => {
    const file = e.target.files[0];
    const errorDiv = qs('#izin-sakit-error');
    
    if (file) {
        // Check file size (5MB = 5 * 1024 * 1024 bytes)
        const maxSize = 5 * 1024 * 1024;
        if (file.size > maxSize) {
            errorDiv.textContent = `File terlalu besar. Maksimal 5MB. Ukuran saat ini: ${(file.size / (1024 * 1024)).toFixed(2)}MB`;
            errorDiv.classList.remove('hidden');
            izinSakitPreview.classList.add('hidden');
            return;
        }
        
        // Check file type
        if (!file.type.startsWith('image/')) {
            errorDiv.textContent = 'File harus berupa gambar (JPG, PNG, GIF)';
            errorDiv.classList.remove('hidden');
            izinSakitPreview.classList.add('hidden');
            return;
        }
        
        // Clear error and show preview
        errorDiv.classList.add('hidden');
        const reader = new FileReader();
        reader.onload = (e) => {
            izinSakitPreviewImg.src = e.target.result;
            izinSakitPreview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        errorDiv.classList.add('hidden');
        izinSakitPreview.classList.add('hidden');
    }
});

// Cancel button
qs('#izin-sakit-cancel') && qs('#izin-sakit-cancel').addEventListener('click', () => {
    izinSakitModal.classList.add('hidden');
    izinSakitForm.reset();
    izinSakitPreview.classList.add('hidden');
});

// Form submit
izinSakitForm && izinSakitForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const type = qs('#izin-sakit-type').value;
    const alasan = qs('#izin-sakit-alasan').value;
    const file = izinSakitBukti.files[0];
    
    if (!type || !alasan || !file) {
        showNotif('Semua field harus diisi', false);
        return;
    }
    
    // Convert file to base64
    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const r = await api('?ajax=submit_izin_sakit', {
                type: type,
                alasan: alasan,
                bukti: e.target.result
            });
            
            if (r.ok) {
                showNotif(r.message, true);
                izinSakitModal.classList.add('hidden');
                izinSakitForm.reset();
                izinSakitPreview.classList.add('hidden');
                initRekapPage(); // Refresh rekap
            } else {
                showNotif(r.message || 'Gagal menyimpan', false);
            }
        } catch (error) {
            console.error('Error submitting izin/sakit:', error);
            showNotif('Terjadi kesalahan', false);
        }
    };
    reader.readAsDataURL(file);
});

// Input keterangan button handler
document.addEventListener('click', async (e) => {
    if (e.target.classList.contains('btn-input-keterangan')) {
        const date = e.target.getAttribute('data-date');
        izinSakitModal.classList.remove('hidden');
        izinSakitModal.classList.add('flex');
    }
});

// Fungsi untuk membuka modal view laporan harian
async function openDailyReportViewModal(date) {
    qs('#dr-user-view-date').textContent = 'Tanggal: ' + date;
        
        const r = await api('?ajax=get_rekap', { month: new Date(date).getMonth()+1, year: new Date(date).getFullYear() });
        const item = (r.data||[]).find(x=> x.date===date);
    
        if(item && item.daily_report){
        qs('#dr-user-view-content').textContent = item.daily_report.content||'';
                if (item.daily_report.evaluation) {
            qs('#dr-user-view-evaluation').textContent = item.daily_report.evaluation;
            qs('#dr-user-view-evaluation-container').classList.remove('hidden');
        } else {
            qs('#dr-user-view-evaluation-container').classList.add('hidden');
                }
            } else {
        qs('#dr-user-view-content').textContent = 'Belum ada laporan harian untuk tanggal ini.';
        qs('#dr-user-view-evaluation-container').classList.add('hidden');
    }
    
    // Cek apakah ada bukti izin/sakit untuk tanggal ini
    if (item && (item.ket === 'izin' || item.ket === 'sakit')) {
        // Get attendance data to find bukti
        const attendanceData = await api('?ajax=get_attendance');
        if (attendanceData.ok && attendanceData.data) {
            const todayRecord = attendanceData.data.find(att => 
                att.jam_masuk_iso && 
                att.jam_masuk_iso.slice(0, 10) === date &&
                (att.ket === 'izin' || att.ket === 'sakit') &&
                att.bukti_izin_sakit
            );
            
            if (todayRecord) {
                // Tampilkan bukti izin/sakit (view only)
                qs('#dr-user-view-bukti-section').classList.remove('hidden');
                qs('#dr-user-view-bukti-container').innerHTML = `
                    <div class="flex justify-center">
                        <img src="${todayRecord.bukti_izin_sakit}" alt="Bukti ${todayRecord.ket}" class="max-w-full max-h-64 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">
                    </div>
                    <p class="text-sm text-gray-600 mt-2 text-center">Bukti ${todayRecord.ket.toUpperCase()}</p>
                `;
            } else {
                qs('#dr-user-view-bukti-section').classList.add('hidden');
            }
        }
    } else {
        qs('#dr-user-view-bukti-section').classList.add('hidden');
    }
    
    qs('#dr-user-view-modal').classList.remove('hidden'); 
    qs('#dr-user-view-modal').classList.add('flex');
}

// Fungsi untuk membuka modal edit laporan harian
async function openDailyReportEditModal(date) {
    qs('#dr-user-edit-date').textContent = 'Tanggal: ' + date;
    qs('#dr-user-edit-modal').dataset.date = date;
    
    const r = await api('?ajax=get_rekap', { month: new Date(date).getMonth()+1, year: new Date(date).getFullYear() });
    const item = (r.data||[]).find(x=> x.date===date);
    
    if(item && item.daily_report){
        qs('#dr-user-edit-content').value = item.daily_report.content||'';
        if (item.daily_report.evaluation) {
            qs('#dr-user-edit-evaluation').textContent = item.daily_report.evaluation;
            qs('#dr-user-edit-evaluation-container').classList.remove('hidden');
        } else {
            qs('#dr-user-edit-evaluation-container').classList.add('hidden');
        }
    } else {
        qs('#dr-user-edit-content').value = '';
        qs('#dr-user-edit-evaluation-container').classList.add('hidden');
        }
        
        // Cek apakah ada bukti izin/sakit untuk tanggal ini
        if (item && (item.ket === 'izin' || item.ket === 'sakit')) {
            // Get attendance data to find bukti
            const attendanceData = await api('?ajax=get_attendance');
            if (attendanceData.ok && attendanceData.data) {
                const todayRecord = attendanceData.data.find(att => 
                    att.jam_masuk_iso && 
                    att.jam_masuk_iso.slice(0, 10) === date &&
                    (att.ket === 'izin' || att.ket === 'sakit') &&
                    att.bukti_izin_sakit
                );
                
                if (todayRecord) {
                // Tampilkan bukti izin/sakit (edit mode)
                qs('#dr-user-edit-bukti-section').classList.remove('hidden');
                qs('#dr-user-edit-bukti-container').innerHTML = `
                        <div class="flex justify-center">
                            <img src="${todayRecord.bukti_izin_sakit}" alt="Bukti ${todayRecord.ket}" class="max-w-full max-h-64 object-contain rounded border shadow-lg" style="max-width: 100%; height: auto;">
                        </div>
                        <p class="text-sm text-gray-600 mt-2 text-center">Bukti ${todayRecord.ket.toUpperCase()}</p>
                    `;
                // Show edit button
                qs('#dr-user-edit-bukti-actions').classList.remove('hidden');
                qs('#dr-user-edit-bukti-btn').dataset.date = date;
                } else {
                qs('#dr-user-edit-bukti-section').classList.add('hidden');
                qs('#dr-user-edit-bukti-actions').classList.add('hidden');
                }
            }
        } else {
        qs('#dr-user-edit-bukti-section').classList.add('hidden');
        qs('#dr-user-edit-bukti-actions').classList.add('hidden');
    }
    
    qs('#dr-user-edit-modal').classList.remove('hidden'); 
    qs('#dr-user-edit-modal').classList.add('flex');
}

// Event listener untuk tombol laporan harian
document.addEventListener('click', async (e)=>{
    const target = e.target.closest('.btn-create-dr, .btn-edit-dr, .btn-view-dr');
    if(target){
        const date = target.getAttribute('data-date');
        const isView = target.classList.contains('btn-view-dr');
        const isEdit = target.classList.contains('btn-edit-dr');
        
        if (isView) {
            await openDailyReportViewModal(date);
        } else if (isEdit) {
            await openDailyReportEditModal(date);
        } else {
            // Create new report - use edit modal
            await openDailyReportEditModal(date);
        }
    }
});
// Event handlers untuk modal view laporan harian
qs('#dr-user-view-cancel') && qs('#dr-user-view-cancel').addEventListener('click', ()=>{ 
    qs('#dr-user-view-modal').classList.add('hidden'); 
    qs('#dr-user-view-modal').classList.remove('flex'); 
});

// Event handlers untuk modal edit laporan harian
qs('#dr-user-edit-cancel') && qs('#dr-user-edit-cancel').addEventListener('click', ()=>{ 
    qs('#dr-user-edit-modal').classList.add('hidden'); 
    qs('#dr-user-edit-modal').classList.remove('flex'); 
});

qs('#dr-user-edit-save') && qs('#dr-user-edit-save').addEventListener('click', async ()=>{
    const date = qs('#dr-user-edit-modal').dataset.date; 
    const content = qs('#dr-user-edit-content').value;
    const r = await api('?ajax=save_daily_report', { date, content });
    if(r.ok){ 
        qs('#dr-user-edit-modal').classList.add('hidden'); 
        qs('#dr-user-edit-modal').classList.remove('flex'); 
        initRekapPage(); 
    } else { 
        showNotif(r.message||'Gagal simpan'); 
    }
});

// Event handler untuk ganti bukti izin/sakit (modal edit)
qs('#dr-user-edit-bukti-btn') && qs('#dr-user-edit-bukti-btn').addEventListener('click', () => {
    const date = qs('#dr-user-edit-bukti-btn').dataset.date;
    // Open edit bukti modal
    qs('#edit-bukti-modal').classList.remove('hidden');
    qs('#edit-bukti-modal').classList.add('flex');
    qs('#edit-bukti-save').dataset.date = date;
    
    // Show current bukti if exists
    const currentImg = qs('#dr-user-edit-bukti-container img');
    if (currentImg) {
        qs('#edit-bukti-current').classList.remove('hidden');
        qs('#edit-bukti-current-img').src = currentImg.src;
    } else {
        // If no current bukti, hide current section
        qs('#edit-bukti-current').classList.add('hidden');
    }
    
    // Reset file input and preview
    qs('#edit-bukti-file').value = '';
    qs('#edit-bukti-preview').classList.add('hidden');
});

// Event handler untuk modal edit bukti
qs('#edit-bukti-cancel') && qs('#edit-bukti-cancel').addEventListener('click', () => {
    qs('#edit-bukti-modal').classList.add('hidden');
    qs('#edit-bukti-modal').classList.remove('flex');
    qs('#edit-bukti-file').value = '';
    qs('#edit-bukti-preview').classList.add('hidden');
    qs('#edit-bukti-current').classList.add('hidden');
});

qs('#edit-bukti-save') && qs('#edit-bukti-save').addEventListener('click', async () => {
    const date = qs('#edit-bukti-save').dataset.date;
    const file = qs('#edit-bukti-file').files[0];
    
    if (!file) {
        showNotif('Pilih file gambar terlebih dahulu', false);
        return;
    }
    
    // Check file size (5MB = 5 * 1024 * 1024 bytes)
    const maxSize = 5 * 1024 * 1024;
    if (file.size > maxSize) {
        showNotif(`File terlalu besar. Maksimal 5MB. Ukuran saat ini: ${(file.size / (1024 * 1024)).toFixed(2)}MB`, false);
        return;
    }
    
    // Check file type
    if (!file.type.startsWith('image/')) {
        showNotif('File harus berupa gambar (JPG, PNG, GIF)', false);
        return;
    }
    
    // Convert file to base64
    const reader = new FileReader();
    reader.onload = async (e) => {
        try {
            const r = await api('?ajax=update_bukti_izin_sakit', {
                date: date,
                action_type: 'update',
                bukti: e.target.result
            });
            
            if (r.ok) {
                showNotif('Bukti berhasil diperbarui');
                qs('#edit-bukti-modal').classList.add('hidden');
                qs('#edit-bukti-modal').classList.remove('flex');
                qs('#edit-bukti-file').value = '';
                qs('#edit-bukti-preview').classList.add('hidden');
                qs('#edit-bukti-current').classList.add('hidden');
                
                // Refresh the daily report modal to show updated bukti
                const drEditModal = qs('#dr-user-edit-modal');
                if (drEditModal && !drEditModal.classList.contains('hidden')) {
                    // Simply refresh the page to show updated data
                    location.reload();
                }
            } else {
                showNotif(r.message || 'Gagal memperbarui bukti', false);
            }
        } catch (error) {
            console.error('Error updating bukti:', error);
            showNotif('Terjadi kesalahan', false);
        }
    };
    reader.readAsDataURL(file);
});

// File upload preview for edit bukti modal
qs('#edit-bukti-file') && qs('#edit-bukti-file').addEventListener('change', (e) => {
    const file = e.target.files[0];
    const preview = qs('#edit-bukti-preview');
    
    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            qs('#edit-bukti-preview').src = e.target.result;
            preview.classList.remove('hidden');
        };
        reader.readAsDataURL(file);
    } else {
        preview.classList.add('hidden');
    }
});

// Helper function for month names
function monthName(monthIndex) {
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    return months[monthIndex] || '';
}

// Tambahkan state untuk paginasi di atas fungsi renderMonthly
let currentMonthlyPageYear = new Date().getFullYear();

async function renderMonthly() {
    const res = await fetch('?ajax=get_monthly_reports');
    const j = await res.json();
    const list = (j.data || []);
    const body = qs('#table-monthly-body');
    if (!body) return;
    body.innerHTML = ''; // Kosongkan tabel body

    const monthName = (m) => ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'][m - 1];

    const year = currentMonthlyPageYear; // Gunakan tahun dari state
    const allMonths = Array.from({ length: 12 }, (_, i) => i + 1);

    // Logic untuk aturan waktu (2 bulan terakhir)
    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1; // 1-12

    allMonths.forEach(m => {
        // Handle case where year/month might be 0 or invalid
        let item = list.find(it => {
            const itemYear = parseInt(it.year) || 0;
            const itemMonth = parseInt(it.month) || 0;
            return itemMonth === m && itemYear === year;
        });
        
        // If no item found for this month, check if there's a record with year=0 or month=0 for this month
        if (!item && m === 8 && year === 2025) {
            item = list.find(it => {
                const itemYear = parseInt(it.year) || 0;
                const itemMonth = parseInt(it.month) || 0;
                return (itemYear === 0 || itemMonth === 0) && it.status === 'approved';
            });
        }
        
        const tr = document.createElement('tr');
        tr.className = 'border-b hover:bg-gray-50 text-center';
        const label = `${monthName(m)} ${year}`;

        let actionBtn;
        let statusBadge;

        // Cek apakah bulan ini valid untuk diedit/dibuat
        const isEditableTime = (year === currentYear && (m === currentMonth || m === currentMonth - 1)) ||
                               (year === currentYear - 1 && currentMonth === 1 && m === 12);

        if (item) { // Jika laporan sudah ada
            const isApproved = item.status === 'approved';
            const isDraft = item.status === 'draft';
            const isSubmitted = item.status === 'belum di approve';
            
            if (isApproved) {
                // Jika sudah di-approve, hanya bisa view (regardless of timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
            } else if (isDraft) {
                // Jika draft, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit Draft</button>`;
                }
            } else if (isSubmitted) {
                // Jika belum di approve, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit</button>`;
                }
            } else {
                // Jika disapproved, bisa view dan edit (jika dalam timeframe)
                actionBtn = `<button class="btn-view-month text-blue-600 font-bold" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-ss-eye"></i> Lihat</button>`;
                if (isEditableTime) {
                    actionBtn += ` <button class="btn-edit-month text-yellow-600 font-bold ml-2" data-json='${JSON.stringify(item).replace(/'/g, "&apos;")}'><i class="fi fi-sr-pen-square"></i> Edit</button>`;
                }
            }
            
            // Status badge
            if (isApproved) {
                statusBadge = `<span class="badge badge-green">Di-approve</span>`;
            } else if (item.status === 'disapproved') {
                statusBadge = `<span class="badge badge-red">Tidak di-approve</span>`;
            } else if (isDraft) {
                statusBadge = `<span class="badge badge-gray">Draft</span>`;
            } else if (isSubmitted) {
                statusBadge = `<span class="badge badge-blue">Belum di Approve</span>`;
            } else {
                statusBadge = `<span class="badge badge-gray">${item.status}</span>`;
            }
        } else { // Jika laporan belum ada
            if (isEditableTime) {
                actionBtn = `<button class="btn-create-month bg-emerald-500 hover:bg-emerald-600 text-white btn-pill" data-year="${year}" data-month="${m}">Buat</button>`;
            } else {
                actionBtn = `<span class="text-gray-400">Not Available</span>`;
            }
            statusBadge = `<span class="badge badge-gray">Belum ada laporan</span>`;
        }

        tr.innerHTML = `
            <td class="py-2 px-4">${label}</td>
            <td class="py-2 px-4">${actionBtn}</td>
            <td class="py-2 px-4">${statusBadge}</td>`;
        body.appendChild(tr);
    });
    
    // Hapus dan buat ulang tombol paginasi
    let paginationDiv = qs('#monthly-pagination');
    if (paginationDiv) paginationDiv.remove();
    
    paginationDiv = document.createElement('div');
    paginationDiv.id = 'monthly-pagination';
    paginationDiv.className = 'mt-4 flex justify-center gap-2';
    paginationDiv.innerHTML = `
        <button data-year="2025" class="page-btn px-4 py-2 rounded ${currentMonthlyPageYear === 2025 ? 'bg-indigo-600 text-white' : 'bg-gray-200'}">2025</button>
        <button data-year="2026" class="page-btn px-4 py-2 rounded ${currentMonthlyPageYear === 2026 ? 'bg-indigo-600 text-white' : 'bg-gray-200'}">2026</button>
    `;
    body.closest('.overflow-x-auto').insertAdjacentElement('afterend', paginationDiv);
}


async function renderAdminMonthly(){
    const mSel = qs('#am-month'); const ySel = qs('#am-year'); const sSel = qs('#am-startup');
    if(mSel && mSel.options.length<=2){
        const months=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        months.forEach((m,i)=>{ const o=document.createElement('option'); o.value=String(i+1); o.textContent=m; mSel.appendChild(o); });
        const yNow=new Date().getFullYear(); for(let y=yNow-2;y<=yNow+1;y++){ const o=document.createElement('option'); o.value=String(y); o.textContent=String(y); ySel.appendChild(o);}
    }
    if(sSel && sSel.options.length<=1){
        const res = await fetch('?ajax=get_startups');
        const j = await res.json();
        if(j.ok && j.data){
            j.data.forEach(startup => {
                const o = document.createElement('option');
                o.value = startup;
                o.textContent = startup;
                sSel.appendChild(o);
            });
        }
    }
    const body = qs('#am-body'); if(!body) return; body.innerHTML='';
    const payload = { term: qs('#am-search')?.value||'', startup: qs('#am-startup')?.value||'', month: qs('#am-month')?.value||'', year: qs('#am-year')?.value||'' };
    const r = await api('?ajax=admin_get_monthly_reports', payload);
    const j = r.data||[];
    // Filter out draft reports from admin view
    const filteredReports = j.filter(it => it.status !== 'draft');
    if(filteredReports.length===0){ body.innerHTML = `<tr><td colspan="6" class="text-center py-4">Tidak ada data.</td></tr>`; return; }
    const monthName=(m)=>['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'][m-1];
    filteredReports.forEach(it=>{
        const tr=document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        const label = `${monthName(parseInt(it.month))} ${it.year}`;
        const detailBtn = `<button class="btn-view-month-detail text-blue-600 font-bold text-center" data-id="${it.id}"><i class="fi fi-ss-eye text-xl"></i></button>`;
        const statusBadge = it.status==='approved'? `<span class="badge badge-green">Di-approve</span>`:(it.status==='disapproved'?`<span class="badge badge-red">Tidak di-approve</span>`:`<span class="badge badge-blue">Belum di Approve</span>`);
        const actions = (it.status === 'belum di approve' || it.status === 'approved' || it.status === 'disapproved') ?
            `<button class="btn-am-approve bg-emerald-600 hover:bg-emerald-700 text-white px-2 py-1 rounded mr-1" data-id="${it.id}">Approve</button>
            <button class="btn-am-disapprove bg-red-500 hover:bg-red-600 text-white px-2 py-1 rounded" data-id="${it.id}">Disapprove</button>` : '';

        tr.innerHTML = `
            <td class="py-2 px-4">${label}</td>
            <td class="py-2 px-4">${it.nama||''}</td>
            <td class="py-2 px-4">${it.startup||'-'}</td>
            <td class="py-2 px-4">${detailBtn}</td>
            <td class="py-2 px-4">${statusBadge}</td>
            <td class="py-2 px-4">${actions}</td>`;
        body.appendChild(tr);
    });
}

['#am-search','#am-startup','#am-month','#am-year'].forEach(sel=>{ if(qs(sel)) qs(sel).addEventListener('input', renderAdminMonthly); });
qs('#am-reset') && qs('#am-reset').addEventListener('click', ()=>{ if(qs('#am-search')) qs('#am-search').value=''; if(qs('#am-startup')) qs('#am-startup').value=''; if(qs('#am-month')) qs('#am-month').value=''; if(qs('#am-year')) qs('#am-year').value=''; renderAdminMonthly(); });

// Settings functions
async function renderSettings() {
    try {
        const response = await fetch('?ajax=get_settings');
        const result = await response.json();
        
        if (result.ok && result.data) {
            const settings = result.data;
            qs('#max-ontime-hour').value = settings.max_ontime_hour?.value || '8';
            qs('#min-checkout-hour').value = settings.min_checkout_hour?.value || '17';
            if(qs('#wfo-address')) qs('#wfo-address').value = settings.wfo_address?.value || '';
            if(qs('#wfo-radius')) qs('#wfo-radius').value = settings.wfo_radius_m?.value || '1200';
            if(qs('#attendance-period-end')) qs('#attendance-period-end').value = settings.attendance_period_end?.value || '';
            if(qs('#kpi-late-penalty')) qs('#kpi-late-penalty').value = settings.kpi_late_penalty_per_minute?.value || '1';
            if(qs('#kpi-izin-sakit')) qs('#kpi-izin-sakit').value = settings.kpi_izin_sakit_score?.value || '85';
            if(qs('#kpi-alpha')) qs('#kpi-alpha').value = settings.kpi_alpha_score?.value || '0';
            if(qs('#kpi-overtime-bonus')) qs('#kpi-overtime-bonus').value = settings.kpi_overtime_bonus?.value || '5';
            
            // WFO API settings
            if(qs('#wfo-mode')) qs('#wfo-mode').value = settings.wfo_mode?.value || 'api';
            if(qs('#wfo-api-provider')) qs('#wfo-api-provider').value = settings.wfo_api_provider?.value || 'ipinfo';
            if(qs('#wfo-api-token')) qs('#wfo-api-token').value = settings.wfo_api_token?.value || '';
            if(qs('#wfo-api-org-keywords')) qs('#wfo-api-org-keywords').value = settings.wfo_api_org_keywords?.value || '';
            if(qs('#wfo-api-asn-list')) qs('#wfo-api-asn-list').value = settings.wfo_api_asn_list?.value || '';
            if(qs('#wfo-api-cidr-list')) qs('#wfo-api-cidr-list').value = settings.wfo_api_cidr_list?.value || '';
        }
    } catch (error) {
        console.error('Error loading settings:', error);
        showNotif('Gagal memuat pengaturan', false);
    }
}

// Address search functionality
let addressSearchTimeout;
let selectedAddress = null;

// Initialize address search when settings page loads
function initAddressSearch() {
    const addressInput = qs('#wfo-address');
    const suggestionsDiv = qs('#address-suggestions');
    
    if (!addressInput || !suggestionsDiv) return;
    
    addressInput.addEventListener('input', (e) => {
        const query = e.target.value.trim();
        
        // Clear previous timeout
        if (addressSearchTimeout) {
            clearTimeout(addressSearchTimeout);
        }
        
        // Hide suggestions if query is empty
        if (query.length < 3) {
            suggestionsDiv.classList.add('hidden');
            return;
        }
        
        // Debounce search
        addressSearchTimeout = setTimeout(() => {
            searchAddresses(query);
        }, 300);
    });
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', (e) => {
        if (!addressInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.classList.add('hidden');
        }
    });
    
    // Handle keyboard navigation
    addressInput.addEventListener('keydown', (e) => {
        const suggestions = suggestionsDiv.querySelectorAll('.suggestion-item');
        const activeSuggestion = suggestionsDiv.querySelector('.suggestion-item.active');
        
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const next = activeSuggestion.nextElementSibling;
                if (next) {
                    next.classList.add('active');
                } else {
                    suggestions[0]?.classList.add('active');
                }
            } else {
                suggestions[0]?.classList.add('active');
            }
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.classList.remove('active');
                const prev = activeSuggestion.previousElementSibling;
                if (prev) {
                    prev.classList.add('active');
                } else {
                    suggestions[suggestions.length - 1]?.classList.add('active');
                }
            } else {
                suggestions[suggestions.length - 1]?.classList.add('active');
            }
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (activeSuggestion) {
                activeSuggestion.click();
            }
        } else if (e.key === 'Escape') {
            suggestionsDiv.classList.add('hidden');
        }
    });
}

async function searchAddresses(query) {
    try {
        // Try multiple search strategies for better results
        const searchQueries = [
            // Original query
            query,
            // Add "Jakarta" for better context
            `${query} Jakarta`,
            // Add "Indonesia" for broader search
            `${query} Indonesia`,
            // Try with "Sekolah" prefix for schools
            query.includes('SMP') || query.includes('SMA') || query.includes('SD') ? 
                `Sekolah ${query}` : query
        ];
        
        let allResults = [];
        
        // Search with multiple queries
        for (const searchQuery of searchQueries) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(searchQuery)}&limit=3&countrycodes=id&addressdetails=1&bounded=1&viewbox=106.5,-6.5,107.0,-6.0`);
                const results = await response.json();
                allResults = allResults.concat(results);
            } catch (err) {
                console.warn('Search failed for query:', searchQuery, err);
            }
        }
        
        // Remove duplicates based on place_id
        const uniqueResults = allResults.filter((result, index, self) => 
            index === self.findIndex(r => r.place_id === result.place_id)
        );
        
        // If no results found, try a broader search without country restriction
        if (uniqueResults.length === 0) {
            try {
                const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&addressdetails=1`);
                const results = await response.json();
                allResults = results;
            } catch (err) {
                console.warn('Broad search failed:', err);
            }
        }
        
        // If still no results, create a manual entry
        if (allResults.length === 0) {
            allResults = [{
                display_name: query,
                lat: '',
                lon: '',
                place_id: 'manual',
                type: 'manual'
            }];
        }
        
        displayAddressSuggestions(allResults.slice(0, 5)); // Limit to 5 results
        
    } catch (error) {
        console.error('Error searching addresses:', error);
        // Fallback: show a simple suggestion
        displayAddressSuggestions([{
            display_name: query,
            lat: '',
            lon: '',
            place_id: 'manual',
            type: 'manual'
        }]);
    }
}

function displayAddressSuggestions(results) {
    const suggestionsDiv = qs('#address-suggestions');
    if (!suggestionsDiv) return;
    
    if (results.length === 0) {
        suggestionsDiv.innerHTML = '<div class="p-3 text-gray-500 text-sm">Tidak ada hasil ditemukan</div>';
    } else {
        suggestionsDiv.innerHTML = results.map((result, index) => {
            const isManual = result.type === 'manual' || result.place_id === 'manual';
            const hasCoordinates = result.lat && result.lon;
            
            return `
                <div class="suggestion-item p-3 hover:bg-gray-50 cursor-pointer border-b border-gray-100 ${index === 0 ? 'active' : ''}" 
                     data-address="${result.display_name}" 
                     data-lat="${result.lat || ''}" 
                     data-lon="${result.lon || ''}">
                    <div class="font-medium text-sm">${result.display_name}</div>
                    ${hasCoordinates ? 
                        `<div class="text-xs text-gray-500 mt-1">Koordinat: ${result.lat}, ${result.lon}</div>` : 
                        `<div class="text-xs text-orange-500 mt-1">${isManual ? 'Manual entry - koordinat akan diisi otomatis' : 'Koordinat tidak tersedia'}</div>`
                    }
                    ${isManual ? '<div class="text-xs text-blue-500 mt-1">💡 Pilih untuk menggunakan alamat ini</div>' : ''}
                </div>
            `;
        }).join('');
        
        // Add click handlers
        suggestionsDiv.querySelectorAll('.suggestion-item').forEach(item => {
            item.addEventListener('click', () => {
                selectAddress(item);
            });
            
            item.addEventListener('mouseenter', () => {
                suggestionsDiv.querySelectorAll('.suggestion-item').forEach(i => i.classList.remove('active'));
                item.classList.add('active');
            });
        });
    }
    
    suggestionsDiv.classList.remove('hidden');
}

async function selectAddress(item) {
    const address = item.dataset.address;
    let lat = item.dataset.lat;
    let lon = item.dataset.lon;
    
    // If no coordinates, try to geocode the address
    if (!lat || !lon) {
        try {
            const geocodeResponse = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}&limit=1&addressdetails=1`);
            const geocodeResults = await geocodeResponse.json();
            
            if (geocodeResults.length > 0) {
                lat = geocodeResults[0].lat;
                lon = geocodeResults[0].lon;
            }
        } catch (error) {
            console.warn('Geocoding failed:', error);
        }
    }
    
    // Update input field
    const addressInput = qs('#wfo-address');
    if (addressInput) {
        addressInput.value = address;
    }
    
    // Store selected address data
    selectedAddress = {
        address: address,
        lat: lat,
        lon: lon
    };
    
    // Show selected address info
    const infoDiv = qs('#selected-address-info');
    const addressText = qs('#selected-address-text');
    const coordinatesSpan = qs('#selected-coordinates');
    
    if (infoDiv && addressText && coordinatesSpan) {
        addressText.textContent = address;
        if (lat && lon) {
            coordinatesSpan.textContent = `${lat}, ${lon}`;
        } else {
            coordinatesSpan.textContent = 'Koordinat akan diisi otomatis saat disimpan';
        }
        infoDiv.classList.remove('hidden');
    }
    
    // Hide suggestions
    const suggestionsDiv = qs('#address-suggestions');
    if (suggestionsDiv) {
        suggestionsDiv.classList.add('hidden');
    }
}

// Settings form handlers
qs('#settings-form') && qs('#settings-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const maxOntimeHour = qs('#max-ontime-hour').value;
    const minCheckoutHour = qs('#min-checkout-hour').value;
    const wfoAddress = qs('#wfo-address')?.value || '';
    const wfoRadius = qs('#wfo-radius')?.value || '';
    const periodEnd = qs('#attendance-period-end')?.value || '';
    const kpiLatePenalty = qs('#kpi-late-penalty')?.value || '';
    const kpiIzinSakit = qs('#kpi-izin-sakit')?.value || '';
    const kpiAlpha = qs('#kpi-alpha')?.value || '';
    const kpiOvertimeBonus = qs('#kpi-overtime-bonus')?.value || '';
    
    // WFO API settings
    const wfoMode = qs('#wfo-mode')?.value || 'api';
    const wfoApiProvider = qs('#wfo-api-provider')?.value || 'ipinfo';
    const wfoApiToken = qs('#wfo-api-token')?.value || '';
    const wfoApiOrgKeywords = qs('#wfo-api-org-keywords')?.value || '';
    const wfoApiAsnList = qs('#wfo-api-asn-list')?.value || '';
    const wfoApiCidrList = qs('#wfo-api-cidr-list')?.value || '';
    
    // Use selected address coordinates if available
    let wfoLat = '';
    let wfoLon = '';
    if (selectedAddress && selectedAddress.lat && selectedAddress.lon) {
        wfoLat = selectedAddress.lat;
        wfoLon = selectedAddress.lon;
    }
    
    if (!maxOntimeHour || !minCheckoutHour) {
        showNotif('Semua field harus diisi', false);
        return;
    }
    
    if (parseInt(maxOntimeHour) < 0 || parseInt(maxOntimeHour) > 23) {
        showNotif('Jam maksimal ontime harus antara 0-23', false);
        return;
    }
    
    if (parseInt(minCheckoutHour) < 0 || parseInt(minCheckoutHour) > 23) {
        showNotif('Jam minimal checkout harus antara 0-23', false);
        return;
    }
    
    try {
        const response = await api('?ajax=update_settings', {
            max_ontime_hour: maxOntimeHour,
            min_checkout_hour: minCheckoutHour,
            wfo_address: wfoAddress,
            wfo_lat: wfoLat,
            wfo_lon: wfoLon,
            wfo_radius_m: wfoRadius,
            attendance_period_end: periodEnd,
            kpi_late_penalty: kpiLatePenalty,
            kpi_izin_sakit: kpiIzinSakit,
            kpi_alpha: kpiAlpha,
            kpi_overtime_bonus: kpiOvertimeBonus,
            wfo_mode: wfoMode,
            wfo_api_provider: wfoApiProvider,
            wfo_api_token: wfoApiToken,
            wfo_api_org_keywords: wfoApiOrgKeywords,
            wfo_api_asn_list: wfoApiAsnList,
            wfo_api_cidr_list: wfoApiCidrList
        });
        
        if (response.ok) {
            showNotif('Pengaturan berhasil disimpan', true);
        } else {
            showNotif(response.message || 'Gagal menyimpan pengaturan', false);
        }
    } catch (error) {
        console.error('Error saving settings:', error);
        showNotif('Terjadi kesalahan saat menyimpan', false);
    }
});

qs('#reset-settings') && qs('#reset-settings').addEventListener('click', () => {
    qs('#max-ontime-hour').value = '8';
    qs('#min-checkout-hour').value = '17';
    if(qs('#kpi-late-penalty')) qs('#kpi-late-penalty').value = '1';
    if(qs('#kpi-izin-sakit')) qs('#kpi-izin-sakit').value = '85';
    if(qs('#kpi-alpha')) qs('#kpi-alpha').value = '0';
    showNotif('Pengaturan direset ke default', true);
});

// Auto-detect WFO button handler
qs('#auto-detect-wfo') && qs('#auto-detect-wfo').addEventListener('click', async () => {
    const button = qs('#auto-detect-wfo');
    const resultDiv = qs('#auto-detect-result');
    const orgDiv = qs('#detect-org');
    const asnDiv = qs('#detect-asn');
    const ipDiv = qs('#detect-ip');
    
    button.disabled = true;
    button.textContent = '🔄 Mendeteksi...';
    
    try {
        // Get current IP
        const ipResponse = await fetch('https://api.ipify.org?format=json');
        const ipData = await ipResponse.json();
        const currentIp = ipData.ip;
        
        // Get IP info using current provider setting
        const provider = qs('#wfo-api-provider')?.value || 'ipinfo';
        const token = qs('#wfo-api-token')?.value || '';
        
        let apiUrl = '';
        if (provider === 'ipinfo') {
            apiUrl = `https://ipinfo.io/${currentIp}/json${token ? `?token=${token}` : ''}`;
        } else if (provider === 'ipapi') {
            apiUrl = `https://ipapi.co/${currentIp}/json/`;
        } else {
            apiUrl = `http://ip-api.com/json/${currentIp}?fields=status,message,org,as,asname,query`;
        }
        
        const headers = {};
        if (provider === 'ipapi' && token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const infoResponse = await fetch(apiUrl, { headers });
        const infoData = await infoResponse.json();
        
        // Extract organization and ASN based on provider
        let org = '';
        let asn = '';
        
        if (provider === 'ipinfo') {
            org = infoData.company?.name || infoData.org || '';
            asn = infoData.org ? infoData.org.split(' ')[0] : '';
        } else if (provider === 'ipapi') {
            org = infoData.org || infoData.company || '';
            asn = infoData.asn || infoData.as || '';
        } else {
            org = infoData.org || infoData.asname || '';
            asn = infoData.as || '';
        }
        
        // Display results
        ipDiv.innerHTML = `<strong>IP:</strong> ${currentIp}`;
        orgDiv.innerHTML = `<strong>Organisasi:</strong> ${org || 'Tidak ditemukan'}`;
        asnDiv.innerHTML = `<strong>ASN:</strong> ${asn || 'Tidak ditemukan'}`;
        
        resultDiv.classList.remove('hidden');
        
        // Auto-fill if organization contains Telkom University
        if (org && org.toLowerCase().includes('telkom')) {
            const currentOrgKeywords = qs('#wfo-api-org-keywords')?.value || '';
            if (!currentOrgKeywords.includes(org)) {
                const newKeywords = currentOrgKeywords ? `${currentOrgKeywords}, ${org}` : org;
                qs('#wfo-api-org-keywords').value = newKeywords;
                showNotif(`Organisasi "${org}" ditambahkan ke kata kunci WFO`, true);
            }
        }
        
        if (asn && asn.startsWith('AS')) {
            const currentAsnList = qs('#wfo-api-asn-list')?.value || '';
            if (!currentAsnList.includes(asn)) {
                const newAsnList = currentAsnList ? `${currentAsnList}, ${asn}` : asn;
                qs('#wfo-api-asn-list').value = newAsnList;
                showNotif(`ASN "${asn}" ditambahkan ke daftar ASN WFO`, true);
            }
        }
        
    } catch (error) {
        console.error('Error detecting WFO:', error);
        showNotif('Gagal mendeteksi informasi IP. Periksa koneksi internet atau token API.', false);
        resultDiv.classList.add('hidden');
    } finally {
        button.disabled = false;
        button.textContent = 'Auto-Detect WFO dari IP Admin Saat Ini';
    }
});

// Dashboard functions
let dashboardCharts = {};

async function renderDashboard() {
    try {
        const response = await fetch('?ajax=get_dashboard_data');
        const result = await response.json();
        
        if (!result.ok) {
            showNotif('Gagal memuat data dashboard', false);
            return;
        }
        
        const data = result.data;
        
        // Update summary cards
        qs('#totalEmployees').textContent = data.summary.total_employees;
        qs('#presentToday').textContent = data.summary.present_today;
        qs('#lateToday').textContent = data.summary.late_today;
        qs('#absentToday').textContent = data.summary.absent_today;
        
        // Render charts
        renderTodayLateChart(data.today_late);
        renderMonthlyPerformanceCharts(data.monthly_stats);
        renderAttendanceTrendChart(data.attendance_trend);
        
        // Initialize KPI filter options first
        initKPIFilterOptions();
        
        // Load KPI data
        loadKPIData();
        
    } catch (error) {
        console.error('Error loading dashboard:', error);
        showNotif('Gagal memuat data dashboard', false);
    }
}

function renderTodayLateChart(todayLateData) {
    const ctx = qs('#todayLateChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (dashboardCharts.todayLate) {
        dashboardCharts.todayLate.destroy();
    }
    
    if (todayLateData.length === 0) {
        ctx.style.display = 'none';
        ctx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada pegawai yang terlambat hari ini</div>';
        return;
    }
    
    ctx.style.display = 'block';
    
    // Create a horizontal bar chart with employee photos
    const chartContainer = ctx.parentElement;
    chartContainer.innerHTML = `
        <div class="space-y-4">
            ${todayLateData.map((item, index) => {
                const checkInTime = item.jam_masuk ? item.jam_masuk.substring(0, 5) : 'N/A';
                const delayMinutes = item.jam_masuk ? 
                    Math.max(0, (parseInt(item.jam_masuk.split(':')[0]) - 8) * 60 + parseInt(item.jam_masuk.split(':')[1])) : 0;
                
                return `
                    <div class="bg-white border border-red-200 rounded-lg p-4 shadow-sm">
                        <div class="flex items-center space-x-4">
                            <div class="relative">
                                <img src="${item.foto_base64 || 'generate-avatar.php?background=ef4444&color=fff&name=' + encodeURIComponent(item.nama) + '&size=64'}" 
                                     alt="${item.nama}" 
                                     class="w-16 h-16 rounded-full border-2 border-red-300" style="object-fit: contain;">
                                <div class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                    ${index + 1}
                                </div>
                            </div>
                            <div class="flex-1">
                                <h4 class="font-semibold text-gray-800 text-lg">${item.nama}</h4>
                                <p class="text-sm text-red-600">Jam Masuk: ${checkInTime}</p>
                                ${delayMinutes > 0 ? `<p class="text-xs text-red-500">Terlambat ${delayMinutes} menit</p>` : ''}
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-bold text-red-600">${checkInTime}</div>
                                <div class="text-xs text-gray-500">Jam Masuk</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('')}
        </div>
    `;
}

function renderMonthlyPerformanceCharts(monthlyStats) {
    // Most Frequently Late Chart - Bar Chart
    const mostLateCtx = qs('#mostLateChart');
    if (mostLateCtx) {
        if (dashboardCharts.mostLate) {
            dashboardCharts.mostLate.destroy();
        }
        
        const topLate = monthlyStats.slice(0, 5).filter(item => item.late_count > 0);
        
        if (topLate.length === 0) {
            mostLateCtx.style.display = 'none';
            mostLateCtx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data keterlambatan bulan ini</div>';
        } else {
            mostLateCtx.style.display = 'block';
            
            // Create bar chart with employee photos
            const lateContainer = mostLateCtx.parentElement;
            lateContainer.innerHTML = `
                <div class="space-y-4">
                    ${topLate.map((item, index) => {
                        const maxLate = Math.max(...topLate.map(x => x.late_count));
                        const percentage = (item.late_count / maxLate) * 100;
                        
                        return `
                            <div class="bg-white border border-red-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-center space-x-4 mb-3">
                                    <div class="relative">
                                        <img src="${item.foto_base64 || 'generate-avatar.php?background=ef4444&color=fff&name=' + encodeURIComponent(item.nama) + '&size=48'}" 
                                             alt="${item.nama}" 
                                             class="w-12 h-12 rounded-full border-2 border-red-300" style="object-fit: contain;">
                                        <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                                            ${index + 1}
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800">${item.nama}</h4>
                                        <p class="text-sm text-red-600">${item.late_count} kali terlambat</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-xl font-bold text-red-600">${item.late_count}</div>
                                        <div class="text-xs text-gray-500">kali</div>
                                    </div>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-gradient-to-r from-red-400 to-red-600 h-3 rounded-full transition-all duration-500" 
                                         style="width: ${percentage}%"></div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
    }
    
    // Most Attentive Chart - Pie Chart Style
    const mostAttentiveCtx = qs('#mostAttentiveChart');
    if (mostAttentiveCtx) {
        if (dashboardCharts.mostAttentive) {
            dashboardCharts.mostAttentive.destroy();
        }
        
        const topAttentive = monthlyStats
            .filter(item => item.ontime_count > 0)
            .sort((a, b) => b.ontime_count - a.ontime_count)
            .slice(0, 5);
        
        if (topAttentive.length === 0) {
            mostAttentiveCtx.style.display = 'none';
            mostAttentiveCtx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data kehadiran bulan ini</div>';
        } else {
            mostAttentiveCtx.style.display = 'block';
            
            // Create pie chart style layout with employee photos
            const attentiveContainer = mostAttentiveCtx.parentElement;
            const totalOnTime = topAttentive.reduce((sum, item) => sum + item.ontime_count, 0);
            
            attentiveContainer.innerHTML = `
                <div class="grid grid-cols-1 gap-4">
                    ${topAttentive.map((item, index) => {
                        const percentage = ((item.ontime_count / totalOnTime) * 100).toFixed(1);
                        const colors = ['#22c55e', '#16a34a', '#15803d', '#166534', '#14532d'];
                        
                        return `
                            <div class="bg-white border border-green-200 rounded-lg p-4 shadow-sm">
                                <div class="flex items-center space-x-4">
                                    <div class="relative">
                                        <div class="w-16 h-16 rounded-full flex items-center justify-center" 
                                             style="background: conic-gradient(${colors[index]} 0deg ${percentage * 3.6}deg, #e5e7eb ${percentage * 3.6}deg 360deg)">
                                            <img src="${item.foto_base64 || 'generate-avatar.php?background=22c55e&color=fff&name=' + encodeURIComponent(item.nama) + '&size=48'}" 
                                                 alt="${item.nama}" 
                                                 class="w-12 h-12 rounded-full border-2 border-white" style="object-fit: contain;">
                                        </div>
                                        <div class="absolute -top-2 -right-2 bg-green-500 text-white text-xs rounded-full w-6 h-6 flex items-center justify-center font-bold">
                                            ${index + 1}
                                        </div>
                                    </div>
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-800 text-lg">${item.nama}</h4>
                                        <p class="text-sm text-green-600">${item.ontime_count} kali tepat waktu</p>
                                        <p class="text-xs text-gray-500">${percentage}% dari total kehadiran</p>
                                    </div>
                                    <div class="text-right">
                                        <div class="text-2xl font-bold text-green-600">${item.ontime_count}</div>
                                        <div class="text-xs text-gray-500">kali</div>
                                    </div>
                                </div>
                            </div>
                        `;
                    }).join('')}
                </div>
            `;
        }
    }
}

function renderAttendanceTrendChart(trendData) {
    const ctx = qs('#attendanceTrendChart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (dashboardCharts.attendanceTrend) {
        dashboardCharts.attendanceTrend.destroy();
    }
    
    if (!trendData || trendData.length === 0) {
        ctx.style.display = 'none';
        ctx.parentElement.innerHTML = '<div class="text-center text-gray-500 py-8">Tidak ada data tren kehadiran</div>';
        return;
    }
    
    ctx.style.display = 'block';
    
    const labels = trendData.map(item => item.day);
    const presentData = trendData.map(item => item.present);
    const lateData = trendData.map(item => item.late);
    const absentData = trendData.map(item => item.absent);
    
    dashboardCharts.attendanceTrend = new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Kejadian On-Time',
                    data: presentData,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#22c55e',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                },
                {
                    label: 'Kejadian Terlambat',
                    data: lateData,
                    borderColor: '#f59e0b',
                    backgroundColor: 'rgba(245, 158, 11, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#f59e0b',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                },
                {
                    label: 'Kejadian Tidak Hadir',
                    data: absentData,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#ef4444',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        usePointStyle: true,
                        padding: 20,
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    borderColor: '#ffffff',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        }
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12,
                            weight: 'bold'
                        }
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });
}

// KPI Functions
async function loadKPIData() {
    try {
        console.log('Loading KPI data...');
        
        // Get filter parameters
        const filterType = kpiFilterType ? kpiFilterType.value : 'period';
        const month = kpiFilterMonth ? kpiFilterMonth.value : '';
        const year = kpiFilterYear ? kpiFilterYear.value : '';
        
        // Build query parameters
        const params = new URLSearchParams();
        if (filterType === 'monthly' && month && year) {
            params.append('filter_type', 'monthly');
            params.append('month', month);
            params.append('year', year);
            console.log('KPI Filter: Monthly mode -', month, year);
        } else {
            params.append('filter_type', 'period');
            console.log('KPI Filter: Period mode');
        }
        
        const response = await fetch(`?ajax=get_kpi_data&${params.toString()}`);
        const result = await response.json();
        
        console.log('KPI response:', result);
        
        if (!result.ok) {
            console.error('KPI API error:', result.message);
            showNotif('Gagal memuat data KPI: ' + (result.message || 'Unknown error'), false);
            return;
        }
        
        if (!result.data || !result.data.kpi_data) {
            console.error('No KPI data in response');
            showNotif('Tidak ada data KPI tersedia', false);
            return;
        }
        
        console.log('KPI data loaded:', result.data.kpi_data.length, 'employees');
        renderKPITable(result.data);
        
    } catch (error) {
        console.error('Error loading KPI data:', error);
        showNotif('Gagal memuat data KPI: ' + error.message, false);
    }
}

function renderKPITable(kpiData) {
    const tbody = qs('#kpi-table-body');
    const loading = qs('#kpi-loading');
    const empty = qs('#kpi-empty');
    const periodRange = qs('#kpi-period-range');
    
    if (!tbody || !loading || !empty || !periodRange) return;
    
    // Hide loading
    loading.style.display = 'none';
    
    // Update period range
    const filterType = kpiFilterType ? kpiFilterType.value : 'period';
    if (filterType === 'monthly') {
        const month = kpiFilterMonth ? kpiFilterMonth.value : '';
        const year = kpiFilterYear ? kpiFilterYear.value : '';
        const monthNames = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
        periodRange.textContent = `${monthNames[month]} ${year}`;
    } else {
        periodRange.textContent = `${kpiData.period_start} - ${kpiData.period_end}`;
    }
    
    // // Add note about individual employee periods
    // const periodNote = document.createElement('p');
    // periodNote.className = 'text-xs text-gray-500 mt-1';
    // periodNote.textContent = filterType === 'monthly' 
    //     ? 'Perhitungan KPI untuk bulan yang dipilih (disesuaikan dengan tanggal registrasi masing-masing pegawai)'
    //     : 'Periode perhitungan disesuaikan dengan tanggal registrasi masing-masing pegawai';
    // periodRange.parentNode.appendChild(periodNote);
    
    // if (!kpiData.kpi_data || kpiData.kpi_data.length === 0) {
    //     empty.style.display = 'block';
    //     tbody.innerHTML = '';
    //     return;
    // }
    
    // Hide empty message
    empty.style.display = 'none';
    
    // Render table rows
    tbody.innerHTML = kpiData.kpi_data.map((employee, index) => {
        const statusClass = getKPIStatusClass(employee.kpi_score);
        const statusText = getKPIStatusText(employee.kpi_score);
        
        return `
            <tr class="hover:bg-gray-50">
                <td class="px-4 py-3 text-gray-900">${index + 1}</td>
                <td class="px-4 py-3 text-gray-900 font-medium">${employee.nama}</td>
                <td class="px-4 py-3 text-center text-gray-700">${employee.total_working_days}</td>
                <td class="px-4 py-3 text-center text-green-600 font-semibold">${employee.ontime_count}</td>
                <td class="px-4 py-3 text-center text-red-600 font-semibold">${employee.late_count}</td>
                <td class="px-4 py-3 text-center text-yellow-600 font-semibold">${employee.izin_sakit_count}</td>
                <td class="px-4 py-3 text-center text-gray-600 font-semibold">${employee.alpha_count}</td>
                <td class="px-4 py-3 text-center text-emerald-600 font-semibold">${employee.overtime_count || 0}</td>
                <td class="px-4 py-3 text-center">
                    <span class="px-2 py-1 rounded-full text-sm font-semibold ${statusClass}">
                        ${employee.kpi_score}%
                    </span>
                </td>
                <td class="px-4 py-3 text-center">
                    <span class="text-sm ${statusClass}">${statusText}</span>
                </td>
            </tr>
        `;
    }).join('');
}

function getKPIStatusClass(score) {
    if (score >= 90) return 'bg-green-100 text-green-800';
    if (score >= 80) return 'bg-blue-100 text-blue-800';
    if (score >= 70) return 'bg-yellow-100 text-yellow-800';
    if (score >= 60) return 'bg-orange-100 text-orange-800';
    return 'bg-red-100 text-red-800';
}

function getKPIStatusText(score) {
    if (score >= 90) return 'Excellent';
    if (score >= 80) return 'Good';
    if (score >= 70) return 'Fair';
    if (score >= 60) return 'Poor';
    return 'Very Poor';
}

// Refresh KPI button handler
qs('#refresh-kpi') && qs('#refresh-kpi').addEventListener('click', () => {
    qs('#kpi-loading').style.display = 'block';
    qs('#kpi-empty').style.display = 'none';
    loadKPIData();
});

// KPI Filter handlers
const kpiFilterType = qs('#kpi-filter-type');
const kpiFilterMonth = qs('#kpi-filter-month');
const kpiFilterYear = qs('#kpi-filter-year');

// Initialize month and year options
function initKPIFilterOptions() {
    if (!kpiFilterMonth || !kpiFilterYear) return;
    
    // Populate months
    const months = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    kpiFilterMonth.innerHTML = '<option value="">Pilih Bulan</option>';
    months.forEach((month, index) => {
        const option = document.createElement('option');
        option.value = index + 1;
        option.textContent = month;
        kpiFilterMonth.appendChild(option);
    });
    
    // Populate years (current year and previous 2 years)
    const currentYear = new Date().getFullYear();
    kpiFilterYear.innerHTML = '<option value="">Pilih Tahun</option>';
    for (let year = currentYear; year >= currentYear - 2; year--) {
        const option = document.createElement('option');
        option.value = year;
        option.textContent = year;
        kpiFilterYear.appendChild(option);
    }
}

// Show/hide month and year filters based on filter type
kpiFilterType && kpiFilterType.addEventListener('change', (e) => {
    const isMonthly = e.target.value === 'monthly';
    console.log('Filter type changed to:', e.target.value, 'isMonthly:', isMonthly);
    if (kpiFilterMonth) kpiFilterMonth.classList.toggle('hidden', !isMonthly);
    if (kpiFilterYear) kpiFilterYear.classList.toggle('hidden', !isMonthly);
    
    if (isMonthly) {
        // Set current month and year as default
        const now = new Date();
        if (kpiFilterMonth) kpiFilterMonth.value = now.getMonth() + 1;
        if (kpiFilterYear) kpiFilterYear.value = now.getFullYear();
        console.log('Set default month/year:', now.getMonth() + 1, now.getFullYear());
    }
    
    // Reload data when filter type changes
    loadKPIData();
});

// Load KPI data when month/year changes
kpiFilterMonth && kpiFilterMonth.addEventListener('change', () => {
    console.log('Month changed to:', kpiFilterMonth.value);
    if (kpiFilterType && kpiFilterType.value === 'monthly') {
        loadKPIData();
    }
});

kpiFilterYear && kpiFilterYear.addEventListener('change', () => {
    console.log('Year changed to:', kpiFilterYear.value);
    if (kpiFilterType && kpiFilterType.value === 'monthly') {
        loadKPIData();
    }
});

// Initialize filter options on page load
initKPIFilterOptions();

document.addEventListener('click', async (e)=>{
    if(e.target.classList.contains('btn-am-approve')||e.target.classList.contains('btn-am-disapprove')){
        const id = e.target.getAttribute('data-id'); const status = e.target.classList.contains('btn-am-approve') ? 'approved' : 'disapproved';
        showConfirmModal('Yakin set status laporan bulanan?', async ()=>{ await api('?ajax=admin_set_monthly_status', { id, status }); renderAdminMonthly(); });
    }
});
<?php endif; ?>

// Tambahkan event listener untuk tombol-tombol di tabel laporan bulanan
document.addEventListener('click', async (e) => {
    const target = e.target.closest('.btn-create-month, .btn-edit-month, .page-btn');
    if (!target) return;

    if (target.classList.contains('page-btn')) {
        currentMonthlyPageYear = parseInt(target.dataset.year);
        renderMonthly();
        return;
    }

    // Tampilkan form di atas daftar
    pageMonthlyForm.classList.remove('hidden');
    pageMonthlyForm.scrollIntoView({ behavior: 'smooth', block: 'start' });

    const isViewOnly = false; // Hanya untuk create dan edit
    
    let year, month, reportData = null;

    if (target.classList.contains('btn-create-month')) {
        year = parseInt(target.dataset.year);
        month = parseInt(target.dataset.month);
        qs('#monthly-form-title').textContent = `Buat Laporan Bulan ${monthName(month-1)} ${year}`;
    } else { // Edit
        reportData = JSON.parse(target.dataset.json.replace(/&apos;/g, "'"));
        year = parseInt(reportData.year) || 0;
        month = parseInt(reportData.month) || 0;
        qs('#monthly-form-title').textContent = `Edit Laporan Bulan ${monthName(month-1)} ${year}`;
    }

    // Set info pegawai di form
    qs('#pegawai-info-monthly-form').innerHTML = qs('#pegawai-info-monthly').innerHTML;
    
    // Reset dan isi form
    qs('#form-monthly-report').reset();
    qs('#table-achievements-body').innerHTML = '';
    qs('#table-obstacles-body').innerHTML = '';
    qs('#monthly-report-year').value = year;
    qs('#monthly-report-month').value = month;

    if (reportData) {
        qs('#monthly-summary').value = reportData.summary || '';
        const achievements = JSON.parse(reportData.achievements || '[]');
        const obstacles = JSON.parse(reportData.obstacles || '[]');
        achievements.forEach(addAchievementRow);
        obstacles.forEach(addObstacleRow);
    } else {
        // Tambah satu baris kosong saat membuat baru
        addAchievementRow();
        addObstacleRow();
    }
    
    // Semua field dapat diedit untuk create dan edit
    const fields = qsa('#form-monthly-report input, #form-monthly-report textarea, #form-monthly-report button');
    fields.forEach(field => {
        // Jangan disable tombol kembali
        if(field.id !== 'btn-back-to-monthly-list') {
            field.disabled = false;
        }
    });

    // Tampilkan semua tombol simpan
    qs('#btn-save-draft').style.display = 'inline-block';
    qs('button[type="submit"]', qs('#form-monthly-report')).style.display = 'inline-block';
});

// Register Service Worker for offline functionality
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js')
            .then(registration => {
                console.log('SW registered: ', registration);
            })
            .catch(registrationError => {
                console.log('SW registration failed: ', registrationError);
            });
    });
}

// Work Schedule Modal Functions
async function openWorkScheduleModal(userId, userName) {
    const modal = qs('#work-schedule-modal');
    const userSelect = qs('#work-schedule-user');
    const form = qs('#work-schedule-form');
    const startDateInput = qs('#work-start-date');
    
    // Load members for dropdown
    const membersRes = await fetch('?ajax=get_members');
    const membersData = await membersRes.json();
    const members = membersData.data || [];
    
    // Populate user dropdown
    userSelect.innerHTML = '<option value="">Pilih pegawai...</option>';
    members.forEach(member => {
        const option = document.createElement('option');
        option.value = member.id;
        option.textContent = `${member.nama} (${member.nim})`;
        if (member.id == userId) {
            option.selected = true;
        }
        userSelect.appendChild(option);
    });
    
    // Load schedule for selected user
    if (userId) {
        await loadWorkSchedule(userId);
        form.classList.remove('hidden');
        // Preload current start date from member JSON if available
        try{
            const membersRes2 = await fetch('?ajax=get_members');
            const md = await membersRes2.json();
            const m = (md.data||[]).find(x=>x.id==userId);
            if(m && m.created_at && startDateInput){ startDateInput.value = (m.work_start_date||m.created_at||'').slice(0,10); }
        }catch{}
    } else {
        form.classList.add('hidden');
    }
    
    modal.classList.remove('hidden');
}

async function loadWorkSchedule(userId) {
    try {
        const response = await api('?ajax=admin_get_work_schedule', { user_id: userId });
        if (response.ok) {
            const schedule = response.data;
            renderWorkScheduleDays(schedule);
        } else {
            showNotif('Gagal memuat jadwal kerja', false);
        }
    } catch (error) {
        console.error('Error loading work schedule:', error);
        showNotif('Gagal memuat jadwal kerja', false);
    }
}

function renderWorkScheduleDays(schedule) {
    const container = qs('#work-schedule-days');
    container.innerHTML = '';
    
    const days = [
        { key: 'monday', label: 'Senin' },
        { key: 'tuesday', label: 'Selasa' },
        { key: 'wednesday', label: 'Rabu' },
        { key: 'thursday', label: 'Kamis' },
        { key: 'friday', label: 'Jumat' },
        { key: 'saturday', label: 'Sabtu' },
        { key: 'sunday', label: 'Minggu' }
    ];
    
    days.forEach(day => {
        const dayData = schedule[day.key] || {
            is_working_day: ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'].includes(day.key),
            start_time: '08:00:00',
            end_time: '17:00:00'
        };
        
        const row = document.createElement('div');
        row.className = 'grid grid-cols-7 gap-2 items-center p-2 border rounded';
        row.innerHTML = `
            <div class="font-medium">${day.label}</div>
            <div>
                <input type="checkbox" ${dayData.is_working_day ? 'checked' : ''} 
                       class="work-day-checkbox" data-day="${day.key}">
            </div>
            <div>
                <input type="time" value="${dayData.start_time}" 
                       class="work-start-time w-full p-1 border rounded text-sm" data-day="${day.key}">
            </div>
            <div>
                <input type="time" value="${dayData.end_time}" 
                       class="work-end-time w-full p-1 border rounded text-sm" data-day="${day.key}">
            </div>
            <div class="text-sm text-gray-600 work-duration" data-day="${day.key}">
                ${calculateDuration(dayData.start_time, dayData.end_time)}
            </div>
            <div class="text-sm">
                <span class="work-status px-2 py-1 rounded text-xs ${dayData.is_working_day ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}" data-day="${day.key}">
                    ${dayData.is_working_day ? 'Bekerja' : 'Libur'}
                </span>
            </div>
            <div>
                <button type="button" class="copy-schedule-btn text-blue-600 hover:text-blue-800 text-sm" data-day="${day.key}">
                    Copy
                </button>
            </div>
        `;
        
        container.appendChild(row);
    });
    
    // Add event listeners
    addWorkScheduleEventListeners();
}

function addWorkScheduleEventListeners() {
    // Handle checkbox changes
    qsa('.work-day-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const day = this.dataset.day;
            const statusSpan = qs(`.work-status[data-day="${day}"]`);
            const startTime = qs(`.work-start-time[data-day="${day}"]`);
            const endTime = qs(`.work-end-time[data-day="${day}"]`);
            
            if (this.checked) {
                statusSpan.textContent = 'Bekerja';
                statusSpan.className = 'work-status px-2 py-1 rounded text-xs bg-green-100 text-green-800';
                startTime.disabled = false;
                endTime.disabled = false;
            } else {
                statusSpan.textContent = 'Libur';
                statusSpan.className = 'work-status px-2 py-1 rounded text-xs bg-gray-100 text-gray-800';
                startTime.disabled = true;
                endTime.disabled = true;
            }
            updateDuration(day);
        });
    });
    
    // Handle time changes
    qsa('.work-start-time, .work-end-time').forEach(input => {
        input.addEventListener('change', function() {
            const day = this.dataset.day;
            updateDuration(day);
        });
    });
    
    // Handle copy buttons
    qsa('.copy-schedule-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const day = this.dataset.day;
            const checkbox = qs(`.work-day-checkbox[data-day="${day}"]`);
            const startTime = qs(`.work-start-time[data-day="${day}"]`);
            const endTime = qs(`.work-end-time[data-day="${day}"]`);
            
            // Copy to all other days
            qsa('.work-day-checkbox').forEach(otherCheckbox => {
                if (otherCheckbox.dataset.day !== day) {
                    otherCheckbox.checked = checkbox.checked;
                    otherCheckbox.dispatchEvent(new Event('change'));
                }
            });
            
            qsa('.work-start-time').forEach(otherStart => {
                if (otherStart.dataset.day !== day) {
                    otherStart.value = startTime.value;
                }
            });
            
            qsa('.work-end-time').forEach(otherEnd => {
                if (otherEnd.dataset.day !== day) {
                    otherEnd.value = endTime.value;
                }
            });
            
            // Update all durations
            qsa('.work-day-checkbox').forEach(cb => updateDuration(cb.dataset.day));
            
            showNotif('Jadwal berhasil disalin ke semua hari');
        });
    });
}

function updateDuration(day) {
    const startTime = qs(`.work-start-time[data-day="${day}"]`);
    const endTime = qs(`.work-end-time[data-day="${day}"]`);
    const durationSpan = qs(`.work-duration[data-day="${day}"]`);
    
    if (startTime && endTime && durationSpan) {
        durationSpan.textContent = calculateDuration(startTime.value, endTime.value);
    }
}

function calculateDuration(startTime, endTime) {
    if (!startTime || !endTime) return '0h 0m';
    
    const start = new Date(`2000-01-01 ${startTime}`);
    const end = new Date(`2000-01-01 ${endTime}`);
    
    if (end <= start) return '0h 0m';
    
    const diffMs = end - start;
    const hours = Math.floor(diffMs / (1000 * 60 * 60));
    const minutes = Math.floor((diffMs % (1000 * 60 * 60)) / (1000 * 60));
    
    return `${hours}h ${minutes}m`;
}

// Work Schedule Modal Event Listeners
qs('#work-schedule-close') && qs('#work-schedule-close').addEventListener('click', () => {
    qs('#work-schedule-modal').classList.add('hidden');
});

qs('#work-schedule-cancel') && qs('#work-schedule-cancel').addEventListener('click', () => {
    qs('#work-schedule-modal').classList.add('hidden');
});

qs('#work-schedule-user') && qs('#work-schedule-user').addEventListener('change', async function() {
    const userId = this.value;
    const form = qs('#work-schedule-form');
    
    if (userId) {
        await loadWorkSchedule(userId);
        form.classList.remove('hidden');
    } else {
        form.classList.add('hidden');
    }
});

qs('#work-schedule-save') && qs('#work-schedule-save').addEventListener('click', async function() {
    const userId = qs('#work-schedule-user').value;
    
    if (!userId) {
        showNotif('Pilih pegawai terlebih dahulu', false);
        return;
    }
    
    // Collect schedule data
    const schedule = {};
    qsa('.work-day-checkbox').forEach(checkbox => {
        const day = checkbox.dataset.day;
        const startTime = qs(`.work-start-time[data-day="${day}"]`).value;
        const endTime = qs(`.work-end-time[data-day="${day}"]`).value;
        
        schedule[day] = {
            is_working_day: checkbox.checked,
            start_time: startTime,
            end_time: endTime
        };
    });
    
    try {
        const response = await api('?ajax=admin_save_work_schedule', {
            user_id: userId,
            schedule: schedule
        });
        
        if (response.ok) {
            // Save per-user work start date setting if provided
            const startDateVal = qs('#work-start-date')?.value || '';
            if(startDateVal){ await api('?ajax=save_setting', { key: `work_start_date_user_${userId}`, value: startDateVal }); }
            showNotif('Jadwal kerja berhasil disimpan');
            qs('#work-schedule-modal').classList.add('hidden');
        } else {
            showNotif(response.message || 'Gagal menyimpan jadwal kerja', false);
        }
    } catch (error) {
        console.error('Error saving work schedule:', error);
        showNotif('Gagal menyimpan jadwal kerja', false);
    }
});
</script>
</body>
</html>