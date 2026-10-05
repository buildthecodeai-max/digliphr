<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringScreenshotService
{
    private Database $db;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_screenshots WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function findByCaptureId(string $captureId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_screenshots WHERE capture_id = :cid LIMIT 1',
            ['cid' => $captureId]
        );
    }

    public function absolutePath(array $row, bool $thumbnail = false): ?string
    {
        $rel = $thumbnail ? ($row['thumbnail_path'] ?? null) : ($row['storage_path'] ?? null);
        if (!$rel) {
            return null;
        }
        $base = rtrim((string) config('app.paths.monitoring'), '/');
        return $base . '/' . ltrim($rel, '/');
    }

    public function markViewed(int $id): void
    {
        $this->db->query(
            'UPDATE monitoring_screenshots SET viewed_count = viewed_count + 1 WHERE id = :id',
            ['id' => $id]
        );
        $this->audit->log('other', 'monitoring_screenshots', $id, null, ['action' => 'screenshot_viewed']);
    }

    public function softDelete(int $id, ?int $userId = null): array
    {
        $row = $this->find($id);
        if (!$row) {
            return ['success' => false, 'message' => 'Screenshot not found.'];
        }

        foreach (['storage_path', 'thumbnail_path'] as $field) {
            $path = $this->absolutePath($row, $field === 'thumbnail_path');
            if ($path && is_file($path)) {
                @unlink($path);
            }
        }

        $this->db->update('monitoring_screenshots', [
            'upload_status' => 'deleted',
            'deleted_at' => date('Y-m-d H:i:s'),
            'storage_path' => null,
            'thumbnail_path' => null,
        ], 'id = :id', ['id' => $id]);

        $this->audit->log('delete', 'monitoring_screenshots', $id, $row, ['deleted' => true], $userId);
        (new MonitoringUploadService())->recalculateStorage((int) $row['company_id']);

        return ['success' => true, 'message' => 'Screenshot deleted.'];
    }

    /**
     * @return array{data: list<array>, total: int, page: int, per_page: int}
     */
    public function search(array $filters, string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 24): array
    {
        $where = ['s.deleted_at IS NULL', "({$scopeSql})"];
        $params = $scopeParams;

        if (!empty($filters['employee_id'])) {
            $where[] = 's.employee_id = :employee_id';
            $params['employee_id'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['session_id'])) {
            $where[] = 's.session_id = :session_id';
            $params['session_id'] = (int) $filters['session_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 's.captured_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.captured_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['upload_status'])) {
            $where[] = 's.upload_status = :upload_status';
            $params['upload_status'] = $filters['upload_status'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_screenshots s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT s.*, e.first_name, e.last_name, e.employee_code
             FROM monitoring_screenshots s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}
             ORDER BY s.captured_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}
