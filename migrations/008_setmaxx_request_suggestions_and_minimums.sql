ALTER TABLE `setmaxx_requests`
  MODIFY `active_lock` tinyint(1) DEFAULT 1;

UPDATE `setmaxx_requests`
SET `active_lock` = NULL
WHERE `active_lock` = 0;

UPDATE `setmaxx_songs`
SET `tip_amount_cents` = 0
WHERE `tip_amount_cents` = 1000;

ALTER TABLE `setmaxx_songs`
  MODIFY `tip_amount_cents` int(10) unsigned NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `setmaxx_song_suggestions` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `gig_session_id` bigint(20) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `suggested_title` varchar(190) NOT NULL,
  `suggested_artist` varchar(190) DEFAULT NULL,
  `requester_name` varchar(190) DEFAULT NULL,
  `suggestion_note` varchar(255) DEFAULT NULL,
  `status` enum('new','reviewed','added','dismissed') NOT NULL DEFAULT 'new',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_setmaxx_suggestions_user` (`user_id`,`status`,`created_at`),
  KEY `idx_setmaxx_suggestions_session` (`gig_session_id`,`created_at`),
  CONSTRAINT `fk_setmaxx_suggestions_session` FOREIGN KEY (`gig_session_id`) REFERENCES `setmaxx_gig_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_setmaxx_suggestions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
