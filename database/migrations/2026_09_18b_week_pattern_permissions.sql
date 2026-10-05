-- Permissions for the weekly-pattern admin CRUD (AttendanceWeekPatternController),
-- added separately from 2026_09_18_weekly_schedule_patterns.sql since that
-- migration was already applied before these were introduced.

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `description`) VALUES
('attendance', 'View weekly schedule patterns', 'attendance.pattern.view', 'View weekly schedule patterns'),
('attendance', 'Manage weekly schedule patterns', 'attendance.pattern.manage', 'Create, edit and assign weekly schedule patterns');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id FROM `roles` r CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager')
  AND p.slug IN ('attendance.pattern.view', 'attendance.pattern.manage');
