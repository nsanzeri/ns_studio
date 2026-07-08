ALTER TABLE `setmaxx_public_profiles`
  ADD COLUMN `directory_show_song_count` tinyint(1) NOT NULL DEFAULT 1 AFTER `directory_state`,
  ADD COLUMN `directory_show_songlist` tinyint(1) NOT NULL DEFAULT 0 AFTER `directory_show_song_count`;
