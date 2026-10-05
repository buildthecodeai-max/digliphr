<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;
use App\Services\AuditService;

final class MonitoringSiteActivityService
{
    private Database $db;
    private MonitoringAlertService $alerts;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->alerts = new MonitoringAlertService();
        $this->audit = new AuditService();
    }

    /**
     * Store one domain event and return the enforcement instruction for the agent.
     * Full URLs, query strings and page contents are never persisted.
     */
    public function ingest(array $session, array $segment): ?array
    {
        if (!$this->db->tableExists('monitoring_site_activity')) {
            return null;
        }

        $domain = $this->normalizeDomain(
            $segment['domain'] ?? $segment['website'] ?? $segment['url_domain'] ?? $segment['application_name'] ?? ''
        );
        if ($domain === '') {
            return null;
        }

        $startedAt = (string) ($segment['started_at'] ?? date('Y-m-d H:i:s'));
        $endedAt = !empty($segment['ended_at']) ? (string) $segment['ended_at'] : null;
        $duration = (int) ($segment['duration_seconds'] ?? 0);
        if ($duration <= 0 && $endedAt) {
            $duration = max(0, strtotime($endedAt) - strtotime($startedAt));
        }
        $duration = min(86400, max(0, $duration));
        $context = $this->normalizeContext($segment['context'] ?? 'any');
        [$workState, $collect, $allowedDuration] = $this->workState((int) $session['employee_id'], (int) $session['company_id'], $startedAt, $endedAt, $duration);
        if (!$collect) {
            return ['domain' => $domain, 'category' => 'neutral', 'action' => 'allow', 'work_state' => $workState, 'collected' => false];
        }
        if ($allowedDuration !== null) {
            $duration = min($duration, $allowedDuration);
        }
        if ($duration <= 0) {
            return ['domain' => $domain, 'category' => 'neutral', 'action' => 'allow', 'work_state' => $workState, 'collected' => false];
        }

        $classification = $this->classify((int) $session['company_id'], (int) $session['employee_id'], $domain, $context);
        $sourceId = (string) ($segment['client_segment_id'] ?? '');
        if ($sourceId === '') {
            $sourceId = substr(hash('sha256', $domain . '|' . $startedAt . '|' . $endedAt . '|' . $session['id']), 0, 64);
        }

        $existing = $this->db->fetch(
            'SELECT id FROM monitoring_site_activity WHERE session_id = :sid AND source_segment_id = :source LIMIT 1',
            ['sid' => (int) $session['id'], 'source' => $sourceId]
        );
        if ($existing) {
            return [
                'site_activity_id' => (int) $existing['id'],
                'domain' => $domain,
                'category' => $classification['category'],
                'action' => $classification['action'],
                'work_state' => $workState,
                'duplicate' => true,
            ];
        }

        $activityId = $this->db->insert('monitoring_site_activity', [
            'company_id' => (int) $session['company_id'],
            'employee_id' => (int) $session['employee_id'],
            'session_id' => (int) $session['id'],
            'device_id' => $session['device_id'] ?? null,
            'domain' => $domain,
            'category' => $classification['category'],
            'policy_action' => $classification['action'],
            'context' => $context,
            'work_state' => $workState,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'duration_seconds' => $duration,
            'source_segment_id' => $sourceId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $event = null;
        if (in_array($classification['category'], ['distracting', 'blocked'], true)
            && $duration >= (int) $classification['threshold_seconds']) {
            $event = $this->createDistractionEvent($activityId, $session, $domain, $classification, $duration);
        }

        return [
            'site_activity_id' => $activityId,
            'domain' => $domain,
            'category' => $classification['category'],
            'action' => $classification['action'],
            'threshold_seconds' => (int) $classification['threshold_seconds'],
            'work_state' => $workState,
            'distraction_event_id' => $event,
        ];
    }

    /** @return array{data: list<array>, total: int, page: int, per_page: int} */
    public function history(array $filters, string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 50): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        if (!empty($filters['employee_id'])) {
            $where[] = 'a.employee_id = :site_employee_id';
            $params['site_employee_id'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['date_from'])) {
            $where[] = 'a.started_at >= :site_date_from';
            $params['site_date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $where[] = 'a.started_at <= :site_date_to';
            $params['site_date_to'] = $filters['date_to'] . ' 23:59:59';
        }
        if (!empty($filters['domain'])) {
            $where[] = 'a.domain LIKE :site_domain';
            $params['site_domain'] = '%' . strtolower(trim((string) $filters['domain'])) . '%';
        }
        if (!empty($filters['category'])) {
            $where[] = 'a.category = :site_category';
            $params['site_category'] = $filters['category'];
        }
        $sqlWhere = implode(' AND ', $where);
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_site_activity a INNER JOIN employees e ON e.id = a.employee_id WHERE {$sqlWhere}",
            $params
        );
        $offset = max(0, ($page - 1) * $perPage);
        $rows = $this->db->fetchAll(
            "SELECT a.*, x.id AS distraction_event_id, e.first_name, e.last_name, e.employee_code
             FROM monitoring_site_activity a INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN monitoring_distraction_events x ON x.site_activity_id = a.id
             WHERE {$sqlWhere} ORDER BY a.started_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );
        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /** @return array{productive_minutes: int, neutral_minutes: int, distracting_minutes: int, blocked_minutes: int, warnings: int} */
    public function summary(array $filters, string $scopeSql, array $scopeParams): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        $from = $filters['date_from'] ?? date('Y-m-d');
        $to = $filters['date_to'] ?? $from;
        $where[] = 'a.started_at BETWEEN :summary_from AND :summary_to';
        $params['summary_from'] = $from . ' 00:00:00';
        $params['summary_to'] = $to . ' 23:59:59';
        if (!empty($filters['employee_id'])) {
            $where[] = 'a.employee_id = :summary_employee';
            $params['summary_employee'] = (int) $filters['employee_id'];
        }
        $row = $this->db->fetch(
            "SELECT
                COALESCE(SUM(CASE WHEN a.category = 'productive' THEN a.duration_seconds ELSE 0 END), 0) AS productive,
                COALESCE(SUM(CASE WHEN a.category = 'neutral' THEN a.duration_seconds ELSE 0 END), 0) AS neutral,
                COALESCE(SUM(CASE WHEN a.category = 'distracting' THEN a.duration_seconds ELSE 0 END), 0) AS distracting,
                COALESCE(SUM(CASE WHEN a.category = 'blocked' THEN a.duration_seconds ELSE 0 END), 0) AS blocked,
                COALESCE(SUM(CASE WHEN a.policy_action IN ('notify','warn','block') THEN 1 ELSE 0 END), 0) AS warnings
             FROM monitoring_site_activity a INNER JOIN employees e ON e.id = a.employee_id
             WHERE " . implode(' AND ', $where),
            $params
        );
        return [
            'productive_minutes' => (int) round(((int) ($row['productive'] ?? 0)) / 60),
            'neutral_minutes' => (int) round(((int) ($row['neutral'] ?? 0)) / 60),
            'distracting_minutes' => (int) round(((int) ($row['distracting'] ?? 0)) / 60),
            'blocked_minutes' => (int) round(((int) ($row['blocked'] ?? 0)) / 60),
            'warnings' => (int) ($row['warnings'] ?? 0),
        ];
    }

    /** @return list<array> */
    public function topDomains(array $filters, string $scopeSql, array $scopeParams, int $limit = 10): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        $from = $filters['date_from'] ?? date('Y-m-d');
        $to = $filters['date_to'] ?? $from;
        $where[] = 'a.started_at BETWEEN :top_from AND :top_to';
        $params['top_from'] = $from . ' 00:00:00';
        $params['top_to'] = $to . ' 23:59:59';
        $where[] = "a.category IN ('distracting','blocked')";
        return $this->db->fetchAll(
            "SELECT a.domain, a.category, SUM(a.duration_seconds) AS total_seconds, COUNT(*) AS visits
             FROM monitoring_site_activity a INNER JOIN employees e ON e.id = a.employee_id
             WHERE " . implode(' AND ', $where) . " GROUP BY a.domain, a.category ORDER BY total_seconds DESC LIMIT {$limit}",
            $params
        );
    }

    /** @return list<array> */
    public function teamSummary(array $filters, string $scopeSql, array $scopeParams, int $limit = 50): array
    {
        $where = ["({$scopeSql})"];
        $params = $scopeParams;
        $from = $filters['date_from'] ?? date('Y-m-d');
        $to = $filters['date_to'] ?? $from;
        $where[] = 'a.started_at BETWEEN :team_from AND :team_to';
        $params['team_from'] = $from . ' 00:00:00';
        $params['team_to'] = $to . ' 23:59:59';
        if (!empty($filters['employee_id'])) {
            $where[] = 'a.employee_id = :team_employee';
            $params['team_employee'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['domain'])) {
            $where[] = 'a.domain LIKE :team_domain';
            $params['team_domain'] = '%' . strtolower(trim((string) $filters['domain'])) . '%';
        }
        if (!empty($filters['category'])) {
            $where[] = 'a.category = :team_category';
            $params['team_category'] = $filters['category'];
        }
        return $this->db->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, e.first_name, e.last_name,
                    COALESCE(SUM(CASE WHEN a.category = 'productive' THEN a.duration_seconds ELSE 0 END), 0) AS productive_seconds,
                    COALESCE(SUM(CASE WHEN a.category = 'neutral' THEN a.duration_seconds ELSE 0 END), 0) AS neutral_seconds,
                    COALESCE(SUM(CASE WHEN a.category = 'distracting' THEN a.duration_seconds ELSE 0 END), 0) AS distracting_seconds,
                    COALESCE(SUM(CASE WHEN a.category = 'blocked' THEN a.duration_seconds ELSE 0 END), 0) AS blocked_seconds,
                    COUNT(*) AS visits, MAX(a.started_at) AS last_activity
             FROM monitoring_site_activity a INNER JOIN employees e ON e.id = a.employee_id
             WHERE " . implode(' AND ', $where) . "
             GROUP BY e.id, e.employee_code, e.first_name, e.last_name
             ORDER BY distracting_seconds DESC, blocked_seconds DESC, productive_seconds DESC
             LIMIT {$limit}",
            $params
        );
    }

    /** @return array{data: list<array>, total: int, page: int, per_page: int} */
    public function disputes(string $scopeSql, array $scopeParams, int $page = 1, int $perPage = 30): array
    {
        $offset = max(0, ($page - 1) * $perPage);
        $where = "d.status = 'open' AND ({$scopeSql})";
        $total = (int) $this->db->fetchColumn(
            "SELECT COUNT(*) FROM monitoring_disputes d INNER JOIN employees e ON e.id = d.employee_id WHERE {$where}",
            $scopeParams
        );
        $rows = $this->db->fetchAll(
            "SELECT d.*, e.first_name, e.last_name, e.employee_code, x.domain, x.message, x.created_at AS event_created_at
             FROM monitoring_disputes d INNER JOIN employees e ON e.id = d.employee_id
             INNER JOIN monitoring_distraction_events x ON x.id = d.distraction_event_id
             WHERE {$where} ORDER BY d.created_at DESC LIMIT {$perPage} OFFSET {$offset}",
            $scopeParams
        );
        return ['data' => $rows, 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    public function employeeHistory(int $employeeId, int $page = 1, int $perPage = 50): array
    {
        $employee = $this->db->fetch('SELECT id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL', ['id' => $employeeId]);
        if (!$employee) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }
        return $this->history(['employee_id' => $employeeId, 'date_from' => date('Y-m-d', strtotime('-7 days')), 'date_to' => date('Y-m-d')], 'e.company_id = :employee_history_company', ['employee_history_company' => (int) $employee['company_id']], $page, $perPage);
    }

    /** @return list<array> */
    public function rules(int $companyId): array
    {
        return $this->db->fetchAll('SELECT * FROM monitoring_site_rules WHERE company_id = :cid ORDER BY is_active DESC, CHAR_LENGTH(domain_pattern) DESC, domain_pattern', ['cid' => $companyId]);
    }

    /**
     * Return the domain policy the desktop agent needs for local warn/block decisions.
     * Only domain patterns and classifications are returned; never URLs or page contents.
     */
    public function rulesForAgent(int $companyId, int $employeeId): array
    {
        return [
            'rules' => $this->db->fetchAll(
                'SELECT domain_pattern, category, action, threshold_seconds, context
                 FROM monitoring_site_rules
                 WHERE company_id = :cid AND is_active = 1
                 ORDER BY CHAR_LENGTH(domain_pattern) DESC, domain_pattern',
                ['cid' => $companyId]
            ),
            'exceptions' => $this->db->fetchAll(
                'SELECT domain_pattern, override_category, starts_at, ends_at
                 FROM monitoring_site_exceptions
                 WHERE company_id = :cid AND employee_id = :eid AND status = "approved"
                   AND (starts_at IS NULL OR starts_at <= NOW())
                   AND (ends_at IS NULL OR ends_at >= NOW())
                 ORDER BY CHAR_LENGTH(domain_pattern) DESC, domain_pattern',
                ['cid' => $companyId, 'eid' => $employeeId]
            ),
        ];
    }

    /** @return list<array> */
    public function exceptions(int $companyId, string $status = 'pending'): array
    {
        if ($status === '') {
            return $this->db->fetchAll(
                'SELECT x.*, e.first_name, e.last_name, e.employee_code
                 FROM monitoring_site_exceptions x
                 INNER JOIN employees e ON e.id = x.employee_id
                 WHERE x.company_id = :cid
                 ORDER BY x.created_at DESC LIMIT 200',
                ['cid' => $companyId]
            );
        }
        return $this->db->fetchAll(
            'SELECT x.*, e.first_name, e.last_name, e.employee_code
             FROM monitoring_site_exceptions x
             INNER JOIN employees e ON e.id = x.employee_id
             WHERE x.company_id = :cid AND x.status = :status
             ORDER BY x.created_at DESC LIMIT 200',
            ['cid' => $companyId, 'status' => $status]
        );
    }

    /** @return list<array> */
    public function schedules(int $companyId): array
    {
        return $this->db->fetchAll('SELECT s.*, COUNT(a.id) AS assigned_employees FROM monitoring_work_schedules s LEFT JOIN monitoring_schedule_assignments a ON a.schedule_id = s.id AND a.is_active = 1 WHERE s.company_id = :cid GROUP BY s.id ORDER BY s.is_active DESC, s.name', ['cid' => $companyId]);
    }

    public function createRule(int $companyId, array $payload, int $userId): array
    {
        $domain = $this->normalizeDomain($payload['domain_pattern'] ?? '');
        if ($domain === '') {
            return ['success' => false, 'message' => 'Enter a valid domain, such as youtube.com.'];
        }
        $category = in_array(($payload['category'] ?? ''), ['productive', 'neutral', 'distracting', 'blocked'], true) ? $payload['category'] : 'neutral';
        $action = in_array(($payload['action'] ?? ''), ['allow', 'notify', 'warn', 'block'], true) ? $payload['action'] : 'allow';
        $context = in_array(($payload['context'] ?? ''), ['any', 'training', 'research'], true) ? $payload['context'] : 'any';
        try {
            $id = $this->db->insert('monitoring_site_rules', [
                'company_id' => $companyId,
                'domain_pattern' => $domain,
                'category' => $category,
                'action' => $action,
                'threshold_seconds' => max(0, min(86400, (int) ($payload['threshold_seconds'] ?? 300))),
                'context' => $context,
                'is_active' => !array_key_exists('is_active', $payload) || !empty($payload['is_active']) ? 1 : 0,
                'created_by' => $userId,
                'updated_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => str_contains($e->getMessage(), 'Duplicate') ? 'A rule already exists for this domain and context.' : 'Unable to save website rule.'];
        }
        $this->audit->log('create', 'monitoring_site_rules', $id, null, ['company_id' => $companyId, 'domain_pattern' => $domain, 'category' => $category, 'action' => $action], $userId);
        return ['success' => true, 'message' => 'Website rule saved.'];
    }

    public function createSchedule(int $companyId, array $payload, int $userId): array
    {
        $days = $payload['work_days'] ?? ['mon', 'tue', 'wed', 'thu', 'fri'];
        if (!is_array($days)) {
            $days = ['mon', 'tue', 'wed', 'thu', 'fri'];
        }
        $breakWindows = $payload['break_windows'] ?? [];
        if (!is_array($breakWindows)) {
            $breakWindows = [];
        }
        if (empty($breakWindows) && !empty($payload['break_start']) && !empty($payload['break_end'])) {
            $breakWindows = [[
                'start' => substr((string) $payload['break_start'], 0, 8),
                'end' => substr((string) $payload['break_end'], 0, 8),
            ]];
        }
        $id = $this->db->insert('monitoring_work_schedules', [
            'company_id' => $companyId,
            'name' => substr(trim((string) ($payload['name'] ?? 'Standard work hours')), 0, 150),
            'timezone' => substr(trim((string) ($payload['timezone'] ?? 'Asia/Karachi')), 0, 80),
            'work_days' => json_encode(array_values($days), JSON_UNESCAPED_UNICODE),
            'start_time' => $payload['start_time'] ?? '09:00:00',
            'end_time' => $payload['end_time'] ?? '18:00:00',
            'break_windows' => json_encode(array_values($breakWindows), JSON_UNESCAPED_UNICODE),
            'is_active' => 1,
            'created_by' => $userId,
            'updated_by' => $userId,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit->log('create', 'monitoring_work_schedules', $id, null, ['company_id' => $companyId, 'name' => $payload['name'] ?? 'Standard work hours'], $userId);
        return ['success' => true, 'message' => 'Work schedule created.'];
    }

    public function assignSchedule(int $companyId, int $scheduleId, int $employeeId, int $userId): array
    {
        $schedule = $this->db->fetch('SELECT id FROM monitoring_work_schedules WHERE id = :sid AND company_id = :cid AND is_active = 1 LIMIT 1', ['sid' => $scheduleId, 'cid' => $companyId]);
        $employee = $this->db->fetch('SELECT id FROM employees WHERE id = :eid AND company_id = :cid AND deleted_at IS NULL LIMIT 1', ['eid' => $employeeId, 'cid' => $companyId]);
        if (!$schedule || !$employee) {
            return ['success' => false, 'message' => 'Schedule or employee does not belong to this company.'];
        }
        $this->db->query(
            'INSERT INTO monitoring_schedule_assignments (company_id, schedule_id, employee_id, is_active, created_by, created_at)
             VALUES (:cid, :sid, :eid, 1, :uid, NOW())
             ON DUPLICATE KEY UPDATE is_active = 1, created_by = VALUES(created_by)',
            ['cid' => $companyId, 'sid' => $scheduleId, 'eid' => $employeeId, 'uid' => $userId]
        );
        $this->audit->log('update', 'monitoring_schedule_assignments', $scheduleId, null, ['company_id' => $companyId, 'schedule_id' => $scheduleId, 'employee_id' => $employeeId], $userId);
        return ['success' => true, 'message' => 'Schedule assigned.'];
    }

    public function requestException(int $employeeId, string $domain, string $reason, string $context = 'training'): array
    {
        $employee = $this->db->fetch('SELECT id, user_id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL', ['id' => $employeeId]);
        $domain = $this->normalizeDomain($domain);
        $context = in_array($context, ['training', 'research'], true) ? $context : 'training';
        if (!$employee || $domain === '' || trim($reason) === '') {
            return ['success' => false, 'message' => 'Domain and reason are required.'];
        }
        $id = $this->db->insert('monitoring_site_exceptions', [
            'company_id' => (int) $employee['company_id'],
            'employee_id' => $employeeId,
            'domain_pattern' => $domain,
            'override_category' => $context === 'research' ? 'neutral' : 'productive',
            'reason' => substr(trim($reason), 0, 500),
            'status' => 'pending',
            'created_by' => !empty($employee['user_id']) ? (int) $employee['user_id'] : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->audit->log('create', 'monitoring_site_exceptions', $id, null, ['company_id' => (int) $employee['company_id'], 'employee_id' => $employeeId, 'domain_pattern' => $domain], !empty($employee['user_id']) ? (int) $employee['user_id'] : null);
        return ['success' => true, 'message' => 'Your exception request was sent for review.'];
    }

    public function reviewException(int $exceptionId, string $status, int $userId): array
    {
        if (!in_array($status, ['approved', 'rejected'], true)) {
            return ['success' => false, 'message' => 'Invalid exception status.'];
        }
        $row = $this->db->fetch('SELECT * FROM monitoring_site_exceptions WHERE id = :id LIMIT 1', ['id' => $exceptionId]);
        if (!$row) {
            return ['success' => false, 'message' => 'Exception request not found.'];
        }
        $this->db->update('monitoring_site_exceptions', ['status' => $status, 'reviewed_by' => $userId, 'reviewed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $exceptionId]);
        $this->audit->log('update', 'monitoring_site_exceptions', $exceptionId, $row, ['status' => $status, 'company_id' => $row['company_id']], $userId);
        return ['success' => true, 'message' => 'Exception request updated.'];
    }

    public function logAccess(string $action, string $resourceType, int $userId, ?int $companyId = null, ?int $employeeId = null, ?int $resourceId = null, array $filters = []): void
    {
        try {
            $this->db->insert('monitoring_access_logs', [
                'company_id' => $companyId,
                'actor_user_id' => $userId,
                'action' => $action,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'employee_id' => $employeeId,
                'filters' => $filters !== [] ? json_encode($filters, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Access logging must not interrupt monitoring views.
        }
    }

    public function submitDispute(int $employeeId, int $eventId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Please explain why this activity is incorrect.'];
        }
        $event = $this->db->fetch(
            'SELECT x.*, e.company_id FROM monitoring_distraction_events x INNER JOIN employees e ON e.id = x.employee_id WHERE x.id = :id AND x.employee_id = :eid AND e.deleted_at IS NULL LIMIT 1',
            ['id' => $eventId, 'eid' => $employeeId]
        );
        if (!$event) {
            return ['success' => false, 'message' => 'Distraction event not found.'];
        }
        $existing = $this->db->fetch('SELECT id FROM monitoring_disputes WHERE distraction_event_id = :event LIMIT 1', ['event' => $eventId]);
        if ($existing) {
            return ['success' => false, 'message' => 'This event already has an open dispute.'];
        }
        $id = $this->db->insert('monitoring_disputes', [
            'company_id' => (int) $event['company_id'],
            'employee_id' => $employeeId,
            'distraction_event_id' => $eventId,
            'reason' => substr($reason, 0, 5000),
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->db->update('monitoring_distraction_events', ['status' => 'acknowledged', 'employee_note' => substr($reason, 0, 5000)], 'id = :id', ['id' => $eventId]);
        $this->audit->log('create', 'monitoring_disputes', $id, null, ['company_id' => (int) $event['company_id'], 'employee_id' => $employeeId, 'distraction_event_id' => $eventId]);
        return ['success' => true, 'message' => 'Your explanation was submitted for review.'];
    }

    public function reviewDispute(int $disputeId, string $status, int $userId, ?string $note = null): array
    {
        if (!in_array($status, ['accepted', 'rejected'], true)) {
            return ['success' => false, 'message' => 'Invalid dispute status.'];
        }
        $dispute = $this->db->fetch('SELECT * FROM monitoring_disputes WHERE id = :id LIMIT 1', ['id' => $disputeId]);
        if (!$dispute) {
            return ['success' => false, 'message' => 'Dispute not found.'];
        }
        $now = date('Y-m-d H:i:s');
        $this->db->update('monitoring_disputes', ['status' => $status, 'reviewed_by' => $userId, 'reviewed_at' => $now, 'resolution_note' => $note ? substr(trim($note), 0, 5000) : null], 'id = :id', ['id' => $disputeId]);
        $this->db->update('monitoring_distraction_events', ['status' => 'resolved', 'resolved_by' => $userId, 'resolved_at' => $now], 'id = :id', ['id' => $dispute['distraction_event_id']]);
        $this->audit->log('update', 'monitoring_disputes', $disputeId, $dispute, ['status' => $status, 'reviewed_by' => $userId], $userId);
        return ['success' => true, 'message' => 'Dispute reviewed.'];
    }

    private function classify(int $companyId, int $employeeId, string $domain, string $context): array
    {
        $exception = $this->db->fetchAll(
            'SELECT override_category FROM monitoring_site_exceptions
             WHERE company_id = :cid AND employee_id = :eid AND status = "approved"
               AND (starts_at IS NULL OR starts_at <= NOW()) AND (ends_at IS NULL OR ends_at >= NOW())',
            ['cid' => $companyId, 'eid' => $employeeId]
        );
        foreach ($exception as $row) {
            if ($this->domainMatches($domain, (string) ($row['domain_pattern'] ?? ''))) {
                return ['category' => $row['override_category'], 'action' => 'allow', 'threshold_seconds' => 0];
            }
        }
        $rules = $this->db->fetchAll('SELECT * FROM monitoring_site_rules WHERE company_id = :cid AND is_active = 1 ORDER BY CHAR_LENGTH(domain_pattern) DESC', ['cid' => $companyId]);
        foreach ($rules as $rule) {
            $ruleContext = (string) ($rule['context'] ?? 'any');
            if ($ruleContext !== 'any' && $ruleContext !== $context) {
                continue;
            }
            if ($this->domainMatches($domain, (string) $rule['domain_pattern'])) {
                return ['category' => $rule['category'], 'action' => $rule['action'], 'threshold_seconds' => (int) $rule['threshold_seconds']];
            }
        }
        return ['category' => 'neutral', 'action' => 'allow', 'threshold_seconds' => 0];
    }

    private function createDistractionEvent(int $activityId, array $session, string $domain, array $classification, int $duration): int
    {
        $recent = (int) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM monitoring_distraction_events WHERE company_id = :cid AND employee_id = :eid AND domain = :domain AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY)',
            ['cid' => (int) $session['company_id'], 'eid' => (int) $session['employee_id'], 'domain' => $domain]
        );
        $severity = $recent > 0 ? 'critical' : 'warning';
        $action = $classification['action'] === 'block' ? 'blocked' : ($classification['action'] === 'notify' ? 'notified' : 'warned');
        $message = $action === 'blocked'
            ? "{$domain} is blocked by the current work policy."
            : "{$domain} has been used for more than the allowed time during working hours.";
        $id = $this->db->insert('monitoring_distraction_events', [
            'company_id' => (int) $session['company_id'],
            'employee_id' => (int) $session['employee_id'],
            'site_activity_id' => $activityId,
            'domain' => $domain,
            'severity' => $severity,
            'action_taken' => $action,
            'duration_seconds' => $duration,
            'message' => $message,
            'status' => 'open',
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        $this->alerts->create((int) $session['company_id'], 'site_distraction', 'Website policy alert', $message, $severity, (int) $session['employee_id'], $session['device_id'] ?? null, (int) $session['id']);
        return $id;
    }

    /** @return array{0: string, 1: bool, 2: int|null} */
    private function workState(int $employeeId, int $companyId, string $startedAt, ?string $endedAt = null, int $duration = 0): array
    {
        $schedule = $this->db->fetch(
            'SELECT s.* FROM monitoring_schedule_assignments a INNER JOIN monitoring_work_schedules s ON s.id = a.schedule_id
             WHERE a.company_id = :cid AND a.employee_id = :eid AND a.is_active = 1 AND s.is_active = 1
               AND (a.starts_at IS NULL OR a.starts_at <= :at1) AND (a.ends_at IS NULL OR a.ends_at >= :at2) LIMIT 1',
            ['cid' => $companyId, 'eid' => $employeeId, 'at1' => $startedAt, 'at2' => $startedAt]
        );
        if (!$schedule) {
            return ['unscheduled', true, null];
        }
        try {
            $time = new \DateTimeImmutable($startedAt, new \DateTimeZone((string) $schedule['timezone']));
        } catch (\Throwable) {
            $time = new \DateTimeImmutable($startedAt);
        }
        $days = json_decode((string) $schedule['work_days'], true) ?: [];
        $day = strtolower($time->format('D'));
        if (!in_array($day, array_map('strtolower', array_map('strval', $days)), true)
            && !in_array((int) $time->format('N'), array_map('intval', $days), true)) {
            return ['off_hours', false, 0];
        }
        $clock = $time->format('H:i:s');
        $start = (string) $schedule['start_time'];
        $end = (string) $schedule['end_time'];
        $inside = $start <= $end ? ($clock >= $start && $clock <= $end) : ($clock >= $start || $clock <= $end);
        if (!$inside) {
            return ['off_hours', false, 0];
        }
        $scheduleStart = new \DateTimeImmutable($time->format('Y-m-d') . ' ' . $start, $time->getTimezone());
        $scheduleEnd = new \DateTimeImmutable($time->format('Y-m-d') . ' ' . $end, $time->getTimezone());
        if ($scheduleEnd <= $scheduleStart) {
            $scheduleEnd = $scheduleEnd->modify('+1 day');
        }
        $eventEnd = $endedAt
            ? new \DateTimeImmutable($endedAt, $time->getTimezone())
            : $time->modify('+' . max(0, $duration) . ' seconds');
        $activeEnd = $eventEnd < $scheduleEnd ? $eventEnd : $scheduleEnd;
        $allowedSeconds = max(0, $activeEnd->getTimestamp() - $time->getTimestamp());
        $breaks = json_decode((string) ($schedule['break_windows'] ?? '[]'), true) ?: [];
        foreach ($breaks as $break) {
            if (!is_array($break)) {
                continue;
            }
            $breakStartText = (string) ($break['start'] ?? '');
            $breakEndText = (string) ($break['end'] ?? '');
            if ($breakStartText === '' || $breakEndText === '') {
                continue;
            }
            $breakStart = new \DateTimeImmutable($time->format('Y-m-d') . ' ' . $breakStartText, $time->getTimezone());
            $breakEnd = new \DateTimeImmutable($time->format('Y-m-d') . ' ' . $breakEndText, $time->getTimezone());
            if ($breakEnd <= $breakStart) {
                $breakEnd = $breakEnd->modify('+1 day');
            }
            $overlapStart = $time > $breakStart ? $time : $breakStart;
            $overlapEnd = $activeEnd < $breakEnd ? $activeEnd : $breakEnd;
            if ($overlapEnd > $overlapStart) {
                $allowedSeconds -= $overlapEnd->getTimestamp() - $overlapStart->getTimestamp();
            }
            if ($time >= $breakStart && $time < $breakEnd) {
                return ['break', false, 0];
            }
        }
        return ['working', $allowedSeconds > 0, max(0, $allowedSeconds)];
    }

    private function normalizeDomain(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        $parsed = filter_var($value, FILTER_VALIDATE_URL) ? parse_url($value, PHP_URL_HOST) : $value;
        $domain = strtolower(trim((string) $parsed));
        $domain = preg_replace('/^https?:\/\//', '', $domain) ?? $domain;
        $domain = preg_replace('/^www\./', '', $domain) ?? $domain;
        $domain = preg_replace('/[\/?#].*$/', '', $domain) ?? $domain;
        return preg_match('/^[a-z0-9][a-z0-9.-]{0,188}$/', $domain) ? $domain : '';
    }

    private function domainMatches(string $domain, string $pattern): bool
    {
        $pattern = strtolower(trim($pattern));
        $pattern = preg_replace('/^\*\.?/', '', $pattern) ?? $pattern;
        $pattern = preg_replace('/^www\./', '', $pattern) ?? $pattern;
        return $pattern !== '' && ($domain === $pattern || str_ends_with($domain, '.' . $pattern));
    }

    private function normalizeContext(mixed $context): string
    {
        return in_array($context, ['training', 'research'], true) ? $context : 'any';
    }
}
