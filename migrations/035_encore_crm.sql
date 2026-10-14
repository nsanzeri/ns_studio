CREATE TABLE IF NOT EXISTS `encore_contacts` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int unsigned NOT NULL,
  `name` varchar(190) NOT NULL,
  `contact_name` varchar(190) DEFAULT NULL,
  `kind` varchar(80) NOT NULL DEFAULT 'Venue',
  `email` varchar(190) DEFAULT NULL,
  `phone` varchar(64) DEFAULT NULL,
  `website` varchar(255) DEFAULT NULL,
  `city` varchar(120) DEFAULT NULL,
  `stage` enum('researching','contacted','follow_up','conversation','date_offered','booked','not_a_fit','revisit') NOT NULL DEFAULT 'researching',
  `estimated_value` decimal(10,2) DEFAULT NULL,
  `fit_notes` text DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_encore_contacts_user_stage` (`user_id`,`stage`),
  CONSTRAINT `fk_encore_contacts_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `encore_interactions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `contact_id` bigint unsigned NOT NULL, `user_id` int unsigned NOT NULL,
  `channel` enum('email','text','phone','instagram','facebook','website','in_person','other') NOT NULL DEFAULT 'email',
  `direction` enum('outbound','inbound','internal') NOT NULL DEFAULT 'outbound', `occurred_at` datetime NOT NULL DEFAULT current_timestamp(),
  `subject` varchar(190) DEFAULT NULL, `summary` text NOT NULL, `next_step` varchar(255) DEFAULT NULL, `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`), KEY `idx_encore_interactions_contact` (`contact_id`,`occurred_at`),
  CONSTRAINT `fk_encore_interactions_contact` FOREIGN KEY (`contact_id`) REFERENCES `encore_contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_encore_interactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS `encore_tasks` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT, `contact_id` bigint unsigned NOT NULL, `user_id` int unsigned NOT NULL, `title` varchar(190) NOT NULL, `due_at` date NOT NULL,
  `status` enum('open','done','dismissed') NOT NULL DEFAULT 'open', `created_at` datetime NOT NULL DEFAULT current_timestamp(), `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`), KEY `idx_encore_tasks_user_due` (`user_id`,`status`,`due_at`),
  CONSTRAINT `fk_encore_tasks_contact` FOREIGN KEY (`contact_id`) REFERENCES `encore_contacts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_encore_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
