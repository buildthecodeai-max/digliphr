-- =============================================================================
-- Employee Management, Attendance, Leave, HR & Payroll System
-- MySQL 8 Schema (InnoDB, utf8mb4 / utf8mb4_unicode_ci)
-- =============================================================================

SET NAMES utf8mb4;
SET CHARACTER SET utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';
SET time_zone = '+00:00';

CREATE DATABASE IF NOT EXISTS `employee_management`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `employee_management`;

-- =============================================================================
-- AUTH & ACCESS CONTROL
-- =============================================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(191) NOT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `avatar` VARCHAR(500) DEFAULT NULL,
  `email_verified_at` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `is_super_admin` TINYINT(1) NOT NULL DEFAULT 0,
  `last_login_at` DATETIME DEFAULT NULL,
  `last_login_ip` VARCHAR(45) DEFAULT NULL,
  `password_changed_at` DATETIME DEFAULT NULL,
  `force_password_reset` TINYINT(1) NOT NULL DEFAULT 0,
  `two_factor_secret` VARCHAR(255) DEFAULT NULL,
  `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `locale` VARCHAR(10) NOT NULL DEFAULT 'en',
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_users_uuid` (`uuid`),
  UNIQUE KEY `uk_users_email` (`email`),
  UNIQUE KEY `uk_users_username` (`username`),
  KEY `idx_users_is_active` (`is_active`),
  KEY `idx_users_deleted_at` (`deleted_at`),
  KEY `idx_users_name` (`name`),
  KEY `idx_users_last_login_at` (`last_login_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) NOT NULL,
  `token` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `used_at` DATETIME DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_resets_email` (`email`),
  KEY `idx_password_resets_token` (`token`(191)),
  KEY `idx_password_resets_expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `remember_tokens` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `selector` VARCHAR(64) NOT NULL,
  `token_hash` VARCHAR(255) NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `last_used_at` DATETIME DEFAULT NULL,
  `revoked_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_remember_tokens_selector` (`selector`),
  KEY `idx_remember_tokens_user_id` (`user_id`),
  KEY `idx_remember_tokens_expires_at` (`expires_at`),
  CONSTRAINT `fk_remember_tokens_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(191) DEFAULT NULL,
  `username` VARCHAR(100) DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `was_successful` TINYINT(1) NOT NULL DEFAULT 0,
  `failure_reason` VARCHAR(255) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_login_attempts_email` (`email`),
  KEY `idx_login_attempts_ip_address` (`ip_address`),
  KEY `idx_login_attempts_attempted_at` (`attempted_at`),
  KEY `idx_login_attempts_user_id` (`user_id`),
  KEY `idx_login_attempts_ip_time` (`ip_address`, `attempted_at`),
  CONSTRAINT `fk_login_attempts_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_roles_company_slug` (`company_id`, `slug`),
  KEY `idx_roles_slug` (`slug`),
  KEY `idx_roles_is_active` (`is_active`),
  KEY `idx_roles_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `module` VARCHAR(100) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_permissions_slug` (`slug`),
  KEY `idx_permissions_module` (`module`),
  KEY `idx_permissions_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `permission_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_permissions` (`role_id`, `permission_id`),
  KEY `idx_role_permissions_permission_id` (`permission_id`),
  CONSTRAINT `fk_role_permissions_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_role_permissions_permission`
    FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `user_roles` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_roles` (`user_id`, `role_id`, `company_id`),
  KEY `idx_user_roles_role_id` (`role_id`),
  KEY `idx_user_roles_company_id` (`company_id`),
  KEY `idx_user_roles_assigned_by` (`assigned_by`),
  CONSTRAINT `fk_user_roles_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_role`
    FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_user_roles_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ORGANIZATION
-- =============================================================================

CREATE TABLE IF NOT EXISTS `companies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `legal_name` VARCHAR(255) DEFAULT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `registration_number` VARCHAR(100) DEFAULT NULL,
  `tax_number` VARCHAR(100) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `website` VARCHAR(255) DEFAULT NULL,
  `logo` VARCHAR(500) DEFAULT NULL,
  `address_line1` VARCHAR(255) DEFAULT NULL,
  `address_line2` VARCHAR(255) DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC',
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `date_format` VARCHAR(20) NOT NULL DEFAULT 'Y-m-d',
  `time_format` VARCHAR(20) NOT NULL DEFAULT 'H:i',
  `fiscal_year_start_month` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `settings` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_companies_uuid` (`uuid`),
  UNIQUE KEY `uk_companies_code` (`code`),
  KEY `idx_companies_name` (`name`),
  KEY `idx_companies_is_active` (`is_active`),
  KEY `idx_companies_deleted_at` (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `branches` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `latitude` DECIMAL(10, 8) DEFAULT NULL,
  `longitude` DECIMAL(11, 8) DEFAULT NULL,
  `attendance_radius` DECIMAL(8, 2) NOT NULL DEFAULT 100.00 COMMENT 'Allowed check-in radius in meters',
  `timezone` VARCHAR(64) NOT NULL DEFAULT 'UTC',
  `contact_person` VARCHAR(150) DEFAULT NULL,
  `contact_phone` VARCHAR(30) DEFAULT NULL,
  `contact_email` VARCHAR(191) DEFAULT NULL,
  `is_head_office` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_branches_company_code` (`company_id`, `code`),
  KEY `idx_branches_company_id` (`company_id`),
  KEY `idx_branches_name` (`name`),
  KEY `idx_branches_is_active` (`is_active`),
  KEY `idx_branches_deleted_at` (`deleted_at`),
  KEY `idx_branches_geo` (`latitude`, `longitude`),
  CONSTRAINT `fk_branches_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `departments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `head_employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_departments_company_code` (`company_id`, `code`),
  KEY `idx_departments_company_id` (`company_id`),
  KEY `idx_departments_branch_id` (`branch_id`),
  KEY `idx_departments_parent_id` (`parent_id`),
  KEY `idx_departments_name` (`name`),
  KEY `idx_departments_is_active` (`is_active`),
  KEY `idx_departments_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_departments_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_departments_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_departments_parent`
    FOREIGN KEY (`parent_id`) REFERENCES `departments` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `designations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `level` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_designations_company_code` (`company_id`, `code`),
  KEY `idx_designations_company_id` (`company_id`),
  KEY `idx_designations_department_id` (`department_id`),
  KEY `idx_designations_name` (`name`),
  KEY `idx_designations_is_active` (`is_active`),
  KEY `idx_designations_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_designations_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_designations_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SHIFTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `shifts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `start_time` TIME NOT NULL,
  `end_time` TIME NOT NULL,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `grace_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `late_mark_after_minutes` INT UNSIGNED NOT NULL DEFAULT 15,
  `half_day_after_minutes` INT UNSIGNED DEFAULT NULL,
  `early_leave_grace_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_after_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `expected_work_minutes` INT UNSIGNED DEFAULT NULL,
  `is_overnight` TINYINT(1) NOT NULL DEFAULT 0,
  `is_flexible` TINYINT(1) NOT NULL DEFAULT 0,
  `color` VARCHAR(20) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shifts_company_code` (`company_id`, `code`),
  KEY `idx_shifts_company_id` (`company_id`),
  KEY `idx_shifts_name` (`name`),
  KEY `idx_shifts_is_active` (`is_active`),
  KEY `idx_shifts_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_shifts_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- LEAVE TYPES & POLICIES (before employees)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `leave_types` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_approval` TINYINT(1) NOT NULL DEFAULT 1,
  `requires_attachment` TINYINT(1) NOT NULL DEFAULT 0,
  `allow_half_day` TINYINT(1) NOT NULL DEFAULT 1,
  `allow_negative_balance` TINYINT(1) NOT NULL DEFAULT 0,
  `max_days_per_request` DECIMAL(8, 2) DEFAULT NULL,
  `min_days_per_request` DECIMAL(8, 2) NOT NULL DEFAULT 0.50,
  `max_consecutive_days` INT UNSIGNED DEFAULT NULL,
  `notice_days` INT UNSIGNED NOT NULL DEFAULT 0,
  `gender_restriction` ENUM('all', 'male', 'female', 'other') NOT NULL DEFAULT 'all',
  `color` VARCHAR(20) DEFAULT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_types_company_code` (`company_id`, `code`),
  KEY `idx_leave_types_company_id` (`company_id`),
  KEY `idx_leave_types_name` (`name`),
  KEY `idx_leave_types_is_active` (`is_active`),
  KEY `idx_leave_types_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_leave_types_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_policies` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `year_type` ENUM('calendar', 'fiscal', 'joining') NOT NULL DEFAULT 'calendar',
  `accrual_method` ENUM('yearly', 'monthly', 'none') NOT NULL DEFAULT 'yearly',
  `carry_forward_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `max_carry_forward_days` DECIMAL(8, 2) DEFAULT NULL,
  `encashment_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `include_weekends` TINYINT(1) NOT NULL DEFAULT 0,
  `include_holidays` TINYINT(1) NOT NULL DEFAULT 0,
  `probation_leave_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `rules` JSON DEFAULT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_policies_company_code` (`company_id`, `code`),
  KEY `idx_leave_policies_company_id` (`company_id`),
  KEY `idx_leave_policies_name` (`name`),
  KEY `idx_leave_policies_is_active` (`is_active`),
  KEY `idx_leave_policies_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_leave_policies_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SALARY STRUCTURES (before employees)
-- =============================================================================

CREATE TABLE IF NOT EXISTS `salary_structures` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `effective_from` DATE DEFAULT NULL,
  `effective_to` DATE DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_salary_structures_company_code` (`company_id`, `code`),
  KEY `idx_salary_structures_company_id` (`company_id`),
  KEY `idx_salary_structures_name` (`name`),
  KEY `idx_salary_structures_is_active` (`is_active`),
  KEY `idx_salary_structures_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_salary_structures_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_components` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) NOT NULL,
  `type` ENUM('earning', 'deduction') NOT NULL,
  `calculation_type` ENUM('fixed', 'percentage', 'formula') NOT NULL DEFAULT 'fixed',
  `default_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `percentage_of` VARCHAR(50) DEFAULT NULL COMMENT 'e.g. basic, gross',
  `formula` TEXT DEFAULT NULL,
  `is_taxable` TINYINT(1) NOT NULL DEFAULT 1,
  `is_statutory` TINYINT(1) NOT NULL DEFAULT 0,
  `affects_gross` TINYINT(1) NOT NULL DEFAULT 1,
  `affects_net` TINYINT(1) NOT NULL DEFAULT 1,
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 1,
  `sort_order` INT NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_salary_components_company_code` (`company_id`, `code`),
  KEY `idx_salary_components_company_id` (`company_id`),
  KEY `idx_salary_components_type` (`type`),
  KEY `idx_salary_components_name` (`name`),
  KEY `idx_salary_components_is_active` (`is_active`),
  KEY `idx_salary_components_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_salary_components_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_structure_items` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `salary_structure_id` BIGINT UNSIGNED NOT NULL,
  `salary_component_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15, 2) DEFAULT NULL,
  `percentage` DECIMAL(8, 4) DEFAULT NULL,
  `calculation_type` ENUM('fixed', 'percentage', 'formula') DEFAULT NULL,
  `formula` TEXT DEFAULT NULL,
  `is_optional` TINYINT(1) NOT NULL DEFAULT 0,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_salary_structure_items` (`salary_structure_id`, `salary_component_id`),
  KEY `idx_salary_structure_items_component` (`salary_component_id`),
  CONSTRAINT `fk_salary_structure_items_structure`
    FOREIGN KEY (`salary_structure_id`) REFERENCES `salary_structures` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_salary_structure_items_component`
    FOREIGN KEY (`salary_component_id`) REFERENCES `salary_components` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- EMPLOYEES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employees` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `designation_id` BIGINT UNSIGNED DEFAULT NULL,
  `reporting_manager_id` BIGINT UNSIGNED DEFAULT NULL,
  `shift_id` BIGINT UNSIGNED DEFAULT NULL,
  `leave_policy_id` BIGINT UNSIGNED DEFAULT NULL,
  `salary_structure_id` BIGINT UNSIGNED DEFAULT NULL,
  `employee_code` VARCHAR(50) NOT NULL,
  `first_name` VARCHAR(100) NOT NULL,
  `middle_name` VARCHAR(100) DEFAULT NULL,
  `last_name` VARCHAR(100) NOT NULL,
  `profile_image` VARCHAR(500) DEFAULT NULL,
  `date_of_birth` DATE DEFAULT NULL,
  `gender` ENUM('male', 'female', 'other', 'prefer_not_to_say') DEFAULT NULL,
  `national_id` VARCHAR(100) DEFAULT NULL,
  `passport_number` VARCHAR(100) DEFAULT NULL,
  `marital_status` ENUM('single', 'married', 'divorced', 'widowed', 'other') DEFAULT NULL,
  `personal_email` VARCHAR(191) DEFAULT NULL,
  `company_email` VARCHAR(191) DEFAULT NULL,
  `phone` VARCHAR(30) DEFAULT NULL,
  `alternate_phone` VARCHAR(30) DEFAULT NULL,
  `current_address` TEXT DEFAULT NULL,
  `permanent_address` TEXT DEFAULT NULL,
  `city` VARCHAR(100) DEFAULT NULL,
  `state` VARCHAR(100) DEFAULT NULL,
  `postal_code` VARCHAR(30) DEFAULT NULL,
  `country` VARCHAR(100) DEFAULT NULL,
  `joining_date` DATE NOT NULL,
  `confirmation_date` DATE DEFAULT NULL,
  `resignation_date` DATE DEFAULT NULL,
  `last_working_date` DATE DEFAULT NULL,
  `employment_type` ENUM('full_time', 'part_time', 'contract', 'intern', 'temporary', 'consultant') NOT NULL DEFAULT 'full_time',
  `employment_status` ENUM('active', 'probation', 'notice_period', 'suspended', 'terminated', 'resigned', 'retired', 'inactive') NOT NULL DEFAULT 'active',
  `probation_start` DATE DEFAULT NULL,
  `probation_end` DATE DEFAULT NULL,
  `contract_start` DATE DEFAULT NULL,
  `contract_end` DATE DEFAULT NULL,
  `attendance_policy_notes` TEXT DEFAULT NULL,
  `basic_salary` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `payment_method` ENUM('bank_transfer', 'cash', 'cheque', 'other') NOT NULL DEFAULT 'bank_transfer',
  `remote_attendance_allowed` TINYINT(1) NOT NULL DEFAULT 0,
  `office_location` VARCHAR(255) DEFAULT NULL COMMENT 'Named office / work location label',
  `office_branch_id` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Physical office branch for attendance geo-fence',
  `work_location_type` ENUM('office', 'remote', 'hybrid') NOT NULL DEFAULT 'office',
  `blood_group` VARCHAR(10) DEFAULT NULL,
  `nationality` VARCHAR(100) DEFAULT NULL,
  `religion` VARCHAR(100) DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employees_uuid` (`uuid`),
  UNIQUE KEY `uk_employees_company_code` (`company_id`, `employee_code`),
  UNIQUE KEY `uk_employees_user_id` (`user_id`),
  UNIQUE KEY `uk_employees_company_national_id` (`company_id`, `national_id`),
  KEY `idx_employees_branch_id` (`branch_id`),
  KEY `idx_employees_department_id` (`department_id`),
  KEY `idx_employees_designation_id` (`designation_id`),
  KEY `idx_employees_reporting_manager_id` (`reporting_manager_id`),
  KEY `idx_employees_shift_id` (`shift_id`),
  KEY `idx_employees_leave_policy_id` (`leave_policy_id`),
  KEY `idx_employees_salary_structure_id` (`salary_structure_id`),
  KEY `idx_employees_office_branch_id` (`office_branch_id`),
  KEY `idx_employees_employment_status` (`employment_status`),
  KEY `idx_employees_employment_type` (`employment_type`),
  KEY `idx_employees_joining_date` (`joining_date`),
  KEY `idx_employees_name` (`last_name`, `first_name`),
  KEY `idx_employees_phone` (`phone`),
  KEY `idx_employees_personal_email` (`personal_email`),
  KEY `idx_employees_company_email` (`company_email`),
  KEY `idx_employees_deleted_at` (`deleted_at`),
  KEY `idx_employees_company_status` (`company_id`, `employment_status`),
  CONSTRAINT `fk_employees_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_employees_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_designation`
    FOREIGN KEY (`designation_id`) REFERENCES `designations` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_reporting_manager`
    FOREIGN KEY (`reporting_manager_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_shift`
    FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_leave_policy`
    FOREIGN KEY (`leave_policy_id`) REFERENCES `leave_policies` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_salary_structure`
    FOREIGN KEY (`salary_structure_id`) REFERENCES `salary_structures` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employees_office_branch`
    FOREIGN KEY (`office_branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deferred FKs (referenced tables created later due to circular dependencies).
-- Plain ALTER works on fresh install / MariaDB via PDO (no PREPARE multi-statement).
ALTER TABLE `departments`
  ADD CONSTRAINT `fk_departments_head_employee`
  FOREIGN KEY (`head_employee_id`) REFERENCES `employees` (`id`)
  ON UPDATE CASCADE ON DELETE SET NULL;

ALTER TABLE `roles`
  ADD CONSTRAINT `fk_roles_company`
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
  ON UPDATE CASCADE ON DELETE CASCADE;

ALTER TABLE `user_roles`
  ADD CONSTRAINT `fk_user_roles_company`
  FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
  ON UPDATE CASCADE ON DELETE CASCADE;

CREATE TABLE IF NOT EXISTS `employee_emergency_contacts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `relationship` VARCHAR(100) NOT NULL,
  `phone` VARCHAR(30) NOT NULL,
  `alternate_phone` VARCHAR(30) DEFAULT NULL,
  `email` VARCHAR(191) DEFAULT NULL,
  `address` TEXT DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_emergency_contacts_employee` (`employee_id`),
  KEY `idx_employee_emergency_contacts_phone` (`phone`),
  KEY `idx_employee_emergency_contacts_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_emergency_contacts_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_bank_accounts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `bank_name` VARCHAR(150) NOT NULL,
  `branch_name` VARCHAR(150) DEFAULT NULL,
  `account_title` VARCHAR(150) NOT NULL,
  `account_number` VARCHAR(100) NOT NULL,
  `iban` VARCHAR(50) DEFAULT NULL,
  `swift_code` VARCHAR(30) DEFAULT NULL,
  `routing_number` VARCHAR(50) DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_bank_accounts_employee` (`employee_id`),
  KEY `idx_employee_bank_accounts_account_number` (`account_number`),
  KEY `idx_employee_bank_accounts_iban` (`iban`),
  KEY `idx_employee_bank_accounts_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_bank_accounts_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `document_type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED DEFAULT NULL,
  `document_number` VARCHAR(100) DEFAULT NULL,
  `issue_date` DATE DEFAULT NULL,
  `expiry_date` DATE DEFAULT NULL,
  `is_verified` TINYINT(1) NOT NULL DEFAULT 0,
  `verified_by` BIGINT UNSIGNED DEFAULT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_documents_employee` (`employee_id`),
  KEY `idx_employee_documents_type` (`document_type`),
  KEY `idx_employee_documents_expiry` (`expiry_date`),
  KEY `idx_employee_documents_verified_by` (`verified_by`),
  KEY `idx_employee_documents_uploaded_by` (`uploaded_by`),
  KEY `idx_employee_documents_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_documents_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_employee_documents_verified_by`
    FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employee_documents_uploaded_by`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employment_agreements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `template_version` VARCHAR(50) NOT NULL,
  `template_filename` VARCHAR(255) NOT NULL,
  `template_sha256` CHAR(64) NOT NULL,
  `status` ENUM('pending','accepted') NOT NULL DEFAULT 'pending',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `viewed_at` DATETIME DEFAULT NULL,
  `accepted_at` DATETIME DEFAULT NULL,
  `signer_name` VARCHAR(191) DEFAULT NULL,
  `signature_path` VARCHAR(500) DEFAULT NULL,
  `signed_document_id` BIGINT UNSIGNED DEFAULT NULL,
  `consent_text` TEXT DEFAULT NULL,
  `accepted_ip` VARCHAR(45) DEFAULT NULL,
  `accepted_user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employment_agreement_employee_version` (`employee_id`, `template_version`),
  KEY `idx_employment_agreements_status` (`company_id`, `status`),
  KEY `idx_employment_agreements_user` (`user_id`),
  KEY `idx_employment_agreements_document` (`signed_document_id`),
  CONSTRAINT `fk_employment_agreements_employee` FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employment_agreements_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_employment_agreements_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_employment_agreements_document` FOREIGN KEY (`signed_document_id`) REFERENCES `employee_documents` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_status_history` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `from_status` VARCHAR(50) DEFAULT NULL,
  `to_status` VARCHAR(50) NOT NULL,
  `effective_date` DATE NOT NULL,
  `reason` TEXT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `changed_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_employee_status_history_employee` (`employee_id`),
  KEY `idx_employee_status_history_effective` (`effective_date`),
  KEY `idx_employee_status_history_to_status` (`to_status`),
  KEY `idx_employee_status_history_changed_by` (`changed_by`),
  CONSTRAINT `fk_employee_status_history_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_employee_status_history_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_assignments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `shift_id` BIGINT UNSIGNED NOT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE DEFAULT NULL,
  `is_primary` TINYINT(1) NOT NULL DEFAULT 1,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_shift_assignments_employee` (`employee_id`),
  KEY `idx_shift_assignments_shift` (`shift_id`),
  KEY `idx_shift_assignments_dates` (`effective_from`, `effective_to`),
  KEY `idx_shift_assignments_assigned_by` (`assigned_by`),
  KEY `idx_shift_assignments_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_shift_assignments_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_shift_assignments_shift`
    FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_shift_assignments_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `shift_rosters` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `shift_id` BIGINT UNSIGNED NOT NULL,
  `roster_date` DATE NOT NULL,
  `start_time` TIME DEFAULT NULL,
  `end_time` TIME DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_shift_rosters_employee_date` (`employee_id`, `roster_date`),
  KEY `idx_shift_rosters_company` (`company_id`),
  KEY `idx_shift_rosters_shift` (`shift_id`),
  KEY `idx_shift_rosters_date` (`roster_date`),
  KEY `idx_shift_rosters_created_by` (`created_by`),
  KEY `idx_shift_rosters_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_shift_rosters_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_shift_rosters_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_shift_rosters_shift`
    FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_shift_rosters_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `holidays` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(200) NOT NULL,
  `holiday_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `type` ENUM('public', 'optional', 'company', 'restricted') NOT NULL DEFAULT 'public',
  `is_paid` TINYINT(1) NOT NULL DEFAULT 1,
  `is_recurring` TINYINT(1) NOT NULL DEFAULT 0,
  `description` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_holidays_company` (`company_id`),
  KEY `idx_holidays_branch` (`branch_id`),
  KEY `idx_holidays_date` (`holiday_date`),
  KEY `idx_holidays_type` (`type`),
  KEY `idx_holidays_created_by` (`created_by`),
  KEY `idx_holidays_deleted_at` (`deleted_at`),
  KEY `idx_holidays_company_date` (`company_id`, `holiday_date`),
  CONSTRAINT `fk_holidays_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_holidays_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_holidays_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ATTENDANCE
-- =============================================================================

CREATE TABLE IF NOT EXISTS `attendance` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `shift_id` BIGINT UNSIGNED DEFAULT NULL,
  `attendance_date` DATE NOT NULL,
  `check_in_at` DATETIME DEFAULT NULL,
  `check_out_at` DATETIME DEFAULT NULL,
  `original_check_in_at` DATETIME DEFAULT NULL COMMENT 'Preserved first/source check-in before corrections',
  `original_check_out_at` DATETIME DEFAULT NULL COMMENT 'Preserved first/source check-out before corrections',
  `status` ENUM(
    'present',
    'late',
    'absent',
    'half_day',
    'on_leave',
    'holiday',
    'weekend',
    'remote',
    'manual',
    'missing_checkout'
  ) NOT NULL DEFAULT 'absent',
  `verification_status` ENUM('pending', 'verified', 'flagged', 'rejected', 'auto_verified') NOT NULL DEFAULT 'pending',
  `work_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `late_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `early_leave_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `break_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `expected_work_minutes` INT UNSIGNED DEFAULT NULL,
  `is_remote` TINYINT(1) NOT NULL DEFAULT 0,
  `is_manual` TINYINT(1) NOT NULL DEFAULT 0,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `source` ENUM('mobile', 'web', 'kiosk', 'biometric', 'manual', 'import', 'system') NOT NULL DEFAULT 'web',
  `remarks` TEXT DEFAULT NULL,
  `admin_notes` TEXT DEFAULT NULL,
  `leave_request_id` BIGINT UNSIGNED DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` BIGINT UNSIGNED DEFAULT NULL,
  `deletion_reason` VARCHAR(500) DEFAULT NULL,
  `is_active_flag` TINYINT
    GENERATED ALWAYS AS (IF(`deleted_at` IS NULL, 1, NULL)) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_attendance_uuid` (`uuid`),
  UNIQUE KEY `uk_attendance_active_employee_date` (`employee_id`, `attendance_date`, `is_active_flag`),
  KEY `idx_attendance_company` (`company_id`),
  KEY `idx_attendance_branch` (`branch_id`),
  KEY `idx_attendance_shift` (`shift_id`),
  KEY `idx_attendance_date` (`attendance_date`),
  KEY `idx_attendance_status` (`status`),
  KEY `idx_attendance_verification_status` (`verification_status`),
  KEY `idx_attendance_is_remote` (`is_remote`),
  KEY `idx_attendance_leave_request` (`leave_request_id`),
  KEY `idx_attendance_approved_by` (`approved_by`),
  KEY `idx_attendance_created_by` (`created_by`),
  KEY `idx_attendance_updated_by` (`updated_by`),
  KEY `idx_attendance_deleted_at` (`deleted_at`),
  KEY `idx_attendance_deleted_by` (`deleted_by`),
  KEY `idx_attendance_company_date` (`company_id`, `attendance_date`),
  KEY `idx_attendance_employee_status_date` (`employee_id`, `status`, `attendance_date`),
  CONSTRAINT `fk_attendance_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_attendance_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_shift`
    FOREIGN KEY (`shift_id`) REFERENCES `shifts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_images` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('check_in', 'check_out') NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED DEFAULT NULL,
  `captured_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_images_attendance` (`attendance_id`),
  KEY `idx_attendance_images_type` (`type`),
  KEY `idx_attendance_images_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_attendance_images_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_locations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `type` ENUM('check_in', 'check_out') NOT NULL,
  `latitude` DECIMAL(10, 8) NOT NULL,
  `longitude` DECIMAL(11, 8) NOT NULL,
  `accuracy` DECIMAL(10, 2) DEFAULT NULL COMMENT 'GPS accuracy in meters',
  `distance_meters` DECIMAL(10, 2) DEFAULT NULL COMMENT 'Distance from branch/office geofence center',
  `altitude` DECIMAL(10, 2) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `device_info` VARCHAR(500) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `is_within_radius` TINYINT(1) DEFAULT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `captured_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_locations_attendance` (`attendance_id`),
  KEY `idx_attendance_locations_type` (`type`),
  KEY `idx_attendance_locations_geo` (`latitude`, `longitude`),
  KEY `idx_attendance_locations_branch` (`branch_id`),
  KEY `idx_attendance_locations_ip` (`ip_address`),
  CONSTRAINT `fk_attendance_locations_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_locations_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_breaks` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `break_start_at` DATETIME NOT NULL,
  `break_end_at` DATETIME DEFAULT NULL,
  `duration_minutes` INT UNSIGNED DEFAULT NULL,
  `break_type` ENUM('lunch', 'tea', 'personal', 'other') NOT NULL DEFAULT 'other',
  `remarks` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_breaks_attendance` (`attendance_id`),
  KEY `idx_attendance_breaks_start` (`break_start_at`),
  CONSTRAINT `fk_attendance_breaks_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_corrections` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `requested_check_in_at` DATETIME DEFAULT NULL,
  `requested_check_out_at` DATETIME DEFAULT NULL,
  `previous_check_in_at` DATETIME DEFAULT NULL,
  `previous_check_out_at` DATETIME DEFAULT NULL,
  `previous_status` VARCHAR(50) DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_corrections_attendance` (`attendance_id`),
  KEY `idx_attendance_corrections_employee` (`employee_id`),
  KEY `idx_attendance_corrections_status` (`status`),
  KEY `idx_attendance_corrections_reviewed_by` (`reviewed_by`),
  KEY `idx_attendance_corrections_created_by` (`created_by`),
  KEY `idx_attendance_corrections_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_attendance_corrections_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_corrections_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_corrections_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_corrections_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `adjustment_type` ENUM('work_minutes', 'late_minutes', 'overtime_minutes', 'break_minutes', 'status', 'other') NOT NULL,
  `field_name` VARCHAR(100) DEFAULT NULL,
  `old_value` VARCHAR(255) DEFAULT NULL,
  `new_value` VARCHAR(255) DEFAULT NULL,
  `minutes_delta` INT DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `adjusted_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_adjustments_attendance` (`attendance_id`),
  KEY `idx_attendance_adjustments_employee` (`employee_id`),
  KEY `idx_attendance_adjustments_type` (`adjustment_type`),
  KEY `idx_attendance_adjustments_adjusted_by` (`adjusted_by`),
  CONSTRAINT `fk_attendance_adjustments_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_adjustments_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_adjustments_adjusted_by`
    FOREIGN KEY (`adjusted_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `attendance_audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attendance_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` VARCHAR(100) NOT NULL,
  `field_name` VARCHAR(100) DEFAULT NULL,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `changes` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `performed_by` BIGINT UNSIGNED DEFAULT NULL,
  `performed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_attendance_audit_logs_attendance` (`attendance_id`),
  KEY `idx_attendance_audit_logs_employee` (`employee_id`),
  KEY `idx_attendance_audit_logs_action` (`action`),
  KEY `idx_attendance_audit_logs_performed_by` (`performed_by`),
  KEY `idx_attendance_audit_logs_performed_at` (`performed_at`),
  CONSTRAINT `fk_attendance_audit_logs_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_attendance_audit_logs_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_attendance_audit_logs_performed_by`
    FOREIGN KEY (`performed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- LEAVE
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employee_leave_balances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `leave_type_id` BIGINT UNSIGNED NOT NULL,
  `year` YEAR NOT NULL,
  `opening_balance` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `accrued` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `used` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `pending` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `carried_forward` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `adjusted` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `encashed` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `closing_balance` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_leave_balances` (`employee_id`, `leave_type_id`, `year`),
  KEY `idx_employee_leave_balances_leave_type` (`leave_type_id`),
  KEY `idx_employee_leave_balances_year` (`year`),
  CONSTRAINT `fk_employee_leave_balances_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_employee_leave_balances_leave_type`
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `leave_type_id` BIGINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `is_half_day` TINYINT(1) NOT NULL DEFAULT 0,
  `half_day_type` ENUM('first_half', 'second_half') DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('draft', 'pending', 'approved', 'rejected', 'cancelled', 'withdrawn') NOT NULL DEFAULT 'pending',
  `calendar_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `weekend_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `holiday_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `chargeable_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `handover_employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `handover_notes` TEXT DEFAULT NULL,
  `contact_during_leave` VARCHAR(255) DEFAULT NULL,
  `emergency_contact` VARCHAR(255) DEFAULT NULL,
  `applied_at` DATETIME DEFAULT NULL,
  `cancelled_at` DATETIME DEFAULT NULL,
  `cancellation_reason` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` BIGINT UNSIGNED DEFAULT NULL,
  `deletion_reason` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_requests_uuid` (`uuid`),
  KEY `idx_leave_requests_company` (`company_id`),
  KEY `idx_leave_requests_employee` (`employee_id`),
  KEY `idx_leave_requests_leave_type` (`leave_type_id`),
  KEY `idx_leave_requests_status` (`status`),
  KEY `idx_leave_requests_dates` (`start_date`, `end_date`),
  KEY `idx_leave_requests_handover` (`handover_employee_id`),
  KEY `idx_leave_requests_created_by` (`created_by`),
  KEY `idx_leave_requests_updated_by` (`updated_by`),
  KEY `idx_leave_requests_deleted_at` (`deleted_at`),
  KEY `idx_leave_requests_deleted_by` (`deleted_by`),
  KEY `idx_leave_requests_employee_status` (`employee_id`, `status`),
  CONSTRAINT `fk_leave_requests_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_leave_requests_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_requests_leave_type`
    FOREIGN KEY (`leave_type_id`) REFERENCES `leave_types` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_leave_requests_handover`
    FOREIGN KEY (`handover_employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_leave_requests_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_leave_requests_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_leave_requests_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `attendance`
  ADD CONSTRAINT `fk_attendance_leave_request`
    FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL;

CREATE TABLE IF NOT EXISTS `leave_approvals` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_request_id` BIGINT UNSIGNED NOT NULL,
  `approver_id` BIGINT UNSIGNED NOT NULL,
  `level` INT UNSIGNED NOT NULL DEFAULT 1,
  `status` ENUM('pending', 'approved', 'rejected', 'skipped') NOT NULL DEFAULT 'pending',
  `comments` TEXT DEFAULT NULL,
  `acted_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_leave_approvals_request_level` (`leave_request_id`, `level`),
  KEY `idx_leave_approvals_approver` (`approver_id`),
  KEY `idx_leave_approvals_status` (`status`),
  CONSTRAINT `fk_leave_approvals_request`
    FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_approvals_approver`
    FOREIGN KEY (`approver_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_amendments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_request_id` BIGINT UNSIGNED NOT NULL,
  `previous_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `reason` VARCHAR(1000) NOT NULL,
  `balance_adjustment` DECIMAL(10, 2) DEFAULT NULL,
  `changed_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_leave_amendments_request` (`leave_request_id`),
  KEY `idx_leave_amendments_changed_by` (`changed_by`),
  CONSTRAINT `fk_leave_amendments_request`
    FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_amendments_changed_by`
    FOREIGN KEY (`changed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_extensions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_request_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `previous_end_date` DATE NOT NULL,
  `new_end_date` DATE NOT NULL,
  `additional_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leave_extensions_request` (`leave_request_id`),
  KEY `idx_leave_extensions_employee` (`employee_id`),
  KEY `idx_leave_extensions_status` (`status`),
  KEY `idx_leave_extensions_reviewed_by` (`reviewed_by`),
  KEY `idx_leave_extensions_created_by` (`created_by`),
  KEY `idx_leave_extensions_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_leave_extensions_request`
    FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_extensions_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_extensions_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_leave_extensions_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `leave_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `leave_request_id` BIGINT UNSIGNED NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_leave_attachments_request` (`leave_request_id`),
  KEY `idx_leave_attachments_uploaded_by` (`uploaded_by`),
  KEY `idx_leave_attachments_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_leave_attachments_request`
    FOREIGN KEY (`leave_request_id`) REFERENCES `leave_requests` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_leave_attachments_uploaded_by`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- EMPLOYEE SALARY ASSIGNMENTS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employee_salary_structures` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `salary_structure_id` BIGINT UNSIGNED NOT NULL,
  `basic_salary` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `gross_salary` DECIMAL(15, 2) DEFAULT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `is_current` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_salary_structures_employee` (`employee_id`),
  KEY `idx_employee_salary_structures_structure` (`salary_structure_id`),
  KEY `idx_employee_salary_structures_dates` (`effective_from`, `effective_to`),
  KEY `idx_employee_salary_structures_is_current` (`is_current`),
  KEY `idx_employee_salary_structures_assigned_by` (`assigned_by`),
  KEY `idx_employee_salary_structures_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_salary_structures_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_employee_salary_structures_structure`
    FOREIGN KEY (`salary_structure_id`) REFERENCES `salary_structures` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_employee_salary_structures_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `employee_salary_components` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `employee_salary_structure_id` BIGINT UNSIGNED DEFAULT NULL,
  `salary_component_id` BIGINT UNSIGNED NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `percentage` DECIMAL(8, 4) DEFAULT NULL,
  `calculation_type` ENUM('fixed', 'percentage', 'formula') NOT NULL DEFAULT 'fixed',
  `formula` TEXT DEFAULT NULL,
  `effective_from` DATE NOT NULL,
  `effective_to` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_employee_salary_components_employee` (`employee_id`),
  KEY `idx_employee_salary_components_structure` (`employee_salary_structure_id`),
  KEY `idx_employee_salary_components_component` (`salary_component_id`),
  KEY `idx_employee_salary_components_dates` (`effective_from`, `effective_to`),
  KEY `idx_employee_salary_components_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_salary_components_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_employee_salary_components_ess`
    FOREIGN KEY (`employee_salary_structure_id`) REFERENCES `employee_salary_structures` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employee_salary_components_component`
    FOREIGN KEY (`salary_component_id`) REFERENCES `salary_components` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- PAYROLL
-- =============================================================================

CREATE TABLE IF NOT EXISTS `payroll_periods` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(50) DEFAULT NULL,
  `period_year` YEAR NOT NULL,
  `period_month` TINYINT UNSIGNED NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NOT NULL,
  `pay_date` DATE DEFAULT NULL,
  `status` ENUM('draft', 'processing', 'calculated', 'approved', 'paid', 'locked', 'reopened', 'cancelled') NOT NULL DEFAULT 'draft',
  `total_employees` INT UNSIGNED NOT NULL DEFAULT 0,
  `total_gross` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_net` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `locked_at` DATETIME DEFAULT NULL,
  `locked_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` BIGINT UNSIGNED DEFAULT NULL,
  `deletion_reason` VARCHAR(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_periods_company_year_month` (`company_id`, `period_year`, `period_month`),
  UNIQUE KEY `uk_payroll_periods_company_code` (`company_id`, `code`),
  KEY `idx_payroll_periods_status` (`status`),
  KEY `idx_payroll_periods_dates` (`start_date`, `end_date`),
  KEY `idx_payroll_periods_locked_by` (`locked_by`),
  KEY `idx_payroll_periods_approved_by` (`approved_by`),
  KEY `idx_payroll_periods_created_by` (`created_by`),
  KEY `idx_payroll_periods_deleted_at` (`deleted_at`),
  KEY `idx_payroll_periods_deleted_by` (`deleted_by`),
  CONSTRAINT `fk_payroll_periods_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_payroll_periods_locked_by`
    FOREIGN KEY (`locked_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_periods_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_periods_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_periods_deleted_by`
    FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll_records` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `payroll_period_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `basic_salary` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `gross_earnings` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `working_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `present_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `absent_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `leave_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `holiday_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `weekend_days` DECIMAL(8, 2) NOT NULL DEFAULT 0.00,
  `overtime_minutes` INT UNSIGNED NOT NULL DEFAULT 0,
  `overtime_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `loan_deduction` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `advance_deduction` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `status` ENUM('draft', 'calculated', 'approved', 'paid', 'on_hold', 'cancelled') NOT NULL DEFAULT 'draft',
  `payment_method` ENUM('bank_transfer', 'cash', 'cheque', 'other') DEFAULT NULL,
  `payment_reference` VARCHAR(150) DEFAULT NULL,
  `paid_at` DATETIME DEFAULT NULL,
  `bank_account_id` BIGINT UNSIGNED DEFAULT NULL,
  `remarks` TEXT DEFAULT NULL,
  `calculated_at` DATETIME DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payroll_records_uuid` (`uuid`),
  UNIQUE KEY `uk_payroll_records_period_employee` (`payroll_period_id`, `employee_id`),
  KEY `idx_payroll_records_employee` (`employee_id`),
  KEY `idx_payroll_records_company` (`company_id`),
  KEY `idx_payroll_records_status` (`status`),
  KEY `idx_payroll_records_bank_account` (`bank_account_id`),
  KEY `idx_payroll_records_approved_by` (`approved_by`),
  KEY `idx_payroll_records_created_by` (`created_by`),
  KEY `idx_payroll_records_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_payroll_records_period`
    FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_records_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_payroll_records_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_payroll_records_bank_account`
    FOREIGN KEY (`bank_account_id`) REFERENCES `employee_bank_accounts` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_records_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_records_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll_earnings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_record_id` BIGINT UNSIGNED NOT NULL,
  `salary_component_id` BIGINT UNSIGNED DEFAULT NULL,
  `component_code` VARCHAR(50) DEFAULT NULL,
  `component_name` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `is_taxable` TINYINT(1) NOT NULL DEFAULT 1,
  `calculation_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_earnings_record` (`payroll_record_id`),
  KEY `idx_payroll_earnings_component` (`salary_component_id`),
  KEY `idx_payroll_earnings_code` (`component_code`),
  CONSTRAINT `fk_payroll_earnings_record`
    FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_earnings_component`
    FOREIGN KEY (`salary_component_id`) REFERENCES `salary_components` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll_deductions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_record_id` BIGINT UNSIGNED NOT NULL,
  `salary_component_id` BIGINT UNSIGNED DEFAULT NULL,
  `component_code` VARCHAR(50) DEFAULT NULL,
  `component_name` VARCHAR(150) NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `is_statutory` TINYINT(1) NOT NULL DEFAULT 0,
  `calculation_notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_deductions_record` (`payroll_record_id`),
  KEY `idx_payroll_deductions_component` (`salary_component_id`),
  KEY `idx_payroll_deductions_code` (`component_code`),
  CONSTRAINT `fk_payroll_deductions_record`
    FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_deductions_component`
    FOREIGN KEY (`salary_component_id`) REFERENCES `salary_components` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payroll_adjustments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `payroll_record_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `adjustment_type` ENUM('earning', 'deduction') NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'applied') NOT NULL DEFAULT 'pending',
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_payroll_adjustments_record` (`payroll_record_id`),
  KEY `idx_payroll_adjustments_employee` (`employee_id`),
  KEY `idx_payroll_adjustments_type` (`adjustment_type`),
  KEY `idx_payroll_adjustments_status` (`status`),
  KEY `idx_payroll_adjustments_approved_by` (`approved_by`),
  KEY `idx_payroll_adjustments_created_by` (`created_by`),
  KEY `idx_payroll_adjustments_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_payroll_adjustments_record`
    FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_adjustments_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payroll_adjustments_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_payroll_adjustments_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `payslips` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `payroll_record_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `payroll_period_id` BIGINT UNSIGNED NOT NULL,
  `payslip_number` VARCHAR(100) NOT NULL,
  `issue_date` DATE NOT NULL,
  `gross_earnings` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_deductions` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `net_salary` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `file_path` VARCHAR(500) DEFAULT NULL,
  `file_mime` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('generated', 'sent', 'viewed', 'downloaded', 'void') NOT NULL DEFAULT 'generated',
  `sent_at` DATETIME DEFAULT NULL,
  `viewed_at` DATETIME DEFAULT NULL,
  `snapshot` JSON DEFAULT NULL COMMENT 'Immutable payslip line-item snapshot',
  `generated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_payslips_uuid` (`uuid`),
  UNIQUE KEY `uk_payslips_number` (`payslip_number`),
  UNIQUE KEY `uk_payslips_record` (`payroll_record_id`),
  KEY `idx_payslips_employee` (`employee_id`),
  KEY `idx_payslips_period` (`payroll_period_id`),
  KEY `idx_payslips_status` (`status`),
  KEY `idx_payslips_issue_date` (`issue_date`),
  KEY `idx_payslips_generated_by` (`generated_by`),
  KEY `idx_payslips_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_payslips_record`
    FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payslips_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_payslips_period`
    FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_payslips_generated_by`
    FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- OVERTIME, LOANS, ADVANCES
-- =============================================================================

CREATE TABLE IF NOT EXISTS `overtime_requests` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `attendance_id` BIGINT UNSIGNED DEFAULT NULL,
  `overtime_date` DATE NOT NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NOT NULL,
  `requested_minutes` INT UNSIGNED NOT NULL,
  `approved_minutes` INT UNSIGNED DEFAULT NULL,
  `hourly_rate` DECIMAL(15, 2) DEFAULT NULL,
  `amount` DECIMAL(15, 2) DEFAULT NULL,
  `reason` TEXT NOT NULL,
  `status` ENUM('pending', 'approved', 'rejected', 'cancelled', 'paid') NOT NULL DEFAULT 'pending',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `review_notes` TEXT DEFAULT NULL,
  `payroll_period_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_overtime_requests_uuid` (`uuid`),
  KEY `idx_overtime_requests_company` (`company_id`),
  KEY `idx_overtime_requests_employee` (`employee_id`),
  KEY `idx_overtime_requests_attendance` (`attendance_id`),
  KEY `idx_overtime_requests_date` (`overtime_date`),
  KEY `idx_overtime_requests_status` (`status`),
  KEY `idx_overtime_requests_reviewed_by` (`reviewed_by`),
  KEY `idx_overtime_requests_payroll_period` (`payroll_period_id`),
  KEY `idx_overtime_requests_created_by` (`created_by`),
  KEY `idx_overtime_requests_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_overtime_requests_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_overtime_requests_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_overtime_requests_attendance`
    FOREIGN KEY (`attendance_id`) REFERENCES `attendance` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_overtime_requests_reviewed_by`
    FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_overtime_requests_payroll_period`
    FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_overtime_requests_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loans` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `loan_number` VARCHAR(50) NOT NULL,
  `loan_type` ENUM('personal', 'salary', 'emergency', 'housing', 'vehicle', 'other') NOT NULL DEFAULT 'personal',
  `principal_amount` DECIMAL(15, 2) NOT NULL,
  `interest_rate` DECIMAL(8, 4) NOT NULL DEFAULT 0.0000,
  `interest_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15, 2) NOT NULL,
  `installment_amount` DECIMAL(15, 2) NOT NULL,
  `total_installments` INT UNSIGNED NOT NULL,
  `paid_installments` INT UNSIGNED NOT NULL DEFAULT 0,
  `paid_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `remaining_amount` DECIMAL(15, 2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `status` ENUM('pending', 'approved', 'active', 'completed', 'rejected', 'cancelled', 'defaulted') NOT NULL DEFAULT 'pending',
  `reason` TEXT DEFAULT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `disbursed_at` DATETIME DEFAULT NULL,
  `completed_at` DATETIME DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_loans_uuid` (`uuid`),
  UNIQUE KEY `uk_loans_company_number` (`company_id`, `loan_number`),
  KEY `idx_loans_employee` (`employee_id`),
  KEY `idx_loans_status` (`status`),
  KEY `idx_loans_type` (`loan_type`),
  KEY `idx_loans_start_date` (`start_date`),
  KEY `idx_loans_approved_by` (`approved_by`),
  KEY `idx_loans_created_by` (`created_by`),
  KEY `idx_loans_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_loans_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_loans_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_loans_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_loans_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `loan_installments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `loan_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `installment_number` INT UNSIGNED NOT NULL,
  `due_date` DATE NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `principal_portion` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `interest_portion` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `paid_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'paid', 'partial', 'skipped', 'waived', 'overdue') NOT NULL DEFAULT 'pending',
  `paid_at` DATETIME DEFAULT NULL,
  `payroll_record_id` BIGINT UNSIGNED DEFAULT NULL,
  `payroll_period_id` BIGINT UNSIGNED DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_loan_installments_loan_number` (`loan_id`, `installment_number`),
  KEY `idx_loan_installments_employee` (`employee_id`),
  KEY `idx_loan_installments_due_date` (`due_date`),
  KEY `idx_loan_installments_status` (`status`),
  KEY `idx_loan_installments_payroll_record` (`payroll_record_id`),
  KEY `idx_loan_installments_payroll_period` (`payroll_period_id`),
  CONSTRAINT `fk_loan_installments_loan`
    FOREIGN KEY (`loan_id`) REFERENCES `loans` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_loan_installments_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_loan_installments_payroll_record`
    FOREIGN KEY (`payroll_record_id`) REFERENCES `payroll_records` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_loan_installments_payroll_period`
    FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `salary_advances` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `advance_number` VARCHAR(50) NOT NULL,
  `amount` DECIMAL(15, 2) NOT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `request_date` DATE NOT NULL,
  `disbursement_date` DATE DEFAULT NULL,
  `repayment_method` ENUM('single', 'installments', 'payroll_deduction') NOT NULL DEFAULT 'payroll_deduction',
  `installments_count` INT UNSIGNED NOT NULL DEFAULT 1,
  `repaid_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `remaining_amount` DECIMAL(15, 2) NOT NULL,
  `status` ENUM('pending', 'approved', 'disbursed', 'repaying', 'completed', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending',
  `reason` TEXT NOT NULL,
  `approved_by` BIGINT UNSIGNED DEFAULT NULL,
  `approved_at` DATETIME DEFAULT NULL,
  `payroll_period_id` BIGINT UNSIGNED DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_salary_advances_uuid` (`uuid`),
  UNIQUE KEY `uk_salary_advances_company_number` (`company_id`, `advance_number`),
  KEY `idx_salary_advances_employee` (`employee_id`),
  KEY `idx_salary_advances_status` (`status`),
  KEY `idx_salary_advances_request_date` (`request_date`),
  KEY `idx_salary_advances_approved_by` (`approved_by`),
  KEY `idx_salary_advances_payroll_period` (`payroll_period_id`),
  KEY `idx_salary_advances_created_by` (`created_by`),
  KEY `idx_salary_advances_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_salary_advances_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_salary_advances_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_salary_advances_approved_by`
    FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_salary_advances_payroll_period`
    FOREIGN KEY (`payroll_period_id`) REFERENCES `payroll_periods` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_salary_advances_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- ASSETS, ANNOUNCEMENTS, NOTIFICATIONS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `employee_assets` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `employee_id` BIGINT UNSIGNED NOT NULL,
  `asset_tag` VARCHAR(100) NOT NULL,
  `asset_name` VARCHAR(200) NOT NULL,
  `asset_type` VARCHAR(100) NOT NULL,
  `brand` VARCHAR(100) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `serial_number` VARCHAR(150) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `assigned_date` DATE NOT NULL,
  `return_date` DATE DEFAULT NULL,
  `condition_on_assign` ENUM('new', 'good', 'fair', 'poor') NOT NULL DEFAULT 'good',
  `condition_on_return` ENUM('new', 'good', 'fair', 'poor', 'damaged', 'lost') DEFAULT NULL,
  `status` ENUM('assigned', 'returned', 'lost', 'damaged', 'retired') NOT NULL DEFAULT 'assigned',
  `value` DECIMAL(15, 2) DEFAULT NULL,
  `currency` CHAR(3) NOT NULL DEFAULT 'PKR',
  `notes` TEXT DEFAULT NULL,
  `assigned_by` BIGINT UNSIGNED DEFAULT NULL,
  `returned_to` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_employee_assets_company_tag` (`company_id`, `asset_tag`),
  KEY `idx_employee_assets_employee` (`employee_id`),
  KEY `idx_employee_assets_type` (`asset_type`),
  KEY `idx_employee_assets_status` (`status`),
  KEY `idx_employee_assets_serial` (`serial_number`),
  KEY `idx_employee_assets_assigned_by` (`assigned_by`),
  KEY `idx_employee_assets_returned_to` (`returned_to`),
  KEY `idx_employee_assets_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_employee_assets_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_employee_assets_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_employee_assets_assigned_by`
    FOREIGN KEY (`assigned_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_employee_assets_returned_to`
    FOREIGN KEY (`returned_to`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `announcements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) DEFAULT NULL,
  `summary` VARCHAR(500) DEFAULT NULL,
  `body` LONGTEXT NOT NULL,
  `priority` ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
  `audience` ENUM('all', 'branch', 'department', 'role', 'custom') NOT NULL DEFAULT 'all',
  `publish_at` DATETIME DEFAULT NULL,
  `expires_at` DATETIME DEFAULT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 0,
  `is_pinned` TINYINT(1) NOT NULL DEFAULT 0,
  `attachment_path` VARCHAR(500) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_announcements_company` (`company_id`),
  KEY `idx_announcements_branch` (`branch_id`),
  KEY `idx_announcements_department` (`department_id`),
  KEY `idx_announcements_priority` (`priority`),
  KEY `idx_announcements_publish` (`publish_at`, `expires_at`),
  KEY `idx_announcements_is_published` (`is_published`),
  KEY `idx_announcements_created_by` (`created_by`),
  KEY `idx_announcements_updated_by` (`updated_by`),
  KEY `idx_announcements_deleted_at` (`deleted_at`),
  KEY `idx_announcements_title` (`title`),
  CONSTRAINT `fk_announcements_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_announcements_branch`
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_announcements_department`
    FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_announcements_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_announcements_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `type` VARCHAR(100) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `message` TEXT NOT NULL,
  `data` JSON DEFAULT NULL,
  `action_url` VARCHAR(500) DEFAULT NULL,
  `channel` ENUM('in_app', 'email', 'sms', 'push') NOT NULL DEFAULT 'in_app',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `read_at` DATETIME DEFAULT NULL,
  `sent_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_notifications_uuid` (`uuid`),
  KEY `idx_notifications_user` (`user_id`),
  KEY `idx_notifications_company` (`company_id`),
  KEY `idx_notifications_type` (`type`),
  KEY `idx_notifications_is_read` (`is_read`),
  KEY `idx_notifications_created_at` (`created_at`),
  KEY `idx_notifications_user_unread` (`user_id`, `is_read`, `created_at`),
  CONSTRAINT `fk_notifications_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_notifications_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
-- SYSTEM / AUDIT / SETTINGS
-- =============================================================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `employee_id` BIGINT UNSIGNED DEFAULT NULL,
  `log_name` VARCHAR(100) DEFAULT NULL,
  `description` VARCHAR(500) NOT NULL,
  `subject_type` VARCHAR(150) DEFAULT NULL,
  `subject_id` BIGINT UNSIGNED DEFAULT NULL,
  `event` VARCHAR(100) DEFAULT NULL,
  `properties` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activity_logs_company` (`company_id`),
  KEY `idx_activity_logs_user` (`user_id`),
  KEY `idx_activity_logs_employee` (`employee_id`),
  KEY `idx_activity_logs_subject` (`subject_type`, `subject_id`),
  KEY `idx_activity_logs_event` (`event`),
  KEY `idx_activity_logs_created_at` (`created_at`),
  KEY `idx_activity_logs_log_name` (`log_name`),
  CONSTRAINT `fk_activity_logs_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_activity_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_activity_logs_employee`
    FOREIGN KEY (`employee_id`) REFERENCES `employees` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` BIGINT UNSIGNED DEFAULT NULL,
  `table_name` VARCHAR(100) NOT NULL,
  `record_id` BIGINT UNSIGNED DEFAULT NULL,
  `action` ENUM('create', 'update', 'delete', 'restore', 'login', 'logout', 'export', 'import', 'other') NOT NULL,
  `old_values` JSON DEFAULT NULL,
  `new_values` JSON DEFAULT NULL,
  `changed_fields` JSON DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `request_id` VARCHAR(64) DEFAULT NULL,
  `url` VARCHAR(500) DEFAULT NULL,
  `method` VARCHAR(10) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_audit_logs_company` (`company_id`),
  KEY `idx_audit_logs_user` (`user_id`),
  KEY `idx_audit_logs_table_record` (`table_name`, `record_id`),
  KEY `idx_audit_logs_action` (`action`),
  KEY `idx_audit_logs_created_at` (`created_at`),
  KEY `idx_audit_logs_request_id` (`request_id`),
  CONSTRAINT `fk_audit_logs_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_audit_logs_user`
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `group_name` VARCHAR(100) NOT NULL DEFAULT 'general',
  `setting_key` VARCHAR(150) NOT NULL,
  `setting_value` LONGTEXT DEFAULT NULL,
  `value_type` ENUM('string', 'integer', 'boolean', 'json', 'text', 'decimal') NOT NULL DEFAULT 'string',
  `description` VARCHAR(500) DEFAULT NULL,
  `is_public` TINYINT(1) NOT NULL DEFAULT 0,
  `is_encrypted` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_system_settings_company_key` (`company_id`, `setting_key`),
  KEY `idx_system_settings_group` (`group_name`),
  KEY `idx_system_settings_key` (`setting_key`),
  CONSTRAINT `fk_system_settings_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` LONGTEXT NOT NULL,
  `body_text` LONGTEXT DEFAULT NULL,
  `placeholders` JSON DEFAULT NULL,
  `locale` VARCHAR(10) NOT NULL DEFAULT 'en',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `updated_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email_templates_company_slug_locale` (`company_id`, `slug`, `locale`),
  KEY `idx_email_templates_slug` (`slug`),
  KEY `idx_email_templates_is_active` (`is_active`),
  KEY `idx_email_templates_created_by` (`created_by`),
  KEY `idx_email_templates_updated_by` (`updated_by`),
  KEY `idx_email_templates_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_email_templates_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_email_templates_created_by`
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_email_templates_updated_by`
    FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `file_uploads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `disk` VARCHAR(50) NOT NULL DEFAULT 'local',
  `path` VARCHAR(500) NOT NULL,
  `filename` VARCHAR(255) NOT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `mime_type` VARCHAR(100) DEFAULT NULL,
  `extension` VARCHAR(20) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED DEFAULT NULL,
  `checksum` VARCHAR(64) DEFAULT NULL,
  `visibility` ENUM('private', 'public') NOT NULL DEFAULT 'private',
  `related_type` VARCHAR(150) DEFAULT NULL,
  `related_id` BIGINT UNSIGNED DEFAULT NULL,
  `collection` VARCHAR(100) DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_file_uploads_uuid` (`uuid`),
  KEY `idx_file_uploads_company` (`company_id`),
  KEY `idx_file_uploads_uploaded_by` (`uploaded_by`),
  KEY `idx_file_uploads_related` (`related_type`, `related_id`),
  KEY `idx_file_uploads_collection` (`collection`),
  KEY `idx_file_uploads_checksum` (`checksum`),
  KEY `idx_file_uploads_deleted_at` (`deleted_at`),
  CONSTRAINT `fk_file_uploads_company`
    FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_file_uploads_uploaded_by`
    FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`)
    ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- TEAM CHAT / COLLABORATION
-- =============================================================================

-- -----------------------------------------------------------------------------
-- Channel roles lookup
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `can_manage` TINYINT(1) NOT NULL DEFAULT 0,
  `can_invite` TINYINT(1) NOT NULL DEFAULT 0,
  `can_post` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `chat_roles` (`id`, `slug`, `name`, `can_manage`, `can_invite`, `can_post`) VALUES
(1, 'owner', 'Owner', 1, 1, 1),
(2, 'admin', 'Admin', 1, 1, 1),
(3, 'member', 'Member', 0, 0, 1),
(4, 'readonly', 'Read Only', 0, 0, 0);

-- -----------------------------------------------------------------------------
-- Channels
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_channels` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `description` VARCHAR(500) DEFAULT NULL,
  `topic` VARCHAR(255) DEFAULT NULL,
  `channel_type` ENUM('public','private','announcement') NOT NULL DEFAULT 'public',
  `scope` ENUM('general','company','branch','department','hr','payroll','onboarding','custom') NOT NULL DEFAULT 'custom',
  `branch_id` BIGINT UNSIGNED DEFAULT NULL,
  `department_id` BIGINT UNSIGNED DEFAULT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `is_archived` TINYINT(1) NOT NULL DEFAULT 0,
  `requires_acknowledgement` TINYINT(1) NOT NULL DEFAULT 0,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `settings` JSON DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_channels_uuid` (`uuid`),
  UNIQUE KEY `uk_chat_channels_company_slug` (`company_id`, `slug`),
  KEY `idx_chat_channels_company` (`company_id`),
  KEY `idx_chat_channels_type` (`channel_type`),
  KEY `idx_chat_channels_scope` (`scope`),
  KEY `idx_chat_channels_branch` (`branch_id`),
  KEY `idx_chat_channels_department` (`department_id`),
  KEY `idx_chat_channels_last_message` (`last_message_at`),
  CONSTRAINT `fk_chat_channels_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_channels_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_channels_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_channels_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_channel_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `channel_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `role_id` TINYINT UNSIGNED NOT NULL DEFAULT 3,
  `last_read_message_id` BIGINT UNSIGNED DEFAULT NULL,
  `last_read_at` DATETIME DEFAULT NULL,
  `muted_until` DATETIME DEFAULT NULL,
  `notification_level` ENUM('all','mentions','none') NOT NULL DEFAULT 'all',
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` DATETIME DEFAULT NULL,
  `added_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_channel_members` (`channel_id`, `user_id`),
  KEY `idx_chat_channel_members_user` (`user_id`),
  KEY `idx_chat_channel_members_role` (`role_id`),
  CONSTRAINT `fk_chat_channel_members_channel` FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_channel_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_channel_members_role` FOREIGN KEY (`role_id`) REFERENCES `chat_roles` (`id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_chat_channel_members_added_by` FOREIGN KEY (`added_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Direct conversations
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_conversations` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `is_group` TINYINT(1) NOT NULL DEFAULT 0,
  `title` VARCHAR(150) DEFAULT NULL,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `last_message_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_conversations_uuid` (`uuid`),
  KEY `idx_chat_conversations_company` (`company_id`),
  KEY `idx_chat_conversations_last` (`last_message_at`),
  CONSTRAINT `fk_chat_conversations_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_conversations_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_conversation_members` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `conversation_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `last_read_message_id` BIGINT UNSIGNED DEFAULT NULL,
  `last_read_at` DATETIME DEFAULT NULL,
  `muted_until` DATETIME DEFAULT NULL,
  `joined_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `left_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_conversation_members` (`conversation_id`, `user_id`),
  KEY `idx_chat_conversation_members_user` (`user_id`),
  CONSTRAINT `fk_chat_conversation_members_conv` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_conversation_members_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Messages
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `conversation_id` BIGINT UNSIGNED DEFAULT NULL,
  `thread_parent_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `body` MEDIUMTEXT NOT NULL,
  `body_html` MEDIUMTEXT DEFAULT NULL,
  `message_type` ENUM('text','system','file','announcement') NOT NULL DEFAULT 'text',
  `requires_acknowledgement` TINYINT(1) NOT NULL DEFAULT 0,
  `is_edited` TINYINT(1) NOT NULL DEFAULT 0,
  `edited_at` DATETIME DEFAULT NULL,
  `reply_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `reaction_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `attachment_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `meta` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  `deleted_by` BIGINT UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_messages_uuid` (`uuid`),
  KEY `idx_chat_messages_company` (`company_id`),
  KEY `idx_chat_messages_channel` (`channel_id`, `id`),
  KEY `idx_chat_messages_conversation` (`conversation_id`, `id`),
  KEY `idx_chat_messages_thread` (`thread_parent_id`, `id`),
  KEY `idx_chat_messages_user` (`user_id`),
  KEY `idx_chat_messages_created` (`created_at`),
  KEY `idx_chat_messages_deleted` (`deleted_at`),
  FULLTEXT KEY `ft_chat_messages_body` (`body`),
  CONSTRAINT `fk_chat_messages_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_channel` FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_thread` FOREIGN KEY (`thread_parent_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_messages_deleted_by` FOREIGN KEY (`deleted_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_message_edits` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `edited_by` BIGINT UNSIGNED NOT NULL,
  `previous_body` MEDIUMTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_message_edits_message` (`message_id`),
  CONSTRAINT `fk_chat_message_edits_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_message_edits_user` FOREIGN KEY (`edited_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_message_reads` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `read_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_message_reads` (`message_id`, `user_id`),
  KEY `idx_chat_message_reads_user` (`user_id`),
  CONSTRAINT `fk_chat_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_message_reads_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_reactions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `emoji` VARCHAR(32) NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_reactions` (`message_id`, `user_id`, `emoji`),
  KEY `idx_chat_reactions_user` (`user_id`),
  CONSTRAINT `fk_chat_reactions_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_reactions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_mentions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `mentioned_user_id` BIGINT UNSIGNED DEFAULT NULL,
  `mention_type` ENUM('user','channel','here') NOT NULL DEFAULT 'user',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_mentions_message` (`message_id`),
  KEY `idx_chat_mentions_user` (`mentioned_user_id`),
  CONSTRAINT `fk_chat_mentions_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_mentions_user` FOREIGN KEY (`mentioned_user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_attachments` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED NOT NULL,
  `original_filename` VARCHAR(255) NOT NULL,
  `stored_filename` VARCHAR(255) NOT NULL,
  `relative_path` VARCHAR(500) NOT NULL,
  `mime_type` VARCHAR(120) NOT NULL,
  `extension` VARCHAR(20) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `virus_scan_status` ENUM('pending','clean','infected','skipped') NOT NULL DEFAULT 'skipped',
  `preview_type` ENUM('none','image','pdf','text','office') NOT NULL DEFAULT 'none',
  `width` INT UNSIGNED DEFAULT NULL,
  `height` INT UNSIGNED DEFAULT NULL,
  `meta` JSON DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_attachments_uuid` (`uuid`),
  KEY `idx_chat_attachments_company` (`company_id`),
  KEY `idx_chat_attachments_message` (`message_id`),
  KEY `idx_chat_attachments_uploader` (`uploaded_by`),
  KEY `idx_chat_attachments_filename` (`original_filename`),
  CONSTRAINT `fk_chat_attachments_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_attachments_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_attachments_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_pins` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `conversation_id` BIGINT UNSIGNED DEFAULT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `pinned_by` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_pins_message` (`message_id`),
  KEY `idx_chat_pins_channel` (`channel_id`),
  KEY `idx_chat_pins_conversation` (`conversation_id`),
  CONSTRAINT `fk_chat_pins_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_pins_channel` FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_pins_conversation` FOREIGN KEY (`conversation_id`) REFERENCES `chat_conversations` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_pins_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_pins_user` FOREIGN KEY (`pinned_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_saved_messages` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_saved` (`user_id`, `message_id`),
  KEY `idx_chat_saved_message` (`message_id`),
  CONSTRAINT `fk_chat_saved_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_saved_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_typing` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `conversation_id` BIGINT UNSIGNED DEFAULT NULL,
  `expires_at` DATETIME NOT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_typing_target` (`user_id`, `channel_id`, `conversation_id`),
  KEY `idx_chat_typing_expires` (`expires_at`),
  CONSTRAINT `fk_chat_typing_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_typing_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_presence` (
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `status` ENUM('online','away','offline') NOT NULL DEFAULT 'online',
  `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `current_channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `current_conversation_id` BIGINT UNSIGNED DEFAULT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`),
  KEY `idx_chat_presence_company` (`company_id`),
  KEY `idx_chat_presence_seen` (`last_seen_at`),
  CONSTRAINT `fk_chat_presence_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_presence_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_notification_preferences` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `channel_id` BIGINT UNSIGNED DEFAULT NULL,
  `notify_dms` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_mentions` TINYINT(1) NOT NULL DEFAULT 1,
  `notify_channel_messages` TINYINT(1) NOT NULL DEFAULT 1,
  `quiet_hours_start` TIME DEFAULT NULL,
  `quiet_hours_end` TIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_notif_prefs` (`user_id`, `company_id`, `channel_id`),
  CONSTRAINT `fk_chat_notif_prefs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_notif_prefs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_notif_prefs_channel` FOREIGN KEY (`channel_id`) REFERENCES `chat_channels` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_reports` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED NOT NULL,
  `reported_by` BIGINT UNSIGNED NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` ENUM('open','reviewed','dismissed','actioned') NOT NULL DEFAULT 'open',
  `reviewed_by` BIGINT UNSIGNED DEFAULT NULL,
  `reviewed_at` DATETIME DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_reports_company` (`company_id`),
  KEY `idx_chat_reports_status` (`status`),
  CONSTRAINT `fk_chat_reports_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_reports_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_reports_reporter` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_settings` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(150) NOT NULL,
  `setting_value` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_settings` (`company_id`, `setting_key`),
  CONSTRAINT `fk_chat_settings_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Shared HR documents (versioned)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `chat_document_categories` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_doc_cats` (`company_id`, `slug`),
  CONSTRAINT `fk_chat_doc_cats_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_shared_documents` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `uuid` CHAR(36) NOT NULL,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `category_id` BIGINT UNSIGNED DEFAULT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT DEFAULT NULL,
  `requires_acknowledgement` TINYINT(1) NOT NULL DEFAULT 0,
  `current_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_by` BIGINT UNSIGNED DEFAULT NULL,
  `is_published` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_shared_docs_uuid` (`uuid`),
  KEY `idx_chat_shared_docs_company` (`company_id`),
  KEY `idx_chat_shared_docs_category` (`category_id`),
  CONSTRAINT `fk_chat_shared_docs_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_shared_docs_category` FOREIGN KEY (`category_id`) REFERENCES `chat_document_categories` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_shared_docs_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_document_versions` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `attachment_id` BIGINT UNSIGNED DEFAULT NULL,
  `original_filename` VARCHAR(255) DEFAULT NULL,
  `stored_filename` VARCHAR(255) DEFAULT NULL,
  `relative_path` VARCHAR(500) DEFAULT NULL,
  `mime_type` VARCHAR(120) DEFAULT NULL,
  `file_size` BIGINT UNSIGNED DEFAULT NULL,
  `change_notes` VARCHAR(500) DEFAULT NULL,
  `uploaded_by` BIGINT UNSIGNED DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_doc_versions` (`document_id`, `version_number`),
  CONSTRAINT `fk_chat_doc_versions_doc` FOREIGN KEY (`document_id`) REFERENCES `chat_shared_documents` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_doc_versions_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `chat_attachments` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_doc_versions_uploader` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_document_acknowledgements` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `document_id` BIGINT UNSIGNED NOT NULL,
  `version_number` INT UNSIGNED NOT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `message_id` BIGINT UNSIGNED DEFAULT NULL,
  `acknowledged_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_doc_acks` (`document_id`, `version_number`, `user_id`),
  KEY `idx_chat_doc_acks_user` (`user_id`),
  CONSTRAINT `fk_chat_doc_acks_doc` FOREIGN KEY (`document_id`) REFERENCES `chat_shared_documents` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_doc_acks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_chat_doc_acks_message` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `chat_file_download_logs` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `attachment_id` BIGINT UNSIGNED DEFAULT NULL,
  `document_version_id` BIGINT UNSIGNED DEFAULT NULL,
  `user_id` BIGINT UNSIGNED NOT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `user_agent` VARCHAR(500) DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_chat_dl_logs_attachment` (`attachment_id`),
  KEY `idx_chat_dl_logs_user` (`user_id`),
  CONSTRAINT `fk_chat_dl_logs_attachment` FOREIGN KEY (`attachment_id`) REFERENCES `chat_attachments` (`id`) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT `fk_chat_dl_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- =============================================================================
-- Work Activity Monitoring (Mode 2)
-- =============================================================================

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

CREATE TABLE IF NOT EXISTS `password_reset_attempts` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email` VARCHAR(190) NOT NULL,
  `ip_address` VARCHAR(45) NOT NULL,
  `requested_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_password_reset_attempts_email_time` (`email`, `requested_at`),
  KEY `idx_password_reset_attempts_ip_time` (`ip_address`, `requested_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `monitoring_agent_versions` (`version`, `platform`, `release_notes`, `is_mandatory`, `is_active`, `released_at`)
VALUES ('0.1.0', 'windows', 'Initial Windows agent scaffold for Mode 2 monitoring.', 0, 1, NOW());

-- Attendance check-in device security (server-issued token hashes only).
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
FROM `companies` c CROSS JOIN (
  SELECT 'attendance_security_mode' setting_key, 'device_only' setting_value, 'string' value_type, 'Controls check-in security: disabled, device_only, ip_only, or device_and_ip.' description
  UNION ALL SELECT 'attendance_device_registration_policy', 'auto_first', 'string', 'auto_first approves the first check-in device; admin_approval requires approval before first use.'
  UNION ALL SELECT 'attendance_device_change_requires_approval', '1', 'boolean', 'Require an administrator to approve replacement attendance devices.'
  UNION ALL SELECT 'attendance_ip_source', 'office', 'string', 'Choose office or approved IP list when IP validation is enabled.'
  UNION ALL SELECT 'attendance_office_ip_addresses', '', 'text', 'Allowed office public IP addresses or CIDR ranges, separated by commas or new lines.'
  UNION ALL SELECT 'attendance_approved_ip_addresses', '', 'text', 'Additional approved IP addresses or CIDR ranges, separated by commas or new lines.'
  UNION ALL SELECT 'attendance_log_failed_attempts', '1', 'boolean', 'Record blocked attendance security attempts.'
) defaults
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` s WHERE s.company_id = c.id AND s.setting_key = defaults.setting_key);

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- End of schema
-- =============================================================================
