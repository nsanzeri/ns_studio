CREATE TABLE IF NOT EXISTS `finance_gigs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `calendar_id` int(10) unsigned DEFAULT NULL,
  `source_event_key` char(64) DEFAULT NULL,
  `title` varchar(255) NOT NULL,
  `venue_name` varchar(190) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `starts_at` datetime NOT NULL,
  `ends_at` datetime DEFAULT NULL,
  `guarantee_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `tips_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `is_taxable` tinyint(1) NOT NULL DEFAULT 1,
  `miles` decimal(8,1) NOT NULL DEFAULT 0.0,
  `notes` text DEFAULT NULL,
  `imported_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_gigs_source` (`user_id`,`calendar_id`,`source_event_key`),
  KEY `idx_finance_gigs_user_date` (`user_id`,`starts_at`),
  KEY `idx_finance_gigs_calendar` (`calendar_id`),
  CONSTRAINT `fk_finance_gigs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_finance_gigs_calendar` FOREIGN KEY (`calendar_id`) REFERENCES `calendars` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `finance_members` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `email` varchar(190) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_members_user_name` (`user_id`,`name`),
  KEY `idx_finance_members_user` (`user_id`,`is_active`,`name`),
  CONSTRAINT `fk_finance_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `finance_gig_payouts` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_id` bigint(20) unsigned NOT NULL,
  `member_id` bigint(20) unsigned NOT NULL,
  `payout_type` enum('band_member','advertising','sound','lights','insurance','travel','other') NOT NULL DEFAULT 'band_member',
  `amount_cents` int(10) unsigned NOT NULL DEFAULT 0,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_gig_payout_member_type` (`gig_id`,`member_id`,`payout_type`),
  KEY `idx_finance_gig_payouts_member` (`member_id`),
  CONSTRAINT `fk_finance_gig_payouts_gig` FOREIGN KEY (`gig_id`) REFERENCES `finance_gigs` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_finance_gig_payouts_member` FOREIGN KEY (`member_id`) REFERENCES `finance_members` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
