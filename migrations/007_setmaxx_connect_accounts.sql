CREATE TABLE IF NOT EXISTS `setmaxx_connect_accounts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `stripe_account_id` varchar(255) NOT NULL,
  `charges_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `payouts_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `details_submitted` tinyint(1) NOT NULL DEFAULT 0,
  `onboarding_completed` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_connect_user` (`user_id`),
  UNIQUE KEY `uq_setmaxx_connect_account` (`stripe_account_id`),
  CONSTRAINT `fk_setmaxx_connect_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
