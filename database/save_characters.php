<?php
/**
 * Save Updated 2D Characters with Hijab
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/character_functions.php';

echo "=== Saving Updated 2D Characters (with Hijab) ===\n\n";

// Updated character paths with hijab
$characterPaths = [
    'happy' => 'C:/Users/Rana/.gemini/antigravity/brain/a11a1222-3d90-4125-8674-1a31bfeb3619/rana_2d_happy_v2_1769667468498.png',
    'sad' => 'C:/Users/Rana/.gemini/antigravity/brain/a11a1222-3d90-4125-8674-1a31bfeb3619/rana_2d_sad_v2_1769667492396.png',
    'angry' => 'C:/Users/Rana/.gemini/antigravity/brain/a11a1222-3d90-4125-8674-1a31bfeb3619/rana_2d_angry_v2_1769667507999.png'
];

try {
    $pdo = getPdo();
    
    // Get user ID for Rana
    $stmt = $pdo->query("SELECT id FROM users WHERE nama LIKE '%Rana%' AND role='pegawai' LIMIT 1");
    $user = $stmt->fetch();
    
    if (!$user) {
        echo "Error: User 'Rana' not found\n";
        exit(1);
    }
    
    $userId = $user['id'];
    echo "User ID: $userId\n\n";
    
    $avatars = [];
    
    foreach ($characterPaths as $emotion => $path) {
        echo "Processing $emotion character (with hijab)...\n";
        
        if (!file_exists($path)) {
            echo "  ✗ File not found: $path\n";
            exit(1);
        }
        
        $imageData = file_get_contents($path);
        $imageInfo = getimagesize($path);
        $mimeType = $imageInfo['mime'];
        $base64 = base64_encode($imageData);
        $dataUrl = "data:$mimeType;base64,$base64";
        
        $avatars[$emotion] = $dataUrl;
        
        $sizeKB = round(strlen($imageData) / 1024, 2);
        echo "  ✓ Converted (Size: {$sizeKB} KB)\n";
    }
    
    echo "\nSaving to database...\n";
    
    if (saveCharacterAvatars($userId, $avatars)) {
        echo "✓ Updated characters saved successfully!\n\n";
        
        // Verify
        if (characterAvatarsExist($userId)) {
            echo "✓ Characters verified in database\n";
            
            $character = getEmployeeCharacter($userId);
            if ($character) {
                echo "✓ Character retrieval working\n";
                echo "✓ Characters now include hijab/kerudung\n";
            }
        }
    } else {
        echo "✗ Failed to save characters\n";
        exit(1);
    }
    
    echo "\n=== Success! ===\n";
    echo "Updated 2D characters with hijab are now in the database.\n";
    echo "The characters now accurately represent the employee.\n";
    echo "Refresh the dashboard to see the updated characters!\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
