<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

/**
 * Thin workspace landing pages — metrics only, no CRUD changes.
 */
class WorkspaceOverviewController extends Controller
{
    public function attendance(): void
    {
        $this->authorize('attendance.view');
        $db = Database::getInstance();

        $metrics = [
            [
                'label' => 'Present Today',
                'value' => number_format($this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status IN ("present","late","remote","manual") AND deleted_at IS NULL')),
                'sub' => 'Checked in',
                'href' => '/admin/attendance?date=' . date('Y-m-d'),
                'tone' => 'mint',
                'icon' => 'user-check',
            ],
            [
                'label' => 'Absent Today',
                'value' => number_format($this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "absent" AND deleted_at IS NULL')),
                'sub' => 'Marked absent',
                'href' => '/admin/attendance?status=absent&date=' . date('Y-m-d'),
                'tone' => 'red',
                'icon' => 'user-x',
            ],
            [
                'label' => 'Pending Corrections',
                'value' => number_format($this->pendingCorrections($db)),
                'sub' => 'Awaiting review',
                'href' => '/admin/attendance/corrections',
                'tone' => 'orange',
                'icon' => 'clipboard-pen',
            ],
            [
                'label' => 'On Leave Today',
                'value' => number_format($this->tenantCount($db, 'attendance', 'attendance_date = CURDATE() AND status = "on_leave" AND deleted_at IS NULL')),
                'sub' => 'Approved leave',
                'href' => '/admin/leave/approved',
                'tone' => 'blue',
                'icon' => 'calendar-off',
            ],
        ];

        $scope = $this->tenant->sql('a.company_id', 'workspace_attendance');
        $recent = $db->fetchAll(
            'SELECT a.id, a.attendance_date, a.status, a.check_in_at, e.first_name, e.last_name, e.employee_code
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE a.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY a.attendance_date DESC, a.id DESC LIMIT 8',
            $scope['params']
        );

        $this->view('admin/workspace/attendance-overview', [
            'title' => 'Attendance Overview',
            'metrics' => $metrics,
            'recent' => $recent,
        ]);
    }

    public function leave(): void
    {
        $this->authorize('leave.view');
        $db = Database::getInstance();

        $metrics = [
            [
                'label' => 'Pending',
                'value' => number_format($this->tenantCount($db, 'leave_requests', 'status = "pending" AND deleted_at IS NULL')),
                'sub' => 'Need approval',
                'href' => '/admin/leave/pending',
                'tone' => 'orange',
                'icon' => 'hourglass',
            ],
            [
                'label' => 'Approved',
                'value' => number_format($this->tenantCount($db, 'leave_requests', 'status = "approved" AND deleted_at IS NULL AND end_date >= CURDATE()')),
                'sub' => 'Upcoming / active',
                'href' => '/admin/leave/approved',
                'tone' => 'mint',
                'icon' => 'check-circle',
            ],
            [
                'label' => 'On Leave Today',
                'value' => number_format($this->tenantCount($db, 'leave_requests', 'status = "approved" AND deleted_at IS NULL AND start_date <= CURDATE() AND end_date >= CURDATE()')),
                'sub' => 'Currently out',
                'href' => '/admin/leave/approved',
                'tone' => 'blue',
                'icon' => 'calendar-days',
            ],
            [
                'label' => 'Leave Types',
                'value' => number_format($this->tenantCount($db, 'leave_types', 'deleted_at IS NULL')),
                'sub' => 'Configured',
                'href' => '/admin/leave/types',
                'tone' => 'purple',
                'icon' => 'list-tree',
            ],
        ];

        $scope = $this->tenant->sql('lr.company_id', 'workspace_leave');
        $recent = $db->fetchAll(
            'SELECT lr.id, lr.status, lr.start_date, lr.end_date, lr.chargeable_days,
                    e.first_name, e.last_name, e.employee_code, lt.name AS leave_type_name
             FROM leave_requests lr
             INNER JOIN employees e ON e.id = lr.employee_id
             INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY lr.created_at DESC LIMIT 8',
            $scope['params']
        );

        $this->view('admin/workspace/leave-overview', [
            'title' => 'Leave Overview',
            'metrics' => $metrics,
            'recent' => $recent,
        ]);
    }

    public function payroll(): void
    {
        $this->authorize('payroll.view');
        $db = Database::getInstance();

        $metrics = [
            [
                'label' => 'Draft Periods',
                'value' => number_format($this->tenantCount($db, 'payroll_periods', 'status = "draft" AND deleted_at IS NULL')),
                'sub' => 'Not processed',
                'href' => '/admin/payroll?status=draft',
                'tone' => 'purple',
                'icon' => 'file-pen',
            ],
            [
                'label' => 'In Progress',
                'value' => number_format($this->tenantCount($db, 'payroll_periods', 'status IN ("processing","calculated") AND deleted_at IS NULL')),
                'sub' => 'Processing',
                'href' => '/admin/payroll',
                'tone' => 'orange',
                'icon' => 'loader',
            ],
            [
                'label' => 'Approved',
                'value' => number_format($this->tenantCount($db, 'payroll_periods', 'status = "approved" AND deleted_at IS NULL')),
                'sub' => 'Ready to pay',
                'href' => '/admin/payroll?status=approved',
                'tone' => 'mint',
                'icon' => 'badge-check',
            ],
            [
                'label' => 'Open Loans',
                'value' => number_format($this->tenantCount($db, 'loans', 'status IN ("approved","active") AND deleted_at IS NULL')),
                'sub' => 'Active loans',
                'href' => '/admin/loans',
                'tone' => 'blue',
                'icon' => 'landmark',
            ],
        ];

        $scope = $this->tenant->sql('company_id', 'workspace_payroll');
        $recent = $db->fetchAll(
            'SELECT id, period_year, period_month, status, total_net, created_at
             FROM payroll_periods WHERE deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY period_year DESC, period_month DESC LIMIT 8',
            $scope['params']
        );

        $this->view('admin/workspace/payroll-overview', [
            'title' => 'Payroll Overview',
            'metrics' => $metrics,
            'recent' => $recent,
        ]);
    }

    private function tenantCount(Database $db, string $table, string $condition): int
    {
        $allowed = ['attendance', 'leave_requests', 'leave_types', 'payroll_periods', 'loans'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported workspace table.');
        }
        $scope = $this->tenant->sql('company_id', 'workspace_count_' . $table);
        return (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM ' . $table . ' WHERE ' . $condition . ' AND (' . $scope['sql'] . ')',
            $scope['params']
        );
    }

    private function pendingCorrections(Database $db): int
    {
        $scope = $this->tenant->sql('a.company_id', 'workspace_correction');
        return (int) $db->fetchColumn(
            'SELECT COUNT(*) FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id
             WHERE ac.status = "pending" AND ac.deleted_at IS NULL AND (' . $scope['sql'] . ')',
            $scope['params']
        );
    }
}
