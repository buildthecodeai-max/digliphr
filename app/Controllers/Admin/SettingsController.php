<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;
use App\Exceptions\HttpException;
use App\Services\ClientIpService;
use App\Services\AttendanceSecurityService;

class SettingsController extends Controller
{
    public function index(): void
    {
        $attendanceOnly = $this->authorizeSettings();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($companyId) {
            (new AttendanceSecurityService())->settings($companyId);
        }
        $where = $companyId ? ' WHERE company_id = :company_id' : '';
        $params = $companyId ? ['company_id' => $companyId] : [];
        $rows = Database::getInstance()->fetchAll(
            'SELECT id, company_id, group_name, setting_key, setting_value, value_type, description
             FROM system_settings
             ' . $where . '
             ORDER BY group_name, setting_key',
            $params
        );
        $grouped = [];
        foreach ($rows as $row) {
            if ($attendanceOnly && ($row['group_name'] ?? '') !== 'attendance_security') {
                continue;
            }
            if (($row['setting_key'] ?? '') === 'sso_client_secret') {
                $row['setting_value'] = '';
            }
            $grouped[$row['group_name'] ?? 'general'][] = $row;
        }

        $this->view('admin/settings/index', [
            'title' => 'System Settings',
            'grouped' => $grouped,
            'companyId' => $companyId,
        ]);
    }

    public function update(): void
    {
        $attendanceOnly = $this->authorizeSettings();
        $db = Database::getInstance();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        $settings = $this->request->input('settings', []);
        if (!is_array($settings)) {
            flash('error', 'Invalid settings payload.');
            $this->redirect('/admin/settings');
        }

        $previous = [];
        foreach ($settings as $key => $value) {
            $key = (string) $key;
            if ($attendanceOnly && !str_starts_with($key, 'attendance_')) {
                continue;
            }
            $value = $this->validatedAttendanceValue($key, $value);
            $companyWhere = $companyId ? ' AND company_id = :company_id' : '';
            $params = ['k' => $key];
            if ($companyId) {
                $params['company_id'] = $companyId;
            }
            $prev = $db->fetch(
                'SELECT setting_value FROM system_settings WHERE setting_key = :k' . $companyWhere . ' LIMIT 1',
                $params
            );
            $previous[$key] = $prev['setting_value'] ?? null;
            if ($key === 'sso_client_secret') {
                if ((string) $value === '') {
                    continue;
                }
                $value = \App\Services\SecretService::encrypt((string) $value);
            }
            if ($prev) {
                $db->update('system_settings', [
                    'setting_value' => is_array($value) ? json_encode($value) : (string) $value,
                ], 'setting_key = :k' . $companyWhere, $params);
            } else {
                $db->insert('system_settings', [
                    'company_id' => $companyId,
                    'group_name' => 'general',
                    'setting_key' => $key,
                    'setting_value' => is_array($value) ? json_encode($value) : (string) $value,
                    'value_type' => 'string',
                ]);
            }
        }

        (new AuditService())->log('update', 'settings', null, $previous, $settings);
        flash('success', 'Settings saved.');
        $this->redirect('/admin/settings');
    }

    private function authorizeSettings(): bool
    {
        if ($this->auth->can('settings.manage')) {
            return false;
        }
        if ($this->auth->can('attendance.security.settings')) {
            return true;
        }
        throw new HttpException('You do not have permission to perform this action.', 403);
    }

    private function validatedAttendanceValue(string $key, mixed $value): mixed
    {
        $allowed = [
            'attendance_security_mode' => ['disabled', 'device_only', 'ip_only', 'device_and_ip'],
            'attendance_device_registration_policy' => ['auto_first', 'admin_approval'],
            'attendance_ip_source' => ['office', 'approved'],
            'attendance_device_change_requires_approval' => ['0', '1'],
            'attendance_log_failed_attempts' => ['0', '1'],
        ];
        if (isset($allowed[$key]) && !in_array((string) $value, $allowed[$key], true)) {
            flash('error', 'Invalid attendance security setting.');
            $this->redirect('/admin/settings');
        }
        if (in_array($key, ['attendance_office_ip_addresses', 'attendance_approved_ip_addresses'], true)) {
            $ipService = new ClientIpService();
            foreach ($ipService->parseRules((string) $value) as $rule) {
                if (!$ipService->isValidRule($rule)) {
                    flash('error', 'Invalid IP address or CIDR range: ' . $rule);
                    $this->redirect('/admin/settings');
                }
            }
        }
        return $value;
    }
}
