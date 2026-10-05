-- Team Chat: channel message delivery repair (admin → employee).
-- Idempotent — safe to re-run.
-- Realtime remains AJAX polling (/api/chat/poll) — no WebSockets.
-- Identity: chat_channel_members.user_id / chat_messages.user_id = users.id only.
-- MySQL-safe: double-nested derived tables avoid Error 1093.
--
-- ============================================================================
-- DRY-RUN SELECTs (inspect only — do not modify)
-- ============================================================================
--
-- Duplicate org channels by (company_id, slug):
--   SELECT company_id, slug, COUNT(*) AS cnt, GROUP_CONCAT(id ORDER BY id) AS channel_ids
--   FROM chat_channels
--   WHERE deleted_at IS NULL
--   GROUP BY company_id, slug
--   HAVING COUNT(*) > 1;
--
-- Org-wide slug membership gaps (active company users missing from canonical channel):
--   SELECT u.id AS user_id, u.email, c.id AS channel_id, c.slug
--   FROM users u
--   INNER JOIN chat_channels c
--     ON c.slug IN ('general','company-announcements','hr-announcements','payroll-announcements')
--    AND c.deleted_at IS NULL
--   WHERE u.deleted_at IS NULL AND u.is_active = 1
--     AND (
--       EXISTS (
--         SELECT 1 FROM employees e
--         WHERE e.user_id = u.id AND e.company_id = c.company_id AND e.deleted_at IS NULL
--           AND e.employment_status IN ('active','probation','notice_period')
--       )
--       OR EXISTS (
--         SELECT 1 FROM user_roles ur
--         WHERE ur.user_id = u.id AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
--       )
--     )
--     AND NOT EXISTS (
--       SELECT 1 FROM chat_channel_members cm
--       WHERE cm.channel_id = c.id AND cm.user_id = u.id AND cm.left_at IS NULL
--     );
--
-- Stale channel members stored as employees.id (non-colliding / orphan):
--   SELECT cm.channel_id, cm.user_id AS stored_id, e.user_id AS canonical_user_id
--   FROM chat_channel_members cm
--   INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
--   LEFT JOIN users u_same ON u_same.id = cm.user_id AND u_same.deleted_at IS NULL
--   WHERE cm.left_at IS NULL;
--
-- Peer-safe collision candidates (do NOT remap when stored id is a company peer):
--   SELECT cm.channel_id, c.company_id, cm.user_id AS stored_id, e.user_id AS mapped_user_id,
--          e_peer.id AS stored_is_employee, ur.id AS stored_has_role
--   FROM chat_channel_members cm
--   INNER JOIN chat_channels c ON c.id = cm.channel_id AND c.deleted_at IS NULL
--   INNER JOIN employees e
--     ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
--    AND e.company_id = c.company_id AND e.deleted_at IS NULL
--   LEFT JOIN employees e_peer
--     ON e_peer.user_id = cm.user_id AND e_peer.company_id = c.company_id AND e_peer.deleted_at IS NULL
--   LEFT JOIN user_roles ur
--     ON ur.user_id = cm.user_id AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
--   WHERE cm.left_at IS NULL;

-- ---------------------------------------------------------------------------
-- 1) Merge duplicate (company_id, slug) channels into the lowest id (canonical)
-- ---------------------------------------------------------------------------

UPDATE chat_messages m
INNER JOIN (
  SELECT * FROM (
    SELECT dup.id AS dup_id, keep.id AS keep_id
    FROM chat_channels dup
    INNER JOIN chat_channels keep
      ON keep.company_id = dup.company_id
     AND keep.slug = dup.slug
     AND keep.deleted_at IS NULL
     AND keep.id = (
       SELECT MIN(k.id) FROM chat_channels k
       WHERE k.company_id = dup.company_id AND k.slug = dup.slug AND k.deleted_at IS NULL
     )
    WHERE dup.deleted_at IS NULL AND dup.id > keep.id
  ) t
) map ON map.dup_id = m.channel_id
SET m.channel_id = map.keep_id;

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id, keep.id AS keep_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels dup ON dup.id = cm2.channel_id AND dup.deleted_at IS NULL
    INNER JOIN chat_channels keep
      ON keep.company_id = dup.company_id
     AND keep.slug = dup.slug
     AND keep.deleted_at IS NULL
     AND keep.id = (
       SELECT MIN(k.id) FROM chat_channels k
       WHERE k.company_id = dup.company_id AND k.slug = dup.slug AND k.deleted_at IS NULL
     )
    WHERE cm2.left_at IS NULL
      AND dup.id > keep.id
      AND NOT EXISTS (
        SELECT 1 FROM chat_channel_members cm_keep
        WHERE cm_keep.channel_id = keep.id
          AND cm_keep.user_id = cm2.user_id
          AND cm_keep.left_at IS NULL
      )
  ) t
) map ON map.member_row_id = cm.id
SET cm.channel_id = map.keep_id;

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels dup ON dup.id = cm2.channel_id AND dup.deleted_at IS NULL
    INNER JOIN chat_channels keep
      ON keep.company_id = dup.company_id
     AND keep.slug = dup.slug
     AND keep.deleted_at IS NULL
     AND keep.id = (
       SELECT MIN(k.id) FROM chat_channels k
       WHERE k.company_id = dup.company_id AND k.slug = dup.slug AND k.deleted_at IS NULL
     )
    INNER JOIN chat_channel_members cm_keep
      ON cm_keep.channel_id = keep.id
     AND cm_keep.user_id = cm2.user_id
     AND cm_keep.left_at IS NULL
    WHERE cm2.left_at IS NULL
      AND dup.id > keep.id
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NOW();

UPDATE chat_channels c
INNER JOIN (
  SELECT dup_id, new_slug FROM (
    SELECT dup.id AS dup_id, CONCAT(dup.slug, '-merged-', dup.id) AS new_slug
    FROM chat_channels dup
    INNER JOIN (
      SELECT company_id, slug, MIN(id) AS keep_id
      FROM chat_channels
      WHERE deleted_at IS NULL
      GROUP BY company_id, slug
      HAVING COUNT(*) > 1
    ) keep ON keep.company_id = dup.company_id AND keep.slug = dup.slug
    WHERE dup.deleted_at IS NULL AND dup.id > keep.keep_id
  ) t
) map ON map.dup_id = c.id
SET c.deleted_at = NOW(),
    c.updated_at = NOW(),
    c.slug = map.new_slug;

-- ---------------------------------------------------------------------------
-- 2) Remap channel members: pure employees.id → users.id (non-colliding)
-- ---------------------------------------------------------------------------
UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id, e.user_id AS new_uid
    FROM chat_channel_members cm2
    INNER JOIN employees e
      ON e.id = cm2.user_id AND e.user_id IS NOT NULL AND e.deleted_at IS NULL
     AND e.user_id <> cm2.user_id
    LEFT JOIN users u_as_user
      ON u_as_user.id = cm2.user_id AND u_as_user.deleted_at IS NULL
    WHERE cm2.left_at IS NULL
      AND u_as_user.id IS NULL
      AND NOT EXISTS (
        SELECT 1 FROM chat_channel_members cm3
        WHERE cm3.channel_id = cm2.channel_id
          AND cm3.user_id = e.user_id
          AND cm3.left_at IS NULL
      )
  ) t
) map ON map.member_row_id = cm.id
SET cm.user_id = map.new_uid;

-- ---------------------------------------------------------------------------
-- 3) Collision remap: ONLY when stored users.id is NOT a company peer
-- ---------------------------------------------------------------------------
UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id, e.user_id AS new_uid
    FROM chat_channel_members cm2
    INNER JOIN chat_channels c ON c.id = cm2.channel_id AND c.deleted_at IS NULL
    INNER JOIN employees e
      ON e.id = cm2.user_id
     AND e.user_id IS NOT NULL
     AND e.deleted_at IS NULL
     AND e.company_id = c.company_id
     AND e.user_id <> cm2.user_id
    LEFT JOIN employees e_peer
      ON e_peer.user_id = cm2.user_id
     AND e_peer.company_id = c.company_id
     AND e_peer.deleted_at IS NULL
    LEFT JOIN user_roles ur_peer
      ON ur_peer.user_id = cm2.user_id
     AND (ur_peer.company_id = c.company_id OR ur_peer.company_id IS NULL)
    WHERE cm2.left_at IS NULL
      AND e_peer.id IS NULL
      AND ur_peer.id IS NULL
      AND NOT EXISTS (
        SELECT 1 FROM chat_channel_members cm3
        WHERE cm3.channel_id = cm2.channel_id
          AND cm3.user_id = e.user_id
          AND cm3.left_at IS NULL
      )
  ) t
) map ON map.member_row_id = cm.id
SET cm.user_id = map.new_uid;

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN employees e
      ON e.id = cm2.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm2.user_id
    INNER JOIN chat_channel_members cm_ok
      ON cm_ok.channel_id = cm2.channel_id
     AND cm_ok.user_id = e.user_id
     AND cm_ok.left_at IS NULL
    LEFT JOIN employees e_peer
      ON e_peer.user_id = cm2.user_id AND e_peer.deleted_at IS NULL
    LEFT JOIN user_roles ur_peer
      ON ur_peer.user_id = cm2.user_id
    WHERE cm2.left_at IS NULL
      AND e_peer.id IS NULL
      AND ur_peer.id IS NULL
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NOW();

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    LEFT JOIN users u ON u.id = cm2.user_id
    WHERE cm2.left_at IS NULL AND u.id IS NULL
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NOW();

-- ---------------------------------------------------------------------------
-- 4) Sync missing org-wide memberships (users.id)
-- ---------------------------------------------------------------------------

INSERT INTO chat_channel_members (channel_id, user_id, role_id, joined_at)
SELECT c.id, e.user_id, 3, NOW()
FROM chat_channels c
INNER JOIN employees e
  ON e.company_id = c.company_id
 AND e.user_id IS NOT NULL
 AND e.deleted_at IS NULL
 AND e.employment_status IN ('active', 'probation', 'notice_period')
INNER JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.slug = 'general'
  AND c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members cm
    WHERE cm.channel_id = c.id AND cm.user_id = e.user_id
  );

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels c ON c.id = cm2.channel_id AND c.slug = 'general' AND c.deleted_at IS NULL
    INNER JOIN employees e
      ON e.user_id = cm2.user_id AND e.company_id = c.company_id
     AND e.deleted_at IS NULL
     AND e.employment_status IN ('active', 'probation', 'notice_period')
    WHERE cm2.left_at IS NOT NULL
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NULL,
    cm.joined_at = COALESCE(cm.joined_at, NOW()),
    cm.role_id = IF(cm.role_id IS NULL OR cm.role_id = 0, 3, cm.role_id);

INSERT INTO chat_channel_members (channel_id, user_id, role_id, joined_at)
SELECT c.id, e.user_id, 4, NOW()
FROM chat_channels c
INNER JOIN employees e
  ON e.company_id = c.company_id
 AND e.user_id IS NOT NULL
 AND e.deleted_at IS NULL
 AND e.employment_status IN ('active', 'probation', 'notice_period')
INNER JOIN users u ON u.id = e.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.slug IN ('company-announcements', 'hr-announcements', 'payroll-announcements')
  AND c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members cm
    WHERE cm.channel_id = c.id AND cm.user_id = e.user_id
  );

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels c
      ON c.id = cm2.channel_id
     AND c.slug IN ('company-announcements', 'hr-announcements', 'payroll-announcements')
     AND c.deleted_at IS NULL
    INNER JOIN employees e
      ON e.user_id = cm2.user_id AND e.company_id = c.company_id
     AND e.deleted_at IS NULL
     AND e.employment_status IN ('active', 'probation', 'notice_period')
    WHERE cm2.left_at IS NOT NULL
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NULL,
    cm.joined_at = COALESCE(cm.joined_at, NOW());

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels c
      ON c.id = cm2.channel_id
     AND c.slug IN ('company-announcements', 'hr-announcements', 'payroll-announcements')
     AND c.deleted_at IS NULL
    INNER JOIN employees e
      ON e.user_id = cm2.user_id AND e.company_id = c.company_id AND e.deleted_at IS NULL
    WHERE cm2.left_at IS NULL
      AND cm2.role_id <> 4
      AND NOT EXISTS (
        SELECT 1 FROM user_roles ur
        INNER JOIN roles r ON r.id = ur.role_id
        WHERE ur.user_id = cm2.user_id
          AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
          AND r.slug IN ('super_admin', 'company_admin', 'hr_manager')
      )
  ) t
) map ON map.member_row_id = cm.id
SET cm.role_id = 4;

INSERT INTO chat_channel_members (channel_id, user_id, role_id, joined_at)
SELECT DISTINCT c.id, u.id, 2, NOW()
FROM chat_channels c
INNER JOIN user_roles ur
  ON (ur.company_id = c.company_id OR ur.company_id IS NULL)
INNER JOIN users u ON u.id = ur.user_id AND u.deleted_at IS NULL AND u.is_active = 1
WHERE c.slug IN ('general', 'company-announcements', 'hr-announcements', 'payroll-announcements')
  AND c.deleted_at IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM employees e
    WHERE e.user_id = u.id AND e.company_id = c.company_id AND e.deleted_at IS NULL
  )
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members cm
    WHERE cm.channel_id = c.id AND cm.user_id = u.id
  );

UPDATE chat_channel_members cm
INNER JOIN (
  SELECT * FROM (
    SELECT cm2.id AS member_row_id
    FROM chat_channel_members cm2
    INNER JOIN chat_channels c
      ON c.id = cm2.channel_id
     AND c.slug IN ('general', 'company-announcements', 'hr-announcements', 'payroll-announcements')
     AND c.deleted_at IS NULL
    INNER JOIN user_roles ur
      ON ur.user_id = cm2.user_id AND (ur.company_id = c.company_id OR ur.company_id IS NULL)
    INNER JOIN users u ON u.id = cm2.user_id AND u.deleted_at IS NULL AND u.is_active = 1
    LEFT JOIN employees e
      ON e.user_id = cm2.user_id AND e.company_id = c.company_id AND e.deleted_at IS NULL
    WHERE e.id IS NULL AND cm2.left_at IS NOT NULL
  ) t
) map ON map.member_row_id = cm.id
SET cm.left_at = NULL,
    cm.joined_at = COALESCE(cm.joined_at, NOW()),
    cm.role_id = IF(cm.role_id IS NULL OR cm.role_id > 2, 2, cm.role_id);

-- ---------------------------------------------------------------------------
-- 5) UNIQUE (company_id, slug) — already defined as uk_chat_channels_company_slug.
-- Dry-run: SHOW INDEX FROM chat_channels WHERE Key_name = 'uk_chat_channels_company_slug';
-- Apply only when absent:
--   ALTER TABLE chat_channels
--     ADD UNIQUE INDEX uk_chat_channels_company_slug (company_id, slug);
