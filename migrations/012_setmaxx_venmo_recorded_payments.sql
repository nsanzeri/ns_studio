ALTER TABLE `setmaxx_public_profiles`
  ADD COLUMN `venmo_handle` varchar(80) DEFAULT NULL AFTER `logo_path`;

ALTER TABLE `setmaxx_gig_sessions`
  ADD COLUMN `venmo_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `status`;

ALTER TABLE `setmaxx_requests`
  ADD COLUMN `payment_method` varchar(24) NOT NULL DEFAULT 'stripe' AFTER `status`;

ALTER TABLE `setmaxx_general_tips`
  ADD COLUMN `payment_method` varchar(24) NOT NULL DEFAULT 'stripe' AFTER `status`;
