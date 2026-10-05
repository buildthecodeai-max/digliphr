-- =============================================================================
-- Team Chat / Slack-like collaboration (Phases 1–4)
-- Safe to re-run on live (CREATE IF NOT EXISTS + INSERT IGNORE)
-- =============================================================================

SET NAMES utf8mb4;

-- -----------------------------------------------------------------------------
-- Permissions
-- -----------------------------------------------------------------------------
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

-- Role defaults for existing roles (global + company-scoped copies with the same slug)
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('super_admin', 'company_admin')
  AND p.module = 'chat';

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'hr_manager'
  AND p.module = 'chat'
  AND p.slug NOT IN ('chat.manage_settings');

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug = 'department_manager'
  AND p.slug IN (
    'chat.access','chat.direct_message','chat.create_channel','chat.join_public_channel',
    'chat.invite_members','chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file','chat.pin_message'
  );

INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.slug IN ('accountant', 'employee')
  AND p.slug IN (
    'chat.access','chat.direct_message','chat.join_public_channel',
    'chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file'
  );

-- Safety net: roles that already have dashboard.view get baseline chat access
INSERT IGNORE INTO `role_permissions` (`role_id`, `permission_id`)
SELECT DISTINCT r.id, p.id
FROM `roles` r
INNER JOIN `role_permissions` rp ON rp.role_id = r.id
INNER JOIN `permissions` dp ON dp.id = rp.permission_id AND dp.slug = 'dashboard.view'
CROSS JOIN `permissions` p
WHERE p.slug IN (
    'chat.access','chat.direct_message','chat.join_public_channel',
    'chat.post_message','chat.edit_own_message','chat.delete_own_message',
    'chat.upload_file','chat.download_file'
  );

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

-- Default chat settings for existing companies
INSERT IGNORE INTO `chat_settings` (`company_id`, `setting_key`, `setting_value`)
SELECT c.id, 'polling_interval_seconds', '5'
FROM `companies` c;

INSERT IGNORE INTO `chat_settings` (`company_id`, `setting_key`, `setting_value`)
SELECT c.id, 'max_upload_bytes', '10485760'
FROM `companies` c;

INSERT IGNORE INTO `chat_settings` (`company_id`, `setting_key`, `setting_value`)
SELECT c.id, 'allow_mass_mentions', '1'
FROM `companies` c;
