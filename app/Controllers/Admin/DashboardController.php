<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->authorize('dashboard.view');
        $db = Database::getInstance();

        $stats = [
            'employees_total'   => $this->tenantCount($db, 'employees', 'deleted_at IS NULL'),
            'employees_active'  => $this->tenantCount($db, 'employees', 'employment_status = "active" AND deleted_at IS NULL'),
            'present_today'     => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status IN ("present","late","remote","manual") AND deleted_at IS NULL'),
            'on_leave_today'    => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "on_leave" AND deleted_at IS NULL'),
            'absent_today'      => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "absent" AND deleted_at IS NULL'),
            'remote_today'      => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "remote" AND deleted_at IS NULL'),
            'pending_leaves'    => $this->tenantCount($db, 'leave_requests', 'status = "pending" AND deleted_at IS NULL'),
            'departments'       => $this->tenantCount($db, 'departments', 'is_active = 1 AND deleted_at IS NULL'),
            'branches'          => $this->tenantCount($db, 'branches', 'is_active = 1 AND deleted_at IS NULL'),
            'late_today'        => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "late" AND deleted_at IS NULL'),
            'missing_checkout'  => $this->tenantCount($db, 'attendance', 'status = "missing_checkout" AND deleted_at IS NULL'),
        ];

        $attendanceScope = $this->tenant->sql('company_id', 'dashboard_attendance');
        $attendanceTrend = $db->fetchAll(
            'SELECT attendance_date AS dt,
                    SUM(CASE WHEN status IN ("present","late","remote","manual") THEN 1 ELSE 0 END) AS present_count,
                    SUM(CASE WHEN status = "absent" THEN 1 ELSE 0 END) AS absent_count,
                    SUM(CASE WHEN status = "late" THEN 1 ELSE 0 END) AS late_count,
                    SUM(CASE WHEN status = "missing_checkout" THEN 1 ELSE 0 END) AS missing_checkout_count
             FROM attendance
             WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) AND deleted_at IS NULL
               AND (' . $attendanceScope['sql'] . ')
             GROUP BY attendance_date ORDER BY attendance_date',
            $attendanceScope['params']
        );

        $exceptionScope = $this->tenant->sql('a.company_id', 'dashboard_exception');
        $attendanceExceptions = $db->fetchAll(
            'SELECT a.id, a.attendance_date, a.status, a.check_in_at, a.check_out_at,
                    e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                    d.name AS department_name
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE a.deleted_at IS NULL
               AND a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
               AND a.status IN ("late", "missing_checkout")
               AND (' . $exceptionScope['sql'] . ')
             ORDER BY a.attendance_date DESC, a.id DESC LIMIT 8',
            $exceptionScope['params']
        );

        $departmentScope = $this->tenant->sql('d.company_id', 'dashboard_department');
        $departmentHeadcount = $db->fetchAll(
            'SELECT d.name, COUNT(e.id) AS total
             FROM departments d
             LEFT JOIN employees e ON e.department_id = d.id AND e.deleted_at IS NULL AND e.employment_status = "active"
             WHERE d.deleted_at IS NULL AND d.is_active = 1 AND (' . $departmentScope['sql'] . ')
             GROUP BY d.id, d.name ORDER BY total DESC LIMIT 8',
            $departmentScope['params']
        );

        // Employees currently on leave today
        $onLeaveTodayScope = $this->tenant->sql('a.company_id', 'dashboard_on_leave_today');
        $onLeaveToday = $db->fetchAll(
            'SELECT e.id AS employee_id, e.first_name, e.last_name, e.employee_code,
                    d.name AS department_name, lt.name AS leave_type_name,
                    a.attendance_date
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN leave_requests lr ON lr.employee_id = a.employee_id
                AND lr.start_date <= CURDATE() AND lr.end_date >= CURDATE()
                AND lr.status = "approved" AND lr.deleted_at IS NULL
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE a.attendance_date = CURDATE() AND a.status = "on_leave"
               AND a.deleted_at IS NULL AND (' . $onLeaveTodayScope['sql'] . ')
             ORDER BY e.first_name ASC LIMIT 10',
            $onLeaveTodayScope['params']
        );

        // Employees absent OR no check-in today (active employees with absent record or no record at all)
        $noShowScope = $this->tenant->sql('e.company_id', 'dashboard_no_show');
        $noShowToday = $db->fetchAll(
            'SELECT e.id AS employee_id, e.first_name, e.last_name, e.employee_code,
                    d.name AS department_name,
                    COALESCE(a.status, "no_checkin") AS status_label
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN attendance a ON a.employee_id = e.id
                AND a.attendance_date = CURDATE() AND a.deleted_at IS NULL
             WHERE e.deleted_at IS NULL AND e.employment_status = "active"
               AND (a.status = "absent" OR a.id IS NULL)
               AND (' . $noShowScope['sql'] . ')
             ORDER BY e.first_name ASC LIMIT 12',
            $noShowScope['params']
        );

        // Recently joined employees (last 60 days)
        $recentJoinScope = $this->tenant->sql('e.company_id', 'dashboard_recent_join');
        $recentJoiners = $db->fetchAll(
            'SELECT e.id, e.first_name, e.last_name, e.employee_code, e.joining_date,
                    d.name AS department_name, ds.name AS designation_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN designations ds ON ds.id = e.designation_id
             WHERE e.deleted_at IS NULL
               AND e.joining_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY)
               AND e.joining_date <= CURDATE()
               AND (' . $recentJoinScope['sql'] . ')
             ORDER BY e.joining_date DESC, e.id DESC LIMIT 8',
            $recentJoinScope['params']
        );

        // Recent leave requests
        $leaveScope = $this->tenant->sql('lr.company_id', 'dashboard_leave');
        $recentLeaves = $db->fetchAll(
            'SELECT lr.*, e.first_name, e.last_name, e.employee_code, lt.name AS leave_type_name
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.deleted_at IS NULL AND (' . $leaveScope['sql'] . ')
             ORDER BY lr.created_at DESC LIMIT 8',
            $leaveScope['params']
        );

        $role = (string) ($this->user()['primary_role'] ?? 'company_admin');
        $profiles = [
            'super_admin' => ['eyebrow' => 'System overview', 'subtitle' => 'Company readiness, workforce health, approvals, and payroll signals'],
            'company_admin' => ['eyebrow' => 'Company operations', 'subtitle' => 'Workforce health, approvals, attendance exceptions, and payroll readiness'],
            'hr_manager' => ['eyebrow' => 'People operations', 'subtitle' => 'New hires, employee changes, leave approvals, and attendance exceptions'],
            'department_manager' => ['eyebrow' => 'Team workspace', 'subtitle' => 'Your team’s attendance, leave requests, and action items'],
            'accountant' => ['eyebrow' => 'Payroll workspace', 'subtitle' => 'Payroll periods, employee variances, loans, advances, and payslips'],
        ];
        $dashboardProfile = $profiles[$role] ?? $profiles['company_admin'];
        $setup = null;
        if ($this->auth->can('settings.manage') && $db->tableExists('company_setup_progress')) {
            $companyId = $this->tenant->resolveCompanyId();
            if (!$companyId) $companyId = (int) (($this->tenant->companies(true)[0]['id'] ?? 0));
            if ($companyId) $setup = (new \App\Services\ProductSetupService())->checklist($companyId);
        }

        $this->view('admin/dashboard/index', [
            'title'               => 'Dashboard',
            'stats'               => $stats,
            'attendanceTrend'     => $attendanceTrend,
            'attendanceExceptions' => $attendanceExceptions,
            'departmentHeadcount' => $departmentHeadcount,
            'recentLeaves'        => $recentLeaves,
            'onLeaveToday'  => $onLeaveToday,
            'noShowToday'   => $noShowToday,
            'recentJoiners' => $recentJoiners,
            'dashboardProfile'    => $dashboardProfile,
            'setup'               => $setup,
            'role'                => $role,
        ]);
    }

    public function stats(): void
    {
        $this->authorize('dashboard.view');
        $db = Database::getInstance();
        $this->jsonSuccess('Dashboard statistics', [
            'employees_total' => $this->tenantCount($db, 'employees', 'deleted_at IS NULL'),
            'employees_active' => $this->tenantCount($db, 'employees', 'employment_status = "active" AND deleted_at IS NULL'),
            'present_today' => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status IN ("present","late","remote","manual") AND deleted_at IS NULL'),
            'pending_leaves' => $this->tenantCount($db, 'leave_requests', 'status = "pending" AND deleted_at IS NULL'),
            'payroll_pending' => $this->tenantCount($db, 'payroll_periods', 'status IN ("draft","processing","calculated") AND deleted_at IS NULL'),
            'late_today' => $this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "late" AND deleted_at IS NULL'),
            'missing_checkout' => $this->tenantCount($db, 'attendance', 'status = "missing_checkout" AND deleted_at IS NULL'),
        ]);
    }

    private function tenantCount(Database $db, string $table, string $condition): int
    {
        $allowed = ['employees', 'attendance', 'leave_requests', 'departments', 'branches', 'payroll_periods'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported dashboard table.');
        }
        $scope = $this->tenant->sql('company_id', 'dashboard_count_' . $table);
        return (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $condition . ' AND (' . $scope['sql'] . ')',
            $scope['params']
        );
    }
}
