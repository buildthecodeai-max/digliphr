<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Monitoring\MonitoringActivityService;
use App\Services\Monitoring\MonitoringDeviceService;
use App\Services\Monitoring\MonitoringHeartbeatService;
use App\Services\Monitoring\MonitoringPolicyService;
use App\Services\Monitoring\MonitoringSessionService;
use App\Services\Monitoring\MonitoringSiteActivityService;
use App\Services\Monitoring\MonitoringUploadService;

class MonitoringApiController extends Controller
{
    private MonitoringPolicyService $policies;
    private MonitoringDeviceService $devices;
    private MonitoringSessionService $sessions;
    private MonitoringActivityService $activity;
    private MonitoringUploadService $uploads;
    private MonitoringHeartbeatService $heartbeats;
    private MonitoringSiteActivityService $siteActivity;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->policies = new MonitoringPolicyService();
        $this->devices = new MonitoringDeviceService();
        $this->sessions = new MonitoringSessionService();
        $this->activity = new MonitoringActivityService();
        $this->uploads = new MonitoringUploadService();
        $this->heartbeats = new MonitoringHeartbeatService();
        $this->siteActivity = new MonitoringSiteActivityService();
    }

    /** Login with email/password, then register/refresh device — returns device access_token. */
    public function login(): void
    {
        $email = trim((string) $this->request->input('email', ''));
        $password = (string) $this->request->input('password', '');
        if ($email === '' || $password === '') {
            $this->jsonError('Email and password are required.', null, 422);
        }

        // Direct password check — monitoring endpoints are stateless (Bearer token).
        // We must NOT call attempt() because it calls Session::regenerate() and issues
        // cookies, which fails in this API context and causes misleading 500 errors.
        $db = \App\Core\Database::getInstance();
        $user = $db->fetch(
            'SELECT * FROM users WHERE email = :email AND deleted_at IS NULL AND is_active = 1 LIMIT 1',
            ['email' => $email]
        );
        if (!$user || !password_verify($password, (string) ($user['password'] ?? ''))) {
            $this->jsonError('Invalid email or password.', null, 401, 'AUTH_FAILED');
        }

        $employee = $db->fetch(
            'SELECT * FROM employees WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1',
            ['uid' => $user['id']]
        );
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        if (!$db->tableExists('monitoring_devices')) {
            $this->jsonError(
                'Work monitoring is not installed. Run migration 2026_07_30_work_activity_monitoring.sql in phpMyAdmin.',
                null,
                503,
                'MIGRATION_REQUIRED'
            );
        }

        try {
            $reg = $this->devices->register((int) $employee['id'], [
                'device_uid' => (string) $this->request->input('device_uid', ''),
                'hostname' => $this->request->input('hostname'),
                'os_name' => $this->request->input('os_name', 'windows'),
                'os_version' => $this->request->input('os_version'),
                'agent_version' => $this->request->input('agent_version', '0.1.0'),
            ], true);
        } catch (\Throwable $e) {
            $msg = $e->getMessage();
            if (str_contains($msg, "doesn't exist") || str_contains($msg, 'Base table or view not found')) {
                $this->jsonError(
                    'Work monitoring is not installed. Run migration 2026_07_30_work_activity_monitoring.sql in phpMyAdmin.',
                    null,
                    503,
                    'MIGRATION_REQUIRED'
                );
            }
            throw $e;
        }

        if (!$reg['success']) {
            $this->jsonError($reg['message'], $reg['data'] ?? null, 403, $reg['code'] ?? null);
        }

        $policy = $this->policies->resolveForEmployee((int) $employee['id']);
        $needsAck = $policy && !$this->policies->hasAcknowledged((int) $employee['id'], $policy);
        $siteRules = $db->tableExists('monitoring_site_rules')
            ? $this->siteActivity->rulesForAgent((int) $employee['company_id'], (int) $employee['id'])
            : ['rules' => [], 'exceptions' => []];

        $this->jsonSuccess('Authenticated.', array_merge($reg['data'] ?? [], [
            'employee' => [
                'id' => (int) $employee['id'],
                'name' => trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
                'employee_code' => $employee['employee_code'] ?? null,
            ],
            'policy' => $policy ? [
                'id' => (int) $policy['id'],
                'name' => $policy['name'],
                'version' => (int) $policy['version'],
                'mode' => $policy['mode'],
                'screenshot_interval_minutes' => (int) $policy['screenshot_interval_minutes'],
                'require_acknowledgement' => (int) $policy['require_acknowledgement'],
                'notice_title' => $policy['notice_title'],
                'notice_text' => $policy['notice_text'],
                'acknowledgement_required' => $needsAck,
            ] : null,
            'site_rules' => $siteRules,
        ]));
    }

    public function registerDevice(): void
    {
        $device = $this->request->getAttribute('monitoring_device');
        $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
        if (!$employeeId) {
            $this->jsonError('Unauthenticated.', null, 401, 'AUTH_REQUIRED');
        }

        $reg = $this->devices->register($employeeId, $this->request->all(), true);
        if (!$reg['success']) {
            $this->jsonError($reg['message'], $reg['data'] ?? null, 403, $reg['code'] ?? null);
        }
        $this->jsonSuccess($reg['message'], $reg['data'] ?? null);
    }

    public function policy(): void
    {
        $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
        $policy = $this->policies->resolveForEmployee($employeeId);
        if (!$policy) {
            $this->jsonError('No monitoring policy found.', null, 404);
        }
        $needsAck = !$this->policies->hasAcknowledged($employeeId, $policy);
        $employee = $this->employee();
        $siteRules = \App\Core\Database::getInstance()->tableExists('monitoring_site_rules')
            ? $this->siteActivity->rulesForAgent((int) ($employee['company_id'] ?? 0), $employeeId)
            : ['rules' => [], 'exceptions' => []];
        $this->jsonSuccess('Policy loaded.', [
            'policy' => [
                'id' => (int) $policy['id'],
                'name' => $policy['name'],
                'version' => (int) $policy['version'],
                'mode' => $policy['mode'],
                'screenshot_enabled' => (int) $policy['screenshot_enabled'],
                'screenshot_interval_minutes' => $this->policies->normalizeInterval(
                    (int) $policy['screenshot_interval_minutes'],
                    $policy
                ),
                'recording_enabled' => 0,
                'excluded_apps' => json_decode((string) ($policy['excluded_apps'] ?? '[]'), true) ?: [],
                'masked_apps' => json_decode((string) ($policy['masked_apps'] ?? '[]'), true) ?: [],
                'notice_title' => $policy['notice_title'],
                'notice_text' => $policy['notice_text'],
                'require_acknowledgement' => (int) $policy['require_acknowledgement'],
                'acknowledgement_required' => $needsAck,
                'allow_employee_view_screenshots' => (int) $policy['allow_employee_view_screenshots'],
            ],
            'site_rules' => $siteRules,
        ]);
    }

    public function acknowledge(): void
    {
        $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
        $device = $this->request->getAttribute('monitoring_device');
        $policyId = (int) $this->request->input('policy_id', 0);
        if ($policyId <= 0) {
            $policy = $this->policies->resolveForEmployee($employeeId);
            $policyId = (int) ($policy['id'] ?? 0);
        }
        $result = $this->policies->acknowledge(
            $employeeId,
            $policyId,
            $device['id'] ?? null,
            $this->request->ip(),
            $this->request->userAgent()
        );
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function startSession(): void
    {
        $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
        $device = $this->request->getAttribute('monitoring_device');
        if (!$device) {
            $this->jsonError('Device registration required.', null, 403, 'DEVICE_NOT_APPROVED');
        }

        $attendanceId = $this->request->input('attendance_id')
            ? (int) $this->request->input('attendance_id')
            : null;

        $result = $this->sessions->startFromAgent($employeeId, (int) $device['id'], $attendanceId);
        if (!$result['success']) {
            $status = ($result['code'] ?? '') === 'POLICY_ACKNOWLEDGEMENT_REQUIRED' ? 403 : 422;
            $this->jsonError($result['message'], $result['data'] ?? null, $status, $result['code'] ?? null);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function stopSession(): void
    {
        $session = $this->request->getAttribute('monitoring_session');
        $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
        if (!$session) {
            $session = $this->sessions->findActiveForEmployee($employeeId);
        }
        if (!$session) {
            $this->jsonSuccess('No active monitoring session.');
            return;
        }
        $result = $this->sessions->completeSession((int) $session['id'], (string) $this->request->input('reason', 'agent_stop'));
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function heartbeat(): void
    {
        $device = $this->request->getAttribute('monitoring_device');
        $session = $this->request->getAttribute('monitoring_session');
        if (!$device) {
            $this->jsonError('Device required.', null, 403, 'DEVICE_NOT_APPROVED');
        }
        $result = $this->heartbeats->beat($device, $session, $this->request->all());
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function activityBatch(): void
    {
        $session = $this->requireActiveSession();
        $segments = $this->request->input('segments', []);
        if (!is_array($segments)) {
            $this->jsonError('segments must be an array.', null, 422);
        }
        $result = $this->activity->ingestBatch($session, $segments);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function authorizeScreenshot(): void
    {
        $session = $this->requireActiveSession();
        $result = $this->uploads->authorize($session, $this->request->all());
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422, $result['code'] ?? null);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function uploadScreenshot(): void
    {
        $session = $this->requireActiveSession();
        $captureId = (string) $this->request->input('capture_id', '');
        if ($captureId === '') {
            $this->jsonError('capture_id is required.', null, 422);
        }

        $binary = null;
        $file = $this->request->file('file') ?? $this->request->file('screenshot');
        if ($file && ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $binary = file_get_contents($file['tmp_name']);
        } else {
            $b64 = (string) $this->request->input('image_base64', '');
            if ($b64 !== '') {
                if (str_contains($b64, ',')) {
                    $b64 = explode(',', $b64, 2)[1];
                }
                $binary = base64_decode($b64, true);
            }
        }

        if ($binary === null || $binary === false || $binary === '') {
            $this->jsonError('Screenshot file or image_base64 is required.', null, 422);
        }

        $result = $this->uploads->uploadFile(
            $session,
            $captureId,
            $binary,
            $this->request->input('checksum') ? (string) $this->request->input('checksum') : null,
            $this->request->input('mime_type') ? (string) $this->request->input('mime_type') : null
        );
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function confirmScreenshot(): void
    {
        $session = $this->requireActiveSession();
        $captureId = (string) $this->request->input('capture_id', '');
        $result = $this->uploads->confirm(
            $session,
            $captureId,
            $this->request->input('checksum') ? (string) $this->request->input('checksum') : null
        );
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    /**
     * Generate a short-lived web-link token for the current web-session employee.
     * Called from the employee portal (cookie auth). Agent then exchanges it.
     */
    public function webTokenGenerate(): void
    {
        $auth = new \App\Services\AuthService();
        if (!$auth->id()) {
            $this->jsonError('Unauthenticated.', null, 401, 'AUTH_REQUIRED');
        }
        $db = Database::getInstance();
        $employee = $db->fetch(
            'SELECT * FROM employees WHERE user_id = :uid AND deleted_at IS NULL LIMIT 1',
            ['uid' => $auth->id()]
        );
        if (!$employee) {
            $this->jsonError('Employee profile required.', null, 403);
        }

        $deviceUid = trim((string) $this->request->input('device_uid', ''));
        if ($deviceUid === '') {
            $this->jsonError('device_uid is required.', null, 422);
        }

        $plain  = bin2hex(random_bytes(16));
        $hash   = hash('sha256', $plain);
        $expires = date('Y-m-d H:i:s', time() + 300); // 5-minute TTL

        $db->query(
            'INSERT INTO monitoring_agent_links (device_uid, employee_id, token_hash, expires_at, created_at)
             VALUES (:uid, :eid, :hash, :exp, NOW())
             ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at),
                                      used_at = NULL, created_at = NOW()',
            ['uid' => $deviceUid, 'eid' => (int) $employee['id'], 'hash' => $hash, 'exp' => $expires]
        );

        $this->jsonSuccess('Web link token generated.', [
            'token'      => $plain,
            'expires_at' => $expires,
            'employee'   => trim($employee['first_name'] . ' ' . $employee['last_name']),
        ]);
    }

    /**
     * Check whether a web-link token is waiting for this device_uid.
     * Returns {ready: true} if a valid unused token exists, {ready: false} otherwise.
     * The agent polls this endpoint then calls webTokenExchange() to consume it.
     */
    public function webTokenStatus(): void
    {
        $db        = Database::getInstance();
        $deviceUid = trim((string) $this->request->query('device_uid', ''));
        if ($deviceUid === '' || !$db->tableExists('monitoring_agent_links')) {
            $this->jsonSuccess('Status checked.', ['ready' => false]);
            return;
        }
        $exists = $db->fetchColumn(
            'SELECT COUNT(*) FROM monitoring_agent_links
             WHERE device_uid = :uid AND expires_at > NOW() AND used_at IS NULL',
            ['uid' => $deviceUid]
        );
        $this->jsonSuccess('Status checked.', ['ready' => (int) $exists > 0]);
    }

    /**
     * Exchange a web-link token for a device access_token (no password needed).
     * Called by the Electron agent after the employee generates a token in the web portal.
     */
    public function webTokenExchange(): void
    {
        $db        = Database::getInstance();
        $deviceUid = trim((string) $this->request->input('device_uid', ''));

        if ($deviceUid === '') {
            $this->jsonError('device_uid is required.', null, 422);
        }

        if (!$db->tableExists('monitoring_agent_links')) {
            $this->jsonError('Run migration 2026_10_13_monitoring_agent_links.sql first.', null, 503, 'MIGRATION_REQUIRED');
        }

        // Agent sends device_uid; server looks up the valid token the employee generated via web
        $link = $db->fetch(
            'SELECT * FROM monitoring_agent_links
             WHERE device_uid = :uid AND expires_at > NOW() AND used_at IS NULL
             ORDER BY created_at DESC LIMIT 1',
            ['uid' => $deviceUid]
        );
        if (!$link) {
            $this->jsonError('Invalid or expired web link token.', null, 401, 'AUTH_FAILED');
        }

        $db->update('monitoring_agent_links', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $link['id']]);

        $employeeId = (int) $link['employee_id'];
        $employee   = $db->fetch('SELECT * FROM employees WHERE id = :id AND deleted_at IS NULL', ['id' => $employeeId]);
        if (!$employee) {
            $this->jsonError('Employee not found.', null, 404);
        }

        $reg = $this->devices->register($employeeId, [
            'device_uid'    => $deviceUid,
            'hostname'      => $this->request->input('hostname'),
            'os_name'       => $this->request->input('os_name', 'windows'),
            'os_version'    => $this->request->input('os_version'),
            'agent_version' => $this->request->input('agent_version', '0.1.0'),
        ], true);

        if (!$reg['success']) {
            $this->jsonError($reg['message'], $reg['data'] ?? null, 403, $reg['code'] ?? null);
        }

        $policy   = $this->policies->resolveForEmployee($employeeId);
        $needsAck = $policy && !$this->policies->hasAcknowledged($employeeId, $policy);
        $siteRules = $db->tableExists('monitoring_site_rules')
            ? $this->siteActivity->rulesForAgent((int) $employee['company_id'], $employeeId)
            : ['rules' => [], 'exceptions' => []];

        $this->jsonSuccess('Authenticated via web session.', array_merge($reg['data'] ?? [], [
            'employee' => [
                'id'            => $employeeId,
                'name'          => trim(($employee['first_name'] ?? '') . ' ' . ($employee['last_name'] ?? '')),
                'employee_code' => $employee['employee_code'] ?? null,
            ],
            'policy'     => $policy ? [
                'id'                        => (int) $policy['id'],
                'name'                      => $policy['name'],
                'version'                   => (int) $policy['version'],
                'mode'                      => $policy['mode'],
                'screenshot_interval_minutes' => (int) $policy['screenshot_interval_minutes'],
                'require_acknowledgement'   => (int) $policy['require_acknowledgement'],
                'notice_title'              => $policy['notice_title'],
                'notice_text'               => $policy['notice_text'],
                'acknowledgement_required'  => $needsAck,
            ] : null,
            'site_rules' => $siteRules,
        ]));
    }

    /** @return array<string, mixed> */
    private function requireActiveSession(): array
    {
        $session = $this->request->getAttribute('monitoring_session');
        if (!$session) {
            $employeeId = (int) $this->request->getAttribute('monitoring_employee_id');
            $session = $this->sessions->findActiveForEmployee($employeeId);
        }
        if (!$session || !in_array($session['status'], ['active', 'paused', 'offline', 'stopping'], true)) {
            $this->jsonError('No active monitoring session.', null, 409);
        }
        return $session;
    }
}
