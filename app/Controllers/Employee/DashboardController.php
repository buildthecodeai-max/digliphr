<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->authorize('dashboard.view');
        $employee = $this->employee();
        if (!$employee) {
            $this->view('employee/dashboard/index', [
                'title' => 'My Dashboard',
                'employee' => null,
                'stats' => [],
            ], 'layouts/employee');
            return;
        }

        $db = Database::getInstance();
        $employeeId = (int) $employee['id'];
        $userId = (int) ($this->user()['id'] ?? 0);

        $today = $db->fetch(
            'SELECT status, check_in_at, check_out_at, work_minutes, late_minutes, overtime_minutes
             FROM attendance
             WHERE employee_id = :eid AND attendance_date = CURDATE() AND deleted_at IS NULL
             LIMIT 1',
            ['eid' => $employeeId]
        );

        $presentMonth = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM attendance
             WHERE employee_id = :eid AND MONTH(attendance_date) = MONTH(CURDATE())
             AND YEAR(attendance_date) = YEAR(CURDATE())
             AND status IN ("present","late","remote","manual","half_day") AND deleted_at IS NULL',
            ['eid' => $employeeId]
        );
        $workedDays = (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM attendance
             WHERE employee_id = :eid AND MONTH(attendance_date) = MONTH(CURDATE())
             AND YEAR(attendance_date) = YEAR(CURDATE()) AND deleted_at IS NULL
             AND status NOT IN ("weekend","holiday")',
            ['eid' => $employeeId]
        );
        $attendancePct = $workedDays > 0 ? round(($presentMonth / $workedDays) * 100, 1) : 0.0;

        $stats = [
            'today' => $today,
            'present_month' => $presentMonth,
            'attendance_pct' => $attendancePct,
            'leave_balance' => (float) $db->fetchColumn(
                'SELECT COALESCE(SUM(closing_balance), 0) FROM employee_leave_balances
                 WHERE employee_id = :eid AND year = YEAR(CURDATE())',
                ['eid' => $employeeId]
            ),
            'pending_leaves' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM leave_requests WHERE employee_id = :eid AND status = "pending" AND deleted_at IS NULL',
                ['eid' => $employeeId]
            ),
            'last_payslip' => $db->fetch(
                'SELECT pr.id, pr.net_salary, pr.currency, pp.name AS period_name
                 FROM payroll_records pr
                 INNER JOIN payroll_periods pp ON pp.id = pr.payroll_period_id
                 WHERE pr.employee_id = :eid AND pr.deleted_at IS NULL
                 ORDER BY pp.period_year DESC, pp.period_month DESC LIMIT 1',
                ['eid' => $employeeId]
            ),
            'loan_outstanding' => (float) $db->fetchColumn(
                'SELECT COALESCE(SUM(remaining_amount), 0) FROM loans
                 WHERE employee_id = :eid AND status IN ("approved","active") AND deleted_at IS NULL',
                ['eid' => $employeeId]
            ),
            'shared_docs' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM employee_documents
                 WHERE employee_id = :eid AND deleted_at IS NULL',
                ['eid' => $employeeId]
            ),
            'unread_notifications' => (int) $db->fetchColumn(
                'SELECT COUNT(*) FROM notifications WHERE user_id = :uid AND read_at IS NULL',
                ['uid' => $userId]
            ),
        ];

        $attendanceWeek = $db->fetchAll(
            'SELECT attendance_date, status, check_in_at, check_out_at, work_minutes
             FROM attendance
             WHERE employee_id = :eid AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
             AND deleted_at IS NULL ORDER BY attendance_date',
            ['eid' => $employeeId]
        );

        $announcements = $db->fetchAll(
            'SELECT title, body, publish_at FROM announcements
             WHERE is_published = 1 AND (expires_at IS NULL OR expires_at >= NOW())
             AND deleted_at IS NULL ORDER BY publish_at DESC LIMIT 5'
        );

        $holidays = $db->fetchAll(
            'SELECT name, holiday_date FROM holidays
             WHERE holiday_date >= CURDATE() AND deleted_at IS NULL
             ORDER BY holiday_date ASC LIMIT 5'
        );

        $this->view('employee/dashboard/index', [
            'title' => 'My Dashboard',
            'employee' => $employee,
            'stats' => $stats,
            'attendanceWeek' => $attendanceWeek,
            'announcements' => $announcements,
            'holidays' => $holidays,
        ], 'layouts/employee');
    }
}
