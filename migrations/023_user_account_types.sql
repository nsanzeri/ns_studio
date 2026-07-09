SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'users' AND column_name = 'account_type') = 0,
  'ALTER TABLE `users` ADD COLUMN `account_type` enum(''customer'',''artist'',''admin'') NOT NULL DEFAULT ''customer'' AFTER `display_name`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles') = 1,
  'UPDATE `users` u JOIN `setmaxx_public_profiles` pp ON pp.user_id = u.id SET u.account_type = ''artist''',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'user_subscriptions') = 1,
  'UPDATE `users` u JOIN `user_subscriptions` us ON us.user_id = u.id SET u.account_type = ''artist'' WHERE us.status IN (''trialing'', ''active'')',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
