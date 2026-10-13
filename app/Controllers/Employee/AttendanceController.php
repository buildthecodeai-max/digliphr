<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attendance;
use App\Services\AttendanceService;
use App\Services\AttendanceSecurityService;

class AttendanceController extends Controller
{
    private Attendance $attendance;
    private AttendanceService $attendanceService;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->attendance = new Attendance();
        $this->attendanceService = new AttendanceService();
    }

    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile required.', 403);
        }

        $status = $this->attendanceService->todayStatus((int) $employee['id']);
        $history = $this->attendance->search(
            ['employee_id' => $employee['id'], 'date_from' => date('Y-m-01'), 'date_to' => date('Y-m-d')],
            1,
            31
        );
        $securitySettings = (new AttendanceSecurityService())->settings((int) $employee['company_id']);

        $this->view('employee/attendance/index', [
            'title' => 'My Attendance',
            'employee' => $employee,
            'today' => $status['data'] ?? [],
            'history' => $history['data'] ?? [],
            'csrfToken' => Session::csrfToken(),
            'attendanceSecurityMode' => $securitySettings['attendance_security_mode'] ?? 'disabled',
        ], 'layouts/employee');
    }

    public function calendar(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile required.', 403);
        }

        $year = (int) $this->request->input('year', date('Y'));
        $month = (int) $this->request->input('month', date('n'));

        $days = $this->attendance->calendarMonth((int) $employee['id'], $year, $month);
        $stats = $this->attendance->stats([
            'employee_id' => $employee['id'],
            'date_from' => sprintf('%04d-%02d-01', $year, $month),
            'date_to' => date('Y-m-t', strtotime(sprintf('%04d-%02d-01', $year, $month))),
        ]);

        $dayMap = [];
        foreach ($days as $day) {
            $dayMap[$day['attendance_date']] = $day;
        }

        $this->view('employee/attendance/calendar', [
            'title' => 'Attendance Calendar',
            'employee' => $employee,
            'year' => $year,
            'month' => $month,
            'dayMap' => $dayMap,
            'stats' => $stats,
        ], 'layouts/employee');
    }

    public function corrections(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile required.', 403);
        }

        if ($this->request->isMethod('POST')) {
            $result = $this->attendanceService->requestCorrection(
                (int) $employee['id'],
                $this->request->all(),
                $this->user()['id'] ?? null
            );

            if ($this->request->wantsJson()) {
                $result['success']
                    ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                    : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
            }

            Session::flash($result['success'] ? 'success' : 'error', $result['message']);

            if ($result['success']) {
                try {
                    $db = $this->attendance->db();
                    $admins = $db->fetchAll(
                        'SELECT u.id FROM users u
                         INNER JOIN user_roles ur ON ur.user_id = u.id
                         INNER JOIN roles r ON r.id = ur.role_id
                         WHERE r.slug IN ("hr_manager","company_admin","super_admin")
                           AND (ur.company_id = :cid OR r.slug = "super_admin")
                           AND u.is_active = 1 AND u.deleted_at IS NULL',
                        ['cid' => (int) $employee['company_id']]
                    );
                    if (!empty($admins)) {
                        $empName = trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? ''));
                        (new \App\Services\NotificationService())->notifyMany(
                            array_column($admins, 'id'),
                            'New Correction Request',
                            $empName . ' has submitted an attendance correction.',
                            '/admin/attendance/corrections',
                            'attendance'
                        );
                    }
                } catch (\Throwable $e) {}
            }

            $this->redirect('/employee/attendance/corrections');
        }

        $records = $this->attendance->search(
            ['employee_id' => $employee['id'], 'date_from' => date('Y-m-01', strtotime('-3 months'))],
            1,
            50
        );

        $corrections = $this->attendance->db()->fetchAll(
            'SELECT ac.*, a.attendance_date
             FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id
             WHERE ac.employee_id = :eid AND ac.deleted_at IS NULL
             ORDER BY ac.created_at DESC
             LIMIT 50',
            ['eid' => $employee['id']]
        );

        $this->view('employee/attendance/calendar', [
            'title' => 'Attendance Corrections',
            'employee' => $employee,
            'records' => $records['data'] ?? [],
            'corrections' => $corrections,
            'correctionsMode' => true,
            'csrfToken' => Session::csrfToken(),
        ], 'layouts/employee');
    }
}
