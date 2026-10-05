<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class QrPunchController extends Controller
{
    private Database $db;

    public function __construct(
        \App\Core\Request $request,
        \App\Core\Response $response
    ) {
        parent::__construct($request, $response);
        $this->db = Database::getInstance();
    }

    // GET /employee/qr-punch?token=XXX
    public function show(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $token = trim((string) $this->request->input('token', ''));
        $qr    = $this->resolveToken($token);

        if (!$qr) {
            $this->view('employee/attendance/qr-punch', [
                'title'  => 'QR Check-In',
                'error'  => 'This QR code is invalid or has been deactivated.',
                'qr'     => null,
                'today'  => null,
            ], 'layouts/employee');
            return;
        }

        $today = date('Y-m-d');
        $att   = $this->db->fetch(
            'SELECT * FROM attendance WHERE employee_id = :eid AND attendance_date = :dt AND deleted_at IS NULL LIMIT 1',
            ['eid' => $employee['id'], 'dt' => $today]
        );

        $this->view('employee/attendance/qr-punch', [
            'title'    => 'QR Check-In / Out — ' . $qr['name'],
            'qr'       => $qr,
            'token'    => $token,
            'employee' => $employee,
            'att'      => $att,
            'today'    => $today,
        ], 'layouts/employee');
    }

    // POST /employee/qr-punch
    public function punch(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $token  = trim((string) $this->request->input('token', ''));
        $action = trim((string) $this->request->input('action', 'check_in'));
        $qr     = $this->resolveToken($token);

        if (!$qr) {
            flash('error', 'Invalid or expired QR code.');
            $this->redirect('/employee/attendance');
        }

        $companyId   = (int) $employee['company_id'];
        $employeeId  = (int) $employee['id'];
        $today       = date('Y-m-d');
        $now         = date('Y-m-d H:i:s');
        $locationNote = $qr['name'] . ($qr['location'] ? ' (' . $qr['location'] . ')' : '');

        if ($action === 'check_in') {
            $existing = $this->db->fetch(
                'SELECT id FROM attendance WHERE employee_id = :eid AND attendance_date = :dt AND deleted_at IS NULL LIMIT 1',
                ['eid' => $employeeId, 'dt' => $today]
            );
            if ($existing) {
                flash('warning', 'You are already checked in today.');
                $this->redirect('/employee/qr-punch?token=' . urlencode($token));
            }

            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );
            $this->db->insert('attendance', [
                'uuid'                => $uuid,
                'company_id'          => $companyId,
                'employee_id'         => $employeeId,
                'attendance_date'     => $today,
                'check_in_at'         => $now,
                'source'              => 'kiosk',
                'status'              => 'present',
                'verification_status' => 'auto_verified',
                'remarks'             => 'QR scan: ' . $locationNote,
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
            flash('success', 'Check-in recorded at ' . date('h:i A') . ' via QR scan.');
        } elseif ($action === 'check_out') {
            $att = $this->db->fetch(
                'SELECT id, check_in_at FROM attendance WHERE employee_id = :eid AND attendance_date = :dt AND check_out_at IS NULL AND deleted_at IS NULL LIMIT 1',
                ['eid' => $employeeId, 'dt' => $today]
            );
            if (!$att) {
                flash('error', 'No active check-in found for today.');
                $this->redirect('/employee/qr-punch?token=' . urlencode($token));
            }
            $workMins = $att['check_in_at']
                ? (int) round((strtotime($now) - strtotime($att['check_in_at'])) / 60)
                : 0;
            $this->db->query(
                'UPDATE attendance SET check_out_at = :co, work_minutes = :wm, updated_at = NOW() WHERE id = :id',
                ['co' => $now, 'wm' => $workMins, 'id' => $att['id']]
            );
            flash('success', 'Check-out recorded at ' . date('h:i A') . ' via QR scan.');
        }

        $this->redirect('/employee/attendance');
    }

    private function resolveToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }
        $qr = $this->db->fetch(
            "SELECT * FROM qr_punch_tokens WHERE token = :t AND active = 1 LIMIT 1",
            ['t' => $token]
        );
        return $qr ?: null;
    }
}
