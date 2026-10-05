-- =============================================================================
-- Team Chat permission fix for live (safe to re-run)
-- Use when /chat returns 403 or nav hides Team Chat after tables exist.
-- Grants chat.* to system roles AND company-scoped role copies (same slug).
-- =============================================================================

SET NAMES utf8mb4;

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`) VALUES
('chat', 'Access team chat', 'chat.access'),
('chat', 'Send direct messages', 'chat.direct_message'),
('chat', 'Create channels', 'chat.create_channel'),
('chat', 'Manage channels', 'chat.manage_channel'),
('chat', 'Join public channels', 'chat.join_public_channel'),
('chat', 'Invite channel members', 'chat.invite_members'),
('chat', 'Remove channel members', 'chat.remove_members'),
('chat', 'Post messages', 'chat.post_message'),
('chat', 'Edit own messages', 'chat.edit_own_message'),
('chat', 'Delete own messages', 'chat.delete_own_message'),
('chat', 'Delete any message', 'chat.delete_any_message'),
('chat', 'Upload files', 'chat.upload_file'),
('chat', 'Download files', 'chat.download_file'),
('chat', 'Pin messages', 'chat.pin_message'),
('chat', 'Use mass mentions', 'chat.use_mass_mentions'),
('chat', 'View chat audit logs', 'chat.view_audit_logs'),
('chat', 'Manage chat settings', 'chat.manage_settings');

-- Super admin / company admin: all chat permissions (global + company-scoped roles)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin')
  AND r.deleted_at IS NULL
  AND p.module = 'chat';

-- HR manager: all chat except manage_settings
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'hr_manager'
  AND r.deleted_at IS NULL
  AND p.module = 'chat'
  AND p.slug NOT IN ('chat.manage_settings');

-- Department manager
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'department_manager'
  AND r.deleted_at IS NULL
  AND p.slug IN (
    'chat.access','chat.direct_message','chat.create_channel','chat.join_public_channel',
    'chat.invite_members','chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file','chat.pin_message'
  );

-- Accountant + employee baseline
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('accountant', 'employee')
  AND r.deleted_at IS NULL
  AND p.slug IN (
    'chat.access','chat.direct_message','chat.join_public_channel',
    'chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file'
  );

-- Safety net: any active role that can open the dashboard gets chat.access + messaging basics
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT r.id, p.id
FROM `roles` r
INNER JOIN `role_permissions` rp ON rp.role_id = r.id
INNER JOIN `permissions` dp ON dp.id = rp.permission_id AND dp.slug = 'dashboard.view'
CROSS JOIN `permissions` p
WHERE r.deleted_at IS NULL
  AND r.is_active = 1
  AND p.slug IN (
    'chat.access','chat.direct_message','chat.join_public_channel',
    'chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file'
  );
