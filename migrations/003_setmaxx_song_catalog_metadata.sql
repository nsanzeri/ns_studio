ALTER TABLE `setmaxx_songs`
  ADD COLUMN `release_year` smallint(5) unsigned DEFAULT NULL AFTER `artist`,
  ADD COLUMN `genre` varchar(120) DEFAULT NULL AFTER `release_year`,
  ADD COLUMN `is_prerecorded` tinyint(1) NOT NULL DEFAULT 0 AFTER `genre`,
  ADD COLUMN `track_length_seconds` smallint(5) unsigned DEFAULT NULL AFTER `is_prerecorded`,
  ADD COLUMN `is_medley` tinyint(1) NOT NULL DEFAULT 0 AFTER `track_length_seconds`,
  ADD COLUMN `medley_name` varchar(190) DEFAULT NULL AFTER `is_medley`,
  ADD COLUMN `opening_song` tinyint(1) NOT NULL DEFAULT 0 AFTER `medley_name`,
  ADD COLUMN `vocal_difficulty` enum('easy','medium','hard') DEFAULT NULL AFTER `opening_song`,
  ADD COLUMN `song_key` varchar(24) DEFAULT NULL AFTER `vocal_difficulty`,
  ADD COLUMN `tempo_bpm` smallint(5) unsigned DEFAULT NULL AFTER `song_key`,
  ADD COLUMN `family_friendly` tinyint(1) NOT NULL DEFAULT 1 AFTER `tempo_bpm`,
  ADD COLUMN `performance_notes` text DEFAULT NULL AFTER `family_friendly`;
