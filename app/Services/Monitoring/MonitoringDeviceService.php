<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringDeviceService
{
    private Database $db;
    private AuditService $audit;
    private MonitoringAlertService $alerts;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
        $this->alerts = new MonitoringAlertService();
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM monitoring_devices WHERE id = :id', ['id' => $id]);
    }

    public function findByUid(string $deviceUid): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_devices WHERE device_uid = :uid LIMIT 1',
            ['uid' => $deviceUid]
        );
    }

    public function findByAccessToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        $row = $this->db->fetch(
            'SELECT * FROM monitoring_devices
             WHERE access_token_hash = :h
               AND access_token_expires_at IS NOT NULL
               AND access_token_expires_at > NOW()
               AND status = "approved"
             LIMIT 1',
            ['h' => $hash]
        );
        return $row;
    }

    /**
     * Register or refresh a device. New devices start as pending unless auto-approve is set.
     *
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array, code?: string}
     */
    public function register(int $employeeId, array $payload, bool $autoApprove = true): array
    {
        $employee = $this->db->fetch(
            'SELECT id, company_id, user_id FROM employees WHERE id = :id AND deleted_at IS NULL',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        $deviceUid = trim((string) ($payload['device_uid'] ?? ''));
        if ($deviceUid === '') {
            return ['success' => false, 'message' => 'device_uid is required.'];
        }

        $existing = $this->findByUid($deviceUid);
        $ttlDays = (int) config('app.monitoring.device_token_ttl_days', 30);
        $plainToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $plainToken);
        $expires = date('Y-m-d H:i:s', time() + $ttlDays * 86400);

        if ($existing) {
            if ((int) $existing['employee_id'] !== $employeeId) {
                return ['success' => false, 'message' => 'This device is registered to another employee.'];
            }
            if ($existing['status'] === 'revoked' || $existing['status'] === 'blocked') {
                $this->alerts->createActionRequired(
                    (int) $employee['company_id'],
                    $employeeId,
                    (int) $existing['id'],
                    null,
                    'device_not_approved',
                    'Device not approved',
                    'Your monitoring device is not approved. Contact your administrator.',
                    (int) ($employee['user_id'] ?? 0) ?: null
                );
                return [
                    'success' => false,
                    'code' => 'DEVICE_NOT_APPROVED',
                    'message' => 'This device is not authorized for monitoring.',
                ];
            }

            $update = [
                'hostname' => $payload['hostname'] ?? $existing['hostname'],
                'os_name' => $payload['os_name'] ?? $existing['os_name'],
                'os_version' => $payload['os_version'] ?? $existing['os_version'],
                'agent_version' => $payload['agent_version'] ?? $existing['agent_version'],
                'access_token_hash' => $tokenHash,
                'access_token_expires_at' => $expires,
                'last_seen_at' => date('Y-m-d H:i:s'),
            ];
            if ($autoApprove && $existing['status'] === 'pending') {
                $update['status'] = 'approved';
                $update['approved_at'] = date('Y-m-d H:i:s');
            }
            $this->db->update('monitoring_devices', $update, 'id = :id', ['id' => $existing['id']]);
            $device = $this->find((int) $existing['id']);
            $this->audit->log('other', 'monitoring_devices', (int) $existing['id'], $existing, $update);

            if (($device['status'] ?? '') !== 'approved') {
                return [
                    'success' => false,
                    'code' => 'DEVICE_NOT_APPROVED',
                    'message' => 'This device is not authorized for monitoring.',
                    'data' => ['device_id' => (int) $existing['id'], 'status' => $device['status'] ?? 'pending'],
                ];
            }

            return [
                'success' => true,
                'message' => 'Device registered.',
                'data' => [
                    'device' => $this->publicDevice($device),
                    'access_token' => $plainToken,
                    'expires_at' => $expires,
                ],
            ];
        }

        $status = $autoApprove ? 'approved' : 'pending';
        $id = $this->db->insert('monitoring_devices', [
            'uuid' => $this->uuid(),
            'employee_id' => $employeeId,
            'company_id' => (int) $employee['company_id'],
            'device_uid' => $deviceUid,
            'hostname' => $payload['hostname'] ?? null,
            'os_name' => $payload['os_name'] ?? null,
            'os_version' => $payload['os_version'] ?? null,
            'agent_version' => $payload['agent_version'] ?? null,
            'status' => $status,
            'access_token_hash' => $status === 'approved' ? $tokenHash : null,
            'access_token_expires_at' => $status === 'approved' ? $expires : null,
            'last_seen_at' => date('Y-m-d H:i:s'),
            'approved_at' => $status === 'approved' ? date('Y-m-d H:i:s') : null,
        ]);

        $this->audit->log('create', 'monitoring_devices', $id, null, [
            'employee_id' => $employeeId,
            'device_uid' => $deviceUid,
            'status' => $status,
        ]);

        $device = $this->find($id);
        if ($status !== 'approved') {
            return [
                'success' => false,
                'code' => 'DEVICE_NOT_APPROVED',
                'message' => 'This device is pending administrator approval.',
                'data' => ['device' => $this->publicDevice($device)],
            ];
        }

        return [
            'success' => true,
            'message' => 'Device registered.',
            'data' => [
                'device' => $this->publicDevice($device),
                'access_token' => $plainToken,
                'expires_at' => $expires,
            ],
        ];
    }

    public function approve(int $deviceId, ?int $userId = null): array
    {
        $device = $this->find($deviceId);
        if (!$device) {
            return ['success' => false, 'message' => 'Device not found.'];
        }
        $this->db->update('monitoring_devices', [
            'status' => 'approved',
            'approved_at' => date('Y-m-d H:i:s'),
            'approved_by' => $userId,
        ], 'id = :id', ['id' => $deviceId]);
        $this->audit->log('update', 'monitoring_devices', $deviceId, $device, ['status' => 'approved'], $userId);
        return ['success' => true, 'message' => 'Device approved.'];
    }

    public function revoke(int $deviceId, ?int $userId = null): array
    {
        $device = $this->find($deviceId);
        if (!$device) {
            return ['success' => false, 'message' => 'Device not found.'];
        }
        $this->db->update('monitoring_devices', [
            'status' => 'revoked',
            'revoked_at' => date('Y-m-d H:i:s'),
            'revoked_by' => $userId,
            'access_token_hash' => null,
            'access_token_expires_at' => null,
        ], 'id = :id', ['id' => $deviceId]);
        $this->audit->log('update', 'monitoring_devices', $deviceId, $device, ['status' => 'revoked'], $userId);
        return ['success' => true, 'message' => 'Device revoked.'];
    }

    public function touch(int $deviceId): void
    {
        $this->db->update('monitoring_devices', [
            'last_seen_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $deviceId]);
    }

    public function listForEmployee(int $employeeId): array
    {
        return $this->db->fetchAll(
            'SELECT id, uuid, device_uid, hostname, os_name, os_version, agent_version, status, last_seen_at, created_at
             FROM monitoring_devices WHERE employee_id = :eid ORDER BY last_seen_at DESC, id DESC',
            ['eid' => $employeeId]
        );
    }

    /**
     * @return array{data: list<array>, total: int, page: int, per_page: int}
     */
    public function search(array $filters, string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 20): array
    {
        $where = ["e.deleted_at IS NULL", "({$scopeSql})"];
        $params = $scopeParams;
        if (!empty($filters['status'])) {
            $where[] = 'd.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['q'])) {
            // PDO forbids reusing the same named placeholder across OR clauses.
            $like = '%' . $filters['q'] . '%';
            $where[] = '(d.hostname LIKE :q1 OR d.device_uid LIKE :q2 OR e.first_name LIKE :q3 OR e.last_name LIKE :q4 OR e.employee_code LIKE :q5)';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
            $params['q5'] = $like;
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_devices d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE {$sqlWhere}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT d.*, e.first_name, e.last_name, e.employee_code
             FROM monitoring_devices d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE {$sqlWhere}
             ORDER BY d.last_seen_at DESC, d.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function isApproved(?array $device): bool
    {
        return $device && ($device['status'] ?? '') === 'approved';
    }

    private function publicDevice(?array $device): ?array
    {
        if (!$device) {
            return null;
        }
        return [
            'id' => (int) $device['id'],
            'uuid' => $device['uuid'],
            'device_uid' => $device['device_uid'],
            'hostname' => $device['hostname'],
            'os_name' => $device['os_name'],
            'os_version' => $device['os_version'],
            'agent_version' => $device['agent_version'],
            'status' => $device['status'],
        ];
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
