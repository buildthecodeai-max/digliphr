<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;

class MonitoringActivityService
{
    private Database $db;
    private MonitoringPolicyService $policies;
    private MonitoringSiteActivityService $siteActivity;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->policies = new MonitoringPolicyService();
        $this->siteActivity = new MonitoringSiteActivityService();
    }

    /**
     * @param list<array<string, mixed>> $segments
     * @return array{success: bool, message: string, data?: array}
     */
    public function ingestBatch(array $session, array $segments): array
    {
        if (!in_array($session['status'], ['active', 'paused', 'offline', 'stopping'], true)) {
            return ['success' => false, 'message' => 'Session is not accepting activity.'];
        }

        $policy = $this->policies->find((int) $session['policy_id']);
        $maskedApps = $this->decodeList($policy['masked_apps'] ?? null);
        $excludedApps = $this->decodeList($policy['excluded_apps'] ?? null);

        $inserted = 0;
        $skipped = 0;
        $siteControls = [];

        foreach ($segments as $segment) {
            $app = trim((string) ($segment['application_name'] ?? ''));
            if ($app !== '' && $this->matchesList($app, $excludedApps)) {
                $skipped++;
                continue;
            }

            $title = (string) ($segment['window_title'] ?? '');
            $masked = 0;
            if ((int) ($policy['mask_window_titles'] ?? 1) === 1 && $this->matchesList($app, $maskedApps)) {
                $title = 'Hidden by monitoring policy';
                $masked = 1;
            }

            $clientId = $segment['client_segment_id'] ?? null;
            if ($clientId) {
                $exists = $this->db->fetch(
                    'SELECT id FROM monitoring_activity_segments WHERE session_id = :sid AND client_segment_id = :cid LIMIT 1',
                    ['sid' => $session['id'], 'cid' => $clientId]
                );
                if ($exists) {
                    $skipped++;
                    continue;
                }
            }

            $started = $segment['started_at'] ?? date('Y-m-d H:i:s');
            $ended = $segment['ended_at'] ?? null;
            $duration = (int) ($segment['duration_seconds'] ?? 0);
            if ($duration <= 0 && $ended) {
                $duration = max(0, strtotime((string) $ended) - strtotime((string) $started));
            }

            $status = $segment['activity_status'] ?? 'active';
            if (!in_array($status, ['active', 'idle', 'locked', 'disconnected'], true)) {
                $status = 'active';
            }

            $this->db->insert('monitoring_activity_segments', [
                'session_id' => (int) $session['id'],
                'employee_id' => (int) $session['employee_id'],
                'device_id' => $session['device_id'] ?? null,
                'company_id' => (int) $session['company_id'],
                'application_name' => $app !== '' ? $app : null,
                'process_name' => $segment['process_name'] ?? null,
                'window_title' => $title !== '' ? substr($title, 0, 500) : null,
                'window_title_masked' => $masked,
                'started_at' => $started,
                'ended_at' => $ended,
                'duration_seconds' => $duration,
                'activity_status' => $status,
                'client_segment_id' => $clientId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $siteResult = $this->siteActivity->ingest($session, $segment);
            if ($siteResult !== null) {
                $siteControls[] = $siteResult;
            }
            $inserted++;
        }

        return [
            'success' => true,
            'message' => 'Activity segments accepted.',
            'data' => ['inserted' => $inserted, 'skipped' => $skipped, 'site_controls' => $siteControls],
        ];
    }

    /**
     * @return array{data: list<array>, total: int, page: int, per_page: int}
     */
    public function search(array $filters, string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 50): array
    {
        $where = ["({$scopeSql})"];
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
            $where[] = 's.started_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.started_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['application_name'])) {
            $where[] = 's.application_name LIKE :app';
            $params['app'] = '%' . $filters['application_name'] . '%';
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_activity_segments s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT s.*, e.first_name, e.last_name, e.employee_code
             FROM monitoring_activity_segments s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}
             ORDER BY s.started_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function applicationSummary(array $filters, string $scopeSql, array $scopeParams): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        if (!empty($filters['date_from'])) {
            $where[] = 's.started_at >= :date_from';
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 's.started_at <= :date_to';
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['employee_id'])) {
            $where[] = 's.employee_id = :employee_id';
            $params['employee_id'] = (int) $filters['employee_id'];
        }
        $sqlWhere = implode(' AND ', $where);

        return $this->db->fetchAll(
            "SELECT COALESCE(s.application_name, '(unknown)') AS application_name,
                    SUM(s.duration_seconds) AS total_seconds,
                    SUM(CASE WHEN s.activity_status = 'idle' THEN s.duration_seconds ELSE 0 END) AS idle_seconds,
                    COUNT(*) AS segment_count
             FROM monitoring_activity_segments s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}
             GROUP BY COALESCE(s.application_name, '(unknown)')
             ORDER BY total_seconds DESC
             LIMIT 100",
            $params
        );
    }

    public function todaySummaryForEmployee(int $employeeId): array
    {
        $row = $this->db->fetch(
            'SELECT
                SUM(CASE WHEN activity_status = "active" THEN duration_seconds ELSE 0 END) AS active_seconds,
                SUM(CASE WHEN activity_status = "idle" THEN duration_seconds ELSE 0 END) AS idle_seconds,
                SUM(duration_seconds) AS total_seconds,
                COUNT(*) AS segment_count
             FROM monitoring_activity_segments
             WHERE employee_id = :eid AND DATE(started_at) = CURDATE()',
            ['eid' => $employeeId]
        );

        return [
            'active_seconds' => (int) ($row['active_seconds'] ?? 0),
            'idle_seconds' => (int) ($row['idle_seconds'] ?? 0),
            'total_seconds' => (int) ($row['total_seconds'] ?? 0),
            'segment_count' => (int) ($row['segment_count'] ?? 0),
        ];
    }

    /** @return list<string> */
    private function decodeList(mixed $json): array
    {
        if (is_array($json)) {
            return array_map('strval', $json);
        }
        if (!is_string($json) || $json === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_map('strval', $decoded) : [];
    }

    private function matchesList(string $app, array $list): bool
    {
        $appLower = strtolower($app);
        foreach ($list as $item) {
            if ($item !== '' && str_contains($appLower, strtolower($item))) {
                return true;
            }
        }
        return false;
    }
}
