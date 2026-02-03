<?php
/**
 * Direct Test of Character API
 */

// Start session
session_start();

// Include config
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/character_functions.php';

// Set headers
header('Content-Type: application/json');

try {
    // Check if user is logged in
    if (!isset($_SESSION['user'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Not logged in',
            'session_exists' => false
        ]);
        exit;
    }
    
    $userId = $_SESSION['user']['id'];
    $userName = $_SESSION['user']['nama'];
    
    // Check if character exists
    $exists = characterAvatarsExist($userId);
    
    if (!$exists) {
        echo json_encode([
            'success' => false,
            'error' => 'Character not found in database',
            'user_id' => $userId,
            'user_name' => $userName,
            'character_exists' => false
        ]);
        exit;
    }
    
    // Get character
    $character = getEmployeeCharacter($userId);
    $missingReports = getMissingReportsCount($userId);
    
    // Determine emotion
    $emotion = 'happy';
    if ($missingReports >= 6) {
        $emotion = 'angry';
    } elseif ($missingReports >= 1) {
        $emotion = 'sad';
    }
    
    echo json_encode([
        'success' => true,
        'user_id' => $userId,
        'user_name' => $userName,
        'character_exists' => true,
        'character_length' => strlen($character),
        'character' => $character,
        'emotion' => $emotion,
        'missing_reports' => $missingReports
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}
