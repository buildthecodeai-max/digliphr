<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringPolicyService
{
    private Database $db;
    private AuditService $audit;

    public const ALLOWED_INTERVALS = [5, 10, 15, 20, 30];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_policies WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
    }

    public function systemDefault(): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_policies
             WHERE is_system_default = 1 AND is_active = 1 AND deleted_at IS NULL
             ORDER BY id ASC LIMIT 1'
        );
    }

    /**
     * Resolve effective policy for an employee (most specific assignment wins).
     */
    public function resolveForEmployee(int $employeeId): ?array
    {
        $employee = $this->db->fetch(
            'SELECT id, company_id, branch_id, department_id FROM employees WHERE id = :id AND deleted_at IS NULL',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return null;
        }

        $companyId = (int) $employee['company_id'];

        // Employee-specific
        $row = $this->db->fetch(
            'SELECT p.* FROM monitoring_policy_assignments a
             INNER JOIN monitoring_policies p ON p.id = a.policy_id AND p.deleted_at IS NULL AND p.is_active = 1
             WHERE a.is_active = 1 AND a.employee_id = :eid
               AND (a.starts_at IS NULL OR a.starts_at <= NOW())
               AND (a.ends_at IS NULL OR a.ends_at >= NOW())
             ORDER BY a.id DESC LIMIT 1',
            ['eid' => $employeeId]
        );
        if ($row) {
            return $row;
        }

        // Department
        if (!empty($employee['department_id'])) {
            $row = $this->db->fetch(
                'SELECT p.* FROM monitoring_policy_assignments a
                 INNER JOIN monitoring_policies p ON p.id = a.policy_id AND p.deleted_at IS NULL AND p.is_active = 1
                 WHERE a.is_active = 1 AND a.company_id = :cid AND a.department_id = :did
                   AND a.employee_id IS NULL
                   AND (a.starts_at IS NULL OR a.starts_at <= NOW())
                   AND (a.ends_at IS NULL OR a.ends_at >= NOW())
                 ORDER BY a.id DESC LIMIT 1',
                ['cid' => $companyId, 'did' => $employee['department_id']]
            );
            if ($row) {
                return $row;
            }
        }

        // Branch
        if (!empty($employee['branch_id'])) {
            $row = $this->db->fetch(
                'SELECT p.* FROM monitoring_policy_assignments a
                 INNER JOIN monitoring_policies p ON p.id = a.policy_id AND p.deleted_at IS NULL AND p.is_active = 1
                 WHERE a.is_active = 1 AND a.company_id = :cid AND a.branch_id = :bid
                   AND a.department_id IS NULL AND a.employee_id IS NULL
                   AND (a.starts_at IS NULL OR a.starts_at <= NOW())
                   AND (a.ends_at IS NULL OR a.ends_at >= NOW())
                 ORDER BY a.id DESC LIMIT 1',
                ['cid' => $companyId, 'bid' => $employee['branch_id']]
            );
            if ($row) {
                return $row;
            }
        }

        // Company-wide
        $row = $this->db->fetch(
            'SELECT p.* FROM monitoring_policy_assignments a
             INNER JOIN monitoring_policies p ON p.id = a.policy_id AND p.deleted_at IS NULL AND p.is_active = 1
             WHERE a.is_active = 1 AND a.company_id = :cid
               AND a.branch_id IS NULL AND a.department_id IS NULL AND a.employee_id IS NULL
               AND (a.starts_at IS NULL OR a.starts_at <= NOW())
               AND (a.ends_at IS NULL OR a.ends_at >= NOW())
             ORDER BY a.id DESC LIMIT 1',
            ['cid' => $companyId]
        );
        if ($row) {
            return $row;
        }

        // Company-owned default policy
        $row = $this->db->fetch(
            'SELECT * FROM monitoring_policies
             WHERE company_id = :cid AND is_active = 1 AND deleted_at IS NULL
             ORDER BY is_system_default DESC, id ASC LIMIT 1',
            ['cid' => $companyId]
        );
        if ($row) {
            return $row;
        }

        return $this->systemDefault();
    }

    public function normalizeInterval(int $minutes, ?array $policy = null): int
    {
        $min = (int) ($policy['min_interval_minutes'] ?? config('app.monitoring.min_interval_minutes', 5));
        $allowed = config('app.monitoring.allowed_intervals', self::ALLOWED_INTERVALS);
        if (!in_array($minutes, $allowed, true)) {
            $minutes = (int) config('app.monitoring.default_interval_minutes', 10);
        }
        return max($min, $minutes);
    }

    public function hasAcknowledged(int $employeeId, array $policy): bool
    {
        if (!(int) ($policy['require_acknowledgement'] ?? 1)) {
            return true;
        }

        $row = $this->db->fetch(
            'SELECT id FROM monitoring_policy_acknowledgements
             WHERE employee_id = :eid AND policy_id = :pid AND policy_version = :ver AND accepted = 1
             LIMIT 1',
            [
                'eid' => $employeeId,
                'pid' => (int) $policy['id'],
                'ver' => (int) $policy['version'],
            ]
        );

        return $row !== null;
    }

    /**
     * @return array{success: bool, message: string, data?: array, code?: string}
     */
    public function acknowledge(int $employeeId, int $policyId, ?int $deviceId = null, ?string $ip = null, ?string $ua = null): array
    {
        $policy = $this->find($policyId);
        if (!$policy) {
            return ['success' => false, 'message' => 'Policy not found.'];
        }

        $existing = $this->db->fetch(
            'SELECT id FROM monitoring_policy_acknowledgements
             WHERE employee_id = :eid AND policy_id = :pid AND policy_version = :ver LIMIT 1',
            ['eid' => $employeeId, 'pid' => $policyId, 'ver' => (int) $policy['version']]
        );
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Policy already acknowledged.',
                'data' => ['acknowledgement_id' => (int) $existing['id']],
            ];
        }

        $id = $this->db->insert('monitoring_policy_acknowledgements', [
            'employee_id' => $employeeId,
            'policy_id' => $policyId,
            'policy_version' => (int) $policy['version'],
            'device_id' => $deviceId,
            'ip_address' => $ip,
            'user_agent' => $ua ? substr($ua, 0, 500) : null,
            'accepted' => 1,
            'acknowledged_at' => date('Y-m-d H:i:s'),
        ]);

        $this->audit->log('other', 'monitoring_policy_acknowledgements', $id, null, [
            'employee_id' => $employeeId,
            'policy_id' => $policyId,
            'policy_version' => (int) $policy['version'],
        ]);

        return [
            'success' => true,
            'message' => 'Monitoring policy acknowledged.',
            'data' => ['acknowledgement_id' => $id],
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{success: bool, message: string, data?: array}
     */
    public function create(array $data, ?int $userId = null): array
    {
        $interval = $this->normalizeInterval((int) ($data['screenshot_interval_minutes'] ?? 10));
        $mode = $data['mode'] ?? 'activity_screenshots';
        if ($mode === 'exceptional_recording' && empty($data['recording_enabled'])) {
            $data['recording_enabled'] = 0;
            $mode = 'activity_screenshots';
        }

        $id = $this->db->insert('monitoring_policies', [
            'uuid' => $this->uuid(),
            'company_id' => $data['company_id'] ?? null,
            'name' => $data['name'],
            'slug' => $data['slug'] ?? $this->slugify((string) $data['name']),
            'version' => 1,
            'mode' => $mode,
            'screenshot_enabled' => (int) ($data['screenshot_enabled'] ?? 1),
            'screenshot_interval_minutes' => $interval,
            'min_interval_minutes' => (int) ($data['min_interval_minutes'] ?? 5),
            'recording_enabled' => 0,
            'track_applications' => (int) ($data['track_applications'] ?? 1),
            'track_window_titles' => (int) ($data['track_window_titles'] ?? 1),
            'mask_window_titles' => (int) ($data['mask_window_titles'] ?? 1),
            'allow_employee_view_screenshots' => (int) ($data['allow_employee_view_screenshots'] ?? 0),
            'require_acknowledgement' => (int) ($data['require_acknowledgement'] ?? 1),
            'notice_title' => $data['notice_title'] ?? 'Work Activity Monitoring',
            'notice_text' => $data['notice_text'] ?? null,
            'activity_retention_days' => (int) ($data['activity_retention_days'] ?? 90),
            'screenshot_retention_days' => (int) ($data['screenshot_retention_days'] ?? 30),
            'excluded_apps' => isset($data['excluded_apps']) ? json_encode($data['excluded_apps']) : null,
            'masked_apps' => isset($data['masked_apps']) ? json_encode($data['masked_apps']) : null,
            'is_system_default' => 0,
            'is_active' => (int) ($data['is_active'] ?? 1),
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->audit->log('create', 'monitoring_policies', $id, null, $data, $userId);

        return ['success' => true, 'message' => 'Policy created.', 'data' => ['id' => $id]];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(int $id, array $data, ?int $userId = null, bool $bumpVersion = false): array
    {
        $policy = $this->find($id);
        if (!$policy) {
            return ['success' => false, 'message' => 'Policy not found.'];
        }

        $update = [];
        foreach ([
            'name', 'notice_title', 'notice_text', 'mode',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = $data[$field];
            }
        }
        foreach ([
            'screenshot_enabled', 'track_applications', 'track_window_titles', 'mask_window_titles',
            'allow_employee_view_screenshots', 'require_acknowledgement', 'is_active',
            'activity_retention_days', 'screenshot_retention_days', 'min_interval_minutes',
            'apply_changes_immediately',
        ] as $field) {
            if (array_key_exists($field, $data)) {
                $update[$field] = (int) $data[$field];
            }
        }
        if (isset($data['screenshot_interval_minutes'])) {
            $update['screenshot_interval_minutes'] = $this->normalizeInterval(
                (int) $data['screenshot_interval_minutes'],
                array_merge($policy, $update)
            );
        }
        // Never silently enable recording via ordinary update
        $update['recording_enabled'] = 0;
        if (($update['mode'] ?? $policy['mode']) === 'exceptional_recording') {
            $update['mode'] = 'activity_screenshots';
        }

        if (isset($data['excluded_apps'])) {
            $update['excluded_apps'] = is_string($data['excluded_apps'])
                ? $data['excluded_apps']
                : json_encode($data['excluded_apps']);
        }
        if (isset($data['masked_apps'])) {
            $update['masked_apps'] = is_string($data['masked_apps'])
                ? $data['masked_apps']
                : json_encode($data['masked_apps']);
        }

        $material = $bumpVersion || $this->isMaterialChange($policy, $update);
        if ($material) {
            $update['version'] = (int) $policy['version'] + 1;
        }
        $update['updated_by'] = $userId;

        $this->db->update('monitoring_policies', $update, 'id = :id', ['id' => $id]);
        $this->audit->log('update', 'monitoring_policies', $id, $policy, $update, $userId);

        return [
            'success' => true,
            'message' => $material
                ? 'Policy updated. Employees must re-acknowledge the new version.'
                : 'Policy updated.',
            'data' => ['id' => $id, 'version' => $update['version'] ?? $policy['version']],
        ];
    }

    public function assign(array $data, ?int $userId = null): array
    {
        $policyId = (int) ($data['policy_id'] ?? 0);
        $policy = $this->find($policyId);
        if (!$policy) {
            return ['success' => false, 'message' => 'Policy not found.'];
        }

        $id = $this->db->insert('monitoring_policy_assignments', [
            'policy_id' => $policyId,
            'company_id' => (int) $data['company_id'],
            'branch_id' => $data['branch_id'] ?? null,
            'department_id' => $data['department_id'] ?? null,
            'employee_id' => $data['employee_id'] ?? null,
            'assigned_by' => $userId,
            'assigned_at' => date('Y-m-d H:i:s'),
            'is_active' => 1,
        ]);

        $this->audit->log('other', 'monitoring_policy_assignments', $id, null, $data, $userId);

        return ['success' => true, 'message' => 'Policy assigned.', 'data' => ['id' => $id]];
    }

    /**
     * @return array{data: list<array>, total: int, page: int, per_page: int}
     */
    public function listPolicies(?int $companyId, int $page = 1, int $perPage = 20): array
    {
        $where = 'deleted_at IS NULL';
        $params = [];
        if ($companyId) {
            $where .= ' AND (company_id = :cid OR company_id IS NULL)';
            $params['cid'] = $companyId;
        }
        $total = (int) $this->db->fetchColumn("SELECT COUNT(*) FROM monitoring_policies WHERE {$where}", $params);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT * FROM monitoring_policies WHERE {$where} ORDER BY is_system_default DESC, name ASC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function acknowledgementsForEmployee(int $employeeId): array
    {
        return $this->db->fetchAll(
            'SELECT a.*, p.name AS policy_name, p.mode
             FROM monitoring_policy_acknowledgements a
             INNER JOIN monitoring_policies p ON p.id = a.policy_id
             WHERE a.employee_id = :eid
             ORDER BY a.acknowledged_at DESC',
            ['eid' => $employeeId]
        );
    }

    private function isMaterialChange(array $before, array $after): bool
    {
        foreach (['mode', 'screenshot_enabled', 'screenshot_interval_minutes', 'recording_enabled', 'notice_text', 'require_acknowledgement'] as $key) {
            if (array_key_exists($key, $after) && (string) $after[$key] !== (string) ($before[$key] ?? '')) {
                return true;
            }
        }
        return false;
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $name) ?? '', '-'));
        return $slug !== '' ? $slug : 'policy-' . bin2hex(random_bytes(3));
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
