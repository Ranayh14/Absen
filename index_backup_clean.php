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

// Include database backup functions
require_once 'database_backup.php';

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

    // Add missing columns if they don't exist (for existing databases)
    $requiredColumns = [
        'ekspresi_masuk' => "ALTER TABLE attendance ADD COLUMN ekspresi_masuk VARCHAR(50) NULL AFTER jam_masuk_iso",
        'ekspresi_pulang' => "ALTER TABLE attendance ADD COLUMN ekspresi_pulang VARCHAR(50) NULL AFTER jam_pulang_iso",
        'screenshot_masuk' => "ALTER TABLE attendance ADD COLUMN screenshot_masuk LONGTEXT NULL AFTER ekspresi_masuk",
        'screenshot_pulang' => "ALTER TABLE attendance ADD COLUMN screenshot_pulang LONGTEXT NULL AFTER ekspresi_pulang",
        'status' => "ALTER TABLE attendance ADD COLUMN status ENUM('ontime','terlambat') DEFAULT 'ontime' AFTER ekspresi_pulang",
        'ket' => "ALTER TABLE attendance ADD COLUMN ket ENUM('wfo','izin','sakit','alpha','wfa') DEFAULT 'wfo' AFTER status",
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

    foreach ($requiredColumns as $column => $sql) {
        try {
            $pdo->exec($sql);
        } catch (PDOException $e) {
            // Column already exists, ignore error
        }
    }

    // Update ket column enum to use WFO/WFA
    try {
        $pdo->exec("ALTER TABLE attendance MODIFY ket ENUM('wfo', 'izin', 'sakit', 'alpha', 'wfa') DEFAULT 'wfo'");
    } catch (PDOException $e) {
        // Ignore error if column doesn't exist or enum is already correct
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
            status ENUM('draft','submitted','approved','disapproved') DEFAULT 'draft',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uniq_user_month (user_id, year, month),
            CONSTRAINT fk_mr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
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
        ['min_checkout_hour', '17', 'Jam minimal untuk bisa presensi pulang (format 24 jam)']
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
        $backupResult = backupDatabase();
        if (!$backupResult['success']) {
            error_log("Backup gagal: " . $backupResult['message']);
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
                    'jam_masuk' => '-',
                    'jam_masuk_iso' => $note['date'] . ' 00:00:00',
                    'ekspresi_masuk' => null,
                    'screenshot_masuk' => null,
                    'lokasi_masuk' => null,
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'jam_pulang' => '-',
                    'jam_pulang_iso' => null,
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
                    'jam_masuk' => '-',
                    'jam_masuk_iso' => $note['date'] . ' 00:00:00',
                    'ekspresi_masuk' => null,
                    'screenshot_masuk' => null,
                    'lokasi_masuk' => null,
                    'lat_masuk' => null,
                    'lng_masuk' => null,
                    'jam_pulang' => '-',
                    'jam_pulang_iso' => null,
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

    if ($action === 'save_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nim = trim($_POST['nim'] ?? '');
        $mode = $_POST['mode'] ?? ''; // masuk/pulang
        $ekspresi = $_POST['ekspresi'] ?? null;
        $screenshot = $_POST['screenshot'] ?? null; // base64 screenshot data

        // Check screenshot size (max 2MB) and validate screenshot exists
        if (!$screenshot || empty($screenshot)) {
            jsonResponse(['ok' => false, 'message' => 'Screenshot tidak berhasil diambil. Silakan coba lagi dengan posisi yang lebih baik.'], 400);
        }
        if ($screenshot) {
            $sizeCheck = checkImageSize($screenshot, 2);
            if (!$sizeCheck['valid']) {
                jsonResponse(['ok' => false, 'message' => $sizeCheck['message']], 400);
            }
        }

        // Debug logging
        error_log("Attendance request: NIM=$nim, Mode=$mode, Expression=$ekspresi, Screenshot=" . ($screenshot ? 'YES' : 'NO'));
        error_log("POST data: " . print_r($_POST, true));

        // Verify attendance table structure before proceeding
        if (!verifyAttendanceTable($pdo)) {
            error_log("Attendance table structure verification failed during save_attendance");
            jsonResponse(['ok' => false, 'message' => 'Database structure error. Please contact administrator.'], 500);
        }

        if (!$nim || !in_array($mode, ['masuk', 'pulang'], true)) {
            error_log("Validation failed: NIM=$nim, Mode=$mode");
            jsonResponse(['ok' => false, 'message' => 'Bad request: NIM atau mode tidak valid'], 400);
        }
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE nim=:nim LIMIT 1");
            $stmt->execute([':nim' => $nim]);
            $u = $stmt->fetch();
            if (!$u) {
                error_log("User not found for NIM: $nim");
                jsonResponse(['ok' => false, 'message' => 'NIM tidak ditemukan'], 404);
            }
        } catch (PDOException $e) {
            error_log("Database error in save_attendance: " . $e->getMessage());
            jsonResponse(['ok' => false, 'message' => 'Database error'], 500);
        }

        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $jamSekarang = $now->format('H:i:s'); // Tetap simpan dengan detik untuk database
        $iso = $now->format('Y-m-d H:i:s');
        $today = $now->format('Y-m-d');

        // Debug logging after variables are defined
        error_log("Current date: $today, User ID: " . $u['id']);
        error_log("User data: " . print_r($u, true));
        error_log("Mode: $mode, Expression: $ekspresi");
        error_log("Screenshot size: " . strlen($screenshot));
        error_log("Screenshot preview: " . substr($screenshot, 0, 100) . "...");
        error_log("Screenshot starts with: " . substr($screenshot, 0, 20));
        error_log("Screenshot ends with: " . substr($screenshot, -20));
        error_log("Screenshot contains data:image: " . (strpos($screenshot, 'data:image') !== false ? 'YES' : 'NO'));
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

            // Check if already checked in today and hasn't checked out yet
            $todayCheck = $pdo->prepare("
                SELECT * FROM attendance
                WHERE user_id = :uid
                AND DATE(jam_masuk_iso) = :today
                AND jam_masuk_iso IS NOT NULL
                AND jam_pulang_iso IS NULL
                ORDER BY jam_masuk_iso DESC
                LIMIT 1
            ");
            $todayCheck->execute([
                ':uid' => $u['id'],
                ':today' => $today
            ]);
            $todayRow = $todayCheck->fetch();

            // Debug logging for attendance check
            if ($todayRow) {
                error_log("Found existing attendance record: ID=" . $todayRow['id'] . ", jam_masuk_iso=" . $todayRow['jam_masuk_iso'] . ", jam_pulang_iso=" . $todayRow['jam_pulang_iso']);
            } else {
                error_log("No existing attendance record found for user " . $u['id'] . " on date " . $today);
            }

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

                // Telkom University main campus simple geofence (circle)
                $teluLat = -6.9738; // approx Telkom University Bandung
                $teluLng = 107.6300;
                $radiusMeters = 1200; // ~1.2km radius
                $isInsideTelu = false;
                if ($lat !== null && $lng !== null) {
                    // Haversine formula for distance
                    $earth = 6371000; // meters
                    $dLat = deg2rad($teluLat - $lat);
                    $dLng = deg2rad($teluLng - $lng);
                    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat)) * cos(deg2rad($teluLat)) * sin($dLng/2) * sin($dLng/2);
                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                    $distance = $earth * $c;
                    $isInsideTelu = ($distance <= $radiusMeters);
                }

                $ketVal = $isInsideTelu ? 'wfo' : 'wfa';
                if (!$isInsideTelu) {
                    $alasanWfa = $_POST['alasan_wfa'] ?? null;
                    if (!$alasanWfa) {
                        jsonResponse(['ok' => false, 'need_reason' => true, 'message' => 'Di luar wilayah kantor. Harap isi alasan kerja di luar (WFA).'], 400);
                    }
                }

                $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, screenshot_masuk, lokasi_masuk, lat_masuk, lng_masuk, status, ket, alasan_wfa) VALUES (:uid, :jam, :iso, :exp, :screenshot, :lokasi, :lat, :lng, :status, :ket, :alasan)");
                $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':status' => $status, ':ket' => $ketVal, ':alasan' => $alasanWfa]);

                // Trigger backup setelah presensi masuk
                triggerDatabaseBackup();

                if ($isLate) {
                    $jamMasukFormat = substr($jamSekarang, 0, 5); // Ambil hanya jam:menit
                    $firstName = getFirstName($u['nama']);
                    $statusText = "Selamat datang, {$firstName}! Anda terlihat {$ekspresi}. Jam masuk tercatat pukul {$jamMasukFormat}. Anda telat masuk{$lateMessage}";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
                } else {
                    $jamMasukFormat = substr($jamSekarang, 0, 5); // Ambil hanya jam:menit
                    $firstName = getFirstName($u['nama']);
                    $statusText = "Selamat datang, {$firstName}! Anda terlihat {$ekspresi}. Jam masuk tercatat pukul {$jamMasukFormat}. On time!";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamMasukFormat, 'statusClass' => 'bg-green-100 text-green-700']);
                }
            } else {
                $masukTime = new DateTime($todayRow['jam_masuk_iso']);
                $statusText = "Anda sudah presensi masuk pada " . $masukTime->format('d/m/Y H:i') . " dan belum pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
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
            $pdo->prepare("DELETE FROM attendance_notes WHERE id=:id")->execute([':id' => $actualId]);
        } else {
            // Regular attendance record
            $actualId = (int)$id;
            $pdo->prepare("DELETE FROM attendance WHERE id=:id")->execute([':id' => $actualId]);
        }

        // Trigger backup setelah menghapus attendance/notes
        triggerDatabaseBackup();

        jsonResponse(['ok' => true]);
    }

    // Update bukti izin/sakit
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
        if(!in_array($type, ['izin','sakit','wfa'], true)) jsonResponse(['ok'=>false,'message'=>'Tipe tidak valid'],400);

        // Logic for setting time based on type
        $jam_masuk_iso = null;
        $jam_pulang_iso = null;
        $status = 'ontime';

        if ($type === 'wfa') {
            if (!$jam_masuk || !$jam_pulang) {
                jsonResponse(['ok' => false, 'message' => 'Jam masuk dan pulang wajib diisi untuk WFA'], 400);
            }
            $jam_masuk_iso = $date . ' ' . $jam_masuk . ':00';
            $jam_pulang_iso = $date . ' ' . $jam_pulang . ':00';
        } else {
            // For Izin/Sakit, use a default timestamp
            $jam_masuk_iso = $date . ' 08:00:00';
        }

        // Avoid duplicates for day
        $check = $pdo->prepare("SELECT id FROM attendance WHERE user_id=:u AND DATE(jam_masuk_iso)=:d");
        $check->execute([':u' => $user_id, ':d' => $date]);
        if($check->fetch()) jsonResponse(['ok' => false, 'message' => 'Data hari tersebut sudah ada'], 400);

        $alasan_izin_sakit = $_POST['alasan_izin_sakit'] ?? null;
        $bukti_izin_sakit = $_POST['bukti_izin_sakit'] ?? null;

        $sql = "INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, jam_pulang, jam_pulang_iso, status, ket, alasan_izin_sakit, bukti_izin_sakit) VALUES (:u, :jm, :jmiso, :jp, :jpiso, :s, :ket, :alasan, :bukti)";
        $ins = $pdo->prepare($sql);
        $ins->execute([
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

        // Trigger backup setelah menambah data absence
        triggerDatabaseBackup();

        jsonResponse(['ok' => true]);
    }

    // Admin: update attendance row
    if ($action === 'admin_update_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        if(!$id) jsonResponse(['ok'=>false,'message'=>'ID tidak valid'],400);
        $fields = ['jam_masuk','jam_pulang','ekspresi_masuk','ekspresi_pulang','status','ket','screenshot_masuk','screenshot_pulang','alasan_wfa','alasan_izin_sakit','bukti_izin_sakit'];
        $set=[]; $params=[':id'=>$id];
        foreach($fields as $f){ if(isset($_POST[$f])){ $set[] = "$f = :$f"; $params[":$f"] = $_POST[$f]!==''? $_POST[$f] : null; } }
        if(isset($_POST['jam_masuk_iso'])){ $set[]='jam_masuk_iso=:jmiso'; $params[':jmiso']= $_POST['jam_masuk_iso'] ?: null; }
        if(isset($_POST['jam_pulang_iso'])){ $set[]='jam_pulang_iso=:jpiso'; $params[':jpiso']= $_POST['jam_pulang_iso'] ?: null; }
        if(!$set) jsonResponse(['ok'=>false,'message'=>'Tidak ada perubahan'],400);
        $sql="UPDATE attendance SET ".implode(',', $set)." WHERE id=:id";
        $pdo->prepare($sql)->execute($params);

        // Trigger backup setelah update attendance
        triggerDatabaseBackup();

        jsonResponse(['ok'=>true]);
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

        if (!is_numeric($maxOntimeHour) || $maxOntimeHour < 0 || $maxOntimeHour > 23) {
            jsonResponse(['ok' => false, 'message' => 'Jam maksimal ontime harus berupa angka 0-23'], 400);
        }
        if (!is_numeric($minCheckoutHour) || $minCheckoutHour < 0 || $minCheckoutHour > 23) {
            jsonResponse(['ok' => false, 'message' => 'Jam minimal checkout harus berupa angka 0-23'], 400);
        }

        setSetting($pdo, 'max_ontime_hour', $maxOntimeHour);
        setSetting($pdo, 'min_checkout_hour', $minCheckoutHour);

        // Trigger backup setelah update settings
        triggerDatabaseBackup();

        jsonResponse(['ok' => true, 'message' => 'Settings berhasil disimpan']);
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

    // Dashboard endpoints
    if ($action === 'get_dashboard_data') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);

        $today = date('Y-m-d');
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

        // Get attendance trend for 1 year (August 2025 - August 2026)
        $trendData = [];
        $startDate = '2025-08-01';
        $endDate = '2026-08-31';

        $currentDate = new DateTime($startDate);
        $endDateTime = new DateTime($endDate);

        while ($currentDate <= $endDateTime) {
            $year = $currentDate->format('Y');
            $month = $currentDate->format('m');
            $monthName = $currentDate->format('M Y');

            // Get monthly statistics
            $presentStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as present
                FROM attendance
                WHERE YEAR(jam_masuk_iso) = :year
                AND MONTH(jam_masuk_iso) = :month
                AND (ket = 'wfo' OR ket = 'wfa')
            ");
            $presentStmt->execute([':year' => $year, ':month' => $month]);
            $present = $presentStmt->fetch()['present'];

            $lateStmt = $pdo->prepare("
                SELECT COUNT(DISTINCT user_id) as late
                FROM attendance
                WHERE YEAR(jam_masuk_iso) = :year
                AND MONTH(jam_masuk_iso) = :month
                AND status = 'terlambat'
            ");
            $lateStmt->execute([':year' => $year, ':month' => $month]);
            $late = $lateStmt->fetch()['late'];

            $trendData[] = [
                'date' => $currentDate->format('Y-m'),
                'day' => $monthName,
                'present' => $present,
                'late' => $late,
                'absent' => $totalEmployees - $present
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
        $submit=(bool)($_POST['submit']??false);

        // Validate year and month
        if($year <= 0 || $month <= 0 || $month > 12) {
            jsonResponse(['ok'=>false,'message'=>'Tahun atau bulan tidak valid'],400);
        }

        $stmt=$pdo->prepare("SELECT * FROM monthly_reports WHERE user_id=:u AND year=:y AND month=:m");
        $stmt->execute([':u'=>$uid, ':y'=>$year, ':m'=>$month]);
        $row=$stmt->fetch();
        if($row && in_array($row['status'], ['approved','disapproved'], true)) jsonResponse(['ok'=>false,'message'=>'Sudah final, tidak bisa diedit'],400);
        $newStatus=$submit?'submitted':'draft';
        if($row){
            $upd=$pdo->prepare("UPDATE monthly_reports SET summary=:s, achievements=:a, obstacles=:o, status=:st, updated_at=NOW() WHERE id=:id");
            $upd->execute([':s'=>$summary, ':a'=>$achievements, ':o'=>$obstacles, ':st'=>$newStatus, ':id'=>$row['id']]);

            // Trigger backup setelah update monthly report
            triggerDatabaseBackup();

            jsonResponse(['ok'=>true,'id'=>$row['id']]);
        }else{
            $ins=$pdo->prepare("INSERT INTO monthly_reports (user_id, year, month, summary, achievements, obstacles, status) VALUES (:u,:y,:m,:s,:a,:o,:st)");
            $ins->execute([':u'=>$uid, ':y'=>$year, ':m'=>$month, ':s'=>$summary, ':a'=>$achievements, ':o'=>$obstacles, ':st'=>$newStatus]);

            // Trigger backup setelah insert monthly report
            triggerDatabaseBackup();

            jsonResponse(['ok'=>true,'id'=>$pdo->lastInsertId()]);
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
    <script src="assets/js/chart.min.js"></script>
    <script src="assets/js/performance-optimizer.js"></script>
    <script src="assets/js/recognition-optimizer.js"></script>
    <link rel="stylesheet" href="assets/css/inter.css">
    <link rel='stylesheet' href='assets/css/uicons-solid-rounded.css'>
    <link rel='stylesheet' href='assets/css/uicons-solid-straight.css'>
    <link rel="stylesheet" href="assets/css/responsive.css">
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
        #video, #canvas { position: absolute; top: 0; left: 0; width: 100%; height: auto; }
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

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .container {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .btn-attendance {
                width: 100%;
                padding: 1rem;
                font-size: 1rem;
                margin-bottom: 0.5rem;
            }

            #video-container {
                aspect-ratio: 4/3;
                max-width: 100%;
            }

            .mobile-video-container {
                aspect-ratio: 4/3;
            }

            .landing-panel {
                padding: 1rem;
            }

            .landing-panel h2 {
                font-size: 1.5rem;
            }

            .text-panel-middle {
                height: auto;
                min-height: 250px;
                margin-top: 2vh;
            }
        }

        /* Tablet optimizations */
        @media (min-width: 641px) and (max-width: 1024px) {
            .btn-attendance {
                width: 48%;
                display: inline-block;
                margin-right: 2%;
            }

            .btn-attendance:last-child {
                margin-right: 0;
            }
        }

        /* Desktop optimizations */
        @media (min-width: 1025px) {
            .btn-attendance {
                width: auto;
                min-width: 200px;
                margin-right: 1rem;
            }

            #video-container {
                max-width: 960px;
            }
        }

        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .btn-attendance {
                border-width: 1px;
            }
        }

        /* Touch device optimizations */
        @media (hover: none) and (pointer: coarse) {
            .btn-attendance {
                min-height: 44px;
                padding: 1rem;
            }

            .btn-attendance:active {
                transform: scale(0.95);
            }
        }

        /* Landscape mobile */
        @media (max-width: 768px) and (orientation: landscape) {
            .text-panel-middle {
                height: auto;
                min-height: 200px;
                margin-top: 1vh;
            }

            .full-height-image {
                height: calc(100vh - 50px);
            }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-slate-50 via-gray-50 to-slate-100 text-gray-800">

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
                <button id="btn-profile" class="flex items-center gap-3 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg transition-colors">
                    <span class="text-sm text-gray-700">Akun</span>
                    <img src="generate-avatar.php?background=64748b&color=ffffff&name=A&size=32" class="w-8 h-8 rounded-full" alt="profile">
                </button>
                <div id="dropdown-profile" class="absolute right-0 mt-2 bg-white rounded-lg shadow-lg border border-gray-200 hidden min-w-max">
                    <a href="?page=login" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">Login</a>
                    <a href="?page=register" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 whitespace-nowrap">Register</a>
                </div>
            </div>
        </div>
    </header>
    <main class="mx-auto p-4">
        <!-- Landing Page Content -->
        <div class="max-w-6xl mx-auto">
            <!-- Hero Section -->
            <div class="text-center mb-12">
                <h1 class="text-4xl md:text-5xl font-bold text-gray-800 mb-6">
                    Sistem Presensi Berbasis Wajah
                </h1>
                <p class="text-xl text-gray-600 mb-8">
                    Teknologi face recognition untuk presensi yang akurat dan efisien
                </p>
            </div>

            <!-- Features Grid -->
            <div class="grid md:grid-cols-3 gap-8 mb-12">
                <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fi fi-sr-camera text-2xl text-blue-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Face Recognition</h3>
                    <p class="text-gray-600">Teknologi AI untuk mengenali wajah dengan akurasi tinggi</p>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fi fi-sr-clock text-2xl text-green-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Real-time</h3>
                    <p class="text-gray-600">Presensi langsung tanpa perlu kartu atau sidik jari</p>
                </div>
                
                <div class="bg-white rounded-lg shadow-lg p-6 text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fi fi-sr-shield-check text-2xl text-purple-600"></i>
                    </div>
                    <h3 class="text-xl font-semibold text-gray-800 mb-2">Aman & Terpercaya</h3>
                    <p class="text-gray-600">Data presensi tersimpan aman dengan enkripsi</p>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="text-center">
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <button id="btn-presensi-masuk" class="btn-attendance blue flex items-center justify-center gap-3 py-4 px-8 rounded-xl text-white font-semibold text-lg">
                        <i class="fi fi-sr-sign-in-alt text-2xl"></i>
                        <span>Presensi Masuk</span>
                    </button>
                    <button id="btn-presensi-pulang" class="btn-attendance red flex items-center justify-center gap-3 py-4 px-8 rounded-xl text-white font-semibold text-lg">
                        <i class="fi fi-sr-sign-out-alt text-2xl"></i>
                        <span>Presensi Pulang</span>
                    </button>
                </div>
            </div>

            <!-- Video Container (hidden by default) -->
            <div id="video-container" class="bg-gray-900 rounded-lg overflow-hidden aspect-video mt-8 max-w-4xl mx-auto hidden">
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
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border border-gray-300 px-4 py-2 text-left">Nama</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Startup</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Jam Masuk</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody id="log-masuk-tbody">
                            <!-- Data akan diisi via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Log Table untuk Presensi Pulang -->
            <div id="log-pulang-container" class="mt-6 hidden">
                <h3 class="text-lg font-semibold mb-3 text-center">Log Presensi Pulang Hari Ini</h3>
                <div class="overflow-x-auto">
                    <table class="w-full border-collapse border border-gray-300">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="border border-gray-300 px-4 py-2 text-left">Nama</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Startup</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Jam Pulang</th>
                                <th class="border border-gray-300 px-4 py-2 text-left">Lokasi</th>
                            </tr>
                        </thead>
                        <tbody id="log-pulang-tbody">
                            <!-- Data akan diisi via JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

<!-- Presensi Interface -->
<div id="page-presensi" class="hidden">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="container mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button id="btn-back-presensi" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fi fi-sr-arrow-left text-xl text-gray-600"></i>
                    </button>
                    <h1 class="text-xl font-bold text-gray-800">Presensi Wajah</h1>
                </div>
                <div class="flex items-center gap-2">
                    <span id="current-time" class="text-sm text-gray-600 font-medium"></span>
                    <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6">
            <!-- Status Display -->
            <div id="presensi-status" class="mb-6 text-center">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div id="status-icon" class="text-6xl mb-4">ðŸ“·</div>
                    <h2 id="status-title" class="text-2xl font-bold text-gray-800 mb-2">Siap Presensi</h2>
                    <p id="status-message" class="text-gray-600">Posisikan wajah Anda di depan kamera</p>
                </div>
            </div>

            <!-- Camera Container -->
            <div id="video-container" class="bg-gray-900 rounded-lg overflow-hidden aspect-video max-w-4xl mx-auto relative">
                <video id="video" autoplay muted playsinline class="w-full h-full object-cover"></video>
                <canvas id="canvas" class="absolute top-0 left-0 w-full h-full pointer-events-none"></canvas>

                <!-- Overlay Controls -->
                <div class="absolute top-4 left-4 flex gap-2">
                    <button id="btn-back-scan" class="bg-white/90 hover:bg-white text-gray-800 font-semibold py-2 px-4 rounded-lg transition-colors hidden">
                        <i class="fi fi-sr-arrow-left mr-2"></i>Kembali
                    </button>
                    <button id="btn-stop-detection" class="bg-red-500/90 hover:bg-red-600 text-white font-semibold py-2 px-4 rounded-lg transition-colors hidden">
                        <i class="fi fi-sr-stop mr-2"></i>Stop
                    </button>
                </div>

                <!-- Face Detection Overlay -->
                <div id="face-overlay" class="absolute inset-0 pointer-events-none">
                    <div id="face-box" class="absolute border-2 border-green-500 rounded-lg hidden">
                        <div class="absolute -top-8 left-0 bg-green-500 text-white px-2 py-1 rounded text-sm font-medium">
                            Wajah Terdeteksi
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-6 flex flex-col sm:flex-row gap-4 justify-center">
                <button id="btn-presensi-masuk" class="btn-attendance blue flex items-center justify-center gap-3 py-4 px-8 rounded-xl text-white font-semibold text-lg">
                    <i class="fi fi-sr-sign-in-alt text-2xl"></i>
                    <span>Presensi Masuk</span>
                </button>
                <button id="btn-presensi-pulang" class="btn-attendance red flex items-center justify-center gap-3 py-4 px-8 rounded-xl text-white font-semibold text-lg">
                    <i class="fi fi-sr-sign-out-alt text-2xl"></i>
                    <span>Presensi Pulang</span>
                </button>
            </div>

            <!-- Location Input (for WFA) -->
            <div id="location-input" class="mt-6 hidden">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Lokasi Kerja</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Alasan Kerja di Luar Kantor</label>
                            <textarea id="alasan-wfa" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" rows="3" placeholder="Jelaskan alasan kerja di luar kantor..."></textarea>
                        </div>
                        <button id="btn-submit-wfa" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                            Submit Presensi
                        </button>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Login Page -->
<div id="page-login" class="hidden">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-md">
            <div class="p-8">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fi fi-sr-user text-2xl text-blue-600"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Login</h1>
                    <p class="text-gray-600 mt-2">Masuk ke akun Anda</p>
                </div>

                <form id="form-login" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="login-email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan email Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="login-password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan password Anda" required>
                    </div>

                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                        Login
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">Belum punya akun? <a href="?page=register" class="text-blue-600 hover:text-blue-700 font-medium">Daftar di sini</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Register Page -->
<div id="page-register" class="hidden">
    <div class="min-h-screen bg-gradient-to-br from-blue-50 via-indigo-50 to-purple-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl shadow-xl border border-gray-200 w-full max-w-md">
            <div class="p-8">
                <div class="text-center mb-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i class="fi fi-sr-user-plus text-2xl text-green-600"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800">Daftar</h1>
                    <p class="text-gray-600 mt-2">Buat akun baru</p>
                </div>

                <form id="form-register" class="space-y-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Email</label>
                        <input type="email" id="register-email" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan email Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">NIM</label>
                        <input type="text" id="register-nim" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan NIM Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nama Lengkap</label>
                        <input type="text" id="register-nama" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan nama lengkap Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Prodi</label>
                        <input type="text" id="register-prodi" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan prodi Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Startup (Opsional)</label>
                        <input type="text" id="register-startup" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan startup Anda">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="register-password" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Masukkan password Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Konfirmasi Password</label>
                        <input type="password" id="register-password2" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Konfirmasi password Anda" required>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Foto Wajah</label>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <div id="photo-preview" class="hidden mb-4">
                                <img id="photo-preview-img" class="w-24 h-24 rounded-full mx-auto object-cover border-4 border-white shadow-lg">
                            </div>
                            <div id="photo-placeholder" class="text-gray-500">
                                <i class="fi fi-sr-camera text-3xl mb-2 block"></i>
                                <p class="text-sm">Klik untuk mengambil foto</p>
                            </div>
                            <input type="file" id="photo-input" accept="image/*" capture="user" class="hidden">
                        </div>
                    </div>

                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-3 px-6 rounded-lg transition-colors">
                        Daftar
                    </button>
                </form>

                <div class="mt-6 text-center">
                    <p class="text-gray-600">Sudah punya akun? <a href="?page=login" class="text-blue-600 hover:text-blue-700 font-medium">Login di sini</a></p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Admin Dashboard -->
<div id="page-admin" class="hidden">
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="container mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button id="btn-back-admin" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fi fi-sr-arrow-left text-xl text-gray-600"></i>
                    </button>
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Admin</h1>
                </div>
                <div class="flex items-center gap-4">
                    <span class="text-sm text-gray-600">Selamat datang, Admin</span>
                    <button id="btn-logout" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fi fi-sr-sign-out-alt mr-2"></i>Logout
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6">
            <!-- Dashboard Stats -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Total Pegawai</p>
                            <p id="total-employees" class="text-2xl font-bold text-gray-900">-</p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center">
                            <i class="fi fi-sr-users text-xl text-blue-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Hadir Hari Ini</p>
                            <p id="present-today" class="text-2xl font-bold text-gray-900">-</p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 rounded-full flex items-center justify-center">
                            <i class="fi fi-sr-check-circle text-xl text-green-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Terlambat Hari Ini</p>
                            <p id="late-today" class="text-2xl font-bold text-gray-900">-</p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <i class="fi fi-sr-clock text-xl text-yellow-600"></i>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600">Tidak Hadir</p>
                            <p id="absent-today" class="text-2xl font-bold text-gray-900">-</p>
                        </div>
                        <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center">
                            <i class="fi fi-sr-times-circle text-xl text-red-600"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button class="tab-button active py-4 px-1 border-b-2 border-blue-500 text-blue-600 font-medium" data-tab="dashboard">
                            <i class="fi fi-sr-dashboard mr-2"></i>Dashboard
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="attendance">
                            <i class="fi fi-sr-calendar mr-2"></i>Presensi
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="members">
                            <i class="fi fi-sr-users mr-2"></i>Anggota
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="settings">
                            <i class="fi fi-sr-settings mr-2"></i>Pengaturan
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Tab Content -->
            <div id="tab-dashboard" class="tab-content">
                <!-- Dashboard content will be loaded here -->
            </div>

            <div id="tab-attendance" class="tab-content hidden">
                <!-- Attendance content will be loaded here -->
            </div>

            <div id="tab-members" class="tab-content hidden">
                <!-- Members content will be loaded here -->
            </div>

            <div id="tab-settings" class="tab-content hidden">
                <!-- Settings content will be loaded here -->
            </div>
        </main>
    </div>
</div>

<!-- Employee Dashboard -->
<div id="page-employee" class="hidden">
    <div class="min-h-screen bg-gray-50">
        <!-- Header -->
        <header class="bg-white shadow-sm border-b border-gray-200">
            <div class="container mx-auto px-4 py-4 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <button id="btn-back-employee" class="p-2 hover:bg-gray-100 rounded-lg transition-colors">
                        <i class="fi fi-sr-arrow-left text-xl text-gray-600"></i>
                    </button>
                    <h1 class="text-xl font-bold text-gray-800">Dashboard Pegawai</h1>
                </div>
                <div class="flex items-center gap-4">
                    <span id="employee-name" class="text-sm text-gray-600">Selamat datang</span>
                    <button id="btn-logout-employee" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg transition-colors">
                        <i class="fi fi-sr-sign-out-alt mr-2"></i>Logout
                    </button>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="container mx-auto px-4 py-6">
            <!-- Employee Info -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 mb-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center">
                        <i class="fi fi-sr-user text-2xl text-blue-600"></i>
                    </div>
                    <div>
                        <h2 id="employee-full-name" class="text-xl font-bold text-gray-800">-</h2>
                        <p id="employee-info" class="text-gray-600">-</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Tabs -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
                <div class="border-b border-gray-200">
                    <nav class="flex space-x-8 px-6">
                        <button class="tab-button active py-4 px-1 border-b-2 border-blue-500 text-blue-600 font-medium" data-tab="employee-dashboard">
                            <i class="fi fi-sr-dashboard mr-2"></i>Dashboard
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="employee-attendance">
                            <i class="fi fi-sr-calendar mr-2"></i>Presensi
                        </button>
                        <button class="tab-button py-4 px-1 border-b-2 border-transparent text-gray-500 hover:text-gray-700 font-medium" data-tab="employee-reports">
                            <i class="fi fi-sr-file-text mr-2"></i>Laporan
                        </button>
                    </nav>
                </div>
            </div>

            <!-- Tab Content -->
            <div id="tab-employee-dashboard" class="tab-content">
                <!-- Employee dashboard content will be loaded here -->
            </div>

            <div id="tab-employee-attendance" class="tab-content hidden">
                <!-- Employee attendance content will be loaded here -->
            </div>

            <div id="tab-employee-reports" class="tab-content hidden">
                <!-- Employee reports content will be loaded here -->
            </div>
        </main>
    </div>
</div>

<script>
// Global variables
let isDetecting = false;
let currentMode = '';
let members = [];
let faceApiLoaded = false;

// Initialize the application
document.addEventListener('DOMContentLoaded', function() {
    console.log('Application started');

    // Initialize page routing
    initPageRouting();

    // Initialize event listeners
    initEventListeners();

    // Load Face API
    loadFaceAPI();

    // Update time display
    updateTime();
    setInterval(updateTime, 1000);

    // Initialize responsive optimizations
    initResponsiveOptimizations();

    // Initialize performance optimizations
    initPerformanceOptimizations();
});

// Page routing
function initPageRouting() {
    const urlParams = new URLSearchParams(window.location.search);
    const page = urlParams.get('page') || 'landing';

    // Hide all pages
    document.querySelectorAll('[id^="page-"]').forEach(page => {
        page.classList.add('hidden');
    });

    // Show current page
    const currentPage = document.getElementById(`page-${page}`);
    if (currentPage) {
        currentPage.classList.remove('hidden');
    }

    // Handle back buttons
    document.getElementById('btn-back-presensi')?.addEventListener('click', () => {
        window.location.href = '?page=landing';
    });

    document.getElementById('btn-back-admin')?.addEventListener('click', () => {
        window.location.href = '?page=landing';
    });

    document.getElementById('btn-back-employee')?.addEventListener('click', () => {
        window.location.href = '?page=landing';
    });
}

// Event listeners
function initEventListeners() {
    // Profile dropdown
    const btnProfile = document.getElementById('btn-profile');
    const dropdownProfile = document.getElementById('dropdown-profile');

    if (btnProfile && dropdownProfile) {
        btnProfile.addEventListener('click', (e) => {
            e.stopPropagation();
            dropdownProfile.classList.toggle('hidden');
        });

        document.addEventListener('click', () => {
            dropdownProfile.classList.add('hidden');
        });
    }

    // Presensi buttons with optimization
    document.getElementById('btn-presensi-masuk')?.addEventListener('click', () => {
        startPresensi('masuk');
    });

    document.getElementById('btn-presensi-pulang')?.addEventListener('click', () => {
        startPresensi('pulang');
    });

    // Optimized presensi submission
    document.getElementById('btn-presensi-masuk')?.addEventListener('click', async () => {
        await submitPresensiOptimized('masuk');
    });

    document.getElementById('btn-presensi-pulang')?.addEventListener('click', async () => {
        await submitPresensiOptimized('pulang');
    });

    // Login form
    document.getElementById('form-login')?.addEventListener('submit', handleLogin);

    // Register form
    document.getElementById('form-register')?.addEventListener('submit', handleRegister);

    // Photo input
    document.getElementById('photo-input')?.addEventListener('change', handlePhotoChange);

    // Logout buttons
    document.getElementById('btn-logout')?.addEventListener('click', handleLogout);
    document.getElementById('btn-logout-employee')?.addEventListener('click', handleLogout);

    // Tab navigation
    document.querySelectorAll('.tab-button').forEach(button => {
        button.addEventListener('click', () => {
            const tab = button.dataset.tab;
            switchTab(tab);
        });
    });
}

// Load Face API with optimization
async function loadFaceAPI() {
    try {
        console.log('Loading Face API with optimization...');

        // Use recognition optimizer for better performance
        if (window.recognitionOptimizer) {
            await window.recognitionOptimizer.init();
            faceApiLoaded = true;
            console.log('Face API loaded with optimization');

            // Load members for recognition
            await loadMembers();

            // Preload face descriptors for better performance
            if (members.length > 0) {
                await window.recognitionOptimizer.preloadFaceDescriptors(members);
            }
        } else {
            // Fallback to standard loading
            await Promise.all([
                faceapi.nets.tinyFaceDetector.loadFromUri('assets/js/models'),
                faceapi.nets.faceLandmark68Net.loadFromUri('assets/js/models'),
                faceapi.nets.faceRecognitionNet.loadFromUri('assets/js/models'),
                faceapi.nets.faceExpressionNet.loadFromUri('assets/js/models')
            ]);

            faceApiLoaded = true;
            console.log('Face API loaded successfully');

            // Load members for recognition
            loadMembers();
        }

    } catch (error) {
        console.error('Error loading Face API:', error);
        showStatus('error', 'Gagal memuat Face API', 'Silakan refresh halaman');
    }
}

// Load members
async function loadMembers() {
    try {
        const response = await fetch('?ajax=get_members');
        const data = await response.json();

        if (data.ok) {
            members = data.data;
            console.log('Members loaded:', members.length);
        }
    } catch (error) {
        console.error('Error loading members:', error);
    }
}

// Start presensi
async function startPresensi(mode) {
    if (!faceApiLoaded) {
        showStatus('error', 'Face API belum dimuat', 'Silakan tunggu sebentar');
        return;
    }

    currentMode = mode;

    try {
        // Request camera access
        const stream = await navigator.mediaDevices.getUserMedia({
            video: {
                width: { ideal: 1280 },
                height: { ideal: 720 },
                facingMode: 'user'
            }
        });

        const video = document.getElementById('video');
        video.srcObject = stream;

        // Show video container
        document.getElementById('video-container').classList.remove('hidden');
        document.getElementById('btn-stop-detection').classList.remove('hidden');

        // Start detection
        startFaceDetection();

        // Update status
        showStatus('info', 'Mendeteksi wajah...', 'Posisikan wajah Anda di depan kamera');

    } catch (error) {
        console.error('Error accessing camera:', error);
        showStatus('error', 'Gagal mengakses kamera', 'Pastikan izin kamera sudah diberikan');
    }
}

// Start face detection
function startFaceDetection() {
    if (isDetecting) return;

    isDetecting = true;
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');

    // Set canvas size
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;

    // Detection loop
    const detectFaces = async () => {
        if (!isDetecting) return;

        try {
            // Detect faces
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions())
                .withFaceLandmarks()
                .withFaceExpressions();

            // Clear canvas
            ctx.clearRect(0, 0, canvas.width, canvas.height);

            if (detections.length > 0) {
                // Draw face boxes
                detections.forEach(detection => {
                    const box = detection.detection.box;
                    ctx.strokeStyle = '#10b981';
                    ctx.lineWidth = 2;
                    ctx.strokeRect(box.x, box.y, box.width, box.height);

                    // Show face detected status
                    showStatus('success', 'Wajah terdeteksi!', 'Tekan tombol presensi untuk melanjutkan');
                });

                // If we have a good face detection, try recognition
                if (detections.length === 1) {
                    await tryFaceRecognition(detections[0]);
                }
            } else {
                showStatus('info', 'Mendeteksi wajah...', 'Posisikan wajah Anda di depan kamera');
            }

        } catch (error) {
            console.error('Detection error:', error);
        }

        // Continue detection
        if (isDetecting) {
            requestAnimationFrame(detectFaces);
        }
    };

    // Start detection
    detectFaces();
}

// Try face recognition with optimization
async function tryFaceRecognition(detection) {
    try {
        const video = document.getElementById('video');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Set canvas size
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw video frame to canvas
        ctx.drawImage(video, 0, 0);

        // Convert to base64
        const imageData = canvas.toDataURL('image/jpeg', 0.8);

        // Use optimized recognition if available
        let recognizedUser = null;
        if (window.recognitionOptimizer && window.recognitionOptimizer.isInitialized) {
            recognizedUser = await window.recognitionOptimizer.recognizeFaceOptimized(imageData);
        } else {
            recognizedUser = await recognizeFace(imageData);
        }

        if (recognizedUser) {
            // Show recognized user
            showStatus('success', `Wajah dikenali: ${recognizedUser.nama}`, 'Tekan tombol presensi untuk melanjutkan');

            // Enable presensi button
            const btnPresensi = currentMode === 'masuk' ?
                document.getElementById('btn-presensi-masuk') :
                document.getElementById('btn-presensi-pulang');

            btnPresensi.disabled = false;
            btnPresensi.classList.remove('opacity-50');

        } else {
            showStatus('warning', 'Wajah tidak dikenali', 'Pastikan wajah Anda terlihat jelas');
        }

    } catch (error) {
        console.error('Recognition error:', error);
    }
}

// Recognize face
async function recognizeFace(imageData) {
    try {
        // Convert image to tensor
        const img = await faceapi.bufferToImage(imageData);
        const tensor = faceapi.bufferToImage(img);

        // Get face descriptor
        const descriptor = await faceapi.computeFaceDescriptor(tensor);

        // Compare with stored faces
        let bestMatch = null;
        let bestDistance = 0.6; // Threshold

        for (const member of members) {
            if (member.foto_base64) {
                try {
                    // Load member photo
                    const memberImg = await faceapi.bufferToImage(member.foto_base64);
                    const memberDescriptor = await faceapi.computeFaceDescriptor(memberImg);

                    // Calculate distance
                    const distance = faceapi.euclideanDistance(descriptor, memberDescriptor);

                    if (distance < bestDistance) {
                        bestDistance = distance;
                        bestMatch = member;
                    }
                } catch (error) {
                    console.error('Error processing member photo:', error);
                }
            }
        }

        return bestMatch;

    } catch (error) {
        console.error('Face recognition error:', error);
        return null;
    }
}

// Show status
function showStatus(type, title, message) {
    const statusDiv = document.getElementById('presensi-status');
    const statusIcon = document.getElementById('status-icon');
    const statusTitle = document.getElementById('status-title');
    const statusMessage = document.getElementById('status-message');

    // Update content
    statusTitle.textContent = title;
    statusMessage.textContent = message;

    // Update styling based on type
    statusDiv.className = `mb-6 text-center ${getStatusClasses(type)}`;
    statusIcon.textContent = getStatusIcon(type);

    // Show status
    statusDiv.classList.remove('hidden');
}

// Get status classes
function getStatusClasses(type) {
    const classes = {
        'success': 'bg-green-50 border border-green-200 rounded-lg p-6',
        'error': 'bg-red-50 border border-red-200 rounded-lg p-6',
        'warning': 'bg-yellow-50 border border-yellow-200 rounded-lg p-6',
        'info': 'bg-blue-50 border border-blue-200 rounded-lg p-6'
    };
    return classes[type] || classes.info;
}

// Get status icon
function getStatusIcon(type) {
    const icons = {
        'success': 'âœ…',
        'error': 'âŒ',
        'warning': 'âš ï¸',
        'info': 'â„¹ï¸'
    };
    return icons[type] || icons.info;
}

// Update time display
function updateTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('id-ID', {
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });

    const timeElement = document.getElementById('current-time');
    if (timeElement) {
        timeElement.textContent = timeString;
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();

    const email = document.getElementById('login-email').value;
    const password = document.getElementById('login-password').value;

    try {
        const response = await fetch('?ajax=login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
        });

        const data = await response.json();

        if (data.ok) {
            if (data.role === 'admin') {
                window.location.href = '?page=admin';
            } else {
                window.location.href = '?page=employee';
            }
        } else {
            alert(data.message || 'Login gagal');
        }
    } catch (error) {
        console.error('Login error:', error);
        alert('Terjadi kesalahan saat login');
    }
}

// Handle register
async function handleRegister(e) {
    e.preventDefault();

    const email = document.getElementById('register-email').value;
    const nim = document.getElementById('register-nim').value;
    const nama = document.getElementById('register-nama').value;
    const prodi = document.getElementById('register-prodi').value;
    const startup = document.getElementById('register-startup').value;
    const password = document.getElementById('register-password').value;
    const password2 = document.getElementById('register-password2').value;

    if (password !== password2) {
        alert('Konfirmasi password tidak cocok');
        return;
    }

    if (!document.getElementById('photo-preview').classList.contains('hidden')) {
        alert('Foto wajah wajib diambil');
        return;
    }

    try {
        const response = await fetch('?ajax=register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `email=${encodeURIComponent(email)}&nim=${encodeURIComponent(nim)}&nama=${encodeURIComponent(nama)}&prodi=${encodeURIComponent(prodi)}&startup=${encodeURIComponent(startup)}&password=${encodeURIComponent(password)}&password2=${encodeURIComponent(password2)}&foto=${encodeURIComponent(document.getElementById('photo-preview-img').src)}`
        });

        const data = await response.json();

        if (data.ok) {
            alert('Registrasi berhasil! Silakan login.');
            window.location.href = '?page=login';
        } else {
            alert(data.message || 'Registrasi gagal');
        }
    } catch (error) {
        console.error('Register error:', error);
        alert('Terjadi kesalahan saat registrasi');
    }
}

// Handle photo change
function handlePhotoChange(e) {
    const file = e.target.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('photo-preview-img');
            img.src = e.target.result;
            document.getElementById('photo-preview').classList.remove('hidden');
            document.getElementById('photo-placeholder').classList.add('hidden');
        };
        reader.readAsDataURL(file);
    }
}

// Handle logout
async function handleLogout() {
    try {
        await fetch('?ajax=logout');
        window.location.href = '?page=landing';
    } catch (error) {
        console.error('Logout error:', error);
        window.location.href = '?page=landing';
    }
}

// Switch tab
function switchTab(tabName) {
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(content => {
        content.classList.add('hidden');
    });

    // Remove active class from all tab buttons
    document.querySelectorAll('.tab-button').forEach(button => {
        button.classList.remove('active', 'border-blue-500', 'text-blue-600');
        button.classList.add('border-transparent', 'text-gray-500');
    });

    // Show selected tab content
    const selectedContent = document.getElementById(`tab-${tabName}`);
    if (selectedContent) {
        selectedContent.classList.remove('hidden');
    }

    // Activate selected tab button
    const selectedButton = document.querySelector(`[data-tab="${tabName}"]`);
    if (selectedButton) {
        selectedButton.classList.add('active', 'border-blue-500', 'text-blue-600');
        selectedButton.classList.remove('border-transparent', 'text-gray-500');
    }
}

// Stop detection
document.getElementById('btn-stop-detection')?.addEventListener('click', () => {
    isDetecting = false;
    document.getElementById('video-container').classList.add('hidden');
    document.getElementById('btn-stop-detection').classList.add('hidden');
    showStatus('info', 'Presensi dihentikan', 'Klik tombol presensi untuk memulai kembali');
});

// Back to scan
document.getElementById('btn-back-scan')?.addEventListener('click', () => {
    isDetecting = false;
    document.getElementById('video-container').classList.add('hidden');
    document.getElementById('btn-back-scan').classList.add('hidden');
    showStatus('info', 'Siap Presensi', 'Posisikan wajah Anda di depan kamera');
});

// Optimized presensi submission
async function submitPresensiOptimized(mode) {
    try {
        if (!isDetecting) {
            showStatus('error', 'Kamera tidak aktif', 'Silakan mulai deteksi wajah terlebih dahulu');
            return;
        }

        const video = document.getElementById('video');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Set canvas size
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw video frame to canvas
        ctx.drawImage(video, 0, 0);

        // Convert to base64
        const imageData = canvas.toDataURL('image/jpeg', 0.8);

        // Show processing status
        showStatus('info', 'Memproses presensi...', 'Mohon tunggu sebentar');

        // Use optimized processing if available
        let result = null;
        if (window.recognitionOptimizer && window.recognitionOptimizer.isInitialized) {
            result = await window.recognitionOptimizer.processAttendanceOptimized(imageData, mode);
        } else {
            // Fallback to standard processing
            result = await processAttendanceStandard(imageData, mode);
        }

        if (result.success) {
            showStatus('success', 'Presensi berhasil!', result.data.message || 'Presensi telah dicatat');

            // Stop detection
            isDetecting = false;
            document.getElementById('video-container').classList.add('hidden');
            document.getElementById('btn-stop-detection').classList.add('hidden');

            // Show processing time if available
            if (result.processingTime) {
                console.log(`Presensi diproses dalam ${result.processingTime.toFixed(2)}ms`);
            }

        } else {
            showStatus('error', 'Presensi gagal', result.message || 'Terjadi kesalahan');
        }

    } catch (error) {
        console.error('Error in optimized presensi submission:', error);
        showStatus('error', 'Terjadi kesalahan', 'Silakan coba lagi');
    }
}

// Standard attendance processing (fallback)
async function processAttendanceStandard(imageData, mode) {
    try {
        // Get recognized user
        const recognizedUser = await recognizeFace(imageData);

        if (!recognizedUser) {
            return {
                success: false,
                message: 'Wajah tidak dikenali'
            };
        }

        // Capture screenshot
        const video = document.getElementById('video');
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        ctx.drawImage(video, 0, 0);
        const screenshot = canvas.toDataURL('image/jpeg', 0.8);

        // Get location
        const locationData = await getLocationData();

        // Prepare data
        const data = {
            nim: recognizedUser.nim,
            mode: mode,
            ekspresi: 'normal',
            screenshot: screenshot,
            lat: locationData.lat,
            lng: locationData.lng,
            lokasi: locationData.address
        };

        // Submit attendance
        const response = await fetch('?ajax=save_attendance', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(data)
        });

        const result = await response.json();

        return {
            success: result.ok,
            data: result,
            user: recognizedUser
        };

    } catch (error) {
        console.error('Standard attendance processing error:', error);
        return {
            success: false,
            message: 'Terjadi kesalahan saat memproses presensi'
        };
    }
}

// Get location data
async function getLocationData() {
    return new Promise((resolve) => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        address: 'Lokasi terdeteksi'
                    });
                },
                (error) => {
                    console.warn('Geolocation error:', error);
                    resolve({
                        lat: null,
                        lng: null,
                        address: 'Lokasi tidak tersedia'
                    });
                },
                { timeout: 3000, enableHighAccuracy: false }
            );
        } else {
            resolve({
                lat: null,
                lng: null,
                address: 'Lokasi tidak tersedia'
            });
        }
    });
}

// Initialize responsive optimizations
function initResponsiveOptimizations() {
    // Check if responsive optimizer is available
    if (window.responsiveOptimizer) {
        console.log('Responsive optimizer initialized');
    }

    // Setup mobile optimizations
    if (window.innerWidth < 768) {
        optimizeForMobile();
    }

    // Setup resize listener
    window.addEventListener('resize', debounce(() => {
        if (window.innerWidth < 768) {
            optimizeForMobile();
        } else {
            optimizeForDesktop();
        }
    }, 250));
}

// Initialize performance optimizations
function initPerformanceOptimizations() {
    // Check if performance optimizer is available
    if (window.performanceOptimizer) {
        console.log('Performance optimizer initialized');
    }

    // Check if recognition optimizer is available
    if (window.recognitionOptimizer) {
        console.log('Recognition optimizer initialized');
    }
}

// Optimize for mobile devices
function optimizeForMobile() {
    console.log('Optimizing for mobile devices');

    // Adjust video container for mobile
    const videoContainer = document.getElementById('video-container');
    if (videoContainer) {
        videoContainer.classList.add('mobile-video-container');
    }

    // Optimize buttons for mobile
    const buttons = document.querySelectorAll('.btn-attendance');
    buttons.forEach(button => {
        button.classList.add('mobile-button');
    });

    // Adjust detection settings for mobile
    if (window.recognitionOptimizer) {
        window.recognitionOptimizer.optimizeForMobile();
    }
}

// Optimize for desktop
function optimizeForDesktop() {
    console.log('Optimizing for desktop');

    // Remove mobile optimizations
    const videoContainer = document.getElementById('video-container');
    if (videoContainer) {
        videoContainer.classList.remove('mobile-video-container');
    }

    const buttons = document.querySelectorAll('.btn-attendance');
    buttons.forEach(button => {
        button.classList.remove('mobile-button');
    });

    // Adjust detection settings for desktop
    if (window.recognitionOptimizer) {
        window.recognitionOptimizer.optimizeForDesktop();
    }
}

// Debounce function for performance
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}
</script>

</body>
<?php endif; ?>