ALTER TABLE `setmaxx_requests`
  DROP INDEX `uq_setmaxx_one_song_per_session`,
  ADD INDEX `idx_setmaxx_requests_session_song` (`gig_session_id`, `song_id`, `status`);
