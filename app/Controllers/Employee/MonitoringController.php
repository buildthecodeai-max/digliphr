<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Exceptions\HttpException;
use App\Services\Monitoring\MonitoringPolicyService;
use App\Services\Monitoring\MonitoringSiteActivityService;

/**
 * Employee portal exposes only the employee's own domain activity and
 * explanation/exception workflows. Administrative monitoring remains separate.
 */
class MonitoringController extends Controller
{
    private MonitoringSiteActivityService $siteActivity;
    private MonitoringPolicyService $policies;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->siteActivity = new MonitoringSiteActivityService();
        $this->policies = new MonitoringPolicyService();
    }

    public function activity(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile is required.', 403);
        }
        $records = $this->siteActivity->employeeHistory((int) $employee['id']);
        $scope = 'e.id = :employee_id';
        $summary = $this->siteActivity->summary(['employee_id' => (int) $employee['id']], $scope, ['employee_id' => (int) $employee['id']]);
        $this->view('employee/monitoring/activity', [
            'title' => 'My Work Activity',
            'records' => $records,
            'summary' => $summary,
        ], 'layouts/employee');
    }

    public function submitDispute(): void
    {
        $employee = $this->employee();
        $result = $this->siteActivity->submitDispute((int) ($employee['id'] ?? 0), (int) $this->request->input('event_id', 0), (string) $this->request->input('reason', ''));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/employee/monitoring/activity');
    }

    public function requestException(): void
    {
        $employee = $this->employee();
        $result = $this->siteActivity->requestException((int) ($employee['id'] ?? 0), (string) $this->request->input('domain', ''), (string) $this->request->input('reason', ''), (string) $this->request->input('context', 'training'));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/employee/monitoring/activity');
    }

    public function acknowledge(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile is required.', 403);
        }
        $policy = $this->policies->resolveForEmployee((int) $employee['id']);
        if (!$policy) {
            Session::flash('error', 'No monitoring policy is assigned to you.');
            $this->redirect('/employee/dashboard');
            return;
        }
        $alreadyAcknowledged = $this->policies->hasAcknowledged((int) $employee['id'], $policy);
        $this->view('employee/monitoring/acknowledge', [
            'title' => 'Monitoring Policy',
            'policy' => $policy,
            'alreadyAcknowledged' => $alreadyAcknowledged,
        ], 'layouts/employee');
    }

    public function acknowledgePost(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new HttpException('Employee profile is required.', 403);
        }
        $policyId = (int) $this->request->input('policy_id', 0);
        $result = $this->policies->acknowledge(
            (int) $employee['id'],
            $policyId,
            null,
            $this->request->ip(),
            $this->request->userAgent()
        );
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/employee/monitoring/acknowledge');
    }

    public function forbidden(): void
    {
        throw new HttpException('Work monitoring is managed by your company administrators and is not available in the employee portal.', 403);
    }

    /** Page that generates a one-time token the desktop agent exchanges for credentials. */
    public function agentConnect(): void
    {
        $db = \App\Core\Database::getInstance();
        $employee = $this->employee();
        if (!$employee) {
            $this->redirect('/login');
        }

        $deviceUid = trim((string) $this->request->query('device_uid', ''));
        $token     = null;
        $expires   = null;

        if ($deviceUid !== '' && $db->tableExists('monitoring_agent_links')) {
            $plain   = bin2hex(random_bytes(16));
            $hash    = hash('sha256', $plain);
            $expires = date('Y-m-d H:i:s', time() + 300);

            $db->query(
                'INSERT INTO monitoring_agent_links (device_uid, employee_id, token_hash, expires_at, created_at)
                 VALUES (:uid, :eid, :hash, :exp, NOW())
                 ON DUPLICATE KEY UPDATE token_hash = VALUES(token_hash), expires_at = VALUES(expires_at),
                                          used_at = NULL, created_at = NOW()',
                ['uid' => $deviceUid, 'eid' => (int) $employee['id'], 'hash' => $hash, 'exp' => $expires]
            );
            $token = $plain;
        }

        $this->view('employee/monitoring/agent-connect', [
            'title'     => 'Connect Desktop Agent',
            'deviceUid' => $deviceUid,
            'token'     => $token,
            'expires'   => $expires,
            'employee'  => $employee,
        ]);
    }
}
