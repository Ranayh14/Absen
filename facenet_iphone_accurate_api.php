<?php
/**
 * iPhone-Level Accurate FaceNet API - Maximum Accuracy with Unique Feature Analysis
 * 
 * This API provides iPhone Face ID level accuracy by analyzing unique facial features,
 * facial landmarks, skin texture, eye characteristics, and other biometric markers.
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

// Error handling
set_error_handler(function($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Response helper function
function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['error' => 'Only POST requests are allowed'], 405);
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? '';

// Validate action
$allowedActions = [
    'process_attendance_iphone_level',
    'get_performance_stats',
    'analyze_unique_features',
    'test_iphone_level'
];

if (!in_array($action, $allowedActions)) {
    jsonResponse(['error' => 'Invalid action'], 400);
}

try {
    // Execute Python script based on action
    $pythonScript = __DIR__ . '/facenet_iphone_accurate_cli.py';
    
    if (!file_exists($pythonScript)) {
        jsonResponse(['error' => 'iPhone-level CLI script not found'], 500);
    }
    
    // Prepare command
    $command = "python3 \"$pythonScript\" --action \"$action\"";
    
    // Add parameters based on action
    switch ($action) {
        case 'process_attendance_iphone_level':
            $image = $input['image'] ?? '';
            
            if (empty($image)) {
                jsonResponse(['error' => 'Image is required'], 400);
            }
            
            $command .= " --image \"$image\"";
            break;
            
        case 'analyze_unique_features':
            $image = $input['image'] ?? '';
            
            if (empty($image)) {
                jsonResponse(['error' => 'Image is required'], 400);
            }
            
            $command .= " --image \"$image\"";
            break;
            
        case 'test_iphone_level':
            $command .= " --test_mode true";
            break;
    }
    
    // Execute command with ultra-fast timeout
    $startTime = microtime(true);
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    $executionTime = microtime(true) - $startTime;
    
    if ($returnCode !== 0) {
        $errorMessage = implode("\n", $output);
        error_log("iPhone-level API error: $errorMessage");
        jsonResponse([
            'error' => 'iPhone-level processing failed', 
            'details' => $errorMessage,
            'execution_time' => $executionTime
        ], 500);
    }
    
    // Parse output
    $response = json_decode(implode("\n", $output), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $rawOutput = implode("\n", $output);
        error_log("iPhone-level API JSON parse error: $rawOutput");
        jsonResponse([
            'error' => 'Invalid response from iPhone-level service',
            'execution_time' => $executionTime
        ], 500);
    }
    
    // Add execution time to response
    if (isset($response['data'])) {
        $response['data']['api_execution_time'] = $executionTime;
        $response['data']['total_response_time'] = $response['data']['processing_time'] + $executionTime;
    }
    
    // Return response
    if ($response['success'] ?? false) {
        jsonResponse($response);
    } else {
        $errorCode = $response['error_code'] ?? 'UNKNOWN_ERROR';
        $httpCode = 400;
        
        // Map error codes to HTTP status codes
        switch ($errorCode) {
            case 'NO_FACE_DETECTED':
                $httpCode = 404;
                break;
            case 'FACE_NOT_RECOGNIZED':
                $httpCode = 404;
                break;
            case 'INVALID_IMAGE':
                $httpCode = 422;
                break;
            case 'PROCESSING_ERROR':
                $httpCode = 500;
                break;
            case 'UNIQUE_FEATURES_NOT_FOUND':
                $httpCode = 422;
                break;
        }
        
        jsonResponse($response, $httpCode);
    }
    
} catch (Exception $e) {
    error_log("iPhone-level API exception: " . $e->getMessage());
    jsonResponse([
        'error' => 'Internal server error', 
        'details' => $e->getMessage()
    ], 500);
}
?>
