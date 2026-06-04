SET @add_payout_type = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gig_payouts` ADD COLUMN `payout_type` enum(''band_member'',''advertising'',''sound'',''lights'',''insurance'',''travel'',''other'') NOT NULL DEFAULT ''band_member'' AFTER `member_id`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gig_payouts'
    AND column_name = 'payout_type'
);
PREPARE stmt FROM @add_payout_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_new_payout_unique = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gig_payouts` ADD UNIQUE KEY `uq_finance_gig_payout_member_type` (`gig_id`,`member_id`,`payout_type`)',
    'SELECT 1'
  )
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gig_payouts'
    AND index_name = 'uq_finance_gig_payout_member_type'
);
PREPARE stmt FROM @add_new_payout_unique;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_gig_fk_index = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gig_payouts` ADD KEY `idx_finance_gig_payouts_gig` (`gig_id`)',
    'SELECT 1'
  )
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gig_payouts'
    AND index_name = 'idx_finance_gig_payouts_gig'
);
PREPARE stmt FROM @add_gig_fk_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_old_payout_unique_after_new = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE `finance_gig_payouts` DROP INDEX `uq_finance_gig_payout_member`',
    'SELECT 1'
  )
  FROM information_schema.statistics
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gig_payouts'
    AND index_name = 'uq_finance_gig_payout_member'
);
PREPARE stmt FROM @drop_old_payout_unique_after_new;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @migrate_advertising_members = (
  SELECT IF(
    COUNT(*) > 0,
    'INSERT IGNORE INTO finance_members (user_id, name) SELECT DISTINCT user_id, ''Advertising'' FROM finance_gigs WHERE advertising_cents > 0',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'advertising_cents'
);
PREPARE stmt FROM @migrate_advertising_members;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @migrate_advertising_payouts = (
  SELECT IF(
    COUNT(*) > 0,
    'INSERT INTO finance_gig_payouts (gig_id, member_id, payout_type, amount_cents, notes) SELECT g.id, m.id, ''advertising'', g.advertising_cents, ''Migrated from gig advertising field'' FROM finance_gigs g JOIN finance_members m ON m.user_id = g.user_id AND m.name = ''Advertising'' WHERE g.advertising_cents > 0 ON DUPLICATE KEY UPDATE amount_cents = VALUES(amount_cents)',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'advertising_cents'
);
PREPARE stmt FROM @migrate_advertising_payouts;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @drop_advertising = (
  SELECT IF(
    COUNT(*) > 0,
    'ALTER TABLE `finance_gigs` DROP COLUMN `advertising_cents`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'advertising_cents'
);
PREPARE stmt FROM @drop_advertising;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
