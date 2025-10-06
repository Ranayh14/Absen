<?php
/**
 * Integrate Enhanced FaceNet into Existing System
 * 
 * This script integrates the enhanced FaceNet system into the existing
 * attendance system, providing backward compatibility while adding
 * advanced facial feature analysis capabilities.
 */

// Include the main index.php to get all existing functions
require_once 'index.php';

// Enhanced FaceNet Integration Functions
class EnhancedFaceNetIntegration {
    
    private $useEnhancedMode = true;
    private $fallbackToStandard = true;
    private $debugMode = false;
    
    public function __construct($useEnhanced = true, $fallback = true, $debug = false) {
        $this->useEnhancedMode = $useEnhanced;
        $this->fallbackToStandard = $fallback;
        $this->debugMode = $debug;
    }
    
    /**
     * Process attendance with enhanced FaceNet, with fallback to standard
     */
    public function processAttendance($base64Image, $threshold = 1.0) {
        if ($this->useEnhancedMode) {
            try {
                // Try enhanced FaceNet first
                $result = $this->processEnhancedAttendance($base64Image, $threshold);
                
                if ($result && $result['recognized']) {
                    if ($this->debugMode) {
                        error_log("Enhanced FaceNet recognition successful: " . $result['nama']);
                    }
                    return $result;
                }
                
                // If enhanced fails and fallback is enabled, try standard
                if ($this->fallbackToStandard) {
                    if ($this->debugMode) {
                        error_log("Enhanced FaceNet failed, falling back to standard");
                    }
                    return $this->processStandardAttendance($base64Image, $threshold);
                }
                
                return $result;
                
            } catch (Exception $e) {
                error_log("Enhanced FaceNet error: " . $e->getMessage());
                
                if ($this->fallbackToStandard) {
                    return $this->processStandardAttendance($base64Image, $threshold);
                }
                
                return [
                    'recognized' => false,
                    'message' => 'Enhanced recognition failed: ' . $e->getMessage()
                ];
            }
        } else {
            // Use standard FaceNet
            return $this->processStandardAttendance($base64Image, $threshold);
        }
    }
    
    /**
     * Process attendance using enhanced FaceNet
     */
    private function processEnhancedAttendance($base64Image, $threshold) {
        $data = [
            'action' => 'process_enhanced_attendance',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        return null;
    }
    
    /**
     * Process attendance using standard FaceNet
     */
    private function processStandardAttendance($base64Image, $threshold) {
        $data = [
            'action' => 'process_attendance',
            'image' => $base64Image,
            'threshold' => $threshold
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        return null;
    }
    
    /**
     * Generate face embedding with enhanced features
     */
    public function generateFaceEmbedding($base64Image, $userId = null) {
        if ($this->useEnhancedMode) {
            try {
                // Try enhanced embedding generation
                $result = $this->generateEnhancedEmbedding($base64Image, $userId);
                
                if ($result) {
                    return $result;
                }
                
                // Fallback to standard if enabled
                if ($this->fallbackToStandard) {
                    return $this->generateStandardEmbedding($base64Image, $userId);
                }
                
                return null;
                
            } catch (Exception $e) {
                error_log("Enhanced embedding generation error: " . $e->getMessage());
                
                if ($this->fallbackToStandard) {
                    return $this->generateStandardEmbedding($base64Image, $userId);
                }
                
                return null;
            }
        } else {
            return $this->generateStandardEmbedding($base64Image, $userId);
        }
    }
    
    /**
     * Generate enhanced embedding
     */
    private function generateEnhancedEmbedding($base64Image, $userId) {
        $data = [
            'action' => 'save_enhanced_embedding',
            'image' => $base64Image
        ];
        
        if ($userId) {
            $data['user_id'] = $userId;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_enhanced_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        return null;
    }
    
    /**
     * Generate standard embedding
     */
    private function generateStandardEmbedding($base64Image, $userId) {
        $data = [
            'action' => 'save_embedding',
            'image' => $base64Image
        ];
        
        if ($userId) {
            $data['user_id'] = $userId;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'http://localhost/facenet_api.php');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $result = json_decode($response, true);
            if ($result && $result['success']) {
                return $result['data'];
            }
        }
        
        return null;
    }
    
    /**
     * Get system statistics
     */
    public function getSystemStats() {
        $stats = [
            'enhanced_mode' => $this->useEnhancedMode,
            'fallback_enabled' => $this->fallbackToStandard,
            'debug_mode' => $this->debugMode
        ];
        
        // Get database stats
        try {
            $pdo = getPdo();
            
            // Total users
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pegawai'");
            $stats['total_users'] = $stmt->fetchColumn();
            
            // Users with standard embeddings
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pegawai' AND face_embedding IS NOT NULL");
            $stats['users_with_standard_embeddings'] = $stmt->fetchColumn();
            
            // Users with enhanced features
            $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pegawai' AND advanced_features IS NOT NULL");
            $stats['users_with_enhanced_features'] = $stmt->fetchColumn();
            
            // Coverage percentages
            $stats['standard_coverage'] = $stats['total_users'] > 0 ? 
                ($stats['users_with_standard_embeddings'] / $stats['total_users'] * 100) : 0;
            $stats['enhanced_coverage'] = $stats['total_users'] > 0 ? 
                ($stats['users_with_enhanced_features'] / $stats['total_users'] * 100) : 0;
            
        } catch (Exception $e) {
            error_log("Error getting system stats: " . $e->getMessage());
            $stats['error'] = $e->getMessage();
        }
        
        return $stats;
    }
    
    /**
     * Migrate users from standard to enhanced embeddings
     */
    public function migrateToEnhanced($limit = 10) {
        try {
            $pdo = getPdo();
            
            // Get users with standard embeddings but no enhanced features
            $stmt = $pdo->prepare("
                SELECT id, nim, nama, foto_base64 
                FROM users 
                WHERE role = 'pegawai' 
                AND face_embedding IS NOT NULL 
                AND advanced_features IS NULL 
                LIMIT ?
            ");
            $stmt->execute([$limit]);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $migrated = 0;
            $errors = 0;
            
            foreach ($users as $user) {
                if ($user['foto_base64']) {
                    try {
                        $result = $this->generateEnhancedEmbedding($user['foto_base64'], $user['id']);
                        if ($result) {
                            $migrated++;
                            if ($this->debugMode) {
                                error_log("Migrated user: " . $user['nama']);
                            }
                        } else {
                            $errors++;
                        }
                    } catch (Exception $e) {
                        error_log("Migration error for user {$user['id']}: " . $e->getMessage());
                        $errors++;
                    }
                }
            }
            
            return [
                'migrated' => $migrated,
                'errors' => $errors,
                'total_processed' => count($users)
            ];
            
        } catch (Exception $e) {
            error_log("Migration error: " . $e->getMessage());
            return [
                'migrated' => 0,
                'errors' => 1,
                'total_processed' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Toggle enhanced mode
     */
    public function toggleEnhancedMode() {
        $this->useEnhancedMode = !$this->useEnhancedMode;
        return $this->useEnhancedMode;
    }
    
    /**
     * Set fallback mode
     */
    public function setFallbackMode($enabled) {
        $this->fallbackToStandard = $enabled;
        return $this->fallbackToStandard;
    }
    
    /**
     * Set debug mode
     */
    public function setDebugMode($enabled) {
        $this->debugMode = $enabled;
        return $this->debugMode;
    }
}

// Global instance
$enhancedFaceNet = new EnhancedFaceNetIntegration(true, true, false);

// Enhanced AJAX endpoints
if (isset($_GET['ajax'])) {
    $action = $_GET['ajax'];
    
    // Enhanced attendance processing
    if ($action === 'process_attendance_enhanced' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $base64Image = $_POST['image'] ?? '';
        $threshold = isset($_POST['threshold']) ? (float)$_POST['threshold'] : 1.0;
        
        if (empty($base64Image)) {
            jsonResponse(['error' => 'Image is required'], 400);
        }
        
        $result = $enhancedFaceNet->processAttendance($base64Image, $threshold);
        
        if ($result) {
            jsonResponse(['ok' => true, 'data' => $result]);
        } else {
            jsonResponse(['error' => 'Attendance processing failed'], 500);
        }
    }
    
    // Enhanced embedding generation
    if ($action === 'generate_face_embedding_enhanced' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_SESSION['user'])) jsonResponse(['error' => 'Unauthorized'], 401);
        
        $base64Image = $_POST['image'] ?? '';
        if (empty($base64Image)) {
            jsonResponse(['error' => 'Image is required'], 400);
        }
        
        $result = $enhancedFaceNet->generateFaceEmbedding($base64Image, $_SESSION['user']['id']);
        
        if ($result) {
            jsonResponse(['ok' => true, 'message' => 'Enhanced face embedding generated successfully']);
        } else {
            jsonResponse(['error' => 'Failed to generate enhanced face embedding'], 500);
        }
    }
    
    // System statistics
    if ($action === 'get_enhanced_stats' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $stats = $enhancedFaceNet->getSystemStats();
        jsonResponse(['ok' => true, 'data' => $stats]);
    }
    
    // Migration
    if ($action === 'migrate_to_enhanced' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
        
        $limit = isset($_POST['limit']) ? (int)$_POST['limit'] : 10;
        $result = $enhancedFaceNet->migrateToEnhanced($limit);
        
        jsonResponse(['ok' => true, 'data' => $result]);
    }
    
    // Toggle enhanced mode
    if ($action === 'toggle_enhanced_mode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
        
        $enabled = $enhancedFaceNet->toggleEnhancedMode();
        jsonResponse(['ok' => true, 'data' => ['enabled' => $enabled]]);
    }
    
    // Set fallback mode
    if ($action === 'set_fallback_mode' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isAdmin()) jsonResponse(['error' => 'Admin access required'], 403);
        
        $enabled = isset($_POST['enabled']) ? (bool)$_POST['enabled'] : true;
        $result = $enhancedFaceNet->setFallbackMode($enabled);
        
        jsonResponse(['ok' => true, 'data' => ['enabled' => $result]]);
    }
}

// Helper function to get enhanced FaceNet instance
function getEnhancedFaceNet() {
    global $enhancedFaceNet;
    return $enhancedFaceNet;
}

// Override existing functions to use enhanced mode
function processAttendanceWithFaceNet($base64Image) {
    global $enhancedFaceNet;
    return $enhancedFaceNet->processAttendance($base64Image);
}

function generateFaceEmbedding($base64Image) {
    global $enhancedFaceNet;
    return $enhancedFaceNet->generateFaceEmbedding($base64Image);
}
?>
