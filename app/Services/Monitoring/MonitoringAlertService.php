<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;
use App\Services\NotificationService;

/**
 * Action-required alerts only for employees (per revised transparency).
 */
class MonitoringAlertService
{
    private Database $db;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
    }

    public function create(
        int $companyId,
        string $alertType,
        string $title,
        string $message,
        string $severity = 'warning',
        ?int $employeeId = null,
        ?int $deviceId = null,
        ?int $sessionId = null,
        bool $requiresEmployeeAction = false
    ): int {
        $id = $this->db->insert('monitoring_alerts', [
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'device_id' => $deviceId,
            'session_id' => $sessionId,
            'alert_type' => $alertType,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'requires_employee_action' => $requiresEmployeeAction ? 1 : 0,
            'is_resolved' => 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit->log('other', 'monitoring_alerts', $id, null, [
            'alert_type' => $alertType,
            'employee_id' => $employeeId,
        ]);
        return $id;
    }

    public function createActionRequired(
        int $companyId,
        ?int $employeeId,
        ?int $deviceId,
        ?int $sessionId,
        string $alertType,
        string $title,
        string $message,
        ?int $userId = null
    ): int {
        $id = $this->create(
            $companyId,
            $alertType,
            $title,
            $message,
            'critical',
            $employeeId,
            $deviceId,
            $sessionId,
            true
        );

        if ($userId) {
            (new NotificationService())->notify(
                $userId,
                $title,
                $message,
                '/employee/monitoring/acknowledge',
                'monitoring_action_required'
            );
        }

        return $id;
    }

    public function resolve(int $alertId, ?int $userId = null): array
    {
        $this->db->update('monitoring_alerts', [
            'is_resolved' => 1,
            'resolved_at' => date('Y-m-d H:i:s'),
            'resolved_by' => $userId,
        ], 'id = :id', ['id' => $alertId]);
        return ['success' => true, 'message' => 'Alert resolved.'];
    }

    /**
     * @return array{data: list<array>, total: int, page: int, per_page: int}
     */
    public function search(array $filters, string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 20): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        $join = 'LEFT JOIN employees e ON e.id = a.employee_id';

        if (isset($filters['is_resolved']) && $filters['is_resolved'] !== '') {
            $where[] = 'a.is_resolved = :is_resolved';
            $params['is_resolved'] = (int) $filters['is_resolved'];
        }
        if (!empty($filters['alert_type'])) {
            $where[] = 'a.alert_type = :alert_type';
            $params['alert_type'] = $filters['alert_type'];
        }

        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_alerts a {$join} WHERE {$sqlWhere}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT a.*, e.first_name, e.last_name, e.employee_code
             FROM monitoring_alerts a
             {$join}
             WHERE {$sqlWhere}
             ORDER BY a.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }
}
