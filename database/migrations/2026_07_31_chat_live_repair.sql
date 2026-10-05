-- Team Chat live repair: ensure core tables exist (safe to re-run).
-- Prefer full import of 2026_07_28_team_chat.sql first; this only covers common gaps.

CREATE TABLE IF NOT EXISTS `chat_roles` (
  `id` TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `slug` VARCHAR(50) NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_chat_roles_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `chat_roles` (`id`, `slug`, `name`) VALUES
  (1, 'owner', 'Owner'),
  (2, 'admin', 'Admin'),
  (3, 'member', 'Member'),
  (4, 'guest', 'Guest');

-- Grant chat permissions to system roles (super_admin uses * already)
INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Access Team Chat', 'chat.access', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.access');

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Direct messages', 'chat.direct_message', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.direct_message');

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Post messages', 'chat.post_message', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.post_message');

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Create channels', 'chat.create_channel', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.create_channel');

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Join public channels', 'chat.join_public_channel', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.join_public_channel');

INSERT IGNORE INTO `permissions` (`module`, `name`, `slug`, `created_at`, `updated_at`)
SELECT 'chat', 'Upload files', 'chat.upload_file', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM `permissions` WHERE `slug` = 'chat.upload_file');

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager', 'department_manager', 'employee')
  AND r.deleted_at IS NULL
  AND p.slug IN (
    'chat.access', 'chat.direct_message', 'chat.post_message',
    'chat.join_public_channel', 'chat.upload_file'
  );

INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.slug IN ('super_admin', 'company_admin', 'hr_manager')
  AND r.deleted_at IS NULL
  AND p.slug IN ('chat.create_channel', 'chat.manage_channel', 'chat.use_mass_mentions', 'chat.manage_settings');
