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

SET @add_invoice_accept_check = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_accept_check` tinyint(1) NOT NULL DEFAULT 1 AFTER `invoice_address`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_accept_check'
);
PREPARE stmt FROM @add_invoice_accept_check;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_invoice_accept_cash = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_accept_cash` tinyint(1) NOT NULL DEFAULT 0 AFTER `invoice_accept_check`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_accept_cash'
);
PREPARE stmt FROM @add_invoice_accept_cash;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_invoice_accept_zelle = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_accept_zelle` tinyint(1) NOT NULL DEFAULT 0 AFTER `invoice_accept_cash`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_accept_zelle'
);
PREPARE stmt FROM @add_invoice_accept_zelle;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @add_invoice_zelle_details = (
  SELECT IF(
    COUNT(*) = 0,
    'ALTER TABLE `setmaxx_public_profiles` ADD COLUMN `invoice_zelle_details` varchar(190) DEFAULT NULL AFTER `invoice_accept_zelle`',
    'SELECT 1'
  )
  FROM information_schema.columns
  WHERE table_schema = DATABASE()
    AND table_name = 'setmaxx_public_profiles'
    AND column_name = 'invoice_zelle_details'
);
PREPARE stmt FROM @add_invoice_zelle_details;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
