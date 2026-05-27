ALTER TABLE `setmaxx_song_suggestions`
  MODIFY `gig_session_id` bigint(20) unsigned DEFAULT NULL;

ALTER TABLE `setmaxx_general_tips`
  MODIFY `gig_session_id` bigint(20) unsigned DEFAULT NULL;
