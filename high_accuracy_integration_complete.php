<?php
/**
 * High Accuracy Integration Complete
 * 
 * This file provides the final integration steps for the high-accuracy FaceNet system.
 * It includes all necessary components and instructions for complete integration.
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
    case 'complete_integration':
        completeHighAccuracyIntegration();
        break;
        
    case 'check_integration_status':
        checkIntegrationStatus();
        break;
        
    case 'get_integration_instructions':
        getIntegrationInstructions();
        break;
        
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function completeHighAccuracyIntegration() {
    try {
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Create user settings table if not exists
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
        
        // Set default thresholds
        $defaultSettings = [
            'confidence_threshold' => '0.90',
            'quality_threshold' => '0.80',
            'max_attempts' => '3',
            'cooldown_period' => '60'
        ];
        
        foreach ($defaultSettings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
            ");
            $stmt->execute([$userId, $key, $value]);
        }
        
        // Log the integration
        error_log("High accuracy integration completed for user ID: $userId");
        
        echo json_encode([
            'success' => true,
            'message' => 'High accuracy integration completed successfully',
            'data' => [
                'user_id' => $userId,
                'high_accuracy_mode' => true,
                'settings' => $defaultSettings,
                'timestamp' => time()
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error completing high accuracy integration: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to complete high accuracy integration']);
    }
}

function checkIntegrationStatus() {
    try {
        $userId = $_SESSION['user']['id'];
        $pdo = getPdo();
        
        // Check if user settings table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'user_settings'");
        $tableExists = $stmt->rowCount() > 0;
        
        // Check user settings
        $userSettings = [];
        if ($tableExists) {
            $stmt = $pdo->prepare("
                SELECT setting_key, setting_value 
                FROM user_settings 
                WHERE user_id = ? AND setting_key LIKE 'high_accuracy_%'
            ");
            $stmt->execute([$userId]);
            $userSettings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        }
        
        // Check if high accuracy files exist
        $files = [
            'facenet_high_accuracy_service.py',
            'facenet_quality_validator.py',
            'facenet_high_accuracy_api.php',
            'facenet_high_accuracy_cli.py',
            'integrate_high_accuracy_system.php',
            'integrate_high_accuracy_system.js',
            'integrate_high_accuracy_system.html'
        ];
        
        $fileStatus = [];
        foreach ($files as $file) {
            $fileStatus[$file] = file_exists(__DIR__ . '/' . $file);
        }
        
        // Check system requirements
        $requirements = [
            'php_curl' => extension_loaded('curl'),
            'python3' => checkPython3(),
            'opencv' => checkOpenCV(),
            'tensorflow' => checkTensorFlow()
        ];
        
        $overallStatus = $tableExists && 
                        $userSettings['high_accuracy_mode'] === '1' && 
                        array_reduce($fileStatus, function($carry, $item) { return $carry && $item; }, true);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'overall_status' => $overallStatus,
                'table_exists' => $tableExists,
                'user_settings' => $userSettings,
                'file_status' => $fileStatus,
                'requirements' => $requirements,
                'recommendations' => getIntegrationRecommendations($tableExists, $userSettings, $fileStatus, $requirements)
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Error checking integration status: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Failed to check integration status']);
    }
}

function getIntegrationInstructions() {
    $instructions = [
        'step1' => [
            'title' => 'Install Python Dependencies',
            'description' => 'Install required Python packages for high-accuracy face recognition',
            'commands' => [
                'pip install opencv-python',
                'pip install numpy',
                'pip install tensorflow',
                'pip install pillow'
            ]
        ],
        'step2' => [
            'title' => 'Verify File Structure',
            'description' => 'Ensure all high-accuracy system files are present',
            'files' => [
                'facenet_high_accuracy_service.py',
                'facenet_quality_validator.py',
                'facenet_high_accuracy_api.php',
                'facenet_high_accuracy_cli.py',
                'integrate_high_accuracy_system.php',
                'integrate_high_accuracy_system.js',
                'integrate_high_accuracy_system.html'
            ]
        ],
        'step3' => [
            'title' => 'Test System Components',
            'description' => 'Test each component to ensure proper functionality',
            'tests' => [
                'Test Python service: python3 facenet_high_accuracy_cli.py --action get_performance_stats',
                'Test API endpoint: curl -X POST http://localhost/facenet_high_accuracy_api.php',
                'Test integration: Access integrate_high_accuracy_system.html'
            ]
        ],
        'step4' => [
            'title' => 'Enable High Accuracy Mode',
            'description' => 'Enable high accuracy mode for users',
            'actions' => [
                'Access the integration page',
                'Toggle high accuracy mode on',
                'Test face recognition with high accuracy',
                'Verify 90% confidence threshold is enforced'
            ]
        ]
    ];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'instructions' => $instructions,
            'notes' => [
                'The high accuracy system requires 90% confidence threshold',
                'Quality validation ensures only high-quality images are processed',
                'Rate limiting prevents abuse with cooldown periods',
                'Multi-verification provides additional security',
                'Real-time feedback helps users achieve optimal results'
            ]
        ]
    ]);
}

function checkPython3() {
    $output = [];
    $returnCode = 0;
    exec('python3 --version 2>&1', $output, $returnCode);
    return $returnCode === 0;
}

function checkOpenCV() {
    $output = [];
    $returnCode = 0;
    exec('python3 -c "import cv2; print(cv2.__version__)" 2>&1', $output, $returnCode);
    return $returnCode === 0;
}

function checkTensorFlow() {
    $output = [];
    $returnCode = 0;
    exec('python3 -c "import tensorflow as tf; print(tf.__version__)" 2>&1', $output, $returnCode);
    return $returnCode === 0;
}

function getIntegrationRecommendations($tableExists, $userSettings, $fileStatus, $requirements) {
    $recommendations = [];
    
    if (!$tableExists) {
        $recommendations[] = 'Run the integration completion process to create user_settings table';
    }
    
    if (!isset($userSettings['high_accuracy_mode']) || $userSettings['high_accuracy_mode'] !== '1') {
        $recommendations[] = 'Enable high accuracy mode for the user';
    }
    
    foreach ($fileStatus as $file => $exists) {
        if (!$exists) {
            $recommendations[] = "Missing file: $file - ensure all high accuracy system files are present";
        }
    }
    
    if (!$requirements['php_curl']) {
        $recommendations[] = 'Enable PHP cURL extension for API communication';
    }
    
    if (!$requirements['python3']) {
        $recommendations[] = 'Install Python 3 for high accuracy processing';
    }
    
    if (!$requirements['opencv']) {
        $recommendations[] = 'Install OpenCV: pip install opencv-python';
    }
    
    if (!$requirements['tensorflow']) {
        $recommendations[] = 'Install TensorFlow: pip install tensorflow';
    }
    
    if (empty($recommendations)) {
        $recommendations[] = 'High accuracy system is ready for use';
    }
    
    return $recommendations;
}
?>
