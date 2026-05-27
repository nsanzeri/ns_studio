CREATE TABLE IF NOT EXISTS `setmaxx_public_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `review_url` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
  CONSTRAINT `fk_setmaxx_public_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_general_tips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tipper_name` varchar(190) DEFAULT NULL,
  `tip_note` varchar(255) DEFAULT NULL,
  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_general_tips_pi` (`stripe_payment_intent_id`),
  KEY `idx_setmaxx_general_tips_user` (`user_id`,`created_at`),
  KEY `idx_setmaxx_general_tips_session` (`gig_session_id`,`created_at`),
  CONSTRAINT `fk_setmaxx_general_tips_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_setmaxx_general_tips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
