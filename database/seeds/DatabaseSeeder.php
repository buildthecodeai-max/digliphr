<?php

declare(strict_types=1);

/**
 * Database seeder — run from CLI:
 *   php database/seeds/DatabaseSeeder.php
 */

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;

final class DatabaseSeeder
{
    private Database $db;
    private array $permissionsConfig;
    private ?int $companyId = null;
    private ?int $superAdminUserId = null;

    public function __construct()
    {
        // Reboot app config so freshly written .env (web installer) is picked up.
        new Application();
        Database::resetInstance();
        $this->db = Database::getInstance();
        $this->permissionsConfig = config('permissions');
    }

    public function run(): void
    {
        echo "Starting EMS database seed...\n";
        $this->db->beginTransaction();
        try {
            $this->seedPermissions();
            $this->seedRoles();
            $this->seedCompanyStructure();
            $this->seedShift();
            $this->seedLeaveTypes();
            $this->seedSalaryComponents();
            $this->seedSalaryStructure();
            $this->seedSuperAdmin();
            $this->seedSettings();
            $this->db->commit();
            $this->createInstallLock();
            echo "Seed completed successfully.\n";
            echo "Super Admin: admin@example.com / Admin@123\n";
        } catch (Throwable $e) {
            $this->db->rollBack();
            if (PHP_SAPI === 'cli') {
                echo "Seed failed: " . $e->getMessage() . "\n";
                exit(1);
            }
            throw $e;
        }
    }

    private function seedPermissions(): void
    {
        echo "- Permissions\n";
        foreach ($this->permissionsConfig['modules'] as $module => $items) {
            foreach ($items as $slug => $name) {
                $exists = $this->db->fetch('SELECT id FROM permissions WHERE slug = :slug', ['slug' => $slug]);
                if ($exists) {
                    continue;
                }
                $this->db->insert('permissions', [
                    'module' => $module,
                    'name' => $name,
                    'slug' => $slug,
                ]);
            }
        }
    }

    private function seedRoles(): void
    {
        echo "- Roles\n";
        $roles = [
            ['name' => 'Super Admin', 'slug' => 'super_admin', 'description' => 'Full system access', 'is_system' => 1],
            ['name' => 'Company Admin', 'slug' => 'company_admin', 'description' => 'Company administrator', 'is_system' => 1],
            ['name' => 'HR Manager', 'slug' => 'hr_manager', 'description' => 'HR operations', 'is_system' => 1],
            ['name' => 'Department Manager', 'slug' => 'department_manager', 'description' => 'Team lead', 'is_system' => 1],
            ['name' => 'Accountant', 'slug' => 'accountant', 'description' => 'Payroll & finance', 'is_system' => 1],
            ['name' => 'Employee', 'slug' => 'employee', 'description' => 'Standard employee', 'is_system' => 1],
        ];

        foreach ($roles as $role) {
            $row = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug AND company_id IS NULL', ['slug' => $role['slug']]);
            if ($row) {
                continue;
            }
            $this->db->insert('roles', array_merge($role, [
                'company_id' => null,
                'is_active' => 1,
            ]));
        }

        $allPermissions = $this->db->fetchAll('SELECT id, slug FROM permissions');
        $permMap = [];
        foreach ($allPermissions as $p) {
            $permMap[$p['slug']] = (int) $p['id'];
        }

        foreach ($this->permissionsConfig['role_defaults'] as $roleSlug => $permSlugs) {
            $role = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug AND company_id IS NULL', ['slug' => $roleSlug]);
            if (!$role) {
                continue;
            }
            $roleId = (int) $role['id'];
            $expanded = $this->expandPermissions($permSlugs, array_keys($permMap));
            foreach ($expanded as $slug) {
                if (!isset($permMap[$slug])) {
                    continue;
                }
                $exists = $this->db->fetch(
                    'SELECT id FROM role_permissions WHERE role_id = :rid AND permission_id = :pid',
                    ['rid' => $roleId, 'pid' => $permMap[$slug]]
                );
                if (!$exists) {
                    $this->db->insert('role_permissions', [
                        'role_id' => $roleId,
                        'permission_id' => $permMap[$slug],
                    ]);
                }
            }
        }
    }

    private function expandPermissions(array $patterns, array $allSlugs): array
    {
        $result = [];
        foreach ($patterns as $pattern) {
            if ($pattern === '*') {
                return $allSlugs;
            }
            if (str_ends_with($pattern, '.*')) {
                $prefix = substr($pattern, 0, -2);
                foreach ($allSlugs as $slug) {
                    if (str_starts_with($slug, $prefix . '.')) {
                        $result[] = $slug;
                    }
                }
                continue;
            }
            $result[] = $pattern;
        }
        return array_values(array_unique($result));
    }

    private function seedCompanyStructure(): void
    {
        echo "- Company structure\n";
        $company = $this->db->fetch('SELECT id FROM companies WHERE code = :code', ['code' => 'DEMO']);
        if ($company) {
            $this->companyId = (int) $company['id'];
            return;
        }

        $this->companyId = $this->db->insert('companies', [
            'uuid' => $this->uuid(),
            'name' => 'Demo Corporation',
            'legal_name' => 'Demo Corporation (Pvt) Ltd',
            'code' => 'DEMO',
            'email' => 'hr@demo.example.com',
            'phone' => '+92-300-0000000',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'timezone' => config('app.timezone', 'UTC'),
            'currency' => config('app.currency', 'PKR'),
            'is_active' => 1,
        ]);

        $branchId = $this->db->insert('branches', [
            'company_id' => $this->companyId,
            'name' => 'Head Office',
            'code' => 'HQ',
            'city' => 'Karachi',
            'country' => 'Pakistan',
            'attendance_radius' => 100,
            'is_head_office' => 1,
            'is_active' => 1,
        ]);

        $deptId = $this->db->insert('departments', [
            'company_id' => $this->companyId,
            'branch_id' => $branchId,
            'name' => 'Human Resources',
            'code' => 'HR',
            'is_active' => 1,
        ]);

        $this->db->insert('designations', [
            'company_id' => $this->companyId,
            'department_id' => $deptId,
            'name' => 'HR Manager',
            'code' => 'HRM',
            'level' => 5,
            'is_active' => 1,
        ]);
    }

    private function seedShift(): void
    {
        echo "- Default shift\n";
        if (!$this->companyId) {
            return;
        }
        $exists = $this->db->fetch(
            'SELECT id FROM shifts WHERE company_id = :cid AND code = :code',
            ['cid' => $this->companyId, 'code' => 'GEN']
        );
        if ($exists) {
            return;
        }
        $this->db->insert('shifts', [
            'company_id' => $this->companyId,
            'name' => 'General Shift',
            'code' => 'GEN',
            'start_time' => '09:00:00',
            'end_time' => '18:00:00',
            'break_minutes' => 60,
            'grace_minutes' => 15,
            'overtime_after_minutes' => 30,
            'expected_work_minutes' => 480,
            'is_active' => 1,
        ]);
    }

    private function seedLeaveTypes(): void
    {
        echo "- Leave types\n";
        if (!$this->companyId) {
            return;
        }
        $types = [
            ['name' => 'Annual Leave', 'code' => 'AL', 'is_paid' => 1],
            ['name' => 'Sick Leave', 'code' => 'SL', 'is_paid' => 1],
            ['name' => 'Unpaid Leave', 'code' => 'UL', 'is_paid' => 0],
            ['name' => 'Casual Leave', 'code' => 'CL', 'is_paid' => 1],
        ];
        foreach ($types as $type) {
            $exists = $this->db->fetch(
                'SELECT id FROM leave_types WHERE company_id = :cid AND code = :code',
                ['cid' => $this->companyId, 'code' => $type['code']]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert('leave_types', array_merge($type, [
                'company_id' => $this->companyId,
                'requires_approval' => 1,
                'allow_half_day' => 1,
                'is_active' => 1,
            ]));
        }
    }

    private function seedSalaryComponents(): void
    {
        echo "- Salary components\n";
        if (!$this->companyId) {
            return;
        }
        $components = [
            ['name' => 'House Rent Allowance', 'code' => 'HRA', 'type' => 'earning', 'calculation_type' => 'percentage', 'default_amount' => 40, 'percentage_of' => 'basic'],
            ['name' => 'Medical Allowance', 'code' => 'MED', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 5000],
            ['name' => 'Transport Allowance', 'code' => 'TA', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_amount' => 3000],
            ['name' => 'Income Tax', 'code' => 'TAX', 'type' => 'deduction', 'calculation_type' => 'percentage', 'default_amount' => 5, 'percentage_of' => 'basic', 'is_statutory' => 1],
            ['name' => 'Provident Fund', 'code' => 'PF', 'type' => 'deduction', 'calculation_type' => 'percentage', 'default_amount' => 8, 'percentage_of' => 'basic', 'is_statutory' => 1],
        ];
        foreach ($components as $i => $c) {
            $exists = $this->db->fetch(
                'SELECT id FROM salary_components WHERE company_id = :cid AND code = :code',
                ['cid' => $this->companyId, 'code' => $c['code']]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert('salary_components', array_merge([
                'company_id' => $this->companyId,
                'is_taxable' => ($c['type'] === 'earning') ? 1 : 0,
                'is_statutory' => $c['is_statutory'] ?? 0,
                'affects_gross' => 1,
                'affects_net' => 1,
                'is_recurring' => 1,
                'sort_order' => $i + 1,
                'is_active' => 1,
            ], $c));
        }
    }

    private function seedSalaryStructure(): void
    {
        echo "- Salary structure\n";
        if (!$this->companyId) {
            return;
        }
        $structure = $this->db->fetch(
            'SELECT id FROM salary_structures WHERE company_id = :cid AND code = :code',
            ['cid' => $this->companyId, 'code' => 'STD']
        );
        if ($structure) {
            return;
        }

        $structureId = $this->db->insert('salary_structures', [
            'company_id' => $this->companyId,
            'name' => 'Standard Structure',
            'code' => 'STD',
            'description' => 'Default salary structure for employees',
            'currency' => config('app.currency', 'PKR'),
            'is_default' => 1,
            'is_active' => 1,
            'effective_from' => date('Y-01-01'),
        ]);

        $components = $this->db->fetchAll(
            'SELECT id, code, calculation_type, default_amount, percentage_of FROM salary_components WHERE company_id = :cid',
            ['cid' => $this->companyId]
        );
        foreach ($components as $i => $comp) {
            $this->db->insert('salary_structure_items', [
                'salary_structure_id' => $structureId,
                'salary_component_id' => $comp['id'],
                'amount' => $comp['calculation_type'] === 'fixed' ? $comp['default_amount'] : null,
                'percentage' => $comp['calculation_type'] === 'percentage' ? $comp['default_amount'] : null,
                'calculation_type' => $comp['calculation_type'],
                'sort_order' => $i + 1,
            ]);
        }
    }

    private function seedSuperAdmin(): void
    {
        echo "- Super admin user\n";
        $user = $this->db->fetch('SELECT id FROM users WHERE email = :email', ['email' => 'admin@example.com']);
        if ($user) {
            $this->superAdminUserId = (int) $user['id'];
            return;
        }

        $this->superAdminUserId = $this->db->insert('users', [
            'uuid' => $this->uuid(),
            'name' => 'Super Admin',
            'email' => 'admin@example.com',
            'password' => password_hash('Admin@123', PASSWORD_DEFAULT),
            'is_active' => 1,
            'is_super_admin' => 1,
            'timezone' => config('app.timezone', 'UTC'),
        ]);

        $role = $this->db->fetch('SELECT id FROM roles WHERE slug = :slug AND company_id IS NULL', ['slug' => 'super_admin']);
        if ($role) {
            $this->db->insert('user_roles', [
                'user_id' => $this->superAdminUserId,
                'role_id' => (int) $role['id'],
                'company_id' => $this->companyId,
            ]);
        }
    }

    private function seedSettings(): void
    {
        echo "- System settings\n";
        if (!$this->companyId) {
            return;
        }
        $settings = [
            ['group_name' => 'general', 'setting_key' => 'company_name', 'setting_value' => 'Demo Corporation', 'value_type' => 'string', 'is_public' => 1],
            ['group_name' => 'payroll', 'setting_key' => 'payroll_day', 'setting_value' => '25', 'value_type' => 'integer'],
            ['group_name' => 'payroll', 'setting_key' => 'overtime_rate_multiplier', 'setting_value' => '1.5', 'value_type' => 'decimal'],
            ['group_name' => 'attendance', 'setting_key' => 'default_radius', 'setting_value' => '100', 'value_type' => 'integer'],
            ['group_name' => 'leave', 'setting_key' => 'annual_leave_days', 'setting_value' => '20', 'value_type' => 'integer'],
        ];
        foreach ($settings as $s) {
            $exists = $this->db->fetch(
                'SELECT id FROM system_settings WHERE company_id = :cid AND setting_key = :key',
                ['cid' => $this->companyId, 'key' => $s['setting_key']]
            );
            if ($exists) {
                continue;
            }
            $this->db->insert('system_settings', array_merge($s, [
                'company_id' => $this->companyId,
                'is_public' => $s['is_public'] ?? 0,
            ]));
        }
    }

    private function createInstallLock(): void
    {
        $dir = dirname(__DIR__, 2) . '/install';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($dir . '/installed.lock', date('c') . " — seeded\n");
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}

if (PHP_SAPI === 'cli' && realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    (new DatabaseSeeder())->run();
}
