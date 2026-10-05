<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringUploadService
{
    private Database $db;
    private AuditService $audit;
    private MonitoringPolicyService $policies;
    private MonitoringAlertService $alerts;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
        $this->policies = new MonitoringPolicyService();
        $this->alerts = new MonitoringAlertService();
    }

    /**
     * Authorize a capture_id before binary upload (idempotent).
     *
     * @param array<string, mixed> $meta
     * @return array{success: bool, message: string, data?: array, code?: string}
     */
    public function authorize(array $session, array $meta): array
    {
        if (!in_array($session['status'], ['active', 'paused', 'offline', 'stopping'], true)) {
            return ['success' => false, 'message' => 'Session is not accepting screenshots.'];
        }

        $policy = $this->policies->find((int) $session['policy_id']);
        if ($policy && !(int) ($policy['screenshot_enabled'] ?? 1)) {
            return ['success' => false, 'message' => 'Screenshots are disabled by policy.'];
        }

        $captureId = trim((string) ($meta['capture_id'] ?? ''));
        if ($captureId === '') {
            return ['success' => false, 'message' => 'capture_id is required.'];
        }

        $existing = $this->db->fetch(
            'SELECT * FROM monitoring_screenshots WHERE capture_id = :cid LIMIT 1',
            ['cid' => $captureId]
        );
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Capture already authorized.',
                'data' => [
                    'screenshot_id' => (int) $existing['id'],
                    'capture_id' => $captureId,
                    'upload_status' => $existing['upload_status'],
                ],
            ];
        }

        $retentionDays = (int) ($policy['screenshot_retention_days'] ?? config('app.monitoring.screenshot_retention_days', 30));
        $capturedAt = $meta['captured_at'] ?? date('Y-m-d H:i:s');

        $id = $this->db->insert('monitoring_screenshots', [
            'uuid' => $this->uuid(),
            'capture_id' => $captureId,
            'session_id' => (int) $session['id'],
            'employee_id' => (int) $session['employee_id'],
            'attendance_id' => (int) $session['attendance_id'],
            'device_id' => $session['device_id'] ?? null,
            'company_id' => (int) $session['company_id'],
            'branch_id' => $session['branch_id'] ?? null,
            'department_id' => $session['department_id'] ?? null,
            'captured_at' => $capturedAt,
            'interval_minutes' => (int) ($session['screenshot_interval_minutes'] ?? 10),
            'width' => $meta['width'] ?? null,
            'height' => $meta['height'] ?? null,
            'original_size' => $meta['original_size'] ?? null,
            'checksum' => $meta['checksum'] ?? null,
            'mime_type' => $meta['mime_type'] ?? 'image/jpeg',
            'upload_status' => 'authorized',
            'active_application' => $meta['active_application'] ?? null,
            'retention_expires_at' => date('Y-m-d H:i:s', strtotime($capturedAt) + $retentionDays * 86400),
        ]);

        return [
            'success' => true,
            'message' => 'Upload authorized.',
            'data' => [
                'screenshot_id' => $id,
                'capture_id' => $captureId,
                'upload_status' => 'authorized',
            ],
        ];
    }

    /**
     * Accept binary (base64 or raw uploaded file path content).
     *
     * @return array{success: bool, message: string, data?: array}
     */
    public function uploadFile(array $session, string $captureId, string $binary, ?string $checksum = null, ?string $mime = null): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM monitoring_screenshots WHERE capture_id = :cid AND session_id = :sid LIMIT 1',
            ['cid' => $captureId, 'sid' => $session['id']]
        );
        if (!$row) {
            $auth = $this->authorize($session, [
                'capture_id' => $captureId,
                'checksum' => $checksum,
                'mime_type' => $mime,
            ]);
            if (!$auth['success']) {
                return $auth;
            }
            $row = $this->db->fetch(
                'SELECT * FROM monitoring_screenshots WHERE capture_id = :cid LIMIT 1',
                ['cid' => $captureId]
            );
        }

        if (($row['upload_status'] ?? '') === 'uploaded' && !empty($row['storage_path'])) {
            return [
                'success' => true,
                'message' => 'Screenshot already uploaded.',
                'data' => ['screenshot_id' => (int) $row['id'], 'capture_id' => $captureId],
            ];
        }

        if ($checksum && !empty($row['checksum']) && !hash_equals((string) $row['checksum'], $checksum)) {
            // prefer client-provided checksum for verification after write
        }
        $computed = hash('sha256', $binary);
        if ($checksum && !hash_equals($computed, $checksum)) {
            return ['success' => false, 'message' => 'Checksum mismatch.'];
        }

        $companyId = (int) $session['company_id'];
        $subdir = sprintf('company-%d/%s/%s', $companyId, date('Y/m/d'), $session['employee_id']);
        $base = rtrim((string) config('app.paths.monitoring'), '/');
        $dir = $base . '/' . $subdir;
        if (!is_dir($dir) && !mkdir($dir, 0750, true) && !is_dir($dir)) {
            return ['success' => false, 'message' => 'Unable to create storage directory.'];
        }

        $ext = 'jpg';
        $mime = $mime ?: ($row['mime_type'] ?? 'image/jpeg');
        if (str_contains($mime, 'png')) {
            $ext = 'png';
        } elseif (str_contains($mime, 'webp')) {
            $ext = 'webp';
        }

        $filename = $captureId . '.' . $ext;
        $relative = $subdir . '/' . $filename;
        $full = $dir . '/' . $filename;

        if (file_put_contents($full, $binary) === false) {
            $this->db->update('monitoring_screenshots', ['upload_status' => 'failed'], 'id = :id', ['id' => $row['id']]);
            return ['success' => false, 'message' => 'Failed to store screenshot.'];
        }
        chmod($full, 0640);

        $thumbRel = null;
        $thumbPath = $this->writeThumbnail($binary, $dir, $captureId, $ext);
        if ($thumbPath) {
            $thumbRel = $subdir . '/' . basename($thumbPath);
        }

        $info = @getimagesizefromstring($binary);
        $this->db->update('monitoring_screenshots', [
            'storage_path' => $relative,
            'thumbnail_path' => $thumbRel,
            'compressed_size' => strlen($binary),
            'checksum' => $computed,
            'mime_type' => $mime,
            'width' => $info[0] ?? $row['width'],
            'height' => $info[1] ?? $row['height'],
            'upload_status' => 'uploaded',
            'scan_status' => 'ok',
        ], 'id = :id', ['id' => $row['id']]);

        $this->recalculateStorage($companyId);
        $this->audit->log('other', 'monitoring_screenshots', (int) $row['id'], null, [
            'action' => 'screenshot_uploaded',
            'capture_id' => $captureId,
        ]);

        return [
            'success' => true,
            'message' => 'Screenshot uploaded.',
            'data' => [
                'screenshot_id' => (int) $row['id'],
                'capture_id' => $captureId,
                'checksum' => $computed,
            ],
        ];
    }

    /**
     * @return array{success: bool, message: string, data?: array}
     */
    public function confirm(array $session, string $captureId, ?string $checksum = null): array
    {
        $row = $this->db->fetch(
            'SELECT * FROM monitoring_screenshots WHERE capture_id = :cid AND session_id = :sid LIMIT 1',
            ['cid' => $captureId, 'sid' => $session['id']]
        );
        if (!$row) {
            return ['success' => false, 'message' => 'Screenshot record not found.'];
        }
        if (($row['upload_status'] ?? '') !== 'uploaded' || empty($row['storage_path'])) {
            return ['success' => false, 'message' => 'Screenshot upload not complete.'];
        }
        if ($checksum && $row['checksum'] && !hash_equals((string) $row['checksum'], $checksum)) {
            return ['success' => false, 'message' => 'Checksum confirmation failed.'];
        }

        return [
            'success' => true,
            'message' => 'Upload confirmed. Local file may be deleted.',
            'data' => [
                'screenshot_id' => (int) $row['id'],
                'capture_id' => $captureId,
                'checksum' => $row['checksum'],
                'delete_local' => true,
            ],
        ];
    }

    public function recalculateStorage(int $companyId): void
    {
        $stats = $this->db->fetch(
            'SELECT COUNT(*) AS cnt, COALESCE(SUM(compressed_size), 0) AS bytes
             FROM monitoring_screenshots
             WHERE company_id = :cid AND deleted_at IS NULL AND upload_status = "uploaded"',
            ['cid' => $companyId]
        );

        $existing = $this->db->fetch(
            'SELECT id FROM monitoring_storage_usage WHERE company_id = :cid',
            ['cid' => $companyId]
        );
        $data = [
            'used_bytes' => (int) ($stats['bytes'] ?? 0),
            'screenshot_count' => (int) ($stats['cnt'] ?? 0),
            'last_calculated_at' => date('Y-m-d H:i:s'),
        ];
        if ($existing) {
            $this->db->update('monitoring_storage_usage', $data, 'id = :id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('monitoring_storage_usage', array_merge($data, [
                'company_id' => $companyId,
                'created_at' => date('Y-m-d H:i:s'),
            ]));
        }
    }

    private function writeThumbnail(string $binary, string $dir, string $captureId, string $ext): ?string
    {
        if (!function_exists('imagecreatefromstring')) {
            return null;
        }
        $src = @imagecreatefromstring($binary);
        if (!$src) {
            return null;
        }
        $w = imagesx($src);
        $h = imagesy($src);
        $max = 320;
        $ratio = min($max / max(1, $w), $max / max(1, $h), 1.0);
        $tw = max(1, (int) floor($w * $ratio));
        $th = max(1, (int) floor($h * $ratio));
        $dst = imagecreatetruecolor($tw, $th);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $tw, $th, $w, $h);
        $path = $dir . '/' . $captureId . '_thumb.jpg';
        imagejpeg($dst, $path, 75);
        imagedestroy($src);
        imagedestroy($dst);
        chmod($path, 0640);
        return $path;
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
