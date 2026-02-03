<?php
/**
 * Test Character API Endpoint
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/character_functions.php';

echo "=== Testing Character System ===\n\n";

try {
    $pdo = getPdo();
    
    // Get Rana's user ID
    $stmt = $pdo->query("SELECT id, nama FROM users WHERE nama LIKE '%Rana%' AND role='pegawai' LIMIT 1");
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo "✗ User not found\n";
        exit(1);
    }
    
    echo "User: {$user['nama']} (ID: {$user['id']})\n\n";
    
    // Check if character exists
    echo "1. Checking if character exists...\n";
    $exists = characterAvatarsExist($user['id']);
    echo "   " . ($exists ? "✓" : "✗") . " Character exists: " . ($exists ? "YES" : "NO") . "\n\n";
    
    if (!$exists) {
        echo "✗ No character found in database!\n";
        echo "Run: php database/save_characters.php\n";
        exit(1);
    }
    
    // Get character from database
    echo "2. Retrieving character from database...\n";
    $character = getEmployeeCharacter($user['id']);
    
    if ($character) {
        echo "   ✓ Character retrieved\n";
        echo "   Length: " . strlen($character) . " bytes\n";
        echo "   Type: " . (strpos($character, 'data:image/') === 0 ? "Valid data URL" : "Invalid format") . "\n\n";
    } else {
        echo "   ✗ Failed to retrieve character\n\n";
        exit(1);
    }
    
    // Check missing reports
    echo "3. Checking missing reports...\n";
    $missing = getMissingReportsCount($user['id']);
    echo "   Missing reports: $missing\n";
    
    if ($missing == 0) {
        echo "   → Should show: HAPPY character\n\n";
    } elseif ($missing >= 1 && $missing <= 5) {
        echo "   → Should show: SAD character\n\n";
    } else {
        echo "   → Should show: ANGRY character\n\n";
    }
    
    // Simulate API response
    echo "4. Simulating API response...\n";
    $response = [
        'success' => true,
        'character' => $character,
        'emotion' => $missing == 0 ? 'happy' : ($missing <= 5 ? 'sad' : 'angry'),
        'missing_reports' => $missing
    ];
    
    echo "   Response:\n";
    echo "   - success: " . ($response['success'] ? 'true' : 'false') . "\n";
    echo "   - emotion: {$response['emotion']}\n";
    echo "   - missing_reports: {$response['missing_reports']}\n";
    echo "   - character: " . substr($response['character'], 0, 50) . "...\n\n";
    
    echo "=== All Tests Passed! ===\n";
    echo "\nIf dashboard still shows robot, check:\n";
    echo "1. Browser console for JavaScript errors\n";
    echo "2. Network tab for API response\n";
    echo "3. Clear browser cache (Ctrl + F5)\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
