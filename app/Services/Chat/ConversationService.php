<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;

final class ConversationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Guarantee users.id peers remain active DM members after heal/canonicalize.
     */
    public function assertDirectPeers(int $conversationId, int $companyId, int ...$userIds): void
    {
        if ($conversationId <= 0) {
            return;
        }
        if ($companyId > 0) {
            $this->healConversationMembers($conversationId, $companyId);
        }
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid <= 0) {
                continue;
            }
            if (!$this->isActiveMember($conversationId, $uid)) {
                $this->ensureMemberRow($conversationId, $uid);
            }
        }
    }

    public function ensureMembership(int $conversationId, int $userId): bool
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return false;
        }
        if ($this->isActiveMember($conversationId, $userId)) {
            return true;
        }

        // Heal stale rows that stored employees.id instead of users.id for this user.
        $healed = $this->healMemberRowForUser($conversationId, $userId);
        if ($healed) {
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'conversation_member_healed',
                'conversation_id' => $conversationId,
                'user_id' => $userId,
            ]);
        }
        return $this->isActiveMember($conversationId, $userId);
    }

    private function isActiveMember(int $conversationId, int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM chat_conversation_members
             WHERE conversation_id = :cid AND user_id = :uid AND left_at IS NULL',
            ['cid' => $conversationId, 'uid' => $userId]
        );
        return (bool) $row;
    }

    /** Insert or revive a conversation member row for users.id. */
    private function ensureMemberRow(int $conversationId, int $userId): void
    {
        if ($conversationId <= 0 || $userId <= 0) {
            return;
        }
        $existing = $this->db->fetch(
            'SELECT id, left_at FROM chat_conversation_members
             WHERE conversation_id = :cid AND user_id = :uid',
            ['cid' => $conversationId, 'uid' => $userId]
        );
        if ($existing) {
            if ($existing['left_at'] !== null) {
                $this->db->update(
                    'chat_conversation_members',
                    ['left_at' => null, 'joined_at' => ChatSupport::now()],
                    'id = :id',
                    ['id' => (int) $existing['id']]
                );
            }
            return;
        }
        $this->db->insert('chat_conversation_members', [
            'conversation_id' => $conversationId,
            'user_id' => $userId,
            'joined_at' => ChatSupport::now(),
        ]);
    }

    /**
     * Remap a membership row keyed by employees.id → authenticated users.id.
     */
    private function healMemberRowForUser(int $conversationId, int $userId): bool
    {
        $emp = $this->db->fetch(
            'SELECT e.id AS employee_id, e.user_id
             FROM employees e
             INNER JOIN chat_conversation_members cm
               ON cm.conversation_id = :cid AND cm.user_id = e.id AND cm.left_at IS NULL
             WHERE e.user_id = :uid AND e.user_id IS NOT NULL
             LIMIT 1',
            ['cid' => $conversationId, 'uid' => $userId]
        );
        if (!$emp) {
            return false;
        }
        $employeePk = (int) $emp['employee_id'];
        if ($employeePk === $userId) {
            return false;
        }
        if ($this->isActiveMember($conversationId, $userId)) {
            // Canonical users.id already present — soft-leave the employees.id row.
            $this->db->update(
                'chat_conversation_members',
                ['left_at' => ChatSupport::now()],
                'conversation_id = :cid AND user_id = :eid AND left_at IS NULL',
                ['cid' => $conversationId, 'eid' => $employeePk]
            );
            return true;
        }
        $this->db->update(
            'chat_conversation_members',
            ['user_id' => $userId],
            'conversation_id = :cid AND user_id = :eid AND left_at IS NULL',
            ['cid' => $conversationId, 'eid' => $employeePk]
        );
        return $this->isActiveMember($conversationId, $userId);
    }

    /**
     * Ensure DM/group members are canonical users.id values (best-effort, idempotent).
     */
    public function healConversationMembers(int $conversationId, int $companyId): void
    {
        if ($conversationId <= 0 || $companyId <= 0) {
            return;
        }
        $rows = $this->db->fetchAll(
            'SELECT cm.id, cm.user_id
             FROM chat_conversation_members cm
             WHERE cm.conversation_id = :cid AND cm.left_at IS NULL',
            ['cid' => $conversationId]
        );
        foreach ($rows as $row) {
            $raw = (int) $row['user_id'];
            $canonical = $this->canonicalizeStoredMemberId($raw, $companyId);
            if ($canonical <= 0 || $canonical === $raw) {
                continue;
            }
            if ($this->isActiveMember($conversationId, $canonical)) {
                $this->db->update(
                    'chat_conversation_members',
                    ['left_at' => ChatSupport::now()],
                    'id = :id',
                    ['id' => (int) $row['id']]
                );
                continue;
            }
            $this->db->update(
                'chat_conversation_members',
                ['user_id' => $canonical],
                'id = :id',
                ['id' => (int) $row['id']]
            );
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'conversation_member_canonicalized',
                'conversation_id' => $conversationId,
                'from_id' => $raw,
                'to_user_id' => $canonical,
            ]);
        }
    }

    /**
     * Map a stored member id to users.id when it was mistakenly employees.id.
     */
    public function canonicalizeStoredMemberId(int $storedId, int $companyId = 0): int
    {
        if ($storedId <= 0) {
            return 0;
        }
        $user = $this->db->fetch(
            'SELECT id FROM users WHERE id = :id AND deleted_at IS NULL',
            ['id' => $storedId]
        );
        if ($user) {
            // If this users.id is a company peer (employee OR role-backed admin), keep it.
            // Remapping on employees.id PK collision previously stole admin users.id=1 when
            // employees.id=1 pointed at another user — breaking DM membership for admins.
            if ($companyId > 0 && $this->isCompanyPeer($companyId, $storedId)) {
                return $storedId;
            }
            // Collision only for non-peers: employees.id == users.id of an unrelated account.
            $emp = $this->db->fetch(
                'SELECT user_id, company_id FROM employees
                 WHERE id = :id AND user_id IS NOT NULL AND deleted_at IS NULL',
                ['id' => $storedId]
            );
            if ($emp) {
                $mapped = (int) $emp['user_id'];
                if ($mapped > 0 && $mapped !== $storedId) {
                    if ($companyId <= 0 || (int) $emp['company_id'] === $companyId) {
                        return $mapped;
                    }
                }
            }
            return $storedId;
        }

        $emp = $this->db->fetch(
            'SELECT user_id FROM employees
             WHERE id = :id AND user_id IS NOT NULL AND deleted_at IS NULL
               AND (:cid = 0 OR company_id = :cid2)',
            ['id' => $storedId, 'cid' => $companyId, 'cid2' => $companyId]
        );
        return (int) ($emp['user_id'] ?? 0);
    }

    public function get(int $conversationId, int $companyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chat_conversations
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $conversationId, 'cid' => $companyId]
        );
    }

    /**
     * Resolve a company peer to users.id (accepts users.id or employees.id via lookup).
     * Prefer resolveUserId() / resolveEmployeeId() when the client field is known.
     */
    public function resolvePeerUserId(int $companyId, int $userIdOrEmployeeId): int
    {
        if ($userIdOrEmployeeId <= 0 || $companyId <= 0) {
            return 0;
        }
        $asUser = $this->resolveUserId($companyId, $userIdOrEmployeeId);
        if ($asUser > 0) {
            return $asUser;
        }
        return $this->resolveEmployeeId($companyId, $userIdOrEmployeeId);
    }

    /** Strict: input must already be users.id of a company peer. */
    public function resolveUserId(int $companyId, int $userId): int
    {
        if ($userId <= 0 || $companyId <= 0) {
            return 0;
        }
        return $this->isCompanyPeer($companyId, $userId) ? $userId : 0;
    }

    /** Map employees.id → users.id for a company peer. */
    public function resolveEmployeeId(int $companyId, int $employeeId): int
    {
        if ($employeeId <= 0 || $companyId <= 0) {
            return 0;
        }
        $emp = $this->db->fetch(
            'SELECT user_id FROM employees
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL AND user_id IS NOT NULL',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        $uid = (int) ($emp['user_id'] ?? 0);
        return ($uid > 0 && $this->isCompanyPeer($companyId, $uid)) ? $uid : 0;
    }

    public function isCompanyPeer(int $companyId, int $userId): bool
    {
        if ($userId <= 0 || $companyId <= 0) {
            return false;
        }

        $employee = $this->db->fetch(
            'SELECT id FROM employees
             WHERE user_id = :uid AND company_id = :cid AND deleted_at IS NULL
             LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        if ($employee) {
            return true;
        }

        $role = $this->db->fetch(
            'SELECT id FROM user_roles
             WHERE user_id = :uid AND (company_id = :cid OR company_id IS NULL)
             LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        return (bool) $role;
    }

    /**
     * Find or create a 1:1 DM between two users in a company.
     */
    public function findOrCreateDirect(int $companyId, int $userId, int $otherUserId): array
    {
        if ($userId === $otherUserId) {
            return ['success' => false, 'message' => 'Cannot start a DM with yourself.'];
        }

        $otherUserId = $this->resolvePeerUserId($companyId, $otherUserId);
        if ($otherUserId <= 0 || !$this->isCompanyPeer($companyId, $userId)) {
            return ['success' => false, 'message' => 'User not found in your company.'];
        }

        $existing = $this->db->fetch(
            'SELECT c.id
             FROM chat_conversations c
             INNER JOIN chat_conversation_members m1 ON m1.conversation_id = c.id AND m1.user_id = :u1 AND m1.left_at IS NULL
             INNER JOIN chat_conversation_members m2 ON m2.conversation_id = c.id AND m2.user_id = :u2 AND m2.left_at IS NULL
             WHERE c.company_id = :cid AND c.is_group = 0 AND c.deleted_at IS NULL
               AND (SELECT COUNT(*) FROM chat_conversation_members mx WHERE mx.conversation_id = c.id AND mx.left_at IS NULL) = 2
             LIMIT 1',
            ['u1' => $userId, 'u2' => $otherUserId, 'cid' => $companyId]
        );

        if ($existing) {
            $existingId = (int) $existing['id'];
            $this->assertDirectPeers($existingId, $companyId, $userId, $otherUserId);
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'dm_reuse',
                'conversation_id' => $existingId,
                'sender_user_id' => $userId,
                'peer_user_id' => $otherUserId,
                'participant_user_ids' => $this->memberUserIds($existingId),
            ]);
            return ['success' => true, 'conversation' => $this->get($existingId, $companyId)];
        }

        $id = $this->db->insert('chat_conversations', [
            'uuid' => ChatSupport::uuid(),
            'company_id' => $companyId,
            'is_group' => 0,
            'created_by' => $userId,
            'created_at' => ChatSupport::now(),
            'updated_at' => ChatSupport::now(),
        ]);

        foreach ([$userId, $otherUserId] as $uid) {
            $this->db->insert('chat_conversation_members', [
                'conversation_id' => $id,
                'user_id' => $uid,
                'joined_at' => ChatSupport::now(),
            ]);
        }

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'dm_created',
            'conversation_id' => $id,
            'sender_user_id' => $userId,
            'peer_user_id' => $otherUserId,
            'participant_user_ids' => $this->memberUserIds($id),
            'company_id' => $companyId,
        ]);
        return ['success' => true, 'conversation' => $this->get($id, $companyId)];
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $companyId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.*,
                    m.last_read_message_id, m.last_read_at,
                    (
                      SELECT COUNT(*) FROM chat_messages msg
                      WHERE msg.conversation_id = c.id
                        AND msg.deleted_at IS NULL
                        AND msg.thread_parent_id IS NULL
                        AND (m.last_read_message_id IS NULL OR msg.id > m.last_read_message_id)
                        AND msg.user_id <> :uid
                    ) AS unread_count
             FROM chat_conversations c
             INNER JOIN chat_conversation_members m
               ON m.conversation_id = c.id AND m.user_id = :uid2 AND m.left_at IS NULL
             WHERE c.company_id = :cid AND c.deleted_at IS NULL
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC',
            ['uid' => $userId, 'uid2' => $userId, 'cid' => $companyId]
        );

        foreach ($rows as &$row) {
            $others = $this->db->fetchAll(
                'SELECT u.id, u.name, u.email
                 FROM chat_conversation_members cm
                 INNER JOIN users u ON u.id = cm.user_id
                 WHERE cm.conversation_id = :cid AND cm.left_at IS NULL AND cm.user_id <> :uid',
                ['cid' => (int) $row['id'], 'uid' => $userId]
            );
            $row['participants'] = $others;
            $row['display_name'] = $row['title']
                ?: implode(', ', array_column($others, 'name'))
                ?: 'Direct message';
        }
        unset($row);

        return $rows;
    }

    public function markRead(int $conversationId, int $userId, int $messageId): void
    {
        $this->db->update('chat_conversation_members', [
            'last_read_message_id' => $messageId,
            'last_read_at' => ChatSupport::now(),
        ], 'conversation_id = :cid AND user_id = :uid AND left_at IS NULL', [
            'cid' => $conversationId,
            'uid' => $userId,
        ]);
    }

    /** @return list<int> Canonical users.id participants */
    public function memberUserIds(int $conversationId): array
    {
        $conv = $this->db->fetch(
            'SELECT company_id FROM chat_conversations WHERE id = :id',
            ['id' => $conversationId]
        );
        $companyId = (int) ($conv['company_id'] ?? 0);

        $rows = $this->db->fetchAll(
            'SELECT user_id FROM chat_conversation_members WHERE conversation_id = :cid AND left_at IS NULL',
            ['cid' => $conversationId]
        );
        $ids = [];
        foreach ($rows as $r) {
            $raw = (int) $r['user_id'];
            if ($raw <= 0) {
                continue;
            }
            $canonical = $this->canonicalizeStoredMemberId($raw, $companyId);
            if ($canonical > 0) {
                $ids[$canonical] = true;
                continue;
            }
            // Legacy row stored employees.id with no users.id match — map within company only.
            $emp = $this->db->fetch(
                'SELECT user_id FROM employees
                 WHERE id = :id AND user_id IS NOT NULL AND deleted_at IS NULL
                   AND (:cid = 0 OR company_id = :cid2)',
                ['id' => $raw, 'cid' => $companyId, 'cid2' => $companyId]
            );
            if ($emp) {
                $mapped = (int) $emp['user_id'];
                if ($mapped > 0) {
                    $ids[$mapped] = true;
                }
            }
        }
        return array_map('intval', array_keys($ids));
    }

    /**
     * Recipients for delivery/notifications: all participants except sender users.id.
     *
     * @return list<int>
     */
    public function recipientUserIds(int $conversationId, int $senderUserId): array
    {
        $conv = $this->db->fetch(
            'SELECT company_id FROM chat_conversations WHERE id = :id',
            ['id' => $conversationId]
        );
        $companyId = (int) ($conv['company_id'] ?? 0);
        if ($companyId > 0) {
            $this->healConversationMembers($conversationId, $companyId);
        }
        return array_values(array_filter(
            $this->memberUserIds($conversationId),
            static fn (int $id) => $id > 0 && $id !== $senderUserId
        ));
    }

    /**
     * Search coworkers for DMs. Returns users.id (never employees.id).
     * Includes employees and company admins (users with roles but no employee row).
     *
     * @return list<array<string,mixed>>
     */
    public function searchableUsers(int $companyId, int $excludeUserId, string $q = ''): array
    {
        return $this->directoryUsers($companyId, $excludeUserId, $q, 30);
    }

    /**
     * Org member directory for Start DM / Members modal.
     * Always returns users.id plus role, department, branch (when available).
     *
     * @return list<array<string,mixed>>
     */
    public function directoryUsers(int $companyId, int $excludeUserId, string $q = '', int $limit = 100): array
    {
        $params = [
            'cid' => $companyId,
            'uid' => $excludeUserId,
            'cid2' => $companyId,
            'uid2' => $excludeUserId,
            'cid3' => $companyId,
        ];
        $employeeFilter = '';
        $adminFilter = '';
        if ($q !== '') {
            $like = '%' . $q . '%';
            $employeeFilter = ' AND (u.name LIKE :q OR u.email LIKE :q2 OR e.employee_code LIKE :q3)';
            $adminFilter = ' AND (u.name LIKE :q4 OR u.email LIKE :q5)';
            $params['q'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
        }

        $limit = max(1, min(200, $limit));

        $sql = 'SELECT u.id, u.name, u.email, u.avatar, e.employee_code,
                       e.department_id, e.branch_id,
                       d.name AS department_name, b.name AS branch_name,
                       (
                         SELECT r.name FROM user_roles urx
                         INNER JOIN roles r ON r.id = urx.role_id
                         WHERE urx.user_id = u.id AND (urx.company_id = :cid_role OR urx.company_id IS NULL)
                         ORDER BY FIELD(r.slug, \'super_admin\', \'company_admin\', \'hr_manager\', \'department_manager\', \'employee\'), r.id
                         LIMIT 1
                       ) AS role_name,
                       \'employee\' AS directory_kind
                FROM users u
                INNER JOIN employees e ON e.user_id = u.id AND e.deleted_at IS NULL AND e.company_id = :cid
                LEFT JOIN departments d ON d.id = e.department_id AND d.deleted_at IS NULL
                LEFT JOIN branches b ON b.id = e.branch_id AND b.deleted_at IS NULL
                WHERE u.deleted_at IS NULL AND u.is_active = 1 AND u.id <> :uid
                  AND e.employment_status IN (\'active\', \'probation\', \'notice_period\')' . $employeeFilter . '
                UNION
                SELECT u.id, u.name, u.email, u.avatar, NULL AS employee_code,
                       NULL AS department_id, NULL AS branch_id,
                       NULL AS department_name, NULL AS branch_name,
                       (
                         SELECT r.name FROM user_roles urx
                         INNER JOIN roles r ON r.id = urx.role_id
                         WHERE urx.user_id = u.id AND (urx.company_id = :cid_role2 OR urx.company_id IS NULL)
                         ORDER BY FIELD(r.slug, \'super_admin\', \'company_admin\', \'hr_manager\', \'department_manager\', \'employee\'), r.id
                         LIMIT 1
                       ) AS role_name,
                       \'admin\' AS directory_kind
                FROM users u
                INNER JOIN user_roles ur ON ur.user_id = u.id AND (ur.company_id = :cid2 OR ur.company_id IS NULL)
                WHERE u.deleted_at IS NULL AND u.is_active = 1 AND u.id <> :uid2
                  AND NOT EXISTS (
                    SELECT 1 FROM employees e2
                    WHERE e2.user_id = u.id AND e2.deleted_at IS NULL AND e2.company_id = :cid3
                  )' . $adminFilter . '
                ORDER BY name ASC
                LIMIT ' . $limit;
        $params['cid_role'] = $companyId;
        $params['cid_role2'] = $companyId;

        $rows = $this->db->fetchAll($sql, $params);
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['department'] = $row['department_name'] ?? null;
            $row['branch'] = $row['branch_name'] ?? null;
            $row['role'] = $row['role_name'] ?? ($row['directory_kind'] === 'admin' ? 'Admin' : 'Employee');
        }
        unset($row);

        return $rows;
    }
}
