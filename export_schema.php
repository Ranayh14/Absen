<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=absen_db;charset=utf8mb4', 'root', '');
$stmt = $pdo->query('SHOW TABLES');
$tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
$out = '';
foreach($tables as $t) {
    if ($t == 'admin_help_requests' || $t == 'attendance' || $t == 'daily_reports' || $t == 'monthly_reports' || $t == 'users') {
        $stmt2 = $pdo->query('SHOW CREATE TABLE '.$t);
        $out .= $stmt2->fetch(PDO::FETCH_ASSOC)['Create Table'].";\n\n";
    }
}
file_put_contents('full_schema.txt', $out);
echo "Done";
