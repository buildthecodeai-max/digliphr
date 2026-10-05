-- Attendance Intelligence & Reporting module.
--
-- 1) shifts.working_days: lets a shift declare its own working week instead of
--    the app hardcoding Sat/Sun as the weekend in three separate places
--    (LeaveService, cron/run.php, PayrollService). NULL preserves today's
--    behavior exactly (Mon-Fri, ISO weekdays 1-5) for every existing shift,
--    so this is purely additive and changes no current calculation.
-- 2) New report-scoping permissions: attendance.report.all_employees /
--    .department / .branch control which employees a viewer's attendance
--    report/export may include. Existing attendance.*/reports.* permissions
--    already gate view/export/correct actions, so only the new scoping
--    dimension is added here (see AuthService::can() — permission keys with
--    3 segments are NOT covered by a 2-segment "module.*" wildcard, so each
--    role that should have them is granted explicitly below, matching the
--    precedent set by attendance.device.* in 2026_09_10_attendance_device_security.sql).

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'shifts' AND COLUMN_NAME = 'working_days'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE `shifts` ADD COLUMN `working_days` VARCHAR(20) NULL COMMENT ''Comma-separated ISO weekdays (1=Mon..7=Sun) this shift works; NULL = Mon-Fri default'' AFTER `is_overnight`',
    'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `description`) VALUES
('attendance', 'View all-employee attendance reports', 'attendance.report.all_employees', 'View attendance reports and exports across every employee in the company'),
('attendance', 'View department attendance reports', 'attendance.report.department', 'View attendance reports scoped to the manager''s own department/team'),
('attendance', 'View branch attendance reports', 'attendance.report.branch', 'View attendance reports scoped to the manager''s own branch');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager')
  AND p.slug IN ('attendance.report.all_employees', 'attendance.report.department', 'attendance.report.branch');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug = 'department_manager'
  AND p.slug = 'attendance.report.department';
