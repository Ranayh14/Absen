<?php
require_once 'pages/layout_header.php';
global $pdo;

try {
    $stmt = $pdo->prepare("UPDATE admin_help_requests SET status = 'pending' WHERE status = '' OR status IS NULL");
    $stmt->execute();
    echo "Fixed " . $stmt->rowCount() . " rows with empty status.\n";
} catch (Exception $e) {
    echo "Error fixing data: " . $e->getMessage() . "\n";
}
?>
