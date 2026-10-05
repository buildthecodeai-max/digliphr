<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;
use App\Services\FileUploadService;
use App\Services\NotificationService;

final class DocumentService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /** @return list<array<string,mixed>> */
    public function listDocuments(int $companyId): array
    {
        return $this->db->fetchAll(
            'SELECT d.*, c.name AS category_name,
                    (SELECT COUNT(*) FROM chat_document_acknowledgements a WHERE a.document_id = d.id AND a.version_number = d.current_version) AS ack_count
             FROM chat_shared_documents d
             LEFT JOIN chat_document_categories c ON c.id = d.category_id
             WHERE d.company_id = :cid AND d.deleted_at IS NULL AND d.is_published = 1
             ORDER BY d.updated_at DESC',
            ['cid' => $companyId]
        );
    }

    /**
     * @param array{name?:string,type?:string,tmp_name?:string,error?:int,size?:int}|null $file
     */
    public function createOrVersion(
        int $companyId,
        int $userId,
        string $title,
        ?string $description,
        ?int $categoryId,
        bool $requiresAck,
        ?array $file,
        ?int $documentId = null,
        ?string $changeNotes = null
    ): array {
        if ($documentId) {
            $doc = $this->db->fetch(
                'SELECT * FROM chat_shared_documents WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
                ['id' => $documentId, 'cid' => $companyId]
            );
            if (!$doc) {
                return ['success' => false, 'message' => 'Document not found.'];
            }
            $version = (int) $doc['current_version'] + 1;
            $this->db->update('chat_shared_documents', [
                'title' => $title,
                'description' => $description,
                'category_id' => $categoryId,
                'requires_acknowledgement' => $requiresAck ? 1 : 0,
                'current_version' => $version,
                'updated_at' => ChatSupport::now(),
            ], 'id = :id', ['id' => $documentId]);
        } else {
            $documentId = $this->db->insert('chat_shared_documents', [
                'uuid' => ChatSupport::uuid(),
                'company_id' => $companyId,
                'category_id' => $categoryId,
                'title' => $title,
                'description' => $description,
                'requires_acknowledgement' => $requiresAck ? 1 : 0,
                'current_version' => 1,
                'created_by' => $userId,
                'is_published' => 1,
                'created_at' => ChatSupport::now(),
                'updated_at' => ChatSupport::now(),
            ]);
            $version = 1;
        }

        $path = null;
        $filename = null;
        $stored = null;
        $mime = null;
        $size = null;

        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $directory = rtrim((string) config('app.paths.chat_attachments'), '/') . '/' . $companyId . '/docs';
            $result = (new FileUploadService())->storeUpload($file, $directory, [
                'allowed_mimes' => ChatSupport::allowedMimes(),
                'max_size' => 15728640,
            ]);
            if (!$result['success']) {
                return $result;
            }
            $stored = (string) $result['filename'];
            $filename = (string) $result['original_filename'];
            $path = $companyId . '/docs/' . $stored;
            $mime = (string) $result['mime_type'];
            $size = (int) $result['size'];
        }

        $this->db->insert('chat_document_versions', [
            'document_id' => $documentId,
            'version_number' => $version,
            'original_filename' => $filename,
            'stored_filename' => $stored,
            'relative_path' => $path,
            'mime_type' => $mime,
            'file_size' => $size,
            'change_notes' => $changeNotes,
            'uploaded_by' => $userId,
            'created_at' => ChatSupport::now(),
        ]);

        if ($requiresAck) {
            $userIds = $this->db->fetchAll(
                'SELECT user_id FROM employees WHERE company_id = :cid AND deleted_at IS NULL AND user_id IS NOT NULL',
                ['cid' => $companyId]
            );
            (new NotificationService())->notifyMany(
                array_map(static fn ($r) => (int) $r['user_id'], $userIds),
                'Document acknowledgement required',
                $title . ' (v' . $version . ') requires your acknowledgement.',
                '/chat/documents'
            );
        }

        return ['success' => true, 'message' => 'Document saved.', 'document_id' => $documentId, 'version' => $version];
    }

    public function acknowledge(int $companyId, int $userId, int $documentId): array
    {
        $doc = $this->db->fetch(
            'SELECT * FROM chat_shared_documents WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $documentId, 'cid' => $companyId]
        );
        if (!$doc) {
            return ['success' => false, 'message' => 'Document not found.'];
        }

        $exists = $this->db->fetch(
            'SELECT id FROM chat_document_acknowledgements
             WHERE document_id = :did AND version_number = :v AND user_id = :uid',
            ['did' => $documentId, 'v' => (int) $doc['current_version'], 'uid' => $userId]
        );
        if ($exists) {
            return ['success' => true, 'message' => 'Already acknowledged.'];
        }

        $this->db->insert('chat_document_acknowledgements', [
            'document_id' => $documentId,
            'version_number' => (int) $doc['current_version'],
            'user_id' => $userId,
            'acknowledged_at' => ChatSupport::now(),
        ]);

        return ['success' => true, 'message' => 'Acknowledged.'];
    }

    public function ensureDefaultCategories(int $companyId): void
    {
        foreach (['Policies' => 'policies', 'Handbooks' => 'handbooks', 'Forms' => 'forms'] as $name => $slug) {
            $exists = $this->db->fetch(
                'SELECT id FROM chat_document_categories WHERE company_id = :cid AND slug = :slug',
                ['cid' => $companyId, 'slug' => $slug]
            );
            if (!$exists) {
                $this->db->insert('chat_document_categories', [
                    'company_id' => $companyId,
                    'name' => $name,
                    'slug' => $slug,
                    'sort_order' => 0,
                    'created_at' => ChatSupport::now(),
                ]);
            }
        }
    }

    /** @return list<array<string,mixed>> */
    public function categories(int $companyId): array
    {
        $this->ensureDefaultCategories($companyId);
        return $this->db->fetchAll(
            'SELECT * FROM chat_document_categories WHERE company_id = :cid ORDER BY sort_order, name',
            ['cid' => $companyId]
        );
    }
}
