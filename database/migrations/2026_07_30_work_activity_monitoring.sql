-- =============================================================================
-- Work Activity Monitoring (Mode 2: Activity + Periodic Screenshots)
-- Safe to re-run (CREATE IF NOT EXISTS + INSERT IGNORE)
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Permissions
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`) VALUES
('monitoring', 'Access work monitoring', 'monitoring.access'),
('monitoring', 'View monitoring overview', 'monitoring.view_overview'),
('monitoring', 'View live employees', 'monitoring.view_live_employees'),
('monitoring', 'View own activity', 'monitoring.view_own_activity'),
('monitoring', 'View team activity', 'monitoring.view_team_activity'),
('monitoring', 'View all activity', 'monitoring.view_all_activity'),
('monitoring', 'View applications', 'monitoring.view_applications'),
('monitoring', 'View screenshots', 'monitoring.view_screenshots'),
('monitoring', 'Download screenshots', 'monitoring.download_screenshots'),
('monitoring', 'Delete screenshots', 'monitoring.delete_screenshots'),
('monitoring', 'Manage devices', 'monitoring.manage_devices'),
('monitoring', 'Manage policies', 'monitoring.manage_policies'),
('monitoring', 'Assign monitoring policies', 'monitoring.assign_policies'),
('monitoring', 'Stop monitoring session', 'monitoring.stop_session'),
('monitoring', 'View monitoring reports', 'monitoring.view_reports'),
('monitoring', 'View monitoring audit logs', 'monitoring.view_audit_logs'),
('monitoring', 'View monitoring storage', 'monitoring.view_storage'),
('monitoring', 'Enable exceptional recording', 'monitoring.enable_exceptional_recording'),
('monitoring', 'View recordings', 'monitoring.view_recordings'),
('monitoring', 'Download recordings', 'monitoring.download_recordings'),
('monitoring', 'Delete recordings', 'monitoring.delete_recordings'),
('monitoring', 'Manage recording policy', 'monitoring.manage_recording_policy'),
('monitoring', 'View recording audit', 'monitoring.view_recording_audit');

-- Role defaults: Mode 2 perms only for ordinary managers (no exceptional recording)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'super_admin'
  AND p.module = 'monitoring';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'company_admin'
  AND p.module = 'monitoring'
  AND p.slug NOT IN (
    'monitoring.enable_exceptional_recording',
    'monitoring.view_recordings',
    'monitoring.download_recordings',
    'monitoring.delete_recordings',
    'monitoring.manage_recording_policy',
    'monitoring.view_recording_audit'
  );

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'hr_manager'
  AND p.slug IN (
    'monitoring.access', 'monitoring.view_overview', 'monitoring.view_live_employees',
    'monitoring.view_team_activity', 'monitoring.view_all_activity', 'monitoring.view_applications',
    'monitoring.view_screenshots', 'monitoring.download_screenshots', 'monitoring.delete_screenshots',
    'monitoring.manage_devices', 'monitoring.manage_policies', 'monitoring.assign_policies',
    'monitoring.stop_session', 'monitoring.view_reports', 'monitoring.view_audit_logs',
    'monitoring.view_storage'
  );

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'department_manager'
  AND p.slug IN (
    'monitoring.access', 'monitoring.view_overview', 'monitoring.view_live_employees',
    'monitoring.view_team_activity', 'monitoring.view_applications',
    'monitoring.view_screenshots', 'monitoring.view_reports'
  );

-- Employee role intentionally receives NO monitoring.* permissions (admin-only portal).

-- -----------------------------------------------------------------------------
-- Policies
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `version` INT UNSIGNED NOT NULL DEFAULT 1,
  `mode` ENUM('activity_only','activity_screenshots','exceptional_recording') NOT NULL DEFAULT 'activity_screenshots',
  `screenshot_enabled` TINYINT(1) NOT NULL DEFAULT 1,
  `screenshot_interval_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
  `min_interval_minutes` INT UNSIGNED NOT NULL DEFAULT 5,
  `recording_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `track_applications` TINYINT(1) NOT NULL DEFAULT 1,
  `track_window_titles` TINYINT(1) NOT NULL DEFAULT 1,
  `mask_window_titles` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_employee_view_screenshots` TINYINT(1) NOT NULL DEFAULT 0,
  `require_acknowledgement` TINYINT(1) NOT NULL DEFAULT 1,
  `notice_title` VARCHAR(255) NOT NULL DEFAULT 'Work Activity Monitoring',
  `notice_text` TEXT DEFAULT NULL,
  `activity_retention_days` INT UNSIGNED NOT NULL DEFAULT 90,
  `screenshot_retention_days` INT UNSIGNED NOT NULL DEFAULT 30,
  `excluded_apps` JSON DEFAULT NULL,
  `masked_apps` JSON DEFAULT NULL,
  `black_screenshot_apps` JSON DEFAULT NULL,
  `pause_screenshot_apps` JSON DEFAULT NULL,
  `apply_changes_immediately` TINYINT(1) NOT NULL DEFAULT 0,
  `is_system_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_policies_uuid` (`uuid`),
  KEY `idx_monitoring_policies_company` (`company_id`),
  KEY `idx_monitoring_policies_active` (`is_active`),
  CONSTRAINT `fk_monitoring_policies_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_policies_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_policy_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `policy_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `starts_at` DATETIME DEFAULT NULL,
  `ends_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mpa_policy` (`policy_id`),
  KEY `idx_mpa_company` (`company_id`),
  KEY `idx_mpa_branch` (`branch_id`),
  KEY `idx_mpa_department` (`department_id`),
  KEY `idx_mpa_employee` (`employee_id`),
  KEY `idx_mpa_active` (`is_active`),
  CONSTRAINT `fk_mpa_policy` FOREIGN KEY (`policy_id`) REFERENCES `monitoring_policies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpa_assigned_by` FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_policy_acknowledgements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `policy_id` BIGINT UNSIGNED NOT NULL,
  `policy_version` INT UNSIGNED NOT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `accepted` TINYINT(1) NOT NULL DEFAULT 1,
  `acknowledged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_mpack_emp_policy_ver` (`employee_id`, `policy_id`, `policy_version`),
  KEY `idx_mpack_policy` (`policy_id`),
  CONSTRAINT `fk_mpack_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mpack_policy` FOREIGN KEY (`policy_id`) REFERENCES `monitoring_policies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Devices
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `device_uid` VARCHAR(120) NOT NULL,
  `hostname` VARCHAR(255) DEFAULT NULL,
  `os_name` VARCHAR(100) DEFAULT NULL,
  `os_version` VARCHAR(100) DEFAULT NULL,
  `agent_version` VARCHAR(50) DEFAULT NULL,
  `status` ENUM('pending','approved','revoked','blocked') NOT NULL DEFAULT 'pending',
  `access_token_hash` CHAR(64) DEFAULT NULL,
  `access_token_expires_at` DATETIME DEFAULT NULL,
  `last_seen_at` DATETIME DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `revoked_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_devices_uuid` (`uuid`),
  UNIQUE KEY `uk_monitoring_devices_uid` (`device_uid`),
  KEY `idx_monitoring_devices_employee` (`employee_id`),
  KEY `idx_monitoring_devices_company` (`company_id`),
  KEY `idx_monitoring_devices_status` (`status`),
  KEY `idx_monitoring_devices_token` (`access_token_hash`),
  CONSTRAINT `fk_monitoring_devices_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_monitoring_devices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Sessions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_sessions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `policy_id` BIGINT UNSIGNED NOT NULL,
  `policy_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `mode` ENUM('activity_only','activity_screenshots','exceptional_recording') NOT NULL DEFAULT 'activity_screenshots',
  `screenshot_interval_minutes` INT UNSIGNED NOT NULL DEFAULT 10,
  `status` ENUM('pending','active','paused','offline','stopping','completed','failed','cancelled') NOT NULL DEFAULT 'pending',
  `session_token_hash` CHAR(64) DEFAULT NULL,
  `token_expires_at` DATETIME DEFAULT NULL,
  `token_revoked_at` DATETIME DEFAULT NULL,
  `started_at` DATETIME DEFAULT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `last_heartbeat_at` DATETIME DEFAULT NULL,
  `stop_reason` VARCHAR(100) DEFAULT NULL,
  `remote_stop_requested` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_sessions_uuid` (`uuid`),
  UNIQUE KEY `uk_monitoring_sessions_attendance` (`attendance_id`),
  KEY `idx_monitoring_sessions_employee` (`employee_id`),
  KEY `idx_monitoring_sessions_company` (`company_id`),
  KEY `idx_monitoring_sessions_status` (`status`),
  KEY `idx_monitoring_sessions_device` (`device_id`),
  KEY `idx_monitoring_sessions_token` (`session_token_hash`),
  CONSTRAINT `fk_ms_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ms_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ms_attendance` FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_ms_device` FOREIGN KEY (`device_id`) REFERENCES `monitoring_devices` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_ms_policy` FOREIGN KEY (`policy_id`) REFERENCES `monitoring_policies` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Activity segments
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_activity_segments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `application_name` VARCHAR(255) DEFAULT NULL,
  `process_name` VARCHAR(255) DEFAULT NULL,
  `window_title` VARCHAR(500) DEFAULT NULL,
  `window_title_masked` TINYINT(1) NOT NULL DEFAULT 0,
  `started_at` DATETIME NOT NULL,
  `ended_at` DATETIME DEFAULT NULL,
  `duration_seconds` INT UNSIGNED NOT NULL DEFAULT 0,
  `activity_status` ENUM('active','idle','locked','disconnected') NOT NULL DEFAULT 'active',
  `client_segment_id` VARCHAR(64) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mas_session` (`session_id`),
  KEY `idx_mas_employee` (`employee_id`),
  KEY `idx_mas_company` (`company_id`),
  KEY `idx_mas_started` (`started_at`),
  KEY `idx_mas_app` (`application_name`),
  UNIQUE KEY `uk_mas_client_segment` (`session_id`, `client_segment_id`),
  CONSTRAINT `fk_mas_session` FOREIGN KEY (`session_id`) REFERENCES `monitoring_sessions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mas_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mas_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Screenshots
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_screenshots` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `capture_id` CHAR(64) NOT NULL,
  `session_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `attendance_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `captured_at` DATETIME NOT NULL,
  `interval_minutes` INT UNSIGNED DEFAULT NULL,
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `original_size` BIGINT UNSIGNED DEFAULT NULL,
  `compressed_size` BIGINT UNSIGNED DEFAULT NULL,
  `storage_path` VARCHAR(500) DEFAULT NULL,
  `thumbnail_path` VARCHAR(500) DEFAULT NULL,
  `checksum` CHAR(64) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT 'image/jpeg',
  `upload_status` ENUM('pending','authorized','uploading','uploaded','failed','deleted') NOT NULL DEFAULT 'pending',
  `encryption_status` VARCHAR(50) DEFAULT 'none',
  `scan_status` VARCHAR(50) DEFAULT 'pending',
  `active_application` VARCHAR(255) DEFAULT NULL,
  `retention_expires_at` DATETIME DEFAULT NULL,
  `deleted_at` DATETIME DEFAULT NULL,
  `viewed_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_screenshots_uuid` (`uuid`),
  UNIQUE KEY `uk_monitoring_screenshots_capture` (`capture_id`),
  KEY `idx_mscreenshots_session` (`session_id`),
  KEY `idx_mscreenshots_employee` (`employee_id`),
  KEY `idx_mscreenshots_company` (`company_id`),
  KEY `idx_mscreenshots_captured` (`captured_at`),
  KEY `idx_mscreenshots_upload` (`upload_status`),
  KEY `idx_mscreenshots_retention` (`retention_expires_at`),
  CONSTRAINT `fk_mscreenshots_session` FOREIGN KEY (`session_id`) REFERENCES `monitoring_sessions` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mscreenshots_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_mscreenshots_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Heartbeats / Alerts / Storage / Agent versions
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `monitoring_heartbeats` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `session_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(50) DEFAULT NULL,
  `agent_version` VARCHAR(50) DEFAULT NULL,
  `idle_seconds` INT UNSIGNED DEFAULT NULL,
  `active_app` VARCHAR(255) DEFAULT NULL,
  `payload` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_mhb_session` (`session_id`),
  KEY `idx_mhb_device` (`device_id`),
  KEY `idx_mhb_created` (`created_at`),
  CONSTRAINT `fk_mhb_device` FOREIGN KEY (`device_id`) REFERENCES `monitoring_devices` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_alerts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `session_id` BIGINT UNSIGNED DEFAULT NULL,
  `alert_type` VARCHAR(100) NOT NULL,
  `severity` ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT DEFAULT NULL,
  `requires_employee_action` TINYINT(1) NOT NULL DEFAULT 0,
  `is_resolved` TINYINT(1) NOT NULL DEFAULT 0,
  `resolved_at` DATETIME DEFAULT NULL,
  `resolved_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_malerts_company` (`company_id`),
  KEY `idx_malerts_employee` (`employee_id`),
  KEY `idx_malerts_type` (`alert_type`),
  KEY `idx_malerts_resolved` (`is_resolved`),
  CONSTRAINT `fk_malerts_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_storage_usage` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `used_bytes` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `screenshot_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `quota_bytes` BIGINT UNSIGNED DEFAULT NULL,
  `last_calculated_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_storage_company` (`company_id`),
  CONSTRAINT `fk_mstorage_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `monitoring_agent_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `version` VARCHAR(50) NOT NULL,
  `platform` VARCHAR(50) NOT NULL DEFAULT 'windows',
  `download_url` VARCHAR(500) DEFAULT NULL,
  `release_notes` TEXT DEFAULT NULL,
  `is_mandatory` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `released_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_monitoring_agent_versions` (`platform`, `version`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- System default policy (Mode 2, interval 10, recording disabled, notice+ack)
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `monitoring_policies` (
  `id`, `uuid`, `company_id`, `name`, `slug`, `version`, `mode`,
  `screenshot_enabled`, `screenshot_interval_minutes`, `min_interval_minutes`,
  `recording_enabled`, `track_applications`, `track_window_titles`, `mask_window_titles`,
  `allow_employee_view_screenshots`, `require_acknowledgement`, `notice_title`, `notice_text`,
  `activity_retention_days`, `screenshot_retention_days`,
  `excluded_apps`, `masked_apps`, `is_system_default`, `is_active`
) VALUES (
  1,
  '00000000-0000-4000-8000-000000000001',
  NULL,
  'Default Activity + Screenshots',
  'system-default-activity-screenshots',
  1,
  'activity_screenshots',
  1, 10, 5,
  0, 1, 1, 1,
  0, 1,
  'Work Activity Monitoring',
  'Your company has enabled work activity monitoring during checked-in working hours.\n\nThis includes:\n- Active application usage\n- Active and idle time\n- Periodic screenshots\n- Device connectivity status\n\nDefault screenshot interval: 10 minutes\n\nMonitoring starts after check-in and stops after checkout.\nScreenshots are captured silently. A quiet tray indicator shows Monitoring Active while you are checked in.\nAuthorized managers may review activity and screenshots according to company policy.\nActivity metadata is retained for about 90 days; screenshots for about 30 days unless your company configures otherwise.\nExcluded applications and breaks follow the assigned monitoring policy.\nPolicy acknowledgement is completed once in the desktop monitoring agent (not the employee web portal).',
  90, 30,
  JSON_ARRAY('1Password', 'LastPass', 'Bitwarden', 'KeePass'),
  JSON_ARRAY('1Password', 'LastPass', 'Bitwarden', 'KeePass', 'Banking'),
  1, 1
);

INSERT IGNORE INTO `monitoring_agent_versions` (`version`, `platform`, `release_notes`, `is_mandatory`, `is_active`, `released_at`)
VALUES ('0.1.0', 'windows', 'Initial Windows agent scaffold for Mode 2 monitoring.', 0, 1, NOW());
