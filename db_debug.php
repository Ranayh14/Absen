<?php
require_once 'pages/layout_header.php';
global $pdo;

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("DESCRIBE admin_help_requests");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'columns' => $columns]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
