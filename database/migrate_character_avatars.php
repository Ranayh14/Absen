<?php
/**
 * Database Migration Script
 * Creates character_avatars table
 */

require_once __DIR__ . '/../config.php';

try {
    $pdo = getPdo();
    
    // Create character_avatars table
    $sql = "CREATE TABLE IF NOT EXISTS `character_avatars` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `user_id` int(11) NOT NULL,
      `happy_avatar` longtext DEFAULT NULL COMMENT 'Base64 encoded happy character image',
      `sad_avatar` longtext DEFAULT NULL COMMENT 'Base64 encoded sad character image',
      `angry_avatar` longtext DEFAULT NULL COMMENT 'Base64 encoded angry character image',
      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
      `updated_at` timestamp NULL DEFAULT NULL ON UPDATE current_timestamp(),
      PRIMARY KEY (`id`),
      UNIQUE KEY `user_id` (`user_id`),
      FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores 3D character avatars with different emotions for each employee'";
    
    $pdo->exec($sql);
    
    echo "✓ Table character_avatars created successfully!\n";
    
    // Verify table exists
    $stmt = $pdo->query("SHOW TABLES LIKE 'character_avatars'");
    if ($stmt->rowCount() > 0) {
        echo "✓ Table verified in database\n";
        
        // Show table structure
        $stmt = $pdo->query("DESCRIBE character_avatars");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "\nTable structure:\n";
        foreach ($columns as $col) {
            echo "  - {$col['Field']} ({$col['Type']})\n";
        }
    }
    
} catch (PDOException $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
