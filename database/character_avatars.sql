-- Table for storing 3D character avatars for employees
-- Each employee has 3 avatar variations: happy, sad, angry

CREATE TABLE IF NOT EXISTS `character_avatars` (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores 3D character avatars with different emotions for each employee';
