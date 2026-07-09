SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'directory_genres') = 0,
  'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `directory_genres` varchar(500) DEFAULT NULL AFTER `directory_show_songlist`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
  (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'setmaxx_public_profiles' AND column_name = 'directory_description') = 0,
  'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `directory_description` text DEFAULT NULL AFTER `directory_genres`',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
