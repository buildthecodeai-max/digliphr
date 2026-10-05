<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Exceptions\HttpException;
use App\Services\AttendanceSecurityService;

final class AttendanceDeviceController extends Controller
{
    public function show(int $employeeId): void
    {
        $this->authorize('attendance.device.view');
        $employee = $this->employeeRecord($employeeId);
        $service = new AttendanceSecurityService();
        $this->view('admin/employees/attendance-device', [
            'title' => 'Attendance Device',
            'employee' => $employee,
            'devices' => $service->devicesForEmployee($employeeId),
            'securitySettings' => $service->settings((int) $employee['company_id']),
        ]);
    }

    public function approve(int $employeeId, int $deviceId): void
    {
        $this->authorize('attendance.device.approve');
        $this->employeeRecord($employeeId);
        try {
            (new AttendanceSecurityService())->approve($deviceId, $employeeId, (int) $this->auth->id());
            flash('success', 'New attendance device approved. The previous approved device was revoked.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/employees/' . $employeeId . '/attendance-device');
    }

    public function reject(int $employeeId, int $deviceId): void
    {
        $this->authorize('attendance.device.approve');
        $this->employeeRecord($employeeId);
        try {
            (new AttendanceSecurityService())->reject($deviceId, $employeeId, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            flash('success', 'Attendance device request rejected.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/employees/' . $employeeId . '/attendance-device');
    }

    public function revoke(int $employeeId, int $deviceId): void
    {
        $this->authorize('attendance.device.manage');
        $this->employeeRecord($employeeId);
        try {
            (new AttendanceSecurityService())->revoke($deviceId, $employeeId, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            flash('success', 'Attendance device revoked.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/employees/' . $employeeId . '/attendance-device');
    }

    public function reset(int $employeeId): void
    {
        $this->authorize('attendance.device.manage');
        $this->employeeRecord($employeeId);
        try {
            (new AttendanceSecurityService())->reset($employeeId, (int) $this->auth->id(), (string) $this->request->input('reason', ''));
            flash('success', 'Attendance device reset. The next device will follow the configured registration policy.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        $this->redirect('/admin/employees/' . $employeeId . '/attendance-device');
    }

    private function employeeRecord(int $employeeId): array
    {
        $employee = Database::getInstance()->fetch(
            "SELECT e.*, CONCAT(e.first_name, ' ', e.last_name) AS employee_name, c.name AS company_name
             FROM employees e INNER JOIN companies c ON c.id = e.company_id
             WHERE e.id = :id AND e.deleted_at IS NULL LIMIT 1",
            ['id' => $employeeId]
        );
        if (!$employee) {
            throw new HttpException('Employee not found.', 404);
        }
        $this->tenant->assertCompany((int) $employee['company_id']);
        return $employee;
    }
}
