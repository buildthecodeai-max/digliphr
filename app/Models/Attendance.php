<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class Attendance extends Model
{
    protected string $table = 'attendance';
    protected array $fillable = [
        'uuid', 'employee_id', 'company_id', 'branch_id', 'shift_id', 'attendance_date',
        'check_in_at', 'check_out_at', 'original_check_in_at', 'original_check_out_at',
        'status', 'verification_status', 'work_minutes', 'late_minutes', 'early_leave_minutes',
        'overtime_minutes', 'break_minutes', 'expected_work_minutes', 'is_remote', 'is_manual',
        'is_locked', 'source', 'remarks', 'admin_notes', 'leave_request_id',
        'approved_by', 'approved_at', 'created_by', 'updated_by',
        'deleted_at', 'deleted_by', 'deletion_reason',
    ];
    protected bool $softDeletes = true;

    public function findToday(int $employeeId, ?string $date = null): ?array
    {
        $date = $date ?? date('Y-m-d');

        return $this->db->fetch(
            'SELECT * FROM attendance
             WHERE employee_id = :employee_id AND attendance_date = :date AND deleted_at IS NULL
             LIMIT 1',
            ['employee_id' => $employeeId, 'date' => $date]
        );
    }

    public function findActive(int $employeeId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM attendance
             WHERE employee_id = :employee_id
               AND check_in_at IS NOT NULL
               AND check_out_at IS NULL
               AND deleted_at IS NULL
             ORDER BY check_in_at DESC
             LIMIT 1',
            ['employee_id' => $employeeId]
        );
    }

    public function findDetailed(int $id, bool $includeArchived = false): ?array
    {
        $deletedClause = $includeArchived ? '1=1' : 'a.deleted_at IS NULL';

        return $this->db->fetch(
            "SELECT a.*,
                    e.employee_code, e.first_name, e.last_name, e.department_id,
                    d.name AS department_name,
                    b.name AS branch_name,
                    c.name AS company_name,
                    s.name AS shift_name, s.start_time AS shift_start, s.end_time AS shift_end,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    au.name AS archived_by_name
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, e.branch_id)
             LEFT JOIN companies c ON c.id = a.company_id
             LEFT JOIN shifts s ON s.id = COALESCE(a.shift_id, e.shift_id)
             LEFT JOIN users au ON au.id = a.deleted_by
             WHERE a.id = :id AND {$deletedClause}
             LIMIT 1",
            ['id' => $id]
        );
    }

    public function search(array $filters, int $page = 1, int $perPage = 20): array
    {
        if (!empty($filters['archived'])) {
            $clauses = ['a.deleted_at IS NOT NULL'];
        } elseif (!empty($filters['include_archived'])) {
            $clauses = ['1=1'];
        } else {
            $clauses = ['a.deleted_at IS NULL'];
        }
        $params = [];

        if (!empty($filters['company_id'])) {
            $clauses[] = 'a.company_id = :company_id';
            $params['company_id'] = $filters['company_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'a.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['shift_id'])) {
            $clauses[] = 'a.shift_id = :shift_id';
            $params['shift_id'] = $filters['shift_id'];
        }
        if (!empty($filters['status'])) {
            if ($filters['status'] === 'missing_checkout') {
                $clauses[] = "(a.status = 'missing_checkout' OR (a.check_in_at IS NOT NULL AND a.check_out_at IS NULL AND a.status NOT IN ('on_leave','holiday','weekend','absent')))";
            } else {
                $clauses[] = 'a.status = :status';
                $params['status'] = $filters['status'];
            }
        }
        if (!empty($filters['verification_status'])) {
            $clauses[] = 'a.verification_status = :verification_status';
            $params['verification_status'] = $filters['verification_status'];
        }
        if (!empty($filters['date'])) {
            $clauses[] = 'a.attendance_date = :date';
            $params['date'] = $filters['date'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'a.attendance_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'a.attendance_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['is_remote'])) {
            $clauses[] = 'a.is_remote = :is_remote';
            $params['is_remote'] = (int) $filters['is_remote'];
        }
        if (!empty($filters['archived_by'])) {
            $clauses[] = 'a.deleted_by = :archived_by';
            $params['archived_by'] = (int) $filters['archived_by'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.first_name LIKE :q1 OR e.last_name LIKE :q2 OR e.employee_code LIKE :q3 OR a.deletion_reason LIKE :q4)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $where = implode(' AND ', $clauses);
        $offset = (max(1, $page) - 1) * $perPage;
        $orderBy = !empty($filters['archived'])
            ? 'a.deleted_at DESC, a.attendance_date DESC'
            : 'a.attendance_date DESC, a.check_in_at DESC';

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT a.*,
                    e.employee_code, e.first_name, e.last_name,
                    d.name AS department_name,
                    b.name AS branch_name,
                    c.name AS company_name,
                    s.name AS shift_name,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    au.name AS archived_by_name
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, e.branch_id)
             LEFT JOIN companies c ON c.id = a.company_id
             LEFT JOIN shifts s ON s.id = COALESCE(a.shift_id, e.shift_id)
             LEFT JOIN users au ON au.id = a.deleted_by
             WHERE {$where}
             ORDER BY {$orderBy}
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

    public function stats(array $filters): array
    {
        $clauses = ['a.deleted_at IS NULL'];
        $params = [];

        if (!empty($filters['company_id'])) {
            $clauses[] = 'a.company_id = :company_id';
            $params['company_id'] = $filters['company_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'a.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'a.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'a.attendance_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'a.attendance_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        $where = implode(' AND ', $clauses);

        $row = $this->db->fetch(
            "SELECT
                COUNT(*) AS total_records,
                SUM(CASE WHEN a.status = 'present' THEN 1 ELSE 0 END) AS present_count,
                SUM(CASE WHEN a.status = 'late' THEN 1 ELSE 0 END) AS late_count,
                SUM(CASE WHEN a.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
                SUM(CASE WHEN a.status = 'remote' THEN 1 ELSE 0 END) AS remote_count,
                SUM(CASE WHEN a.status = 'half_day' THEN 1 ELSE 0 END) AS half_day_count,
                SUM(CASE WHEN a.status = 'on_leave' THEN 1 ELSE 0 END) AS on_leave_count,
                SUM(CASE WHEN a.status = 'missing_checkout'
                              OR (a.check_in_at IS NOT NULL AND a.check_out_at IS NULL
                                  AND a.status NOT IN ('on_leave','holiday','weekend','absent'))
                    THEN 1 ELSE 0 END) AS missing_checkout_count,
                SUM(CASE WHEN a.verification_status = 'flagged' THEN 1 ELSE 0 END) AS flagged_count,
                COALESCE(SUM(CASE WHEN a.check_out_at IS NOT NULL THEN a.work_minutes ELSE 0 END), 0) AS total_work_minutes,
                COALESCE(SUM(CASE WHEN a.check_out_at IS NOT NULL THEN a.overtime_minutes ELSE 0 END), 0) AS total_overtime_minutes,
                COALESCE(SUM(a.late_minutes), 0) AS total_late_minutes
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}",
            $params
        );

        return $row ?: [
            'total_records' => 0,
            'present_count' => 0,
            'late_count' => 0,
            'absent_count' => 0,
            'remote_count' => 0,
            'half_day_count' => 0,
            'on_leave_count' => 0,
            'missing_checkout_count' => 0,
            'flagged_count' => 0,
            'total_work_minutes' => 0,
            'total_overtime_minutes' => 0,
            'total_late_minutes' => 0,
        ];
    }

    public function remoteByEmployee(array $filters = []): array
    {
        $deletedClause = !empty($filters['archived']) ? 'a.deleted_at IS NOT NULL' : 'a.deleted_at IS NULL';
        $clauses = [$deletedClause, "a.status = 'remote'"];
        $params  = [];

        if (!empty($filters['company_id'])) {
            $clauses[] = 'a.company_id = :company_id';
            $params['company_id'] = $filters['company_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = $filters['department_id'];
        }
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'a.branch_id = :branch_id';
            $params['branch_id'] = $filters['branch_id'];
        }
        if (!empty($filters['date_from'])) {
            $clauses[] = 'a.attendance_date >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $clauses[] = 'a.attendance_date <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.first_name LIKE :q1 OR e.last_name LIKE :q2 OR e.employee_code LIKE :q3)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
        }

        $where = implode(' AND ', $clauses);

        return $this->db->fetchAll(
            "SELECT
                e.id            AS employee_id,
                e.employee_code,
                e.first_name,
                e.last_name,
                d.name          AS department_name,
                b.name          AS branch_name,
                COUNT(a.id)                        AS remote_days,
                COALESCE(SUM(a.work_minutes), 0)   AS total_work_minutes,
                COALESCE(SUM(a.overtime_minutes), 0) AS total_overtime_minutes,
                MAX(a.attendance_date)             AS last_remote_date
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = COALESCE(a.branch_id, e.branch_id)
             WHERE {$where}
             GROUP BY e.id, e.employee_code, e.first_name, e.last_name, d.name, b.name
             ORDER BY remote_days DESC, e.first_name ASC",
            $params
        );
    }

    public function calendarMonth(int $employeeId, int $year, int $month): array
    {
        $start = sprintf('%04d-%02d-01', $year, $month);
        $end = date('Y-m-t', strtotime($start));

        return $this->db->fetchAll(
            'SELECT attendance_date, status, check_in_at, check_out_at, work_minutes,
                    late_minutes, overtime_minutes, verification_status, is_remote
             FROM attendance
             WHERE employee_id = :employee_id
               AND attendance_date BETWEEN :start AND :end
               AND deleted_at IS NULL
             ORDER BY attendance_date ASC',
            ['employee_id' => $employeeId, 'start' => $start, 'end' => $end]
        );
    }

    public function images(int $attendanceId): array
    {
        return $this->db->fetchAll(
            'SELECT * FROM attendance_images
             WHERE attendance_id = :id AND deleted_at IS NULL
             ORDER BY type ASC, created_at ASC',
            ['id' => $attendanceId]
        );
    }

    public function locations(int $attendanceId): array
    {
        return $this->db->fetchAll(
            'SELECT al.*, b.name AS branch_name,
                    b.latitude AS branch_latitude, b.longitude AS branch_longitude,
                    b.attendance_radius AS branch_radius
             FROM attendance_locations al
             LEFT JOIN branches b ON b.id = al.branch_id
             WHERE al.attendance_id = :id
             ORDER BY al.type ASC, al.created_at ASC',
            ['id' => $attendanceId]
        );
    }

    public function corrections(int $attendanceId): array
    {
        return $this->db->fetchAll(
            'SELECT ac.*, u.name AS reviewer_name
             FROM attendance_corrections ac
             LEFT JOIN users u ON u.id = ac.reviewed_by
             WHERE ac.attendance_id = :id AND ac.deleted_at IS NULL
             ORDER BY ac.created_at DESC',
            ['id' => $attendanceId]
        );
    }

    public function auditLogs(int $attendanceId): array
    {
        return $this->db->fetchAll(
            'SELECT al.*, u.name AS performer_name
             FROM attendance_audit_logs al
             LEFT JOIN users u ON u.id = al.performed_by
             WHERE al.attendance_id = :id
             ORDER BY al.performed_at DESC',
            ['id' => $attendanceId]
        );
    }
}
