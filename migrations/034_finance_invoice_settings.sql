CREATE TABLE IF NOT EXISTS `finance_invoice_settings` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `invoice_payable_to` varchar(190) DEFAULT NULL,
  `invoice_address` text DEFAULT NULL,
  `invoice_accept_check` tinyint(1) NOT NULL DEFAULT 1,
  `invoice_accept_cash` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_accept_zelle` tinyint(1) NOT NULL DEFAULT 0,
  `invoice_zelle_details` varchar(190) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_finance_invoice_settings_user` (`user_id`),
  CONSTRAINT `fk_finance_invoice_settings_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `finance_invoice_settings` (
  `user_id`,
  `invoice_payable_to`,
  `invoice_address`,
  `invoice_accept_check`,
  `invoice_accept_cash`,
  `invoice_accept_zelle`,
  `invoice_zelle_details`
)
SELECT
  `user_id`,
  `invoice_payable_to`,
  `invoice_address`,
  COALESCE(`invoice_accept_check`, 1),
  COALESCE(`invoice_accept_cash`, 0),
  COALESCE(`invoice_accept_zelle`, 0),
  `invoice_zelle_details`
FROM `setmaxx_public_profiles`
WHERE `invoice_payable_to` IS NOT NULL
   OR `invoice_address` IS NOT NULL
   OR COALESCE(`invoice_accept_cash`, 0) = 1
   OR COALESCE(`invoice_accept_zelle`, 0) = 1
   OR `invoice_zelle_details` IS NOT NULL
ON DUPLICATE KEY UPDATE
  `invoice_payable_to` = VALUES(`invoice_payable_to`),
  `invoice_address` = VALUES(`invoice_address`),
  `invoice_accept_check` = VALUES(`invoice_accept_check`),
  `invoice_accept_cash` = VALUES(`invoice_accept_cash`),
  `invoice_accept_zelle` = VALUES(`invoice_accept_zelle`),
  `invoice_zelle_details` = VALUES(`invoice_zelle_details`);
