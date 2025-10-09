<?php
/**
 * Ultra Detailed Face Recognition API - iPhone Face ID Level Accuracy
 * 
 * This API provides ultra detailed face recognition with iPhone Face ID level accuracy
 * by analyzing extremely detailed facial features including cheek dimensions, chin dimensions,
 * forehead dimensions, nose dimensions, eyeball dimensions, eye fold dimensions, and more.
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

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
    exit();
}

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'process_attendance_ultra_detailed':
            if (!isset($input['image'])) {
                throw new Exception('Image is required');
            }
            
            $result = processUltraDetailedAttendance($input['image']);
            echo json_encode($result);
            break;
            
        case 'get_performance_stats':
            $stats = getUltraDetailedPerformanceStats();
            echo json_encode(['success' => true, 'data' => $stats]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function processUltraDetailedAttendance($base64Image) {
    try {
        // Execute Python script for ultra detailed processing
        $command = "python facenet_ultra_detailed_service.py process_attendance_ultra_detailed " . escapeshellarg($base64Image);
        
        $startTime = microtime(true);
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        $executionTime = microtime(true) - $startTime;
        
        if ($returnCode === 0 && !empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result && $result['success']) {
                // Add execution time to result
                $result['execution_time'] = $executionTime;
                return $result;
            }
        }
        
        error_log("Ultra detailed processing failed: " . implode("\n", $output));
        return [
            'success' => false,
            'error' => 'Ultra detailed processing failed',
            'execution_time' => $executionTime
        ];
    } catch (Exception $e) {
        error_log("Error in ultra detailed processing: " . $e->getMessage());
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'execution_time' => 0
        ];
    }
}

function getUltraDetailedPerformanceStats() {
    try {
        $command = "python facenet_ultra_detailed_service.py get_performance_stats";
        
        $output = [];
        $returnCode = 0;
        exec($command . ' 2>&1', $output, $returnCode);
        
        if ($returnCode === 0 && !empty($output)) {
            $result = json_decode(implode("\n", $output), true);
            if ($result) {
                return $result;
            }
        }
        
        error_log("Failed to get ultra detailed performance stats: " . implode("\n", $output));
        return null;
    } catch (Exception $e) {
        error_log("Error getting ultra detailed performance stats: " . $e->getMessage());
        return null;
    }
}
?>


