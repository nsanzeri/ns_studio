SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_requests' AND column_name = 'hired_invite_id') = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `hired_invite_id` bigint(20) unsigned DEFAULT NULL AFTER `status`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_requests' AND column_name = 'closed_note') = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `closed_note` text DEFAULT NULL AFTER `hired_invite_id`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'booking_requests' AND column_name = 'closed_at') = 0,
  'ALTER TABLE `booking_requests` ADD COLUMN `closed_at` datetime DEFAULT NULL AFTER `closed_note`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'booking_requests' AND index_name = 'idx_booking_requests_hired_invite') = 0,
  'CREATE INDEX `idx_booking_requests_hired_invite` ON `booking_requests` (`hired_invite_id`)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
