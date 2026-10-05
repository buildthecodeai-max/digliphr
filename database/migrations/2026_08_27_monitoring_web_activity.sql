-- Browser/domain monitoring, work schedules, distraction controls, disputes,
-- and explicit access logging. Domains only: full URLs and page contents are
-- intentionally not stored.

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`) VALUES
('monitoring', 'View website history', 'monitoring.view_site_history'),
('monitoring', 'Manage website rules', 'monitoring.manage_site_rules'),
('monitoring', 'Manage work schedules', 'monitoring.manage_schedules'),
('monitoring', 'View website reports', 'monitoring.view_site_reports'),
('monitoring', 'Review monitoring disputes', 'monitoring.review_disputes');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager')
  AND p.slug IN ('monitoring.view_site_history', 'monitoring.manage_site_rules',
                 'monitoring.manage_schedules', 'monitoring.view_site_reports',
                 'monitoring.review_disputes');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug = 'department_manager'
  AND p.slug IN ('monitoring.view_site_history', 'monitoring.view_site_reports');

CREATE TABLE IF NOT EXISTS `monitoring_site_rules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `domain_pattern` VARCHAR(190) NOT NULL,
  `category` ENUM('productive','neutral','distracting','blocked') NOT NULL DEFAULT 'neutral',
  `action` ENUM('allow','notify','warn','block') NOT NULL DEFAULT 'allow',
  `threshold_seconds` INT UNSIGNED NOT NULL DEFAULT 300,
  `context` ENUM('any','training','research') NOT NULL DEFAULT 'any',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_site_rule` (`company_id`, `domain_pattern`, `context`),
  KEY `idx_monitoring_site_rules_category` (`company_id`, `category`, `is_active`),
  CONSTRAINT `fk_monitoring_site_rules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_rules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monitoring_site_rules_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_site_exceptions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `domain_pattern` VARCHAR(190) NOT NULL,
  `override_category` ENUM('productive','neutral') NOT NULL DEFAULT 'productive',
  `reason` VARCHAR(500) NOT NULL,
  `status` ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `starts_at` DATETIME DEFAULT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monitoring_site_exceptions_employee` (`company_id`, `employee_id`, `status`),
  CONSTRAINT `fk_monitoring_site_exceptions_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_exceptions_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_exceptions_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monitoring_site_exceptions_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_work_schedules` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `timezone` VARCHAR(80) NOT NULL DEFAULT 'Asia/Karachi',
  `work_days` JSON NOT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `break_windows` JSON DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monitoring_work_schedules_company` (`company_id`, `is_active`),
  CONSTRAINT `fk_monitoring_work_schedules_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_work_schedules_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monitoring_work_schedules_updated_by` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_schedule_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `schedule_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `starts_at` DATETIME DEFAULT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_schedule_employee` (`schedule_id`, `employee_id`),
  KEY `idx_monitoring_schedule_assignments_company` (`company_id`, `employee_id`, `is_active`),
  CONSTRAINT `fk_monitoring_schedule_assignments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_schedule_assignments_schedule` FOREIGN KEY (`schedule_id`) REFERENCES `monitoring_work_schedules` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_schedule_assignments_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_schedule_assignments_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_site_activity` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `session_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `domain` VARCHAR(190) NOT NULL,
  `category` ENUM('productive','neutral','distracting','blocked') NOT NULL DEFAULT 'neutral',
  `policy_action` ENUM('allow','notify','warn','block') NOT NULL DEFAULT 'allow',
  `context` ENUM('any','training','research') NOT NULL DEFAULT 'any',
  `work_state` ENUM('working','break','off_hours','unscheduled') NOT NULL DEFAULT 'working',
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `source_segment_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_site_activity_source` (`session_id`, `source_segment_id`),
  KEY `idx_monitoring_site_activity_employee_time` (`company_id`, `employee_id`, `started_at`),
  KEY `idx_monitoring_site_activity_domain` (`company_id`, `domain`, `category`),
  CONSTRAINT `fk_monitoring_site_activity_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_activity_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_activity_session` FOREIGN KEY (`session_id`) REFERENCES `monitoring_sessions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_site_activity_device` FOREIGN KEY (`device_id`) REFERENCES `monitoring_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_distraction_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `site_activity_id` BIGINT UNSIGNED NOT NULL,
  `domain` VARCHAR(190) NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  `action_taken` ENUM('notified','warned','blocked') NOT NULL DEFAULT 'warned',
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `message` VARCHAR(500) NOT NULL,
  `employee_note` TEXT DEFAULT NULL,
  `status` ENUM('open','acknowledged','resolved') NOT NULL DEFAULT 'open',
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  `resolved_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monitoring_distraction_events_employee` (`company_id`, `employee_id`, `created_at`),
  KEY `idx_monitoring_distraction_events_status` (`company_id`, `status`),
  CONSTRAINT `fk_monitoring_distraction_events_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_distraction_events_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_distraction_events_activity` FOREIGN KEY (`site_activity_id`) REFERENCES `monitoring_site_activity` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_distraction_events_resolved_by` FOREIGN KEY (`resolved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_disputes` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `distraction_event_id` BIGINT UNSIGNED NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('open','accepted','rejected') NOT NULL DEFAULT 'open',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `resolution_note` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_dispute_event` (`distraction_event_id`),
  KEY `idx_monitoring_disputes_company_status` (`company_id`, `status`),
  CONSTRAINT `fk_monitoring_disputes_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_disputes_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_disputes_event` FOREIGN KEY (`distraction_event_id`) REFERENCES `monitoring_distraction_events` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_disputes_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_access_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `actor_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `resource_type` VARCHAR(100) NOT NULL,
  `resource_id` BIGINT UNSIGNED DEFAULT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `filters` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_monitoring_access_logs_company_time` (`company_id`, `created_at`),
  KEY `idx_monitoring_access_logs_actor` (`actor_user_id`, `created_at`),
  CONSTRAINT `fk_monitoring_access_logs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monitoring_access_logs_actor` FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_monitoring_access_logs_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
