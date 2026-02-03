<?php
require_once 'pages/layout_header.php';
global $pdo;

header('Content-Type: application/json');

try {
    $stmt = $pdo->query("SELECT id, request_type, status, admin_note FROM admin_help_requests WHERE request_type = 'bug_report'");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['ok' => true, 'data' => $data]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
?>
