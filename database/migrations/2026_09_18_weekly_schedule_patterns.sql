-- Configurable weekly schedule patterns (full/half/off per weekday), resolved
-- per employee with this precedence: designation-level "manager or above"
-- flag > department-level pattern assignment > company default pattern.
--
-- Replaces the previous hardcoded assumption (Mon-Fri full, Sat+Sun off)
-- baked into shifts.working_days with a richer, admin-configurable model
-- that also supports HALF days (e.g. "Saturday is a half day, not a full
-- rest day" for most staff, while managers get Sat+Sun off, and specific
-- departments get a swapped Sat-full/Mon-half pattern).
--
-- shifts.working_days (added in 2026_09_17_attendance_reporting_engine.sql)
-- is left in place as a lower-priority fallback for any company that hasn't
-- configured patterns yet — see AttendanceCalculationService::resolveWeekPattern().

CREATE TABLE IF NOT EXISTS `attendance_week_patterns` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_id` BIGINT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Company-wide fallback pattern when no designation/department override applies',
  `is_manager_pattern` TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Applied to any employee whose designation.is_manager_or_above = 1',
  `monday_type` ENUM('full','half','off') NOT NULL DEFAULT 'full',
  `tuesday_type` ENUM('full','half','off') NOT NULL DEFAULT 'full',
  `wednesday_type` ENUM('full','half','off') NOT NULL DEFAULT 'full',
  `thursday_type` ENUM('full','half','off') NOT NULL DEFAULT 'full',
  `friday_type` ENUM('full','half','off') NOT NULL DEFAULT 'full',
  `saturday_type` ENUM('full','half','off') NOT NULL DEFAULT 'half',
  `sunday_type` ENUM('full','half','off') NOT NULL DEFAULT 'off',
  `created_by` BIGINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `deleted_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_week_patterns_company` (`company_id`),
  KEY `idx_week_patterns_company_default` (`company_id`, `is_default`),
  KEY `idx_week_patterns_company_manager` (`company_id`, `is_manager_pattern`),
  CONSTRAINT `fk_week_patterns_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'designations' AND COLUMN_NAME = 'is_manager_or_above'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `designations` ADD COLUMN `is_manager_or_above` TINYINT(1) NOT NULL DEFAULT 0 COMMENT ''Managers and above get the company''''s manager weekly pattern (e.g. Sat+Sun off) instead of the department/default pattern'' AFTER `level`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @col_exists2 := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND COLUMN_NAME = 'week_pattern_id'
);
SET @sql2 := IF(@col_exists2 = 0,
    'ALTER TABLE `departments` ADD COLUMN `week_pattern_id` BIGINT UNSIGNED NULL AFTER `parent_id`',
    'SELECT 1'
);
PREPARE stmt2 FROM @sql2;
EXECUTE stmt2;
DEALLOCATE PREPARE stmt2;

SET @fk_exists := (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'departments' AND CONSTRAINT_NAME = 'fk_departments_week_pattern'
);
SET @sql3 := IF(@fk_exists = 0,
    'ALTER TABLE `departments` ADD CONSTRAINT `fk_departments_week_pattern` FOREIGN KEY (`week_pattern_id`) REFERENCES `attendance_week_patterns` (`id`) ON DELETE SET NULL',
    'SELECT 1'
);
PREPARE stmt3 FROM @sql3;
EXECUTE stmt3;
DEALLOCATE PREPARE stmt3;

-- Seed the three patterns described by HR for every existing company, and
-- wire up the demo data: HR Manager designation -> manager pattern;
-- Human Resources department (the "result team" / "data entry team") ->
-- the Sat-full/Mon-half pattern. INSERT IGNORE + NOT EXISTS guards make this
-- safe to re-run and leave any already-customized company untouched.

INSERT INTO `attendance_week_patterns`
  (`company_id`, `name`, `is_default`, `is_manager_pattern`,
   `monday_type`, `tuesday_type`, `wednesday_type`, `thursday_type`, `friday_type`, `saturday_type`, `sunday_type`)
SELECT c.id, 'Standard (Saturday half day)', 1, 0, 'full','full','full','full','full','half','off'
FROM `companies` c
WHERE NOT EXISTS (SELECT 1 FROM `attendance_week_patterns` p WHERE p.company_id = c.id AND p.is_default = 1);

INSERT INTO `attendance_week_patterns`
  (`company_id`, `name`, `is_default`, `is_manager_pattern`,
   `monday_type`, `tuesday_type`, `wednesday_type`, `thursday_type`, `friday_type`, `saturday_type`, `sunday_type`)
SELECT c.id, 'Manager (weekend off)', 0, 1, 'full','full','full','full','full','off','off'
FROM `companies` c
WHERE NOT EXISTS (SELECT 1 FROM `attendance_week_patterns` p WHERE p.company_id = c.id AND p.is_manager_pattern = 1);

INSERT INTO `attendance_week_patterns`
  (`company_id`, `name`, `is_default`, `is_manager_pattern`,
   `monday_type`, `tuesday_type`, `wednesday_type`, `thursday_type`, `friday_type`, `saturday_type`, `sunday_type`)
SELECT c.id, 'Result & Data Entry Team (Sat full, Mon half)', 0, 0, 'half','full','full','full','full','full','off'
FROM `companies` c
WHERE NOT EXISTS (
    SELECT 1 FROM `attendance_week_patterns` p
    WHERE p.company_id = c.id AND p.name = 'Result & Data Entry Team (Sat full, Mon half)'
);

UPDATE `designations` SET `is_manager_or_above` = 1
WHERE `name` LIKE '%manager%' AND `is_manager_or_above` = 0;

UPDATE `departments` d
INNER JOIN `attendance_week_patterns` p
    ON p.company_id = d.company_id AND p.name = 'Result & Data Entry Team (Sat full, Mon half)'
SET d.`week_pattern_id` = p.id
WHERE d.`name` = 'Human Resources' AND d.`week_pattern_id` IS NULL;
