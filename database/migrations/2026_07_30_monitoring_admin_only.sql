-- =============================================================================
-- Work Activity Monitoring — Admin-only portal (live patch)
-- Safe to re-run (INSERT IGNORE + DELETE scoped to employee monitoring grants)
-- =============================================================================
-- Removes employee-portal monitoring permissions from live DBs that already ran
-- 2026_07_30_work_activity_monitoring.sql, and adds monitoring.assign_policies
-- for Super Admin / Company Admin / HR Manager.
-- =============================================================================

SET NAMES utf8mb4;

-- New permission: assign policies (separate from manage/create)
INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`) VALUES
('monitoring', 'Assign monitoring policies', 'monitoring.assign_policies');

-- Grant assign_policies to admin roles that manage policies
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'super_admin'
  AND p.slug = 'monitoring.assign_policies';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'company_admin'
  AND p.slug = 'monitoring.assign_policies';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'hr_manager'
  AND p.slug = 'monitoring.assign_policies';

-- Ensure Mode-2 admin grants remain present (idempotent)
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
    'monitoring.view_recording_audit',
    'monitoring.view_own_activity'
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

-- Revoke ALL monitoring.* permissions from the employee role (admin-only UI)
DELETE rp
FROM `role_permissions` rp
INNER JOIN `roles` r ON r.id = rp.role_id
INNER JOIN `permissions` p ON p.id = rp.permission_id
WHERE r.slug = 'employee'
  AND p.module = 'monitoring';
