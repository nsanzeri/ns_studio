CREATE TABLE IF NOT EXISTS `setmaxx_songs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `artist` varchar(190) DEFAULT NULL,
  `release_year` smallint(5) unsigned DEFAULT NULL,
  `genre` varchar(120) DEFAULT NULL,
  `broad_genre` varchar(80) DEFAULT NULL,
  `is_prerecorded` tinyint(1) NOT NULL DEFAULT 0,
  `track_length_seconds` smallint(5) unsigned DEFAULT NULL,
  `is_medley` tinyint(1) NOT NULL DEFAULT 0,
  `medley_name` varchar(190) DEFAULT NULL,
  `opening_song` tinyint(1) NOT NULL DEFAULT 0,
  `vocal_difficulty` enum('easy','medium','hard') DEFAULT NULL,
  `song_key` varchar(24) DEFAULT NULL,
  `tempo_bpm` smallint(5) unsigned DEFAULT NULL,
  `family_friendly` tinyint(1) NOT NULL DEFAULT 1,
  `instrumental` tinyint(1) NOT NULL DEFAULT 0,
  `performance_notes` text DEFAULT NULL,
  `tip_amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `display_order` int(10) unsigned NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_setmaxx_songs_user` (`user_id`,`is_active`,`display_order`),
  CONSTRAINT `fk_setmaxx_songs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_gig_sessions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `venue_name` varchar(190) DEFAULT NULL,
  `session_slug` varchar(190) NOT NULL,
  `public_token` char(32) NOT NULL,
  `status` enum('draft','live','closed') NOT NULL DEFAULT 'draft',
  `venmo_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `starts_at` datetime DEFAULT NULL,
  `ends_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_session_slug` (`session_slug`),
  UNIQUE KEY `uq_setmaxx_public_token` (`public_token`),
  KEY `idx_setmaxx_sessions_user` (`user_id`,`status`,`created_at`),
  CONSTRAINT `fk_setmaxx_sessions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_public_links` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `public_token` char(32) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_public_links_user` (`user_id`),
  UNIQUE KEY `uq_setmaxx_public_links_token` (`public_token`),
  CONSTRAINT `fk_setmaxx_public_links_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_public_profiles` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `artist_name` varchar(190) DEFAULT NULL,
  `website_url` varchar(255) DEFAULT NULL,
  `review_url` varchar(255) DEFAULT NULL,
  `logo_path` varchar(255) DEFAULT NULL,
  `venmo_handle` varchar(80) DEFAULT NULL,
  `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `suggested_request_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10,
  `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_public_profiles_user` (`user_id`),
  CONSTRAINT `fk_setmaxx_public_profiles_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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

CREATE TABLE IF NOT EXISTS `setmaxx_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned DEFAULT NULL,
  `song_id` bigint(20) unsigned NOT NULL,
  `requester_name` varchar(190) DEFAULT NULL,
  `request_note` varchar(255) DEFAULT NULL,
  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('pending','queued','played','declined','canceled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(24) NOT NULL DEFAULT 'stripe',
  `active_lock` tinyint(1) DEFAULT 1,
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_one_song_per_session` (`gig_session_id`,`song_id`,`active_lock`),
  KEY `idx_setmaxx_requests_session` (`gig_session_id`,`status`,`created_at`),
  KEY `idx_setmaxx_requests_song` (`song_id`),
  CONSTRAINT `fk_setmaxx_requests_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_setmaxx_requests_song` FOREIGN KEY (`song_id`) REFERENCES `setmaxx_songs` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_song_suggestions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `suggested_title` varchar(190) NOT NULL,
  `suggested_artist` varchar(190) DEFAULT NULL,
  `requester_name` varchar(190) DEFAULT NULL,
  `suggestion_note` varchar(255) DEFAULT NULL,
  `status` enum('new','reviewed','added','dismissed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_setmaxx_suggestions_user` (`user_id`,`status`,`created_at`),
  KEY `idx_setmaxx_suggestions_session` (`gig_session_id`,`created_at`),
  CONSTRAINT `fk_setmaxx_suggestions_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_setmaxx_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_mailing_list_signups` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned DEFAULT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `email` varchar(190) NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `source` varchar(80) NOT NULL DEFAULT 'setmaxx_public_page',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_mailing_user_email` (`user_id`,`email`),
  KEY `idx_setmaxx_mailing_user_created` (`user_id`,`created_at`),
  KEY `idx_setmaxx_mailing_session` (`gig_session_id`,`created_at`),
  CONSTRAINT `fk_setmaxx_mailing_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_setmaxx_mailing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `setmaxx_general_tips` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `tipper_name` varchar(190) DEFAULT NULL,
  `tip_note` varchar(255) DEFAULT NULL,
  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('paid','refunded') NOT NULL DEFAULT 'paid',
  `payment_method` varchar(24) NOT NULL DEFAULT 'stripe',
  `stripe_payment_intent_id` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setmaxx_general_tips_pi` (`stripe_payment_intent_id`),
  KEY `idx_setmaxx_general_tips_user` (`user_id`,`created_at`),
  KEY `idx_setmaxx_general_tips_session` (`gig_session_id`,`created_at`),
  CONSTRAINT `fk_setmaxx_general_tips_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_setmaxx_general_tips_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
