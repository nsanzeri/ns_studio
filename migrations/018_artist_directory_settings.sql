ALTER TABLE `setmaxx_public_profiles`
  ADD COLUMN `directory_visible` tinyint(1) NOT NULL DEFAULT 1 AFTER `user_id`,
  ADD COLUMN `directory_state` char(2) DEFAULT NULL AFTER `directory_visible`;

CREATE INDEX `idx_setmaxx_directory` ON `setmaxx_public_profiles` (`directory_visible`, `directory_state`, `artist_name`);
