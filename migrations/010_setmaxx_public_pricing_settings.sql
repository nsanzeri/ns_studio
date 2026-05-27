ALTER TABLE `setmaxx_public_profiles`
  ADD COLUMN `minimum_tip_dollars` tinyint(3) unsigned NOT NULL DEFAULT 10 AFTER `logo_path`,
  ADD COLUMN `price_step_dollars` tinyint(3) unsigned NOT NULL DEFAULT 1 AFTER `minimum_tip_dollars`;
