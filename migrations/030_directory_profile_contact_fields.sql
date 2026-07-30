SET @add_directory_youtube_url = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `youtube_url` varchar(255) DEFAULT NULL AFTER `website_url`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'youtube_url'
);
PREPARE stmt FROM @add_directory_youtube_url;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_directory_contact_email = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `contact_email` varchar(190) DEFAULT NULL AFTER `youtube_url`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'contact_email'
);
PREPARE stmt FROM @add_directory_contact_email;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_directory_contact_phone = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `contact_phone` varchar(64) DEFAULT NULL AFTER `contact_email`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'contact_phone'
);
PREPARE stmt FROM @add_directory_contact_phone;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
