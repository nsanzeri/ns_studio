CREATE TABLE IF NOT EXISTS `setmaxx_favorite_setlists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `criteria_json` longtext DEFAULT NULL,
  `sets_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_setmaxx_favorite_setlists_user` (`user_id`,`updated_at`),
  CONSTRAINT `fk_setmaxx_favorite_setlists_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
