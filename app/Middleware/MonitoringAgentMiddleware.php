<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Monitoring\MonitoringDeviceService;
use App\Services\Monitoring\MonitoringSessionService;
use Closure;

/**
 * Bearer-token auth for the desktop monitoring agent (not cookie-only).
 * Accepts device access tokens or session tokens.
 */
class MonitoringAgentMiddleware
{
    public function handle(Request $request, Response $response, Closure $next): mixed
    {
        $token = $request->bearerToken()
            ?? (is_string($request->input('access_token')) ? (string) $request->input('access_token') : null)
            ?? (is_string($request->input('session_token')) ? (string) $request->input('session_token') : null);
        if ($token === null || $token === '') {
            $response->json(['success' => false, 'message' => 'Unauthenticated.', 'code' => 'AUTH_REQUIRED'], 401);
        }

        $devices = new MonitoringDeviceService();
        $sessions = new MonitoringSessionService();

        try {
            $device = $devices->findByAccessToken($token);
            $session = null;

            if ($device) {
                $request->setAttribute('monitoring_device', $device);
                $request->setAttribute('monitoring_employee_id', (int) $device['employee_id']);
                $active = $sessions->findActiveForEmployee((int) $device['employee_id']);
                if ($active) {
                    $session = $active;
                }
            } else {
                $session = $sessions->findBySessionToken($token);
                if (!$session) {
                    $response->json([
                        'success' => false,
                        'message' => 'Invalid or expired monitoring token.',
                        'code' => 'AUTH_FAILED',
                    ], 401);
                }
                $device = !empty($session['device_id']) ? $devices->find((int) $session['device_id']) : null;
                $request->setAttribute('monitoring_device', $device);
                $request->setAttribute('monitoring_employee_id', (int) $session['employee_id']);
            }
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table or view not found')) {
                $response->json([
                    'success' => false,
                    'message' => 'Work monitoring is not installed. Run migration 2026_07_30_work_activity_monitoring.sql in phpMyAdmin.',
                    'code' => 'MIGRATION_REQUIRED',
                ], 503);
            }
            throw $e;
        }

        $request->setAttribute('monitoring_session', $session);
        $request->setAttribute('monitoring_token', $token);

        return $next();
    }
}
