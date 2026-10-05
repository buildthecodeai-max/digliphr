<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringRetentionService
{
    private Database $db;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
    }

    /**
     * @return array{screenshots: int, activities: int, heartbeats: int}
     */
    public function cleanup(int $batchSize = 200): array
    {
        $deletedScreenshots = $this->cleanupScreenshots($batchSize);
        $deletedActivities = $this->cleanupActivities($batchSize);
        $deletedHeartbeats = $this->cleanupHeartbeats($batchSize);

        $this->audit->log('other', 'monitoring_retention', null, null, [
            'action' => 'retention_cleanup',
            'screenshots' => $deletedScreenshots,
            'activities' => $deletedActivities,
            'heartbeats' => $deletedHeartbeats,
        ]);

        return [
            'screenshots' => $deletedScreenshots,
            'activities' => $deletedActivities,
            'heartbeats' => $deletedHeartbeats,
        ];
    }

    private function cleanupScreenshots(int $batchSize): int
    {
        $defaultDays = (int) config('app.monitoring.screenshot_retention_days', 30);
        $rows = $this->db->fetchAll(
            'SELECT id, company_id, storage_path, thumbnail_path
             FROM monitoring_screenshots
             WHERE deleted_at IS NULL
               AND (
                    (retention_expires_at IS NOT NULL AND retention_expires_at < NOW())
                    OR (retention_expires_at IS NULL AND captured_at < DATE_SUB(NOW(), INTERVAL ' . $defaultDays . ' DAY))
               )
             ORDER BY id ASC
             LIMIT ' . (int) $batchSize
        );

        $base = rtrim((string) config('app.paths.monitoring'), '/');
        $count = 0;
        $companies = [];

        foreach ($rows as $row) {
            foreach (['storage_path', 'thumbnail_path'] as $field) {
                if (!empty($row[$field])) {
                    $path = $base . '/' . ltrim((string) $row[$field], '/');
                    if (is_file($path)) {
                        @unlink($path);
                    }
                }
            }
            $this->db->update('monitoring_screenshots', [
                'upload_status' => 'deleted',
                'deleted_at' => date('Y-m-d H:i:s'),
                'storage_path' => null,
                'thumbnail_path' => null,
            ], 'id = :id', ['id' => $row['id']]);
            $companies[(int) $row['company_id']] = true;
            $count++;
        }

        $uploader = new MonitoringUploadService();
        foreach (array_keys($companies) as $companyId) {
            $uploader->recalculateStorage($companyId);
        }

        return $count;
    }

    private function cleanupActivities(int $batchSize): int
    {
        $days = (int) config('app.monitoring.activity_retention_days', 90);
        $rows = $this->db->fetchAll(
            'SELECT id FROM monitoring_activity_segments
             WHERE started_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
             ORDER BY id ASC LIMIT ' . (int) $batchSize
        );
        $count = 0;
        foreach ($rows as $row) {
            $this->db->delete('monitoring_activity_segments', 'id = :id', ['id' => $row['id']]);
            $count++;
        }
        return $count;
    }

    private function cleanupHeartbeats(int $batchSize): int
    {
        $days = (int) config('app.monitoring.heartbeat_retention_days', 30);
        $rows = $this->db->fetchAll(
            'SELECT id FROM monitoring_heartbeats
             WHERE created_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)
             ORDER BY id ASC LIMIT ' . (int) $batchSize
        );
        $count = 0;
        foreach ($rows as $row) {
            $this->db->delete('monitoring_heartbeats', 'id = :id', ['id' => $row['id']]);
            $count++;
        }
        return $count;
    }
}
