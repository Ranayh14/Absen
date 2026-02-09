<?php
$DB_HOST = '127.0.0.1';
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'absen_db';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // 1. Verify Columns
    echo "--- Verifying Columns ---\n";
    $stmt = $pdo->query("DESCRIBE admin_help_requests");
    $cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('attendance_type', $cols) && in_array('attendance_reason', $cols)) {
        echo "PASS: Columns attendance_type and attendance_reason exist.\n";
    } else {
        echo "FAIL: Columns missing!\n";
        exit;
    }

    // 2. Simulate User Submission (Insert directly)
    echo "\n--- Simulating User 'Late Attendance - WFA' Request ---\n";
    $userId = 1; // Assuming ID 1 exists (admin usually) or we pick one
    $stmtUser = $pdo->query("SELECT id FROM users LIMIT 1");
    $user = $stmtUser->fetch();
    if ($user) $userId = $user['id'];
    
    $reqDate = date('Y-m-d');
    $reqTime = '08:00';
    $reqType = 'wfa';
    $reqReason = 'Verification Test Reason';
    
    $sql = "INSERT INTO admin_help_requests (user_id, request_type, tanggal, jam_masuk, attendance_type, attendance_reason, status) 
            VALUES (:u, 'late_attendance', :d, :jm, :at, :ar, 'pending')";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':u' => $userId, 
        ':d' => $reqDate, 
        ':jm' => $reqTime, 
        ':at' => $reqType, 
        ':ar' => $reqReason
    ]);
    $reqId = $pdo->lastInsertId();
    echo "PASS: Inserted Help Request ID: $reqId\n";
    
    // 3. Simulate Admin Approval (Run the logic we added)
    echo "\n--- Simulating Admin Approval ---\n";
    
    // Fetch request like the handler does
    $stmt = $pdo->prepare("SELECT * FROM admin_help_requests WHERE id = :id");
    $stmt->execute([':id' => $reqId]);
    $req = $stmt->fetch();
    
    if ($req['request_type'] === 'late_attendance') {
        // LOGIC FROM backend
        $ket = $req['attendance_type'] ?? 'wfo';
        $reason = $req['attendance_reason'] ?? null;
        
        $alasanWfa = ($ket === 'wfa') ? $reason : null;
        $alasanOvertime = ($ket === 'overtime') ? $reason : null;

        // Verify logic variables
        if ($ket === 'wfa' && $alasanWfa === $reqReason) {
            echo "PASS: Logic correctly identified WFA and Reason.\n";
        } else {
            echo "FAIL: Logic failed. Ket: $ket, AlasanWfa: $alasanWfa\n";
        }

        // Simulate Insert into Attendance
        // We won't actually insert to avoid polluting 'attendance' table heavily, or we can insert and then delete.
        // Let's insert to be sure.
        
        $ins = $pdo->prepare("INSERT INTO attendance (user_id, jam_masuk, jam_masuk_iso, screenshot_masuk, lokasi_masuk, jam_pulang, jam_pulang_iso, screenshot_pulang, lokasi_pulang, ket, alasan_wfa, alasan_overtime, status) 
            VALUES (:u, :jm, :jmi, :sm, :lm, :jp, :jpi, :sp, :lp, :ket, :awfa, :aovt, 'ontime') 
            ON DUPLICATE KEY UPDATE jam_masuk=:jm, jam_masuk_iso=:jmi, ket=:ket, alasan_wfa=:awfa, alasan_overtime=:aovt");
        
        $jmi = $req['tanggal'] . ' ' . $req['jam_masuk'];
        $jpi = null;
        
        $ins->execute([
            ':u' => $req['user_id'],
            ':jm' => substr($req['jam_masuk'], 0, 5),
            ':jmi' => $jmi,
            ':sm' => 'test_proof',
            ':lm' => 'test_loc',
            ':jp' => null,
            ':jpi' => null,
            ':sp' => null, 
            ':lp' => null,
            ':ket' => $ket,
            ':awfa' => $alasanWfa,
            ':aovt' => $alasanOvertime
        ]);
        
        echo "PASS: Executed Attendance Insert/Update.\n";
        
        // Check result
        $stmtCheck = $pdo->prepare("SELECT * FROM attendance WHERE user_id = :u AND DATE(jam_masuk_iso) = :d");
        $stmtCheck->execute([':u' => $userId, ':d' => $reqDate]);
        $att = $stmtCheck->fetch();
        
        if ($att && $att['ket'] === 'wfa' && $att['alasan_wfa'] === $reqReason) {
             echo "PASS: Database record verified! Ket='wfa', Alasan='{$att['alasan_wfa']}'\n";
        } else {
             echo "FAIL: Database record mismatch.\n";
             print_r($att);
        }
    }
    
    // Cleanup
    echo "\n--- Cleanup ---\n";
    $pdo->exec("DELETE FROM admin_help_requests WHERE id = $reqId");
    $pdo->exec("DELETE FROM attendance WHERE user_id = $userId AND DATE(jam_masuk_iso) = '$reqDate' AND screenshot_masuk = 'test_proof'");
    echo "DONE.\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
