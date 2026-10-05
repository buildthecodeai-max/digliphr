<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;
use App\Services\AuditService;
use App\Services\NotificationService;

final class MessageService
{
    private Database $db;
    private ChannelService $channels;
    private ConversationService $conversations;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->channels = new ChannelService();
        $this->conversations = new ConversationService();
    }

    public function getMessage(int $messageId, int $companyId): ?array
    {
        return $this->db->fetch(
            'SELECT m.*, u.name AS user_name, u.avatar AS user_avatar
             FROM chat_messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.id = :id AND m.company_id = :cid',
            ['id' => $messageId, 'cid' => $companyId]
        );
    }

    /**
     * @param array{channel_id?:int,conversation_id?:int,thread_parent_id?:int,message_type?:string,requires_acknowledgement?:bool} $context
     */
    public function post(int $companyId, int $userId, string $body, array $context = []): array
    {
        $body = ChatSupport::sanitizeMessage($body);
        if ($body === '' && empty($context['allow_empty'])) {
            return ['success' => false, 'message' => 'Message cannot be empty.'];
        }

        $channelId = isset($context['channel_id']) ? (int) $context['channel_id'] : null;
        $conversationId = isset($context['conversation_id']) ? (int) $context['conversation_id'] : null;
        $threadParentId = isset($context['thread_parent_id']) ? (int) $context['thread_parent_id'] : null;

        if (!$channelId && !$conversationId) {
            return ['success' => false, 'message' => 'Target channel or conversation required.'];
        }

        if ($channelId && !$this->channels->ensureMembership($channelId, $userId)) {
            return ['success' => false, 'message' => 'You are not a member of this channel.'];
        }
        if ($conversationId && !$this->conversations->ensureMembership($conversationId, $userId)) {
            return ['success' => false, 'message' => 'You are not a member of this conversation.'];
        }
        if ($conversationId) {
            $this->conversations->healConversationMembers($conversationId, $companyId);
        }
        if ($channelId) {
            $this->channels->healChannelMembers($channelId, $companyId);
        }

        $senderRole = $this->senderRoleLabel($userId, $companyId);
        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'message_post_attempt',
            'sender_user_id' => $userId,
            'sender_role' => $senderRole,
            'company_id' => $companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'participant_user_ids' => $conversationId
                ? $this->conversations->memberUserIds($conversationId)
                : ($channelId ? $this->channels->memberUserIds($channelId) : []),
        ]);
        if ($channelId) {
            ChatSupport::debugLog('channel_message_delivery', [
                'event' => 'message_post_attempt',
                'sender_user_id' => $userId,
                'sender_role' => $senderRole,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'participant_user_ids' => $this->channels->memberUserIds($channelId),
            ]);
        }

        if ($threadParentId) {
            $parent = $this->getMessage($threadParentId, $companyId);
            if (!$parent || $parent['deleted_at']) {
                return ['success' => false, 'message' => 'Thread parent not found.'];
            }
            $channelId = $parent['channel_id'] ? (int) $parent['channel_id'] : null;
            $conversationId = $parent['conversation_id'] ? (int) $parent['conversation_id'] : null;
        }

        if ($channelId) {
            $channel = $this->channels->getChannel($channelId, $companyId);
            if ($channel && $channel['channel_type'] === 'announcement') {
                // Respect chat_roles.can_post / owner-admin roles; manage_channel may force post.
                $member = $this->db->fetch(
                    'SELECT m.role_id, COALESCE(cr.can_post, 1) AS can_post
                     FROM chat_channel_members m
                     LEFT JOIN chat_roles cr ON cr.id = m.role_id
                     WHERE m.channel_id = :cid AND m.user_id = :uid AND m.left_at IS NULL',
                    ['cid' => $channelId, 'uid' => $userId]
                );
                $roleId = (int) ($member['role_id'] ?? 3);
                $canPost = (bool) (int) ($member['can_post'] ?? 1);
                if (($roleId > 2 || !$canPost) && empty($context['force_announcement_post'])) {
                    return ['success' => false, 'message' => 'Only channel admins can post in announcement channels.'];
                }
            }
        }

        $id = $this->db->insert('chat_messages', [
            'uuid' => ChatSupport::uuid(),
            'company_id' => $companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'thread_parent_id' => $threadParentId,
            'user_id' => $userId,
            'body' => $body,
            'body_html' => ChatSupport::renderBodyHtml($body),
            'message_type' => $context['message_type'] ?? 'text',
            'requires_acknowledgement' => !empty($context['requires_acknowledgement']) ? 1 : 0,
            'meta' => isset($context['meta']) ? json_encode($context['meta']) : null,
            'created_at' => ChatSupport::now(),
            'updated_at' => ChatSupport::now(),
        ]);

        if ($threadParentId) {
            $this->db->query(
                'UPDATE chat_messages SET reply_count = reply_count + 1 WHERE id = :id',
                ['id' => $threadParentId]
            );
        }

        if ($channelId && !$threadParentId) {
            $this->db->update('chat_channels', ['last_message_at' => ChatSupport::now()], 'id = :id', ['id' => $channelId]);
            $this->channels->markRead($channelId, $userId, $id);
        }
        if ($conversationId && !$threadParentId) {
            $this->db->update('chat_conversations', ['last_message_at' => ChatSupport::now()], 'id = :id', ['id' => $conversationId]);
            $this->conversations->markRead($conversationId, $userId, $id);
        }

        $mentions = $this->extractAndStoreMentions($id, $body, $companyId, $channelId, $userId, !empty($context['allow_mass_mentions']));
        $mentionedIds = [];
        foreach ($mentions as $m) {
            if (($m['type'] ?? '') === 'user' && !empty($m['user_id'])) {
                $mentionedIds[] = (int) $m['user_id'];
            }
        }
        $this->notifyMentions($companyId, $userId, $id, $channelId, $conversationId, $mentions);
        // Bell notifications for other members (DM + channel). Skip sender; skip users who already got a mention ping.
        $recipientIds = [];
        if (!$threadParentId) {
            $recipientIds = $this->notifyMessageRecipients($companyId, $userId, $id, $channelId, $conversationId, $body, $mentionedIds);
        }

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'message_posted',
            'message_id' => $id,
            'sender_user_id' => $userId,
            'sender_role' => $senderRole,
            'company_id' => $companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'recipient_user_ids' => $recipientIds,
            'participant_user_ids' => $conversationId
                ? $this->conversations->memberUserIds($conversationId)
                : ($channelId ? $this->channels->memberUserIds($channelId) : []),
        ]);
        if ($channelId) {
            ChatSupport::debugLog('channel_message_delivery', [
                'event' => 'message_posted',
                'message_id' => $id,
                'sender_user_id' => $userId,
                'sender_role' => $senderRole,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'recipient_user_ids' => $recipientIds,
                'participant_user_ids' => $this->channels->memberUserIds($channelId),
            ]);
        }

        $message = $this->hydrateMessage($this->getMessage($id, $companyId), $userId);
        return ['success' => true, 'message' => 'Sent.', 'data' => $message];
    }

    public function edit(int $companyId, int $userId, int $messageId, string $body, bool $canDeleteAny = false): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || $msg['deleted_at']) {
            return ['success' => false, 'message' => 'Message not found.'];
        }
        if ((int) $msg['user_id'] !== $userId && !$canDeleteAny) {
            return ['success' => false, 'message' => 'You can only edit your own messages.'];
        }

        $body = ChatSupport::sanitizeMessage($body);
        if ($body === '') {
            return ['success' => false, 'message' => 'Message cannot be empty.'];
        }

        $this->db->insert('chat_message_edits', [
            'message_id' => $messageId,
            'edited_by' => $userId,
            'previous_body' => $msg['body'],
            'created_at' => ChatSupport::now(),
        ]);

        $this->db->update('chat_messages', [
            'body' => $body,
            'body_html' => ChatSupport::renderBodyHtml($body),
            'is_edited' => 1,
            'edited_at' => ChatSupport::now(),
            'updated_at' => ChatSupport::now(),
        ], 'id = :id', ['id' => $messageId]);

        return ['success' => true, 'message' => 'Updated.', 'data' => $this->hydrateMessage($this->getMessage($messageId, $companyId), $userId)];
    }

    public function softDelete(int $companyId, int $userId, int $messageId, bool $canDeleteAny = false): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || $msg['deleted_at']) {
            return ['success' => false, 'message' => 'Message not found.'];
        }
        if ((int) $msg['user_id'] !== $userId && !$canDeleteAny) {
            return ['success' => false, 'message' => 'You can only delete your own messages.'];
        }

        $this->db->update('chat_messages', [
            'deleted_at' => ChatSupport::now(),
            'deleted_by' => $userId,
            'body' => '',
            'body_html' => '<em class="chat-deleted">Message deleted</em>',
        ], 'id = :id', ['id' => $messageId]);

        (new AuditService())->log('delete', 'chat_messages', $messageId);
        return ['success' => true, 'message' => 'Deleted.'];
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function listMessages(
        int $companyId,
        int $userId,
        ?int $channelId,
        ?int $conversationId,
        ?int $afterId = null,
        ?int $beforeId = null,
        int $limit = 50,
        ?int $threadParentId = null
    ): array {
        if ($channelId && !$this->channels->ensureMembership($channelId, $userId)) {
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'poll_list_denied_channel',
                'viewer_user_id' => $userId,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'after_id' => $afterId,
            ]);
            ChatSupport::debugLog('employee_channel_access', [
                'event' => 'poll_list_denied_channel',
                'viewer_user_id' => $userId,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'after_id' => $afterId,
                'allowed' => false,
            ]);
            return [];
        }
        if ($conversationId && !$this->conversations->ensureMembership($conversationId, $userId)) {
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'poll_list_denied_conversation',
                'viewer_user_id' => $userId,
                'company_id' => $companyId,
                'conversation_id' => $conversationId,
                'after_id' => $afterId,
                'participant_user_ids' => $this->conversations->memberUserIds($conversationId),
            ]);
            return [];
        }
        if ($conversationId) {
            $this->conversations->healConversationMembers($conversationId, $companyId);
        }
        if ($channelId) {
            $this->channels->healChannelMembers($channelId, $companyId);
        }

        $params = ['cid' => $companyId];
        $sql = 'SELECT m.*, u.name AS user_name, u.avatar AS user_avatar
                FROM chat_messages m
                INNER JOIN users u ON u.id = m.user_id
                WHERE m.company_id = :cid';

        if ($threadParentId) {
            $sql .= ' AND m.thread_parent_id = :tid';
            $params['tid'] = $threadParentId;
        } else {
            $sql .= ' AND m.thread_parent_id IS NULL';
        }

        if ($channelId) {
            $sql .= ' AND m.channel_id = :chid';
            $params['chid'] = $channelId;
        }
        if ($conversationId) {
            $sql .= ' AND m.conversation_id = :convid';
            $params['convid'] = $conversationId;
        }
        if ($afterId) {
            $sql .= ' AND m.id > :after';
            $params['after'] = $afterId;
        }
        if ($beforeId) {
            $sql .= ' AND m.id < :before';
            $params['before'] = $beforeId;
        }

        $limit = max(1, min(100, $limit));
        $sql .= ' ORDER BY m.id ' . ($beforeId ? 'DESC' : 'ASC') . ' LIMIT ' . $limit;

        $rows = $this->db->fetchAll($sql, $params);
        if ($beforeId) {
            $rows = array_reverse($rows);
        }

        if ($afterId) {
            ChatSupport::debugLog('admin_chat_delivery', [
                'event' => 'poll_list_result',
                'viewer_user_id' => $userId,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'conversation_id' => $conversationId,
                'after_id' => $afterId,
                'returned_ids' => array_map(static fn ($r) => (int) $r['id'], $rows),
                'returned_sender_user_ids' => array_map(static fn ($r) => (int) $r['user_id'], $rows),
            ]);
            if ($channelId) {
                ChatSupport::debugLog('channel_message_delivery', [
                    'event' => 'poll_list_result',
                    'viewer_user_id' => $userId,
                    'company_id' => $companyId,
                    'channel_id' => $channelId,
                    'after_id' => $afterId,
                    'returned_ids' => array_map(static fn ($r) => (int) $r['id'], $rows),
                    'returned_sender_user_ids' => array_map(static fn ($r) => (int) $r['user_id'], $rows),
                ]);
            }
        }

        return array_map(fn ($row) => $this->hydrateMessage($row, $userId), $rows);
    }

    public function toggleReaction(int $companyId, int $userId, int $messageId, string $emoji): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || $msg['deleted_at']) {
            return ['success' => false, 'message' => 'Message not found.'];
        }
        if (!$this->canAccessMessage($msg, $userId)) {
            return ['success' => false, 'message' => 'Forbidden.'];
        }

        $emoji = mb_substr(trim($emoji), 0, 32);
        if ($emoji === '') {
            return ['success' => false, 'message' => 'Emoji required.'];
        }

        $existing = $this->db->fetch(
            'SELECT id FROM chat_reactions WHERE message_id = :mid AND user_id = :uid AND emoji = :emoji',
            ['mid' => $messageId, 'uid' => $userId, 'emoji' => $emoji]
        );

        if ($existing) {
            $this->db->delete('chat_reactions', 'id = :id', ['id' => $existing['id']]);
            $this->db->query('UPDATE chat_messages SET reaction_count = GREATEST(reaction_count - 1, 0) WHERE id = :id', ['id' => $messageId]);
            $action = 'removed';
        } else {
            $this->db->insert('chat_reactions', [
                'message_id' => $messageId,
                'user_id' => $userId,
                'emoji' => $emoji,
                'created_at' => ChatSupport::now(),
            ]);
            $this->db->query('UPDATE chat_messages SET reaction_count = reaction_count + 1 WHERE id = :id', ['id' => $messageId]);
            $action = 'added';
        }

        return ['success' => true, 'message' => 'Reaction ' . $action . '.', 'data' => ['reactions' => $this->reactionsFor($messageId)]];
    }

    public function pin(int $companyId, int $userId, int $messageId): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || $msg['deleted_at']) {
            return ['success' => false, 'message' => 'Message not found.'];
        }
        if (!$this->canAccessMessage($msg, $userId)) {
            return ['success' => false, 'message' => 'Forbidden.'];
        }

        $existing = $this->db->fetch('SELECT id FROM chat_pins WHERE message_id = :mid', ['mid' => $messageId]);
        if ($existing) {
            $this->db->delete('chat_pins', 'id = :id', ['id' => $existing['id']]);
            return ['success' => true, 'message' => 'Unpinned.', 'data' => ['pinned' => false]];
        }

        $this->db->insert('chat_pins', [
            'company_id' => $companyId,
            'channel_id' => $msg['channel_id'],
            'conversation_id' => $msg['conversation_id'],
            'message_id' => $messageId,
            'pinned_by' => $userId,
            'created_at' => ChatSupport::now(),
        ]);

        return ['success' => true, 'message' => 'Pinned.', 'data' => ['pinned' => true]];
    }

    public function toggleSaved(int $userId, int $messageId, int $companyId): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || !$this->canAccessMessage($msg, $userId)) {
            return ['success' => false, 'message' => 'Message not found.'];
        }

        $existing = $this->db->fetch(
            'SELECT id FROM chat_saved_messages WHERE user_id = :uid AND message_id = :mid',
            ['uid' => $userId, 'mid' => $messageId]
        );
        if ($existing) {
            $this->db->delete('chat_saved_messages', 'id = :id', ['id' => $existing['id']]);
            return ['success' => true, 'message' => 'Removed from saved.', 'data' => ['saved' => false]];
        }

        $this->db->insert('chat_saved_messages', [
            'user_id' => $userId,
            'message_id' => $messageId,
            'created_at' => ChatSupport::now(),
        ]);
        return ['success' => true, 'message' => 'Saved.', 'data' => ['saved' => true]];
    }

    public function markReadReceipts(int $userId, array $messageIds): void
    {
        foreach ($messageIds as $mid) {
            $mid = (int) $mid;
            if ($mid <= 0) {
                continue;
            }
            $exists = $this->db->fetch(
                'SELECT id FROM chat_message_reads WHERE message_id = :mid AND user_id = :uid',
                ['mid' => $mid, 'uid' => $userId]
            );
            if (!$exists) {
                $this->db->insert('chat_message_reads', [
                    'message_id' => $mid,
                    'user_id' => $userId,
                    'read_at' => ChatSupport::now(),
                ]);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function savedForUser(int $userId, int $companyId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.*, u.name AS user_name, u.avatar AS user_avatar, s.created_at AS saved_at
             FROM chat_saved_messages s
             INNER JOIN chat_messages m ON m.id = s.message_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE s.user_id = :uid AND m.company_id = :cid AND m.deleted_at IS NULL
             ORDER BY s.created_at DESC LIMIT 100',
            ['uid' => $userId, 'cid' => $companyId]
        );
        return array_map(fn ($r) => $this->hydrateMessage($r, $userId), $rows);
    }

    /** @return list<array<string,mixed>> */
    public function mentionsForUser(int $userId, int $companyId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT m.*, u.name AS user_name, u.avatar AS user_avatar, mn.mention_type, mn.created_at AS mentioned_at
             FROM chat_mentions mn
             INNER JOIN chat_messages m ON m.id = mn.message_id
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.company_id = :cid AND m.deleted_at IS NULL
               AND (mn.mentioned_user_id = :uid OR mn.mention_type IN (\'channel\', \'here\'))
             ORDER BY mn.created_at DESC LIMIT 100',
            ['cid' => $companyId, 'uid' => $userId]
        );
        // Filter mass mentions to rooms user can access
        $out = [];
        foreach ($rows as $row) {
            if ($this->canAccessMessage($row, $userId)) {
                $out[] = $this->hydrateMessage($row, $userId);
            }
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    public function search(int $companyId, int $userId, string $q, int $limit = 40): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        $limit = max(1, min(80, $limit));

        // Membership is enforced in SQL so LIMIT counts only rows the viewer may read.
        // Filtering afterwards in PHP can return an empty page while older readable matches exist.
        $visible = '((m.channel_id IS NOT NULL AND EXISTS (
                          SELECT 1 FROM chat_channel_members ccm
                          WHERE ccm.channel_id = m.channel_id AND ccm.user_id = :uid AND ccm.left_at IS NULL))
                     OR (m.conversation_id IS NOT NULL AND EXISTS (
                          SELECT 1 FROM chat_conversation_members ccv
                          WHERE ccv.conversation_id = m.conversation_id AND ccv.user_id = :uid2 AND ccv.left_at IS NULL)))';

        $params = ['cid' => $companyId, 'uid' => $userId, 'uid2' => $userId];

        $boolean = $this->booleanFulltextQuery($q);
        if ($boolean !== null && $this->hasBodyFulltextIndex()) {
            // Uses ft_chat_messages_body; a leading-wildcard LIKE cannot use any index.
            $match = 'MATCH(m.body) AGAINST(:q IN BOOLEAN MODE)';
            $params['q'] = $boolean;
        } else {
            // Terms below the full-text token size, or index absent on an older install.
            $match = 'm.body LIKE :q';
            $params['q'] = '%' . $q . '%';
        }

        $rows = $this->db->fetchAll(
            'SELECT m.*, u.name AS user_name, u.avatar AS user_avatar
             FROM chat_messages m
             INNER JOIN users u ON u.id = m.user_id
             WHERE m.company_id = :cid AND m.deleted_at IS NULL
               AND ' . $match . '
               AND ' . $visible . '
             ORDER BY m.id DESC LIMIT ' . $limit,
            $params
        );

        $out = [];
        foreach ($rows as $row) {
            $hydrated = $this->hydrateMessage($row, $userId);
            if ($hydrated !== null) {
                $out[] = $hydrated;
            }
        }
        return $out;
    }

    /**
     * Build a BOOLEAN MODE query from raw input: drop the operator characters that would
     * otherwise change the query's meaning, require every term, and prefix-match each one.
     * Returns null when no term is long enough for the full-text tokenizer to index.
     */
    private function booleanFulltextQuery(string $q): ?string
    {
        $minTokenSize = 3; // innodb_ft_min_token_size default
        $cleaned = (string) preg_replace('/[+\-><()~*"@]+/', ' ', $q);

        $terms = [];
        foreach (preg_split('/\s+/', $cleaned, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $term) {
            if (mb_strlen($term) >= $minTokenSize) {
                $terms[] = '+' . $term . '*';
            }
        }

        return $terms ? implode(' ', $terms) : null;
    }

    /** Cached check that the full-text index this search depends on exists. */
    private function hasBodyFulltextIndex(): bool
    {
        static $has = null;
        if ($has !== null) {
            return $has;
        }

        try {
            $has = ((int) $this->db->fetchColumn(
                "SELECT COUNT(*) FROM information_schema.statistics
                 WHERE table_schema = DATABASE() AND table_name = 'chat_messages'
                   AND index_name = 'ft_chat_messages_body'"
            )) > 0;
        } catch (\Throwable) {
            $has = false;
        }

        return $has;
    }

    /** @return list<array<string,mixed>> */
    public function pinsForTarget(int $companyId, ?int $channelId, ?int $conversationId): array
    {
        $params = ['cid' => $companyId];
        $sql = 'SELECT p.*, m.body, m.body_html, m.user_id, u.name AS user_name
                FROM chat_pins p
                INNER JOIN chat_messages m ON m.id = p.message_id
                INNER JOIN users u ON u.id = m.user_id
                WHERE p.company_id = :cid AND m.deleted_at IS NULL';
        if ($channelId) {
            $sql .= ' AND p.channel_id = :chid';
            $params['chid'] = $channelId;
        }
        if ($conversationId) {
            $sql .= ' AND p.conversation_id = :convid';
            $params['convid'] = $conversationId;
        }
        $sql .= ' ORDER BY p.created_at DESC';
        return $this->db->fetchAll($sql, $params);
    }

    public function acknowledgeMessage(int $companyId, int $userId, int $messageId): array
    {
        $msg = $this->getMessage($messageId, $companyId);
        if (!$msg || !(int) $msg['requires_acknowledgement']) {
            return ['success' => false, 'message' => 'Message does not require acknowledgement.'];
        }
        if (!$this->canAccessMessage($msg, $userId)) {
            return ['success' => false, 'message' => 'Forbidden.'];
        }

        $exists = $this->db->fetch(
            'SELECT id FROM chat_document_acknowledgements WHERE message_id = :mid AND user_id = :uid',
            ['mid' => $messageId, 'uid' => $userId]
        );
        if ($exists) {
            return ['success' => true, 'message' => 'Already acknowledged.'];
        }

        // Use a synthetic document_id=0 path is invalid due to FK; store via message_reads + meta flag table reuse
        // Prefer a lightweight ack via message_reads with special note in meta — use chat_document_acknowledgements only for docs.
        // For announcement message acks we insert into chat_message_reads and a dedicated ack marker in mentions table? Better:
        $this->db->query(
            'INSERT IGNORE INTO chat_message_reads (message_id, user_id, read_at) VALUES (:mid, :uid, :at)',
            ['mid' => $messageId, 'uid' => $userId, 'at' => ChatSupport::now()]
        );

        // Store ack in a JSON meta list via separate inserts into chat_mentions? No.
        // Use chat_document_acknowledgements with document_id nullable — schema requires document_id.
        // Add acknowledgment via message meta update:
        $meta = [];
        if (!empty($msg['meta'])) {
            $meta = json_decode((string) $msg['meta'], true) ?: [];
        }
        $acks = $meta['acknowledgements'] ?? [];
        if (!in_array($userId, $acks, true)) {
            $acks[] = $userId;
            $meta['acknowledgements'] = $acks;
            $this->db->update('chat_messages', ['meta' => json_encode($meta)], 'id = :id', ['id' => $messageId]);
        }

        return ['success' => true, 'message' => 'Acknowledged.'];
    }

    private function canAccessMessage(array $msg, int $userId): bool
    {
        if (!empty($msg['channel_id'])) {
            return $this->channels->ensureMembership((int) $msg['channel_id'], $userId);
        }
        if (!empty($msg['conversation_id'])) {
            return $this->conversations->ensureMembership((int) $msg['conversation_id'], $userId);
        }
        return false;
    }

    private function hydrateMessage(?array $row, int $viewerId): ?array
    {
        if (!$row) {
            return null;
        }
        $mid = (int) $row['id'];
        $row['reactions'] = $this->reactionsFor($mid);
        $row['attachments'] = $this->attachmentsFor($mid);
        $row['is_saved'] = (bool) $this->db->fetch(
            'SELECT id FROM chat_saved_messages WHERE user_id = :uid AND message_id = :mid',
            ['uid' => $viewerId, 'mid' => $mid]
        );
        $row['is_pinned'] = (bool) $this->db->fetch('SELECT id FROM chat_pins WHERE message_id = :mid', ['mid' => $mid]);
        $row['read_count'] = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM chat_message_reads WHERE message_id = :mid',
            ['mid' => $mid]
        );
        $row['initials'] = ChatSupport::initials((string) ($row['user_name'] ?? '?'));
        $row['is_deleted'] = !empty($row['deleted_at']);
        if ($row['is_deleted']) {
            $row['body'] = '';
            $row['body_html'] = '<em class="chat-deleted">Message deleted</em>';
        }
        return $row;
    }

    /** @return list<array{emoji:string,count:int,me:bool}> */
    private function reactionsFor(int $messageId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT emoji, COUNT(*) AS cnt, GROUP_CONCAT(user_id) AS user_ids
             FROM chat_reactions WHERE message_id = :mid GROUP BY emoji ORDER BY cnt DESC, emoji ASC',
            ['mid' => $messageId]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'emoji' => $r['emoji'],
                'count' => (int) $r['cnt'],
                'user_ids' => array_map('intval', explode(',', (string) $r['user_ids'])),
            ];
        }
        return $out;
    }

    /** @return list<array<string,mixed>> */
    private function attachmentsFor(int $messageId): array
    {
        return $this->db->fetchAll(
            'SELECT id, uuid, original_filename, mime_type, file_size, preview_type, width, height
             FROM chat_attachments WHERE message_id = :mid AND deleted_at IS NULL',
            ['mid' => $messageId]
        );
    }

    /**
     * @return list<array{type:string,user_id:?int}>
     */
    private function extractAndStoreMentions(
        int $messageId,
        string $body,
        int $companyId,
        ?int $channelId,
        int $authorId,
        bool $allowMass
    ): array {
        $mentions = [];
        if (preg_match_all('/@([a-zA-Z0-9._-]{2,64})/', $body, $matches)) {
            foreach (array_unique($matches[1]) as $handle) {
                $lower = strtolower($handle);
                if (in_array($lower, ['channel', 'here'], true)) {
                    if (!$allowMass) {
                        continue;
                    }
                    $this->db->insert('chat_mentions', [
                        'message_id' => $messageId,
                        'mentioned_user_id' => null,
                        'mention_type' => $lower,
                        'created_at' => ChatSupport::now(),
                    ]);
                    $mentions[] = ['type' => $lower, 'user_id' => null];
                    continue;
                }

                $user = $this->db->fetch(
                    'SELECT u.id FROM users u
                     INNER JOIN employees e ON e.user_id = u.id AND e.company_id = :cid AND e.deleted_at IS NULL
                     WHERE u.deleted_at IS NULL AND (u.username = :h OR u.email LIKE :email OR u.name = :name)
                     LIMIT 1',
                    ['cid' => $companyId, 'h' => $handle, 'email' => $handle . '@%', 'name' => $handle]
                );
                // Also match first name tokens loosely
                if (!$user) {
                    $user = $this->db->fetch(
                        'SELECT u.id FROM users u
                         INNER JOIN employees e ON e.user_id = u.id AND e.company_id = :cid AND e.deleted_at IS NULL
                         WHERE u.deleted_at IS NULL AND LOWER(u.name) LIKE :q
                         LIMIT 1',
                        ['cid' => $companyId, 'q' => strtolower($handle) . '%']
                    );
                }
                if ($user && (int) $user['id'] !== $authorId) {
                    $this->db->insert('chat_mentions', [
                        'message_id' => $messageId,
                        'mentioned_user_id' => (int) $user['id'],
                        'mention_type' => 'user',
                        'created_at' => ChatSupport::now(),
                    ]);
                    $mentions[] = ['type' => 'user', 'user_id' => (int) $user['id']];
                }
            }
        }
        return $mentions;
    }

    /**
     * @param list<array{type:string,user_id:?int}> $mentions
     */
    private function notifyMentions(
        int $companyId,
        int $authorId,
        int $messageId,
        ?int $channelId,
        ?int $conversationId,
        array $mentions
    ): void {
        $notifier = new NotificationService();
        $url = $this->chatActionUrl($channelId, $conversationId, $messageId);

        $userIds = [];
        foreach ($mentions as $m) {
            if ($m['type'] === 'user' && $m['user_id']) {
                $userIds[] = (int) $m['user_id'];
            } elseif (in_array($m['type'], ['channel', 'here'], true) && $channelId) {
                $userIds = array_merge($userIds, $this->channels->memberUserIds($channelId));
            }
        }
        $userIds = array_values(array_unique(array_filter($userIds, static fn ($id) => $id !== $authorId)));
        if ($userIds) {
            $notifier->notifyMany($userIds, 'Chat mention', 'You were mentioned in Team Chat.', $url, 'chat_mention');
        }
    }

    /**
     * @param list<int> $excludeUserIds Already notified (e.g. @mentions)
     */
    /**
     * @param list<int> $excludeUserIds Already notified (e.g. @mentions)
     * @return list<int> Recipient users.id values notified (excluding sender)
     */
    private function notifyMessageRecipients(
        int $companyId,
        int $authorId,
        int $messageId,
        ?int $channelId,
        ?int $conversationId,
        string $body,
        array $excludeUserIds = []
    ): array {
        // Same path for admin and employee: participants where user_id !== sender users.id
        if ($channelId) {
            $recipientIds = $this->channels->recipientUserIds($channelId, $authorId);
        } elseif ($conversationId) {
            $recipientIds = $this->conversations->recipientUserIds($conversationId, $authorId);
        } else {
            $recipientIds = [];
        }

        $exclude = array_fill_keys(array_map('intval', $excludeUserIds), true);
        $recipientIds = array_values(array_filter(
            array_unique(array_map('intval', $recipientIds)),
            static fn (int $id) => $id > 0 && $id !== $authorId && !isset($exclude[$id])
        ));

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'notify_recipients',
            'message_id' => $messageId,
            'sender_user_id' => $authorId,
            'company_id' => $companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'recipient_user_ids' => $recipientIds,
        ]);
        if ($channelId) {
            ChatSupport::debugLog('channel_message_delivery', [
                'event' => 'notify_recipients',
                'message_id' => $messageId,
                'sender_user_id' => $authorId,
                'company_id' => $companyId,
                'channel_id' => $channelId,
                'recipient_user_ids' => $recipientIds,
            ]);
        }

        if ($recipientIds === []) {
            return [];
        }

        $author = $this->db->fetch('SELECT name FROM users WHERE id = :id', ['id' => $authorId]);
        $authorName = trim((string) ($author['name'] ?? 'Someone'));
        $preview = trim(preg_replace('/\s+/', ' ', $body) ?? $body);
        if (mb_strlen($preview) > 120) {
            $preview = mb_substr($preview, 0, 117) . '…';
        }
        if ($preview === '') {
            $preview = 'Sent an attachment';
        }

        if ($conversationId) {
            $title = 'New message from ' . $authorName;
        } else {
            $channel = $channelId ? $this->channels->getChannel($channelId, $companyId) : null;
            $channelName = $channel['name'] ?? 'channel';
            $title = 'New message in #' . $channelName;
        }

        (new NotificationService())->notifyMany(
            $recipientIds,
            $title,
            $preview,
            $this->chatActionUrl($channelId, $conversationId, $messageId),
            'chat_message'
        );
        return $recipientIds;
    }

    private function senderRoleLabel(int $userId, int $companyId): string
    {
        $role = $this->db->fetch(
            "SELECT r.slug FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id
             WHERE ur.user_id = :uid AND (ur.company_id = :cid OR ur.company_id IS NULL)
             ORDER BY FIELD(r.slug, 'super_admin', 'company_admin', 'hr_manager', 'department_manager', 'employee'), r.id
             LIMIT 1",
            ['uid' => $userId, 'cid' => $companyId]
        );
        if ($role && !empty($role['slug'])) {
            return (string) $role['slug'];
        }
        $emp = $this->db->fetch(
            'SELECT id FROM employees WHERE user_id = :uid AND company_id = :cid AND deleted_at IS NULL LIMIT 1',
            ['uid' => $userId, 'cid' => $companyId]
        );
        return $emp ? 'employee' : 'user';
    }

    private function chatActionUrl(?int $channelId, ?int $conversationId, int $messageId): string
    {
        $url = '/chat?';
        if ($channelId) {
            $url .= 'channel=' . $channelId;
        } elseif ($conversationId) {
            $url .= 'dm=' . $conversationId;
        }
        $url .= '&msg=' . $messageId;
        return $url;
    }
}
