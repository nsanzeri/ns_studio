CREATE TABLE IF NOT EXISTS `setmaxx_songs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `title` varchar(190) NOT NULL,
  `artist` varchar(190) DEFAULT NULL,
  `release_year` smallint(5) unsigned DEFAULT NULL,
  `genre` varchar(120) DEFAULT NULL,
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
  `tip_amount_cents` int(10) unsigned NOT NULL DEFAULT 1000,
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

CREATE TABLE IF NOT EXISTS `setmaxx_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned NOT NULL,
  `song_id` bigint(20) unsigned NOT NULL,
  `requester_name` varchar(190) DEFAULT NULL,
  `request_note` varchar(255) DEFAULT NULL,
  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `status` enum('pending','queued','played','declined','canceled') NOT NULL DEFAULT 'pending',
  `active_lock` tinyint(1) NOT NULL DEFAULT 1,
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
