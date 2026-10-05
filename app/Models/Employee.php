<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Employee extends Model
{
    protected string $table = 'employees';
    protected array $fillable = [
        'uuid', 'employee_code', 'user_id', 'company_id', 'branch_id', 'department_id',
        'designation_id', 'reporting_manager_id', 'shift_id', 'leave_policy_id',
        'salary_structure_id', 'first_name', 'last_name', 'profile_image',
        'date_of_birth', 'gender', 'national_id', 'marital_status',
        'personal_email', 'company_email', 'phone', 'alternate_phone',
        'current_address', 'permanent_address', 'joining_date',
        'employment_type', 'employment_status', 'probation_start',
        'probation_end', 'contract_start', 'contract_end',
        'basic_salary', 'remote_attendance_allowed',
        'attendance_policy_notes', 'deleted_at',
    ];
    protected bool $softDeletes = true;

    public function fullName(array $employee): string
    {
        return trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
    }

    public function findDetailed(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT e.*,
                    c.name AS company_name,
                    b.name AS branch_name, b.latitude AS branch_lat, b.longitude AS branch_lng,
                    b.attendance_radius, b.timezone AS branch_timezone,
                    d.name AS department_name,
                    des.name AS designation_name,
                    s.name AS shift_name, s.start_time AS shift_start, s.end_time AS shift_end,
                    s.grace_minutes, s.break_minutes,
                    CONCAT(m.first_name, " ", m.last_name) AS manager_name,
                    u.email AS user_email, u.is_active AS user_is_active
             FROM employees e
             LEFT JOIN companies c ON c.id = e.company_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN designations des ON des.id = e.designation_id
             LEFT JOIN shifts s ON s.id = e.shift_id
             LEFT JOIN employees m ON m.id = e.reporting_manager_id
             LEFT JOIN users u ON u.id = e.user_id
             WHERE e.id = :id AND e.deleted_at IS NULL
             LIMIT 1',
            ['id' => $id]
        );
    }

    public function search(array $filters, int $page = 1, int $perPage = 15): array
    {
        $clauses = ['e.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['q'])) {
            $clauses[] = '(e.first_name LIKE :q OR e.last_name LIKE :q OR e.employee_code LIKE :q OR e.company_email LIKE :q OR e.phone LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['employment_status'])) {
            $clauses[] = 'e.employment_status = :employment_status';
            $params['employment_status'] = $filters['employment_status'];
        }
        if (!empty($filters['designation_id'])) {
            $clauses[] = 'e.designation_id = :designation_id';
            $params['designation_id'] = $filters['designation_id'];
        }
        if (array_key_exists('company_id', $filters) && $filters['company_id'] !== null) {
            $clauses[] = 'e.company_id = :company_id';
            $params['company_id'] = (int) $filters['company_id'];
        }

        $where = implode(' AND ', $clauses);
        $offset = (max(1, $page) - 1) * $perPage;

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM employees e WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT e.*, d.name AS department_name, b.name AS branch_name, des.name AS designation_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN designations des ON des.id = e.designation_id
             WHERE {$where}
             ORDER BY e.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        return [
            'data' => $rows,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => max(1, $page),
            'last_page' => max(1, (int) ceil($total / $perPage)),
        ];
    }

    public function activeCount(): int
    {
        return $this->count(['employment_status' => 'active']);
    }

    public function nextCode(string $prefix = 'EMP', ?int $companyId = null): string
    {
        $companySql = $companyId ? ' AND company_id = :company_id' : '';
        $params = ['p' => $prefix . '%'];
        if ($companyId) {
            $params['company_id'] = $companyId;
        }
        $last = $this->db->fetchColumn(
            "SELECT employee_code FROM employees WHERE employee_code LIKE :p{$companySql} ORDER BY id DESC LIMIT 1",
            $params
        );

        if (!$last) {
            return $prefix . '0001';
        }

        $num = (int) preg_replace('/\D/', '', (string) $last);
        return $prefix . str_pad((string) ($num + 1), 4, '0', STR_PAD_LEFT);
    }
}
