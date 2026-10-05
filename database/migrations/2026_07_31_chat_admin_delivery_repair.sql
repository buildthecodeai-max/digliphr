-- Team Chat: admin → employee live delivery repair (users.id membership).
-- Idempotent — safe to re-run.
-- Realtime remains AJAX polling (/api/chat/poll) — no WebSockets.
--
-- DRY-RUN (inspect only, do not modify):
--   SELECT cm.conversation_id, cm.user_id AS stored_id, e.id AS employee_id, e.user_id AS canonical_user_id
--   FROM chat_conversation_members cm
--   INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
--   LEFT JOIN users u_same ON u_same.id = cm.user_id
--   WHERE cm.left_at IS NULL;
--
--   SELECT cm.channel_id, cm.user_id AS stored_id, e.id AS employee_id, e.user_id AS canonical_user_id
--   FROM chat_channel_members cm
--   INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
--   WHERE cm.left_at IS NULL;

-- ---------------------------------------------------------------------------
-- 1) Remap conversation members: employees.id → users.id (non-colliding)
-- ---------------------------------------------------------------------------
UPDATE chat_conversation_members cm
INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.deleted_at IS NULL
LEFT JOIN users u_as_user ON u_as_user.id = cm.user_id AND u_as_user.deleted_at IS NULL
SET cm.user_id = e.user_id
WHERE cm.left_at IS NULL
  AND e.user_id <> cm.user_id
  AND u_as_user.id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_conversation_members cm2
    WHERE cm2.conversation_id = cm.conversation_id
      AND cm2.user_id = e.user_id
      AND cm2.left_at IS NULL
  );

-- ---------------------------------------------------------------------------
-- 2) Collision case (employees.id == some users.id of a non-company user):
--    For 1:1 DMs in the employee company, remap to employees.user_id when the
--    stored users.id is NOT an employee of that company.
-- ---------------------------------------------------------------------------
UPDATE chat_conversation_members cm
INNER JOIN chat_conversations c ON c.id = cm.conversation_id AND c.is_group = 0 AND c.deleted_at IS NULL
INNER JOIN employees e
  ON e.id = cm.user_id
 AND e.user_id IS NOT NULL
 AND e.deleted_at IS NULL
 AND e.company_id = c.company_id
 AND e.user_id <> cm.user_id
LEFT JOIN employees e_stored
  ON e_stored.user_id = cm.user_id
 AND e_stored.company_id = c.company_id
 AND e_stored.deleted_at IS NULL
SET cm.user_id = e.user_id
WHERE cm.left_at IS NULL
  AND e_stored.id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_conversation_members cm2
    WHERE cm2.conversation_id = cm.conversation_id
      AND cm2.user_id = e.user_id
      AND cm2.left_at IS NULL
  );

-- Soft-leave leftover employees.id rows when canonical users.id already present
UPDATE chat_conversation_members cm
INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
INNER JOIN chat_conversation_members cm_ok
  ON cm_ok.conversation_id = cm.conversation_id
 AND cm_ok.user_id = e.user_id
 AND cm_ok.left_at IS NULL
SET cm.left_at = NOW()
WHERE cm.left_at IS NULL;

-- ---------------------------------------------------------------------------
-- 3) Same remap for channel members
-- ---------------------------------------------------------------------------
UPDATE chat_channel_members cm
INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.deleted_at IS NULL
LEFT JOIN users u_as_user ON u_as_user.id = cm.user_id AND u_as_user.deleted_at IS NULL
SET cm.user_id = e.user_id
WHERE cm.left_at IS NULL
  AND e.user_id <> cm.user_id
  AND u_as_user.id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members cm2
    WHERE cm2.channel_id = cm.channel_id
      AND cm2.user_id = e.user_id
      AND cm2.left_at IS NULL
  );

UPDATE chat_channel_members cm
INNER JOIN chat_channels c ON c.id = cm.channel_id AND c.deleted_at IS NULL
INNER JOIN employees e
  ON e.id = cm.user_id
 AND e.user_id IS NOT NULL
 AND e.deleted_at IS NULL
 AND e.company_id = c.company_id
 AND e.user_id <> cm.user_id
LEFT JOIN employees e_stored
  ON e_stored.user_id = cm.user_id
 AND e_stored.company_id = c.company_id
 AND e_stored.deleted_at IS NULL
SET cm.user_id = e.user_id
WHERE cm.left_at IS NULL
  AND e_stored.id IS NULL
  AND NOT EXISTS (
    SELECT 1 FROM chat_channel_members cm2
    WHERE cm2.channel_id = cm.channel_id
      AND cm2.user_id = e.user_id
      AND cm2.left_at IS NULL
  );

UPDATE chat_channel_members cm
INNER JOIN employees e ON e.id = cm.user_id AND e.user_id IS NOT NULL AND e.user_id <> cm.user_id
INNER JOIN chat_channel_members cm_ok
  ON cm_ok.channel_id = cm.channel_id
 AND cm_ok.user_id = e.user_id
 AND cm_ok.left_at IS NULL
SET cm.left_at = NOW()
WHERE cm.left_at IS NULL;

-- ---------------------------------------------------------------------------
-- 4) Ensure 1:1 DMs that include an admin (role-only user) also include the
--    employee's users.id when a stale employees.id peer row was soft-left.
--    (Runtime heal in ConversationService also covers this on next poll/send.)
-- ---------------------------------------------------------------------------

-- Soft-remove orphan membership rows that still point at non-users
UPDATE chat_conversation_members cm
LEFT JOIN users u ON u.id = cm.user_id
SET cm.left_at = NOW()
WHERE u.id IS NULL AND cm.left_at IS NULL;

UPDATE chat_channel_members cm
LEFT JOIN users u ON u.id = cm.user_id
SET cm.left_at = NOW()
WHERE u.id IS NULL AND cm.left_at IS NULL;
