SET @add_main_gig_calendar = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `calendars` ADD COLUMN `is_main_gig` tinyint(1) NOT NULL DEFAULT 0 AFTER `is_default`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'calendars'
    AND column_name = 'is_main_gig'
);
PREPARE stmt FROM @add_main_gig_calendar;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
