<?php
session_start();

// ----- CONFIG -----
// Change if needed for your XAMPP/MySQL setup
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

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
            jam_pulang VARCHAR(20) NULL,
            jam_pulang_iso DATETIME NULL,
            ekspresi_pulang VARCHAR(50) NULL,
            status ENUM('ontime','terlambat') DEFAULT 'ontime',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(user_id),
            CONSTRAINT fk_att_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
    
    // Add status column if it doesn't exist (for existing databases)
    try {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN status ENUM('ontime','terlambat') DEFAULT 'ontime' AFTER ekspresi_pulang");
    } catch (PDOException $e) {
        // Column already exists, ignore error
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

$pdo = getPdo();
ensureSchema($pdo);
seedAdmin($pdo, $DEFAULT_ADMIN_EMAIL, $DEFAULT_ADMIN_PASSWORD);

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

// ----- AJAX ENDPOINTS -----
if (isset($_GET['ajax'])) {
    $action = $_GET['ajax'];

    // Must be authenticated for all endpoints except auth-related
    if (!in_array($action, ['login', 'register'], true)) {
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
            $params = [':nama' => $nama, ':prodi' => $prodi, ':startup' => $startup ?: null, ':id' => $id];
            $sql = "UPDATE users SET nama=:nama, prodi=:prodi, startup=:startup" . ($foto ? ", foto_base64=:foto" : "") . " WHERE id=:id";
            if ($foto) $params[':foto'] = $foto;
            $pdo->prepare($sql)->execute($params);
            jsonResponse(['ok' => true]);
        } else {
            // Create new
            if (!$nim || !$nama || !$prodi || !$foto) jsonResponse(['ok' => false, 'message' => 'Field wajib belum lengkap'], 400);
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
            jsonResponse(['ok' => true]);
        }
    }

    if ($action === 'delete_member' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM users WHERE id=:id AND role='pegawai'")->execute([':id' => $id]);
        jsonResponse(['ok' => true]);
    }

    if ($action === 'get_attendance') {
        // Admin: all; Pegawai: only their records
        if (isAdmin()) {
            $stmt = $pdo->query("SELECT a.*, u.nim, u.nama FROM attendance a JOIN users u ON u.id=a.user_id ORDER BY a.jam_masuk_iso DESC");
        } else {
            $uid = (int)$_SESSION['user']['id'];
            $stmt = $pdo->prepare("SELECT a.*, u.nim, u.nama FROM attendance a JOIN users u ON u.id=a.user_id WHERE a.user_id=:uid ORDER BY a.jam_masuk_iso DESC");
            $stmt->execute([':uid' => $uid]);
        }
        jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($action === 'save_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $nim = trim($_POST['nim'] ?? '');
        $mode = $_POST['mode'] ?? ''; // masuk/pulang
        $ekspresi = $_POST['ekspresi'] ?? null;
        if (!$nim || !in_array($mode, ['masuk', 'pulang'], true)) jsonResponse(['ok' => false, 'message' => 'Bad request'], 400);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE nim=:nim LIMIT 1");
        $stmt->execute([':nim' => $nim]);
        $u = $stmt->fetch();
        if (!$u) jsonResponse(['ok' => false, 'message' => 'NIM tidak ditemukan'], 404);
    
        $now = new DateTime('now', new DateTimeZone('Asia/Jakarta'));
        $jamSekarang = $now->format('H:i:s');
        $iso = $now->format('Y-m-d H:i:s');
        $today = $now->format('Y-m-d');
        $currentHour = (int)$now->format('H');
        $currentMinute = (int)$now->format('i');
        $todayStart = $today . ' 00:00:00';
        $todayEnd   = $today . ' 23:59:59';
    
        if ($mode === 'masuk') {
            // Check if within check-in time window (6 AM - 4 PM)
            if ($currentHour < 6 || $currentHour >= 16) {
                $statusText = "Presensi masuk hanya tersedia dari jam 06:00 sampai 16:00.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700'], 400);
            }
    
            // Check if already checked in today (any record for today, regardless of check-out status)
            $todayCheck = $pdo->prepare("
                SELECT * FROM attendance 
                WHERE user_id = :uid 
                AND jam_masuk_iso BETWEEN :start AND :end 
                ORDER BY jam_masuk_iso DESC 
                LIMIT 1
            ");
            $todayCheck->execute([
                ':uid' => $u['id'],
                ':start' => $todayStart,
                ':end' => $todayEnd
            ]);
            $todayRow = $todayCheck->fetch();
            
            if (!$todayRow) {
                // Calculate if late (after 8 AM)
                $isLate = false;
                $lateMessage = '';
                $status = 'ontime';
                
                if ($currentHour > 8 || ($currentHour === 8 && $currentMinute > 0)) {
                    $isLate = true;
                    $status = 'terlambat';
                    
                    // Calculate delay time
                    $deadline = new DateTime($today . ' 08:00:00', new DateTimeZone('Asia/Jakarta'));
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
                
                $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, ekspresi_masuk, status) VALUES (:uid, :jam, :iso, :exp, :status)");
                $ins->execute([':uid' => $u['id'], ':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':status' => $status]);
                
                if ($isLate) {
                    $statusText = "Selamat datang, {$u['nama']}! Anda terlihat {$ekspresi}. Jam masuk tercatat pukul {$jamSekarang}. Anda telat masuk{$lateMessage}";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamSekarang, 'statusClass' => 'bg-yellow-100 text-yellow-700']);
                } else {
                    $statusText = "Selamat datang, {$u['nama']}! Anda terlihat {$ekspresi}. Jam masuk tercatat pukul {$jamSekarang}. On time!";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamSekarang, 'statusClass' => 'bg-green-100 text-green-700']);
                }
            } else {
                $masukTime = new DateTime($todayRow['jam_masuk_iso']);
                $statusText = "Anda sudah presensi masuk pada " . $masukTime->format('d/m/Y H:i:s') . " dan belum pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700'], 400);
            }
        } else {
            // Check if within check-out time window (after 5 PM)
            if ($currentHour < 17) {
                $statusText = "Hei Anda dilarang Kabur, ini masih jam Kerja.. Wardani Mengawasi Anda";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700'], 400);
            }
    
            // Check if checked in today and not yet checked out
            $todayCheck = $pdo->prepare("SELECT * FROM attendance WHERE user_id=:uid AND DATE(jam_masuk_iso)=:today AND jam_pulang_iso IS NULL ORDER BY jam_masuk_iso DESC LIMIT 1");
            $todayCheck->execute([':uid' => $u['id'], ':today' => $today]);
            $todayRow = $todayCheck->fetch();
            
            if (!$todayRow) {
                $statusText = "Anda belum melakukan presensi masuk hari ini atau sudah pulang.";
                jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-yellow-100 text-yellow-700'], 400);
            } else {
                // Calculate work hours
                $masuk = new DateTime($todayRow['jam_masuk_iso']);
                $diffHours = ($now->getTimestamp() - $masuk->getTimestamp()) / 3600;
                
                if ($diffHours < 8) {
                    $statusText = "Hei Anda dilarang Kabur, ini masih jam Kerja.. Wardani Mengawasi Anda";
                    jsonResponse(['ok' => false, 'message' => $statusText, 'statusClass' => 'bg-red-100 text-red-700'], 400);
                } else {
                    $upd = $pdo->prepare("UPDATE attendance SET jam_pulang=:jam, jam_pulang_iso=:iso, ekspresi_pulang=:exp WHERE id=:id");
                    $upd->execute([':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':id' => $todayRow['id']]);
                    $statusText = "Selamat jalan, {$u['nama']}! Anda terlihat {$ekspresi}. Jam pulang tercatat pukul {$jamSekarang}.";
                    jsonResponse(['ok' => true, 'message' => $statusText, 'nama' => $u['nama'], 'jam' => $jamSekarang, 'statusClass' => 'bg-green-100 text-green-700']);
                }
            }
        }
    }

    if ($action === 'delete_attendance' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        $pdo->prepare("DELETE FROM attendance WHERE id=:id")->execute([':id' => $id]);
        jsonResponse(['ok' => true]);
    }

    jsonResponse(['error' => 'Unknown endpoint'], 404);
}

// ----- PAGE ROUTING -----
$page = $_GET['page'] ?? '';
if ($page === 'logout') {
    session_destroy();
    header('Location: ?page=login');
exit; 
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Aplikasi Presensi Wajah</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/face-api.js@0.22.2/dist/face-api.min.js"></script>
    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
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
    </style>
</head>
<body class="bg-gray-100 text-gray-800">

<?php if (!isset($_SESSION['user']) && (!in_array($page, ['register'], true))) { $page = 'login'; } ?>

<?php if ($page === 'login'): ?>
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
                    <img id="reg-foto-preview" class="mt-2 h-32 w-32 object-cover rounded-lg hidden">
                    <input type="hidden" name="foto" id="reg-foto-data">
                    <div class="flex gap-2">
                        <button type="button" id="reg-start-camera" class="flex-1 bg-indigo-500 hover:bg-indigo-600 text-white font-semibold py-2 rounded-lg">Buka Kamera</button>
                        <button type="button" id="reg-take-photo" class="flex-1 bg-green-500 hover:bg-green-600 text-white font-semibold py-2 rounded-lg hidden">Ambil Foto</button>
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
        </div>
    </div>
<?php else: ?>
    <header class="bg-white shadow-md">
        <div class="container mx-auto px-4 py-4 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-gray-700">Sistem Presensi Berbasis Wajah</h1>
            <div class="relative">
                <button id="btn-profile" class="flex items-center gap-3 px-3 py-2 bg-gray-100 hover:bg-gray-200 rounded-lg">
                    <span class="text-sm text-gray-700"><?php echo htmlspecialchars($_SESSION['user']['nama'] ?? ''); ?></span>
                    <img src="https://ui-avatars.com/api/?background=6366f1&color=fff&name=<?php echo urlencode($_SESSION['user']['nama'] ?? 'U'); ?>" class="w-8 h-8 rounded-full" alt="profile">
                </button>
                <div id="dropdown-profile" class="absolute right-0 mt-2 bg-white rounded-lg shadow-lg border hidden min-w-max">
                    <div class="px-4 py-2 text-sm text-gray-600 border-b whitespace-nowrap"><?php echo htmlspecialchars($_SESSION['user']['email'] ?? ''); ?></div>
                    <a href="?page=logout" class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50 whitespace-nowrap">Logout</a>
                </div>
            </div>
        </div>
    </header>

    <nav class="bg-indigo-600 text-white">
        <div class="container mx-auto px-4">
            <div class="flex items-center justify-center space-x-4">
                <?php if (!isAdmin()): ?>
                    <button data-tab="presensi" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Input Presensi</button>
                <?php endif; ?>
                <?php if (isAdmin()): ?>
                    <button data-tab="members" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Kelola Member</button>
                    <button data-tab="laporan" class="tab-link py-3 px-4 font-semibold hover:bg-indigo-700 focus:outline-none focus:bg-indigo-700 transition duration-300">Data Presensi</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <main class="container mx-auto p-4">
        <div id="page-presensi" class="">
            <div class="bg-white p-6 rounded-lg shadow-lg text-center">
                <h2 class="text-xl font-bold mb-4">Pilih Jenis Presensi</h2>
                <div id="scan-buttons-container" class="flex justify-center gap-4">
                    <button id="btn-scan-masuk" class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 text-lg">Presensi Masuk</button>
                    <button id="btn-scan-pulang" class="bg-red-500 hover:bg-red-600 text-white font-bold py-3 px-6 rounded-lg transition duration-300 text-lg">Presensi Pulang</button>
                </div>
                <div id="video-container" class="bg-gray-900 rounded-lg overflow-hidden aspect-video mt-4 hidden">
                    <video id="video" autoplay muted playsinline></video>
                    <canvas id="canvas"></canvas>
                    <div class="absolute top-3 left-3">
                        <button id="btn-back-scan" class="bg-white/90 hover:bg-white text-gray-800 font-semibold py-1.5 px-3 rounded-lg hidden">Kembali</button>
                    </div>
                </div>
                <div id="presensi-status" class="mt-4 text-center font-medium text-lg p-3 rounded-md hidden"></div>
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
                    <table class="min-w-full bg-white">
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
                <div class="grid md:grid-cols-4 gap-4 mb-4">
                    <input type="text" id="search-laporan" placeholder="Cari berdasarkan NIM atau Nama..." class="p-2 border rounded-lg">
                    <input type="date" id="filter-tanggal-mulai" class="p-2 border rounded-lg">
                    <input type="date" id="filter-tanggal-selesai" class="p-2 border rounded-lg">
                    <button id="btn-show-all" class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded-lg transition">Reset</button>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full bg-white">
                        <thead class="bg-gray-200">
                            <tr>
                                <th class="py-2 px-4">Tanggal</th>
                                <th class="py-2 px-4">NIM</th>
                                <th class="py-2 px-4">Nama</th>
                                <th class="py-2 px-4">Jam Masuk</th>
                                <th class="py-2 px-4">Ekspresi Masuk</th>
                                <th class="py-2 px-4">Status</th>
                                <th class="py-2 px-4">Jam Pulang</th>
                                <th class="py-2 px-4">Ekspresi Pulang</th>
                                <th class="py-2 px-4">Aksi</th>
                            </tr>
                        </thead>
                        <tbody id="table-laporan-body"></tbody>
                    </table>
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
                    <button type="button" id="btn-start-camera" class="w-full bg-indigo-500 hover:bg-indigo-600 text-white font-bold py-2 px-4 rounded-lg mb-2 transition">Buka Kamera untuk Foto</button>
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

    <!-- Confirm Modal -->
    <div id="confirm-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white p-8 rounded-lg shadow-2xl w-full max-w-sm text-center">
            <p id="confirm-modal-message" class="text-lg mb-6">Apakah Anda yakin?</p>
            <div class="flex justify-center space-x-4">
                <button id="btn-confirm-no" class="bg-gray-300 hover:bg-gray-400 text-gray-800 font-bold py-2 px-6 rounded-lg">Tidak</button>
                <button id="btn-confirm-yes" class="bg-red-500 hover:bg-red-600 text-white font-bold py-2 px-6 rounded-lg">Ya</button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Loading Overlay for model -->
<div id="loading-overlay" class="fixed inset-0 bg-black bg-opacity-75 flex flex-col items-center justify-center z-50 hidden">
    <div class="loader ease-linear rounded-full border-8 border-t-8 border-gray-200 h-24 w-24 mb-4"></div>
    <h2 class="text-center text-white text-xl font-semibold">Memuat Sistem Presensi...</h2>
    <p class="w-1/3 text-center text-white text-sm">Memuat model AI dan database wajah. Mohon tunggu sebentar.</p>
    <div class="mt-4 text-white text-xs opacity-75">
        <div id="loading-progress">Memulai...</div>
    </div>
</div>

<script>
function qs(sel){ return document.querySelector(sel); }
function qsa(sel){ return Array.from(document.querySelectorAll(sel)); }
function speak(text){ 
    try{ 
        // Cancel any ongoing speech first
        speechSynthesis.cancel();
        
        const u = new SpeechSynthesisUtterance(text); 
        u.lang='id-ID'; 
        u.rate=0.9; // Slightly slower for better clarity
        u.pitch=1.0;
        u.volume=1.0;
        
        // Add event listeners for better control
        u.onstart = () => console.log('Speech started:', text);
        u.onend = () => console.log('Speech ended:', text);
        u.onerror = (e) => console.error('Speech error:', e);
        
        speechSynthesis.speak(u);
    }catch(e){
        console.error('Speech synthesis error:', e);
    } 
}

async function api(url, data){
    const res = await fetch(url, { method: 'POST', body: data instanceof FormData ? data : new URLSearchParams(data) });
    const json = await res.json();
    return json;
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
qs('#form-login').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await api('?ajax=login', fd);
    const msg = qs('#login-msg');
    if(r.ok){
        msg.className = 'text-green-600';
        msg.textContent = 'Login berhasil. Mengalihkan...';
        setTimeout(()=> location.href='?', 600);
    } else {
        msg.className = 'text-red-600';
        msg.textContent = r.message || 'Gagal login';
    }
});
<?php elseif ($page === 'register'): ?>
// Register camera
const regStart = qs('#reg-start-camera');
const regTake = qs('#reg-take-photo');
const regVideo = qs('#reg-video');
const regCanvas = qs('#reg-canvas');
const regPreview = qs('#reg-foto-preview');
const regVidContainer = qs('#reg-video-container');
const regFotoData = qs('#reg-foto-data');
let regStream = null;

regStart.addEventListener('click', async ()=>{
    try{
        regStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } });
        regVideo.srcObject = regStream;
        regVidContainer.classList.remove('hidden');
        regTake.classList.remove('hidden');
        regStart.classList.add('hidden');
    }catch(err){ alert('Tidak bisa mengakses kamera'); console.error(err); }
});

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
});

qs('#form-register').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const fd = new FormData(e.target);
    const r = await api('?ajax=register', fd);
    const msg = qs('#register-msg');
    if(r.ok){ msg.className='text-green-600'; msg.textContent='Registrasi berhasil. Silakan login.'; setTimeout(()=>location.href='?page=login', 800); }
    else { msg.className='text-red-600'; msg.textContent=r.message||'Gagal registrasi'; }
});
<?php else: ?>
// App (logged in)
const pages = { presensi: qs('#page-presensi'), members: qs('#page-members'), laporan: qs('#page-laporan') };
qsa('.tab-link').forEach(btn=>{
    btn.addEventListener('click', ()=> showPage(btn.dataset.tab));
});
function showPage(name){ Object.values(pages).forEach(p=> p && (p.style.display='none')); if(pages[name]) pages[name].style.display='block'; if(name==='members') renderMembers(); if(name==='laporan') renderLaporan(); if(name==='presensi') resetPresensiPage(); }

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

// Ensure initial page sets after variables exist
<?php if (isAdmin()): ?>
showPage('members');
<?php else: ?>
showPage('presensi');
<?php endif; ?>

function statusMessage(text, cls){ 
    presensiStatus.textContent = text; 
    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md '+cls; 
    presensiStatus.classList.remove('hidden'); 
    
    if(text!==lastSpokenMessage){ 
        speak(text); 
        lastSpokenMessage=text; 
        
        // Stop scanning temporarily to let speech finish
        if(videoInterval) {
            clearInterval(videoInterval);
            videoInterval = null;
        }
        
        // Calculate speech duration based on text length (more accurate)
        // Indonesian speech rate: approximately 150-200 words per minute
        // Average word length: 5-6 characters
        const wordCount = text.split(' ').length;
        const estimatedSpeechDuration = Math.max(5000, wordCount * 350); // 350ms per word, minimum 5 seconds
        
        console.log(`Text: "${text}" | Words: ${wordCount} | Estimated duration: ${estimatedSpeechDuration}ms`);
        
        // Store the timeout ID for potential cancellation
        window.speechTimeout = setTimeout(()=>{
            lastSpokenMessage='';
            if(isCameraActive && !videoInterval) {
                startVideoInterval();
            }
        }, estimatedSpeechDuration);
    } 
}

async function loadFaceApiModels(){
    const loadingProgress = qs('#loading-progress');
    loadingOverlay.classList.remove('hidden');
    
    // Use working CDN for model weights
    const MODEL_URL = 'https://justadudewhohacks.github.io/face-api.js/models';
    
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
        console.log('Face recognition models loaded successfully');
        
        // Small delay to show completion message
        await new Promise(resolve => setTimeout(resolve, 500));
        
    } catch (error) {
        console.error('Error loading face recognition models:', error);
        loadingProgress.textContent = 'Error memuat model AI. Coba refresh halaman.';
        
        // Show error message to user
        if (presensiStatus) {
            presensiStatus.textContent = 'Gagal memuat model AI. Silakan refresh halaman.';
            presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-red-100 text-red-700';
            presensiStatus.classList.remove('hidden');
        }
        
        // Hide loading after error
        setTimeout(() => {
            loadingOverlay.classList.add('hidden');
        }, 3000);
        
        throw error; // Re-throw to stop further execution
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
    const members = await fetchMembers();
    labeledFaceDescriptors = [];
    
    // Process members in batches for better performance
    const batchSize = 3;
    for (let i = 0; i < members.length; i += batchSize) {
        const batch = members.slice(i, i + batchSize);
        
        const batchPromises = batch.map(async (m) => {
            if (!m.foto_base64) return null;
            
            try {
                const img = await faceapi.fetchImage(m.foto_base64);
                const det = await faceapi.detectSingleFace(img, new faceapi.TinyFaceDetectorOptions({
                    inputSize: 320,
                    scoreThreshold: 0.5
                })).withFaceLandmarks().withFaceDescriptor();
                
                if (det) {
                    return new faceapi.LabeledFaceDescriptors(m.nim, [det.descriptor]);
                }
            } catch (err) {
                console.warn('Deteksi gagal untuk', m.nama, err);
            }
            return null;
        });
        
        const batchResults = await Promise.all(batchPromises);
        labeledFaceDescriptors.push(...batchResults.filter(Boolean));
        
        // Small delay between batches to prevent blocking
        if (i + batchSize < members.length) {
            await new Promise(resolve => setTimeout(resolve, 50));
        }
    }
    
    console.log(`Loaded ${labeledFaceDescriptors.length} face descriptors`);
}

function startScan(mode){
    scanMode = mode;
    scanButtonsContainer.classList.add('hidden');
    videoContainer.classList.remove('hidden');
    btnBackScan.classList.remove('hidden');
    startVideo();
}

btnScanMasuk && btnScanMasuk.addEventListener('click', ()=> startScan('masuk'));
btnScanPulang && btnScanPulang.addEventListener('click', ()=> startScan('pulang'));
btnBackScan && btnBackScan.addEventListener('click', ()=>{ resetPresensiPage(); });

function resetPresensiPage(){
    stopVideo();
    scanButtonsContainer.classList.remove('hidden');
    videoContainer.classList.add('hidden');
    btnBackScan.classList.add('hidden');
    presensiStatus.classList.add('hidden');
    presensiStatus.textContent='';
    
    // Reset video play listener flag
    videoPlayListenerAdded = false;
    
    // Clear any pending timeouts
    if (window.presensiTimeout) {
        clearTimeout(window.presensiTimeout);
        window.presensiTimeout = null;
    }
    
    // Clear speech timeout
    if (window.speechTimeout) {
        clearTimeout(window.speechTimeout);
        window.speechTimeout = null;
    }
    
    // Stop any ongoing speech
    speechSynthesis.cancel();
}

function startVideo(){
    if(navigator.mediaDevices && navigator.mediaDevices.getUserMedia){
        // Optimize camera settings for better performance
        const constraints = {
            video: {
                width: { ideal: 640, max: 1280 },
                height: { ideal: 480, max: 720 },
                frameRate: { ideal: 15, max: 30 }, // Lower frame rate for better performance
                facingMode: 'user'
            }
        };
        
        navigator.mediaDevices.getUserMedia(constraints).then(stream => {
            video.srcObject = stream;
            isCameraActive = true;
            
            // Wait for video to be ready before starting detection
            video.addEventListener('loadedmetadata', () => {
                console.log('Camera started successfully');
            });
        }).catch(err => {
            console.error('Error camera', err);
            statusMessage('Error: Tidak dapat mengakses kamera.', 'bg-red-100 text-red-700');
        });
    }
}

function stopVideo(){
    if(video.srcObject){ video.srcObject.getTracks().forEach(t=>t.stop()); video.srcObject=null; }
    isCameraActive=false; if(videoInterval) clearInterval(videoInterval); speechSynthesis.cancel();
    if(canvas){ const ctx = canvas.getContext('2d'); ctx.clearRect(0,0,canvas.width,canvas.height); }
}

function startVideoInterval(){
    if(!isCameraActive || videoInterval) return;
    
    // Check if models are loaded
    if (!faceapi.nets.tinyFaceDetector.isLoaded) {
        console.error('Face detection models not loaded');
        statusMessage('Model AI belum dimuat. Silakan refresh halaman.', 'bg-red-100 text-red-700');
        return;
    }
    
    const displaySize = { width: video.clientWidth, height: video.clientHeight };
    faceapi.matchDimensions(canvas, displaySize);
    
    // Optimize interval for better performance
    videoInterval = setInterval(async ()=>{
        try {
            // Use more efficient detection options
            const detections = await faceapi.detectAllFaces(video, new faceapi.TinyFaceDetectorOptions({
                inputSize: 320, // Smaller input size for faster processing
                scoreThreshold: 0.5
            })).withFaceLandmarks().withFaceDescriptors().withFaceExpressions();
            
            const resized = faceapi.resizeResults(detections, displaySize);
            const ctx = canvas.getContext('2d');
            ctx.clearRect(0,0,canvas.width,canvas.height);
            
            if (resized.length > 0) {
                faceapi.draw.drawDetections(canvas, resized);
                
                if (labeledFaceDescriptors && labeledFaceDescriptors.length > 0) {
                    const faceMatcher = new faceapi.FaceMatcher(labeledFaceDescriptors, 0.6); // Slightly higher threshold
                    const results = resized.map(d => faceMatcher.findBestMatch(d.descriptor));
                    
                    results.forEach((result, i) => {
                        const box = resized[i].detection.box;
                        const expressions = resized[i].expressions || {};
                        const topExpression = getTopExpression(expressions);
                        const drawBox = new faceapi.draw.DrawBox(box, { 
                            label: `${result.toString()} (${topExpression})` 
                        });
                        drawBox.draw(canvas);
                        
                        if (result.label !== 'unknown') {
                            handleRecognition(result.label, topExpression);
                        }
                    });
                } else {
                    statusMessage('Database wajah kosong. Silakan tambah member.', 'bg-gray-200 text-gray-600');
                }
            } else {
                // Only update status if no faces detected
                if (presensiStatus.textContent !== 'Arahkan wajah ke kamera') {
                    presensiStatus.textContent = 'Arahkan wajah ke kamera';
                    presensiStatus.className = 'mt-4 text-center font-medium text-lg p-3 rounded-md bg-blue-100 text-blue-700';
                    presensiStatus.classList.remove('hidden');
                }
            }
        } catch (error) {
            console.error('Face detection error:', error);
            // Don't spam error messages
            if (presensiStatus.textContent !== 'Error deteksi wajah') {
                statusMessage('Error deteksi wajah. Coba refresh halaman.', 'bg-red-100 text-red-700');
            }
        }
    }, 1000); // Reduced from 1500ms to 1000ms for better responsiveness
}

// Optimize video event handling
video && video.addEventListener('play', ()=>{
    if (!videoPlayListenerAdded) {
        startVideoInterval();
        videoPlayListenerAdded = true;
    }
});

function getTopExpression(expressions){
    const map = { happy:'Senang', sad:'Sedih', neutral:'Biasa', angry:'Marah', disgusted:'Capek', surprised:'Ngantuk', fearful:'Laper' };
    let top='neutral', max=0; for(const [k,v] of Object.entries(expressions||{})){ if(v>max){ max=v; top=k; } }
    return map[top] || 'Biasa';
}

// Prevent multiple recognition calls
let isProcessingRecognition = false;

async function handleRecognition(nim, topExpression){
    if(!scanMode || isProcessingRecognition) return;
    
    isProcessingRecognition = true;
    
    try{
        const r = await api('?ajax=save_attendance', { nim, mode: scanMode, ekspresi: topExpression });
        if(r.ok){
            statusMessage(r.message, r.statusClass || 'bg-green-100 text-green-700');
            stopVideoAfterRecognition();
        } else {
            statusMessage(r.message || 'Gagal menyimpan presensi', r.statusClass || 'bg-yellow-100 text-yellow-700');
        }
    }catch(err){ 
        console.error(err); 
        statusMessage('Terjadi kesalahan server', 'bg-red-100 text-red-700'); 
    } finally {
        // Reset processing flag after a delay
        setTimeout(() => {
            isProcessingRecognition = false;
        }, 2000);
    }
}

function stopVideoAfterRecognition(){ 
    if(videoInterval) {
        clearInterval(videoInterval); 
        videoInterval = null;
    }
    
        // Calculate appropriate delay based on current status message
    let delayDuration = 10000; // Default 10 seconds
    
    if (presensiStatus && presensiStatus.textContent) {
        const currentText = presensiStatus.textContent;
        const wordCount = currentText.split(' ').length;
        
        // More generous timing: 500ms per word + buffer time for natural pauses
        delayDuration = Math.max(10000, wordCount * 500 + 3000);
        
        console.log(`Current message: "${currentText}" | Words: ${wordCount} | Delay: ${delayDuration}ms`);
    }
    
    // Wait for speech to finish completely before returning to main page
    setTimeout(()=>{ 
        if(isCameraActive) resetPresensiPage(); 
    }, delayDuration);
}

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
            <td class="py-2 px-4"><img src="${m.foto_base64||''}" alt="Foto ${m.nama||''}" class="h-12 w-12 object-cover rounded-full"></td>
            <td class="py-2 px-4">${m.nim||''}</td>
            <td class="py-2 px-4">${m.nama||''}</td>
            <td class="py-2 px-4">${m.prodi||''}</td>
            <td class="py-2 px-4">${m.startup||'-'}</td>
            <td class="py-2 px-4">
                <button class="btn-edit-member bg-yellow-400 hover:bg-yellow-500 text-white font-bold py-1 px-2 rounded" data-id="${m.id}" data-json='${JSON.stringify(m).replace(/'/g,"&apos;")}' >Edit</button>
                <button class="btn-delete-member bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded ml-2" data-id="${m.id}">Hapus</button>
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
const fotoPreview = qs('#foto-preview');
const fotoDataUrlInput = qs('#foto-data-url');
let modalStream = null;

function resetModalCamera(){ stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); fotoPreview.classList.add('hidden'); fotoDataUrlInput.value=''; }
function stopModalCamera(){ if(modalStream){ modalStream.getTracks().forEach(t=>t.stop()); modalStream=null; } }

btnStartCamera && btnStartCamera.addEventListener('click', async ()=>{
    try{ modalStream = await navigator.mediaDevices.getUserMedia({ video: { width: 480, height: 360 } }); modalVideo.srcObject = modalStream; modalVideoContainer.classList.remove('hidden'); btnTakePhoto.classList.remove('hidden'); btnStartCamera.classList.add('hidden'); fotoPreview.classList.add('hidden'); }catch(err){ alert('Tidak bisa mengakses kamera.'); console.error(err); }
});

btnTakePhoto && btnTakePhoto.addEventListener('click', ()=>{
    const ctx = modalCanvas.getContext('2d'); modalCanvas.width = modalVideo.videoWidth; modalCanvas.height = modalVideo.videoHeight; ctx.drawImage(modalVideo,0,0,modalCanvas.width,modalCanvas.height);
    const dataUrl = modalCanvas.toDataURL('image/jpeg'); fotoPreview.src = dataUrl; fotoDataUrlInput.value = dataUrl; fotoPreview.classList.remove('hidden'); stopModalCamera(); modalVideoContainer.classList.add('hidden'); btnTakePhoto.classList.add('hidden'); btnStartCamera.classList.remove('hidden'); btnStartCamera.textContent='Ambil Ulang Foto';
});

btnAddMember && btnAddMember.addEventListener('click', ()=>{
    memberForm.reset(); qs('#modal-title').textContent='Tambah Member Baru'; qs('#member-id').value=''; qs('#nim').readOnly=false; resetModalCamera(); btnStartCamera.textContent='Buka Kamera untuk Foto'; memberModal.classList.remove('hidden'); qs('#password-admin-wrapper').classList.remove('hidden');
});

btnCancelModal && btnCancelModal.addEventListener('click', ()=>{ stopModalCamera(); memberModal.classList.add('hidden'); });

document.addEventListener('click', (e)=>{
    if(e.target.classList.contains('btn-edit-member')){
        const data = JSON.parse(e.target.getAttribute('data-json').replace(/&apos;/g, "'"));
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
    if(e.target.classList.contains('btn-delete-member')){
        const id = e.target.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus member ini?', async ()=>{
            await api('?ajax=delete_member', { id });
            renderMembers(); loadLabeledFaceDescriptors();
        });
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
    if(!id){ payload.password = qs('#password-new').value; const confirm = qs('#password-confirm').value; if(!payload.password || payload.password!==confirm){ alert('Password admin untuk member baru wajib dan harus cocok'); return; } }
    const r = await api('?ajax=save_member', payload);
    if(r.ok){ renderMembers(); loadLabeledFaceDescriptors(); stopModalCamera(); memberModal.classList.add('hidden'); } else { alert(r.message||'Gagal menyimpan'); }
});

// Laporan
async function renderLaporan(){
    const res = await fetch('?ajax=get_attendance'); const j = await res.json(); const list = (j.data||[]);
    const term = (qs('#search-laporan')?.value||'').toLowerCase();
    const tglMulai = qs('#filter-tanggal-mulai')?.value || '';
    const tglSelesai = qs('#filter-tanggal-selesai')?.value || '';
    const filtered = list.filter(a=>{
        const nameMatch = (a.nama||'').toLowerCase().includes(term);
        const nimMatch = (a.nim||'').toLowerCase().includes(term);
        const recordDate = a.jam_masuk_iso ? a.jam_masuk_iso.slice(0,10) : '';
        const dateMatch = (!tglMulai || recordDate>=tglMulai) && (!tglSelesai || recordDate<=tglSelesai);
        return (nameMatch||nimMatch) && dateMatch;
    }).sort((a,b)=> new Date(b.jam_masuk_iso||0) - new Date(a.jam_masuk_iso||0));
    const body = qs('#table-laporan-body'); if(!body) return; body.innerHTML='';
    if(filtered.length===0){ body.innerHTML = `<tr><td colspan="9" class="text-center py-4">Tidak ada data kehadiran.</td></tr>`; return; }
    filtered.forEach(att=>{
        const d = new Date(att.jam_masuk_iso);
        const tanggal = isNaN(d.getTime()) ? '-' : d.toLocaleDateString('id-ID', { year:'numeric', month:'long', day:'numeric'});
        const statusClass = att.status === 'terlambat' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700';
        const statusText = att.status === 'terlambat' ? 'Terlambat' : 'On Time';
        const tr = document.createElement('tr'); tr.className='border-b hover:bg-gray-50';
        tr.innerHTML = `
            <td class="py-2 px-4">${tanggal}</td>
            <td class="py-2 px-4">${att.nim||''}</td>
            <td class="py-2 px-4">${att.nama||''}</td>
            <td class="py-2 px-4">${att.jam_masuk||''}</td>
            <td class="py-2 px-4">${att.ekspresi_masuk||'-'}</td>
            <td class="py-2 px-4"><span class="px-2 py-1 rounded-full text-xs font-medium ${statusClass}">${statusText}</span></td>
            <td class="py-2 px-4">${att.jam_pulang||'Belum Pulang'}</td>
            <td class="py-2 px-4">${att.ekspresi_pulang||'-'}</td>
            <td class="py-2 px-4"><button class="btn-delete-laporan bg-red-500 hover:bg-red-600 text-white font-bold py-1 px-2 rounded" data-id="${att.id}">Hapus</button></td>`;
        body.appendChild(tr);
    });
}

[qs('#search-laporan'), qs('#filter-tanggal-mulai'), qs('#filter-tanggal-selesai')].forEach(el=>{ if(el) el.addEventListener('input', renderLaporan); });

// Show all data button
qs('#btn-show-all') && qs('#btn-show-all').addEventListener('click', ()=>{
    if(qs('#search-laporan')) qs('#search-laporan').value = '';
    if(qs('#filter-tanggal-mulai')) qs('#filter-tanggal-mulai').value = '';
    if(qs('#filter-tanggal-selesai')) qs('#filter-tanggal-selesai').value = '';
    renderLaporan();
});

document.addEventListener('click', async (e)=>{
    if(e.target.classList.contains('btn-delete-laporan')){
        const id = e.target.getAttribute('data-id');
        showConfirmModal('Apakah Anda yakin ingin menghapus data kehadiran ini?', async ()=>{ await api('?ajax=delete_attendance', { id }); renderLaporan(); });
    }
});

// Confirm modal logic
let onConfirmCallback = null;
function showConfirmModal(message, cb){ const modal=qs('#confirm-modal'); qs('#confirm-modal-message').textContent=message; onConfirmCallback=cb; modal.classList.remove('hidden'); }
qs('#btn-confirm-yes') && qs('#btn-confirm-yes').addEventListener('click', ()=>{ if(typeof onConfirmCallback==='function') onConfirmCallback(); qs('#confirm-modal').classList.add('hidden'); onConfirmCallback=null; });
qs('#btn-confirm-no') && qs('#btn-confirm-no').addEventListener('click', ()=>{ qs('#confirm-modal').classList.add('hidden'); onConfirmCallback=null; });

// Init models and descriptors on first load - only for pegawai
(async function init(){
    <?php if (isAdmin()): ?>
    // Admin doesn't need face recognition - skip loading
    console.log('Admin user - skipping face recognition models');
    <?php else: ?>
    // Show loading for pegawai users
    loadingOverlay.classList.remove('hidden');
    try {
        await loadFaceApiModels();
        await loadLabeledFaceDescriptors();
    } catch (error) {
        console.error('Error loading face recognition:', error);
    } finally {
        loadingOverlay.classList.add('hidden');
    }
    <?php endif; ?>
})();
<?php endif; ?>
</script>
</body>
</html>
