-- Team Chat unification: org-wide membership for all company users (users.id).
-- Idempotent — safe to re-run.
-- Realtime remains AJAX polling (no WebSockets).

-- Ensure readonly chat role exists (can_post = 0) for announcement channels.
INSERT IGNORE INTO `chat_roles` (`id`, `slug`, `name`, `can_manage`, `can_invite`, `can_post`)
SELECT 4, 'readonly', 'Read Only', 0, 0, 0
WHERE NOT EXISTS (SELECT 1 FROM `chat_roles` WHERE `slug` = 'readonly');

UPDATE `chat_roles`
SET `can_post` = 0, `name` = 'Read Only'
WHERE `slug` IN ('readonly', 'guest') AND `can_post` <> 0;

-- ---------------------------------------------------------------------------
-- Org-wide channels: add every active company user (employees + role-only admins)
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `chat_channel_members` (`channel_id`, `user_id`, `role_id`, `joined_at`)
SELECT c.id,
       u.id,
       CASE
         WHEN c.channel_type = 'announcement'
              AND NOT EXISTS (
                SELECT 1 FROM user_roles ur
                INNER JOIN roles r ON r.id = ur.role_id
                WHERE ur.user_id = u.id
                  AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
                  AND r.slug IN ('super_admin', 'company_admin', 'hr_manager')
              )
         THEN COALESCE((SELECT id FROM chat_roles WHERE slug = 'readonly' LIMIT 1), 4)
         WHEN EXISTS (
           SELECT 1 FROM user_roles ur
           INNER JOIN roles r ON r.id = ur.role_id
           WHERE ur.user_id = u.id
             AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
             AND r.slug IN ('super_admin', 'company_admin', 'hr_manager')
         )
         THEN COALESCE((SELECT id FROM chat_roles WHERE slug = 'admin' LIMIT 1), 2)
         ELSE COALESCE((SELECT id FROM chat_roles WHERE slug = 'member' LIMIT 1), 3)
       END,
       NOW()
FROM chat_channels c
INNER JOIN (
  SELECT DISTINCT e.company_id, e.user_id AS user_id
  FROM employees e
  INNER JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL AND u.is_active = 1
  WHERE e.deleted_at IS NULL
    AND e.user_id IS NOT NULL
    AND e.employment_status IN ('active', 'probation', 'notice_period')
  UNION
  SELECT DISTINCT ur.company_id, ur.user_id
  FROM user_roles ur
  INNER JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.is_active = 1
  WHERE ur.company_id IS NOT NULL
  UNION
  -- Global role users (company_id NULL) belong to every company for org-wide chat
  SELECT DISTINCT co.id AS company_id, ur.user_id
  FROM user_roles ur
  INNER JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.is_active = 1
  CROSS JOIN companies co
  WHERE ur.company_id IS NULL AND co.deleted_at IS NULL
) peeps ON peeps.company_id = c.company_id
INNER JOIN users u ON u.id = peeps.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.deleted_at IS NULL
  AND c.slug IN ('general', 'company-announcements', 'hr-announcements', 'payroll-announcements')
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members m
    WHERE m.channel_id = c.id AND m.user_id = u.id
  );

-- Re-activate soft-left memberships on org-wide defaults
UPDATE chat_channel_members m
INNER JOIN chat_channels c ON c.id = m.channel_id
SET m.left_at = NULL,
    m.joined_at = COALESCE(m.joined_at, NOW())
WHERE m.left_at IS NOT NULL
  AND c.deleted_at IS NULL
  AND c.slug IN ('general', 'company-announcements', 'hr-announcements', 'payroll-announcements');

-- Elevate company-scope admins on announcement channels
UPDATE chat_channel_members m
INNER JOIN chat_channels c ON c.id = m.channel_id
INNER JOIN user_roles ur ON ur.user_id = m.user_id AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
INNER JOIN roles r ON r.id = ur.role_id AND r.slug IN ('super_admin', 'company_admin', 'hr_manager')
SET m.role_id = COALESCE((SELECT id FROM chat_roles WHERE slug = 'admin' LIMIT 1), 2),
    m.left_at = NULL
WHERE c.deleted_at IS NULL
  AND c.channel_type = 'announcement'
  AND c.slug IN ('company-announcements', 'hr-announcements', 'payroll-announcements')
  AND m.role_id > 2;

-- ---------------------------------------------------------------------------
-- Branch / department channels for matching employees
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `chat_channel_members` (`channel_id`, `user_id`, `role_id`, `joined_at`)
SELECT c.id, e.user_id,
       COALESCE((SELECT id FROM chat_roles WHERE slug = 'member' LIMIT 1), 3),
       NOW()
FROM chat_channels c
INNER JOIN employees e
  ON e.company_id = c.company_id
 AND e.deleted_at IS NULL
 AND e.user_id IS NOT NULL
 AND e.employment_status IN ('active', 'probation', 'notice_period')
 AND e.branch_id = c.branch_id
INNER JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.deleted_at IS NULL AND c.scope = 'branch' AND c.branch_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members m
    WHERE m.channel_id = c.id AND m.user_id = e.user_id
  );

INSERT IGNORE INTO `chat_channel_members` (`channel_id`, `user_id`, `role_id`, `joined_at`)
SELECT c.id, e.user_id,
       COALESCE((SELECT id FROM chat_roles WHERE slug = 'member' LIMIT 1), 3),
       NOW()
FROM chat_channels c
INNER JOIN employees e
  ON e.company_id = c.company_id
 AND e.deleted_at IS NULL
 AND e.user_id IS NOT NULL
 AND e.employment_status IN ('active', 'probation', 'notice_period')
 AND e.department_id = c.department_id
INNER JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.deleted_at IS NULL AND c.scope = 'department' AND c.department_id IS NOT NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members m
    WHERE m.channel_id = c.id AND m.user_id = e.user_id
  );

-- Company-scope admins get every branch/dept channel
INSERT IGNORE INTO `chat_channel_members` (`channel_id`, `user_id`, `role_id`, `joined_at`)
SELECT c.id, u.id,
       COALESCE((SELECT id FROM chat_roles WHERE slug = 'admin' LIMIT 1), 2),
       NOW()
FROM chat_channels c
INNER JOIN user_roles ur ON (ur.company_id = c.company_id OR ur.company_id IS NULL)
INNER JOIN roles r ON r.id = ur.role_id AND r.slug IN ('super_admin', 'company_admin', 'hr_manager')
INNER JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.deleted_at IS NULL
  AND c.scope IN ('branch', 'department')
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members m
    WHERE m.channel_id = c.id AND m.user_id = u.id
  );

-- ---------------------------------------------------------------------------
-- Repair conversation/channel members that accidentally stored employees.id
-- as user_id (only when that id is not a real users.id).
-- Uses derived tables to avoid MySQL 1093 (can't update target from subquery).
-- ---------------------------------------------------------------------------
UPDATE chat_conversation_members cm
INNER JOIN (
  SELECT member_row_id, real_user_id FROM (
    SELECT cm1.id AS member_row_id, e.user_id AS real_user_id
    FROM chat_conversation_members cm1
    INNER JOIN employees e ON e.id = cm1.user_id AND e.user_id IS NOT NULL
    LEFT JOIN users u_bad ON u_bad.id = cm1.user_id
    WHERE u_bad.id IS NULL
      AND e.user_id <> cm1.user_id
      AND NOT EXISTS (
        SELECT 1 FROM chat_conversation_members cm2
        WHERE cm2.conversation_id = cm1.conversation_id
          AND cm2.user_id = e.user_id
      )
  ) AS conv_fix_inner
) AS conv_fix ON conv_fix.member_row_id = cm.id
SET cm.user_id = conv_fix.real_user_id;

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT member_row_id, real_user_id FROM (
    SELECT cm1.id AS member_row_id, e.user_id AS real_user_id
    FROM chat_channel_members cm1
    INNER JOIN employees e ON e.id = cm1.user_id AND e.user_id IS NOT NULL
    LEFT JOIN users u_bad ON u_bad.id = cm1.user_id
    WHERE u_bad.id IS NULL
      AND e.user_id <> cm1.user_id
      AND NOT EXISTS (
        SELECT 1 FROM chat_channel_members cm2
        WHERE cm2.channel_id = cm1.channel_id
          AND cm2.user_id = e.user_id
      )
  ) AS ch_fix_inner
) AS ch_fix ON ch_fix.member_row_id = cm.id
SET cm.user_id = ch_fix.real_user_id;

-- Soft-remove orphan membership rows that still point at non-users and couldn't be remapped
UPDATE chat_conversation_members cm
LEFT JOIN users u ON u.id = cm.user_id
SET cm.left_at = NOW()
WHERE u.id IS NULL AND cm.left_at IS NULL;

UPDATE chat_channel_members cm
LEFT JOIN users u ON u.id = cm.user_id
SET cm.left_at = NOW()
WHERE u.id IS NULL AND cm.left_at IS NULL;
