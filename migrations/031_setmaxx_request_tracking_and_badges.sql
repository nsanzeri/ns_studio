SET @add_free_limit = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'free_request_limit') = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `free_request_limit` tinyint(3) unsigned NOT NULL DEFAULT 2 AFTER `price_step_dollars`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_free_limit;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_badge_1 = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'request_badge_1_dollars') = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `request_badge_1_dollars` tinyint(3) unsigned NOT NULL DEFAULT 5 AFTER `free_request_limit`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_badge_1;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_badge_2 = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'request_badge_2_dollars') = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `request_badge_2_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER `request_badge_1_dollars`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_badge_2;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_badge_3 = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'request_badge_3_dollars') = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `request_badge_3_dollars` tinyint(3) unsigned NOT NULL DEFAULT 20 AFTER `request_badge_2_dollars`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_badge_3;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_requester_identifier = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND column_name = 'requester_identifier') = 0,
    'ALTER TABLE `setmaxx_requests` ADD COLUMN `requester_identifier` char(64) DEFAULT NULL AFTER `requester_name`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_requester_identifier;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_requester_ip = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND column_name = 'requester_ip') = 0,
    'ALTER TABLE `setmaxx_requests` ADD COLUMN `requester_ip` varchar(45) DEFAULT NULL AFTER `requester_identifier`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_requester_ip;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_requester_user_agent = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND column_name = 'requester_user_agent') = 0,
    'ALTER TABLE `setmaxx_requests` ADD COLUMN `requester_user_agent` varchar(255) DEFAULT NULL AFTER `requester_ip`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_requester_user_agent;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_request_referrer = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND column_name = 'request_referrer') = 0,
    'ALTER TABLE `setmaxx_requests` ADD COLUMN `request_referrer` varchar(255) DEFAULT NULL AFTER `requester_user_agent`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_request_referrer;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_request_source_url = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND column_name = 'request_source_url') = 0,
    'ALTER TABLE `setmaxx_requests` ADD COLUMN `request_source_url` varchar(255) DEFAULT NULL AFTER `request_referrer`',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_request_source_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_requester_free_index = (
  SELECT IF(
    (SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = 'setmaxx_requests' AND index_name = 'idx_setmaxx_requests_requester_free') = 0,
    'CREATE INDEX `idx_setmaxx_requests_requester_free` ON `setmaxx_requests` (`requester_identifier`, `payment_method`, `created_at`)',
    'SELECT 1'
  )
);
PREPARE stmt FROM @add_requester_free_index;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
