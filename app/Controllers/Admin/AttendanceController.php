<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Attendance;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\AttendanceService;
use App\Services\FileUploadService;

class AttendanceController extends Controller
{
    private Attendance $attendance;
    private AttendanceService $attendanceService;
    private FileUploadService $uploader;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->attendance = new Attendance();
        $this->attendanceService = new AttendanceService();
        $this->uploader = new FileUploadService();
    }

    public function index(): void
    {
        $this->authorize('attendance.view');

        $filters = [
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'employee_id' => $this->request->input('employee_id'),
            'branch_id' => $this->request->input('branch_id'),
            'department_id' => $this->request->input('department_id'),
            'shift_id' => $this->request->input('shift_id'),
            'status' => $this->request->input('status'),
            'verification_status' => $this->request->input('verification_status'),
            'date' => $this->request->input('date'),
            'date_from' => $this->request->input('date_from', date('Y-m-01')),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
            'q' => $this->request->input('q'),
        ];

        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $page = max(1, (int) $this->request->input('page', 1));

        $records = $this->attendance->search($filters, $page, 20);
        $stats = $this->attendance->stats($filters);

        $recordIds = array_column($records['data'], 'id');
        $thumbnails = $this->loadCheckInThumbnails($recordIds);

        $this->view('admin/attendance/index', [
            'title' => 'Attendance',
            'records' => $records,
            'stats' => $stats,
            'filters' => $filters,
            'thumbnails' => $thumbnails,
            'departments' => (new Department())->all('name'),
            'branches' => (new Branch())->withCompany(),
            'shifts' => (new Shift())->all('name'),
            'savedFilters' => !empty($filters['company_id']) ? (new \App\Services\SavedFilterService())->forUser((int) $filters['company_id'], (int) $this->auth->id(), 'attendance') : [],
        ]);
    }

    public function show(int $id): void
    {
        $this->authorize('attendance.view');

        $includeArchived = can('attendance.view_archived');
        $record = $this->attendance->findDetailed($id, $includeArchived);
        if (!$record) {
            Session::flash('error', 'Attendance record not found.');
            $this->redirect('/admin/attendance');
            return;
        }
        $this->tenant->assertCompany((int) $record['company_id']);

        $isArchived = !empty($record['deleted_at']);
        if ($isArchived && !can('attendance.view_archived')) {
            Session::flash('error', 'You do not have permission to view archived attendance.');
            $this->redirect('/admin/attendance');
            return;
        }

        $locations = $this->attendance->locations($id);
        foreach ($locations as &$loc) {
            if ($loc['distance_meters'] === null
                && $loc['branch_latitude'] !== null
                && $loc['branch_longitude'] !== null
            ) {
                $dist = haversine_distance(
                    (float) $loc['branch_latitude'],
                    (float) $loc['branch_longitude'],
                    (float) $loc['latitude'],
                    (float) $loc['longitude']
                );
                $loc['distance_meters'] = round($dist, 2);
                $radius = (float) ($loc['branch_radius'] ?? 100);
                $loc['is_within_radius'] = $dist <= $radius ? 1 : 0;
                $loc['distance_computed'] = true;
            }
        }
        unset($loc);

        $this->view('admin/attendance/show', [
            'title' => $isArchived ? 'Archived Attendance' : 'Attendance Details',
            'record' => $record,
            'images' => $this->attendance->images($id),
            'locations' => $locations,
            'corrections' => $this->attendance->corrections($id),
            'auditLogs' => can('attendance.view_history') || can('attendance.view')
                ? $this->attendance->auditLogs($id)
                : [],
            'shifts' => (new Shift())->all('name'),
            'branches' => (new Branch())->all('name'),
            'isArchived' => $isArchived,
        ]);
    }

    public function archived(): void
    {
        $this->authorize('attendance.view_archived');

        $filters = [
            'archived' => 1,
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'employee_id' => $this->request->input('employee_id'),
            'branch_id' => $this->request->input('branch_id'),
            'department_id' => $this->request->input('department_id'),
            'status' => $this->request->input('status'),
            'date_from' => $this->request->input('date_from'),
            'date_to' => $this->request->input('date_to'),
            'archived_by' => $this->request->input('archived_by'),
            'q' => $this->request->input('q'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        $filters['archived'] = 1;
        $page = max(1, (int) $this->request->input('page', 1));

        $records = $this->attendance->search($filters, $page, 20);

        $this->view('admin/attendance/archived', [
            'title' => 'Archived Attendance',
            'records' => $records,
            'filters' => $filters,
            'departments' => (new Department())->all('name'),
            'branches' => (new Branch())->withCompany(),
            'archivists' => $this->attendance->db()->fetchAll(
                'SELECT DISTINCT u.id, u.name
                 FROM attendance a
                 INNER JOIN users u ON u.id = a.deleted_by
                 WHERE a.deleted_at IS NOT NULL
                 ORDER BY u.name ASC'
            ),
        ]);
    }

    public function corrections(): void
    {
        $this->authorize('attendance.correct');

        $page = max(1, (int) $this->request->input('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;
        $status = $this->request->input('status', 'pending');

        $clauses = ['ac.deleted_at IS NULL'];
        $scope = $this->tenant->sql('a.company_id', 'correction_company');
        $clauses[] = $scope['sql'];
        $params = $scope['params'];
        if ($status !== '' && $status !== null) {
            $clauses[] = 'ac.status = :status';
            $params['status'] = $status;
        }
        $where = implode(' AND ', $clauses);

        $total = (int) $this->attendance->db()->fetchColumn(
            "SELECT COUNT(*) FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id WHERE {$where}",
            $params
        );
        $rows = $this->attendance->db()->fetchAll(
            "SELECT ac.*, a.attendance_date, a.status AS attendance_status,
                    CONCAT(e.first_name, ' ', e.last_name) AS employee_name, e.employee_code
             FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id
             INNER JOIN employees e ON e.id = ac.employee_id
             WHERE {$where}
             ORDER BY ac.created_at DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $this->view('admin/attendance/corrections', [
            'title' => 'Correction Requests',
            'records' => [
                'data' => $rows,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'filters' => ['status' => $status],
        ]);
    }

    public function approveCorrection(int $id): void
    {
        $this->authorize('attendance.correct');
        $db = $this->attendance->db();
        $correction = $db->fetch(
            "SELECT ac.*, a.attendance_date FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id WHERE ac.id = :id AND ac.deleted_at IS NULL",
            ['id' => $id]
        );
        if (!$correction || $correction['status'] !== 'pending') {
            flash('error', 'Correction request not found or already processed.');
            $this->redirect('/admin/attendance/corrections');
            return;
        }
        $result = (new AttendanceService())->adminCorrect((int) $correction['attendance_id'], [
            'check_in_at'  => $correction['requested_check_in_at'],
            'check_out_at' => $correction['requested_check_out_at'],
            'reason'       => 'Regularization approved: ' . $correction['reason'],
        ], (int) ($this->user()['id'] ?? 0));
        if ($result['success']) {
            $db->update('attendance_corrections', [
                'status' => 'approved', 'reviewed_by' => $this->user()['id'] ?? null,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => (string) $this->request->input('review_notes', ''),
            ], 'id = :id', ['id' => $id]);
            flash('success', 'Correction approved and applied.');
        } else {
            flash('error', $result['message'] ?? 'Failed to apply correction.');
        }
        $this->redirect('/admin/attendance/corrections');
    }

    public function rejectCorrection(int $id): void
    {
        $this->authorize('attendance.correct');
        $db = $this->attendance->db();
        $correction = $db->fetch('SELECT * FROM attendance_corrections WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$correction || $correction['status'] !== 'pending') {
            flash('error', 'Correction request not found or already processed.');
            $this->redirect('/admin/attendance/corrections');
            return;
        }
        $db->update('attendance_corrections', [
            'status' => 'rejected', 'reviewed_by' => $this->user()['id'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => (string) $this->request->input('review_notes', 'Rejected'),
        ], 'id = :id', ['id' => $id]);
        flash('success', 'Correction request rejected.');
        $this->redirect('/admin/attendance/corrections');
    }

    public function deleteCorrection(int $id): void
    {
        $this->authorize('attendance.correct');
        $db = $this->attendance->db();
        $correction = $db->fetch('SELECT * FROM attendance_corrections WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$correction) {
            flash('error', 'Correction request not found.');
            $this->redirect('/admin/attendance/corrections');
            return;
        }
        $db->update('attendance_corrections', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        flash('success', 'Correction request deleted.');
        $this->redirect('/admin/attendance/corrections');
    }

    public function restore(int $id): void
    {
        $this->authorize('attendance.restore');
        $this->assertAttendanceAccess($id, true);

        $result = $this->attendanceService->restore(
            $id,
            $this->user()['id'] ?? null,
            $this->request->input('reason')
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect($result['success'] ? '/admin/attendance/' . $id : '/admin/attendance/archived');
    }

    public function forceDestroy(int $id = 0): void
    {
        $this->authorize('attendance.permanently_delete');

        if ($id <= 0) {
            $id = (int) $this->request->input('attendance_id', 0);
        }
        $this->assertAttendanceAccess($id, true);
        if ($id <= 0) {
            Session::flash('error', 'Unable to determine which attendance record to delete.');
            $this->redirect('/admin/attendance/archived');
            return;
        }

        $reason = trim((string) $this->request->input('reason', ''));
        $result = $this->attendanceService->permanentlyDelete($id, $reason, $this->user()['id'] ?? null);

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/attendance/archived');
    }

    public function approve(int $id): void
    {
        $this->authorize('attendance.approve');
        $this->assertAttendanceAccess($id);

        $result = $this->attendanceService->approve(
            (int) $id,
            $this->user()['id'] ?? null,
            $this->request->input('admin_notes')
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/attendance/' . $id);
    }

    public function reject(int $id): void
    {
        $this->authorize('attendance.reject');
        $this->assertAttendanceAccess($id);

        $result = $this->attendanceService->reject(
            (int) $id,
            $this->user()['id'] ?? null,
            $this->request->input('admin_notes')
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/attendance/' . $id);
    }

    public function correct(int $id): void
    {
        $this->authorize('attendance.correct');
        $this->assertAttendanceAccess($id);

        if ($this->request->isMethod('GET')) {
            $record = $this->attendance->findDetailed((int) $id);
            if (!$record) {
                Session::flash('error', 'Attendance record not found.');
                $this->redirect('/admin/attendance');
            }

            $this->view('admin/attendance/show', [
                'title' => 'Correct Attendance',
                'record' => $record,
                'images' => $this->attendance->images((int) $id),
                'locations' => $this->attendance->locations((int) $id),
                'corrections' => $this->attendance->corrections((int) $id),
                'auditLogs' => $this->attendance->auditLogs((int) $id),
                'correctMode' => true,
            ]);
            return;
        }

        $result = $this->attendanceService->adminCorrect(
            (int) $id,
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/attendance/' . $id);
    }

    public function manual(): void
    {
        $this->authorize('attendance.manual');

        if ($this->request->isMethod('GET')) {
            $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
            $this->view('admin/attendance/index', [
                'title' => 'Manual Attendance',
                'records' => ['data' => [], 'total' => 0, 'current_page' => 1, 'last_page' => 1, 'per_page' => 20],
                'stats' => [],
                'filters' => [],
                'thumbnails' => [],
                'departments' => (new Department())->all('name'),
                'branches' => (new Branch())->withCompany(),
                'shifts' => (new Shift())->all('name'),
                'employees' => $companyId
                    ? (new Employee())->where(['employment_status' => 'active', 'company_id' => $companyId], 'first_name')
                    : (new Employee())->where(['employment_status' => 'active'], 'first_name'),
                'manualMode' => true,
            ]);
            return;
        }
        $this->tenant->employee((int) $this->request->input('employee_id', 0));

        $result = $this->attendanceService->manualEntry(
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect($result['success'] ? '/admin/attendance/' . ($result['data']['attendance']['id'] ?? '') : '/admin/attendance/manual');
    }

    public function destroy(int $id): void
    {
        $this->authorize('attendance.archive');
        $this->assertAttendanceAccess($id);

        $reason = trim((string) $this->request->input('reason', ''));
        $result = $this->attendanceService->softDelete($id, $reason, $this->user()['id'] ?? null);

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect($result['success'] ? '/admin/attendance' : '/admin/attendance/' . $id);
    }

    public function update(int $id): void
    {
        $this->authorize('attendance.edit');
        $this->assertAttendanceAccess($id);

        $result = $this->attendanceService->adminUpdate(
            $id,
            $this->request->all(),
            $this->user()['id'] ?? null
        );

        if ($this->request->wantsJson()) {
            $result['success']
                ? $this->jsonSuccess($result['message'], $result['data'] ?? null)
                : $this->jsonError($result['message'], $result['errors'] ?? null, 422);
        }

        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/attendance/' . $id);
    }

    public function export(): void
    {
        $this->authorize('attendance.export');

        $filters = [
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'date_from' => $this->request->input('date_from', date('Y-m-01')),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
            'branch_id' => $this->request->input('branch_id'),
            'department_id' => $this->request->input('department_id'),
            'shift_id' => $this->request->input('shift_id'),
            'status' => $this->request->input('status'),
            'verification_status' => $this->request->input('verification_status'),
            'q' => $this->request->input('q'),
            'archived' => $this->request->input('archived'),
        ];
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '');
        if (!empty($filters['archived'])) {
            $filters['archived'] = 1;
        }

        $rows = $this->attendance->search($filters, 1, 10000)['data'];

        $exportDir = config('app.paths.exports');
        if (!is_dir($exportDir)) {
            mkdir($exportDir, 0755, true);
        }

        $filename = 'attendance_' . date('Ymd_His') . '.csv';
        $path = rtrim($exportDir, '/') . '/' . $filename;

        $fp = fopen($path, 'w');
        fputcsv($fp, [
            'Date', 'Employee Code', 'Employee Name', 'Department', 'Branch', 'Shift',
            'Check In', 'Check Out', 'Status', 'Verification', 'Work Minutes',
            'Late Minutes', 'Overtime Minutes', 'Remote', 'Manual',
        ], ',', '"', '\\');

        foreach ($rows as $row) {
            fputcsv($fp, array_map('csv_safe', [
                $row['attendance_date'],
                $row['employee_code'],
                $row['employee_name'],
                $row['department_name'] ?? '',
                $row['branch_name'] ?? '',
                $row['shift_name'] ?? '',
                $row['check_in_at'] ?? '',
                $row['check_out_at'] ?? '',
                $row['status'],
                $row['verification_status'],
                $row['work_minutes'],
                $row['late_minutes'],
                $row['overtime_minutes'],
                $row['is_remote'] ? 'Yes' : 'No',
                $row['is_manual'] ? 'Yes' : 'No',
            ]), ',', '"', '\\');
        }

        fclose($fp);
        $this->response->download($path, $filename, 'text/csv');
    }

    public function image(int $id): void
    {
        $this->authorize('attendance.images');

        $image = $this->attendance->db()->fetch(
            'SELECT ai.*, a.employee_id FROM attendance_images ai
             INNER JOIN attendance a ON a.id = ai.attendance_id
             WHERE ai.id = :id AND ai.deleted_at IS NULL LIMIT 1',
            ['id' => (int) $id]
        );

        if (!$image) {
            http_response_code(404);
            exit('Image not found.');
        }
        $this->tenant->employee((int) $image['employee_id']);

        $fullPath = $this->uploader->resolveFullPath($image['path']);
        if (!is_file($fullPath)) {
            http_response_code(404);
            exit('Image file not found.');
        }

        $this->response->file($fullPath, $image['mime_type'] ?? 'image/jpeg');
    }

    /**
     * @param array<int|string> $attendanceIds
     * @return array<int, array<string, mixed>>
     */
    private function loadCheckInThumbnails(array $attendanceIds): array
    {
        if (empty($attendanceIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($attendanceIds), '?'));
        $rows = $this->attendance->db()->fetchAll(
            "SELECT id, attendance_id, path, mime_type FROM attendance_images
             WHERE attendance_id IN ({$placeholders}) AND type = 'check_in' AND deleted_at IS NULL
             ORDER BY created_at ASC",
            array_values($attendanceIds)
        );

        $map = [];
        foreach ($rows as $row) {
            $aid = (int) $row['attendance_id'];
            if (!isset($map[$aid])) {
                $map[$aid] = $row;
            }
        }

        return $map;
    }

    private function assertAttendanceAccess(int $id, bool $includingArchived = false): array
    {
        $sql = 'SELECT * FROM attendance WHERE id = :id';
        if (!$includingArchived) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $record = $this->attendance->db()->fetch($sql . ' LIMIT 1', ['id' => $id]);
        if (!$record) {
            throw new \App\Exceptions\HttpException('Attendance record not found.', 404);
        }
        $this->tenant->assertCompany((int) $record['company_id']);
        return $record;
    }
}
