<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;

/**
 * Resolves and enforces the companies available to the current user.
 * Super administrators are global; every other role fails closed when no
 * employee or user-role company assignment exists.
 */
final class TenantContext
{
    private Database $db;
    private ?array $companyIdsCache = null;

    public function __construct(private readonly AuthService $auth)
    {
        $this->db = Database::getInstance();
    }

    public function isGlobal(): bool
    {
        $user = $this->auth->user();
        return $user !== null
            && ($this->auth->hasRole('super_admin') || !empty($user['is_super_admin']));
    }

    /** @return list<int> */
    public function companyIds(): array
    {
        if ($this->companyIdsCache !== null) {
            return $this->companyIdsCache;
        }

        if ($this->isGlobal()) {
            return $this->companyIdsCache = [];
        }

        $userId = $this->auth->id();
        if (!$userId) {
            return $this->companyIdsCache = [];
        }

        $rows = $this->db->fetchAll(
            'SELECT company_id FROM user_roles
             WHERE user_id = :uid AND company_id IS NOT NULL
             UNION
             SELECT company_id FROM employees
             WHERE user_id = :uid2 AND company_id IS NOT NULL AND deleted_at IS NULL',
            ['uid' => $userId, 'uid2' => $userId]
        );

        $ids = array_values(array_unique(array_filter(array_map(
            static fn (array $row): int => (int) ($row['company_id'] ?? 0),
            $rows
        ))));
        sort($ids);
        return $this->companyIdsCache = $ids;
    }

    public function canAccessCompany(int $companyId): bool
    {
        return $companyId > 0 && ($this->isGlobal() || in_array($companyId, $this->companyIds(), true));
    }

    public function assertCompany(int $companyId): int
    {
        if (!$this->canAccessCompany($companyId)) {
            throw new HttpException('Company not found or access denied.', 403);
        }
        return $companyId;
    }

    public function resolveCompanyId(mixed $requested = null): ?int
    {
        $requestedId = ($requested === null || $requested === '') ? null : (int) $requested;
        if ($requestedId !== null) {
            return $this->assertCompany($requestedId);
        }
        if ($this->isGlobal()) {
            return null;
        }

        $ids = $this->companyIds();
        if ($ids === []) {
            throw new HttpException('No company is assigned to this account.', 403);
        }
        return $ids[0];
    }

    /** @return array{sql:string,params:array<string,int>} */
    public function sql(string $column, string $prefix = 'tenant'): array
    {
        if ($this->isGlobal()) {
            return ['sql' => '1 = 1', 'params' => []];
        }

        $ids = $this->companyIds();
        if ($ids === []) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        $params = [];
        $placeholders = [];
        foreach ($ids as $index => $id) {
            $key = $prefix . '_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $id;
        }
        return ['sql' => $column . ' IN (' . implode(', ', $placeholders) . ')', 'params' => $params];
    }

    public function employee(int $employeeId): array
    {
        $row = $this->db->fetch(
            'SELECT id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => $employeeId]
        );
        if (!$row || !$this->canAccessCompany((int) $row['company_id'])) {
            throw new HttpException('Employee not found.', 404);
        }
        return $row;
    }

    public function record(string $table, int $id, string $companyColumn = 'company_id'): array
    {
        if (!preg_match('/^[a-z_][a-z0-9_]*$/i', $table)
            || !preg_match('/^[a-z_][a-z0-9_]*$/i', $companyColumn)) {
            throw new \InvalidArgumentException('Invalid tenant record identifier.');
        }
        $row = $this->db->fetch(
            "SELECT * FROM `{$table}` WHERE id = :id LIMIT 1",
            ['id' => $id]
        );
        if (!$row || !$this->canAccessCompany((int) ($row[$companyColumn] ?? 0))) {
            throw new HttpException('Record not found.', 404);
        }
        return $row;
    }

    public function companies(bool $activeOnly = false): array
    {
        $scope = $this->sql('c.id', 'tenant_company');
        $active = $activeOnly ? ' AND c.is_active = 1' : '';
        return $this->db->fetchAll(
            "SELECT c.* FROM companies c
             WHERE c.deleted_at IS NULL AND ({$scope['sql']}){$active}
             ORDER BY c.name",
            $scope['params']
        );
    }
}
