<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Models\Branch;
use App\Models\Company;
use App\Models\Department;
use App\Models\Employee;
use App\Services\Monitoring\MonitoringActivityService;
use App\Services\Monitoring\MonitoringAlertService;
use App\Services\Monitoring\MonitoringAuthorizationService;
use App\Services\Monitoring\MonitoringDeviceService;
use App\Services\Monitoring\MonitoringPolicyService;
use App\Services\Monitoring\MonitoringReportService;
use App\Services\Monitoring\MonitoringScreenshotService;
use App\Services\Monitoring\MonitoringSessionService;
use App\Services\Monitoring\MonitoringSiteActivityService;
use App\Core\Database;

class MonitoringController extends Controller
{
    private MonitoringAuthorizationService $authz;
    private MonitoringReportService $reports;
    private MonitoringActivityService $activity;
    private MonitoringScreenshotService $screenshots;
    private MonitoringDeviceService $devices;
    private MonitoringPolicyService $policies;
    private MonitoringAlertService $alerts;
    private MonitoringSessionService $sessions;
    private MonitoringSiteActivityService $siteActivity;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
        $this->authz = new MonitoringAuthorizationService($this->auth);
        $this->reports = new MonitoringReportService();
        $this->activity = new MonitoringActivityService();
        $this->screenshots = new MonitoringScreenshotService();
        $this->devices = new MonitoringDeviceService();
        $this->policies = new MonitoringPolicyService();
        $this->alerts = new MonitoringAlertService();
        $this->sessions = new MonitoringSessionService();
        $this->siteActivity = new MonitoringSiteActivityService();
    }

    private function ensureMonitoringInstalled(): void
    {
        if (!Database::getInstance()->tableExists('monitoring_policies')) {
            Session::flash(
                'error',
                'Work Monitoring is not installed yet. Run migrations 2026_07_30_work_activity_monitoring.sql then 2026_07_30_monitoring_admin_only.sql in phpMyAdmin.'
            );
            $this->redirect('/admin/dashboard');
        }
    }

    private function ensureWebActivityInstalled(): void
    {
        $this->ensureMonitoringInstalled();
        if (!Database::getInstance()->tableExists('monitoring_site_activity')) {
            Session::flash('error', 'Website activity features are not installed. Run the 2026_08_27_monitoring_web_activity.sql migration.');
            $this->redirect('/admin/monitoring');
        }
    }

    public function index(): void
    {
        $this->authorize('monitoring.view_overview');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $overview = $this->reports->overview($scope['sql'], $scope['params']);
        $live = $this->reports->liveEmployees($scope['sql'], $scope['params'], 1, 8);
        $siteSummary = Database::getInstance()->tableExists('monitoring_site_activity')
            ? $this->siteActivity->summary(['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')], $scope['sql'], $scope['params'])
            : [];
        $topDomains = Database::getInstance()->tableExists('monitoring_site_activity')
            ? $this->siteActivity->topDomains(['date_from' => date('Y-m-d'), 'date_to' => date('Y-m-d')], $scope['sql'], $scope['params'], 5)
            : [];
        $this->siteActivity->logAccess('view_overview', 'monitoring_overview', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId());

        $this->view('admin/monitoring/overview', [
            'title' => 'Work Monitoring',
            'overview' => $overview,
            'live' => $live['data'],
            'siteSummary' => $siteSummary,
            'topDomains' => $topDomains,
        ]);
    }

    public function live(): void
    {
        $this->authorize('monitoring.view_live_employees');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->reports->liveEmployees($scope['sql'], $scope['params'], $page, 30);
        $this->siteActivity->logAccess('view_live', 'monitoring_sessions', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, ['page' => $page]);
        $this->view('admin/monitoring/live', [
            'title' => 'Live Employees',
            'records' => $result,
        ]);
    }

    public function activity(): void
    {
        $this->authorizeAny(['monitoring.view_team_activity', 'monitoring.view_all_activity']);
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = array_filter([
            'employee_id' => $this->request->input('employee_id'),
            'date_from' => $this->request->input('date_from', date('Y-m-d')),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
            'application_name' => $this->request->input('application_name'),
        ], fn ($v) => $v !== null && $v !== '');
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->activity->search($filters, $scope['sql'], $scope['params'], $page, 50);
        $this->siteActivity->logAccess('view_activity', 'monitoring_activity_segments', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null, null, $filters);
        $this->view('admin/monitoring/activity', [
            'title' => 'Activity',
            'records' => $result,
            'filters' => $filters,
        ]);
    }

    public function applications(): void
    {
        $this->authorize('monitoring.view_applications');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = array_filter([
            'employee_id' => $this->request->input('employee_id'),
            'date_from' => $this->request->input('date_from', date('Y-m-d', strtotime('-7 days'))),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
        ], fn ($v) => $v !== null && $v !== '');
        $rows = $this->activity->applicationSummary($filters, $scope['sql'], $scope['params']);
        $this->siteActivity->logAccess('view_applications', 'monitoring_activity_segments', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null, null, $filters);
        $this->view('admin/monitoring/applications', [
            'title' => 'Applications',
            'rows' => $rows,
            'filters' => $filters,
        ]);
    }

    public function webActivity(): void
    {
        $this->authorizeAny(['monitoring.view_site_history', 'monitoring.view_site_reports']);
        $this->ensureWebActivityInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = array_filter([
            'employee_id' => $this->request->input('employee_id'),
            'date_from' => $this->request->input('date_from', date('Y-m-d')),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
            'domain' => $this->request->input('domain'),
            'category' => $this->request->input('category'),
        ], static fn ($value) => $value !== null && $value !== '');
        $page = max(1, (int) $this->request->input('page', 1));
        $records = $this->siteActivity->history($filters, $scope['sql'], $scope['params'], $page, 50);
        $summary = $this->siteActivity->summary($filters, $scope['sql'], $scope['params']);
        $topDomains = $this->siteActivity->topDomains($filters, $scope['sql'], $scope['params']);
        $teamSummary = $this->siteActivity->teamSummary($filters, $scope['sql'], $scope['params']);
        $this->siteActivity->logAccess('view_history', 'website_activity', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId($this->request->input('company_id')), !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null, null, $filters);
        $this->view('admin/monitoring/web-activity', compact('records', 'summary', 'topDomains', 'teamSummary', 'filters') + ['title' => 'Website Activity']);
    }

    public function siteRules(): void
    {
        $this->authorize('monitoring.manage_site_rules');
        $this->ensureWebActivityInstalled();
        $companies = $this->tenant->companies(true);
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId && !empty($companies[0]['id'])) {
            $companyId = (int) $companies[0]['id'];
        }
        $this->siteActivity->logAccess('view_site_rules', 'monitoring_site_rules', (int) ($this->user()['id'] ?? 0), $companyId ?: null);
        $this->view('admin/monitoring/site-rules', [
            'title' => 'Website Rules',
            'companyId' => $companyId,
            'companies' => $companies,
            'rules' => $companyId ? $this->siteActivity->rules((int) $companyId) : [],
            'exceptions' => $companyId ? $this->siteActivity->exceptions((int) $companyId) : [],
            'schedules' => $companyId ? $this->siteActivity->schedules((int) $companyId) : [],
            'employees' => $companyId ? (new Employee())->where(['company_id' => $companyId], 'first_name') : [],
        ]);
    }

    public function storeSiteRule(): void
    {
        $this->authorize('monitoring.manage_site_rules');
        $this->ensureWebActivityInstalled();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId) {
            throw new \App\Exceptions\HttpException('Select a company before saving a website rule.', 422);
        }
        $result = $this->siteActivity->createRule((int) $companyId, $this->request->all(), (int) ($this->user()['id'] ?? 0));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/site-rules?company_id=' . (int) $companyId);
    }

    public function storeSchedule(): void
    {
        $this->authorize('monitoring.manage_schedules');
        $this->ensureWebActivityInstalled();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId) {
            throw new \App\Exceptions\HttpException('Select a company before saving a schedule.', 422);
        }
        $result = $this->siteActivity->createSchedule((int) $companyId, $this->request->all(), (int) ($this->user()['id'] ?? 0));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/site-rules?company_id=' . (int) $companyId);
    }

    public function assignSchedule(): void
    {
        $this->authorize('monitoring.manage_schedules');
        $this->ensureWebActivityInstalled();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId) {
            throw new \App\Exceptions\HttpException('Select a company before assigning a schedule.', 422);
        }
        $result = $this->siteActivity->assignSchedule((int) $companyId, (int) $this->request->input('schedule_id', 0), (int) $this->request->input('employee_id', 0), (int) ($this->user()['id'] ?? 0));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/site-rules?company_id=' . (int) $companyId);
    }

    public function reviewException(int $id): void
    {
        $this->authorize('monitoring.manage_site_rules');
        $row = $this->tenant->record('monitoring_site_exceptions', $id);
        $result = $this->siteActivity->reviewException($id, (string) $this->request->input('status', 'rejected'), (int) ($this->user()['id'] ?? 0));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/site-rules?company_id=' . (int) $row['company_id']);
    }

    public function screenshots(): void
    {
        $this->authorize('monitoring.view_screenshots');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = array_filter([
            'employee_id' => $this->request->input('employee_id'),
            'date_from' => $this->request->input('date_from', date('Y-m-d')),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
        ], fn ($v) => $v !== null && $v !== '');
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->screenshots->search($filters, $scope['sql'], $scope['params'], $page, 24);
        $this->siteActivity->logAccess('view_screenshots', 'monitoring_screenshots', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), !empty($filters['employee_id']) ? (int) $filters['employee_id'] : null, null, $filters);
        $this->view('admin/monitoring/screenshots', [
            'title' => 'Screenshots',
            'records' => $result,
            'filters' => $filters,
        ]);
    }

    public function deleteScreenshot(int $id): void
    {
        $this->authorize('monitoring.delete_screenshots');
        $row = $this->screenshots->find($id);
        if (!$row || !$this->authz->canAccessScreenshot($row)) {
            Session::flash('error', 'Screenshot not found or access denied.');
            $this->redirect('/admin/monitoring/screenshots');
            return;
        }
        $this->screenshots->softDelete($id, $this->user()['id'] ?? null);
        Session::flash('success', 'Screenshot deleted.');
        $this->redirect('/admin/monitoring/screenshots');
    }

    public function devices(): void
    {
        $this->authorize('monitoring.manage_devices');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = array_filter([
            'status' => $this->request->input('status'),
            'q' => $this->request->input('q'),
        ], fn ($v) => $v !== null && $v !== '');
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->devices->search($filters, $scope['sql'], $scope['params'], $page, 20);
        $this->siteActivity->logAccess('view_devices', 'monitoring_devices', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, $filters);
        $this->view('admin/monitoring/devices', [
            'title' => 'Devices',
            'records' => $result,
            'filters' => $filters,
        ]);
    }

    public function approveDevice(int $id): void
    {
        $this->authorize('monitoring.manage_devices');
        $device = $this->devices->find($id);
        if (!$device || !$this->authz->canAccessEmployee((int) $device['employee_id'])) {
            Session::flash('error', 'Device not found or access denied.');
            $this->redirect('/admin/monitoring/devices');
            return;
        }
        $this->devices->approve($id, $this->user()['id'] ?? null);
        Session::flash('success', 'Device approved.');
        $this->redirect('/admin/monitoring/devices');
    }

    public function revokeDevice(int $id): void
    {
        $this->authorize('monitoring.manage_devices');
        $device = $this->devices->find($id);
        if (!$device || !$this->authz->canAccessEmployee((int) $device['employee_id'])) {
            Session::flash('error', 'Device not found or access denied.');
            $this->redirect('/admin/monitoring/devices');
            return;
        }
        $this->devices->revoke($id, $this->user()['id'] ?? null);
        Session::flash('success', 'Device revoked.');
        $this->redirect('/admin/monitoring/devices');
    }

    public function policies(): void
    {
        $this->authorize('monitoring.manage_policies');
        $this->ensureMonitoringInstalled();
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->policies->listPolicies($companyId ? (int) $companyId : null, $page, 20);
        $this->siteActivity->logAccess('view_policies', 'monitoring_policies', (int) ($this->user()['id'] ?? 0), $companyId ? (int) $companyId : null, null, null, ['page' => $page]);
        $this->view('admin/monitoring/policies', [
            'title' => 'Policies',
            'records' => $result,
            'companies' => $this->tenant->companies(true),
            'branches' => $companyId ? (new Branch())->where(['company_id' => $companyId], 'name') : (new Branch())->all('name'),
            'departments' => $companyId ? (new Department())->where(['company_id' => $companyId], 'name') : (new Department())->all('name'),
            'intervals' => config('app.monitoring.allowed_intervals', [5, 10, 15, 20, 30]),
        ]);
    }

    public function storePolicy(): void
    {
        $this->authorize('monitoring.manage_policies');
        $this->ensureMonitoringInstalled();
        $data = $this->request->all();
        $data['company_id'] = $this->tenant->resolveCompanyId($data['company_id'] ?? null);
        $result = $this->policies->create($data, $this->user()['id'] ?? null);
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/policies');
    }

    public function updatePolicy(int $id): void
    {
        $this->authorize('monitoring.manage_policies');
        $this->tenant->record('monitoring_policies', $id);
        $payload = $this->request->all();
        if (!empty($payload['company_id'])) {
            $this->tenant->assertCompany((int) $payload['company_id']);
        }
        $result = $this->policies->update($id, $payload, $this->user()['id'] ?? null, true);
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/policies');
    }

    public function assignPolicy(): void
    {
        $this->authorize('monitoring.assign_policies');
        $this->ensureMonitoringInstalled();
        $payload = $this->request->all();
        $policy = $this->tenant->record('monitoring_policies', (int) ($payload['policy_id'] ?? 0));
        $payload['company_id'] = (int) $policy['company_id'];
        if (!empty($payload['employee_id'])) {
            $employee = $this->tenant->employee((int) $payload['employee_id']);
            if ((int) $employee['company_id'] !== (int) $policy['company_id']) {
                throw new \App\Exceptions\HttpException('Employee and policy must belong to the same company.', 422);
            }
        }
        $result = $this->policies->assign($payload, $this->user()['id'] ?? null);
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/policies');
    }

    public function alerts(): void
    {
        $this->authorize('monitoring.access');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = [
            'is_resolved' => $this->request->input('is_resolved', '0'),
            'alert_type' => $this->request->input('alert_type'),
        ];
        $page = max(1, (int) $this->request->input('page', 1));
        $result = $this->alerts->search($filters, $scope['sql'], $scope['params'], $page, 30);
        $this->siteActivity->logAccess('view_alerts', 'monitoring_alerts', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, $filters);
        $this->view('admin/monitoring/alerts', [
            'title' => 'Alerts',
            'records' => $result,
            'filters' => $filters,
        ]);
    }

    public function resolveAlert(int $id): void
    {
        $this->authorize('monitoring.access');
        $this->tenant->record('monitoring_alerts', $id);
        $this->alerts->resolve($id, $this->user()['id'] ?? null);
        Session::flash('success', 'Alert resolved.');
        $this->redirect('/admin/monitoring/alerts');
    }

    public function reports(): void
    {
        $this->authorize('monitoring.view_reports');
        $this->ensureMonitoringInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $filters = [
            'date_from' => $this->request->input('date_from', date('Y-m-d', strtotime('-7 days'))),
            'date_to' => $this->request->input('date_to', date('Y-m-d')),
        ];
        $rows = $this->reports->dailyReport($filters, $scope['sql'], $scope['params']);
        $siteSummary = Database::getInstance()->tableExists('monitoring_site_activity')
            ? $this->siteActivity->summary($filters, $scope['sql'], $scope['params'])
            : [];
        $topDomains = Database::getInstance()->tableExists('monitoring_site_activity')
            ? $this->siteActivity->topDomains($filters, $scope['sql'], $scope['params'])
            : [];
        if (Database::getInstance()->tableExists('monitoring_site_activity')) {
            $this->siteActivity->logAccess('view_report', 'website_activity_report', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, $filters);
        }
        $this->view('admin/monitoring/reports', [
            'title' => 'Monitoring Reports',
            'rows' => $rows,
            'filters' => $filters,
            'siteSummary' => $siteSummary,
            'topDomains' => $topDomains,
        ]);
    }

    public function disputes(): void
    {
        $this->authorize('monitoring.review_disputes');
        $this->ensureWebActivityInstalled();
        $scope = $this->authz->employeeScopeSql('e');
        $page = max(1, (int) $this->request->input('page', 1));
        $records = $this->siteActivity->disputes($scope['sql'], $scope['params'], $page, 30);
        $this->siteActivity->logAccess('view_disputes', 'monitoring_disputes', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, []);
        $this->view('admin/monitoring/disputes', ['title' => 'Employee Disputes', 'records' => $records]);
    }

    public function reviewDispute(int $id): void
    {
        $this->authorize('monitoring.review_disputes');
        $row = $this->tenant->record('monitoring_disputes', $id);
        $result = $this->siteActivity->reviewDispute($id, (string) $this->request->input('status', 'rejected'), (int) ($this->user()['id'] ?? 0), $this->request->input('resolution_note'));
        Session::flash($result['success'] ? 'success' : 'error', $result['message']);
        $this->redirect('/admin/monitoring/disputes?company_id=' . (int) $row['company_id']);
    }

    public function audit(): void
    {
        $this->authorize('monitoring.view_audit_logs');
        $this->ensureMonitoringInstalled();
        $page = max(1, (int) $this->request->input('page', 1));
        $perPage = 40;
        $offset = ($page - 1) * $perPage;
        $db = Database::getInstance();
        $scope = $this->tenant->sql('company_id', 'monitoring_audit_company');
        $accessScope = $this->tenant->sql('company_id', 'monitoring_access_company');
        $queryParams = array_merge($scope['params'], $accessScope['params']);
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM (
                SELECT id FROM audit_logs WHERE table_name LIKE 'monitoring_%' AND ({$scope['sql']})
                UNION ALL
                SELECT id FROM monitoring_access_logs WHERE ({$accessScope['sql']})
             ) AS monitoring_log_count",
            $queryParams
        );
        $rows = $db->fetchAll(
            "SELECT id, user_id, action, table_name, record_id, old_values, new_values, ip_address, created_at
             FROM audit_logs WHERE table_name LIKE 'monitoring_%' AND ({$scope['sql']})
             UNION ALL
             SELECT id, actor_user_id AS user_id, action, resource_type AS table_name, resource_id AS record_id,
                    NULL AS old_values, filters AS new_values, ip_address, created_at
             FROM monitoring_access_logs WHERE ({$accessScope['sql']})
             ORDER BY created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $queryParams
        );
        $this->siteActivity->logAccess('view_audit', 'monitoring_access_logs', (int) ($this->user()['id'] ?? 0), $this->tenant->resolveCompanyId(), null, null, ['page' => $page]);
        $this->view('admin/monitoring/audit', [
            'title' => 'Monitoring Audit Logs',
            'records' => ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage],
        ]);
    }

    public function stopSession(int $id): void
    {
        $this->authorize('monitoring.stop_session');
        $session = $this->sessions->find($id);
        if (!$session || !$this->authz->canAccessEmployee((int) $session['employee_id'])) {
            Session::flash('error', 'Session not found or access denied.');
            $this->redirect('/admin/monitoring/live');
            return;
        }
        $this->sessions->requestRemoteStop($id, $this->user()['id'] ?? null);
        Session::flash('success', 'Remote stop requested.');
        $this->redirect('/admin/monitoring/live');
    }

    /** @param list<string> $permissions */
    private function authorizeAny(array $permissions): void
    {
        foreach ($permissions as $perm) {
            if ($this->auth->can($perm)) {
                return;
            }
        }
        $this->authorize($permissions[0]);
    }
}
