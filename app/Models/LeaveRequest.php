<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class LeaveRequest extends Model
{
    protected string $table = 'leave_requests';
    protected array $fillable = [
        'uuid', 'company_id', 'employee_id', 'leave_type_id', 'start_date', 'end_date',
        'is_half_day', 'half_day_type', 'reason', 'status', 'calendar_days', 'weekend_days',
        'holiday_days', 'chargeable_days', 'handover_employee_id', 'handover_notes',
        'contact_during_leave', 'emergency_contact', 'applied_at', 'cancelled_at',
        'cancellation_reason', 'created_by', 'updated_by',
        'deleted_at', 'deleted_by', 'deletion_reason',
    ];
    protected bool $softDeletes = true;

    public function findDetailed(int $id, bool $includeArchived = false): ?array
    {
        $deletedClause = $includeArchived ? '1=1' : 'lr.deleted_at IS NULL';

        return $this->db->fetch(
            "SELECT lr.*,
                    lt.name AS leave_type_name, lt.code AS leave_type_code, lt.color AS leave_type_color,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name,
                    e.employee_code, e.company_id AS emp_company_id,
                    d.name AS department_name,
                    CONCAT(h.first_name, ' ', h.last_name) AS handover_name,
                    au.name AS archived_by_name
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             INNER JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN employees h ON h.id = lr.handover_employee_id
             LEFT JOIN users au ON au.id = lr.deleted_by
             WHERE lr.id = :id AND {$deletedClause}
             LIMIT 1",
            ['id' => $id]
        );
    }

    public function search(array $filters, int $page = 1, int $perPage = 15): array
    {
        if (!empty($filters['archived'])) {
            $clauses = ['lr.deleted_at IS NOT NULL'];
        } elseif (!empty($filters['include_archived'])) {
            $clauses = ['1=1'];
        } else {
            $clauses = ['lr.deleted_at IS NULL'];
        }
        $params = [];

        if (!empty($filters['company_id'])) {
            $clauses[] = 'lr.company_id = :company_id';
            $params['company_id'] = $filters['company_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'lr.employee_id = :employee_id';
            $params['employee_id'] = $filters['employee_id'];
        }
        if (!empty($filters['status'])) {
            $clauses[] = 'lr.status = :status';
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['leave_type_id'])) {
            $clauses[] = 'lr.leave_type_id = :leave_type_id';
            $params['leave_type_id'] = $filters['leave_type_id'];
        }
        if (!empty($filters['from_date'])) {
            $clauses[] = 'lr.start_date >= :from_date';
            $params['from_date'] = $filters['from_date'];
        }
        if (!empty($filters['to_date'])) {
            $clauses[] = 'lr.end_date <= :to_date';
            $params['to_date'] = $filters['to_date'];
        }
        if (!empty($filters['q'])) {
            $clauses[] = '(e.first_name LIKE :q1 OR e.last_name LIKE :q2 OR e.employee_code LIKE :q3 OR lr.reason LIKE :q4)';
            $like = '%' . $filters['q'] . '%';
            $params['q1'] = $like;
            $params['q2'] = $like;
            $params['q3'] = $like;
            $params['q4'] = $like;
        }

        $where = implode(' AND ', $clauses);
        $offset = (max(1, $page) - 1) * $perPage;
        $orderBy = !empty($filters['archived']) ? 'lr.deleted_at DESC' : 'lr.id DESC';

        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             WHERE {$where}",
            $params
        );

        $rows = $this->db->fetchAll(
            "SELECT lr.*, lt.name AS leave_type_name, lt.color AS leave_type_color,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.employee_code,
                    au.name AS archived_by_name
             FROM leave_requests lr
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             INNER JOIN employees e ON e.id = lr.employee_id
             LEFT JOIN users au ON au.id = lr.deleted_by
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

    public function getAmendments(int $leaveRequestId): array
    {
        return $this->db->fetchAll(
            'SELECT la.*, u.name AS changed_by_name
             FROM leave_amendments la
             LEFT JOIN users u ON u.id = la.changed_by
             WHERE la.leave_request_id = :id
             ORDER BY la.id DESC',
            ['id' => $leaveRequestId]
        );
    }

    public function getApprovals(int $leaveRequestId): array
    {
        return $this->db->fetchAll(
            'SELECT la.*, u.name AS approver_name, u.email AS approver_email
             FROM leave_approvals la
             INNER JOIN users u ON u.id = la.approver_id
             WHERE la.leave_request_id = :id
             ORDER BY la.level ASC',
            ['id' => $leaveRequestId]
        );
    }

    public function getExtensions(int $leaveRequestId): array
    {
        return $this->db->fetchAll(
            'SELECT le.*, u.name AS reviewer_name
             FROM leave_extensions le
             LEFT JOIN users u ON u.id = le.reviewed_by
             WHERE le.leave_request_id = :id AND le.deleted_at IS NULL
             ORDER BY le.id DESC',
            ['id' => $leaveRequestId]
        );
    }
}
