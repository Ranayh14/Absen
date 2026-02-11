<?php
session_start();
date_default_timezone_set('Asia/Jakarta');

// Global action variable from GET or POST
$action = $_GET['ajax'] ?? $_POST['ajax'] ?? null;

// Production-optimized PHP settings
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . DIRECTORY_SEPARATOR . 'php-error.log');
ini_set('log_errors_max_len', '1024'); // Limit error log entry size
error_reporting(E_ALL);
ini_set('display_errors', '0'); // Never show errors in production

// Increase limits for large datasets (production hosting)
@ini_set('memory_limit', '256M'); // Increase from default 128M
@ini_set('max_execution_time', '60'); // Prevent infinite hangs

error_log('bootstrap: index.php started');

// Load Composer autoloader for Google Authenticator
if (file_exists(dirname(__DIR__) . '/vendor/autoload.php')) {
    require_once dirname(__DIR__) . '/vendor/autoload.php';
}

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
            alasan_overtime TEXT NULL,
            lokasi_overtime VARCHAR(255) NULL,
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
        'alasan_overtime' => "ALTER TABLE attendance ADD COLUMN alasan_overtime TEXT NULL AFTER alasan_wfa",
        'lokasi_overtime' => "ALTER TABLE attendance ADD COLUMN lokasi_overtime VARCHAR(255) NULL AFTER alasan_overtime",
        'alasan_izin_sakit' => "ALTER TABLE attendance ADD COLUMN alasan_izin_sakit TEXT NULL AFTER lokasi_overtime",
        'bukti_izin_sakit' => "ALTER TABLE attendance ADD COLUMN bukti_izin_sakit LONGTEXT NULL AFTER alasan_izin_sakit",
        'daily_report_id' => "ALTER TABLE attendance ADD COLUMN daily_report_id INT NULL AFTER ket",
        'alasan_pulang_awal' => "ALTER TABLE attendance ADD COLUMN alasan_pulang_awal TEXT NULL AFTER bukti_izin_sakit"
    ];
    
            // Add FaceNet embedding columns to users table
            $userColumns = [
                'face_embedding' => "ALTER TABLE users ADD COLUMN face_embedding LONGTEXT NULL AFTER foto_base64",
                'face_embedding_updated' => "ALTER TABLE users ADD COLUMN face_embedding_updated TIMESTAMP NULL AFTER face_embedding",
                'advanced_features' => "ALTER TABLE users ADD COLUMN advanced_features LONGTEXT NULL AFTER face_embedding_updated",
                'facial_geometry' => "ALTER TABLE users ADD COLUMN facial_geometry LONGTEXT NULL AFTER advanced_features",
                'feature_vector' => "ALTER TABLE users ADD COLUMN feature_vector LONGTEXT NULL AFTER facial_geometry",
                'google_authenticator_secret' => "ALTER TABLE users ADD COLUMN google_authenticator_secret VARCHAR(255) NULL AFTER password_hash",
                'password_reset_token' => "ALTER TABLE users ADD COLUMN password_reset_token VARCHAR(255) NULL AFTER google_authenticator_secret",
                'password_reset_expires' => "ALTER TABLE users ADD COLUMN password_reset_expires DATETIME NULL AFTER password_reset_token"
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

    // Admin help requests table
    $pdo->exec(
        "CREATE TABLE IF NOT EXISTS admin_help_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            request_type ENUM('past_attendance', 'late_attendance', 'bug_report') NOT NULL,
            tanggal DATE NULL,
            jam_masuk TIME NULL,
            jam_pulang TIME NULL,
            alasan_izin TEXT NULL,
            jenis_izin ENUM('izin', 'sakit') NULL,
            bukti_izin LONGTEXT NULL,
            bukti_presensi LONGTEXT NULL,
            lokasi_presensi VARCHAR(255) NULL,
            bug_description TEXT NULL,
            bug_proof LONGTEXT NULL,
            status ENUM('pending', 'approved', 'disapproved', 'solved') DEFAULT 'pending',
            admin_note TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            INDEX(status),
            CONSTRAINT fk_ahr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    // Migration for status ENUM in admin_help_requests
    try {
        $pdo->exec("ALTER TABLE admin_help_requests MODIFY COLUMN status ENUM('pending', 'approved', 'disapproved', 'solved') DEFAULT 'pending'");
    } catch (PDOException $e) {}

    // Add is_read_by_user column for employee notifications
    try {
        $pdo->exec("ALTER TABLE admin_help_requests ADD COLUMN is_read_by_user BOOLEAN DEFAULT FALSE AFTER admin_note");
    } catch (PDOException $e) {}

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
        ['wfo_wifi_ssids', 'Telkom University,TelU,WiFi Telkom University,WiFi-TelU,Telkom-University,TelU-Connect,TelU-Guest', 'Daftar SSID WiFi yang valid untuk WFO (dipisah koma)'],
        ['wfo_require_wifi', '1', 'Wajib menggunakan WiFi Telkom University untuk presensi WFO (1=Ya, 0=Tidak)'],
        ['attendance_period_end', date('Y-12-31'), 'Tanggal akhir periode perhitungan absen (YYYY-MM-DD)'],
        ['kpi_late_penalty_per_minute', '1', 'Pengurangan KPI per menit terlambat (%)'],
        ['kpi_izin_sakit_score', '85', 'Nilai KPI untuk izin/sakit (%)'],
        ['kpi_alpha_score', '0', 'Nilai KPI untuk alpha (%)'],
        ['kpi_overtime_bonus', '5', 'Bonus KPI untuk overtime (%)'],
        ['max_daily_report_days_back', '5', 'Maksimal hari kebelakang untuk isi laporan harian (default: 5)'],
        ['max_monthly_report_months_back', '999', 'Maksimal bulan kebelakang untuk isi laporan bulanan (default: 999 = tidak terbatas)'],
        ['monthly_report_end_year', '2026', 'Tahun akhir untuk laporan bulanan (default: 2026)'],
        ['face_recognition_threshold', '0.38', 'Threshold untuk face recognition (0.0-1.0, semakin rendah semakin ketat, default: 0.38)'],
        ['face_recognition_input_size', '416', 'Ukuran input untuk face detection (semakin besar semakin akurat tapi lebih lambat, default: 416)'],
        ['face_recognition_score_threshold', '0.35', 'Score threshold untuk face detection (0.0-1.0, default: 0.35)'],
        ['face_recognition_quality_threshold', '0.55', 'Quality threshold untuk validasi wajah (0.0-1.0, default: 0.55)'],
        ['geocode_timeout', '3', 'Timeout untuk reverse geocoding dalam detik (default: 3)'],
        ['geocode_accuracy_radius', '50', 'Radius akurasi GPS dalam meter untuk validasi lokasi (default: 50)']
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
 * Robust HTTP request helper that tries cURL first and file_get_contents as fallback.
 */
function httpRequest(string $url, array $headers = [], int $timeout = 10): ?string {
    // Try cURL
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $resp) return $resp;
    }
    
    // Try file_get_contents as fallback
    if (ini_get('allow_url_fopen')) {
        $headerStr = "";
        foreach ($headers as $h) {
            $headerStr .= $h . "\r\n";
        }
        
        $opts = [
            "http" => [
                "method" => "GET",
                "header" => $headerStr ?: "User-Agent: AbsenApp/1.0\r\n",
                "timeout" => $timeout
            ],
            "ssl" => [
                "verify_peer" => false,
                "verify_peer_name" => false
            ]
        ];
        $context = stream_context_create($opts);
        $resp = @file_get_contents($url, false, $context);
        if ($resp) return $resp;
    }
    
    return null;
}

/**
 * Search for addresses using Google Geocoding API.
 * Returns an array of results with display_name, lat, and lon.
 */
function searchAddressGoogle(string $query): array {
    $apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?address=" . urlencode($query) . "&key={$apiKey}&language=id&region=id";
    
    $resp = httpRequest($url);
    
    if (!$resp) {
        // Fallback to Nominatim if Google fails
        return searchAddressNominatim($query);
    }
    
    $data = json_decode($resp, true);
    if (!isset($data['status']) || ($data['status'] !== 'OK' && $data['status'] !== 'ZERO_RESULTS') || empty($data['results'])) {
        return searchAddressNominatim($query);
    }
    
    $results = [];
    foreach ($data['results'] as $res) {
        $results[] = [
            'display_name' => $res['formatted_address'],
            'lat' => $res['geometry']['location']['lat'],
            'lon' => $res['geometry']['location']['lng'],
            'place_id' => $res['place_id'],
            'type' => 'google'
        ];
    }
    
    return $results;
}

/**
 * Search for addresses using Nominatim (fallback).
 */
function searchAddressNominatim(string $query): array {
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=5&addressdetails=1&countrycodes=id&q=' . urlencode($query);
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];
    
    $resp = httpRequest($url, $headers, 5);
    
    if (!$resp) return [];
    
    $data = json_decode($resp, true);
    if (!is_array($data)) return [];
    
    $results = [];
    foreach ($data as $res) {
        $results[] = [
            'display_name' => $res['display_name'],
            'lat' => $res['lat'],
            'lon' => $res['lon'],
            'place_id' => $res['place_id'],
            'type' => 'nominatim'
        ];
    }
    
    return $results;
}

/**
 * Geocode a free-form address string to [lat, lng] using OpenStreetMap Nominatim.
 * Returns ['lat' => float, 'lng' => float] or null on failure.
 */
function geocodeAddress(string $address): ?array {
    // Try Google first for better accuracy
    $googleResults = searchAddressGoogle($address);
    if (!empty($googleResults)) {
        return ['lat' => (float)$googleResults[0]['lat'], 'lng' => (float)$googleResults[0]['lon']];
    }
    
    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&addressdetails=0&q=' . urlencode($address);
    $headers = ['User-Agent: AbsenApp/1.0 (XAMPP PHP)'];
    
    $resp = httpRequest($url, $headers, 4);
    if (!$resp) return null;
    
    $data = json_decode($resp, true);
    if (!is_array($data) || empty($data) || !isset($data[0]['lat'], $data[0]['lon'])) return null;
    return ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon']];
}

/**
 * Reverse geocode coordinates to address using MULTIPLE providers for maximum accuracy.
 * ENHANCED VERSION with RT/RW extraction and detailed Indonesian address parsing.
 * Returns complete address with street name, number, RT/RW, kelurahan, postal code.
 */
function reverseGeocodeAddress(float $lat, float $lng): ?string {
    // TIER 1: Try Google Maps API (MOST ACCURATE - PRIMARY)
    $googleAddress = reverseGeocodeGoogle($lat, $lng);
    if ($googleAddress && !isGenericAddress($googleAddress)) {
        error_log("Geocoding SUCCESS: Google Maps - $googleAddress");
        return $googleAddress;
    }
    
    // TIER 2: Try with zoom 18 (maximum detail)
    $detailedAddress = reverseGeocodeNominatim($lat, $lng, 18);
    if ($detailedAddress && !isGenericAddress($detailedAddress)) {
        error_log("Geocoding SUCCESS: OSM Zoom 18 - $detailedAddress");
        return $detailedAddress;
    }
    
    // TIER 3: Try with zoom 17 (slightly broader, might have more data)
    $mediumAddress = reverseGeocodeNominatim($lat, $lng, 17);
    if ($mediumAddress && !isGenericAddress($mediumAddress)) {
        error_log("Geocoding SUCCESS: OSM Zoom 17 - $mediumAddress");
        return $mediumAddress;
    }
    
    // TIER 4: Fallback to coordinates
    error_log("All geocoding methods failed for lat=$lat, lng=$lng, using coordinates fallback");
    return "Koordinat: " . round($lat, 6) . ", " . round($lng, 6);
}

/**
 * Google Maps Geocoding API - PRIMARY PROVIDER (Most Accurate for Indonesian Addresses)
 */
function reverseGeocodeGoogle(float $lat, float $lng): ?string {
    $apiKey = 'AIzaSyCTdOHXg5hSu_2fneyBP9mItCLyG5VQ-x0';
    $url = "https://maps.googleapis.com/maps/api/geocode/json?latlng={$lat},{$lng}&key={$apiKey}&language=id&result_type=street_address|route|sublocality|premise";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$resp) {
        error_log("Google Geocoding API request failed: HTTP $httpCode, Error: $curlError");
        return null;
    }
    
    $data = json_decode($resp, true);
    
    if (!isset($data['status']) || $data['status'] !== 'OK') {
        $errorMsg = $data['error_message'] ?? $data['status'] ?? 'UNKNOWN';
        error_log("Google Geocoding API error: $errorMsg");
        return null;
    }
    
    if (empty($data['results'])) {
        error_log("Google Geocoding API: No results");
        return null;
    }
    
    // Get first result (most accurate)
    $result = $data['results'][0];
    $addressComponents = $result['address_components'] ?? [];
    $formattedAddress = $result['formatted_address'] ?? '';
    
    // Parse for Indonesian address format
    $houseNumber = '';
    $street = '';
    $rt = '';
    $rw = '';
    $kelurahan = '';
    $kecamatan = '';
    $city = '';
    $province = '';
    $postalCode = '';
    
    foreach ($addressComponents as $component) {
        $types = $component['types'];
        $longName = $component['long_name'];
        
        if (in_array('street_number', $types)) {
            $houseNumber = $longName;
        } elseif (in_array('route', $types)) {
            $street = $longName;
        } elseif (in_array('premise', $types) || in_array('establishment', $types)) {
            // Building name
            if (empty($houseNumber)) {
                $houseNumber = $longName;
            }
        } elseif (in_array('sublocality_level_4', $types) || in_array('neighborhood', $types)) {
            // Check for RT/RW
            if (preg_match('/RT[\s.]*0*([0-9]{1,3})[\s\/]*RW[\s.]*0*([0-9]{1,3})/i', $longName, $matches)) {
                $rt = str_pad($matches[1], 3, '0', STR_PAD_LEFT);
                $rw = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
            } elseif (empty($kelurahan)) {
                $kelurahan = $longName;
            }
        } elseif (in_array('sublocality_level_3', $types) || in_array('administrative_area_level_4', $types)) {
            $kelurahan = $longName;
        } elseif (in_array('sublocality_level_2', $types) || in_array('administrative_area_level_3', $types)) {
            $kecamatan = $longName;
        } elseif (in_array('administrative_area_level_2', $types) || in_array('locality', $types)) {
            $city = $longName;
        } elseif (in_array('administrative_area_level_1', $types)) {
            $province = $longName;
        } elseif (in_array('postal_code', $types)) {
            $postalCode = $longName;
        }
    }
    
    // Build Indonesian address
    $parts = [];
    
    // Street with number
    if ($houseNumber && $street) {
        $parts[] = "No. $houseNumber, Jl. $street";
    } elseif ($street) {
        $parts[] = "Jl. $street";
    } elseif ($houseNumber) {
        $parts[] = $houseNumber;
    }
    
    // RT/RW
    if ($rt && $rw) {
        $parts[] = "RT $rt/RW $rw";
    }
    
    // Kelurahan
    if ($kelurahan) {
        $parts[] = $kelurahan;
    }
    
    // Kecamatan
    if ($kecamatan) {
        $parts[] = $kecamatan;
    }
    
    // City
    if ($city) {
        $parts[] = $city;
    }
    
    // Province  
    if ($province) {
        $parts[] = $province;
    }
    
    // Postal code
    if ($postalCode) {
        $parts[] = $postalCode;
    }
    
    // Return detailed address if we have enough components
    if (!empty($parts) && count($parts) >= 3) {
        return implode(', ', $parts);
    }
    
    // Fallback to Google's formatted address
    if ($formattedAddress) {
        $cleanAddress = preg_replace('/,\s*Indonesia\s*$/', '', $formattedAddress);
        return $cleanAddress;
    }
    
    return null;
}

/**
 * Helper: Check if address is too generic (just city + postal)
 */
function isGenericAddress(string $address): bool {
    // If address only has 1-2 components (just city and postal code), it's too generic
    $parts = explode(', ', $address);
    return count($parts) <= 2;
}

/**
 * Core reverse geocoding using Nominatim with specified zoom
 */
function reverseGeocodeNominatim(float $lat, float $lng, int $zoom): ?string {
    $url = 'https://nominatim.openstreetmap.org/reverse?format=json&lat=' . $lat . '&lon=' . $lng . '&addressdetails=1&accept-language=id&zoom=' . $zoom . '&extratags=1&namedetails=1';
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // INCREASED from 1 to 5 seconds for better accuracy
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Connection timeout 3 seconds
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: AbsenApp/1.0 (XAMPP PHP)'
    ]);
    
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($code !== 200 || !$resp) {
        // Fallback to coordinates if geocoding fails
        error_log("Reverse geocoding failed for lat=$lat, lng=$lng");
        return "Koordinat: " . round($lat, 6) . ", " . round($lng, 6);
    }
    
    $data = json_decode($resp, true);
    if (!is_array($data) || !isset($data['address'])) {
        return "Koordinat: " . round($lat, 6) . ", " . round($lng, 6);
    }
    
    $address = $data['address'];
    $displayName = $data['display_name'] ?? '';
    
    // ENHANCED: Extract RT/RW from various address components
    $rt = '';
    $rw = '';
    
    // Pattern untuk mencari RT/RW dalam format: "RT 001/RW 002", "RT.01 RW.02", "RT 1 RW 2", etc
    $rtRwPattern = '/RT[\s.]*0*([0-9]{1,3})[\s\/]*RW[\s.]*0*([0-9]{1,3})/i';
    
    // Check dalam berbagai field yang mungkin mengandung RT/RW
    $searchFields = ['suburb', 'neighbourhood', 'hamlet', 'quarter', 'city_district', 'residential'];
    foreach ($searchFields as $field) {
        if (isset($address[$field]) && $address[$field]) {
            if (preg_match($rtRwPattern, $address[$field], $matches)) {
                $rt = str_pad($matches[1], 3, '0', STR_PAD_LEFT); // Format: 001, 002, etc
                $rw = str_pad($matches[2], 3, '0', STR_PAD_LEFT);
                break;
            }
        }
    }
    
    // Build DETAILED address from components with proper Indonesian order
    $parts = [];
    
    // 1. Building name atau house name (paling spesifik)
    if (isset($address['building']) && $address['building']) {
        $parts[] = $address['building'];
    } elseif (isset($address['house_name']) && $address['house_name']) {
        $parts[] = $address['house_name'];
    } elseif (isset($address['amenity']) && $address['amenity']) {
        $parts[] = $address['amenity'];
    }
    
    // 2. Road/Street dengan house number jika ada
    $roadParts = [];
    if (isset($address['house_number']) && $address['house_number']) {
        $roadParts[] = 'No. ' . $address['house_number'];
    }
    if (isset($address['road']) && $address['road']) {
        $roadParts[] = 'Jl. ' . $address['road'];
    } elseif (isset($address['pedestrian']) && $address['pedestrian']) {
        $roadParts[] = 'Jl. ' . $address['pedestrian'];
    } elseif (isset($address['footway']) && $address['footway']) {
        $roadParts[] = 'Jl. ' . $address['footway'];
    } elseif (isset($address['path']) && $address['path']) {
        $roadParts[] = $address['path'];
    }
    if (!empty($roadParts)) {
        $parts[] = implode(' ', $roadParts);
    }
    
    // 3. RT/RW jika ditemukan
    if ($rt && $rw) {
        $parts[] = "RT $rt/RW $rw";
    }
    
    // 4. Kelurahan/Desa (suburb/neighbourhood)
    if (isset($address['suburb']) && $address['suburb']) {
        // Skip jika suburb sama dengan RT/RW pattern (sudah diambil di atas)
        if (!preg_match($rtRwPattern, $address['suburb'])) {
            $parts[] = $address['suburb'];
        }
    } elseif (isset($address['neighbourhood']) && $address['neighbourhood']) {
        if (!preg_match($rtRwPattern, $address['neighbourhood'])) {
            $parts[] = $address['neighbourhood'];
        }
    } elseif (isset($address['hamlet']) && $address['hamlet']) {
        $parts[] = $address['hamlet'];
    } elseif (isset($address['village']) && $address['village']) {
        $parts[] = $address['village'];
    }
    
    // 5. Kecamatan (city_district)
    if (isset($address['city_district']) && $address['city_district']) {
        $parts[] = $address['city_district'];
    } elseif (isset($address['municipality']) && $address['municipality']) {
        $parts[] = $address['municipality'];
    }
    
    // 6. Kota/Kabupaten
    if (isset($address['city']) && $address['city']) {
        $parts[] = $address['city'];
    } elseif (isset($address['town']) && $address['town']) {
        $parts[] = $address['town'];
    } elseif (isset($address['county']) && $address['county']) {
        $parts[] = $address['county'];
    }
    
    // 7. Provinsi
    if (isset($address['state']) && $address['state']) {
        $parts[] = $address['state'];
    }
    
    // 8. Postal code (PENTING untuk alamat lengkap)
    if (isset($address['postcode']) && $address['postcode']) {
        $parts[] = $address['postcode'];
    }
    
    // If we have good parts, join them
    if (!empty($parts)) {
        $detailedAddress = implode(', ', $parts);
        
        // Log untuk debugging
        error_log("Reverse geocoding success: $detailedAddress (RT: $rt, RW: $rw)");
        
        return $detailedAddress;
    }
    
    // Fallback to display_name if no parts extracted
    if ($displayName) {
        // Clean up the display name
        $cleanName = preg_replace('/,\s*Indonesia$/', '', $displayName);
        
        // Try to append postal code if available
        if (isset($address['postcode']) && $address['postcode']) {
            if (strpos($cleanName, $address['postcode']) === false) {
                $cleanName .= ', ' . $address['postcode'];
            }
        }
        
        error_log("Reverse geocoding fallback to display_name: $cleanName");
        return $cleanName;
    }
    
    // Final fallback to coordinates
    error_log("Reverse geocoding no address found, using coordinates");
    return "Koordinat: " . round($lat, 6) . ", " . round($lng, 6);
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
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Reduced from 5 to 3 seconds for faster response
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2); // Connection timeout 2 seconds
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
 * Check if IP is in Telkom University private IP range
 * Telkom University uses private IP ranges: 10.x.x.x
 */
function isTelkomUniversityPrivateIp(string $ip): bool {
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        return false;
    }
    
    // Check if it's a private IP (10.x.x.x, 172.16-31.x.x, 192.168.x.x)
    $isPrivate = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    
    if (!$isPrivate) {
        return false; // Not a private IP
    }
    
    // Check if it's in Telkom University private IP range (10.x.x.x)
    // Based on screenshots: 10.60.43.33 (TelU-Connect) and 10.30.114.48 (TelU-Guest)
    // Telkom University uses 10.x.x.x range
    if (strpos($ip, '10.') === 0) {
        return true; // IP starts with 10. - likely Telkom University network
    }
    
    return false;
}

/**
 * Detect WFO by external IP information API or private IP range
 * Returns true if IP belongs to allowed org/ASN/CIDR list or Telkom University private IP range
 */
function isWfoByApi(PDO $pdo, ?string $publicIp = null): bool {
    // PERFORMANCE: Cache WFO check to avoid slow API calls
    $cacheKey = 'wfo_check_' . md5($publicIp ?? 'auto');
    if (isset($_SESSION[$cacheKey]) && $_SESSION[$cacheKey]['time'] > time() - 300) {
        return $_SESSION[$cacheKey]['result'];
    }
    
    $result = _isWfoByApiInternal($pdo, $publicIp);
    
    $_SESSION[$cacheKey] = ['time' => time(), 'result' => $result];
    return $result;
}

function _isWfoByApiInternal(PDO $pdo, ?string $publicIp = null): bool {
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

    // CRITICAL FIX: Check private IP range first (for laptops on Telkom University network)
    // This is important because laptops often get private IP (10.x.x.x) which cannot be validated via external API
    if (isTelkomUniversityPrivateIp($publicIp)) {
        error_log("WFO Private IP Check - IP: $publicIp, Result: VALID (Telkom University private IP range)");
        return true; // Private IP in Telkom University range - valid WFO
    }

    // For public IPs, check via external API
    // Skip API check for private IPs (they won't work with external APIs anyway)
    $isPrivate = filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    if ($isPrivate) {
        // Private IP but not in Telkom University range
        return false;
    }

    // Check public IP via external API
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
        
        if (!($backupResult['ok'] ?? $backupResult['success'] ?? false)) {
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
    // PERFORMANCE: Only run schema verification if explicitly requested
    if (isset($_GET['install_db'])) {
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
    }
} catch (Exception $e) {
    error_log("Database initialization failed: " . $e->getMessage());
    if (isset($_GET['ajax'])) {
        jsonResponse(['error' => 'Database connection failed'], 500);
    }
    // For non-AJAX requests, we'll let the page load but show an error
}

// Helper function for JSON response
function jsonResponse($data, $status = 200) {
    if (ob_get_length()) ob_clean(); // Clear any previous output (BOM, whitespace)
    header('Content-Type: application/json; charset=utf-8');
    http_response_code($status);
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

// Helper function to convert memory limit string to bytes
function return_bytes($val) {
    $val = trim($val);
    if (empty($val)) return 0;
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// Google Authenticator Helper Functions
function generateGoogleAuthenticatorSecret() {
    if (!class_exists('\Sonata\GoogleAuthenticator\GoogleAuthenticator')) {
        return null;
    }
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
    return $g->generateSecret();
}

function getGoogleAuthenticatorQRCode($secret, $email, $issuer = 'Sistem Presensi') {
    if (!class_exists('\Sonata\GoogleAuthenticator\GoogleQrUrl')) {
        return null;
    }
    try {
        // Generate QR code URL for Google Authenticator
        // Format: otpauth://totp/ISSUER:EMAIL?secret=SECRET&issuer=ISSUER
        $qrContent = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            urlencode($issuer),
            urlencode($email),
            urlencode($secret),
            urlencode($issuer)
        );
        
        // Use Google Charts API to generate QR code image
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qrContent);
        
        return $qrUrl;
    } catch (Exception $e) {
        error_log("Error generating QR code: " . $e->getMessage());
        return null;
    }
}

function verifyGoogleAuthenticatorOTP($secret, $code) {
    if (!class_exists('\Sonata\GoogleAuthenticator\GoogleAuthenticator')) {
        return false;
    }
    if (empty($secret) || empty($code)) {
        return false;
    }
    $g = new \Sonata\GoogleAuthenticator\GoogleAuthenticator();
    return $g->checkCode($secret, $code);
}

// Email Helper Functions
function sendPasswordResetEmail($email, $resetToken) {
    try {
        // Build reset URL - handle both localhost and production
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = dirname($scriptName);
        
        // Clean up base path - remove trailing slash and normalize
        $basePath = rtrim($basePath, '/');
        if ($basePath === '.') {
            $basePath = '';
        }
        if (!empty($basePath) && $basePath !== '/') {
            $basePath = '/' . ltrim($basePath, '/');
        }
        
        $resetUrl = $protocol . '://' . $host . $basePath . '/index.php?page=verify-otp&token=' . urlencode($resetToken);
        
        $subject = "Reset Password - Sistem Presensi";
        
        // Professional email template
        $htmlBody = '<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;">
    <table role="presentation" style="width: 100%; border-collapse: collapse;">
        <tr>
            <td style="padding: 20px 0; text-align: center; background-color: #ffffff;">
                <table role="presentation" style="width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                    <tr>
                        <td style="padding: 40px 40px 20px 40px; text-align: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px 8px 0 0;">
                            <h1 style="margin: 0; color: #ffffff; font-size: 28px; font-weight: bold;">Reset Password</h1>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 40px; background-color: #ffffff;">
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">Halo,</p>
                            <p style="margin: 0 0 20px 0; color: #333333; font-size: 16px; line-height: 1.6;">Kami menerima permintaan untuk mereset password akun Anda di Sistem Presensi Berbasis Wajah.</p>
                            <p style="margin: 0 0 30px 0; color: #333333; font-size: 16px; line-height: 1.6;">Untuk melanjutkan proses reset password, silakan verifikasi dengan kode OTP dari Google Authenticator Anda terlebih dahulu.</p>
                            <div style="text-align: center; margin: 30px 0;">
                                <a href="' . htmlspecialchars($resetUrl) . '" style="display: inline-block; padding: 14px 32px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px; box-shadow: 0 4px 6px rgba(0,0,0,0.1);">Verifikasi OTP</a>
                            </div>
                            <p style="margin: 30px 0 10px 0; color: #666666; font-size: 14px; line-height: 1.6;">Atau salin link berikut ke browser Anda:</p>
                            <p style="margin: 0 0 30px 0; color: #667eea; font-size: 14px; word-break: break-all; line-height: 1.6;">' . htmlspecialchars($resetUrl) . '</p>
                            <div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 30px 0; border-radius: 4px;">
                                <p style="margin: 0; color: #856404; font-size: 14px; line-height: 1.6;"><strong>Penting:</strong> Link ini akan kedaluwarsa dalam 1 jam. Jika Anda tidak meminta reset password, abaikan email ini.</p>
                            </div>
                            <p style="margin: 30px 0 0 0; color: #666666; font-size: 14px; line-height: 1.6;">Terima kasih,<br><strong>Tim Sistem Presensi</strong></p>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 20px 40px; background-color: #f8f9fa; border-radius: 0 0 8px 8px; text-align: center; border-top: 1px solid #e9ecef;">
                            <p style="margin: 0; color: #6c757d; font-size: 12px;">&copy; ' . date('Y') . ' Sistem Presensi Berbasis Wajah. All rights reserved.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';
        
        $textBody = "Reset Password\n\n";
        $textBody .= "Kami menerima permintaan untuk mereset password akun Anda.\n\n";
        $textBody .= "Untuk melanjutkan, silakan verifikasi dengan kode OTP dari Google Authenticator Anda:\n";
        $textBody .= $resetUrl . "\n\n";
        $textBody .= "Link ini akan kedaluwarsa dalam 1 jam.\n\n";
        $textBody .= "Jika Anda tidak meminta reset password, abaikan email ini.\n\n";
        $textBody .= "Terima kasih,\nTim Sistem Presensi";
        
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: Sistem Presensi <noreply@presensi.local>" . "\r\n";
        $headers .= "Reply-To: noreply@presensi.local" . "\r\n";
        
        // Try to send email
        $result = @mail($email, $subject, $htmlBody, $headers);
        
        // Log email attempt
        error_log("Password reset email sent to: $email, URL: $resetUrl, Result: " . ($result ? 'SUCCESS' : 'FAILED'));
        
        // For development/testing: if mail() fails, log but don't fail completely
        // In production, you should configure SMTP properly
        if (!$result) {
            error_log("Warning: mail() function returned false for $email. Check PHP mail configuration.");
            // For development: we'll still allow the reset to proceed
            // In production, you should configure SMTP properly or use PHPMailer
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Error in sendPasswordResetEmail: " . $e->getMessage());
        return false;
    }
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
        $maxOntimeHour = (int)getSetting($pdo, 'max_ontime_hour', '8');
        
        // Get employee data
        $stmt = $pdo->prepare("SELECT nama, created_at, nim, startup, foto_base64 FROM users WHERE id = ?");
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
        // Store late records with their minutes for per-occurrence calculation
        // Use jam_masuk (time format) instead of jam_masuk_iso for late_minutes calculation
        // Use max_ontime_hour from settings instead of hardcoded 08:00
        $stmt = $pdo->prepare("
            SELECT 
                DATE(jam_masuk_iso) as attendance_date,
                jam_masuk_iso,
                jam_masuk,
                status,
                ket,
                CASE 
                    WHEN status = 'terlambat' AND jam_masuk IS NOT NULL THEN 
                        GREATEST(0, 
                            FLOOR(
                                TIMESTAMPDIFF(MINUTE, 
                                    CONCAT('2000-01-01 ', LPAD(:max_ontime_hour, 2, '0'), ':00:00'),
                                    CONCAT('2000-01-01 ', 
                                        CASE 
                                            WHEN LENGTH(jam_masuk) = 5 THEN CONCAT(jam_masuk, ':00')
                                            ELSE jam_masuk
                                        END
                                    )
                                )
                            )
                        )
                    WHEN status = 'terlambat' AND jam_masuk IS NULL THEN 
                        GREATEST(0, TIMESTAMPDIFF(MINUTE, 
                            CONCAT(DATE(jam_masuk_iso), ' ', LPAD(:max_ontime_hour, 2, '0'), ':00:00'), 
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
            'period_end' => $periodEnd,
            'max_ontime_hour' => $maxOntimeHour
        ]);
        $attendanceRecords = $stmt->fetchAll();
        
        // Debug: log attendance records to see late_minutes values
        error_log("KPI Debug - User $userId: Found " . count($attendanceRecords) . " attendance records");
        foreach ($attendanceRecords as $idx => $rec) {
            if ($rec['status'] === 'terlambat') {
                error_log("KPI Debug - User $userId: Record $idx - Date: {$rec['attendance_date']}, Status: {$rec['status']}, jam_masuk: {$rec['jam_masuk']}, jam_masuk_iso: {$rec['jam_masuk_iso']}, late_minutes: {$rec['late_minutes']}");
            }
        }
        
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
        
        // Get daily reports for the period
        $dailyReportsStmt = $pdo->prepare("
            SELECT report_date 
            FROM daily_reports 
            WHERE user_id = :user_id 
            AND report_date BETWEEN :period_start AND :period_end
        ");
        $dailyReportsStmt->execute([
            'user_id' => $userId,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ]);
        $dailyReportsRecords = $dailyReportsStmt->fetchAll();
        
        // Create maps for quick lookup
        $attendanceMap = [];
        foreach ($attendanceRecords as $record) {
            $attendanceMap[$record['attendance_date']] = $record;
        }
        
        $dailyReportsMap = [];
        foreach ($dailyReportsRecords as $record) {
            $dailyReportsMap[$record['report_date']] = true;
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
        $wfoCount = 0; // NEW: Count WFO attendance
        $wfaCount = 0; // NEW: Count WFA attendance
        $totalLateMinutes = 0; // Keep for backward compatibility/reporting
        $lateRecords = []; // Store late records with minutes for per-occurrence calculation
        $izinSakitCount = 0;
        $alphaCount = 0;
        $overtimeCount = 0;
        $actualWorkingDays = 0; // Count actual working days for this employee (only past dates)
        $totalWorkingDaysInPeriod = 0; // Count all working days in period for this employee
        $missingDailyReportsCount = 0; // Count days with attendance but no daily report
        $daysWithoutReport = []; // Store dates that need daily report penalty
        
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
            // Use attendanceMap for faster lookup instead of looping
            $attendanceRecord = isset($attendanceMap[$dateStr]) ? $attendanceMap[$dateStr] : null;
            
            // Only process dates that have already passed for KPI calculation
            if ($dateStr <= $currentDate) {
                // Check if it's izin/sakit first (from attendance_notes table)
                if (isset($izinDates[$dateStr])) {
                    $izinSakitCount++;
                    error_log("KPI Debug - User $userId: Found izin/sakit on $dateStr, count now: $izinSakitCount");
                } else if ($attendanceRecord) {
                    // Check if daily report exists for this date
                    $hasDailyReport = isset($dailyReportsMap[$dateStr]);
                    
                    // If attendance exists but no daily report, mark for penalty
                    if (!$hasDailyReport && ($attendanceRecord['ket'] === 'wfo' || $attendanceRecord['ket'] === 'wfa')) {
                        $missingDailyReportsCount++;
                        $daysWithoutReport[] = $dateStr;
                        error_log("KPI Debug - User $userId: Missing daily report on $dateStr");
                    }
                    
                    // Check attendance status (only WFO, WFA, Overtime)
                    if ($attendanceRecord['status'] === 'ontime') {
                        $ontimeCount++;
                        // Count WFO and WFA separately
                        if ($attendanceRecord['ket'] === 'wfo') {
                            $wfoCount++;
                        } else if ($attendanceRecord['ket'] === 'wfa') {
                            $wfaCount++;
                        }
                        error_log("KPI Debug - User $userId: Found ontime on $dateStr");
                    } else {
                        $lateCount++;
                        // Count WFO and WFA even if late
                        if ($attendanceRecord['ket'] === 'wfo') {
                            $wfoCount++;
                        } else if ($attendanceRecord['ket'] === 'wfa') {
                            $wfaCount++;
                        }
                        $lateMinutes = (int)$attendanceRecord['late_minutes'];
                        $totalLateMinutes += $lateMinutes;
                        // Store late record with minutes for per-occurrence calculation
                        $lateRecords[] = $lateMinutes;
                        error_log("KPI Debug - User $userId: Found late on $dateStr, late_minutes from DB: {$attendanceRecord['late_minutes']}, jam_masuk: {$attendanceRecord['jam_masuk']}, jam_masuk_iso: {$attendanceRecord['jam_masuk_iso']}, status: {$attendanceRecord['status']}");
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
        error_log("KPI Debug - User $userId: actualWorkingDays from loop: $actualWorkingDays");
        error_log("KPI Debug - User $userId: lateRecords count: " . count($lateRecords) . ", lateRecords: " . print_r($lateRecords, true));
        
        // Calculate actual working days based on days with actual data
        // This should be the sum of all days with attendance records (ontime, late, alpha, izin/sakit)
        // NOT the total working days in period, because we only calculate KPI for days with data
        $daysWithData = (int)$ontimeCount + (int)$lateCount + (int)$izinSakitCount + (int)$alphaCount;
        
        error_log("KPI Debug - User $userId: daysWithData calculation: $ontimeCount + $lateCount + $izinSakitCount + $alphaCount = $daysWithData");
        
        // IMPORTANT: Always use daysWithData as divisor if it's greater than 0
        // This ensures KPI is calculated correctly: total score / days with data
        // Only fallback to actualWorkingDays if daysWithData is 0 (shouldn't happen in normal cases)
        if ($daysWithData > 0) {
            $actualDaysForKPI = $daysWithData;
            error_log("KPI Debug - User $userId: Using daysWithData ($daysWithData) as divisor");
        } else {
            // Fallback: use actualWorkingDays only if no data at all
            $actualDaysForKPI = $actualWorkingDays > 0 ? $actualWorkingDays : 1; // Prevent division by zero
            error_log("KPI Debug - User $userId: WARNING - daysWithData is 0, using actualWorkingDays ($actualDaysForKPI) as fallback");
        }
        
        error_log("KPI Debug - User $userId: Final divisor (actualDaysForKPI): $actualDaysForKPI");
        
        // Calculate KPI score using new per-occurrence method
        // Formula: 
        // - On-time: 100% each
        // - Late: 100% - (minutes late) for each occurrence
        // - Alpha: 0% each
        // - Izin/Sakit: use setting score (default 85%)
        // - Overtime: bonus (default 5%)
        // Total = sum of all scores / days with actual data
        $kpiScore = 0;
        
        // On-time: 100% each
        $ontimeScore = $ontimeCount * 100;
        $kpiScore += $ontimeScore;
        error_log("KPI Debug - User $userId: Ontime score: $ontimeScore (count: $ontimeCount)");
        
        // Late: calculate per occurrence (100% - minutes late)
        $lateTotalScore = 0;
        foreach ($lateRecords as $lateMinutes) {
            // Formula: 100% - (minutes late)
            // Example: terlambat 10 menit = 100 - 10 = 90%
            // Example: terlambat 9 menit = 100 - 9 = 91%
            $lateScore = 100 - $lateMinutes; // 100% - minutes late
            $lateScore = max(0, $lateScore); // Ensure not negative (if terlambat > 100 menit, score = 0)
            $lateTotalScore += $lateScore;
            error_log("KPI Debug - User $userId: Late occurrence: $lateMinutes minutes late = $lateScore score (100 - $lateMinutes = $lateScore)");
        }
        $kpiScore += $lateTotalScore;
        error_log("KPI Debug - User $userId: Late total score: $lateTotalScore (count: $lateCount, records: " . print_r($lateRecords, true) . ")");
        
        // Alpha: 0% each (no need to add, already 0)
        // $kpiScore += ($alphaCount * 0); // Not needed
        error_log("KPI Debug - User $userId: Alpha count: $alphaCount (score: 0)");
        
        // Izin/Sakit: use setting score (default 85%)
        $izinSakitScoreTotal = $izinSakitCount * $izinSakitScore;
        $kpiScore += $izinSakitScoreTotal;
        error_log("KPI Debug - User $userId: Izin/Sakit score: $izinSakitScoreTotal (count: $izinSakitCount, per occurrence: $izinSakitScore)");
        
        // Overtime: bonus (default 5% per occurrence)
        $overtimeScoreTotal = $overtimeCount * $overtimeBonus;
        $kpiScore += $overtimeScoreTotal;
        error_log("KPI Debug - User $userId: Overtime score: $overtimeScoreTotal (count: $overtimeCount, per occurrence: $overtimeBonus)");
        
        // Apply daily report penalty: reduce 50% per day without report
        // This penalty is applied per day, not from total score
        $dailyReportPenalty = 0;
        if (isset($daysWithoutReport) && is_array($daysWithoutReport)) {
            foreach ($daysWithoutReport as $dateWithoutReport) {
                // Find the score for that day
                $dayScore = 0;
                if (isset($attendanceMap[$dateWithoutReport])) {
                    $dayRecord = $attendanceMap[$dateWithoutReport];
                    if ($dayRecord['status'] === 'ontime') {
                        $dayScore = 100;
                    } else {
                        // Late: 100 - minutes late
                        $lateMinutes = (int)$dayRecord['late_minutes'];
                        $dayScore = max(0, 100 - $lateMinutes);
                    }
                }
                // Reduce 50% of that day's score
                $penaltyForDay = $dayScore * 0.5;
                $dailyReportPenalty += $penaltyForDay;
                error_log("KPI Debug - User $userId: Daily report penalty for $dateWithoutReport: $penaltyForDay (day score: $dayScore)");
            }
        }
        $kpiScore -= $dailyReportPenalty;
        error_log("KPI Debug - User $userId: Total daily report penalty: $dailyReportPenalty, score after penalty: $kpiScore");

        // Calculate average based on days with actual data
        error_log("KPI Debug - User $userId: Total score before division: $kpiScore, Divided by: $actualDaysForKPI");
        $kpiScore = $kpiScore / $actualDaysForKPI;
        error_log("KPI Debug - User $userId: KPI score after division: $kpiScore");
        
        // Ensure score is between 0 and 100
        $kpiScore = max(0, min(100, $kpiScore));
        error_log("KPI Debug - User $userId: Final KPI score: $kpiScore");
        
        // Determine KPI status
        $status = 'Very Poor';
        if ($kpiScore >= 90) $status = 'Excellent';
        elseif ($kpiScore >= 80) $status = 'Good';
        elseif ($kpiScore >= 70) $status = 'Fair';
        elseif ($kpiScore >= 60) $status = 'Poor';
        
        return [
            'user_id' => $userId,
            'nama' => $employee['nama'],
            'nim' => $employee['nim'] ?? '-',
            'startup' => $employee['startup'] ?? '-',
            'foto_base64' => $employee['foto_base64'] ?? '',
            'total_working_days' => $totalWorkingDaysInPeriod, // Total working days in period
            'actual_working_days' => $actualWorkingDays, // Days that have passed for KPI calculation
            'ontime_count' => $ontimeCount,
            'wfo_count' => $wfoCount, // NEW: Add WFO count
            'wfa_count' => $wfaCount, // NEW: Add WFA count
            'late_count' => $lateCount,
            'izin_sakit_count' => $izinSakitCount,
            'alpha_count' => $alphaCount,
            'overtime_count' => $overtimeCount,
            'missing_daily_reports_count' => $missingDailyReportsCount,
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
$action = $_GET['ajax'] ?? $_POST['action'] ?? null;
if ($action) {

    // Check if database is available
    if (!isset($pdo)) {
        error_log("Database connection failed in AJAX handler");
        jsonResponse(['error' => 'Database connection failed'], 500);
    }

    // Must be authenticated for all endpoints except auth-related and public landing scan
    if (!in_array($action, ['login', 'register', 'get_members', 'save_attendance', 'get_today_attendance', 'forgot_password', 'verify_otp', 'reset_password', 'get_ga_qr', 'get_public_daily_report_stats', 'reverse_geocode'], true)) {
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

    // Forgot Password - Request reset
    if ($action === 'forgot_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = trim($_POST['email'] ?? '');
        if (empty($email)) {
            jsonResponse(['ok' => false, 'message' => 'Email wajib diisi'], 400);
        }
        
        $stmt = $pdo->prepare("SELECT id, email, google_authenticator_secret FROM users WHERE email=:email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            // Don't reveal if email exists for security
            jsonResponse(['ok' => true, 'message' => 'Jika email terdaftar, link reset password telah dikirim.']);
        }
        
        // Check if user has Google Authenticator secret
        if (empty($user['google_authenticator_secret'])) {
            jsonResponse(['ok' => false, 'message' => 'Akun Anda belum memiliki Google Authenticator. Silakan hubungi administrator untuk mengatur QR code.'], 400);
        }
        
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $stmt = $pdo->prepare("UPDATE users SET password_reset_token=:token, password_reset_expires=:expires WHERE id=:id");
        $stmt->execute([
            ':token' => $resetToken,
            ':expires' => $resetExpires,
            ':id' => $user['id']
        ]);
        
        // Build reset URL for response (same as email)
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $basePath = dirname($scriptName);
        $basePath = rtrim($basePath, '/');
        if ($basePath === '.') {
            $basePath = '';
        }
        if (!empty($basePath) && $basePath !== '/') {
            $basePath = '/' . ltrim($basePath, '/');
        }
        $resetUrl = $protocol . '://' . $host . $basePath . '/index.php?page=verify-otp&token=' . urlencode($resetToken);
        
        // Try to send email
        $emailSent = @sendPasswordResetEmail($email, $resetToken);
        
        // Always return success with reset URL for direct redirect
        // Email is optional (for production, configure SMTP properly)
        error_log("Password reset token generated for $email. Reset URL: $resetUrl");
        
        // Return success with token for direct redirect
        jsonResponse([
            'ok' => true, 
            'reset_url' => $resetUrl,
            'token' => $resetToken,
            'message' => 'Redirecting to OTP verification...'
        ]);
    }

    // Verify OTP - Step 2 of forgot password
    if ($action === 'verify_otp' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = trim($_POST['token'] ?? '');
        $otp = trim($_POST['otp'] ?? '');
        
        if (empty($token) || empty($otp)) {
            jsonResponse(['ok' => false, 'message' => 'Token dan OTP wajib diisi'], 400);
        }
        
        // Find user by reset token
        $stmt = $pdo->prepare("SELECT id, email, google_authenticator_secret, password_reset_expires FROM users WHERE password_reset_token=:token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['ok' => false, 'message' => 'Token tidak valid atau telah kedaluwarsa'], 400);
        }
        
        // Check if token expired
        if (strtotime($user['password_reset_expires']) < time()) {
            jsonResponse(['ok' => false, 'message' => 'Token telah kedaluwarsa. Silakan request reset password lagi.'], 400);
        }
        
        // Verify OTP with Google Authenticator
        if (empty($user['google_authenticator_secret'])) {
            jsonResponse(['ok' => false, 'message' => 'Akun Anda belum memiliki Google Authenticator.'], 400);
        }
        
        if (!verifyGoogleAuthenticatorOTP($user['google_authenticator_secret'], $otp)) {
            jsonResponse(['ok' => false, 'message' => 'Kode OTP tidak valid. Pastikan kode dari Google Authenticator masih berlaku.'], 400);
        }
        
        // OTP verified successfully, redirect to reset password page
        jsonResponse(['ok' => true, 'token' => $token, 'message' => 'OTP berhasil diverifikasi. Silakan buat password baru.']);
    }

    // Reset Password - Step 3 of forgot password
    if ($action === 'reset_password' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = trim($_POST['token'] ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        
        if (empty($token) || empty($password) || empty($password2)) {
            jsonResponse(['ok' => false, 'message' => 'Semua field wajib diisi'], 400);
        }
        
        if ($password !== $password2) {
            jsonResponse(['ok' => false, 'message' => 'Konfirmasi password tidak cocok'], 400);
        }
        
        // Find user by reset token
        $stmt = $pdo->prepare("SELECT id, password_reset_expires FROM users WHERE password_reset_token=:token LIMIT 1");
        $stmt->execute([':token' => $token]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['ok' => false, 'message' => 'Token tidak valid atau telah kedaluwarsa'], 400);
        }
        
        // Check if token expired
        if (strtotime($user['password_reset_expires']) < time()) {
            jsonResponse(['ok' => false, 'message' => 'Token telah kedaluwarsa. Silakan request reset password lagi.'], 400);
        }
        
        // Update password
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash=:hash, password_reset_token=NULL, password_reset_expires=NULL WHERE id=:id");
        $stmt->execute([
            ':hash' => $hash,
            ':id' => $user['id']
        ]);
        
        jsonResponse(['ok' => true, 'message' => 'Password berhasil direset. Silakan login dengan password baru.']);
    }

    // Get Google Authenticator QR Code for member
    if ($action === 'get_ga_qr') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $userId = (int)($_GET['user_id'] ?? 0);
        if (!$userId) {
            jsonResponse(['ok' => false, 'message' => 'User ID tidak valid'], 400);
        }
        
        $stmt = $pdo->prepare("SELECT id, email, google_authenticator_secret FROM users WHERE id=:id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            jsonResponse(['ok' => false, 'message' => 'User tidak ditemukan'], 404);
        }
        
        // Generate secret if doesn't exist
        if (empty($user['google_authenticator_secret'])) {
            $secret = generateGoogleAuthenticatorSecret();
            if (!$secret) {
                jsonResponse(['ok' => false, 'message' => 'Gagal menghasilkan secret. Pastikan Google Authenticator library terpasang.'], 500);
            }
            
            $stmt = $pdo->prepare("UPDATE users SET google_authenticator_secret=:secret WHERE id=:id");
            $stmt->execute([':secret' => $secret, ':id' => $userId]);
        } else {
            $secret = $user['google_authenticator_secret'];
        }
        
        // Generate QR code URL
        $qrUrl = getGoogleAuthenticatorQRCode($secret, $user['email'], 'Sistem Presensi');
        
        if (!$qrUrl) {
            jsonResponse(['ok' => false, 'message' => 'Gagal menghasilkan QR code.'], 500);
        }
        
        jsonResponse(['ok' => true, 'qr_url' => $qrUrl, 'secret' => $secret, 'email' => $user['email']]);
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
        
        try {
        $id = $_POST['id'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $nim = trim($_POST['nim'] ?? '');
        $nama = trim($_POST['nama'] ?? '');
        $prodi = trim($_POST['prodi'] ?? '');
        $startup = trim($_POST['startup'] ?? '');
        $foto = $_POST['foto'] ?? null;

        if ($id) {
            // Update existing by id
            $user = $pdo->prepare("SELECT id, email, nim FROM users WHERE id=:id AND role='pegawai'");
            $user->execute([':id' => $id]);
            $currentUser = $user->fetch();
            if (!$currentUser) jsonResponse(['ok' => false, 'message' => 'Member tidak ditemukan'], 404);
            
            // Check if email is being changed and if it's unique
            if ($email && $email !== $currentUser['email']) {
                $checkEmail = $pdo->prepare("SELECT id FROM users WHERE email=:email AND id!=:id LIMIT 1");
                $checkEmail->execute([':email' => $email, ':id' => $id]);
                if ($checkEmail->fetch()) {
                    jsonResponse(['ok' => false, 'message' => 'Email sudah digunakan oleh member lain'], 400);
                }
            }
            
            // Check if nim is being changed and if it's unique
            if ($nim && $nim !== $currentUser['nim']) {
                $checkNim = $pdo->prepare("SELECT id FROM users WHERE nim=:nim AND id!=:id LIMIT 1");
                $checkNim->execute([':nim' => $nim, ':id' => $id]);
                if ($checkNim->fetch()) {
                    jsonResponse(['ok' => false, 'message' => 'NIM sudah digunakan oleh member lain'], 400);
                }
            }
            
            // Check image size if updating photo (max 1MB)
            if ($foto && !checkImageSize($foto, 1)) {
                jsonResponse(['ok' => false, 'message' => 'Ukuran foto terlalu besar. Maksimal 1MB. Silakan kompres foto atau gunakan foto dengan resolusi lebih kecil.'], 400);
            }
            
            // Build update query with email and nim
            $params = [':nama' => $nama, ':prodi' => $prodi, ':startup' => $startup ?: null, ':id' => $id];
            $setParts = ['nama=:nama', 'prodi=:prodi', 'startup=:startup'];
            
            if ($email) {
                $setParts[] = 'email=:email';
                $params[':email'] = $email;
            }
            
            if ($nim) {
                $setParts[] = 'nim=:nim';
                $params[':nim'] = $nim;
            }
            
            if ($foto) {
                $setParts[] = 'foto_base64=:foto';
                $params[':foto'] = $foto;
            }
            
            $sql = "UPDATE users SET " . implode(', ', $setParts) . " WHERE id=:id";
            $pdo->prepare($sql)->execute($params);
            
            // OPTIMIZED: Backup trigger removed from frequent operations
            // triggerDatabaseBackup(); // Backup happens on schedule instead
            
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
        } catch (PDOException $e) {
            error_log("Database error in save_member: " . $e->getMessage());
            jsonResponse(['error' => 'Gagal menyimpan data member'], 500);
        } catch (Exception $e) {
            error_log("Error in save_member: " . $e->getMessage());
            jsonResponse(['error' => 'Terjadi kesalahan'], 500);
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
        try {
            // Check memory usage before heavy operation
            $memoryUsage = memory_get_usage(true);
            $memoryLimit = ini_get('memory_limit');
            $memoryLimitBytes = return_bytes($memoryLimit);
            
            if ($memoryUsage > $memoryLimitBytes * 0.8) {
                error_log("Memory usage high before get_attendance: " . round($memoryUsage / 1024 / 1024, 2) . "MB");
                jsonResponse(['error' => 'Sistem sedang sibuk, coba lagi dalam beberapa saat'], 503);
            }
            
            // Get pagination parameters
            $limit = min((int)($_GET['limit'] ?? 500), 1000); // Max 1000 records at once
            $offset = max((int)($_GET['offset'] ?? 0), 0);
            
            // Get date filters if provided
            $startDate = $_GET['start_date'] ?? null;
            $endDate = $_GET['end_date'] ?? null;
            
            // Admin: all; Pegawai: only their records
            if (isAdmin()) {
                // Build WHERE clause for date filtering
                $whereClause = "1=1";
                $params = [];
                
                if ($startDate && $endDate) {
                    $whereClause .= " AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date";
                    $params[':start_date'] = $startDate;
                    $params[':end_date'] = $endDate;
                }
                
                // Get regular attendance records with pagination
                $sql = "SELECT a.*, u.nim, u.nama, u.startup,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                    FROM attendance a 
                    JOIN users u ON u.id=a.user_id 
                    WHERE $whereClause
                    ORDER BY a.jam_masuk_iso DESC 
                    LIMIT :limit OFFSET :offset";
                
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $attendanceData = $stmt->fetchAll();
                
                // Get izin/sakit records from attendance_notes with pagination
                $notesWhereClause = "1=1";
                $notesParams = [];
                
                if ($startDate && $endDate) {
                    $notesWhereClause .= " AND an.date BETWEEN :start_date AND :end_date";
                    $notesParams[':start_date'] = $startDate;
                    $notesParams[':end_date'] = $endDate;
                }
                
                $notesSql = "SELECT an.*, u.nim, u.nama, u.startup,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                    FROM attendance_notes an 
                    JOIN users u ON u.id=an.user_id 
                    WHERE $notesWhereClause
                    ORDER BY an.date DESC 
                    LIMIT :limit OFFSET :offset";
                
                $notesStmt = $pdo->prepare($notesSql);
                foreach ($notesParams as $key => $value) {
                    $notesStmt->bindValue($key, $value);
                }
                $notesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $notesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $notesStmt->execute();
                $notesData = $notesStmt->fetchAll();
                
            } else {
                $uid = (int)$_SESSION['user']['id'];
                
                // Build WHERE clause for date filtering
                $whereClause = "a.user_id=:uid";
                $params = [':uid' => $uid];
                
                if ($startDate && $endDate) {
                    $whereClause .= " AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date";
                    $params[':start_date'] = $startDate;
                    $params[':end_date'] = $endDate;
                }
                
                // Get regular attendance records with pagination
                $sql = "SELECT a.*, u.nim, u.nama, u.startup,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=a.user_id AND dr.report_date=DATE(a.jam_masuk_iso) LIMIT 1) AS daily_report_status
                    FROM attendance a 
                    JOIN users u ON u.id=a.user_id 
                    WHERE $whereClause
                    ORDER BY a.jam_masuk_iso DESC 
                    LIMIT :limit OFFSET :offset";
                
                $stmt = $pdo->prepare($sql);
                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $stmt->execute();
                $attendanceData = $stmt->fetchAll();
                
                // Get izin/sakit records from attendance_notes for this user with pagination
                $notesWhereClause = "an.user_id=:uid";
                $notesParams = [':uid' => $uid];
                
                if ($startDate && $endDate) {
                    $notesWhereClause .= " AND an.date BETWEEN :start_date AND :end_date";
                    $notesParams[':start_date'] = $startDate;
                    $notesParams[':end_date'] = $endDate;
                }
                
                $notesSql = "SELECT an.*, u.nim, u.nama, u.startup,
                    (SELECT dr.status FROM daily_reports dr WHERE dr.user_id=an.user_id AND dr.report_date=an.date LIMIT 1) AS daily_report_status
                    FROM attendance_notes an 
                    JOIN users u ON u.id=an.user_id 
                    WHERE $notesWhereClause
                    ORDER BY an.date DESC 
                    LIMIT :limit OFFSET :offset";
                
                $notesStmt = $pdo->prepare($notesSql);
                foreach ($notesParams as $key => $value) {
                    $notesStmt->bindValue($key, $value);
                }
                $notesStmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $notesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                $notesStmt->execute();
                $notesData = $notesStmt->fetchAll();
            }
            
            // Convert notes data to attendance format (only if notes exist)
            if (!empty($notesData)) {
                foreach ($notesData as $note) {
                    $attendanceData[] = [
                        'id' => 'note_' . $note['id'],
                        'user_id' => $note['user_id'],
                        'nim' => $note['nim'],
                        'nama' => $note['nama'],
                        'startup' => $note['startup'],
                        'jam_masuk' => '08:00',
                        'jam_masuk_iso' => $note['date'] . ' 08:00:00',
                        'ekspresi_masuk' => null,
                        'screenshot_masuk' => null,
                        'lokasi_masuk' => null,
                        'lat_masuk' => null,
                        'lng_masuk' => null,
                        'jam_pulang' => '17:00',
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
                        'is_note' => true
                    ];
                }
                
                // Sort combined data by date descending (only if we have notes)
                usort($attendanceData, function($a, $b) {
                    return strtotime($b['jam_masuk_iso']) - strtotime($a['jam_masuk_iso']);
                });
            }
            
            jsonResponse(['ok' => true, 'data' => $attendanceData, 'limit' => $limit, 'offset' => $offset]);
            
        } catch (PDOException $e) {
            error_log("Database error in get_attendance: " . $e->getMessage());
            jsonResponse(['error' => 'Gagal memuat data presensi. Silakan refresh halaman.'], 500);
        } catch (Exception $e) {
            error_log("Error in get_attendance: " . $e->getMessage());
            jsonResponse(['error' => 'Terjadi kesalahan. Silakan coba lagi.'], 500);
        }
    }
    
    if ($action === 'get_kpi_data') {
        try {
            // Check if this is for admin dashboard (filter_type parameter)
            $filterType = $_REQUEST['filter_type'] ?? '';
            $isAdminDashboard = isAdmin() && $filterType !== '';
            
            if ($isAdminDashboard) {
                // Admin dashboard - get all KPI data with optional monthly filter
                $customPeriodStart = null;
                $customPeriodEnd = null;
                
                if ($filterType === 'monthly') {
                    $month = (int)($_REQUEST['month'] ?? date('n'));
                    $year = (int)($_REQUEST['year'] ?? date('Y'));
                    $customPeriodStart = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
                    $customPeriodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
                    error_log("get_kpi_data - Monthly filter: $month/$year ($customPeriodStart to $customPeriodEnd)");
                }
                
                $kpiData = getAllKPIData($pdo, $customPeriodStart, $customPeriodEnd);
                error_log("get_kpi_data - Admin dashboard, returning all KPI data");
                jsonResponse(['ok' => true, 'data' => $kpiData]);
            } else {
                // Individual employee KPI - get specific user
                $userId = isAdmin() ? (int)($_REQUEST['user_id'] ?? 0) : (int)$_SESSION['user']['id'];
                
                error_log("get_kpi_data - User ID: $userId, Is Admin: " . (isAdmin() ? 'Yes' : 'No'));
                error_log("get_kpi_data - Session user: " . print_r($_SESSION['user'] ?? 'No session', true));
                error_log("get_kpi_data - REQUEST user_id: " . ($_REQUEST['user_id'] ?? 'Not set'));
                
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
                $periodStart = $_REQUEST['period_start'] ?? date('Y-m-01');
                $periodEnd = $_REQUEST['period_end'] ?? date('Y-m-t');
                
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
                // FIRST: Check if it's a working day
                $isWorkingDay = isEmployeeWorkingDay($pdo, $u['id'], $today);
                $dayOfWeek = (int)$now->format('N'); // 1=Monday, 7=Sunday
                $isWeekend = $dayOfWeek >= 6; // Saturday or Sunday
                $isManualHolidayDate = isManualHoliday($pdo, $today);
                $isNationalHolidayDate = isNationalHoliday($today);
                
                // If NOT a working day (weekend or holiday), treat as overtime
                if (!$isWorkingDay || $isWeekend || $isManualHolidayDate || $isNationalHolidayDate) {
                    // This is overtime - require overtime reason and location
                    $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
                    $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
                    $lokasi = $_POST['lokasi'] ?? null;
                    $alasanOvertime = $_POST['overtime_reason'] ?? $_POST['alasan_overtime'] ?? null;
                    $lokasiOvertime = $_POST['overtime_location'] ?? $_POST['lokasi_overtime'] ?? null;
                    
                    // Strict validation: GPS location is mandatory
                    if ($lat === null || $lng === null || $lat === 0 || $lng === 0) {
                        jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Lokasi GPS wajib untuk presensi overtime. Pastikan GPS aktif dan izin lokasi diberikan.'], 400);
                    }
                    
                    // OPTIMIZED: Quick reverse geocoding - ensure lokasi is never empty
                    if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
                        if ($lat !== null && $lng !== null) {
                            // Try reverse geocoding with shorter timeout
                            $reverseGeocoded = @reverseGeocodeAddress($lat, $lng);
                            if ($reverseGeocoded && !empty($reverseGeocoded)) {
                                $lokasi = $reverseGeocoded;
                            } else {
                                // Fallback - ensure lokasi is never empty
                                $lokasi = 'Lokasi: ' . round($lat, 6) . ', ' . round($lng, 6);
                            }
                        } else {
                            // No coordinates - use default
                            $lokasi = 'Lokasi tidak tersedia';
                        }
                    }
                    
                    // Use lokasi as lokasi_overtime if not provided
                    if (empty($lokasiOvertime)) {
                        $lokasiOvertime = $lokasi;
                    }
                    
                    // Require overtime reason and location
                    if (!$alasanOvertime) {
                        jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Presensi di hari libur/weekend dianggap overtime. Harap isi alasan dan lokasi overtime.'], 400);
                    }
                    
                    if (!$lokasiOvertime) {
                        jsonResponse(['ok' => false, 'need_overtime_reason' => true, 'message' => 'Lokasi overtime wajib diisi.'], 400);
                    }
                    
                    // Insert overtime attendance - no location check needed
                    $status = 'ontime'; // Overtime is always considered ontime
                    $ketVal = 'overtime';
                    
                    $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, screenshot_masuk, lokasi_masuk, lat_masuk, lng_masuk, status, ket, alasan_overtime, lokasi_overtime) VALUES (:uid, :jam, :iso, :exp, :screenshot, :lokasi, :lat, :lng, :status, :ket, :alasan, :lokasi_ot)");
                    $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':status' => $status, ':ket' => $ketVal, ':alasan' => $alasanOvertime, ':lokasi_ot' => $lokasiOvertime]);
                    
                    // Trigger backup setelah presensi overtime
                    triggerDatabaseBackup();
                    
                    // Response for overtime
                    $jamMasukFormat = substr($jamSekarang, 0, 5);
                    $firstName = getFirstName($u['nama']);
                    $statusText = "Selamat datang {$firstName}, anda masuk {$jamMasukFormat}. Overtime dicatat!";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-purple-100 text-purple-700']);
                    return; // Exit early for overtime
                }
                
                // If it's a working day, continue with normal WFO/WFA check
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
                $gpsAccuracy = isset($_POST['gps_accuracy']) ? (float)$_POST['gps_accuracy'] : null;
                $wifiSSID = trim($_POST['wifi_ssid'] ?? '');
                
                // Strict validation: GPS location is mandatory
                if ($lat === null || $lng === null || $lat === 0 || $lng === 0) {
                    jsonResponse(['ok' => false, 'message' => 'Lokasi GPS wajib untuk presensi. Pastikan GPS aktif dan izin lokasi diberikan.'], 400);
                }
                
                // Accept GPS even with lower accuracy (indoors/gymnasium buildings are common)
                // Log warning but don't reject - GPS accuracy can be low indoors which is normal
                if ($gpsAccuracy !== null && $gpsAccuracy > 50) {
                    error_log('GPS accuracy low: ' . round($gpsAccuracy) . 'm - accepting anyway (user may be indoors)');
                }
                
                // OPTIMIZED: Skip reverse geocoding for faster performance
                // Use coordinates directly - reverse geocoding can be slow and is not critical
                if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
                    if ($lat !== null && $lng !== null) {
                        // Use coordinates directly for faster response
                        $lokasi = 'Lokasi: ' . round($lat, 6) . ', ' . round($lng, 6);
                    } else {
                        // No coordinates - use default
                        $lokasi = 'Lokasi tidak tersedia';
                    }
                }
                
                // Final validation - ensure lokasi is never empty
                if (empty($lokasi)) {
                    if ($lat !== null && $lng !== null) {
                        $lokasi = 'Lokasi: ' . round($lat, 6) . ', ' . round($lng, 6);
                    } else {
                        $lokasi = 'Lokasi tidak tersedia';
                    }
                }

                // Determine WFO via API or coordinate fallback
                $wfoMode = strtolower(getSetting($pdo, 'wfo_mode', 'api'));
                
                // CRITICAL: IP detection - prioritize POST data first (from frontend), then REMOTE_ADDR
                // Skip localhost IPs (127.0.0.1, ::1) as they indicate local development/testing
                $publicIp = $_POST['public_ip'] ?? '';
                
                // If POST IP is empty or localhost, try REMOTE_ADDR (but skip localhost)
                if (empty($publicIp) || !filter_var($publicIp, FILTER_VALIDATE_IP) || 
                    $publicIp === '127.0.0.1' || $publicIp === '::1' || strpos($publicIp, '127.') === 0) {
                    
                    // Try REMOTE_ADDR but skip if it's localhost
                    $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '';
                    if (!empty($remoteAddr) && filter_var($remoteAddr, FILTER_VALIDATE_IP) && 
                        $remoteAddr !== '127.0.0.1' && $remoteAddr !== '::1' && strpos($remoteAddr, '127.') !== 0) {
                        $publicIp = $remoteAddr;
                    } else {
                        // Try other sources as fallback
                        $ipSources = [
                            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '',
                            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
                            $_SERVER['HTTP_X_REAL_IP'] ?? ''
                        ];
                        
                        foreach ($ipSources as $ipSource) {
                            if (!empty($ipSource)) {
                                if (strpos($ipSource, ',') !== false) {
                                    $ipSource = trim(explode(',', $ipSource)[0]);
                                }
                                // Accept both public and private IPs, but skip localhost
                                if (filter_var($ipSource, FILTER_VALIDATE_IP) && 
                                    $ipSource !== '127.0.0.1' && $ipSource !== '::1' && strpos($ipSource, '127.') !== 0) {
                                    $publicIp = $ipSource;
                                    break;
                                }
                            }
                        }
                    }
                }
                
                // Log IP detection result
                if (empty($publicIp) || !filter_var($publicIp, FILTER_VALIDATE_IP)) {
                    error_log("WARNING: Could not detect valid IP address (skipped localhost) - will rely on WiFi/GPS validation");
                } else {
                    error_log("IP Detected: $publicIp (from " . (isset($_POST['public_ip']) ? 'POST' : 'SERVER') . ")");
                }
                
                // Log IP detection for debugging
                error_log("WFO IP Detection - Public IP: " . ($publicIp ?: 'NOT DETECTED') . ", Mode: $wfoMode");
                
                $requireWifi = (int)getSetting($pdo, 'wfo_require_wifi', '1');
                $isInsideTelu = false;
                $isValidWifi = false;
                
                // Check WiFi SSID if required (case-insensitive, exact or partial match)
                // CRITICAL: WiFi SSID validation must work even if IP is localhost
                if ($requireWifi && !empty($wifiSSID)) {
                    $validWifiSSIDs = array_filter(array_map('trim', explode(',', getSetting($pdo, 'wfo_wifi_ssids', 'Telkom University,TelU,WiFi Telkom University,WiFi-TelU,Telkom-University,TelU-Connect,TelU-Guest'))));
                    $wifiSSIDLower = strtolower(trim($wifiSSID));
                    error_log("Checking WiFi SSID: '$wifiSSID' against valid list: " . implode(', ', $validWifiSSIDs));
                    
                    foreach ($validWifiSSIDs as $validSSID) {
                        $validSSIDLower = strtolower(trim($validSSID));
                        // Exact match or partial match - check if WiFi SSID contains valid SSID or vice versa
                        if ($wifiSSIDLower === $validSSIDLower || 
                            strpos($wifiSSIDLower, $validSSIDLower) !== false || 
                            strpos($validSSIDLower, $wifiSSIDLower) !== false) {
                            $isValidWifi = true;
                            error_log("WiFi SSID MATCHED: '$wifiSSID' matches '$validSSID'");
                            break;
                        }
                    }
                    
                    if (!$isValidWifi) {
                        error_log("WiFi SSID NOT MATCHED: '$wifiSSID' does not match any valid SSID");
                    }
                } else if ($requireWifi && empty($wifiSSID)) {
                    error_log("WiFi SSID is empty but WiFi is required - will check IP/GPS fallback");
                }
                
                // Improved location validation with stricter radius for WFO (better accuracy)
                $wfoLat = (float)getSetting($pdo, 'wfo_lat', '-6.9738');
                $wfoLng = (float)getSetting($pdo, 'wfo_lng', '107.6300');
                $wfoRadius = (int)getSetting($pdo, 'wfo_radius_m', '600'); // Reduced radius to 600m for stricter validation and better accuracy
                
                if ($lat !== null && $lng !== null) {
                    $earth = 6371000; // meters
                    $dLat = deg2rad($wfoLat - $lat);
                    $dLng = deg2rad($wfoLng - $lng);
                    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat)) * cos(deg2rad($wfoLat)) * sin($dLng/2) * sin($dLng/2);
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    $distance = $earth * $c;
                    
                    // Improved accuracy: stricter validation using configured radius
                    // Only consider inside if within the configured radius
                    $isInsideTelu = ($distance <= max(0, $wfoRadius));
                    
                    // Log distance for debugging accuracy issues
                    error_log("WFO Distance Check - Distance: " . round($distance) . "m, Radius: {$wfoRadius}m, Inside: " . ($isInsideTelu ? 'Yes' : 'No'));
                }
                
                // PRIORITAS MUTLAK: IP ADDRESS adalah validasi UTAMA (bukan WiFi, bukan GPS)
                // Urutan validasi: 1. IP Address (private 10.x.x.x atau public via API), 2. WiFi (fallback), 3. GPS Location (fallback terakhir)
                // Radius 200m hanya digunakan sebagai fallback jika IP dan WiFi tidak valid
                
                // Log semua data untuk debugging
                error_log("=== WFO VALIDATION DEBUG ===");
                error_log("WiFi SSID: " . ($wifiSSID ?: 'NOT DETECTED') . " (Raw: " . var_export($_POST['wifi_ssid'] ?? 'NOT IN POST', true) . ")");
                error_log("Public IP: " . ($publicIp ?: 'NOT DETECTED'));
                error_log("REMOTE_ADDR: " . ($_SERVER['REMOTE_ADDR'] ?? 'NOT SET'));
                error_log("POST public_ip: " . ($_POST['public_ip'] ?? 'NOT IN POST'));
                error_log("Location: " . ($lokasi ?: 'NOT DETECTED'));
                error_log("GPS Lat: " . ($lat ?? 'NULL') . ", Lng: " . ($lng ?? 'NULL'));
                error_log("GPS Accuracy: " . ($gpsAccuracy !== null ? round($gpsAccuracy) . 'm' : 'N/A'));
                error_log("Distance: " . (isset($distance) ? round($distance) . 'm' : 'N/A') . ", Radius: {$wfoRadius}m");
                error_log("Require WiFi: " . ($requireWifi ? 'YES' : 'NO'));
                error_log("Is Valid WiFi: " . ($isValidWifi ? 'YES' : 'NO'));
                
                $isInsideTeluByApi = false;
                $ketVal = 'wfa'; // Default to WFA, akan diubah jika validasi berhasil
                
                // PRIORITAS 1: IP ADDRESS - Check IP validation FIRST (private atau public)
                // Ini adalah validasi UTAMA berdasarkan IP Telkom University, bukan WiFi
                // SKIP jika IP adalah localhost (127.0.0.1, ::1) - gunakan WiFi/GPS validation
                $isLocalhost = ($publicIp === '127.0.0.1' || $publicIp === '::1' || strpos($publicIp, '127.') === 0);
                
                if (!empty($publicIp) && filter_var($publicIp, FILTER_VALIDATE_IP) && !$isLocalhost) {
                    try {
                        // Check both private IP range (10.x.x.x) and public IP API
                        $isInsideTeluByApi = isWfoByApi($pdo, $publicIp);
                        error_log("WFO IP Check - IP: $publicIp, Result: " . ($isInsideTeluByApi ? 'VALID (Telkom University IP)' : 'INVALID'));
                        
                        // CRITICAL: Jika IP valid (private 10.x.x.x atau public via API), langsung WFO
                        // TIDAK perlu cek WiFi atau GPS - IP adalah prioritas mutlak
                        if ($isInsideTeluByApi) {
                            $ketVal = 'wfo';
                            $isInsideTelu = true;
                            error_log('✓ IP Address valid (Telkom University) - LANGSUNG SET WFO tanpa cek WiFi/GPS');
                        }
                    } catch (Exception $e) {
                        error_log("WFO IP Check Error: " . $e->getMessage());
                        $isInsideTeluByApi = false;
                    }
                } else {
                    if ($isLocalhost) {
                        error_log("WFO IP Check - Skipped (IP is localhost: $publicIp - will use WiFi/GPS validation)");
                    } else {
                        error_log("WFO IP Check - Skipped (IP: " . ($publicIp ?: 'EMPTY') . ")");
                    }
                }
                
                // PRIORITAS 2: WiFi - Hanya jika IP tidak valid (fallback)
                // WiFi hanya digunakan sebagai fallback, bukan prioritas utama
                // CRITICAL: WiFi validation harus bekerja bahkan jika IP tidak terdeteksi (localhost/::1)
                if ($ketVal !== 'wfo') {
                    $hasValidWifi = false;
                    
                    // Check WiFi SSID validation (even if IP is localhost/::1)
                    if ($requireWifi && !empty($wifiSSID)) {
                        $hasValidWifi = $isValidWifi;
                        if ($hasValidWifi) {
                            $ketVal = 'wfo';
                            $isInsideTelu = true;
                            error_log('✓ WiFi SSID valid (' . $wifiSSID . ') - set WFO (fallback karena IP tidak valid/localhost)');
                        } else {
                            error_log('✗ WiFi SSID tidak valid (' . $wifiSSID . ') - Valid SSIDs: ' . getSetting($pdo, 'wfo_wifi_ssids', ''));
                        }
                    } else if ($requireWifi && empty($wifiSSID)) {
                        // WiFi SSID tidak terdeteksi (browser limitation pada laptop)
                        // Cek apakah IP private Telkom University sebagai fallback
                        if (!empty($publicIp) && filter_var($publicIp, FILTER_VALIDATE_IP) && isTelkomUniversityPrivateIp($publicIp)) {
                            $ketVal = 'wfo';
                            $isInsideTelu = true;
                            error_log('✓ WiFi SSID tidak terdeteksi tapi IP private Telkom University (' . $publicIp . ') - set WFO');
                        } else {
                            error_log('✗ WiFi SSID tidak terdeteksi dan IP tidak valid (IP: ' . ($publicIp ?: 'EMPTY') . ')');
                            // If WiFi is required but not detected and IP is invalid, we need WFA reason
                            // But let GPS check happen first as fallback
                        }
                    } else if (!$requireWifi) {
                        // WiFi tidak wajib - skip WiFi validation
                        error_log('WiFi tidak wajib - skip WiFi validation');
                    }
                }
                
                // PRIORITAS 3: GPS Location - Hanya jika IP dan WiFi tidak valid (fallback terakhir)
                // CRITICAL: Location string (lokasi) is NEVER used for validation - it's only for display
                // Only GPS coordinates (lat/lng) and distance calculation are used for validation
                // This prevents users from manipulating location string to bypass validation
                if ($ketVal !== 'wfo' && $lat !== null && $lng !== null) {
                    // Only use GPS coordinates and distance - location string is ignored for validation
                    if ($isInsideTelu) {
                        // GPS inside radius - set WFO (fallback terakhir, but stricter)
                        // Only accept if distance is within configured radius (no lenient 1000m check)
                        if ($distance <= $wfoRadius) {
                            $ketVal = 'wfo';
                            error_log('✓ GPS inside radius - set WFO berdasarkan GPS (fallback terakhir, distance: ' . round($distance) . 'm, radius: ' . $wfoRadius . 'm)');
                        } else {
                            // GPS outside radius - tetap WFA (stricter validation)
                            $ketVal = 'wfa';
                            error_log('✗ GPS outside radius - require WFA (distance: ' . round($distance) . 'm, radius: ' . $wfoRadius . 'm)');
                        }
                    } else {
                        // GPS outside radius - tetap WFA
                        $ketVal = 'wfa';
                        error_log('✗ GPS outside radius - require WFA (distance: ' . round($distance) . 'm, radius: ' . $wfoRadius . 'm)');
                    }
                } else if ($ketVal !== 'wfo') {
                    // Tidak ada GPS data dan IP/WiFi tidak valid
                    $ketVal = 'wfa';
                    error_log('✗ Semua validasi gagal - require WFA (IP: ' . ($publicIp ?: 'EMPTY') . ', WiFi: ' . ($wifiSSID ?: 'EMPTY') . ', GPS: ' . (($lat && $lng) ? 'OK' : 'MISSING') . ')');
                }
                
                // Final check - jika bukan WFO, require alasan WFA
                if ($ketVal === 'wfa') {
                    $alasanWfa = $_POST['wfa_reason'] ?? $_POST['alasan_wfa'] ?? null;
                    if (!$alasanWfa) {
                        jsonResponse(['ok' => false, 'need_reason' => true, 'message' => 'Di luar wilayah kantor atau tidak terhubung ke WiFi/IP Telkom University. Harap isi alasan kerja di luar (WFA).'], 400);
                    }
                }

                // ULTRA-FAST: Minimal insert for maximum speed
                $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, screenshot_masuk, lokasi_masuk, lat_masuk, lng_masuk, status, ket, alasan_wfa, alasan_overtime, lokasi_overtime) VALUES (:uid, :jam, :iso, :exp, :screenshot, :lokasi, :lat, :lng, :status, :ket, :alasan, :alasan_ot, :lokasi_ot)");
                $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':status' => $status, ':ket' => $ketVal, ':alasan' => $alasanWfa, ':alasan_ot' => null, ':lokasi_ot' => null]);
                
                // OPTIMIZED: Backup trigger removed - happens on schedule
                // triggerDatabaseBackup();
                
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
            
            // Check if checked in today and not yet checked out
            $todayCheck = $pdo->prepare("SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today AND jam_pulang_iso IS NULL ORDER BY jam_masuk_iso DESC LIMIT 1");
            $todayCheck->execute([':uid' => $u['id'], ':today' => $today]);
            $todayRow = $todayCheck->fetch();
            
            if (!$todayRow) {
                $statusText = "Anda belum melakukan presensi masuk hari ini atau sudah pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
            } else {
                // Check if pulang sebelum jam yang diizinkan
                if ($currentHour < $minCheckoutHour) {
                    // Minta alasan pulang awal
                    $alasanPulangAwal = $_POST['alasan_pulang_awal'] ?? $_POST['early_leave_reason'] ?? null;
                    if (!$alasanPulangAwal) {
                        $firstName = getFirstName($u['nama']);
                        $statusText = "Anda pulang sebelum jam {$minCheckoutHour}:00. Harap isi alasan pulang awal.";
                        jsonResponse(['ok' => false, 'need_early_leave_reason' => true, 'message' => $statusText, 'statusClass' => 'bg-orange-100 text-orange-700']);
                    }
                }
                
                $lat = isset($_POST['lat']) ? (float)$_POST['lat'] : null;
                $lng = isset($_POST['lng']) ? (float)$_POST['lng'] : null;
                $lokasi = $_POST['lokasi'] ?? null;
                
                // OPTIMIZED: Quick reverse geocoding - ensure lokasi is never empty
                if (empty($lokasi) || strpos($lokasi, 'Lokasi:') === 0) {
                    if ($lat !== null && $lng !== null) {
                        // Try reverse geocoding with shorter timeout
                        $reverseGeocoded = @reverseGeocodeAddress($lat, $lng);
                        if ($reverseGeocoded && !empty($reverseGeocoded)) {
                            $lokasi = $reverseGeocoded;
                        } else {
                            // Fallback - ensure lokasi is never empty
                            $lokasi = 'Lokasi: ' . round($lat, 6) . ', ' . round($lng, 6);
                        }
                    } else {
                        // No coordinates - use default
                        $lokasi = 'Lokasi tidak tersedia';
                    }
                }
                
                // Final validation - ensure lokasi is never empty
                if (empty($lokasi)) {
                    if ($lat !== null && $lng !== null) {
                        $lokasi = 'Lokasi: ' . round($lat, 6) . ', ' . round($lng, 6);
                    } else {
                        $lokasi = 'Lokasi tidak tersedia';
                    }
                }
                
                // Get alasan pulang awal if provided
                $alasanPulangAwal = $_POST['alasan_pulang_awal'] ?? $_POST['early_leave_reason'] ?? null;
                
                $upd = $pdo->prepare("UPDATE attendance SET jam_pulang=:jam, jam_pulang_iso=:iso, ekspresi_pulang=:exp, screenshot_pulang=:screenshot, lokasi_pulang=:lokasi, lat_pulang=:lat, lng_pulang=:lng, alasan_pulang_awal=:alasan WHERE id=:id");
                $upd->execute([':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':alasan' => $alasanPulangAwal, ':id' => $todayRow['id']]);
                
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
        $alasan_wfa = $_POST['alasan_wfa'] ?? $_POST['wfa_reason'] ?? null;
        $alasan_overtime = $_POST['alasan_overtime'] ?? $_POST['overtime_reason'] ?? null;
        $lokasi_overtime = $_POST['lokasi_overtime'] ?? $_POST['overtime_location'] ?? null;
        
        // Validate overtime fields
        if ($type === 'overtime') {
            if (empty($alasan_overtime) || trim($alasan_overtime) === '') {
                jsonResponse(['ok' => false, 'message' => 'Alasan overtime wajib diisi'], 400);
            }
            if (empty($lokasi_overtime) || trim($lokasi_overtime) === '') {
                jsonResponse(['ok' => false, 'message' => 'Lokasi overtime wajib diisi'], 400);
            }
        }
        
        // Validate WFA fields
        if ($type === 'wfa' && (empty($alasan_wfa) || trim($alasan_wfa) === '')) {
            jsonResponse(['ok' => false, 'message' => 'Alasan WFA wajib diisi'], 400);
        }
        
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
                if ($type === 'overtime') {
                    // For overtime, use alasan_overtime and lokasi_overtime
                    $sql = "INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_wfa, alasan_overtime, lokasi_overtime) VALUES (:u, :jm, :jmiso, :jp, :jpiso, :s, :ket, :alasan_wfa, :alasan_ot, :lokasi_ot)";
                    $ins = $pdo->prepare($sql);
                    $result = $ins->execute([
                        ':u' => $user_id,
                        ':jm' => $jam_masuk,
                        ':jmiso' => $jam_masuk_iso,
                        ':jp' => $jam_pulang,
                        ':jpiso' => $jam_pulang_iso,
                        ':s' => $status,
                        ':ket' => $type,
                        ':alasan_wfa' => null,
                        ':alasan_ot' => $alasan_overtime,
                        ':lokasi_ot' => $lokasi_overtime
                    ]);
                } else {
                    // For wfa, use alasan_wfa
                    $sql = "INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_wfa, alasan_overtime, lokasi_overtime) VALUES (:u, :jm, :jmiso, :jp, :jpiso, :s, :ket, :alasan_wfa, :alasan_ot, :lokasi_ot)";
                    $ins = $pdo->prepare($sql);
                    $result = $ins->execute([
                        ':u' => $user_id,
                        ':jm' => $jam_masuk,
                        ':jmiso' => $jam_masuk_iso,
                        ':jp' => $jam_pulang,
                        ':jpiso' => $jam_pulang_iso,
                        ':s' => $status,
                        ':ket' => $type,
                        ':alasan_wfa' => $alasan_wfa,
                        ':alasan_ot' => null,
                        ':lokasi_ot' => null
                    ]);
                }
                
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
        
        $fields = ['jam_masuk','jam_pulang','ekspresi_masuk','ekspresi_pulang','status','ket','screenshot_masuk','screenshot_pulang','alasan_wfa','alasan_overtime','lokasi_overtime','alasan_izin_sakit','bukti_izin_sakit'];
        $set=[]; $params=[':id'=>$id];
        
        // Get date from current record for ISO time construction
        $datePart = $currentRecord ? date('Y-m-d', strtotime($currentRecord['attendance_date'])) : date('Y-m-d');
        
        // Handle jam_masuk and jam_masuk_iso
        // Frontend sends jam_masuk in HH:MM:SS format
        if(isset($_POST['jam_masuk']) && $_POST['jam_masuk'] !== '') {
            $jam_masuk_value = $_POST['jam_masuk'];
            // Extract HH:MM for jam_masuk field (remove seconds if present)
            $jam_masuk_hhmm = preg_match('/^(\d{2}:\d{2})/', $jam_masuk_value, $matches) ? $matches[1] : $jam_masuk_value;
            $set[] = "jam_masuk = :jam_masuk";
            $params[':jam_masuk'] = $jam_masuk_hhmm;
            
            // Construct ISO time from jam_masuk if not explicitly provided
            if(!isset($_POST['jam_masuk_iso']) || $_POST['jam_masuk_iso'] === '') {
                // Ensure we have full time format (HH:MM:SS)
                $time_part = preg_match('/^(\d{2}:\d{2})(:?\d{2})?$/', $jam_masuk_value, $time_matches) 
                    ? ($time_matches[2] ? $jam_masuk_value : $jam_masuk_value . ':00')
                    : $jam_masuk_value;
                $jam_masuk_iso = $datePart . ' ' . $time_part;
                $set[] = 'jam_masuk_iso = :jmiso';
                $params[':jmiso'] = $jam_masuk_iso;
            }
        }
        // If jam_masuk_iso is explicitly provided, use it
        if(isset($_POST['jam_masuk_iso']) && $_POST['jam_masuk_iso'] !== '' && (!isset($_POST['jam_masuk']) || $_POST['jam_masuk'] === '')) {
            $set[] = 'jam_masuk_iso = :jmiso';
            $params[':jmiso'] = $_POST['jam_masuk_iso'];
        }
        
        // Handle jam_pulang and jam_pulang_iso
        // Frontend sends jam_pulang in HH:MM:SS format
        if(isset($_POST['jam_pulang']) && $_POST['jam_pulang'] !== '') {
            $jam_pulang_value = $_POST['jam_pulang'];
            // Extract HH:MM for jam_pulang field (remove seconds if present)
            $jam_pulang_hhmm = preg_match('/^(\d{2}:\d{2})/', $jam_pulang_value, $matches) ? $matches[1] : $jam_pulang_value;
            $set[] = "jam_pulang = :jam_pulang";
            $params[':jam_pulang'] = $jam_pulang_hhmm;
            
            // Construct ISO time from jam_pulang if not explicitly provided
            if(!isset($_POST['jam_pulang_iso']) || $_POST['jam_pulang_iso'] === '') {
                // Ensure we have full time format (HH:MM:SS)
                $time_part = preg_match('/^(\d{2}:\d{2})(:?\d{2})?$/', $jam_pulang_value, $time_matches) 
                    ? ($time_matches[2] ? $jam_pulang_value : $jam_pulang_value . ':00')
                    : $jam_pulang_value;
                $jam_pulang_iso = $datePart . ' ' . $time_part;
                $set[] = 'jam_pulang_iso = :jpiso';
                $params[':jpiso'] = $jam_pulang_iso;
            }
        }
        // If jam_pulang_iso is explicitly provided, use it
        if(isset($_POST['jam_pulang_iso']) && $_POST['jam_pulang_iso'] !== '' && (!isset($_POST['jam_pulang']) || $_POST['jam_pulang'] === '')) {
            $set[] = 'jam_pulang_iso = :jpiso';
            $params[':jpiso'] = $_POST['jam_pulang_iso'];
        }
        
        // Handle other fields
        foreach($fields as $f){ 
            if($f !== 'jam_masuk' && $f !== 'jam_pulang') { // Skip jam_masuk and jam_pulang as they're handled above
                if(isset($_POST[$f])){ 
                    $set[] = "$f = :$f"; 
                    $params[":$f"] = $_POST[$f]!==''? $_POST[$f] : null; 
                } 
            }
        }
        
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

    // Admin: list backup files
    if ($action === 'list_backup_files' && ($_SERVER['REQUEST_METHOD'] === 'GET' || $_SERVER['REQUEST_METHOD'] === 'POST')) {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $backupDir = __DIR__ . '/database_backup';
        $files = [];
        $timezone = new DateTimeZone('Asia/Jakarta');
        
        // Always add "Current Database" option (generated on-the-fly)
        $currentTime = new DateTime('now', $timezone);
        $files[] = [
            'name' => 'current_database_backup.sql',
            'size' => 0, // Will be calculated on download
            'size_formatted' => 'Current Database',
            'created' => $currentTime->format('Y-m-d H:i:s'),
            'modified' => $currentTime->format('Y-m-d H:i:s'),
            'is_current' => true,
            'description' => 'Backup langsung dari database saat ini (selalu terbaru)'
        ];
        
        if (is_dir($backupDir)) {
            $items = scandir($backupDir);
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_file($backupDir . '/' . $item)) {
                    $filePath = $backupDir . '/' . $item;
                    $timestamp = filemtime($filePath);
                    
                    // Convert timestamp to Asia/Jakarta timezone
                    $dateTime = new DateTime('@' . $timestamp);
                    $dateTime->setTimezone($timezone);
                    $formattedDate = $dateTime->format('Y-m-d H:i:s');
                    
                    $files[] = [
                        'name' => $item,
                        'size' => filesize($filePath),
                        'size_formatted' => function_exists('formatBytes') ? formatBytes(filesize($filePath)) : number_format(filesize($filePath) / 1024, 2) . ' KB',
                        'created' => $formattedDate,
                        'modified' => $formattedDate,
                        'is_current' => false
                    ];
                }
            }
        }
        
        // Sort by modified date (newest first), but keep current_database_backup.sql at top
        usort($files, function($a, $b) {
            if (isset($a['is_current']) && $a['is_current']) return -1;
            if (isset($b['is_current']) && $b['is_current']) return 1;
            return strtotime($b['modified']) - strtotime($a['modified']);
        });
        
        jsonResponse(['ok' => true, 'data' => $files]);
    }

    // Admin: download backup file
    if ($action === 'download_backup' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        if (!isAdmin()) {
            http_response_code(403);
            die('Forbidden');
        }
        
        $fileName = $_GET['file'] ?? '';
        if (empty($fileName)) {
            http_response_code(400);
            die('File name required');
        }
        
        // Special case: download current database backup (generate on-the-fly)
        if ($fileName === 'current_database_backup.sql' || $fileName === 'database_current.sql') {
            if (!function_exists('createDatabaseBackupPHP')) {
                http_response_code(500);
                die('Backup function not available');
            }
            
            $result = createDatabaseBackupPHP($pdo);
            if (!$result['success']) {
                http_response_code(500);
                die('Failed to generate backup: ' . $result['message']);
            }
            
            $sqlContent = $result['sql_content'];
            $downloadFileName = 'absen_db_backup_' . date('Y-m-d_His') . '.sql';
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . strlen($sqlContent));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output SQL content
            echo $sqlContent;
            exit;
        }
        
        // Security: only allow files in backup directory, prevent directory traversal
        $backupDir = __DIR__ . '/database_backup';
        $filePath = $backupDir . '/' . basename($fileName);
        
        // Verify file is in backup directory
        $realBackupDir = realpath($backupDir);
        $realFilePath = realpath($filePath);
        
        if (!$realFilePath || ($realBackupDir && strpos($realFilePath, $realBackupDir) !== 0)) {
            // If file doesn't exist in backup directory, try generating from database
            if (!function_exists('createDatabaseBackupPHP')) {
                http_response_code(404);
                die('File not found');
            }
            
            $result = createDatabaseBackupPHP($pdo);
            if (!$result['success']) {
                http_response_code(404);
                die('File not found and failed to generate backup');
            }
            
            $sqlContent = $result['sql_content'];
            $downloadFileName = basename($fileName);
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . strlen($sqlContent));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output SQL content
            echo $sqlContent;
            exit;
        }
        
        if (!file_exists($filePath)) {
            // File doesn't exist, generate from database
            if (!function_exists('createDatabaseBackupPHP')) {
                http_response_code(404);
                die('File not found');
            }
            
            $result = createDatabaseBackupPHP($pdo);
            if (!$result['success']) {
                http_response_code(404);
                die('File not found and failed to generate backup');
            }
            
            $sqlContent = $result['sql_content'];
            $downloadFileName = basename($fileName);
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . strlen($sqlContent));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output SQL content
            echo $sqlContent;
            exit;
        }
        
        // Set headers for file download
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . basename($fileName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        
        // Output file
        readfile($filePath);
        exit;
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
        error_log("[DEBUG] update_settings hit!");
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $maxOntimeRaw = trim($_POST['max_ontime_hour'] ?? '');
        $minCheckoutRaw = trim($_POST['min_checkout_hour'] ?? '');
        
        // Handle HH:mm format from type="time"
        $maxOntimeHour = strpos($maxOntimeRaw, ':') !== false ? explode(':', $maxOntimeRaw)[0] : $maxOntimeRaw;
        $minCheckoutHour = strpos($minCheckoutRaw, ':') !== false ? explode(':', $minCheckoutRaw)[0] : $minCheckoutRaw;

        $wfoAddress = trim($_POST['wfo_address'] ?? '');
        $wfoLat = trim($_POST['wfo_lat'] ?? '');
        $wfoLng = trim($_POST['wfo_lng'] ?? '');
        $wfoRadius = trim($_POST['wfo_radius_m'] ?? '');
        $periodEnd = trim($_POST['attendance_period_end'] ?? '');
        $kpiLatePenalty = trim($_POST['kpi_late_penalty'] ?? '');
        $kpiIzinSakit = trim($_POST['kpi_izin_sakit'] ?? '');
        $kpiAlpha = trim($_POST['kpi_alpha'] ?? '');
        $kpiOvertimeBonus = trim($_POST['kpi_overtime_bonus'] ?? '');
        $maxDailyReportDaysBack = trim($_POST['max_daily_report_days_back'] ?? '');
        $maxMonthlyReportMonthsBack = trim($_POST['max_monthly_report_months_back'] ?? '');
        $monthlyReportEndYear = trim($_POST['monthly_report_end_year'] ?? '');
        $faceRecognitionThreshold = trim($_POST['face_recognition_threshold'] ?? '');
        $faceRecognitionInputSize = trim($_POST['face_recognition_input_size'] ?? '');
        $faceRecognitionScoreThreshold = trim($_POST['face_recognition_score_threshold'] ?? '');
        $faceRecognitionQualityThreshold = trim($_POST['face_recognition_quality_threshold'] ?? '');
        $geocodeTimeout = trim($_POST['geocode_timeout'] ?? '');
        $geocodeAccuracyRadius = trim($_POST['geocode_accuracy_radius'] ?? '');
        
        // WFO API settings
        $wfoMode = trim($_POST['wfo_mode'] ?? '');
        $wfoApiProvider = trim($_POST['wfo_api_provider'] ?? '');
        $wfoApiToken = trim($_POST['wfo_api_token'] ?? '');
        $wfoApiOrgKeywords = trim($_POST['wfo_api_org_keywords'] ?? '');
        $wfoApiAsnList = trim($_POST['wfo_api_asn_list'] ?? '');
        $wfoApiCidrList = trim($_POST['wfo_api_cidr_list'] ?? '');
        $wfoWifiSSIDs = trim($_POST['wfo_wifi_ssids'] ?? '');
        $wfoRequireWifi = trim($_POST['wfo_require_wifi'] ?? '');
        
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
        if ($wfoWifiSSIDs !== '') setSetting($pdo, 'wfo_wifi_ssids', $wfoWifiSSIDs);
        if ($wfoRequireWifi !== '') setSetting($pdo, 'wfo_require_wifi', $wfoRequireWifi);
        
        // Save report settings
        if ($maxDailyReportDaysBack !== '') setSetting($pdo, 'max_daily_report_days_back', $maxDailyReportDaysBack);
        if ($maxMonthlyReportMonthsBack !== '') setSetting($pdo, 'max_monthly_report_months_back', $maxMonthlyReportMonthsBack);
        if ($monthlyReportEndYear !== '') setSetting($pdo, 'monthly_report_end_year', $monthlyReportEndYear);
        
        // Save face recognition settings
        if ($faceRecognitionThreshold !== '' && is_numeric($faceRecognitionThreshold) && $faceRecognitionThreshold >= 0 && $faceRecognitionThreshold <= 1) {
            setSetting($pdo, 'face_recognition_threshold', $faceRecognitionThreshold);
        }
        if ($faceRecognitionInputSize !== '' && is_numeric($faceRecognitionInputSize) && $faceRecognitionInputSize >= 224 && $faceRecognitionInputSize <= 640) {
            setSetting($pdo, 'face_recognition_input_size', $faceRecognitionInputSize);
        }
        if ($faceRecognitionScoreThreshold !== '' && is_numeric($faceRecognitionScoreThreshold) && $faceRecognitionScoreThreshold >= 0 && $faceRecognitionScoreThreshold <= 1) {
            setSetting($pdo, 'face_recognition_score_threshold', $faceRecognitionScoreThreshold);
        }
        if ($faceRecognitionQualityThreshold !== '' && is_numeric($faceRecognitionQualityThreshold) && $faceRecognitionQualityThreshold >= 0 && $faceRecognitionQualityThreshold <= 1) {
            setSetting($pdo, 'face_recognition_quality_threshold', $faceRecognitionQualityThreshold);
        }
        
        // Save geocode settings
        if ($geocodeTimeout !== '' && is_numeric($geocodeTimeout) && $geocodeTimeout >= 1 && $geocodeTimeout <= 10) {
            setSetting($pdo, 'geocode_timeout', $geocodeTimeout);
        }
        if ($geocodeAccuracyRadius !== '' && is_numeric($geocodeAccuracyRadius) && $geocodeAccuracyRadius >= 10 && $geocodeAccuracyRadius <= 200) {
            setSetting($pdo, 'geocode_accuracy_radius', $geocodeAccuracyRadius);
        }
        
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
        $term = strtolower(trim($_REQUEST['term'] ?? ''));
        $startup = trim($_REQUEST['startup'] ?? '');
        $year = (int)($_REQUEST['year'] ?? 0);
        $month = (int)($_REQUEST['month'] ?? 0);
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
        // For monthly performance, always use current month only (not entire period)
        $monthStart = date('Y-m-01');
        $monthEnd = date('Y-m-t');
        
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
        
        // Get monthly attendance statistics for current month only
        // Only count actual attendance records (ontime/terlambat) within current month
        // Count distinct dates to match KPI calculation logic (one count per day)
        // Also calculate average time for sorting when counts are equal
        $monthlyStatsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                u.foto_base64,
                COUNT(DISTINCT CASE WHEN a.status = 'terlambat' THEN DATE(a.jam_masuk_iso) END) as late_count,
                COUNT(DISTINCT CASE WHEN a.status = 'ontime' THEN DATE(a.jam_masuk_iso) END) as ontime_count,
                COUNT(DISTINCT CASE WHEN a.id IS NOT NULL AND (a.ket = 'wfo' OR a.ket = 'wfa') THEN DATE(a.jam_masuk_iso) END) as present_count,
                COUNT(DISTINCT DATE(a.jam_masuk_iso)) as total_days,
                SEC_TO_TIME(AVG(CASE WHEN a.status = 'ontime' THEN TIME_TO_SEC(TIME(a.jam_masuk_iso)) END)) as avg_ontime_time,
                SEC_TO_TIME(AVG(CASE WHEN a.status = 'terlambat' THEN TIME_TO_SEC(TIME(a.jam_masuk_iso)) END)) as avg_late_time
            FROM users u
            LEFT JOIN attendance a ON u.id = a.user_id 
                AND DATE(a.jam_masuk_iso) BETWEEN :month_start AND :month_end
                AND (a.status = 'ontime' OR a.status = 'terlambat')
            WHERE u.role = 'pegawai'
            GROUP BY u.id, u.nama, u.foto_base64
            HAVING total_days > 0
            ORDER BY late_count DESC, ontime_count DESC
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
        
        // Get daily report statistics - count missing reports with employee details
        // Count employees who have attendance but no daily report for dates up to today
        $currentDateForReports = date('Y-m-d');
        
        // Get summary statistics
        $dailyReportSummaryStmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT a.user_id) as employees_without_reports,
                COUNT(*) as total_missing_reports
            FROM attendance a
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa')
                AND dr.id IS NULL
        ");
        $dailyReportSummaryStmt->execute([':current_date' => $currentDateForReports]);
        $dailyReportStats = $dailyReportSummaryStmt->fetch();
        
        // Get detailed list of employees with missing reports, sorted by count
        $dailyReportDetailsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                u.foto_base64,
                COUNT(*) as missing_count
            FROM attendance a
            JOIN users u ON u.id = a.user_id
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa')
                AND dr.id IS NULL
                AND u.role = 'pegawai'
            GROUP BY u.id, u.nama, u.foto_base64
            ORDER BY missing_count DESC
            LIMIT 10
        ");
        $dailyReportDetailsStmt->execute([':current_date' => $currentDateForReports]);
        $dailyReportDetails = $dailyReportDetailsStmt->fetchAll();
        
        jsonResponse([
            'ok' => true,
            'data' => [
                'today_late' => $todayLate,
                'monthly_stats' => $monthlyStats,
                'attendance_trend' => $trendData,
                'daily_report_stats' => [
                    'employees_without_reports' => (int)$dailyReportStats['employees_without_reports'],
                    'total_missing_reports' => (int)$dailyReportStats['total_missing_reports'],
                    'employee_details' => $dailyReportDetails
                ],
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

    // Public endpoint for daily report statistics (no login required)
    if ($action === 'get_public_daily_report_stats' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        $currentDateForReports = date('Y-m-d');
        
        // Get all employees with their missing report counts (including those with 0 missing)
        $dailyReportDetailsStmt = $pdo->prepare("
            SELECT 
                u.id,
                u.nama,
                u.foto_base64,
                COALESCE((
                    SELECT COUNT(DISTINCT DATE(a2.jam_masuk_iso))
                    FROM attendance a2
                    LEFT JOIN daily_reports dr2 ON dr2.user_id = a2.user_id 
                        AND dr2.report_date = DATE(a2.jam_masuk_iso)
                    WHERE a2.user_id = u.id
                        AND DATE(a2.jam_masuk_iso) >= DATE(u.created_at)
                        AND DATE(a2.jam_masuk_iso) <= :current_date
                        AND (a2.ket = 'wfo' OR a2.ket = 'wfa')
                        AND dr2.id IS NULL
                ), 0) as missing_count
            FROM users u
            WHERE u.role = 'pegawai'
            ORDER BY missing_count DESC, u.nama ASC
        ");
        $dailyReportDetailsStmt->execute([':current_date' => $currentDateForReports]);
        $dailyReportDetails = $dailyReportDetailsStmt->fetchAll();
        
        jsonResponse([
            'ok' => true,
            'data' => [
                'employee_details' => $dailyReportDetails
            ]
        ]);
    }

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

        // Get employee registration date
        $employeeRegDate = getEmployeeRegistrationDate($pdo, $uid);
        $employeeRegDateOnly = $employeeRegDate ? date('Y-m-d', strtotime($employeeRegDate)) : null;

        // Fetch attendance and reports for month (including overtime on weekends/holidays)
        $attStmt = $pdo->prepare("SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso) BETWEEN :s AND :e");
        $attStmt->execute([':uid'=>$uid, ':s'=>$start, ':e'=>$end]);
        $attRows = $attStmt->fetchAll();
        $attByDate = [];
        foreach($attRows as $r){ $d = date('Y-m-d', strtotime($r['jam_masuk_iso'])); $attByDate[$d] = $r; }

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

        // Get manual holidays for the month
        $manualHolidays = getManualHolidaysInRange($pdo, $start, $end);
        $manualHolidayDates = [];
        foreach($manualHolidays as $h){ $manualHolidayDates[$h['date']] = true; }

        $drStmt = $pdo->prepare("SELECT * FROM daily_reports WHERE user_id=:uid AND report_date BETWEEN :s AND :e");
        $drStmt->execute([':uid'=>$uid, ':s'=>$start, ':e'=>$end]);
        $drByDate = [];
        foreach($drStmt->fetchAll() as $r){ $drByDate[$r['report_date']]=$r; }

        // Build all days in month (including weekends)
        $out = [];
        $cur = new DateTime($start);
        $endDt = new DateTime($end);
        while($cur <= $endDt){
            $dstr = $cur->format('Y-m-d');
            $dow = (int)$cur->format('N'); // 1 Mon .. 7 Sun
            $att = $attByDate[$dstr] ?? null;
            $notes = $notesByDate[$dstr] ?? null;
            $dr = $drByDate[$dstr] ?? null;
            
            // Check if date is before employee registration
            $isBeforeRegistration = $employeeRegDateOnly && $dstr < $employeeRegDateOnly;
            
            // Check if date is manual holiday
            $isManualHolidayDate = isset($manualHolidayDates[$dstr]);
            
            // Check if date is national holiday
            $isNationalHolidayDate = isNationalHoliday($dstr);
            
            // Check if date is weekend
            $isWeekend = $dow >= 6; // Saturday = 6, Sunday = 7
            
            // Check if date is working day for this employee
            $isWorkingDay = isEmployeeWorkingDay($pdo, $uid, $dstr);
            
            // Determine ket value
            $ket = null;
            if ($att && $att['ket']) {
                $ket = $att['ket'];
            } elseif ($notes && $notes['type']) {
                $ket = $notes['type'];
            } elseif ($isManualHolidayDate) {
                $ket = 'libur';
            } elseif ($isBeforeRegistration) {
                $ket = 'na'; // Not Available
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
                'daily_report'=> $reportContent,
                'is_working_day'=>$isWorkingDay,
                'is_weekend'=>$isWeekend,
                'is_manual_holiday'=>$isManualHolidayDate,
                'is_national_holiday'=>$isNationalHolidayDate,
                'is_before_registration'=>$isBeforeRegistration
            ];
            $cur->modify('+1 day');
        }
        jsonResponse(['ok'=>true,'data'=>$out]);
    }

    // Get missing daily reports for current user - all dates during period
    if ($action === 'get_missing_daily_reports' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        if (!isset($_SESSION['user'])) jsonResponse(['error'=>'Unauthorized'],401);
        $uid = (int)$_SESSION['user']['id'];
        
        // Get employee registration date to determine period start
        $employeeRegDate = getEmployeeRegistrationDate($pdo, $uid);
        $employeeRegDateOnly = $employeeRegDate ? date('Y-m-d', strtotime($employeeRegDate)) : null;
        
        // Use registration date as start, or fallback to start of current year instead of month
        // Broadened to at least 90 days ago if registration is recent/missing to ensure we catch all missing reports
        $startDate = $employeeRegDateOnly ? $employeeRegDateOnly : date('Y-m-d', strtotime('-90 days'));
        
        // If registration date is today, look back at least 30 days anyway 
        // because sometimes the registration date is set to the current date on host
        if ($employeeRegDateOnly === date('Y-m-d')) {
            $startDate = date('Y-m-d', strtotime('-30 days'));
        }
        
        $endDate = date('Y-m-d');
        
        // Get attendance records that don't have daily reports for all period
        // Added 'overtime' to the criteria as users should also fill reports for overtime work
        $stmt = $pdo->prepare("
            SELECT DISTINCT DATE(a.jam_masuk_iso) as date
            FROM attendance a
            LEFT JOIN daily_reports dr ON dr.user_id = a.user_id 
                AND dr.report_date = DATE(a.jam_masuk_iso)
            WHERE a.user_id = :uid
                AND DATE(a.jam_masuk_iso) BETWEEN :start_date AND :end_date
                AND DATE(a.jam_masuk_iso) <= :current_date
                AND (a.ket = 'wfo' OR a.ket = 'wfa' OR a.ket = 'overtime')
                AND dr.id IS NULL
            ORDER BY DATE(a.jam_masuk_iso) DESC
        ");
        $stmt->execute([
            ':uid' => $uid, 
            ':start_date' => $startDate, 
            ':end_date' => $endDate,
            ':current_date' => $endDate
        ]);
        $missingDates = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        jsonResponse(['ok' => true, 'data' => $missingDates]);
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


}

// ----- PAGE ROUTING -----
$page = $_GET['page'] ?? '';
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=landing');
    exit;
}






// Professional Excel Export Helper (XML Spreadsheet 2003)
function exportToExcelXML($filename, $sheets) {
    if (ob_get_level()) ob_end_clean();
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    header('Cache-Control: max-age=0');

    echo '<?xml version="1.0"?>' . "\n";
    echo '<?mso-application progid="Excel.Sheet"?>' . "\n";
    echo '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:o="urn:schemas-microsoft-com:office:office"' . "\n";
    echo ' xmlns:x="urn:schemas-microsoft-com:office:excel"' . "\n";
    echo ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet"' . "\n";
    echo ' xmlns:html="http://www.w3.org/TR/REC-html40">' . "\n";
    
    echo " <Styles>\n";
    echo '  <Style ss:ID="Default" ss:Name="Normal"><Alignment ss:Vertical="Bottom"/><Borders/><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#000000"/><Interior/><NumberFormat/><Protection/></Style>' . "\n";
    echo '  <Style ss:ID="sHeader"><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Color="#FFFFFF" ss:Bold="1"/><Interior ss:Color="#4F81BD" ss:Pattern="Solid"/><Alignment ss:Horizontal="Center" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>' . "\n";
    echo '  <Style ss:ID="sSubHeader"><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="10" ss:Color="#000000" ss:Bold="1"/><Interior ss:Color="#D9D9D9" ss:Pattern="Solid"/><Alignment ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>' . "\n";
    echo '  <Style ss:ID="sInfoLabel"><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="11" ss:Bold="1"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>' . "\n";
    echo '  <Style ss:ID="sData"><Borders><Border ss:Position="Bottom" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Left" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Right" ss:LineStyle="Continuous" ss:Weight="1"/><Border ss:Position="Top" ss:LineStyle="Continuous" ss:Weight="1"/></Borders></Style>' . "\n";
    echo '  <Style ss:ID="sTitle"><Font ss:FontName="Calibri" x:Family="Swiss" ss:Size="14" ss:Bold="1"/><Alignment ss:Horizontal="Left" ss:Vertical="Center"/></Style>' . "\n";
    echo " </Styles>\n";

    foreach ($sheets as $sheetName => $sheet) {
        $cleanName = preg_replace('/[\\\\\/\\?\\*\\[\\]]/', '', $sheetName);
        $cleanName = substr($cleanName, 0, 31);
        echo ' <Worksheet ss:Name="' . htmlspecialchars($cleanName) . '">' . "\n";
        echo '  <Table>' . "\n";
        
        // --- Calculate Column Widths ---
        $colWidths = [];
        // Check Title
        if (isset($sheet['title'])) {
            $len = strlen($sheet['title']);
            $colWidths[0] = max($colWidths[0] ?? 0, $len * 7);
        }
        // Check Header
        if (isset($sheet['header'])) {
            foreach ($sheet['header'] as $i => $h) {
                $len = strlen($h);
                $colWidths[$i] = max($colWidths[$i] ?? 0, $len * 7 + 20);
            }
        }
        // Check Rows
        if (isset($sheet['rows'])) {
            foreach ($sheet['rows'] as $r) {
                // Ignore _style helper key for calculation
                $dataParts = array_filter(array_keys($r), function($k) { return $k !== '_style'; });
                $idx = 0;
                foreach ($r as $k => $val) {
                    if ($k === '_style') continue;
                    $len = strlen((string)$val);
                    $colWidths[$idx] = max($colWidths[$idx] ?? 0, $len * 6.5 + 5);
                    $idx++;
                }
            }
        }
        // Output Column Tags
        foreach ($colWidths as $w) {
            $w = min($w, 400); // Cap at 400 units
            echo '   <Column ss:Width="' . $w . '"/>' . "\n";
        }
        // --- End Width Calculation ---

        if (isset($sheet['title'])) {
               echo '   <Row ss:Height="25"><Cell ss:StyleID="sTitle"><Data ss:Type="String">' . htmlspecialchars($sheet['title']) . '</Data></Cell></Row>' . "\n";
               echo '   <Row></Row>' . "\n";
        }

        if (isset($sheet['header'])) {
            echo '   <Row ss:Height="20">' . "\n";
            foreach ($sheet['header'] as $h) {
                echo '    <Cell ss:StyleID="sHeader"><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>' . "\n";
            }
            echo '   </Row>' . "\n";
        }

        if (isset($sheet['rows'])) {
            foreach ($sheet['rows'] as $r) {
                $rowStyle = $r['_style'] ?? 'sData';
                unset($r['_style']); 
                
                echo '   <Row>' . "\n";
                foreach ($r as $val) {
                    // Logic to detect if a numeric string should be forced as String (e.g. NIM, NIP, Phone)
                    // Forced String if longer than 8 digits OR starts with 0
                    $strVal = (string)$val;
                    $forceString = (strlen($strVal) > 8 || preg_match('/^0/', $strVal));
                    $type = (is_numeric($val) && !$forceString) ? 'Number' : 'String';
                    
                    echo '    <Cell ss:StyleID="' . $rowStyle . '"><Data ss:Type="' . $type . '">' . htmlspecialchars($strVal) . '</Data></Cell>' . "\n";
                }
                echo '   </Row>' . "\n";
            }
        }
        
        echo '  </Table>' . "\n";
        echo ' </Worksheet>' . "\n";
    }
    echo '</Workbook>' . "\n";
    exit;
}

