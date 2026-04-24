<?php
if (isset($_REQUEST['ajax'])) {
    $action = $_REQUEST['ajax'];

    // Check if database is available
    if (!isset($pdo)) {
        error_log("Database connection failed in AJAX handler");
        jsonResponse(['error' => 'Database connection failed'], 500);
    }

    // Must be authenticated for all endpoints except auth-related and public landing scan
    if (!in_array($action, ['login', 'register', 'get_members', 'save_attendance', 'get_today_attendance', 'forgot_password', 'verify_otp', 'reset_password', 'get_ga_qr', 'get_public_daily_report_stats', 'reverse_geocode', 'submit_help_request', 'search_address', 'get_clockin_location'], true)) {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
    }
    // Address Search
    if ($action === 'search_address') {
        $q = $_REQUEST['q'] ?? '';
        if (strlen($q) < 3) jsonResponse(['ok' => true, 'data' => []]);
        $results = searchAddressGoogle($q);
        jsonResponse(['ok' => true, 'data' => $results]);
    }


    // Admin manual holidays CRUD
    if ($action === 'admin_get_manual_holidays') {
        if (!isAdmin()) jsonResponse(['error'=>'Forbidden'],403);
        $start = $_GET['start'] ?? ($_POST['start'] ?? date('Y-01-01'));
        $end = $_GET['end'] ?? ($_POST['end'] ?? date('Y-12-31'));
        $rows = getManualHolidaysInRange($pdo, $start, $end);
        jsonResponse(['ok'=>true,'data'=>$rows]);
    }

    if ($action === 'get_clockin_location') {
        $nim = $_GET['nim'] ?? '';
        if (!$nim) jsonResponse(['ok' => false, 'message' => 'NIM required'], 400);
        
        $today = date('Y-m-d');
        $stmt = $pdo->prepare("SELECT lat_masuk, lng_masuk FROM attendance a JOIN users u ON u.id = a.user_id WHERE u.nim = :nim AND DATE(a.jam_masuk_iso) = :today AND a.jam_masuk IS NOT NULL LIMIT 1");
        $stmt->execute([':nim' => $nim, ':today' => $today]);
        $row = $stmt->fetch();
        
        if ($row) {
            jsonResponse(['ok' => true, 'lat' => (float)$row['lat_masuk'], 'lng' => (float)$row['lng_masuk']]);
        } else {
            jsonResponse(['ok' => false, 'message' => 'No clock-in found today']);
        }
    }

    if ($action === 'admin_bulk_fix_empty_checkout') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $date = $_POST['date'] ?? date('Y-m-d');
        
        // Get fallback jam_pulang from settings
        $stmt = $pdo->prepare("SELECT setting_value FROM settings WHERE setting_key = 'default_checkout_time'");
        $stmt->execute();
        $fallbackTime = $stmt->fetchColumn() ?: '17:00';
        
        $stmt = $pdo->prepare("UPDATE attendance SET jam_pulang = :time, jam_pulang_iso = :iso 
                              WHERE DATE(jam_masuk_iso) = :date AND jam_pulang IS NULL AND status_masuk != 'alpha'");
        // Wait, check status columns
        // Let's use simple query for now. Alpha is alpha.
        // Update ALL records with missing clock-outs
        // We use a CASE or CONCAT to ensure jam_pulang_iso matches the original date of attendance
        $stmt = $pdo->prepare("UPDATE attendance 
                              SET jam_pulang = :time, 
                                  jam_pulang_iso = CONCAT(DATE(jam_masuk_iso), ' ', :time2)
                              WHERE (jam_pulang IS NULL OR jam_pulang = '' OR jam_pulang = '-') 
                              AND ket IN ('wfo', 'wfa', 'overtime')");
        
        $res = $stmt->execute([':time' => $fallbackTime, ':time2' => $fallbackTime . ':00']);
        
        if ($res) {
            jsonResponse(['ok' => true, 'message' => 'Berhasil mengisi jam pulang kosong untuk tanggal ' . $date]);
        } else {
            jsonResponse(['ok' => false, 'message' => 'Gagal memperbarui data']);
        }
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

        if ($action === 'reverse_geocode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $lat = $_POST['lat'] ?? null;
            $lng = $_POST['lng'] ?? null;

            if (!$lat || !$lng) {
                jsonResponse(['ok' => false, 'message' => 'Coordinates required'], 400);
                return;
            }

            $address = reverseGeocodeAddress((float)$lat, (float)$lng);

            if ($address) {
                jsonResponse([
                    'ok' => true,
                    'data' => [
                        'display_name' => $address,
                        'address' => [
                            'full' => $address
                        ]
                    ]
                ]);
            } else {
                error_log("reverse_geocode failed for lat=$lat, lng=$lng");
                jsonResponse(['error' => 'Geocoding failed'], 500);
            }
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
        $stmt = $pdo->query("SELECT id, role, email, nim, nama, prodi, startup, foto_base64, face_embedding FROM users WHERE role='pegawai'");
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

                // -------------------------------------------------------------------------
                // ANTI-SPOOFING: Bounding Box Check (Indonesia Only)
                // Roughly: Lat -11 to 6 (South to North), Lng 95 to 141 (West to East)
                // -------------------------------------------------------------------------
                if ($lat < -11.0 || $lat > 6.0 || $lng < 95.0 || $lng > 141.0) {
                    error_log("Anti-Spoofing: Out of bounds coordinates detected ($lat, $lng)");
                    jsonResponse(['ok' => false, 'message' => 'Lokasi terdeteksi di luar negara Indonesia (kemungkinan Fake GPS/VPN). Mohon matikan aplikasi pemalsu lokasi dan coba lagi.'], 400);
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

                // -------------------------------------------------------------------------
                // ANTI-SPOOFING: IP Geolocation Check (Indonesia Only)
                // -------------------------------------------------------------------------
                $ipCountry = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? null;
                $isLocalhost = ($publicIp === '127.0.0.1' || $publicIp === '::1' || strpos($publicIp, '127.') === 0);
                
                if ($ipCountry !== null && strtoupper($ipCountry) !== 'ID') {
                    error_log("Anti-Spoofing: IP Country mismatch detected. CF_IPCOUNTRY: $ipCountry");
                    jsonResponse(['ok' => false, 'message' => 'Akses dari luar negeri dilarang. Harap matikan VPN/Proxy Anda.'], 400);
                } 
                
                // IP Geolocation API validation ALWAYS runs (if not localhost)
                if (!empty($publicIp) && filter_var($publicIp, FILTER_VALIDATE_IP) && !$isLocalhost) {
                    $isPrivateIp = !filter_var($publicIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
                    if (!isTelkomUniversityPrivateIp($publicIp) && !$isPrivateIp) {
                        try {
                            // Quick IP check using ip-api
                            $url = 'http://ip-api.com/json/' . urlencode($publicIp) . '?fields=status,countryCode,lat,lon';
                            $ch = curl_init($url);
                            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                            curl_setopt($ch, CURLOPT_TIMEOUT, 2); // 2 second timeout so we don't block
                            $resp = curl_exec($ch);
                            curl_close($ch);
                            
                            if ($resp) {
                                $ipData = json_decode($resp, true);
                                
                                // Extra fallback if CF header was missing
                                if ($ipCountry === null && isset($ipData['countryCode']) && strtoupper($ipData['countryCode']) !== 'ID') {
                                    error_log("Anti-Spoofing: API reported out of country IP: " . $ipData['countryCode']);
                                    jsonResponse(['ok' => false, 'message' => 'Alamat IP internet Anda terdeteksi dari luar negara Republik Indonesia. Harap matikan aplikasi VPN/Proxy Anda.'], 400);
                                }
                                
                                // IP-GPS Distance Check
                                if (isset($ipData['lat']) && isset($ipData['lon']) && $lat !== null && $lng !== null) {
                                    $ipLat = (float)$ipData['lat'];
                                    $ipLon = (float)$ipData['lon'];
                                    
                                    // Haversine formula
                                    $earthRadius = 6371; // km
                                    $dLat = deg2rad($ipLat - $lat);
                                    $dLon = deg2rad($ipLon - $lng);
                                    $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat)) * cos(deg2rad($ipLat)) * sin($dLon/2) * sin($dLon/2);
                                    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
                                    $distanceKm = $earthRadius * $c;
                                    
                                    error_log("Anti-Spoofing: IP-GPS Distance: " . round($distanceKm, 2) . " km");
                                    
                                    // 50km tolerance (tightened from 150km to catch nearby spoofing)
                                    if ($distanceKm > 50) {
                                        error_log("Anti-Spoofing: IP-GPS mismatch ($distanceKm km). IP: $publicIp ($ipLat, $ipLon), GPS: $lat, $lng");
                                        jsonResponse(['ok' => false, 'message' => 'Akurasi ditolak: Lokasi GPS terdeteksi terlalu jauh dari lokasi IP Internet Anda. Mohon matikan VPN / Proxy / Aplikasi Fake GPS.'], 400);
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // Silently ignore to avoid blocking legitimate users if API fails
                        }
                    }
                }
                
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
                if ($ketVal !== 'wfo' && $lat !== null && $lng !== null && isset($distance)) {
                    // Use GPS distance check - accept if within configured radius
                    if ($distance <= $wfoRadius) {
                        $ketVal = 'wfo';
                        error_log('✓ GPS inside radius - set WFO berdasarkan GPS (fallback terakhir, distance: ' . round($distance) . 'm, radius: ' . $wfoRadius . 'm)');
                    } else {
                        // GPS outside radius - require WFA
                        $ketVal = 'wfa';
                        error_log('✗ GPS outside radius - require WFA (distance: ' . round($distance) . 'm, radius: ' . $wfoRadius . 'm)');
                    }
                } else if ($ketVal !== 'wfo') {
                    // IP/WiFi invalid, GPS not available
                    $ketVal = 'wfa';
                    error_log('✗ Semua validasi gagal - require WFA (IP: ' . ($publicIp ?: 'EMPTY') . ', WiFi: ' . ($wifiSSID ?: 'EMPTY') . ', GPS: ' . (($lat && $lng) ? 'OK' : 'MISSING') . ')');
                }
                
                // Final check - jika bukan WFO, require alasan WFA
                if ($ketVal === 'wfa') {
                    $alasanWfa = $_POST['wfa_reason'] ?? $_POST['alasan_wfa'] ?? null;
                    if (!$alasanWfa) {
                        // Build helpful message with distance info for debugging
                        $debugInfo = '';
                        if (isset($distance)) {
                            $debugInfo = ' (Jarak GPS: ' . round($distance) . 'm, Radius WFO: ' . $wfoRadius . 'm)';
                        }
                        $wfaMsg = 'Di luar wilayah kantor atau tidak terhubung ke WiFi/IP Telkom University. Harap isi alasan kerja di luar (WFA).' . $debugInfo;
                        // FIX: Return HTTP 200 with need_reason flag so api() doesn't throw an exception
                        // Previously returned 400 which caused api() catch block to intercept it
                        jsonResponse(['ok' => false, 'need_reason' => true, 'message' => $wfaMsg]);
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
                $diffLocationReason = $_POST['diff_location_reason'] ?? null;
                
                $upd = $pdo->prepare("UPDATE attendance SET jam_pulang=:jam, jam_pulang_iso=:iso, ekspresi_pulang=:exp, screenshot_pulang=:screenshot, lokasi_pulang=:lokasi, lat_pulang=:lat, lng_pulang=:lng, alasan_pulang_awal=:alasan, alasan_lokasi_berbeda=:diff_loc WHERE id=:id");
                $upd->execute([':jam' => $jamSekarang, ':iso' => $iso, ':exp' => $ekspresi, ':screenshot' => $screenshot, ':lokasi' => $lokasi, ':lat' => $lat, ':lng' => $lng, ':alasan' => $alasanPulangAwal, ':diff_loc' => $diffLocationReason, ':id' => $todayRow['id']]);
                
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
        
        $type = $_GET['type'] ?? 'standard';
        $backupInfo = getBackupInfo($type);
        jsonResponse(['ok' => true, 'data' => $backupInfo]);
    }

    // Admin: create manual backup
    if ($action === 'create_backup' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        if (!function_exists('createDatabaseBackup')) {
            jsonResponse(['ok' => false, 'message' => 'Backup functions not available']);
        }
        
        $type = $_POST['type'] ?? 'standard';
        $result = createDatabaseBackup($type);
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
            if (!($result['ok'] ?? $result['success'] ?? false)) {
                // Double check if message indicates success but flag is wrong
                if (isset($result['message']) && strpos($result['message'], 'berhasil') !== false) {
                    // It actually succeeded but flags were missing/wrong
                } else {
                    http_response_code(500);
                    die('Failed to generate backup: ' . ($result['message'] ?? 'Unknown error'));
                }
            }
            
            $filePath = $result['file'];
            $downloadFileName = 'absen_db_backup_' . date('Y-m-d_His') . '.sql';
            
            // Clear output buffer to save memory
            while (ob_get_level()) ob_end_clean();
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output file
            readfile($filePath);
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
            if (!($result['ok'] ?? $result['success'] ?? false)) {
                if (isset($result['message']) && strpos($result['message'], 'berhasil') !== false) {
                } else {
                    http_response_code(404);
                    die('File not found and failed to generate backup: ' . ($result['message'] ?? 'Unknown error'));
                }
            }
            
            $filePath = $result['file'];
            $downloadFileName = basename($fileName);
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output file
            readfile($filePath);
            exit;
        }
        
        if (!file_exists($filePath)) {
            // File doesn't exist, generate from database
            if (!function_exists('createDatabaseBackupPHP')) {
                http_response_code(404);
                die('File not found');
            }
            
            $filePath = $result['file'];
            $downloadFileName = basename($fileName);
            
            // Set headers for file download
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $downloadFileName . '"');
            header('Content-Length: ' . filesize($filePath));
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            
            // Output file
            readfile($filePath);
            exit;
        }
        
        // Clear output buffer to save memory
        while (ob_get_level()) ob_end_clean();
        
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

    // Admin/Employee: get current user member data (Optimized for face verification)
    if ($action === 'get_current_user_descriptor') {
        $nim = $_SESSION['user']['nim'] ?? null;
        if (!$nim) jsonResponse(['error' => 'Not authenticated'], 401);
        $stmt = $pdo->prepare("SELECT nim, nama, foto_base64 FROM users WHERE nim = :nim LIMIT 1");
        $stmt->execute([':nim' => $nim]);
        $user = $stmt->fetch();
        if (!$user) jsonResponse(['error' => 'User not found'], 404);
        jsonResponse(['ok' => true, 'data' => $user]);
    }

    // Admin/Employee: get settings (Read-only access for UI rendering)
    if ($action === 'get_settings' && in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
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

    // Admin: get KPI data
    if ($action === 'get_kpi_data') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        try {
            $filterType = $_REQUEST['filter_type'] ?? 'period';
            $periodStart = null;
            $periodEnd = null;
            
            if ($filterType === 'monthly') {
                $month = (int)($_REQUEST['month'] ?? date('n'));
                $year = (int)($_REQUEST['year'] ?? date('Y'));
                $periodStart = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
                $periodEnd = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
            } else {
                $periodStart = $_REQUEST['period_start'] ?? null;
                $periodEnd = $_REQUEST['period_end'] ?? null;
            }
            
            $userId = isset($_REQUEST['user_id']) ? (int)$_REQUEST['user_id'] : null;
            
            if ($userId) {
                // Single employee KPI
                $kpiData = calculateKPIForEmployee($pdo, $userId, $periodStart, $periodEnd);
            } else {
                // All employees KPI (for dashboard)
                $kpiData = getAllKPIData($pdo, $periodStart, $periodEnd);
            }
            
            jsonResponse(['ok' => true, 'data' => $kpiData]);
        } catch (Exception $e) {
            jsonResponse(['ok' => false, 'message' => $e->getMessage()], 500);
        }
    }

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

    // --- Export Actions ---
    
    // Export KPI to Professional Excel
    if ($action === 'export_kpi') {
        if (!isAdmin()) die('Forbidden');
        
        $filterType = $_GET['filter_type'] ?? 'period';
        $periodStart = null;
        $periodEnd = null;
        
        if ($filterType === 'monthly') {
            $year = (int)($_GET['year'] ?? date('Y'));
            $month = (int)($_GET['month'] ?? date('n'));
            $periodStart = sprintf('%04d-%02d-01', $year, $month);
            $periodEnd = date('Y-m-t', strtotime($periodStart));
        }
        
        $data = getAllKPIData($pdo, $periodStart, $periodEnd);
        if (!$data) die('No data found');
        
        $rows = [];
        $no = 1;
        foreach ($data['kpi_data'] as $row) {
            $rows[] = [
                $no++,
                $row['nama'],
                $row['total_working_days'],
                $row['actual_working_days'],
                $row['ontime_count'],
                $row['wfa_count'] ?? 0,
                $row['late_count'],
                $row['total_late_minutes'],
                $row['izin_sakit_count'],
                $row['alpha_count'],
                $row['overtime_count'],
                $row['missing_daily_reports_count'] ?? 0,
                $row['kpi_score'],
                $row['status']
            ];
        }

        $sheets = [
            'KPI Summary' => [
                'title' => 'Penilaian KPI Absen - ' . ($filterType === 'monthly' ? "Bulan $month Tahun $year" : "Seluruh Periode"),
                'header' => ['No', 'Nama', 'Hari Kerja (T)', 'Hari Kerja (A)', 'Ontime', 'WFA', 'Terlambat', 'Menit Terlambat', 'Izin/Sakit', 'Alpha', 'Overtime', 'Laporan Kosong', 'Score', 'Status'],
                'rows' => $rows
            ]
        ];

        exportToExcelXML('export_kpi_' . date('Y-m-d'), $sheets);
    }

    // Export Daily Attendance to Professional Excel
    if ($action === 'export_daily') {
        if (!isAdmin()) die('Forbidden');
        
        // Final robustness measures for Phase 6
        ini_set('memory_limit', '512M'); 
        error_reporting(E_ALL);
        ini_set('display_errors', 0); // Keep off but logged/buffered
        if (ob_get_level()) ob_end_clean();
        ob_start();

        try {
            $filterType = $_GET['filter_type'] ?? 'period';
            $format = $_GET['format'] ?? ($_GET['export_format'] ?? 'combined');
            $year = (int)($_GET['year'] ?? date('Y'));
            
            // Handle multi-month selection (JS string vs Standard array)
            $monthsArr = [];
            if (isset($_GET['months']) && !empty($_GET['months'])) {
                $monthsArr = array_map('intval', explode(',', $_GET['months']));
            } elseif (isset($_GET['export_months']) && is_array($_GET['export_months'])) {
                $monthsArr = array_map('intval', $_GET['export_months']);
            }
            
            $sql = "SELECT u.nama, u.nim, u.startup, 
                           DATE(a.jam_masuk_iso) as tanggal,
                           a.jam_masuk, a.status as status_masuk,
                           a.jam_pulang, a.ket,
                           dr.content as laporan, dr.status as report_status
                    FROM users u
                    LEFT JOIN attendance a ON u.id = a.user_id
                    LEFT JOIN daily_reports dr ON u.id = dr.user_id AND DATE(a.jam_masuk_iso) = dr.report_date
                    WHERE u.role = 'pegawai'";
            
            $params = [];
            if ($filterType === 'monthly' && !empty($monthsArr)) {
                $monthPlaceholders = implode(',', array_fill(0, count($monthsArr), '?'));
                $sql .= " AND MONTH(a.jam_masuk_iso) IN ($monthPlaceholders) AND YEAR(a.jam_masuk_iso) = ?";
                foreach ($monthsArr as $m) $params[] = $m;
                $params[] = $year;
            }
            
            $sql .= " ORDER BY a.jam_masuk_iso DESC, u.nama ASC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            
            $m_names = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            if (empty($rows)) {
                ob_end_clean();
                $_SESSION['error'] = "Data tidak ditemukan untuk periode/kategori yang dipilih.";
                if (!headers_sent()) {
                    header("Location: ?page=dashboard");
                } else {
                    echo '<script>alert("Data tidak ditemukan."); window.location.href="?page=dashboard";</script>';
                }
                exit;
            }

            $sheets = [];
            $header = ['Tanggal', 'NIM', 'Nama', 'Startup', 'Jam Masuk', 'Status', 'Jam Pulang', 'Keterangan', 'Laporan Harian'];
            
            if ($format === 'per_employee') {
                foreach ($rows as $r) {
                    if (!$r['tanggal']) continue; 
                    $sheets[$r['nama']]['header'] = $header;
                    $sheets[$r['nama']]['rows'][] = [
                        $r['tanggal'], $r['nim'], $r['nama'], $r['startup'],
                        $r['jam_masuk'], $r['status_masuk'], $r['jam_pulang'],
                        $r['ket'], $r['laporan']
                    ];
                    $sheets[$r['nama']]['title'] = "Laporan Presensi - " . $r['nama'];
                }
            } else {
                $selectedMonthNames = array_map(function($m) use ($m_names) { return $m_names[$m] ?? $m; }, $monthsArr);
                $sheets['Combined'] = [
                    'title' => 'Data Presensi Lengkap - ' . ($filterType === 'monthly' ? "Bulan " . implode(', ', $selectedMonthNames) . " Tahun $year" : "Seluruh Periode"),
                    'header' => $header,
                    'rows' => []
                ];
                foreach ($rows as $r) {
                    if (!$r['tanggal']) continue;
                    $sheets['Combined']['rows'][] = [
                        $r['tanggal'], $r['nim'], $r['nama'], $r['startup'],
                        $r['jam_masuk'], $r['status_masuk'], $r['jam_pulang'],
                        $r['ket'], $r['laporan']
                    ];
                }
            }
            
            if (empty($sheets)) {
                ob_end_clean();
                $_SESSION['error'] = "Data kosong setelah filter.";
                if (!headers_sent()) {
                    header("Location: ?page=dashboard");
                } else {
                    echo '<script>alert("Data kosong."); window.location.href="?page=dashboard";</script>';
                }
                exit;
            }

            ob_end_clean();
            exportToExcelXML('export_presensi_' . date('Y-m-d'), $sheets);

        } catch (Throwable $e) {
            if (ob_get_level()) ob_end_clean();
            $_SESSION['error'] = "Terjadi kesalahan saat export: " . $e->getMessage();
            if (!headers_sent()) {
                header("Location: ?page=dashboard");
            } else {
                echo '<script>alert("Error: ' . addslashes($e->getMessage()) . '"); window.location.href="?page=dashboard";</script>';
            }
            exit;
        }
    }

    // Export Monthly Reports to Professional Excel
    if ($action === 'export_monthly') {
        if (!isAdmin()) die('Forbidden');
        
        $year = $_GET['year'] ?? '';
        $month = $_GET['month'] ?? '';
        $startup = $_GET['startup'] ?? '';
        $term = $_GET['term'] ?? '';
        
        $sql = "SELECT mr.*, u.nim, u.nama, u.startup, u.foto_base64 
                FROM monthly_reports mr 
                JOIN users u ON u.id=mr.user_id 
                WHERE 1=1";
        $params = [];
        
        if ($year) { $sql .= " AND mr.year = :y"; $params[':y'] = $year; }
        if ($month) { $sql .= " AND mr.month = :m"; $params[':m'] = $month; }
        if ($startup) { $sql .= " AND u.startup = :s"; $params[':s'] = $startup; }
        if ($term) { $sql .= " AND (u.nama LIKE :t OR u.nim LIKE :t)"; $params[':t'] = "%$term%"; }
        
        $sql .= " ORDER BY u.nama ASC, mr.year DESC, mr.month DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        
        if (empty($rows)) {
            $_SESSION['error'] = "Data laporan bulanan tidak ditemukan.";
            if (!headers_sent()) {
                header("Location: ?page=dashboard");
            } else {
                echo '<script>alert("Data tidak ditemukan."); window.location.href="?page=dashboard";</script>';
            }
            exit;
        }

        $sheets = [];
        $months = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

        foreach ($rows as $r) {
            $uName = $r['nama'];
            if (!isset($sheets[$uName])) {
                $sheets[$uName] = [
                    'title' => 'LAPORAN BULANAN MAGANG - ' . strtoupper($uName),
                    'rows' => []
                ];
                // Employee Info with Labels
                $sheets[$uName]['rows'][] = ['NAMA:', $uName, '_style' => 'sInfoLabel'];
                $sheets[$uName]['rows'][] = ['NIM:', $r['nim'], '_style' => 'sInfoLabel'];
                $sheets[$uName]['rows'][] = ['STARTUP:', $r['startup'], '_style' => 'sInfoLabel'];
                $sheets[$uName]['rows'][] = []; 
            }
            
            $monthName = $months[$r['month']] ?? $r['month'];
            
            // Month Header Block
            $sheets[$uName]['rows'][] = ['PERIODE LAPORAN:', strtoupper($monthName) . ' ' . $r['year'], '', '', '_style' => 'sSubHeader'];
            $sheets[$uName]['rows'][] = ['Status:', strtoupper($r['status']), '', '', '_style' => 'sInfoLabel'];
            $sheets[$uName]['rows'][] = [];

            // Ringkasan Pekerjaan
            $sheets[$uName]['rows'][] = ['RINGKASAN PEKERJAAN:', '', '', '', '_style' => 'sSubHeader'];
            $sheets[$uName]['rows'][] = [$r['summary']];
            $sheets[$uName]['rows'][] = [];

            // Pencapaian Table
            $sheets[$uName]['rows'][] = ['PENCAPAIAN DAN HASIL KERJA:', '', '', '', '_style' => 'sSubHeader'];
            $sheets[$uName]['rows'][] = ['No', 'Pencapaian', 'Detail', '', '_style' => 'sSubHeader'];
            $achievements = json_decode($r['achievements'], true);
            if (is_array($achievements) && !empty($achievements)) {
                $no = 1;
                foreach ($achievements as $ach) {
                    $sheets[$uName]['rows'][] = [$no++, $ach['achievement'] ?? '', $ach['detail'] ?? ''];
                }
            } else {
                $sheets[$uName]['rows'][] = ['-', 'Tidak ada data pencapaian', '-', ''];
            }
            $sheets[$uName]['rows'][] = [];

            // Kendala Table
            $sheets[$uName]['rows'][] = ['KENDALA:', '', '', '', '_style' => 'sSubHeader'];
            $sheets[$uName]['rows'][] = ['No', 'Kendala', 'Solusi', 'Catatan', '_style' => 'sSubHeader'];
            $obstacles = json_decode($r['obstacles'], true);
            if (is_array($obstacles) && !empty($obstacles)) {
                $no = 1;
                foreach ($obstacles as $obs) {
                    $sheets[$uName]['rows'][] = [$no++, $obs['obstacle'] ?? '', $obs['solution'] ?? '', $obs['note'] ?? ''];
                }
            } else {
                $sheets[$uName]['rows'][] = ['-', 'Tidak ada data kendala', '-', '-'];
            }
            
            $sheets[$uName]['rows'][] = [];
            $sheets[$uName]['rows'][] = ['================================================================================', '', '', '']; 
            $sheets[$uName]['rows'][] = []; 
        }

        exportToExcelXML('export_bulanan_' . date('Y-m-d'), $sheets);
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
            
            jsonResponse(['ok'=>true,'id'=>$row['id']]);
        } else {
            $ins=$pdo->prepare("INSERT INTO daily_reports (user_id, report_date, content) VALUES (:u,:d,:c)");
            $ins->execute([':u'=>$uid, ':d'=>$date, ':c'=>$content]);
            
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

    if ($action === 'pegawai_get_notifications') {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if (!$uid) jsonResponse(['error' => 'Unauthorized'], 401);

        $filter = $_GET['filter'] ?? 'unread'; // unread, read
        
        $sql = "SELECT r.* FROM admin_help_requests r WHERE r.user_id = :u AND r.status != 'pending' ";
        $params = [':u' => $uid];

        if ($filter === 'unread') {
            $sql .= " AND r.is_read_by_user = 0 ";
        } elseif ($filter === 'read') {
            $sql .= " AND r.is_read_by_user = 1 ";
        }
        
        $sql .= " ORDER BY r.created_at DESC LIMIT 50";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'pegawai_mark_notifications_read' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if (!$uid) jsonResponse(['error' => 'Unauthorized'], 401);

        $stmt = $pdo->prepare("UPDATE admin_help_requests SET is_read_by_user = 1 WHERE user_id = :u AND status != 'pending'");
        if ($stmt->execute([':u' => $uid])) {
            jsonResponse(['ok' => true]);
        } else {
            jsonResponse(['ok' => false, 'message' => 'Gagal memperbarui notifikasi'], 500);
        }
    }

    if ($action === 'submit_help_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if (!$uid) jsonResponse(['error' => 'Unauthorized'], 401);

        $type = $_POST['request_type'] ?? '';
        
        $params = [':u' => $uid, ':t' => $type];
        $fields = ['user_id', 'request_type'];
        $values = [':u', ':t'];

        if ($type === 'past_attendance') {
            $fields = array_merge($fields, ['alasan_izin', 'jenis_izin', 'bukti_izin', 'tanggal']);
            $values = array_merge($values, [':alasan', ':jenis', ':bukti', ':tanggal']);
            $params[':alasan'] = $_POST['alasan_izin'] ?? '';
            $params[':jenis'] = $_POST['jenis_izin'] ?? 'izin';
            $params[':bukti'] = $_POST['bukti_izin'] ?? null;
            $params[':tanggal'] = $_POST['tanggal'] ?? date('Y-m-d');
        } elseif ($type === 'late_attendance') {
            $fields = array_merge($fields, ['tanggal', 'jam_masuk', 'jam_pulang', 'bukti_presensi', 'lokasi_presensi', 'attendance_type', 'attendance_reason']);
            $values = array_merge($values, [':tanggal', ':jm', ':jp', ':bukti', ':lokasi', ':att_type', ':att_reason']);
            $params[':tanggal'] = $_POST['tanggal'] ?? date('Y-m-d');
            $params[':jm'] = $_POST['jam_masuk'] ?? null;
            $params[':jp'] = $_POST['jam_pulang'] ?? null;
            $params[':bukti'] = $_POST['bukti_presensi'] ?? null;
            $params[':lokasi'] = $_POST['lokasi_presensi'] ?? '';
            $params[':att_type'] = $_POST['attendance_type'] ?? 'wfo';
            $params[':att_reason'] = $_POST['attendance_reason'] ?? null;
        } elseif ($type === 'bug_report') {
            $fields = array_merge($fields, ['bug_description', 'bug_proof']);
            $values = array_merge($values, [':desc', ':proof']);
            $params[':desc'] = $_POST['bug_description'] ?? '';
            $params[':proof'] = $_POST['bug_proof'] ?? null;
        } else {
            jsonResponse(['ok' => false, 'message' => 'Tipe request tidak valid'], 400);
        }

        $sql = "INSERT INTO admin_help_requests (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
        $stmt = $pdo->prepare($sql);
        if ($stmt->execute($params)) {
             jsonResponse(['ok' => true, 'message' => 'Request berhasil dikirim dan menunggu persetujuan admin.']);
        } else {
             jsonResponse(['ok' => false, 'message' => 'Gagal mengirim request.'], 500);
        }
    }

    // User: Get their own help request status list
    if ($action === 'get_user_help_requests') {
        $uid = (int)($_SESSION['user']['id'] ?? 0);
        if (!$uid) jsonResponse(['error' => 'Unauthorized'], 401);
        
        try {
            $stmt = $pdo->prepare(
                "SELECT id, request_type, status, admin_note, created_at,
                        tanggal, jenis_izin, alasan_izin,
                        jam_masuk, jam_pulang, attendance_type,
                        bug_description
                 FROM admin_help_requests
                 WHERE user_id = :uid
                 ORDER BY created_at DESC
                 LIMIT 20"
            );
            $stmt->execute([':uid' => $uid]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            jsonResponse(['ok' => true, 'data' => $rows]);
        } catch (PDOException $e) {
            // Fallback: select only guaranteed columns if some don't exist
            error_log('get_user_help_requests error: ' . $e->getMessage());
            try {
                $stmt2 = $pdo->prepare(
                    "SELECT id, request_type, status, admin_note, created_at,
                            tanggal, jenis_izin, alasan_izin,
                            jam_masuk, jam_pulang, bug_description
                     FROM admin_help_requests
                     WHERE user_id = :uid
                     ORDER BY created_at DESC
                     LIMIT 20"
                );
                $stmt2->execute([':uid' => $uid]);
                $rows2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                // Add missing attendance_type as null
                foreach ($rows2 as &$row) {
                    $row['attendance_type'] = $row['attendance_type'] ?? null;
                }
                jsonResponse(['ok' => true, 'data' => $rows2]);
            } catch (PDOException $e2) {
                error_log('get_user_help_requests fallback error: ' . $e2->getMessage());
                jsonResponse(['ok' => false, 'message' => 'Gagal memuat data: ' . $e2->getMessage()], 500);
            }
        }
    }

    if ($action === 'admin_get_help_notifications') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $stmt = $pdo->query("SELECT r.*, u.nama, u.nim FROM admin_help_requests r JOIN users u ON u.id = r.user_id WHERE r.status = 'pending' ORDER BY r.created_at DESC");
        $rows = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'admin_get_all_help_requests') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $stmt = $pdo->query("SELECT r.*, u.nama, u.nim FROM admin_help_requests r JOIN users u ON u.id = r.user_id ORDER BY r.created_at DESC");
        $rows = $stmt->fetchAll();
        jsonResponse(['ok' => true, 'data' => $rows]);
    }

    if ($action === 'admin_handle_help_request' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? ''; // approved / disapproved / solved
        $note = $_POST['admin_note'] ?? ($_POST['note'] ?? '');
        
        error_log("admin_handle_help_request: ID=$id, Status='$status', Note='$note'");
        
        if (!in_array($status, ['approved', 'disapproved', 'solved'])) {
            error_log("admin_handle_help_request: Invalid status '$status'");
            jsonResponse(['ok' => false, 'message' => 'Status tidak valid'], 400);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("SELECT * FROM admin_help_requests WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $id]);
            $req = $stmt->fetch();
            
            if (!$req) throw new Exception("Request tidak ditemukan");
            if ($req['status'] !== 'pending') throw new Exception("Request sudah diproses sebelumnya");

            if (($status === 'approved' || $status === 'solved') && $req['request_type'] !== 'bug_report') {
                if ($req['request_type'] === 'past_attendance') {
                    // Insert into attendance_notes
                    $ins = $pdo->prepare("INSERT INTO attendance_notes (user_id, date, type, keterangan, bukti) VALUES (:u, :d, :t, :k, :b) ON DUPLICATE KEY UPDATE type=:t, keterangan=:k, bukti=:b");
                    $ins->execute([
                        ':u' => $req['user_id'],
                        ':d' => $req['tanggal'],
                        ':t' => $req['jenis_izin'],
                        ':k' => $req['alasan_izin'],
                        ':b' => $req['bukti_izin']
                    ]);
                } elseif ($req['request_type'] === 'late_attendance') {
                    // Determine ket and reason based on request
                    $ket = $req['attendance_type'] ?? 'wfo';
                    $reason = $req['attendance_reason'] ?? null;
                    
                    $alasanWfa = ($ket === 'wfa') ? $reason : null;
                    $alasanOvertime = ($ket === 'overtime') ? $reason : null;

                    // Insert into attendance
                    $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, screenshot_masuk, lokasi_masuk, jam_pulang, jam_pulang_iso, screenshot_pulang, lokasi_pulang, ket, alasan_wfa, alasan_overtime, status) 
                        VALUES (:u, :jm, :jmi, :sm, :lm, :jp, :jpi, :sp, :lp, :ket, :awfa, :aovt, 'ontime') 
                        ON DUPLICATE KEY UPDATE jam_masuk=:jm, jam_masuk_iso=:jmi, screenshot_masuk=:sm, lokasi_masuk=:lm, jam_pulang=:jp, jam_pulang_iso=:jpi, screenshot_pulang=:sp, lokasi_pulang=:lp, ket=:ket, alasan_wfa=:awfa, alasan_overtime=:aovt");
                    
                    $jmi = $req['tanggal'] . ' ' . $req['jam_masuk'];
                    $jpi = $req['jam_pulang'] ? ($req['tanggal'] . ' ' . $req['jam_pulang']) : null;
                    
                    $ins->execute([
                        ':u' => $req['user_id'],
                        ':jm' => substr($req['jam_masuk'], 0, 5),
                        ':jmi' => $jmi,
                        ':sm' => $req['bukti_presensi'],
                        ':lm' => $req['lokasi_presensi'],
                        ':jp' => $req['jam_pulang'] ? substr($req['jam_pulang'], 0, 5) : null,
                        ':jpi' => $jpi,
                        ':sp' => null, 
                        ':lp' => null,
                        ':ket' => $ket,
                        ':awfa' => $alasanWfa,
                        ':aovt' => $alasanOvertime
                    ]);
                }
            }

            $upd = $pdo->prepare("UPDATE admin_help_requests SET status = :s, admin_note = :n WHERE id = :id");
            $upd->execute([':s' => $status, ':n' => $note, ':id' => $id]);

            $pdo->commit();
            $msg = 'Request berhasil ';
            if ($status === 'solved') $msg .= 'diselesaikan';
            elseif ($status === 'approved') $msg .= 'disetujui';
            else $msg .= 'ditolak';
            jsonResponse(['ok' => true, 'message' => $msg]);
        } catch (Exception $e) {
            $pdo->rollBack();
            jsonResponse(['ok' => false, 'message' => 'Gagal memproses: ' . $e->getMessage()], 500);
        }
    }

    // Admin: Manage Manual Holidays
    if ($action === 'get_manual_holidays') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        // Check if table exists first preventing error if not migrated
        try {
            $stmt = $pdo->query("SELECT * FROM manual_holidays ORDER BY date DESC");
            jsonResponse(['ok' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
             jsonResponse(['ok' => true, 'data' => [], 'message' => 'Table not found or empty']);
        }
    }

    if ($action === 'add_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $date = $_POST['date'] ?? '';
        $desc = $_POST['description'] ?? '';
        
        if (!$date || !$desc) jsonResponse(['ok'=>false, 'message'=>'Tanggal dan keterangan harus diisi'], 400);
        
        try {
            // Insert into 'name' column appropriately
            $stmt = $pdo->prepare("INSERT INTO manual_holidays (date, name) VALUES (:d, :n)");
            $stmt->execute([':d'=>$date, ':n'=>$desc]);
            jsonResponse(['ok'=>true]);
        } catch (PDOException $e) {
            jsonResponse(['ok'=>false, 'message'=>'Gagal menyimpan: '.$e->getMessage()], 500);
        }
    }

    if ($action === 'delete_manual_holiday' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Forbidden'], 403);
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) jsonResponse(['ok'=>false, 'message'=>'ID tidak valid'], 400);
        
        try {
            $stmt = $pdo->prepare("DELETE FROM manual_holidays WHERE id = :id");
            $stmt->execute([':id'=>$id]);
            jsonResponse(['ok'=>true]);
        } catch (PDOException $e) {
            jsonResponse(['ok'=>false, 'message'=>'Gagal menghapus: '.$e->getMessage()], 500);
        }
    }

    jsonResponse(['ok' => false, 'message' => 'Endpoint tidak ditemukan'], 404);
}
