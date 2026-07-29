SET @add_commission_payout_type = (
  SELECT IF(
    COUNT(*) > 0 AND MAX(column_type) NOT LIKE '%commission%',
    'ALTER TABLE `finance_gig_payouts` MODIFY COLUMN `payout_type` enum(''band_member'',''advertising'',''commission'',''sound'',''lights'',''insurance'',''travel'',''other'') NOT NULL DEFAULT ''band_member''',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'finance_gig_payouts'
    AND column_name = 'payout_type'
);
PREPARE stmt FROM @add_commission_payout_type;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
