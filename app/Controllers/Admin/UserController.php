<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Exceptions\HttpException;
use App\Models\User;
use App\Services\AuditService;

class UserController extends Controller
{
    private User $users;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->users = new User();
    }

    public function index(): void
    {
        $this->authorize('roles.manage');
        $db = Database::getInstance();
        $q = trim((string) $this->request->input('q', ''));
        $status = $this->request->input('status');

        $clauses = ['u.deleted_at IS NULL'];
        $params = [];
        if ($q !== '') {
            $clauses[] = '(u.name LIKE :q OR u.email LIKE :q OR u.username LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }
        if ($status === 'active') {
            $clauses[] = 'u.is_active = 1';
        } elseif ($status === 'inactive') {
            $clauses[] = 'u.is_active = 0';
        }
        $scope = $this->tenant->sql('tenant_company.company_id', 'user_company');
        if (!$this->tenant->isGlobal()) {
            $clauses[] = 'EXISTS (
                SELECT 1 FROM (
                    SELECT ur.company_id FROM user_roles ur WHERE ur.user_id = u.id AND ur.company_id IS NOT NULL
                    UNION
                    SELECT e.company_id FROM employees e WHERE e.user_id = u.id AND e.deleted_at IS NULL
                ) tenant_company WHERE ' . $scope['sql'] . '
            )';
            $params = array_merge($params, $scope['params']);
            $clauses[] = 'u.is_super_admin = 0';
        }
        $where = implode(' AND ', $clauses);

        $rows = $db->fetchAll(
            "SELECT u.id, u.name, u.email, u.username, u.phone, u.is_active, u.is_super_admin,
                    u.last_login_at, u.created_at, u.temp_password,
                    (SELECT GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', ')
                       FROM user_roles ur
                       INNER JOIN roles r ON r.id = ur.role_id
                      WHERE ur.user_id = u.id) AS role_names
             FROM users u
             WHERE {$where}
             ORDER BY u.id DESC
             LIMIT 500",
            $params
        );

        $this->view('admin/users/index', [
            'title' => 'Users',
            'rows' => $rows,
            'filters' => ['q' => $q, 'status' => $status],
        ]);
    }

    public function create(): void
    {
        $this->authorize('roles.manage');
        $this->renderForm(null);
    }

    public function store(): void
    {
        $this->authorize('roles.manage');
        $data = $this->validateUser(null);
        $db = Database::getInstance();

        $id = $db->insert('users', [
            'uuid' => $this->uuid(),
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?: null,
            'phone' => $data['phone'] ?: null,
            'password' => password_hash($data['password'], PASSWORD_DEFAULT),
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'force_password_reset' => !empty($data['force_password_reset']) ? 1 : 0,
            'password_changed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->users->syncRoles($id, $data['role_ids'], $data['company_id'], (int) ($this->user()['id'] ?? 0));
        (new AuditService())->log('create', 'users', $id, null, [
            'name' => $data['name'],
            'email' => $data['email'],
            'role_ids' => $data['role_ids'],
        ]);

        flash('success', 'User created successfully.');
        $this->redirect('/admin/users');
    }

    public function edit(int $id): void
    {
        $this->authorize('roles.manage');
        $user = $this->users->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            throw new HttpException('User not found.', 404);
        }
        $this->assertUserAccess($id);
        $this->renderForm($user);
    }

    public function update(int $id): void
    {
        $this->authorize('roles.manage');
        $user = $this->users->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            throw new HttpException('User not found.', 404);
        }
        $this->assertUserAccess($id);

        $data = $this->validateUser($id);
        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?: null,
            'phone' => $data['phone'] ?: null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'force_password_reset' => !empty($data['force_password_reset']) ? 1 : 0,
        ];
        if (!empty($data['password'])) {
            $payload['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $payload['password_changed_at'] = date('Y-m-d H:i:s');
        }

        $this->users->update($id, $payload);

        // Super admin role assignments stay managed separately; still allow role sync.
        if (empty($user['is_super_admin'])) {
            $this->users->syncRoles($id, $data['role_ids'], $data['company_id'], (int) ($this->user()['id'] ?? 0));
        }

        (new AuditService())->log('update', 'users', $id, $user, [
            'name' => $payload['name'],
            'email' => $payload['email'],
            'is_active' => $payload['is_active'],
            'role_ids' => $data['role_ids'],
        ]);

        flash('success', 'User updated successfully.');
        $this->redirect('/admin/users');
    }

    public function resetAllPasswords(): void
    {
        $this->authorize('roles.manage');
        if (empty($this->user()['is_super_admin'])) {
            flash('error', 'Only super admins can reset all passwords.');
            $this->redirect('/admin/users');
        }

        $db = Database::getInstance();
        $users = $db->fetchAll(
            'SELECT id FROM users WHERE deleted_at IS NULL AND is_super_admin = 0'
        );

        foreach ($users as $user) {
            $tmp = $this->makeTempPassword();
            $db->update('users', [
                'password'            => password_hash($tmp, PASSWORD_DEFAULT),
                'temp_password'       => $tmp,
                'force_password_reset' => 1,
                'password_changed_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $user['id']]);
        }

        (new AuditService())->log('reset_all_passwords', 'users', 0, null, ['count' => count($users)]);
        flash('success', 'Passwords reset for ' . count($users) . ' users. Open each user\'s credentials to see the temporary password.');
        $this->redirect('/admin/users');
    }

    private function makeTempPassword(): string
    {
        $u = chr(random_int(65, 90));
        $l = chr(random_int(97, 122)) . chr(random_int(97, 122));
        $d = random_int(100, 999);
        return $u . $l . $d . '@!';
    }

    public function destroy(int $id): void
    {
        $this->authorize('roles.manage');
        $user = $this->users->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            throw new HttpException('User not found.', 404);
        }
        $this->assertUserAccess($id);

        $authId = (int) ($this->user()['id'] ?? 0);
        if ($authId === $id) {
            flash('error', 'You cannot delete your own account.');
            $this->redirect('/admin/users');
        }
        if (!empty($user['is_super_admin'])) {
            flash('error', 'Super admin accounts cannot be deleted.');
            $this->redirect('/admin/users');
        }

        $this->users->update($id, ['deleted_at' => date('Y-m-d H:i:s'), 'is_active' => 0]);
        (new AuditService())->log('delete', 'users', $id, $user, []);

        flash('success', 'User deleted.');
        $this->redirect('/admin/users');
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('roles.manage');
        $user = $this->users->find($id);
        if (!$user || !empty($user['deleted_at'])) {
            throw new HttpException('User not found.', 404);
        }
        $this->assertUserAccess($id);

        $authId = (int) ($this->user()['id'] ?? 0);
        if ($authId === $id) {
            flash('error', 'You cannot deactivate your own account.');
            $this->redirect('/admin/users');
        }

        if (!empty($user['is_super_admin']) && (int) $user['is_active'] === 1) {
            flash('error', 'Super admin accounts cannot be deactivated here.');
            $this->redirect('/admin/users');
        }

        $next = (int) $user['is_active'] === 1 ? 0 : 1;
        $this->users->update($id, ['is_active' => $next]);
        (new AuditService())->log($next ? 'activate' : 'deactivate', 'users', $id, $user, ['is_active' => $next]);

        flash('success', $next ? 'User activated.' : 'User deactivated.');
        $this->redirect('/admin/users');
    }

    private function renderForm(?array $user): void
    {
        $db = Database::getInstance();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($user) {
            $existingCompany = $db->fetchColumn(
                'SELECT company_id FROM user_roles WHERE user_id = :id AND company_id IS NOT NULL LIMIT 1',
                ['id' => (int) $user['id']]
            ) ?: $db->fetchColumn(
                'SELECT company_id FROM employees WHERE user_id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => (int) $user['id']]
            );
            if ($existingCompany) {
                $companyId = $this->tenant->assertCompany((int) $existingCompany);
            }
        }
        $roles = $db->fetchAll(
            'SELECT id, name, slug FROM roles
             WHERE deleted_at IS NULL AND is_active = 1
               AND (company_id IS NULL OR company_id = :company_id)
               AND (:allow_super = 1 OR slug <> "super_admin")
             ORDER BY name',
            ['company_id' => $companyId, 'allow_super' => $this->tenant->isGlobal() ? 1 : 0]
        );
        $assigned = [];
        if ($user) {
            $assigned = array_map(
                'intval',
                array_column(
                    $db->fetchAll(
                        'SELECT role_id FROM user_roles
                         WHERE user_id = :id AND (company_id = :company_id OR company_id IS NULL)',
                        ['id' => (int) $user['id'], 'company_id' => $companyId]
                    ),
                    'role_id'
                )
            );
        }

        $this->view('admin/users/form', [
            'title' => $user ? 'Edit User' : 'Add User',
            'user' => $user,
            'roles' => $roles,
            'assignedRoleIds' => $assigned,
            'companies' => $this->tenant->companies(true),
            'companyId' => $companyId,
        ]);
    }

    /**
     * @return array{name:string,email:string,username:?string,phone:?string,password:?string,is_active:int,force_password_reset:int,role_ids:list<int>}
     */
    private function validateUser(?int $id): array
    {
        $rules = [
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'email' => 'required|email|max:190',
            'username' => 'nullable|max:100',
            'phone' => 'nullable|max:40',
            'password' => ($id ? 'nullable' : 'required') . '|min:8|max:100',
        ];
        $data = $this->validate($rules);
        $companyId = $this->tenant->assertCompany((int) $data['company_id']);

        $db = Database::getInstance();
        $emailExists = $db->fetch(
            'SELECT id FROM users WHERE email = :email AND deleted_at IS NULL'
            . ($id ? ' AND id <> :id' : '')
            . ' LIMIT 1',
            $id ? ['email' => $data['email'], 'id' => $id] : ['email' => $data['email']]
        );
        if ($emailExists) {
            throw new HttpException('Email is already in use.', 422);
        }

        $roleIds = $this->request->input('role_ids', []);
        if (!is_array($roleIds)) {
            $roleIds = [];
        }
        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roleIds = array_values(array_filter($roleIds, static fn (int $rid) => $rid > 0));
        if ($roleIds !== []) {
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
            $bindings = array_merge($roleIds, [$companyId, $this->tenant->isGlobal() ? 1 : 0]);
            $validCount = (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM roles
                 WHERE id IN (' . $placeholders . ') AND deleted_at IS NULL AND is_active = 1
                   AND (company_id IS NULL OR company_id = ?)
                   AND (? = 1 OR slug <> "super_admin")',
                $bindings
            );
            if ($validCount !== count($roleIds)) {
                throw new HttpException('One or more selected roles are not available for this company.', 422);
            }
        }

        return [
            'name' => $data['name'],
            'email' => $data['email'],
            'username' => $data['username'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'] ?? null,
            'is_active' => $this->request->input('is_active') ? 1 : 0,
            'force_password_reset' => $this->request->input('force_password_reset') ? 1 : 0,
            'role_ids' => $roleIds,
            'company_id' => $companyId,
        ];
    }

    private function assertUserAccess(int $userId): void
    {
        if ($this->tenant->isGlobal()) {
            return;
        }
        $scope = $this->tenant->sql('tenant_company.company_id', 'managed_user_company');
        $row = Database::getInstance()->fetch(
            'SELECT 1 FROM (
                SELECT ur.company_id FROM user_roles ur WHERE ur.user_id = :uid AND ur.company_id IS NOT NULL
                UNION
                SELECT e.company_id FROM employees e WHERE e.user_id = :uid2 AND e.deleted_at IS NULL
             ) tenant_company WHERE ' . $scope['sql'] . ' LIMIT 1',
            array_merge(['uid' => $userId, 'uid2' => $userId], $scope['params'])
        );
        if (!$row) {
            throw new HttpException('User not found.', 404);
        }
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
