<?php
require_once 'config.php';
$pdo = getPdo();
$stmt = $pdo->query("DESCRIBE admin_help_requests");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach($rows as $row) {
    echo $row['Field'] . " (" . $row['Type'] . ")\n";
}
