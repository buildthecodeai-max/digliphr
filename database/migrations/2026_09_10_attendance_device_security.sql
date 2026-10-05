-- Secure, server-issued attendance device binding and check-in security audit.

CREATE TABLE IF NOT EXISTS `employee_attendance_devices` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `device_identifier_hash` CHAR(64) NOT NULL,
  `device_name` VARCHAR(191) NOT NULL,
  `browser` VARCHAR(100) DEFAULT NULL,
  `operating_system` VARCHAR(100) DEFAULT NULL,
  `registered_ip` VARCHAR(45) DEFAULT NULL,
  `last_ip` VARCHAR(45) DEFAULT NULL,
  `first_registered_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen_at` DATETIME DEFAULT NULL,
  `status` ENUM('pending','approved','rejected','revoked') NOT NULL DEFAULT 'pending',
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `revoked_by` BIGINT UNSIGNED DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `revocation_reason` VARCHAR(500) DEFAULT NULL,
  `approved_employee_id` BIGINT UNSIGNED GENERATED ALWAYS AS (IF(`status` = 'approved', `employee_id`, NULL)) STORED,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_device_token_hash` (`device_identifier_hash`),
  UNIQUE KEY `uk_attendance_device_one_approved` (`approved_employee_id`),
  KEY `idx_attendance_devices_employee_status` (`employee_id`, `status`),
  KEY `idx_attendance_devices_company_status` (`company_id`, `status`),
  CONSTRAINT `fk_attendance_devices_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_attendance_devices_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_attendance_devices_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_devices_reviewed_by` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_devices_revoked_by` FOREIGN KEY (`revoked_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_security_events` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `device_id` BIGINT UNSIGNED DEFAULT NULL,
  `event_type` VARCHAR(80) NOT NULL,
  `device_identifier_hash` CHAR(64) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `result` ENUM('success','blocked','pending','approved','rejected','revoked') NOT NULL,
  `failure_reason` VARCHAR(500) DEFAULT NULL,
  `metadata` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_security_employee_time` (`employee_id`, `created_at`),
  KEY `idx_attendance_security_company_event` (`company_id`, `event_type`),
  KEY `idx_attendance_security_device` (`device_id`),
  CONSTRAINT `fk_attendance_security_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`),
  CONSTRAINT `fk_attendance_security_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  CONSTRAINT `fk_attendance_security_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_security_device` FOREIGN KEY (`device_id`) REFERENCES `employee_attendance_devices` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `description`) VALUES
('attendance', 'View attendance devices', 'attendance.device.view', 'View employee attendance device status and history'),
('attendance', 'Manage attendance devices', 'attendance.device.manage', 'Reset or revoke approved attendance devices'),
('attendance', 'Approve attendance devices', 'attendance.device.approve', 'Approve or reject pending attendance devices'),
('attendance', 'Manage attendance security settings', 'attendance.security.settings', 'Configure attendance device and network restrictions');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager')
  AND p.slug IN ('attendance.device.view', 'attendance.device.manage', 'attendance.device.approve', 'attendance.security.settings');

INSERT INTO `system_settings` (`company_id`, `group_name`, `setting_key`, `setting_value`, `value_type`, `description`)
SELECT c.id, 'attendance_security', defaults.setting_key, defaults.setting_value, defaults.value_type, defaults.description
FROM `companies` c
CROSS JOIN (
  SELECT 'attendance_security_mode' setting_key, 'device_only' setting_value, 'string' value_type, 'Controls check-in security: disabled, device_only, ip_only, or device_and_ip.' description
  UNION ALL SELECT 'attendance_device_registration_policy', 'auto_first', 'string', 'auto_first approves the first check-in device; admin_approval requires approval before first use.'
  UNION ALL SELECT 'attendance_device_change_requires_approval', '1', 'boolean', 'Require an administrator to approve replacement attendance devices.'
  UNION ALL SELECT 'attendance_ip_source', 'office', 'string', 'Choose office or approved IP list when IP validation is enabled.'
  UNION ALL SELECT 'attendance_office_ip_addresses', '', 'text', 'Allowed office public IP addresses or CIDR ranges, separated by commas or new lines.'
  UNION ALL SELECT 'attendance_approved_ip_addresses', '', 'text', 'Additional approved IP addresses or CIDR ranges, separated by commas or new lines.'
  UNION ALL SELECT 'attendance_log_failed_attempts', '1', 'boolean', 'Record blocked attendance security attempts.'
) defaults
WHERE NOT EXISTS (
  SELECT 1 FROM `system_settings` s WHERE s.company_id = c.id AND s.setting_key = defaults.setting_key
);
