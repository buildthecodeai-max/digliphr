<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\TenantContext;

/**
 * Manager / company scope helpers for monitoring queries.
 */
class MonitoringAuthorizationService
{
    private Database $db;
    private AuthService $auth;
    private TenantContext $tenant;

    public function __construct(?AuthService $auth = null)
    {
        $this->db = Database::getInstance();
        $this->auth = $auth ?? new AuthService();
        $this->tenant = new TenantContext($this->auth);
    }

    public function can(string $permission): bool
    {
        return $this->auth->can($permission);
    }

    public function user(): ?array
    {
        return $this->auth->user();
    }

    public function employee(): ?array
    {
        return $this->auth->employee();
    }

    /**
     * SQL fragment + params restricting employees the current user may see.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function employeeScopeSql(string $employeeAlias = 'e'): array
    {
        $user = $this->user();
        if (!$user) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        if ($this->auth->hasRole('super_admin')) {
            return ['sql' => '1 = 1', 'params' => []];
        }

        $tenantScope = $this->tenant->sql("{$employeeAlias}.company_id", 'monitoring_company');
        $params = $tenantScope['params'];
        $clauses = [$tenantScope['sql']];

        if ($this->auth->hasRole('department_manager') && !$this->can('monitoring.view_all_activity')) {
            $me = $this->employee();
            if ($me && !empty($me['department_id'])) {
                $clauses[] = "({$employeeAlias}.department_id = :scope_dept_id OR {$employeeAlias}.reporting_manager_id = :scope_mgr_emp_id)";
                $params['scope_dept_id'] = (int) $me['department_id'];
                $params['scope_mgr_emp_id'] = (int) $me['id'];
            } elseif ($me) {
                $clauses[] = "{$employeeAlias}.reporting_manager_id = :scope_mgr_emp_id";
                $params['scope_mgr_emp_id'] = (int) $me['id'];
            } else {
                return ['sql' => '1 = 0', 'params' => []];
            }
        }

        return ['sql' => implode(' AND ', $clauses), 'params' => $params];
    }

    public function canAccessEmployee(int $employeeId): bool
    {
        if ($this->auth->hasRole('super_admin')) {
            return true;
        }

        $own = $this->employee();
        if ($own && (int) $own['id'] === $employeeId && $this->can('monitoring.view_own_activity')) {
            return true;
        }

        $scope = $this->employeeScopeSql('e');
        $row = $this->db->fetch(
            "SELECT e.id FROM employees e WHERE e.id = :eid AND e.deleted_at IS NULL AND ({$scope['sql']}) LIMIT 1",
            array_merge(['eid' => $employeeId], $scope['params'])
        );

        return $row !== null;
    }

    public function canAccessScreenshot(array $screenshot): bool
    {
        $employeeId = (int) ($screenshot['employee_id'] ?? 0);
        $own = $this->employee();

        if ($own && (int) $own['id'] === $employeeId) {
            return $this->can('monitoring.view_own_activity');
        }

        return $this->can('monitoring.view_screenshots') && $this->canAccessEmployee($employeeId);
    }

    public function audit(string $action, string $module, ?int $recordId = null, mixed $previous = null, mixed $new = null): void
    {
        (new AuditService())->log($action, $module, $recordId, $previous, $new);
    }
}
