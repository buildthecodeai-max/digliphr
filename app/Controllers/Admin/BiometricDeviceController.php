<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

class BiometricDeviceController extends Controller
{
    private Database $db;

    public function __construct(
        \App\Core\Request $request,
        \App\Core\Response $response
    ) {
        parent::__construct($request, $response);
        $this->db = Database::getInstance();
    }

    public function index(): void
    {
        $companyId = $this->tenant->companyId();
        $devices   = $this->db->fetchAll(
            "SELECT * FROM biometric_devices WHERE company_id = :cid AND deleted_at IS NULL ORDER BY name",
            ['cid' => $companyId]
        );
        $unmatched = (int) ($this->db->fetch(
            "SELECT COUNT(*) AS n FROM biometric_device_logs WHERE company_id = :cid AND status = 'unmatched'",
            ['cid' => $companyId]
        )['n'] ?? 0);

        $this->view('admin/attendance/biometric-devices', [
            'title'     => 'Biometric Devices',
            'devices'   => $devices,
            'unmatched' => $unmatched,
        ]);
    }

    public function create(): void
    {
        $this->view('admin/attendance/biometric-device-form', [
            'title'  => 'Register Device',
            'device' => null,
        ]);
    }

    public function store(): void
    {
        $companyId = $this->tenant->companyId();
        $data = $this->validate([
            'serial_number' => 'required|max:100',
            'name'          => 'required|max:100',
            'location'      => 'nullable|max:200',
            'device_type'   => 'required',
        ]);

        $this->db->insert('biometric_devices', [
            'company_id'    => $companyId,
            'serial_number' => trim($data['serial_number']),
            'name'          => trim($data['name']),
            'location'      => $data['location'] ?? null,
            'device_type'   => $data['device_type'],
            'status'        => 'active',
        ]);

        flash('success', 'Device registered. Configure it to push to /iclock/cdata on this server.');
        $this->redirect('/admin/attendance/devices');
    }

    public function edit(int $id): void
    {
        $device = $this->findDevice($id);
        $this->view('admin/attendance/biometric-device-form', [
            'title'  => 'Edit Device',
            'device' => $device,
        ]);
    }

    public function update(int $id): void
    {
        $device = $this->findDevice($id);
        $data   = $this->validate([
            'name'        => 'required|max:100',
            'location'    => 'nullable|max:200',
            'device_type' => 'required',
            'status'      => 'required',
        ]);

        $this->db->query(
            'UPDATE biometric_devices SET name = :n, location = :l, device_type = :dt, status = :s, updated_at = NOW() WHERE id = :id',
            ['n' => $data['name'], 'l' => $data['location'] ?? null, 'dt' => $data['device_type'], 's' => $data['status'], 'id' => $device['id']]
        );

        flash('success', 'Device updated.');
        $this->redirect('/admin/attendance/devices');
    }

    public function destroy(int $id): void
    {
        $device = $this->findDevice($id);
        $this->db->query(
            'UPDATE biometric_devices SET deleted_at = NOW() WHERE id = :id',
            ['id' => $device['id']]
        );
        flash('success', 'Device removed.');
        $this->redirect('/admin/attendance/devices');
    }

    public function logs(int $id): void
    {
        $device = $this->findDevice($id);
        $logs   = $this->db->fetchAll(
            "SELECT l.*, CONCAT(e.first_name,' ',e.last_name) AS employee_name
             FROM biometric_device_logs l
             LEFT JOIN employees e ON e.id = l.employee_id
             WHERE l.device_id = :did ORDER BY l.punch_time DESC LIMIT 200",
            ['did' => $device['id']]
        );
        $this->view('admin/attendance/biometric-device-logs', [
            'title'  => $device['name'] . ' — Logs',
            'device' => $device,
            'logs'   => $logs,
        ]);
    }

    public function unmatchedLogs(): void
    {
        $companyId = $this->tenant->companyId();
        $logs = $this->db->fetchAll(
            "SELECT l.*, d.name AS device_name FROM biometric_device_logs l
             LEFT JOIN biometric_devices d ON d.id = l.device_id
             WHERE l.company_id = :cid AND l.status = 'unmatched' ORDER BY l.punch_time DESC LIMIT 200",
            ['cid' => $companyId]
        );
        $employees = $this->db->fetchAll(
            "SELECT id, CONCAT(first_name,' ',last_name) AS name, employee_code FROM employees
             WHERE company_id = :cid AND deleted_at IS NULL ORDER BY first_name",
            ['cid' => $companyId]
        );
        $this->view('admin/attendance/biometric-unmatched', [
            'title'     => 'Unmatched Punches',
            'logs'      => $logs,
            'employees' => $employees,
        ]);
    }

    public function assignPin(): void
    {
        $companyId  = $this->tenant->companyId();
        $employeeId = (int) $this->request->input('employee_id');
        $pin        = trim((string) $this->request->input('device_pin'));

        if (!$employeeId || $pin === '') {
            flash('error', 'Employee and PIN are required.');
            $this->redirect('/admin/attendance/devices/unmatched');
        }

        $emp = $this->db->fetch(
            'SELECT id FROM employees WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $employeeId, 'cid' => $companyId]
        );
        if (!$emp) {
            flash('error', 'Employee not found.');
            $this->redirect('/admin/attendance/devices/unmatched');
        }

        $this->db->query(
            'UPDATE employees SET device_pin = :pin WHERE id = :id',
            ['pin' => $pin, 'id' => $employeeId]
        );

        // Retry unmatched logs with this PIN
        $this->db->query(
            "UPDATE biometric_device_logs SET employee_id = :eid, status = 'matched'
             WHERE company_id = :cid AND device_pin = :pin AND status = 'unmatched'",
            ['eid' => $employeeId, 'cid' => $companyId, 'pin' => $pin]
        );

        flash('success', 'PIN assigned. Unmatched punches re-linked to employee.');
        $this->redirect('/admin/attendance/devices/unmatched');
    }

    // ─── QR Punch Tokens ─────────────────────────────────────────────────────

    public function qrCodes(): void
    {
        $companyId = $this->tenant->companyId();
        $tokens    = $this->db->fetchAll(
            'SELECT * FROM qr_punch_tokens WHERE company_id = :cid ORDER BY created_at DESC',
            ['cid' => $companyId]
        );
        $this->view('admin/attendance/qr-codes', [
            'title'  => 'QR Punch Codes',
            'tokens' => $tokens,
        ]);
    }

    public function generateQr(): void
    {
        $companyId = $this->tenant->companyId();
        $name      = trim((string) $this->request->input('name', 'Main Office'));
        $location  = trim((string) $this->request->input('location', ''));

        $token = bin2hex(random_bytes(24));

        $this->db->insert('qr_punch_tokens', [
            'company_id' => $companyId,
            'token'      => $token,
            'name'       => $name ?: 'Main Office',
            'location'   => $location ?: null,
            'active'     => 1,
            'created_by' => $this->user()['id'] ?? null,
        ]);

        flash('success', 'QR code generated.');
        $this->redirect('/admin/attendance/qr-codes');
    }

    public function deleteQr(int $id): void
    {
        $companyId = $this->tenant->companyId();
        $this->db->query(
            'DELETE FROM qr_punch_tokens WHERE id = :id AND company_id = :cid',
            ['id' => $id, 'cid' => $companyId]
        );
        flash('success', 'QR code deleted.');
        $this->redirect('/admin/attendance/qr-codes');
    }

    // ─── helpers ─────────────────────────────────────────────────────────────

    private function findDevice(int $id): array
    {
        $companyId = $this->tenant->companyId();
        $d = $this->db->fetch(
            'SELECT * FROM biometric_devices WHERE id = :id AND company_id = :cid AND deleted_at IS NULL',
            ['id' => $id, 'cid' => $companyId]
        );
        if (!$d) {
            throw new \App\Exceptions\HttpException('Device not found.', 404);
        }
        return $d;
    }
}
