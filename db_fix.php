<?php
require_once 'pages/layout_header.php';
global $pdo;

echo "<pre>";

function runSql($sql) {
    global $pdo;
    echo "Running: $sql\n";
    try {
        $pdo->exec($sql);
        echo "✅ Success\n";
    } catch (PDOException $e) {
        echo "❌ Failed: " . $e->getMessage() . "\n";
    }
}

runSql("ALTER TABLE admin_help_requests MODIFY COLUMN status ENUM('pending', 'approved', 'disapproved', 'solved') DEFAULT 'pending'");
runSql("ALTER TABLE admin_help_requests ADD COLUMN is_read_by_user BOOLEAN DEFAULT FALSE AFTER admin_note");

echo "\nNew Structure:\n";
$stmt = $pdo->query("DESCRIBE admin_help_requests");
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
?>
