<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;

class MonitoringReportService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function overview(string $scopeSql, array $scopeParams): array
    {
        $active = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_sessions s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE s.status IN ('active','paused','offline') AND ({$scopeSql})",
            $scopeParams
        );

        $screenshotsToday = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_screenshots s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE s.deleted_at IS NULL AND DATE(s.captured_at) = CURDATE() AND ({$scopeSql})",
            $scopeParams
        );

        $pendingDevices = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_devices d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.status = 'pending' AND ({$scopeSql})",
            $scopeParams
        );

        $openAlerts = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_alerts a
             LEFT JOIN employees e ON e.id = a.employee_id
             WHERE a.is_resolved = 0 AND (e.id IS NULL OR ({$scopeSql}))",
            $scopeParams
        );

        $storage = $this->db->fetchAll(
            'SELECT company_id, used_bytes, screenshot_count, quota_bytes, last_calculated_at
             FROM monitoring_storage_usage ORDER BY used_bytes DESC LIMIT 10'
        );

        return [
            'active_sessions' => $active,
            'screenshots_today' => $screenshotsToday,
            'pending_devices' => $pendingDevices,
            'open_alerts' => $openAlerts,
            'storage' => $storage,
        ];
    }

    public function liveEmployees(string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 30): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_sessions s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE s.status IN ('active','paused','offline','stopping') AND ({$scopeSql})",
            $scopeParams
        );
        $rows = $this->db->fetchAll(
            "SELECT s.*, e.first_name, e.last_name, e.employee_code, e.department_id,
                    d.hostname, d.agent_version, d.last_seen_at AS device_last_seen
             FROM monitoring_sessions s
             INNER JOIN employees e ON e.id = s.employee_id
             LEFT JOIN monitoring_devices d ON d.id = s.device_id
             WHERE s.status IN ('active','paused','offline','stopping') AND ({$scopeSql})
             ORDER BY s.last_heartbeat_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $scopeParams
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function dailyReport(array $filters, string $scopeSql, array $scopeParams): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        $dateFrom = $filters['date_from'] ?? date('Y-m-d', strtotime('-7 days'));
        $dateTo = $filters['date_to'] ?? date('Y-m-d');
        $where[] = 'DATE(s.started_at) BETWEEN :date_from AND :date_to';
        $params['date_from'] = $dateFrom;
        $params['date_to'] = $dateTo;

        $sqlWhere = implode(' AND ', $where);

        // PDO forbids reusing the same named placeholder; subquery needs unique names.
        $params['ss_date_from'] = $dateFrom;
        $params['ss_date_to'] = $dateTo;

        return $this->db->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                    COUNT(DISTINCT s.id) AS session_count,
                    SUM(TIMESTAMPDIFF(MINUTE, s.started_at, COALESCE(s.ended_at, NOW()))) AS monitored_minutes,
                    (SELECT COUNT(*) FROM monitoring_screenshots ms
                     WHERE ms.employee_id = e.id AND ms.deleted_at IS NULL
                       AND DATE(ms.captured_at) BETWEEN :ss_date_from AND :ss_date_to) AS screenshot_count
             FROM monitoring_sessions s
             INNER JOIN employees e ON e.id = s.employee_id
             WHERE {$sqlWhere}
             GROUP BY e.id, e.employee_code, e.first_name, e.last_name
             ORDER BY monitored_minutes DESC
             LIMIT 200",
            $params
        );
    }
}
