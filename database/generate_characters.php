<?php
/**
 * AI Character Generator
 * Generates 3D character avatars for all employees using AI
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/character_functions.php';

// Check if running from command line
$isCLI = php_sapi_name() === 'cli';

if (!$isCLI) {
    // If accessed via web, check admin permission
    session_start();
    if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
        die('Access denied. Admin only.');
    }
}

echo "=== AI Character Generator ===\n\n";

// Get user ID from command line or POST
$targetUserId = null;
if ($isCLI && isset($argv[1])) {
    $targetUserId = (int)$argv[1];
} elseif (isset($_POST['user_id'])) {
    $targetUserId = (int)$_POST['user_id'];
}

try {
    $pdo = getPdo();
    
    // Get users to generate characters for
    if ($targetUserId) {
        $stmt = $pdo->prepare("SELECT id, nama, email, foto_base64 FROM users WHERE id = ? AND role = 'pegawai'");
        $stmt->execute([$targetUserId]);
        $users = $stmt->fetchAll();
        echo "Generating character for user ID: $targetUserId\n\n";
    } else {
        $stmt = $pdo->query("SELECT id, nama, email, foto_base64 FROM users WHERE role = 'pegawai'");
        $users = $stmt->fetchAll();
        echo "Generating characters for all employees (" . count($users) . " users)\n\n";
    }
    
    if (empty($users)) {
        echo "No users found.\n";
        exit(0);
    }
    
    foreach ($users as $user) {
        echo "Processing: {$user['nama']} (ID: {$user['id']})\n";
        
        // Check if user has profile photo
        if (empty($user['foto_base64'])) {
            echo "  ⚠ No profile photo - skipping\n\n";
            continue;
        }
        
        // Save photo to temp file for AI processing
        $photoData = $user['foto_base64'];
        
        // Extract base64 data
        if (preg_match('/^data:image\/(\w+);base64,(.+)$/', $photoData, $matches)) {
            $imageType = $matches[1];
            $base64Data = $matches[2];
        } else {
            $base64Data = $photoData;
            $imageType = 'png';
        }
        
        $imageData = base64_decode($base64Data);
        
        // Save to temp file
        $tempDir = sys_get_temp_dir();
        $tempPhotoPath = $tempDir . '/profile_' . $user['id'] . '.' . $imageType;
        file_put_contents($tempPhotoPath, $imageData);
        
        echo "  → Saved profile photo to: $tempPhotoPath\n";
        
        // This is where we would call the AI generation
        // For now, we'll create a marker file that the generate_image tool will process
        $requestFile = __DIR__ . '/character_generation_request_' . $user['id'] . '.json';
        $requestData = [
            'user_id' => $user['id'],
            'user_name' => $user['nama'],
            'photo_path' => $tempPhotoPath,
            'status' => 'pending'
        ];
        
        file_put_contents($requestFile, json_encode($requestData, JSON_PRETTY_PRINT));
        
        echo "  → Created generation request: $requestFile\n";
        echo "  ✓ Ready for AI generation\n\n";
    }
    
    echo "\n=== Generation Requests Created ===\n";
    echo "Next step: Run the AI generation processor to create the actual characters.\n";
    echo "Command: php database/process_character_generation.php\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
