<?php
// facenet_enhanced_api.php
header('Content-Type: application/json');

// Path to the Python CLI script
$pythonCliScript = __DIR__ . '/facenet_enhanced_cli.py';

// Get action from POST request
$action = $_POST['action'] ?? '';
if (empty($action)) {
    http_response_code(400);
    echo json_encode(['error' => 'Action is required']);
    exit();
}

// Validate action
$allowedActions = ['generate_enhanced_embedding', 'save_enhanced_embedding', 'recognize_enhanced_face', 'process_enhanced_attendance'];
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
// Use 'python3' explicitly if 'python' points to an older version
$command = escapeshellcmd("python3 {$pythonCliScript} '{$jsonArgs}' 2>&1");
$output = shell_exec($command);

// Decode and return the JSON response from the Python script
$result = json_decode($output, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to decode Python script response', 'raw_output' => $output]);
} else {
    if (isset($result['success']) && $result['success'] === false) {
        http_response_code(500); // Or 400 depending on the error type
    }
    echo json_encode($result);
}
?>
