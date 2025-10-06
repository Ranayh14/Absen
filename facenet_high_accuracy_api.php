<?php
/**
 * FaceNet High Accuracy API
 * 
 * This API provides high-accuracy face recognition with strict quality validation
 * and confidence thresholds to ensure only reliable recognitions are accepted.
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
    'process_high_accuracy_attendance',
    'generate_high_accuracy_embedding',
    'get_performance_stats',
    'update_thresholds',
    'validate_face_quality'
];

if (!in_array($action, $allowedActions)) {
    jsonResponse(['error' => 'Invalid action'], 400);
}

try {
    // Execute Python script based on action
    $pythonScript = __DIR__ . '/facenet_high_accuracy_cli.py';
    
    if (!file_exists($pythonScript)) {
        jsonResponse(['error' => 'High accuracy CLI script not found'], 500);
    }
    
    // Prepare command
    $command = "python3 \"$pythonScript\" --action \"$action\"";
    
    // Add parameters based on action
    switch ($action) {
        case 'process_high_accuracy_attendance':
            $image = $input['image'] ?? '';
            $userId = $input['user_id'] ?? null;
            
            if (empty($image)) {
                jsonResponse(['error' => 'Image is required'], 400);
            }
            
            $command .= " --image \"$image\"";
            if ($userId !== null) {
                $command .= " --user_id \"$userId\"";
            }
            break;
            
        case 'generate_high_accuracy_embedding':
            $image = $input['image'] ?? '';
            $userId = $input['user_id'] ?? '';
            
            if (empty($image) || empty($userId)) {
                jsonResponse(['error' => 'Image and user_id are required'], 400);
            }
            
            $command .= " --image \"$image\" --user_id \"$userId\"";
            break;
            
        case 'update_thresholds':
            $thresholds = $input['thresholds'] ?? [];
            
            if (empty($thresholds)) {
                jsonResponse(['error' => 'Thresholds are required'], 400);
            }
            
            $thresholdsJson = json_encode($thresholds);
            $command .= " --thresholds \"$thresholdsJson\"";
            break;
            
        case 'validate_face_quality':
            $image = $input['image'] ?? '';
            
            if (empty($image)) {
                jsonResponse(['error' => 'Image is required'], 400);
            }
            
            $command .= " --image \"$image\"";
            break;
    }
    
    // Execute command
    $output = [];
    $returnCode = 0;
    exec($command . ' 2>&1', $output, $returnCode);
    
    if ($returnCode !== 0) {
        $errorMessage = implode("\n", $output);
        error_log("High accuracy API error: $errorMessage");
        jsonResponse(['error' => 'High accuracy processing failed', 'details' => $errorMessage], 500);
    }
    
    // Parse output
    $response = json_decode(implode("\n", $output), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        $rawOutput = implode("\n", $output);
        error_log("High accuracy API JSON parse error: $rawOutput");
        jsonResponse(['error' => 'Invalid response from high accuracy service'], 500);
    }
    
    // Return response
    if ($response['success'] ?? false) {
        jsonResponse($response);
    } else {
        $errorCode = $response['error_code'] ?? 'UNKNOWN_ERROR';
        $httpCode = 400;
        
        // Map error codes to HTTP status codes
        switch ($errorCode) {
            case 'RATE_LIMIT':
                $httpCode = 429;
                break;
            case 'QUALITY_INSUFFICIENT':
            case 'QUALITY_TOO_LOW':
                $httpCode = 422;
                break;
            case 'FACE_NOT_RECOGNIZED':
                $httpCode = 404;
                break;
            case 'VERIFICATION_FAILED':
            case 'SECURITY_CHECK_FAILED':
                $httpCode = 403;
                break;
            case 'ATTENDANCE_RECORDING_FAILED':
                $httpCode = 500;
                break;
        }
        
        jsonResponse($response, $httpCode);
    }
    
} catch (Exception $e) {
    error_log("High accuracy API exception: " . $e->getMessage());
    jsonResponse(['error' => 'Internal server error', 'details' => $e->getMessage()], 500);
}
?>
