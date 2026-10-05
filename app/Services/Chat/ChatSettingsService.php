<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;

final class ChatSettingsService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return array<string,string> */
    public function all(int $companyId): array
    {
        $out = [
            'polling_interval_seconds' => '5',
            'max_upload_bytes' => '10485760',
            'allow_mass_mentions' => '1',
        ];
        if (!$this->db->tableExists('chat_settings')) {
            return $out;
        }
        try {
            $rows = $this->db->fetchAll(
                'SELECT setting_key, setting_value FROM chat_settings WHERE company_id = :cid',
                ['cid' => $companyId]
            );
        } catch (\Throwable) {
            return $out;
        }
        foreach ($rows as $row) {
            $out[$row['setting_key']] = (string) $row['setting_value'];
        }
        return $out;
    }

    public function get(int $companyId, string $key, ?string $default = null): ?string
    {
        $all = $this->all($companyId);
        return $all[$key] ?? $default;
    }

    /** @param array<string,string|int|bool> $values */
    public function save(int $companyId, array $values): void
    {
        foreach ($values as $key => $value) {
            $key = preg_replace('/[^a-z0-9_]/', '', strtolower((string) $key)) ?? '';
            if ($key === '') {
                continue;
            }
            $str = is_bool($value) ? ($value ? '1' : '0') : (string) $value;
            $exists = $this->db->fetch(
                'SELECT id FROM chat_settings WHERE company_id = :cid AND setting_key = :k',
                ['cid' => $companyId, 'k' => $key]
            );
            if ($exists) {
                $this->db->update('chat_settings', [
                    'setting_value' => $str,
                    'updated_at' => ChatSupport::now(),
                ], 'id = :id', ['id' => $exists['id']]);
            } else {
                $this->db->insert('chat_settings', [
                    'company_id' => $companyId,
                    'setting_key' => $key,
                    'setting_value' => $str,
                    'created_at' => ChatSupport::now(),
                    'updated_at' => ChatSupport::now(),
                ]);
            }
        }
    }

    public function getOrCreatePreferences(int $companyId, int $userId): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM chat_notification_preferences
             WHERE user_id = :uid AND company_id = :cid AND channel_id IS NULL',
            ['uid' => $userId, 'cid' => $companyId]
        );
        if ($row) {
            return $row;
        }
        $id = $this->db->insert('chat_notification_preferences', [
            'user_id' => $userId,
            'company_id' => $companyId,
            'channel_id' => null,
            'notify_dms' => 1,
            'notify_mentions' => 1,
            'notify_channel_messages' => 1,
            'created_at' => ChatSupport::now(),
            'updated_at' => ChatSupport::now(),
        ]);
        return $this->db->fetch('SELECT * FROM chat_notification_preferences WHERE id = :id', ['id' => $id]) ?? [];
    }

    public function savePreferences(int $companyId, int $userId, array $data): void
    {
        $prefs = $this->getOrCreatePreferences($companyId, $userId);
        $this->db->update('chat_notification_preferences', [
            'notify_dms' => !empty($data['notify_dms']) ? 1 : 0,
            'notify_mentions' => !empty($data['notify_mentions']) ? 1 : 0,
            'notify_channel_messages' => !empty($data['notify_channel_messages']) ? 1 : 0,
            'quiet_hours_start' => $data['quiet_hours_start'] ?: null,
            'quiet_hours_end' => $data['quiet_hours_end'] ?: null,
            'updated_at' => ChatSupport::now(),
        ], 'id = :id', ['id' => $prefs['id']]);
    }
}
