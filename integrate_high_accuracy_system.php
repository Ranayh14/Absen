<?php
/**
 * High Accuracy System Integration
 * 
 * This script integrates the high-accuracy FaceNet system into the existing
 * attendance system with 90% confidence threshold and quality validation.
 */

// Include the main index.php to get access to functions
require_once 'index.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get the action from request
$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'enable_high_accuracy':
        enableHighAccuracyMode();
        break;
        
    case 'disable_high_accuracy':
        disableHighAccuracyMode();
        break;
        
    case 'get_high_accuracy_status':
        getHighAccuracyStatus();
        break;
        
    case 'update_high_accuracy_settings':
        updateHighAccuracySettings();
        break;
        
    case 'test_high_accuracy':
        testHighAccuracySystem();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function enableHighAccuracyMode() {
    try {
        // Update user settings to enable high accuracy mode
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Check if settings table exists, create if not
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_setting (user_id, setting_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        
        // Enable high accuracy mode for user
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value) 
            VALUES (?, 'high_accuracy_mode', '1')
            ON DUPLICATE KEY UPDATE setting_value = '1', updated_at = NOW()
        ");
        $stmt->execute([$userId]);
        
        // Log the action
        error_log("High accuracy mode enabled for user ID: $userId");
        
        echo json_encode([
            'success' => true,
            'message' => 'High accuracy mode enabled successfully',
            'data' => [
                'user_id' => $userId,
                'high_accuracy_mode' => true,
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error enabling high accuracy mode: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to enable high accuracy mode']);
    }
}

function disableHighAccuracyMode() {
    try {
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Disable high accuracy mode for user
        $stmt = $pdo->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value) 
            VALUES (?, 'high_accuracy_mode', '0')
            ON DUPLICATE KEY UPDATE setting_value = '0', updated_at = NOW()
        ");
        $stmt->execute([$userId]);
        
        // Log the action
        error_log("High accuracy mode disabled for user ID: $userId");
        
        echo json_encode([
            'success' => true,
            'message' => 'High accuracy mode disabled successfully',
            'data' => [
                'user_id' => $userId,
                'high_accuracy_mode' => false,
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error disabling high accuracy mode: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to disable high accuracy mode']);
    }
}

function getHighAccuracyStatus() {
    try {
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Get user's high accuracy settings
        $stmt = $pdo->prepare("
            SELECT setting_key, setting_value 
            FROM user_settings 
            WHERE user_id = ? AND setting_key LIKE 'high_accuracy_%'
        ");
        $stmt->execute([$userId]);
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        
        // Get system performance stats
        $performanceStats = getHighAccuracyPerformanceStats();
        
        // Get user's face embedding status
        $stmt = $pdo->prepare("
            SELECT 
                face_embedding IS NOT NULL as has_embedding,
                face_embedding_updated,
                advanced_features IS NOT NULL as has_advanced_features,
                facial_geometry IS NOT NULL as has_geometry,
                feature_vector IS NOT NULL as has_feature_vector
            FROM users 
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $embeddingStatus = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'user_id' => $userId,
                'high_accuracy_mode' => ($settings['high_accuracy_mode'] ?? '0') === '1',
                'settings' => $settings,
                'embedding_status' => $embeddingStatus,
                'performance_stats' => $performanceStats,
                'system_status' => [
                    'high_accuracy_service' => checkHighAccuracyServiceStatus(),
                    'quality_validator' => checkQualityValidatorStatus(),
                    'database_connection' => $pdo !== null
                ],
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error getting high accuracy status: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to get high accuracy status']);
    }
}

function updateHighAccuracySettings() {
    try {
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Get settings from request
        $settings = json_decode(file_get_contents('php://input'), true);
        
        if (!$settings) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid settings data']);
            return;
        }
        
        // Validate and update settings
        $allowedSettings = [
            'high_accuracy_mode',
            'confidence_threshold',
            'quality_threshold',
            'max_attempts',
            'cooldown_period'
        ];
        
        $updatedSettings = [];
        
        foreach ($settings as $key => $value) {
            if (in_array($key, $allowedSettings)) {
                // Validate setting values
                if ($key === 'confidence_threshold' && ($value < 0.5 || $value > 1.0)) {
                    continue; // Skip invalid values
                }
                if ($key === 'quality_threshold' && ($value < 0.5 || $value > 1.0)) {
                    continue; // Skip invalid values
                }
                if ($key === 'max_attempts' && ($value < 1 || $value > 10)) {
                    continue; // Skip invalid values
                }
                if ($key === 'cooldown_period' && ($value < 10 || $value > 300)) {
                    continue; // Skip invalid values
                }
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_settings (user_id, setting_key, setting_value) 
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
                ");
                $stmt->execute([$userId, $key, $value]);
                $updatedSettings[$key] = $value;
            }
        }
        
        // Log the action
        error_log("High accuracy settings updated for user ID: $userId - " . json_encode($updatedSettings));
        
        echo json_encode([
            'success' => true,
            'message' => 'High accuracy settings updated successfully',
            'data' => [
                'user_id' => $userId,
                'updated_settings' => $updatedSettings,
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error updating high accuracy settings: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update high accuracy settings']);
    }
}

function testHighAccuracySystem() {
    try {
        $userId = $_SESSION['user']['id'];
        
        // Test high accuracy service availability
        $serviceStatus = checkHighAccuracyServiceStatus();
        
        // Test quality validator
        $validatorStatus = checkQualityValidatorStatus();
        
        // Test database connection
        $pdo = getPdo();
        $dbStatus = $pdo !== null;
        
        // Test API endpoints
        $apiStatus = testHighAccuracyAPI();
        
        $overallStatus = $serviceStatus && $validatorStatus && $dbStatus && $apiStatus;
        
        echo json_encode([
            'success' => true,
            'data' => [
                'overall_status' => $overallStatus,
                'components' => [
                    'high_accuracy_service' => $serviceStatus,
                    'quality_validator' => $validatorStatus,
                    'database_connection' => $dbStatus,
                    'api_endpoints' => $apiStatus
                ],
                'recommendations' => getSystemRecommendations($serviceStatus, $validatorStatus, $dbStatus, $apiStatus),
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error testing high accuracy system: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to test high accuracy system']);
    }
}

function checkHighAccuracyServiceStatus() {
    try {
        // Check if high accuracy service files exist
        $serviceFile = __DIR__ . '/facenet_high_accuracy_service.py';
        $cliFile = __DIR__ . '/facenet_high_accuracy_cli.py';
        
        if (!file_exists($serviceFile) || !file_exists($cliFile)) {
            return false;
        }
        
        // Test Python service
        $command = "python3 \"$cliFile\" --action get_performance_stats 2>&1";
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
        
    } catch (Exception $e) {
        error_log("Error checking high accuracy service status: " . $e->getMessage());
        return false;
    }
}

function checkQualityValidatorStatus() {
    try {
        // Check if quality validator file exists
        $validatorFile = __DIR__ . '/facenet_quality_validator.py';
        
        if (!file_exists($validatorFile)) {
            return false;
        }
        
        // Test quality validator
        $command = "python3 -c \"import sys; sys.path.insert(0, '.'); from facenet_quality_validator import validate_face_quality; print('OK')\" 2>&1";
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        return $returnCode === 0;
        
    } catch (Exception $e) {
        error_log("Error checking quality validator status: " . $e->getMessage());
        return false;
    }
}

function testHighAccuracyAPI() {
    try {
        // Test API endpoint availability
        $apiFile = __DIR__ . '/facenet_high_accuracy_api.php';
        
        if (!file_exists($apiFile)) {
            return false;
        }
        
        // Test API with a simple request
        $testData = json_encode(['action' => 'get_performance_stats']);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_high_accuracy_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $testData);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
        
    } catch (Exception $e) {
        error_log("Error testing high accuracy API: " . $e->getMessage());
        return false;
    }
}

function getSystemRecommendations($serviceStatus, $validatorStatus, $dbStatus, $apiStatus) {
    $recommendations = [];
    
    if (!$serviceStatus) {
        $recommendations[] = 'Install Python dependencies: pip install opencv-python numpy tensorflow';
        $recommendations[] = 'Check Python service files are present and executable';
    }
    
    if (!$validatorStatus) {
        $recommendations[] = 'Install OpenCV for quality validation: pip install opencv-python';
        $recommendations[] = 'Check quality validator file exists and is accessible';
    }
    
    if (!$dbStatus) {
        $recommendations[] = 'Check database connection settings';
        $recommendations[] = 'Verify database server is running';
    }
    
    if (!$apiStatus) {
        $recommendations[] = 'Check PHP cURL extension is enabled';
        $recommendations[] = 'Verify API endpoint files are accessible';
    }
    
    if (empty($recommendations)) {
        $recommendations[] = 'System is ready for high-accuracy face recognition';
    }
    
    return $recommendations;
}

// Helper function to get user's high accuracy mode setting
function isUserHighAccuracyMode($userId) {
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = ? AND setting_key = 'high_accuracy_mode'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result && $result['setting_value'] === '1';
        
    } catch (Exception $e) {
        error_log("Error checking user high accuracy mode: " . $e->getMessage());
        return false;
    }
}

// Helper function to get user's confidence threshold
function getUserConfidenceThreshold($userId) {
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = ? AND setting_key = 'confidence_threshold'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['setting_value']) : 0.90; // Default 90%
        
    } catch (Exception $e) {
        error_log("Error getting user confidence threshold: " . $e->getMessage());
        return 0.90; // Default 90%
    }
}

// Helper function to get user's quality threshold
function getUserQualityThreshold($userId) {
    try {
        $pdo = getPdo();
        $stmt = $pdo->prepare("
            SELECT setting_value 
            FROM user_settings 
            WHERE user_id = ? AND setting_key = 'quality_threshold'
        ");
        $stmt->execute([$userId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? floatval($result['setting_value']) : 0.80; // Default 80%
        
    } catch (Exception $e) {
        error_log("Error getting user quality threshold: " . $e->getMessage());
        return 0.80; // Default 80%
    }
}
?>
