<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;
use App\Services\AuditService;

final class ChannelService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function ensureMembership(int $channelId, int $userId): bool
    {
        if ($channelId <= 0 || $userId <= 0) {
            return false;
        }
        if ($this->isActiveMember($channelId, $userId)) {
            return true;
        }

        $channel = $this->db->fetch(
            'SELECT company_id, slug FROM chat_channels WHERE id = :id AND deleted_at IS NULL',
            ['id' => $channelId]
        );
        $companyId = (int) ($channel['company_id'] ?? 0);
        if ($companyId > 0) {
            // Peer-safe bulk heal (never steals role-backed admin users.id on employees.id collision).
            $this->healChannelMembers($channelId, $companyId);
            if ($this->isActiveMember($channelId, $userId)) {
                ChatSupport::debugLog('employee_channel_access', [
                    'event' => 'channel_member_healed_bulk',
                    'channel_id' => $channelId,
                    'company_id' => $companyId,
                    'slug' => $channel['slug'] ?? null,
                    'user_id' => $userId,
                    'allowed' => true,
                ]);
                return true;
            }
        }

        // Heal stale rows that stored employees.id instead of users.id for this user.
        $emp = $this->db->fetch(
            'SELECT e.id AS employee_id
             FROM employees e
             INNER JOIN chat_channel_members cm
               ON cm.channel_id = :cid AND cm.user_id = e.id AND cm.left_at IS NULL
             WHERE e.user_id = :uid AND e.user_id IS NOT NULL
             LIMIT 1',
            ['cid' => $channelId, 'uid' => $userId]
        );
        if ($emp) {
            $employeePk = (int) $emp['employee_id'];
            if ($employeePk !== $userId) {
                if ($this->isActiveMember($channelId, $userId)) {
                    $this->db->update(
                        'chat_channel_members',
                        ['left_at' => ChatSupport::now()],
                        'channel_id = :cid AND user_id = :eid AND left_at IS NULL',
                        ['cid' => $channelId, 'eid' => $employeePk]
                    );
                } else {
                    $this->db->update(
                        'chat_channel_members',
                        ['user_id' => $userId],
                        'channel_id = :cid AND user_id = :eid AND left_at IS NULL',
                        ['cid' => $channelId, 'eid' => $employeePk]
                    );
                }
                ChatSupport::debugLog('channel_message_delivery', [
                    'event' => 'channel_member_healed',
                    'channel_id' => $channelId,
                    'company_id' => $companyId,
                    'user_id' => $userId,
                    'from_employee_id' => $employeePk,
                ]);
            }
        }

        $allowed = $this->isActiveMember($channelId, $userId);
        ChatSupport::debugLog('employee_channel_access', [
            'event' => 'ensure_membership',
            'channel_id' => $channelId,
            'company_id' => $companyId,
            'slug' => $channel['slug'] ?? null,
            'user_id' => $userId,
            'allowed' => $allowed,
        ]);
        return $allowed;
    }

    /**
     * Ensure channel members are canonical users.id values (best-effort, idempotent).
     * Mirrors ConversationService::healConversationMembers — peer-safe via canonicalizeStoredMemberId.
     */
    public function healChannelMembers(int $channelId, int $companyId): void
    {
        if ($channelId <= 0 || $companyId <= 0) {
            return;
        }
        $conversations = new ConversationService();
        $rows = $this->db->fetchAll(
            'SELECT cm.id, cm.user_id
             FROM chat_channel_members cm
             WHERE cm.channel_id = :cid AND cm.left_at IS NULL',
            ['cid' => $channelId]
        );
        foreach ($rows as $row) {
            $raw = (int) $row['user_id'];
            $canonical = $conversations->canonicalizeStoredMemberId($raw, $companyId);
            if ($canonical <= 0 || $canonical === $raw) {
                continue;
            }
            if ($this->isActiveMember($channelId, $canonical)) {
                $this->db->update(
                    'chat_channel_members',
                    ['left_at' => ChatSupport::now()],
                    'id = :id',
                    ['id' => (int) $row['id']]
                );
                continue;
            }
            $this->db->update(
                'chat_channel_members',
                ['user_id' => $canonical],
                'id = :id',
                ['id' => (int) $row['id']]
            );
            ChatSupport::debugLog('channel_message_delivery', [
                'event' => 'channel_member_canonicalized',
                'channel_id' => $channelId,
                'company_id' => $companyId,
                'from_id' => $raw,
                'to_user_id' => $canonical,
            ]);
        }
    }

    private function isActiveMember(int $channelId, int $userId): bool
    {
        $row = $this->db->fetch(
            'SELECT id FROM chat_channel_members
             WHERE channel_id = :cid AND user_id = :uid AND left_at IS NULL',
            ['cid' => $channelId, 'uid' => $userId]
        );
        return (bool) $row;
    }

    public function getChannel(int $channelId, int $companyId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chat_channels
             WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $channelId, 'cid' => $companyId]
        );
    }

    /** @return list<array<string,mixed>> */
    public function listForUser(int $companyId, int $userId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT c.*,
                    m.role_id, m.last_read_message_id, m.last_read_at, m.notification_level,
                    cr.slug AS member_role_slug,
                    cr.can_post AS role_can_post,
                    (
                      SELECT COUNT(*) FROM chat_messages msg
                      WHERE msg.channel_id = c.id
                        AND msg.deleted_at IS NULL
                        AND msg.thread_parent_id IS NULL
                        AND (m.last_read_message_id IS NULL OR msg.id > m.last_read_message_id)
                        AND msg.user_id <> :uid
                    ) AS unread_count
             FROM chat_channels c
             INNER JOIN chat_channel_members m
               ON m.channel_id = c.id AND m.user_id = :uid2 AND m.left_at IS NULL
             LEFT JOIN chat_roles cr ON cr.id = m.role_id
             WHERE c.company_id = :cid AND c.deleted_at IS NULL AND c.is_archived = 0
             ORDER BY COALESCE(c.last_message_at, c.created_at) DESC, c.name ASC',
            ['uid' => $userId, 'uid2' => $userId, 'cid' => $companyId]
        );

        foreach ($rows as &$row) {
            $roleCanPost = $row['role_can_post'] === null ? true : (bool) (int) $row['role_can_post'];
            $roleId = (int) ($row['role_id'] ?? 3);
            // Announcement channels: only owner/admin chat roles may post (unless elevated later by permission).
            if (($row['channel_type'] ?? '') === 'announcement' && $roleId > 2) {
                $roleCanPost = false;
            }
            $row['can_post'] = $roleCanPost ? 1 : 0;
        }
        unset($row);

        ChatSupport::debugLog('employee_channel_access', [
            'event' => 'list_for_user',
            'company_id' => $companyId,
            'user_id' => $userId,
            'channel_ids' => array_map(static fn ($r) => (int) $r['id'], $rows),
            'slugs' => array_map(static fn ($r) => (string) ($r['slug'] ?? ''), $rows),
        ]);

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    public function discoverablePublic(int $companyId, int $userId): array
    {
        return $this->db->fetchAll(
            'SELECT c.*
             FROM chat_channels c
             WHERE c.company_id = :cid
               AND c.deleted_at IS NULL
               AND c.is_archived = 0
               AND c.channel_type = \'public\'
               AND NOT EXISTS (
                 SELECT 1 FROM chat_channel_members m
                 WHERE m.channel_id = c.id AND m.user_id = :uid AND m.left_at IS NULL
               )
             ORDER BY c.name ASC',
            ['cid' => $companyId, 'uid' => $userId]
        );
    }

    public function create(
        int $companyId,
        int $userId,
        string $name,
        string $channelType = 'public',
        ?string $description = null,
        string $scope = 'custom',
        ?int $branchId = null,
        ?int $departmentId = null,
        bool $isSystem = false,
        bool $requiresAck = false
    ): array {
        $slugBase = ChatSupport::slugify($name);
        $slug = $slugBase;
        $i = 1;
        while ($this->db->fetch(
            'SELECT id FROM chat_channels WHERE company_id = :cid AND slug = :slug',
            ['cid' => $companyId, 'slug' => $slug]
        )) {
            $slug = $slugBase . '-' . $i++;
        }

        $id = $this->db->insert('chat_channels', [
            'uuid' => ChatSupport::uuid(),
            'company_id' => $companyId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'channel_type' => in_array($channelType, ['public', 'private', 'announcement'], true) ? $channelType : 'public',
            'scope' => $scope,
            'branch_id' => $branchId,
            'department_id' => $departmentId,
            'is_system' => $isSystem ? 1 : 0,
            'requires_acknowledgement' => $requiresAck ? 1 : 0,
            'created_by' => $userId,
            'created_at' => ChatSupport::now(),
            'updated_at' => ChatSupport::now(),
        ]);

        $this->addMember($id, $userId, 1, $userId);
        (new AuditService())->log('create', 'chat_channels', $id, null, ['name' => $name, 'type' => $channelType]);

        return $this->getChannel($id, $companyId) ?? ['id' => $id];
    }

    public function addMember(int $channelId, int $userId, int $roleId = 3, ?int $addedBy = null): void
    {
        $existing = $this->db->fetch(
            'SELECT id, left_at, role_id FROM chat_channel_members WHERE channel_id = :cid AND user_id = :uid',
            ['cid' => $channelId, 'uid' => $userId]
        );
        if ($existing) {
            $patch = [];
            if ($existing['left_at'] !== null) {
                $patch = [
                    'left_at' => null,
                    'role_id' => $roleId,
                    'joined_at' => ChatSupport::now(),
                    'added_by' => $addedBy,
                ];
            } elseif ($roleId > 0 && (int) ($existing['role_id'] ?? 0) !== $roleId) {
                // Membership sync is authoritative (e.g. announcement → readonly).
                $patch = ['role_id' => $roleId];
            }
            if ($patch !== []) {
                $this->db->update('chat_channel_members', $patch, 'id = :id', ['id' => $existing['id']]);
            }
            return;
        }

        $this->db->insert('chat_channel_members', [
            'channel_id' => $channelId,
            'user_id' => $userId,
            'role_id' => $roleId,
            'joined_at' => ChatSupport::now(),
            'added_by' => $addedBy,
        ]);
    }

    public function removeMember(int $channelId, int $userId): void
    {
        $this->db->update('chat_channel_members', [
            'left_at' => ChatSupport::now(),
        ], 'channel_id = :cid AND user_id = :uid AND left_at IS NULL', [
            'cid' => $channelId,
            'uid' => $userId,
        ]);
    }

    public function joinPublic(int $companyId, int $channelId, int $userId): array
    {
        $channel = $this->getChannel($channelId, $companyId);
        if (!$channel || $channel['channel_type'] !== 'public') {
            return ['success' => false, 'message' => 'Channel not found or not joinable.'];
        }
        $this->addMember($channelId, $userId, 3, $userId);
        return ['success' => true, 'message' => 'Joined channel.', 'channel' => $channel];
    }

    /** @return list<array<string,mixed>> */
    public function members(int $channelId): array
    {
        return $this->db->fetchAll(
            'SELECT m.*, u.name, u.email, u.avatar, cr.slug AS role_slug
             FROM chat_channel_members m
             INNER JOIN users u ON u.id = m.user_id
             LEFT JOIN chat_roles cr ON cr.id = m.role_id
             WHERE m.channel_id = :cid AND m.left_at IS NULL
             ORDER BY cr.id ASC, u.name ASC',
            ['cid' => $channelId]
        );
    }

    public function markRead(int $channelId, int $userId, int $messageId): void
    {
        $this->db->update('chat_channel_members', [
            'last_read_message_id' => $messageId,
            'last_read_at' => ChatSupport::now(),
        ], 'channel_id = :cid AND user_id = :uid AND left_at IS NULL', [
            'cid' => $channelId,
            'uid' => $userId,
        ]);
    }

    public function findBySlug(int $companyId, string $slug): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chat_channels WHERE company_id = :cid AND slug = :slug AND deleted_at IS NULL',
            ['cid' => $companyId, 'slug' => $slug]
        );
    }

    /** @return list<int> Canonical users.id participants */
    public function memberUserIds(int $channelId): array
    {
        $channel = $this->db->fetch('SELECT company_id FROM chat_channels WHERE id = :id', ['id' => $channelId]);
        $companyId = (int) ($channel['company_id'] ?? 0);
        $conversations = new ConversationService();

        $rows = $this->db->fetchAll(
            'SELECT user_id FROM chat_channel_members WHERE channel_id = :cid AND left_at IS NULL',
            ['cid' => $channelId]
        );
        $ids = [];
        foreach ($rows as $r) {
            $raw = (int) $r['user_id'];
            if ($raw <= 0) {
                continue;
            }
            $canonical = $conversations->canonicalizeStoredMemberId($raw, $companyId);
            if ($canonical > 0) {
                $ids[$canonical] = true;
                // Do NOT also expand via employees.id — that leaked colliding PKs
                // (admin users.id kept + employees.user_id added) and broke notify targeting.
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
     * Recipients for delivery/notifications: all active members except sender users.id.
     * Includes readonly / can_post=false announcement members.
     *
     * @return list<int>
     */
    public function recipientUserIds(int $channelId, int $senderUserId): array
    {
        $channel = $this->db->fetch('SELECT company_id FROM chat_channels WHERE id = :id', ['id' => $channelId]);
        $companyId = (int) ($channel['company_id'] ?? 0);
        if ($companyId > 0) {
            $this->healChannelMembers($channelId, $companyId);
        }
        return array_values(array_filter(
            $this->memberUserIds($channelId),
            static fn (int $id) => $id > 0 && $id !== $senderUserId
        ));
    }
}
