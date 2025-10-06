<?php
/**
 * FaceNet API Endpoint
 * 
 * This file serves as an API endpoint for FaceNet operations.
 * It receives requests from the main application and forwards them to the Python FaceNet service.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get the action from POST data
$action = $_POST['action'] ?? '';

if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Action is required']);
    exit();
}

// Validate action
$allowedActions = ['generate_embedding', 'save_embedding', 'recognize_face', 'process_attendance'];
if (!in_array($action, $allowedActions)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit();
}

// Get image data
$image = $_POST['image'] ?? '';
if (empty($image)) {
    http_response_code(400);
    echo json_encode(['error' => 'Image is required']);
    exit();
}

// Get threshold if provided
$threshold = isset($_POST['threshold']) ? (float)$_POST['threshold'] : 1.0;

// Get user_id if provided
$user_id = $_POST['user_id'] ?? null;

// Prepare command arguments
$args = [
    'action' => $action,
    'image' => $image,
    'threshold' => $threshold
];

// Add user_id if provided
if ($user_id !== null) {
    $args['user_id'] = $user_id;
}

// Convert to JSON for command line
$jsonArgs = json_encode($args);

// Execute Python CLI script
$command = "python facenet_cli.py " . escapeshellarg($jsonArgs);
$output = shell_exec($command . ' 2>&1');

// Check if command executed successfully
if ($output === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute FaceNet service']);
    exit();
}

// Parse output
$result = json_decode($output, true);

if ($result === null) {
    http_response_code(500);
    echo json_encode(['error' => 'Invalid response from FaceNet service', 'raw_output' => $output]);
    exit();
}

// Return the result
echo json_encode($result);
?>