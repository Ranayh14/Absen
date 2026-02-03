<?php
/**
 * Test Script for Character Avatar System
 * Run this script to verify character generation and display logic
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/character_functions.php';

echo "=== Character Avatar System Test ===\n\n";

// Test 1: Check if character_avatars table exists
echo "Test 1: Checking database table...\n";
try {
    $pdo = getPdo();
    $stmt = $pdo->query("SHOW TABLES LIKE 'character_avatars'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table 'character_avatars' exists\n";
    } else {
        echo "✗ Table 'character_avatars' NOT found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Get a test user
echo "\nTest 2: Getting test user...\n";
$stmt = $pdo->query("SELECT id, nama, email, foto_base64 FROM users WHERE role='pegawai' LIMIT 1");
$testUser = $stmt->fetch();

if (!$testUser) {
    echo "✗ No employee users found in database\n";
    exit(1);
}

echo "✓ Found test user: {$testUser['nama']} (ID: {$testUser['id']})\n";

// Test 3: Generate placeholder avatars
echo "\nTest 3: Generating placeholder avatars...\n";
$photoBase64 = $testUser['foto_base64'] ?? '';

if (!$photoBase64) {
    echo "⚠ User has no profile photo, using default\n";
    $photoBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
}

$avatars = generatePlaceholderAvatars($photoBase64);

echo "✓ Generated avatars:\n";
echo "  - Happy: " . substr($avatars['happy'], 0, 50) . "...\n";
echo "  - Sad: " . substr($avatars['sad'], 0, 50) . "...\n";
echo "  - Angry: " . substr($avatars['angry'], 0, 50) . "...\n";

// Test 4: Save avatars to database
echo "\nTest 4: Saving avatars to database...\n";
if (saveCharacterAvatars($testUser['id'], $avatars)) {
    echo "✓ Avatars saved successfully\n";
} else {
    echo "✗ Failed to save avatars\n";
    exit(1);
}

// Test 5: Check if avatars exist
echo "\nTest 5: Verifying avatars exist...\n";
if (characterAvatarsExist($testUser['id'])) {
    echo "✓ Avatars exist in database\n";
} else {
    echo "✗ Avatars not found\n";
    exit(1);
}

// Test 6: Get missing reports count
echo "\nTest 6: Checking missing reports count...\n";
$missingCount = getMissingReportsCount($testUser['id']);
echo "✓ Missing reports: $missingCount\n";

// Test 7: Get appropriate character
echo "\nTest 7: Getting appropriate character based on reports...\n";
$character = getEmployeeCharacter($testUser['id']);

if ($character) {
    echo "✓ Character retrieved: " . substr($character, 0, 50) . "...\n";
    
    // Determine emotion
    if ($missingCount == 0) {
        echo "  Emotion: HAPPY (all reports complete)\n";
    } elseif ($missingCount >= 1 && $missingCount <= 5) {
        echo "  Emotion: SAD ($missingCount missing reports)\n";
    } else {
        echo "  Emotion: ANGRY ($missingCount missing reports)\n";
    }
} else {
    echo "✗ Failed to retrieve character\n";
    exit(1);
}

// Test 8: Verify all database records
echo "\nTest 8: Verifying database records...\n";
$stmt = $pdo->prepare("SELECT * FROM character_avatars WHERE user_id = ?");
$stmt->execute([$testUser['id']]);
$record = $stmt->fetch();

if ($record) {
    echo "✓ Database record found:\n";
    echo "  - ID: {$record['id']}\n";
    echo "  - User ID: {$record['user_id']}\n";
    echo "  - Happy Avatar: " . (strlen($record['happy_avatar']) > 0 ? "✓" : "✗") . "\n";
    echo "  - Sad Avatar: " . (strlen($record['sad_avatar']) > 0 ? "✓" : "✗") . "\n";
    echo "  - Angry Avatar: " . (strlen($record['angry_avatar']) > 0 ? "✓" : "✗") . "\n";
    echo "  - Created: {$record['created_at']}\n";
} else {
    echo "✗ No database record found\n";
    exit(1);
}

echo "\n=== All Tests Passed! ===\n";
echo "\nNext steps:\n";
echo "1. Login to the employee dashboard at http://localhost/Magang/Absen/\n";
echo "2. Look for the character avatar in the hero banner (top section)\n";
echo "3. The character should change based on your daily report completion:\n";
echo "   - 😊 Happy: All reports complete\n";
echo "   - 😔 Sad: 1-5 missing reports\n";
echo "   - 😠 Angry: 6+ missing reports\n";
