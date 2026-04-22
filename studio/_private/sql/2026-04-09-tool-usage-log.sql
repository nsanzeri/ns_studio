CREATE TABLE IF NOT EXISTS `tool_usage_log` (
  `id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(10) UNSIGNED DEFAULT NULL,
  `user_email` VARCHAR(190) DEFAULT NULL,
  `feature_key` VARCHAR(64) NOT NULL,
  `action_key` VARCHAR(64) DEFAULT NULL,
  `calendar_count` INT(10) UNSIGNED DEFAULT NULL,
  `input_json` LONGTEXT DEFAULT NULL,
  `result_count` INT(10) UNSIGNED DEFAULT NULL,
  `status` ENUM('success','blocked','error') NOT NULL DEFAULT 'success',
  `note` VARCHAR(255) DEFAULT NULL,
  `ip` VARBINARY(16) DEFAULT NULL,
  `user_agent` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tool_usage_feature_created` (`feature_key`,`created_at`),
  KEY `idx_tool_usage_user_created` (`user_id`,`created_at`),
  KEY `idx_tool_usage_email_created` (`user_email`,`created_at`),
  KEY `idx_tool_usage_status_created` (`status`,`created_at`),
  CONSTRAINT `fk_tool_usage_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
