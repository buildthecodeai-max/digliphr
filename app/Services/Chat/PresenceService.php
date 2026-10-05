<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;

final class PresenceService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function heartbeat(int $companyId, int $userId, ?int $channelId = null, ?int $conversationId = null): void
    {
        $existing = $this->db->fetch('SELECT user_id FROM chat_presence WHERE user_id = :uid', ['uid' => $userId]);
        $data = [
            'company_id' => $companyId,
            'status' => 'online',
            'last_seen_at' => ChatSupport::now(),
            'current_channel_id' => $channelId,
            'current_conversation_id' => $conversationId,
            'updated_at' => ChatSupport::now(),
        ];
        if ($existing) {
            $this->db->update('chat_presence', $data, 'user_id = :uid', ['uid' => $userId]);
        } else {
            $this->db->insert('chat_presence', array_merge(['user_id' => $userId], $data));
        }
    }

    public function setTyping(int $companyId, int $userId, ?int $channelId, ?int $conversationId): void
    {
        $expires = date('Y-m-d H:i:s', time() + 6);
        // Unique key includes nullable columns; delete then insert for simplicity
        if ($channelId) {
            $this->db->delete('chat_typing', 'user_id = :uid AND channel_id = :cid', [
                'uid' => $userId,
                'cid' => $channelId,
            ]);
            $this->db->insert('chat_typing', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'channel_id' => $channelId,
                'conversation_id' => null,
                'expires_at' => $expires,
                'updated_at' => ChatSupport::now(),
            ]);
        } elseif ($conversationId) {
            $this->db->delete('chat_typing', 'user_id = :uid AND conversation_id = :cid', [
                'uid' => $userId,
                'cid' => $conversationId,
            ]);
            $this->db->insert('chat_typing', [
                'company_id' => $companyId,
                'user_id' => $userId,
                'channel_id' => null,
                'conversation_id' => $conversationId,
                'expires_at' => $expires,
                'updated_at' => ChatSupport::now(),
            ]);
        }
    }

    /** @return list<array<string,mixed>> */
    public function typingFor(int $companyId, ?int $channelId, ?int $conversationId, int $excludeUserId): array
    {
        $this->db->delete('chat_typing', 'expires_at < :now', ['now' => ChatSupport::now()]);
        $params = ['cid' => $companyId, 'uid' => $excludeUserId];
        $sql = 'SELECT t.user_id, u.name
                FROM chat_typing t
                INNER JOIN users u ON u.id = t.user_id
                WHERE t.company_id = :cid AND t.user_id <> :uid AND t.expires_at >= NOW()';
        if ($channelId) {
            $sql .= ' AND t.channel_id = :chid';
            $params['chid'] = $channelId;
        }
        if ($conversationId) {
            $sql .= ' AND t.conversation_id = :convid';
            $params['convid'] = $conversationId;
        }
        return $this->db->fetchAll($sql, $params);
    }

    /** @return list<array<string,mixed>> */
    public function onlineUsers(int $companyId): array
    {
        $cutoff = date('Y-m-d H:i:s', time() - 45);
        return $this->db->fetchAll(
            'SELECT p.user_id, p.status, p.last_seen_at, u.name, u.avatar
             FROM chat_presence p
             INNER JOIN users u ON u.id = p.user_id
             WHERE p.company_id = :cid AND p.last_seen_at >= :cut
             ORDER BY u.name ASC',
            ['cid' => $companyId, 'cut' => $cutoff]
        );
    }
}
