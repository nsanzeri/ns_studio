SET @add_merch_url = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `merch_url` varchar(255) DEFAULT NULL AFTER `website_url`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'merch_url'
);
PREPARE stmt FROM @add_merch_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
