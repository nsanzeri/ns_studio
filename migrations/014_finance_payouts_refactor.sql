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
  `payout_type` enum('band_member','advertising','commission','sound','lights','insurance','travel','other') NOT NULL DEFAULT 'band_member',
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

SET @add_is_taxable = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gigs` ADD COLUMN `is_taxable` tinyint(1) NOT NULL DEFAULT 1 AFTER `tips_cents`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'is_taxable'
);
PREPARE stmt FROM @add_is_taxable;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_tax_withheld = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE `finance_gigs` DROP COLUMN `tax_withheld_cents`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'tax_withheld_cents'
);
PREPARE stmt FROM @drop_tax_withheld;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_split_member_count = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE `finance_gigs` DROP COLUMN `split_member_count`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'split_member_count'
);
PREPARE stmt FROM @drop_split_member_count;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
