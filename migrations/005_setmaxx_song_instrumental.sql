ALTER TABLE `setmaxx_songs`
  ADD COLUMN `instrumental` tinyint(1) NOT NULL DEFAULT 0 AFTER `family_friendly`;
