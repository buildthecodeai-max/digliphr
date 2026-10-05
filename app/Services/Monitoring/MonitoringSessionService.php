<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

class MonitoringSessionService
{
    private Database $db;
    private AuditService $audit;
    private MonitoringPolicyService $policies;
    private MonitoringDeviceService $devices;
    private MonitoringAlertService $alerts;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->audit = new AuditService();
        $this->policies = new MonitoringPolicyService();
        $this->devices = new MonitoringDeviceService();
        $this->alerts = new MonitoringAlertService();
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM monitoring_sessions WHERE id = :id', ['id' => $id]);
    }

    public function findByAttendance(int $attendanceId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_sessions WHERE attendance_id = :aid LIMIT 1',
            ['aid' => $attendanceId]
        );
    }

    public function findActiveForEmployee(int $employeeId): ?array
    {
        return $this->db->fetch(
            'SELECT * FROM monitoring_sessions
             WHERE employee_id = :eid AND status IN ("pending","active","paused","offline","stopping")
             ORDER BY id DESC LIMIT 1',
            ['eid' => $employeeId]
        );
    }

    public function findBySessionToken(string $token): ?array
    {
        $hash = hash('sha256', $token);
        return $this->db->fetch(
            'SELECT * FROM monitoring_sessions
             WHERE session_token_hash = :h
               AND token_revoked_at IS NULL
               AND (token_expires_at IS NULL OR token_expires_at > NOW())
               AND status IN ("pending","active","paused","offline","stopping")
             LIMIT 1',
            ['h' => $hash]
        );
    }

    /**
     * Called AFTER attendance check-in commit. Failures must not affect attendance.
     *
     * @return array{success: bool, message: string, data?: array, code?: string}
     */
    public function startAfterCheckIn(int $employeeId, int $attendanceId, ?int $deviceId = null): array
    {
        $existing = $this->findByAttendance($attendanceId);
        if ($existing) {
            return [
                'success' => true,
                'message' => 'Monitoring session already exists for this attendance.',
                'data' => ['session' => $this->publicSession($existing)],
            ];
        }

        $employee = $this->db->fetch(
            'SELECT id, company_id, branch_id, department_id, user_id FROM employees WHERE id = :id AND deleted_at IS NULL',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        $policy = $this->policies->resolveForEmployee($employeeId);
        if (!$policy) {
            return ['success' => false, 'message' => 'No monitoring policy assigned.'];
        }

        if (!$this->policies->hasAcknowledged($employeeId, $policy)) {
            $this->alerts->createActionRequired(
                (int) $employee['company_id'],
                $employeeId,
                $deviceId,
                null,
                'policy_ack_required',
                'Policy acknowledgement required',
                'Please acknowledge the monitoring policy before monitoring can start.',
                (int) ($employee['user_id'] ?? 0) ?: null
            );
            return [
                'success' => false,
                'code' => 'POLICY_ACKNOWLEDGEMENT_REQUIRED',
                'message' => 'Please acknowledge the monitoring policy before checking in.',
                'data' => [
                    'policy_id' => (int) $policy['id'],
                    'policy_version' => (int) $policy['version'],
                    'notice_title' => $policy['notice_title'],
                    'notice_text' => $policy['notice_text'],
                ],
            ];
        }

        $device = null;
        if ($deviceId) {
            $device = $this->devices->find($deviceId);
            if (!$this->devices->isApproved($device) || (int) ($device['employee_id'] ?? 0) !== $employeeId) {
                $this->alerts->createActionRequired(
                    (int) $employee['company_id'],
                    $employeeId,
                    $deviceId,
                    null,
                    'device_not_approved',
                    'Device not approved',
                    'Your monitoring device is not approved.',
                    (int) ($employee['user_id'] ?? 0) ?: null
                );
                return [
                    'success' => false,
                    'code' => 'DEVICE_NOT_APPROVED',
                    'message' => 'This device is not authorized for monitoring.',
                ];
            }
        }

        $interval = $this->policies->normalizeInterval(
            (int) ($policy['screenshot_interval_minutes'] ?? 10),
            $policy
        );
        $mode = $policy['mode'] ?? 'activity_screenshots';
        if (!empty($policy['recording_enabled']) || $mode === 'exceptional_recording') {
            // Deferred exceptional recording — never silent Mode 2 default
            $mode = 'activity_screenshots';
        }

        $plainToken = bin2hex(random_bytes(32));
        $ttlHours = (int) config('app.monitoring.session_token_ttl_hours', 16);
        $expires = date('Y-m-d H:i:s', time() + $ttlHours * 3600);
        $now = date('Y-m-d H:i:s');

        $id = $this->db->insert('monitoring_sessions', [
            'uuid' => $this->uuid(),
            'employee_id' => $employeeId,
            'company_id' => (int) $employee['company_id'],
            'branch_id' => $employee['branch_id'] ?? null,
            'department_id' => $employee['department_id'] ?? null,
            'attendance_id' => $attendanceId,
            'device_id' => $deviceId,
            'policy_id' => (int) $policy['id'],
            'policy_version' => (int) $policy['version'],
            'mode' => $mode,
            'screenshot_interval_minutes' => $interval,
            'status' => 'active',
            'session_token_hash' => hash('sha256', $plainToken),
            'token_expires_at' => $expires,
            'started_at' => $now,
            'last_heartbeat_at' => $now,
            'remote_stop_requested' => 0,
        ]);

        $session = $this->find($id);
        $this->audit->log('other', 'monitoring_sessions', $id, null, [
            'action' => 'monitoring_started',
            'attendance_id' => $attendanceId,
            'mode' => $mode,
            'interval' => $interval,
        ]);

        // Quiet: no employee popup/notification for routine start
        return [
            'success' => true,
            'message' => 'Monitoring session started successfully.',
            'data' => [
                'session_id' => $id,
                'session' => $this->publicSession($session),
                'session_token' => $plainToken,
                'mode' => $mode,
                'screenshot_interval_minutes' => $interval,
                'policy' => [
                    'id' => (int) $policy['id'],
                    'name' => $policy['name'],
                    'version' => (int) $policy['version'],
                    'screenshot_enabled' => (int) $policy['screenshot_enabled'],
                    'allow_employee_view_screenshots' => (int) $policy['allow_employee_view_screenshots'],
                ],
            ],
        ];
    }

    /**
     * Agent explicitly starts/binds device to an already-created or new session for active attendance.
     */
    public function startFromAgent(int $employeeId, int $deviceId, ?int $attendanceId = null): array
    {
        $device = $this->devices->find($deviceId);
        if (!$this->devices->isApproved($device) || (int) $device['employee_id'] !== $employeeId) {
            return [
                'success' => false,
                'code' => 'DEVICE_NOT_APPROVED',
                'message' => 'This device is not authorized for monitoring.',
            ];
        }

        if (!$attendanceId) {
            $active = $this->db->fetch(
                'SELECT id FROM attendance
                 WHERE employee_id = :eid AND check_in_at IS NOT NULL AND check_out_at IS NULL AND deleted_at IS NULL
                 ORDER BY id DESC LIMIT 1',
                ['eid' => $employeeId]
            );
            if (!$active) {
                return ['success' => false, 'message' => 'No active check-in found. Monitoring cannot start.'];
            }
            $attendanceId = (int) $active['id'];
        }

        $existing = $this->findByAttendance($attendanceId);
        if ($existing) {
            if (in_array($existing['status'], ['completed', 'cancelled', 'failed'], true)) {
                return ['success' => false, 'message' => 'Monitoring session already closed for this attendance.'];
            }
            $plainToken = bin2hex(random_bytes(32));
            $ttlHours = (int) config('app.monitoring.session_token_ttl_hours', 16);
            $this->db->update('monitoring_sessions', [
                'device_id' => $deviceId,
                'status' => 'active',
                'session_token_hash' => hash('sha256', $plainToken),
                'token_expires_at' => date('Y-m-d H:i:s', time() + $ttlHours * 3600),
                'token_revoked_at' => null,
                'last_heartbeat_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $existing['id']]);
            $session = $this->find((int) $existing['id']);
            return [
                'success' => true,
                'message' => 'Monitoring session started successfully.',
                'data' => [
                    'session_id' => (int) $existing['id'],
                    'session' => $this->publicSession($session),
                    'session_token' => $plainToken,
                    'mode' => $session['mode'],
                    'screenshot_interval_minutes' => (int) $session['screenshot_interval_minutes'],
                ],
            ];
        }

        return $this->startAfterCheckIn($employeeId, $attendanceId, $deviceId);
    }

    /**
     * Called AFTER attendance checkout commit.
     */
    public function stopAfterCheckOut(int $employeeId, int $attendanceId, string $reason = 'checkout'): array
    {
        $session = $this->findByAttendance($attendanceId);
        if (!$session) {
            $session = $this->findActiveForEmployee($employeeId);
        }
        if (!$session) {
            return ['success' => true, 'message' => 'No active monitoring session.'];
        }

        return $this->completeSession((int) $session['id'], $reason);
    }

    public function completeSession(int $sessionId, string $reason = 'checkout'): array
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return ['success' => false, 'message' => 'Session not found.'];
        }
        if (in_array($session['status'], ['completed', 'cancelled', 'failed'], true)) {
            return ['success' => true, 'message' => 'Session already closed.', 'data' => ['session' => $this->publicSession($session)]];
        }

        $this->db->update('monitoring_sessions', [
            'status' => 'completed',
            'ended_at' => date('Y-m-d H:i:s'),
            'stop_reason' => $reason,
            'token_revoked_at' => date('Y-m-d H:i:s'),
            'remote_stop_requested' => 0,
        ], 'id = :id', ['id' => $sessionId]);

        $this->audit->log('other', 'monitoring_sessions', $sessionId, $session, [
            'action' => 'monitoring_stopped',
            'reason' => $reason,
        ]);

        return [
            'success' => true,
            'message' => 'Monitoring session stopped.',
            'data' => ['session' => $this->publicSession($this->find($sessionId))],
        ];
    }

    public function requestRemoteStop(int $sessionId, ?int $userId = null, string $reason = 'admin_force_stop'): array
    {
        $session = $this->find($sessionId);
        if (!$session) {
            return ['success' => false, 'message' => 'Session not found.'];
        }

        $this->db->update('monitoring_sessions', [
            'remote_stop_requested' => 1,
            'status' => 'stopping',
            'stop_reason' => $reason,
        ], 'id = :id', ['id' => $sessionId]);

        $this->audit->log('other', 'monitoring_sessions', $sessionId, $session, [
            'action' => 'session_force_stop',
            'reason' => $reason,
        ], $userId);

        return ['success' => true, 'message' => 'Remote stop requested. Agent will stop on next heartbeat.'];
    }

    public function publicSession(?array $session): ?array
    {
        if (!$session) {
            return null;
        }
        return [
            'id' => (int) $session['id'],
            'uuid' => $session['uuid'],
            'employee_id' => (int) $session['employee_id'],
            'attendance_id' => (int) $session['attendance_id'],
            'device_id' => $session['device_id'] ? (int) $session['device_id'] : null,
            'policy_id' => (int) $session['policy_id'],
            'mode' => $session['mode'],
            'screenshot_interval_minutes' => (int) $session['screenshot_interval_minutes'],
            'status' => $session['status'],
            'started_at' => $session['started_at'],
            'ended_at' => $session['ended_at'],
            'last_heartbeat_at' => $session['last_heartbeat_at'],
            'remote_stop_requested' => (int) $session['remote_stop_requested'],
        ];
    }

    private function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
