<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use RuntimeException;

final class AttendanceSecurityService
{
    public const COOKIE = 'ems_attendance_device';

    private Database $db;
    private ClientIpService $ips;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ips = new ClientIpService();
    }

    public function installed(): bool
    {
        return $this->db->tableExists('employee_attendance_devices')
            && $this->db->tableExists('attendance_security_events');
    }

    public function settings(int $companyId): array
    {
        $settings = [
            'attendance_security_mode' => 'device_only',
            'attendance_device_registration_policy' => 'auto_first',
            'attendance_device_change_requires_approval' => '1',
            'attendance_ip_source' => 'office',
            'attendance_office_ip_addresses' => '',
            'attendance_approved_ip_addresses' => '',
            'attendance_log_failed_attempts' => '1',
        ];
        if (!$this->installed()) {
            $settings['attendance_security_mode'] = 'disabled';
            return $settings;
        }
        $rows = $this->db->fetchAll(
            "SELECT setting_key, setting_value FROM system_settings
             WHERE company_id = :cid AND group_name = 'attendance_security'",
            ['cid' => $companyId]
        );
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) ($row['setting_value'] ?? '');
        }
        $existing = array_column($rows, 'setting_key');
        $types = [
            'attendance_device_change_requires_approval' => 'boolean',
            'attendance_office_ip_addresses' => 'text',
            'attendance_approved_ip_addresses' => 'text',
            'attendance_log_failed_attempts' => 'boolean',
        ];
        foreach ($settings as $key => $value) {
            if (!in_array($key, $existing, true)) {
                $this->db->query(
                    "INSERT IGNORE INTO system_settings
                     (company_id, group_name, setting_key, setting_value, value_type, description)
                     VALUES (:cid, 'attendance_security', :key, :value, :type, 'Attendance security configuration.')",
                    ['cid' => $companyId, 'key' => $key, 'value' => $value, 'type' => $types[$key] ?? 'string']
                );
            }
        }
        return $settings;
    }

    /** Must be called while the employee row is locked by the caller. */
    public function validateCheckIn(array $employee, ?int $userId, ?string $rawToken, string $ip, string $userAgent): array
    {
        $companyId = (int) $employee['company_id'];
        $employeeId = (int) $employee['id'];
        $settings = $this->settings($companyId);
        $mode = (string) $settings['attendance_security_mode'];
        if (!in_array($mode, ['disabled', 'device_only', 'ip_only', 'device_and_ip'], true)) {
            $mode = 'device_only';
        }

        $result = ['success' => true, 'message' => '', 'device_id' => null, 'device_cookie_token' => null];
        if (in_array($mode, ['device_only', 'device_and_ip'], true)) {
            $result = $this->validateDevice($employeeId, $companyId, $userId, $rawToken, $ip, $userAgent, $settings);
            if (!$result['success']) {
                return $result;
            }
        }

        if (in_array($mode, ['ip_only', 'device_and_ip'], true)) {
            $source = $settings['attendance_ip_source'] === 'approved' ? 'attendance_approved_ip_addresses' : 'attendance_office_ip_addresses';
            $rules = $this->ips->parseRules((string) ($settings[$source] ?? ''));
            if ($rules === [] || !$this->ips->matchesAny($ip, $rules)) {
                $this->recordEvent($employeeId, $companyId, $userId, $result['device_id'], 'CHECK_IN_BLOCKED_IP', $rawToken, $ip, $userAgent, 'blocked', 'IP address is not in the configured allowlist', [], $settings);
                return array_merge($result, [
                    'success' => false,
                    'message' => 'Check-in is not allowed from this network.',
                    'code' => 'ATTENDANCE_IP_NOT_ALLOWED',
                ]);
            }
        }

        return $result;
    }

    public function recordCheckInResult(array $employee, ?int $userId, ?int $deviceId, ?string $rawToken, string $ip, string $userAgent, string $event, string $result, ?string $reason = null): void
    {
        $this->recordEvent((int) $employee['id'], (int) $employee['company_id'], $userId, $deviceId, $event, $rawToken, $ip, $userAgent, $result, $reason, [], $this->settings((int) $employee['company_id']));
    }

    public function devicesForEmployee(int $employeeId): array
    {
        if (!$this->installed()) {
            return [];
        }
        return $this->db->fetchAll(
            'SELECT d.*, ua.name AS approved_by_name, ur.name AS reviewed_by_name, uv.name AS revoked_by_name
             FROM employee_attendance_devices d
             LEFT JOIN users ua ON ua.id = d.approved_by
             LEFT JOIN users ur ON ur.id = d.reviewed_by
             LEFT JOIN users uv ON uv.id = d.revoked_by
             WHERE d.employee_id = :eid ORDER BY (d.status = :approved) DESC, d.created_at DESC',
            ['eid' => $employeeId, 'approved' => 'approved']
        );
    }

    public function approve(int $deviceId, int $employeeId, int $adminId): void
    {
        $this->db->beginTransaction();
        try {
            $employee = $this->lockedEmployee($employeeId);
            $device = $this->deviceForEmployee($deviceId, $employeeId);
            if (!$employee || !$device || $device['status'] !== 'pending') {
                throw new RuntimeException('Pending attendance device was not found.');
            }
            $now = date('Y-m-d H:i:s');
            $this->db->query(
                "UPDATE employee_attendance_devices SET status = 'revoked', revoked_by = :admin, revoked_at = :now,
                 revocation_reason = 'Replaced by approved device', updated_at = :now2
                 WHERE employee_id = :eid AND status = 'approved'",
                ['admin' => $adminId, 'now' => $now, 'now2' => $now, 'eid' => $employeeId]
            );
            $revokedId = (int) $this->db->fetchColumn(
                "SELECT id FROM employee_attendance_devices WHERE employee_id = :eid AND status = 'revoked' AND revoked_at = :now ORDER BY id DESC LIMIT 1",
                ['eid' => $employeeId, 'now' => $now]
            );
            if ($revokedId > 0) {
                $this->recordEvent($employeeId, (int) $employee['company_id'], $adminId, $revokedId, 'DEVICE_REVOKED', null, '', '', 'revoked', 'Replaced by approved device');
            }
            $this->db->update('employee_attendance_devices', [
                'status' => 'approved', 'approved_by' => $adminId, 'approved_at' => $now,
                'reviewed_by' => $adminId, 'reviewed_at' => $now,
            ], 'id = :id AND employee_id = :eid AND status = :pending', ['id' => $deviceId, 'eid' => $employeeId, 'pending' => 'pending']);
            $this->recordEvent($employeeId, (int) $employee['company_id'], $adminId, $deviceId, 'DEVICE_CHANGE_APPROVED', null, (string) ($device['last_ip'] ?? ''), (string) ($device['user_agent'] ?? ''), 'approved', null);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reject(int $deviceId, int $employeeId, int $adminId, string $reason): void
    {
        $employee = $this->lockedEmployee($employeeId, false);
        $device = $this->deviceForEmployee($deviceId, $employeeId);
        if (!$employee || !$device || $device['status'] !== 'pending') {
            throw new RuntimeException('Pending attendance device was not found.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->update('employee_attendance_devices', [
            'status' => 'rejected', 'reviewed_by' => $adminId, 'reviewed_at' => $now,
            'revocation_reason' => substr(trim($reason), 0, 500) ?: 'Rejected by administrator',
        ], 'id = :id AND employee_id = :eid AND status = :pending', ['id' => $deviceId, 'eid' => $employeeId, 'pending' => 'pending']);
        $this->recordEvent($employeeId, (int) $employee['company_id'], $adminId, $deviceId, 'DEVICE_CHANGE_REJECTED', null, (string) ($device['last_ip'] ?? ''), '', 'rejected', substr(trim($reason), 0, 500));
    }

    public function revoke(int $deviceId, int $employeeId, int $adminId, string $reason, string $event = 'DEVICE_REVOKED'): void
    {
        $employee = $this->lockedEmployee($employeeId, false);
        $device = $this->deviceForEmployee($deviceId, $employeeId);
        if (!$employee || !$device || !in_array($device['status'], ['approved', 'pending'], true)) {
            throw new RuntimeException('Active attendance device was not found.');
        }
        $now = date('Y-m-d H:i:s');
        $this->db->update('employee_attendance_devices', [
            'status' => 'revoked', 'revoked_by' => $adminId, 'revoked_at' => $now,
            'revocation_reason' => substr(trim($reason), 0, 500) ?: 'Reset by administrator',
        ], 'id = :id AND employee_id = :eid', ['id' => $deviceId, 'eid' => $employeeId]);
        $this->recordEvent($employeeId, (int) $employee['company_id'], $adminId, $deviceId, $event, null, (string) ($device['last_ip'] ?? ''), '', 'revoked', substr(trim($reason), 0, 500));
    }

    public function reset(int $employeeId, int $adminId, string $reason): void
    {
        $approved = $this->db->fetch("SELECT id FROM employee_attendance_devices WHERE employee_id = :eid AND status = 'approved' LIMIT 1", ['eid' => $employeeId]);
        if (!$approved) {
            throw new RuntimeException('This employee has no approved attendance device to reset.');
        }
        $this->revoke((int) $approved['id'], $employeeId, $adminId, $reason, 'DEVICE_RESET');
    }

    private function validateDevice(int $employeeId, int $companyId, ?int $userId, ?string $rawToken, string $ip, string $userAgent, array $settings): array
    {
        $rawToken = $this->validToken($rawToken) ? $rawToken : null;
        $hash = $rawToken ? $this->hashToken($rawToken) : null;
        $current = $hash ? $this->db->fetch('SELECT * FROM employee_attendance_devices WHERE employee_id = :eid AND device_identifier_hash = :hash LIMIT 1', ['eid' => $employeeId, 'hash' => $hash]) : null;
        $approved = $this->db->fetch("SELECT * FROM employee_attendance_devices WHERE employee_id = :eid AND status = 'approved' LIMIT 1 FOR UPDATE", ['eid' => $employeeId]);
        $meta = $this->parseUserAgent($userAgent);

        if ($current && $current['status'] === 'approved') {
            $this->db->update('employee_attendance_devices', ['last_seen_at' => date('Y-m-d H:i:s'), 'last_ip' => $ip], 'id = :id', ['id' => $current['id']]);
            return ['success' => true, 'message' => '', 'device_id' => (int) $current['id'], 'device_cookie_token' => null];
        }

        // A rejected/revoked credential remains in history and cannot be reused.
        // Issue a fresh credential for a new change request instead.
        if ($current && in_array($current['status'], ['rejected', 'revoked'], true)) {
            $rawToken = bin2hex(random_bytes(32));
            $hash = $this->hashToken($rawToken);
            $current = null;
        }

        if (!$rawToken) {
            $rawToken = bin2hex(random_bytes(32));
            $hash = $this->hashToken($rawToken);
        }

        if (!$approved) {
            $autoApprove = ($settings['attendance_device_registration_policy'] ?? 'auto_first') === 'auto_first';
            if (!$current) {
                $deviceId = $this->db->insert('employee_attendance_devices', [
                    'employee_id' => $employeeId, 'company_id' => $companyId,
                    'device_identifier_hash' => $hash, 'device_name' => $meta['device_name'],
                    'browser' => $meta['browser'], 'operating_system' => $meta['operating_system'],
                    'registered_ip' => $ip, 'last_ip' => $ip, 'first_registered_at' => date('Y-m-d H:i:s'),
                    'last_seen_at' => date('Y-m-d H:i:s'), 'status' => $autoApprove ? 'approved' : 'pending',
                    'approved_by' => null, 'approved_at' => $autoApprove ? date('Y-m-d H:i:s') : null,
                ]);
                $event = $autoApprove ? 'DEVICE_REGISTERED' : 'DEVICE_CHANGE_REQUESTED';
                $this->recordEvent($employeeId, $companyId, $userId, $deviceId, $event, $rawToken, $ip, $userAgent, $autoApprove ? 'approved' : 'pending');
            } else {
                $deviceId = (int) $current['id'];
                if ($autoApprove && $current['status'] === 'pending') {
                    $this->db->update('employee_attendance_devices', ['status' => 'approved', 'approved_by' => null, 'approved_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $deviceId]);
                    $this->recordEvent($employeeId, $companyId, $userId, $deviceId, 'DEVICE_REGISTERED', $rawToken, $ip, $userAgent, 'approved');
                }
            }
            if ($autoApprove) {
                return ['success' => true, 'message' => '', 'device_id' => $deviceId, 'device_cookie_token' => $rawToken];
            }
            return ['success' => false, 'message' => 'This device is pending administrator approval.', 'code' => 'ATTENDANCE_DEVICE_PENDING', 'device_id' => $deviceId, 'device_cookie_token' => $rawToken];
        }

        if (($settings['attendance_device_change_requires_approval'] ?? '1') === '0') {
            $now = date('Y-m-d H:i:s');
            $this->db->update('employee_attendance_devices', [
                'status' => 'revoked', 'revoked_at' => $now,
                'revocation_reason' => 'Automatically replaced by a newly registered device',
            ], 'id = :id', ['id' => $approved['id']]);
            if ($current && $current['status'] === 'pending') {
                $deviceId = (int) $current['id'];
                $this->db->update('employee_attendance_devices', [
                    'status' => 'approved', 'approved_by' => $userId, 'approved_at' => $now,
                    'last_seen_at' => $now, 'last_ip' => $ip,
                ], 'id = :id', ['id' => $deviceId]);
            } else {
                $deviceId = $this->db->insert('employee_attendance_devices', [
                    'employee_id' => $employeeId, 'company_id' => $companyId,
                    'device_identifier_hash' => $hash, 'device_name' => $meta['device_name'],
                    'browser' => $meta['browser'], 'operating_system' => $meta['operating_system'],
                    'registered_ip' => $ip, 'last_ip' => $ip, 'first_registered_at' => $now,
                    'last_seen_at' => $now, 'status' => 'approved', 'approved_by' => null, 'approved_at' => $now,
                ]);
            }
            $this->recordEvent($employeeId, $companyId, $userId, $deviceId, 'DEVICE_REGISTERED', $rawToken, $ip, $userAgent, 'approved', null, ['automatic_replacement' => true]);
            return ['success' => true, 'message' => '', 'device_id' => $deviceId, 'device_cookie_token' => $rawToken];
        }

        if (!$current || !in_array($current['status'], ['pending'], true)) {
            $deviceId = $this->db->insert('employee_attendance_devices', [
                'employee_id' => $employeeId, 'company_id' => $companyId,
                'device_identifier_hash' => $hash, 'device_name' => $meta['device_name'],
                'browser' => $meta['browser'], 'operating_system' => $meta['operating_system'],
                'registered_ip' => $ip, 'last_ip' => $ip, 'first_registered_at' => date('Y-m-d H:i:s'),
                'last_seen_at' => date('Y-m-d H:i:s'), 'status' => 'pending',
            ]);
            $this->recordEvent($employeeId, $companyId, $userId, $deviceId, 'DEVICE_CHANGE_REQUESTED', $rawToken, $ip, $userAgent, 'pending');
        } else {
            $deviceId = (int) $current['id'];
            $this->db->update('employee_attendance_devices', ['last_seen_at' => date('Y-m-d H:i:s'), 'last_ip' => $ip], 'id = :id', ['id' => $deviceId]);
        }

        $this->recordEvent($employeeId, $companyId, $userId, $deviceId, 'CHECK_IN_BLOCKED_DEVICE', $rawToken, $ip, $userAgent, 'blocked', 'Device credential does not match the approved device', [], $settings);
        return [
            'success' => false,
            'message' => 'This device is not authorized for attendance check-in. Please contact your administrator.',
            'code' => 'ATTENDANCE_DEVICE_NOT_AUTHORIZED',
            'device_id' => $deviceId,
            'device_cookie_token' => $rawToken,
        ];
    }

    private function recordEvent(int $employeeId, int $companyId, ?int $userId, ?int $deviceId, string $event, ?string $rawToken, string $ip, string $userAgent, string $result, ?string $reason = null, array $metadata = [], ?array $settings = null): void
    {
        if (!$this->installed() || (($settings['attendance_log_failed_attempts'] ?? '1') === '0' && $result === 'blocked')) {
            return;
        }
        $id = $this->db->insert('attendance_security_events', [
            'employee_id' => $employeeId, 'company_id' => $companyId, 'user_id' => $userId,
            'device_id' => $deviceId, 'event_type' => $event,
            'device_identifier_hash' => $rawToken ? $this->hashToken($rawToken) : null,
            'ip_address' => $ip ?: null, 'user_agent' => substr($userAgent, 0, 500) ?: null,
            'result' => $result, 'failure_reason' => $reason,
            'metadata' => $metadata ? json_encode($metadata, JSON_UNESCAPED_SLASHES) : null,
        ]);
        (new AuditService())->log(strtolower($event), 'attendance_security_events', $id, null, [
            'company_id' => $companyId, 'employee_id' => $employeeId, 'device_id' => $deviceId,
            'event_type' => $event, 'result' => $result, 'failure_reason' => $reason,
        ], $userId);
    }

    private function lockedEmployee(int $employeeId, bool $lock = true): ?array
    {
        return $this->db->fetch('SELECT id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1' . ($lock ? ' FOR UPDATE' : ''), ['id' => $employeeId]);
    }

    private function deviceForEmployee(int $deviceId, int $employeeId): ?array
    {
        return $this->db->fetch('SELECT * FROM employee_attendance_devices WHERE id = :id AND employee_id = :eid LIMIT 1', ['id' => $deviceId, 'eid' => $employeeId]);
    }

    private function hashToken(string $token): string
    {
        $key = (string) config('app.key', '');
        return $key !== '' ? hash_hmac('sha256', $token, $key) : hash('sha256', $token);
    }

    private function validToken(?string $token): bool
    {
        return is_string($token) && preg_match('/\A[a-f0-9]{64}\z/', $token) === 1;
    }

    private function parseUserAgent(string $ua): array
    {
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Microsoft Edge',
            str_contains($ua, 'OPR/') => 'Opera',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Unknown browser',
        };
        $os = match (true) {
            str_contains($ua, 'Windows') => 'Windows',
            str_contains($ua, 'Android') => 'Android',
            preg_match('/iPhone|iPad/', $ua) === 1 => 'iOS/iPadOS',
            str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Unknown OS',
        };
        $kind = preg_match('/Mobile|Android|iPhone/', $ua) === 1 ? 'Mobile device' : 'Computer';
        return ['browser' => $browser, 'operating_system' => $os, 'device_name' => $kind . ' · ' . $browser . ' on ' . $os];
    }
}
