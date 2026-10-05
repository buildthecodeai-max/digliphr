<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

class NotificationService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function notify(int $userId, string $title, string $message, ?string $actionUrl = null, string $type = 'info'): void
    {
        if ($userId <= 0) {
            return;
        }

        $this->db->insert('notifications', [
            'uuid' => $this->uuid(),
            'user_id' => $userId,
            'type' => $type !== '' ? $type : 'info',
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'is_read' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function notifyMany(
        array $userIds,
        string $title,
        string $message,
        ?string $actionUrl = null,
        string $type = 'info'
    ): void {
        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId > 0) {
                $this->notify($userId, $title, $message, $actionUrl, $type);
            }
        }
    }

    public function unreadCount(int $userId): int
    {
        return (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );
    }

    /**
     * Mark unread notifications whose action_url matches a chat deep-link as read.
     */
    public function markChatTargetRead(int $userId, ?int $channelId, ?int $conversationId): int
    {
        if ($userId <= 0 || (!$channelId && !$conversationId)) {
            return 0;
        }

        $patterns = [];
        if ($channelId) {
            $patterns[] = '%channel=' . (int) $channelId . '%';
        }
        if ($conversationId) {
            $patterns[] = '%dm=' . (int) $conversationId . '%';
        }

        $updated = 0;
        foreach ($patterns as $i => $like) {
            $key = 'p' . $i;
            $updated += $this->db->update('notifications', [
                'is_read' => 1,
                'read_at' => date('Y-m-d H:i:s'),
            ], "user_id = :uid AND is_read = 0 AND action_url LIKE :{$key}", [
                'uid' => $userId,
                $key => $like,
            ]);
        }

        return $updated;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
