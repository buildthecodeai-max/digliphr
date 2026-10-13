-- =============================================================================
-- Monitoring Agent Web-Link Tokens
-- Allows an employee logged into the web portal to authenticate the desktop
-- agent without entering a password (one-time 5-minute token exchange).
-- Safe to re-run (CREATE TABLE IF NOT EXISTS).
-- =============================================================================

CREATE TABLE IF NOT EXISTS `monitoring_agent_links` (
  `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `device_uid`  VARCHAR(120)    NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `token_hash`  CHAR(64)        NOT NULL,
  `expires_at`  DATETIME        NOT NULL,
  `used_at`     DATETIME        DEFAULT NULL,
  `created_at`  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_agent_link_device` (`device_uid`),
  KEY `idx_agent_link_employee` (`employee_id`),
  KEY `idx_agent_link_token` (`token_hash`),
  CONSTRAINT `fk_agent_link_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
