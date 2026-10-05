<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

final class ApprovalInboxService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function pending(int $companyId, ?string $module = null): array
    {
        $items = [];
        if (!$module || $module === 'leave') {
            foreach ($this->db->fetchAll('SELECT lr.id, lr.employee_id, lr.created_at, lr.start_date, lr.end_date, lr.chargeable_days, CONCAT(e.first_name," ",e.last_name) employee_name, e.employee_code, lt.name detail FROM leave_requests lr INNER JOIN employees e ON e.id=lr.employee_id INNER JOIN leave_types lt ON lt.id=lr.leave_type_id WHERE lr.company_id=:cid AND lr.status="pending" AND lr.deleted_at IS NULL', ['cid' => $companyId]) as $row) {
                $items[] = $this->item('leave', 'Leave request', $row, '/admin/leave/' . $row['id'], $row['start_date'] . ' — ' . $row['end_date'] . ' · ' . $row['detail']);
            }
        }
        if (!$module || $module === 'attendance') {
            foreach ($this->db->fetchAll('SELECT ac.id, ac.employee_id, ac.created_at, ac.reason detail, CONCAT(e.first_name," ",e.last_name) employee_name, e.employee_code, a.attendance_date FROM attendance_corrections ac INNER JOIN attendance a ON a.id=ac.attendance_id INNER JOIN employees e ON e.id=ac.employee_id WHERE a.company_id=:cid AND ac.status="pending" AND ac.deleted_at IS NULL', ['cid' => $companyId]) as $row) {
                $items[] = $this->item('attendance', 'Attendance correction', $row, '/admin/attendance/corrections?correction_id=' . $row['id'], $row['attendance_date'] . ' · ' . $row['detail']);
            }
        }
        if (!$module || $module === 'overtime') {
            foreach ($this->db->fetchAll('SELECT o.id, o.employee_id, o.created_at, o.overtime_date, o.requested_minutes, o.reason detail, CONCAT(e.first_name," ",e.last_name) employee_name, e.employee_code FROM overtime_requests o INNER JOIN employees e ON e.id=o.employee_id WHERE o.company_id=:cid AND o.status="pending" AND o.deleted_at IS NULL', ['cid' => $companyId]) as $row) {
                $items[] = $this->item('overtime', 'Overtime request', $row, '/admin/overtime#overtime-' . $row['id'], $row['overtime_date'] . ' · ' . round($row['requested_minutes'] / 60, 1) . ' hours');
            }
        }
        if (!$module || $module === 'loan') {
            foreach ($this->db->fetchAll('SELECT l.id, l.employee_id, l.created_at, l.principal_amount, l.currency, l.reason detail, CONCAT(e.first_name," ",e.last_name) employee_name, e.employee_code FROM loans l INNER JOIN employees e ON e.id=l.employee_id WHERE l.company_id=:cid AND l.status="pending" AND l.deleted_at IS NULL', ['cid' => $companyId]) as $row) {
                $items[] = $this->item('loan', 'Loan request', $row, '/admin/loans#loan-' . $row['id'], format_money($row['principal_amount'], $row['currency']) . ' · ' . ($row['detail'] ?: 'No reason supplied'));
            }
        }
        if (!$module || $module === 'payroll') {
            foreach ($this->db->fetchAll('SELECT id, created_at, name, total_net, total_employees, currency FROM (SELECT pp.*, c.currency FROM payroll_periods pp INNER JOIN companies c ON c.id=pp.company_id WHERE pp.company_id=:cid AND pp.status="calculated" AND pp.deleted_at IS NULL) x', ['cid' => $companyId]) as $row) {
                $row['employee_name'] = $row['name']; $row['employee_code'] = $row['total_employees'] . ' employees';
                $items[] = $this->item('payroll', 'Payroll approval', $row, '/admin/payroll/' . $row['id'] . '/variance', format_money($row['total_net'], $row['currency']) . ' net payroll');
            }
        }
        usort($items, static fn (array $a, array $b): int => strcmp($a['created_at'], $b['created_at']));
        return $items;
    }

    private function item(string $module, string $title, array $row, string $url, string $detail): array
    {
        $ageHours = max(0, (int) floor((time() - strtotime((string) $row['created_at'])) / 3600));
        return ['module' => $module, 'title' => $title, 'record_id' => (int) $row['id'], 'employee_id' => isset($row['employee_id']) ? (int) $row['employee_id'] : null, 'employee_name' => $row['employee_name'], 'employee_code' => $row['employee_code'], 'detail' => $detail, 'created_at' => $row['created_at'], 'age_hours' => $ageHours, 'url' => $url];
    }
}
