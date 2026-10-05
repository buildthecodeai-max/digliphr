<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Exceptions\HttpException;
use App\Services\AuditService;

class RoleController extends Controller
{
    public function index(): void
    {
        $this->authorize('roles.view');
        try {
            if ($this->tenant->isGlobal()) {
                $roles = Database::getInstance()->fetchAll(
                    'SELECT r.*,
                            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,
                            (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id) AS user_count
                     FROM roles r WHERE r.deleted_at IS NULL ORDER BY r.id'
                );
            } else {
                $roleScope = $this->tenant->sql('r.company_id', 'role_company');
                $assignmentScope = $this->tenant->sql('ur.company_id', 'role_assignment_company');
                $roles = Database::getInstance()->fetchAll(
                    'SELECT r.*,
                            (SELECT COUNT(*) FROM role_permissions rp WHERE rp.role_id = r.id) AS permission_count,
                            (SELECT COUNT(*) FROM user_roles ur WHERE ur.role_id = r.id AND (' . $assignmentScope['sql'] . ')) AS user_count
                     FROM roles r
                     WHERE r.deleted_at IS NULL AND (r.company_id IS NULL OR (' . $roleScope['sql'] . '))
                     ORDER BY r.id',
                    array_merge($assignmentScope['params'], $roleScope['params'])
                );
            }
        } catch (\Throwable) {
            $roles = [];
        }

        $this->view('admin/roles/index', [
            'title' => 'Roles & Permissions',
            'roles' => $roles,
        ]);
    }

    public function create(): void
    {
        $this->authorize('roles.manage');
        $this->view('admin/roles/form', [
            'title' => 'Add Role',
            'role' => null,
            'companies' => $this->tenant->isGlobal() ? $this->tenant->companies(true) : [],
            'isGlobalViewer' => $this->tenant->isGlobal(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('roles.manage');
        $db = Database::getInstance();

        $data = $this->validate([
            'name' => 'required|min:2|max:100',
            'description' => 'nullable|max:500',
        ]);

        $companyId = $this->tenant->isGlobal()
            ? ((int) $this->request->input('company_id', 0) ?: null)
            : $this->tenant->resolveCompanyId(null);
        if ($companyId) {
            $this->tenant->assertCompany($companyId);
        }

        $slugBase = trim((string) preg_replace('/[^a-z0-9]+/', '_', strtolower($data['name'])), '_') ?: 'role';
        $slug = $slugBase;
        $suffix = 1;
        while ($db->fetchColumn('SELECT COUNT(*) FROM roles WHERE slug = :slug', ['slug' => $slug]) > 0) {
            $slug = $slugBase . '_' . (++$suffix);
        }

        $id = $db->insert('roles', [
            'company_id' => $companyId,
            'name' => $data['name'],
            'slug' => $slug,
            'description' => ($data['description'] ?? '') !== '' ? $data['description'] : null,
            'is_system' => 0,
            'is_active' => 1,
        ]);

        (new AuditService())->log('create', 'roles', $id, null, ['name' => $data['name'], 'slug' => $slug, 'company_id' => $companyId]);

        flash('success', 'Role created. Assign its permissions next.');
        $this->redirect('/admin/roles/' . $id . '/edit');
    }

    public function destroy(int $id): void
    {
        $this->authorize('roles.manage');
        $db = Database::getInstance();
        $role = $db->fetch('SELECT * FROM roles WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$role) {
            throw new HttpException('Role not found.', 404);
        }
        $this->assertRoleAccess($role);

        if ((int) $role['is_system'] === 1) {
            flash('error', 'Built-in system roles cannot be deleted.');
            $this->redirect('/admin/roles');
            return;
        }

        $userCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM user_roles WHERE role_id = :id', ['id' => $id]);
        if ($userCount > 0) {
            flash('error', "This role is still assigned to {$userCount} user(s) — reassign them to a different role before deleting.");
            $this->redirect('/admin/roles');
            return;
        }

        $db->update('roles', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        (new AuditService())->log('delete', 'roles', $id, $role);

        flash('success', 'Role deleted successfully.');
        $this->redirect('/admin/roles');
    }

    public function edit(int $id): void
    {
        $this->authorize('roles.manage');
        $db = Database::getInstance();
        $role = $db->fetch('SELECT * FROM roles WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$role) {
            throw new HttpException('Role not found.', 404);
        }
        $this->assertRoleAccess($role);

        $all = $db->fetchAll('SELECT * FROM permissions ORDER BY module, slug');
        $assigned = $db->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = :id', ['id' => $id]);
        $assignedIds = array_map('intval', array_column($assigned, 'permission_id'));

        $grouped = [];
        foreach ($all as $p) {
            $grouped[$p['module']][] = $p;
        }

        $this->view('admin/roles/edit', [
            'title' => 'Edit Role: ' . $role['name'],
            'role' => $role,
            'grouped' => $grouped,
            'assignedIds' => $assignedIds,
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('roles.manage');
        $db = Database::getInstance();
        $role = $db->fetch('SELECT * FROM roles WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$role) {
            throw new HttpException('Role not found.', 404);
        }
        $this->assertRoleAccess($role);

        if (($role['slug'] ?? '') === 'super_admin') {
            flash('warning', 'Super Admin always has full access.');
            $this->redirect('/admin/roles/' . $id . '/edit');
        }

        $permissionIds = $this->request->input('permissions', []);
        if (!is_array($permissionIds)) {
            $permissionIds = [];
        }

        $db->beginTransaction();
        try {
            $previous = $db->fetchAll('SELECT permission_id FROM role_permissions WHERE role_id = :id', ['id' => $id]);
            $db->delete('role_permissions', 'role_id = :id', ['id' => $id]);
            foreach ($permissionIds as $pid) {
                $db->insert('role_permissions', [
                    'role_id' => (int) $id,
                    'permission_id' => (int) $pid,
                ]);
            }
            $db->commit();
            (new AuditService())->log('permissions_update', 'roles', (int) $id, $previous, $permissionIds);
            flash('success', 'Role permissions updated.');
        } catch (\Throwable $e) {
            $db->rollBack();
            flash('error', 'Failed to update permissions.');
        }

        $this->redirect('/admin/roles/' . $id . '/edit');
    }

    private function assertRoleAccess(array $role): void
    {
        if ($this->tenant->isGlobal()) {
            return;
        }
        if (empty($role['company_id'])) {
            throw new HttpException('Global role permissions can only be changed by a super administrator.', 403);
        }
        $this->tenant->assertCompany((int) $role['company_id']);
    }
}
