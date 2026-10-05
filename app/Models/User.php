<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class User extends Model
{
    protected string $table = 'users';
    protected array $fillable = [
        'name', 'email', 'password', 'phone', 'avatar', 'is_active',
        'is_super_admin', 'two_factor_secret', 'two_factor_enabled',
        'email_verified_at', 'last_login_at', 'last_login_ip', 'password_changed_at',
        'force_password_reset', 'locale', 'timezone', 'deleted_at',
    ];
    protected array $hidden = ['password', 'two_factor_secret'];
    protected bool $softDeletes = true;

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL LIMIT 1',
            ['email' => $email]
        );
    }

    public function findWithRoles(int $id): ?array
    {
        $user = $this->find($id);
        if (!$user) {
            return null;
        }

        $roles = $this->db->fetchAll(
            'SELECT r.id, r.name, r.slug, ur.company_id
             FROM roles r
             INNER JOIN user_roles ur ON ur.role_id = r.id
             WHERE ur.user_id = :id',
            ['id' => $id]
        );

        $user['roles'] = array_column($roles, 'slug');
        $user['role_names'] = array_column($roles, 'name');
        $user['primary_role'] = $user['roles'][0] ?? 'employee';
        $companyIds = array_values(array_unique(array_filter(array_map(
            static fn (array $role): int => (int) ($role['company_id'] ?? 0),
            $roles
        ))));
        $employeeCompany = $this->db->fetchColumn(
            'SELECT company_id FROM employees WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1',
            ['uid' => $id]
        );
        if ($employeeCompany) {
            $companyIds[] = (int) $employeeCompany;
        }
        $companyIds = array_values(array_unique($companyIds));
        sort($companyIds);
        $user['company_ids'] = $companyIds;
        $user['company_id'] = $companyIds[0] ?? null;

        return $user;
    }

    public function assignRole(int $userId, int $roleId, ?int $companyId = null, ?int $assignedBy = null): void
    {
        $exists = $this->db->fetch(
            'SELECT id FROM user_roles
             WHERE user_id = :uid AND role_id = :rid AND company_id <=> :company_id',
            ['uid' => $userId, 'rid' => $roleId, 'company_id' => $companyId]
        );

        if (!$exists) {
            $this->db->insert('user_roles', [
                'user_id' => $userId,
                'role_id' => $roleId,
                'company_id' => $companyId,
                'assigned_by' => $assignedBy,
                'assigned_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function syncRoles(int $userId, array $roleIds, ?int $companyId = null, ?int $assignedBy = null): void
    {
        if ($companyId === null) {
            $this->db->delete('user_roles', 'user_id = :id', ['id' => $userId]);
        } else {
            $this->db->delete(
                'user_roles',
                'user_id = :id AND (company_id = :company_id OR company_id IS NULL)',
                ['id' => $userId, 'company_id' => $companyId]
            );
        }
        foreach ($roleIds as $roleId) {
            $this->assignRole($userId, (int) $roleId, $companyId, $assignedBy);
        }
    }
}
