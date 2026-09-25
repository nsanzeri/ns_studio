SET @add_directory_country = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `directory_country` char(2) DEFAULT NULL AFTER `directory_visible`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'directory_country'
);
PREPARE stmt FROM @add_directory_country;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_directory_region = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `directory_region` varchar(80) DEFAULT NULL AFTER `directory_country`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'directory_region'
);
PREPARE stmt FROM @add_directory_region;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `setmaxx_public_profiles`
SET `directory_region` = `directory_state`
WHERE (`directory_region` IS NULL OR `directory_region` = '')
  AND `directory_state` IS NOT NULL
  AND `directory_state` <> '';
