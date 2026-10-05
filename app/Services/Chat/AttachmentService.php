<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;
use App\Services\AuditService;
use App\Services\FileUploadService;

final class AttachmentService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int} $file
     */
    public function storeForMessage(int $companyId, int $userId, int $messageId, array $file, int $maxBytes = 10485760): array
    {
        $directory = rtrim((string) config('app.paths.chat_attachments'), '/') . '/' . $companyId . '/' . date('Y/m');
        $uploader = new FileUploadService();
        $result = $uploader->storeUpload($file, $directory, [
            'allowed_mimes' => ChatSupport::allowedMimes(),
            'max_size' => $maxBytes,
        ]);

        if (!$result['success']) {
            return $result;
        }

        $mime = (string) ($result['mime_type'] ?? 'application/octet-stream');
        $relative = $companyId . '/' . date('Y/m') . '/' . ($result['filename'] ?? '');

        $id = $this->db->insert('chat_attachments', [
            'uuid' => ChatSupport::uuid(),
            'company_id' => $companyId,
            'message_id' => $messageId,
            'uploaded_by' => $userId,
            'original_filename' => (string) ($result['original_filename'] ?? 'file'),
            'stored_filename' => (string) ($result['filename'] ?? ''),
            'relative_path' => $relative,
            'mime_type' => $mime,
            'extension' => pathinfo((string) ($result['filename'] ?? ''), PATHINFO_EXTENSION) ?: null,
            'file_size' => (int) ($result['size'] ?? 0),
            'virus_scan_status' => 'skipped',
            'preview_type' => ChatSupport::previewType($mime),
            'created_at' => ChatSupport::now(),
        ]);

        $this->db->query(
            'UPDATE chat_messages SET attachment_count = attachment_count + 1, message_type = IF(message_type = \'text\', \'file\', message_type) WHERE id = :id',
            ['id' => $messageId]
        );

        (new AuditService())->log('create', 'chat_attachments', $id);

        return [
            'success' => true,
            'attachment' => $this->db->fetch('SELECT * FROM chat_attachments WHERE id = :id', ['id' => $id]),
        ];
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM chat_attachments WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function absolutePath(array $attachment): string
    {
        $base = rtrim((string) config('app.paths.chat_attachments'), '/');
        return $base . '/' . ltrim((string) $attachment['relative_path'], '/');
    }

    public function logDownload(int $attachmentId, int $userId): void
    {
        $this->db->insert('chat_file_download_logs', [
            'attachment_id' => $attachmentId,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'created_at' => ChatSupport::now(),
        ]);
    }

    /** @return list<array<string,mixed>> */
    public function library(int $companyId, int $userId, string $q = ''): array
    {
        $channels = new ChannelService();
        $conversations = new ConversationService();
        $params = ['cid' => $companyId];
        $sql = 'SELECT a.*, m.channel_id, m.conversation_id, u.name AS uploader_name
                FROM chat_attachments a
                INNER JOIN chat_messages m ON m.id = a.message_id
                INNER JOIN users u ON u.id = a.uploaded_by
                WHERE a.company_id = :cid AND a.deleted_at IS NULL AND m.deleted_at IS NULL';
        if ($q !== '') {
            $sql .= ' AND a.original_filename LIKE :q';
            $params['q'] = '%' . $q . '%';
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 200';
        $rows = $this->db->fetchAll($sql, $params);

        $out = [];
        foreach ($rows as $row) {
            $ok = false;
            if (!empty($row['channel_id'])) {
                $ok = $channels->ensureMembership((int) $row['channel_id'], $userId);
            } elseif (!empty($row['conversation_id'])) {
                $ok = $conversations->ensureMembership((int) $row['conversation_id'], $userId);
            }
            if ($ok) {
                $out[] = $row;
            }
        }
        return $out;
    }
}
