<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;

class MonitoringHeartbeatService
{
    private Database $db;
    private MonitoringSessionService $sessions;
    private MonitoringDeviceService $devices;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->sessions = new MonitoringSessionService();
        $this->devices = new MonitoringDeviceService();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array}
     */
    public function beat(array $device, ?array $session, array $payload = []): array
    {
        $this->devices->touch((int) $device['id']);

        $this->db->insert('monitoring_heartbeats', [
            'session_id' => $session['id'] ?? null,
            'device_id' => (int) $device['id'],
            'employee_id' => (int) $device['employee_id'],
            'status' => $payload['status'] ?? ($session['status'] ?? 'online'),
            'agent_version' => $payload['agent_version'] ?? $device['agent_version'] ?? null,
            'idle_seconds' => isset($payload['idle_seconds']) ? (int) $payload['idle_seconds'] : null,
            'active_app' => $payload['active_app'] ?? null,
            'payload' => $payload ? json_encode($payload) : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $commands = [];
        $tray = [
            'label' => 'Monitoring Stopped',
            'mode' => null,
            'started_at' => null,
            'interval_minutes' => null,
            'last_sync_at' => date('Y-m-d H:i:s'),
        ];

        if ($session) {
            $this->db->update('monitoring_sessions', [
                'last_heartbeat_at' => date('Y-m-d H:i:s'),
                'status' => ((int) ($session['remote_stop_requested'] ?? 0) === 1)
                    ? 'stopping'
                    : (in_array($session['status'], ['completed', 'cancelled', 'failed'], true) ? $session['status'] : 'active'),
            ], 'id = :id', ['id' => $session['id']]);

            if ((int) ($session['remote_stop_requested'] ?? 0) === 1 || ($session['status'] ?? '') === 'stopping') {
                $commands[] = ['type' => 'stop', 'reason' => $session['stop_reason'] ?? 'remote_stop'];
                $this->sessions->completeSession((int) $session['id'], $session['stop_reason'] ?? 'remote_stop');
                $tray['label'] = 'Monitoring Stopped';
            } elseif (in_array($session['status'], ['active', 'paused', 'offline', 'pending'], true)) {
                $tray = [
                    'label' => 'Monitoring Active',
                    'mode' => 'Activity + Periodic Screenshots',
                    'started_at' => $session['started_at'],
                    'interval_minutes' => (int) $session['screenshot_interval_minutes'],
                    'last_sync_at' => date('Y-m-d H:i:s'),
                    // Intentionally NO next_screenshot field (revised transparency)
                ];
            }
        } else {
            // Check if attendance ended while agent was offline
            $active = $this->sessions->findActiveForEmployee((int) $device['employee_id']);
            if ($active && (int) ($active['remote_stop_requested'] ?? 0) === 1) {
                $commands[] = ['type' => 'stop', 'reason' => $active['stop_reason'] ?? 'remote_stop'];
                $this->sessions->completeSession((int) $active['id'], $active['stop_reason'] ?? 'remote_stop');
            }
        }

        // Mandatory agent update check
        $minVersion = $this->db->fetch(
            'SELECT version FROM monitoring_agent_versions
             WHERE platform = :p AND is_active = 1 AND is_mandatory = 1
             ORDER BY id DESC LIMIT 1',
            ['p' => $payload['platform'] ?? 'windows']
        );
        if ($minVersion && !empty($payload['agent_version'])) {
            if (version_compare((string) $payload['agent_version'], (string) $minVersion['version'], '<')) {
                $commands[] = ['type' => 'update_required', 'version' => $minVersion['version']];
            }
        }

        return [
            'success' => true,
            'message' => 'Heartbeat received.',
            'data' => [
                'commands' => $commands,
                'tray' => $tray,
                'server_time' => date('c'),
            ],
        ];
    }
}
