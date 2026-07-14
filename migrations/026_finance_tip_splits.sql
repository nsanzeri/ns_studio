SET @add_cash_tips = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gigs` ADD COLUMN `cash_tips_cents` int(10) unsigned NOT NULL DEFAULT 0 AFTER `tips_cents`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'cash_tips_cents'
);
PREPARE stmt FROM @add_cash_tips;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_platform_tips = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `finance_gigs` ADD COLUMN `platform_tips_cents` int(10) unsigned NOT NULL DEFAULT 0 AFTER `cash_tips_cents`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gigs'
    AND column_name = 'platform_tips_cents'
);
PREPARE stmt FROM @add_platform_tips;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

UPDATE `finance_gigs`
SET `cash_tips_cents` = `tips_cents`,
    `platform_tips_cents` = 0
WHERE `tips_cents` > 0
  AND `cash_tips_cents` = 0
  AND `platform_tips_cents` IN (0, `tips_cents`);
