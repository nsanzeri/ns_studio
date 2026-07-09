CREATE TABLE IF NOT EXISTS `booking_requests` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `requester_user_id` bigint(20) unsigned DEFAULT NULL,
  `requester_profile_id` bigint(20) unsigned DEFAULT NULL,
  `requester_type` enum('guest','user','artist','venue') NOT NULL DEFAULT 'guest',
  `contact_name` varchar(120) DEFAULT NULL,
  `contact_email` varchar(190) DEFAULT NULL,
  `contact_phone` varchar(64) DEFAULT NULL,
  `event_title` varchar(190) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `venue_name` varchar(190) DEFAULT NULL,
  `venue_address` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `state` varchar(64) DEFAULT NULL,
  `zip` char(5) DEFAULT NULL,
  `budget_min` decimal(10,2) DEFAULT NULL,
  `budget_max` decimal(10,2) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `auto_fallback` tinyint(1) NOT NULL DEFAULT 0,
  `status` enum('draft','open','fulfilled','cancelled') NOT NULL DEFAULT 'open',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_requester_user` (`requester_user_id`),
  KEY `idx_requester_profile` (`requester_profile_id`),
  KEY `idx_event_date` (`event_date`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_invites` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `target_user_id` bigint(20) unsigned DEFAULT NULL,
  `target_profile_id` bigint(20) unsigned DEFAULT NULL,
  `target_type` enum('artist','venue') NOT NULL,
  `priority` int(11) NOT NULL DEFAULT 1,
  `status` enum('pending','accepted','declined','expired','cancelled') NOT NULL DEFAULT 'pending',
  `sent_at` datetime NOT NULL DEFAULT current_timestamp(),
  `responded_at` datetime DEFAULT NULL,
  `quote_amount` decimal(10,2) DEFAULT NULL,
  `quote_message` text DEFAULT NULL,
  `decline_reason` varchar(190) DEFAULT NULL,
  `decline_message` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `decline_reason_id` bigint(20) unsigned DEFAULT NULL,
  `decline_note` text DEFAULT NULL,
  `declined_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_booking_invites_request_target_user` (`request_id`,`target_user_id`),
  KEY `idx_request` (`request_id`),
  KEY `idx_booking_invites_target_user` (`target_user_id`,`status`),
  KEY `idx_booking_invites_request_profile` (`request_id`,`target_profile_id`),
  KEY `idx_target` (`target_profile_id`,`status`),
  CONSTRAINT `fk_invite_request` FOREIGN KEY (`request_id`) REFERENCES `booking_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `booking_bids` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `request_id` bigint(20) unsigned NOT NULL,
  `invite_id` bigint(20) unsigned DEFAULT NULL,
  `bidder_user_id` bigint(20) unsigned NOT NULL,
  `bidder_profile_id` bigint(20) unsigned DEFAULT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('draft','sent','accepted','rejected','withdrawn') NOT NULL DEFAULT 'sent',
  `message` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_request` (`request_id`),
  KEY `idx_invite` (`invite_id`),
  KEY `idx_bidder_user` (`bidder_user_id`),
  CONSTRAINT `fk_bids_invite` FOREIGN KEY (`invite_id`) REFERENCES `booking_invites` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_bids_request` FOREIGN KEY (`request_id`) REFERENCES `booking_requests` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_invites' AND column_name = 'target_user_id') = 0,
  'ALTER TABLE `booking_invites` ADD COLUMN `target_user_id` bigint(20) unsigned DEFAULT NULL AFTER `request_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_invites' AND column_name = 'target_profile_id' AND is_nullable = 'NO') = 1,
  'ALTER TABLE `booking_invites` MODIFY COLUMN `target_profile_id` bigint(20) unsigned DEFAULT NULL',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'booking_invites' AND index_name = 'idx_booking_invites_target_user') = 0,
  'CREATE INDEX `idx_booking_invites_target_user` ON `booking_invites` (`target_user_id`, `status`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'booking_invites' AND index_name = 'idx_booking_invites_request_profile') = 0,
  'CREATE INDEX `idx_booking_invites_request_profile` ON `booking_invites` (`request_id`, `target_profile_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'booking_invites' AND index_name = 'uniq_booking_invites_request_target_user') = 0,
  'CREATE UNIQUE INDEX `uniq_booking_invites_request_target_user` ON `booking_invites` (`request_id`, `target_user_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
