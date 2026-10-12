SET @add_invoice_payable_to = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_payable_to` varchar(190) DEFAULT NULL AFTER `artist_name`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_payable_to'
);
PREPARE stmt FROM @add_invoice_payable_to;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_invoice_address = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_address` text DEFAULT NULL AFTER `invoice_payable_to`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_address'
);
PREPARE stmt FROM @add_invoice_address;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
