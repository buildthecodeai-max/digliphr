<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AttendanceCalculationService;
use App\Services\AttendanceService;
use App\Services\ReportService;
use App\Services\SavedFilterService;

class ReportController extends Controller
{
    private ReportService $reports;
    private AttendanceCalculationService $calc;

    public const ATTENDANCE_SECTIONS = [
        'overview', 'daily', 'employee_summary', 'employee_detail', 'department', 'branch', 'shift',
        'absence', 'late', 'early', 'overtime', 'missing', 'corrections', 'monthly_sheet',
    ];

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->reports = new ReportService();
        $this->calc = new AttendanceCalculationService();
    }

    public function index(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $options = $this->reports->filterOptions();
        $summary = $this->reports->executiveSummary($filters);

        $this->view('admin/reports/index', [
            'title' => 'Reporting Workspace',
            'filters' => $filters,
            'options' => $options,
            'summary' => $summary,
            'attendanceTrend' => $this->reports->attendanceTrend($filters),
            'statusDistribution' => $this->reports->attendanceStatusDistribution($filters),
            'byDepartment' => $this->reports->attendanceByDepartment($filters),
            'workHours' => $this->reports->workHoursAnalysis($filters),
            'leave' => $this->reports->leaveAnalytics($filters),
            'payroll' => $this->reports->payrollAnalytics($filters, (int) ($this->request->input('period_id') ?: 0) ?: null),
            'loans' => $this->reports->loanAnalytics($filters),
            'workforce' => $this->reports->workforceAnalytics($filters),
            'workforcePeriod' => $this->reports->workforcePeriodAnalytics($filters),
            'documents' => $this->reports->documentAnalytics(),
            'savedReports' => $filters['company_id'] ? (new SavedFilterService())->forUser((int) $filters['company_id'], (int) $this->auth->id(), 'reports') : [],
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    /**
     * Real report builder: it forwards a selected report and filters to the
     * existing report route, which uses the live reporting queries and export.
     */
    public function builder(): void
    {
        $this->authorize('reports.view');
        $type = (string) $this->request->input('report');
        $routes = [
            'attendance' => '/admin/reports/attendance',
            'leave' => '/admin/reports/leave',
            'payroll' => '/admin/reports/payroll',
            'workforce' => '/admin/reports/employees',
            'loans' => '/admin/reports/loans',
            'documents' => '/admin/reports/documents',
        ];
        if (isset($routes[$type])) {
            $query = array_filter([
                'period' => $this->request->input('period', 'this_month'),
                'from' => $this->request->input('from'),
                'to' => $this->request->input('to'),
                'branch_id' => $this->request->input('branch_id'),
                'department_id' => $this->request->input('department_id'),
                'employee_id' => $this->request->input('employee_id'),
                'view' => $this->request->input('view', 'table'),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');
            $this->redirect($routes[$type] . '?' . http_build_query($query));
        }

        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->view('admin/reports/builder', [
            'title' => 'Create HR Report',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'savedReports' => $filters['company_id'] ? (new SavedFilterService())->forUser((int) $filters['company_id'], (int) $this->auth->id(), 'reports') : [],
            'viewMode' => 'table',
        ]);
    }

    public function attendance(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $page = max(1, (int) $this->request->input('page', 1));
        $section = (string) $this->request->input('section', 'overview');
        if (!in_array($section, self::ATTENDANCE_SECTIONS, true)) {
            $section = 'overview';
        }

        $data = [
            'title' => 'Attendance Analytics',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'section' => $section,
            'sections' => self::ATTENDANCE_SECTIONS,
            'viewMode' => $this->request->input('view', 'table'),
            'sort' => (string) $this->request->input('sort', 'employee_name'),
            'dir' => (string) $this->request->input('dir', 'asc') === 'desc' ? 'desc' : 'asc',
        ];

        switch ($section) {
            case 'employee_summary':
                $bulk = $this->calc->bulkSummary($filters);
                $data['bulk'] = $bulk;
                $data['rows'] = $this->sortRows($bulk['rows'], $data['sort'], $data['dir']);
                $data['page'] = $page;
                break;

            case 'employee_detail':
                $employeeId = (int) ($filters['employee_id'] ?? 0);
                if ($employeeId > 0 && $this->calc->canAccessEmployee($employeeId)) {
                    $data['detail'] = $this->calc->employeeDailyBreakdown($employeeId, $filters['from'], $filters['to']);
                    $data['payrollSummary'] = $this->calc->payrollAttendanceSummary($employeeId, $filters['from'], $filters['to']);
                    $data['deviceInfo'] = can('attendance.device.view') ? $this->employeeDeviceInfo($employeeId) : [];
                } elseif ($employeeId > 0) {
                    flash('error', 'You do not have permission to view this employee\'s attendance.');
                }
                break;

            case 'department':
                $bulk = $this->calc->bulkSummary($filters);
                $data['groups'] = $this->groupBulkRows($bulk['rows'], 'department');
                break;

            case 'branch':
                $bulk = $this->calc->bulkSummary($filters);
                $data['groups'] = $this->groupBulkRows($bulk['rows'], 'branch');
                break;

            case 'shift':
                $bulk = $this->calc->bulkSummary($filters);
                $data['groups'] = $this->groupBulkRows($bulk['rows'], 'shift');
                break;

            case 'daily':
                $date = (string) $this->request->input('date', $filters['to']);
                $data['date'] = $date;
                $data['daily'] = $this->calc->bulkSummary(['from' => $date, 'to' => $date] + $filters);
                $data['dailyTable'] = $this->reports->attendanceTable(array_merge($filters, ['from' => $date, 'to' => $date]), $page, 50);
                break;

            case 'absence':
                $data['absences'] = $this->absenceRows($filters);
                break;

            case 'late':
                $data['late'] = $this->lateArrivalRows($filters);
                break;

            case 'early':
                $data['early'] = $this->earlyDepartureRows($filters);
                break;

            case 'overtime':
                $data['overtime'] = $this->overtimeRows($filters);
                break;

            case 'missing':
                $data['missing'] = $this->missingAttendanceRows($filters);
                break;

            case 'corrections':
                $data['corrections'] = $this->correctionRows($filters);
                break;

            case 'monthly_sheet':
                $data['sheet'] = $this->monthlySheet($filters);
                break;

            case 'overview':
            default:
                $bulk = $this->calc->bulkSummary($filters);
                $data['summary'] = $this->reports->executiveSummary($filters);
                $data['trend'] = $this->reports->attendanceTrend($filters);
                $data['distribution'] = $this->reports->attendanceStatusDistribution($filters);
                $data['byDepartment'] = $this->reports->attendanceByDepartment($filters);
                $data['workHours'] = $this->reports->workHoursAnalysis($filters);
                $data['heatmap'] = $this->reports->attendanceHeatmap($filters);
                $data['table'] = $this->reports->attendanceTable($filters, $page, 25);
                $data['bulkTotals'] = $bulk['totals'];
                break;
        }

        $this->view('admin/reports/attendance', $data);
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function sortRows(array $rows, string $sort, string $dir): array
    {
        $allowed = ['employee_name', 'department', 'branch', 'scheduled_working_days', 'present_days',
            'absent_days', 'leave_days', 'late_days', 'attendance_pct', 'worked_minutes', 'overtime_minutes'];
        if (!in_array($sort, $allowed, true)) {
            $sort = 'employee_name';
        }
        usort($rows, static function ($a, $b) use ($sort, $dir) {
            $va = $a[$sort] ?? '';
            $vb = $b[$sort] ?? '';
            $cmp = is_numeric($va) && is_numeric($vb) ? ($va <=> $vb) : strcasecmp((string) $va, (string) $vb);
            return $dir === 'desc' ? -$cmp : $cmp;
        });
        return $rows;
    }

    /**
     * Groups bulkSummary() employee rows by department/branch/shift in PHP
     * (cheap: employee counts are always bounded, and bulkSummary already
     * did the expensive aggregation in SQL) rather than writing a third
     * near-duplicate aggregate query per grouping dimension.
     *
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    private function groupBulkRows(array $rows, string $key): array
    {
        $groups = [];
        foreach ($rows as $row) {
            $label = $row[$key] ?: 'Unassigned';
            if (!isset($groups[$label])) {
                $groups[$label] = [
                    'label' => $label, 'employees' => 0, 'scheduled_working_days' => 0.0,
                    'present_days' => 0.0, 'absent_days' => 0.0, 'leave_days' => 0.0, 'late_days' => 0.0,
                    'worked_minutes' => 0, 'overtime_minutes' => 0,
                ];
            }
            $groups[$label]['employees']++;
            foreach (['scheduled_working_days', 'present_days', 'absent_days', 'leave_days', 'late_days', 'worked_minutes', 'overtime_minutes'] as $f) {
                $groups[$label][$f] += $row[$f];
            }
        }
        foreach ($groups as &$g) {
            $g['attendance_pct'] = $g['scheduled_working_days'] > 0 ? round(($g['present_days'] / $g['scheduled_working_days']) * 100, 1) : 0.0;
        }
        unset($g);
        usort($groups, static fn ($a, $b) => strcasecmp((string) $a['label'], (string) $b['label']));
        return $groups;
    }

    /**
     * Absence rows for HR review: both recorded 'absent' attendance rows and
     * gap days (no record at all on a scheduled working day) are included,
     * each date's row also gets that employee's leave-request status (if
     * any exists but is not yet approved) and how many consecutive absent
     * days precede it, computed in PHP after one bounded per-date fetch —
     * bounded by the same date-range filter every other tab respects.
     */
    private function absenceRows(array $filters): array
    {
        // PDO::ATTR_EMULATE_PREPARES is off (see Database/config), so MySQL's
        // native prepared statements reject execute() calls whose params
        // array contains ANY key not referenced by that exact query's SQL —
        // not just missing ones. $baseParams below holds only the
        // scope/branch/department/employee filters shared by both queries;
        // each query then adds its own distinct date placeholders on top,
        // rather than reusing one shared $params array with leftover keys.
        $scope = $this->calc->employeeScopeSql('e');
        $baseParams = $scope['params'];
        $clauses = [$scope['sql'], 'e.deleted_at IS NULL'];
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $baseParams['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $baseParams['department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'e.id = :employee_id';
            $baseParams['employee_id'] = (int) $filters['employee_id'];
        }
        $where = implode(' AND ', $clauses);

        $recorded = $this->db()->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, b.name AS branch_name, s.name AS shift_name,
                    a.attendance_date, 'Absent' AS absence_type
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE a.status = 'absent' AND a.attendance_date BETWEEN :from AND :to AND a.deleted_at IS NULL AND {$where}",
            array_merge($baseParams, ['from' => $filters['from'], 'to' => $filters['to']])
        );

        $dayWeight = $this->calc->dayWeightSql('cal.d');
        $patternJoins = $this->calc->patternJoinSql('e');
        $today = date('Y-m-d');
        $gap = $this->db()->fetchAll(
            "WITH RECURSIVE cal AS (
                SELECT DATE(:from2) AS d UNION ALL SELECT d + INTERVAL 1 DAY FROM cal WHERE d < DATE(:to2)
            )
            SELECT e.id AS employee_id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                   d.name AS department_name, b.name AS branch_name, sh.name AS shift_name,
                   cal.d AS attendance_date,
                   CASE WHEN ({$dayWeight}) = 0.5 THEN 'Not Recorded (Half Day)' ELSE 'Not Recorded' END AS absence_type
            FROM cal
            CROSS JOIN employees e
            LEFT JOIN departments d ON d.id = e.department_id
            LEFT JOIN branches b ON b.id = e.branch_id
            LEFT JOIN shifts sh ON sh.id = e.shift_id
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = cal.d AND a.deleted_at IS NULL
            {$patternJoins}
            LEFT JOIN holidays hol ON hol.company_id = e.company_id AND (hol.branch_id IS NULL OR hol.branch_id = e.branch_id)
                AND cal.d BETWEEN hol.holiday_date AND COALESCE(hol.end_date, hol.holiday_date) AND hol.deleted_at IS NULL
            LEFT JOIN leave_requests lr ON lr.employee_id = e.id AND lr.status = 'approved'
                AND cal.d BETWEEN lr.start_date AND lr.end_date AND lr.deleted_at IS NULL
            WHERE {$where}
              AND a.id IS NULL AND hol.id IS NULL AND lr.id IS NULL
              AND ({$dayWeight}) > 0
              AND cal.d < :today AND cal.d >= e.joining_date
              AND (e.last_working_date IS NULL OR cal.d <= e.last_working_date)",
            array_merge($baseParams, ['from2' => $filters['from'], 'to2' => $filters['to'], 'today' => $today])
        );

        $all = array_merge($recorded, $gap);
        usort($all, static fn ($a, $b) => [$a['employee_id'], $a['attendance_date']] <=> [$b['employee_id'], $b['attendance_date']]);

        $pendingLeaveByEmployee = $this->db()->fetchAll(
            "SELECT lr.employee_id, lr.start_date, lr.end_date FROM leave_requests lr
             WHERE lr.status = 'pending' AND lr.start_date <= :to AND lr.end_date >= :from AND lr.deleted_at IS NULL",
            ['from' => $filters['from'], 'to' => $filters['to']]
        );

        $streak = [];
        $rows = [];
        foreach ($all as $row) {
            $eid = (int) $row['employee_id'];
            $date = $row['attendance_date'];
            $prevStreak = $streak[$eid]['date'] ?? null;
            $consecutive = ($prevStreak && strtotime($date) - strtotime($prevStreak) === 86400)
                ? ($streak[$eid]['count'] + 1) : 1;
            $streak[$eid] = ['date' => $date, 'count' => $consecutive];

            $leaveStatus = 'None';
            foreach ($pendingLeaveByEmployee as $lp) {
                if ((int) $lp['employee_id'] === $eid && $date >= $lp['start_date'] && $date <= $lp['end_date']) {
                    $leaveStatus = 'Pending Leave Request';
                    break;
                }
            }

            $rows[] = [
                'employee_id' => $eid,
                'employee_code' => $row['employee_code'],
                'employee_name' => $row['employee_name'],
                'department' => $row['department_name'],
                'branch' => $row['branch_name'],
                'shift' => $row['shift_name'],
                'date' => $date,
                'absence_type' => $row['absence_type'],
                'consecutive_days' => $consecutive,
                'leave_request_status' => $leaveStatus,
            ];
        }

        return $rows;
    }

    private function lateArrivalRows(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        [$where, $params] = $this->attendanceScopedWhere($filters, $scope);
        $rows = $this->db()->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, b.name AS branch_name, s.name AS shift_name,
                    a.attendance_date, s.start_time AS scheduled_in, a.check_in_at, a.late_minutes,
                    s.grace_minutes, GREATEST(0, a.late_minutes - COALESCE(s.grace_minutes,0)) AS effective_late_minutes
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE a.status = 'late' AND a.late_minutes > 0 AND {$where}
             ORDER BY a.attendance_date DESC, a.late_minutes DESC",
            $params
        );

        $employees = [];
        $totalMinutes = 0;
        foreach ($rows as $row) {
            $employees[(int) $row['employee_id']] = true;
            $totalMinutes += (int) $row['late_minutes'];
        }

        return [
            'rows' => $rows,
            'summary' => [
                'total_late_employees' => count($employees),
                'total_occurrences' => count($rows),
                'total_late_minutes' => $totalMinutes,
            ],
        ];
    }

    private function earlyDepartureRows(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        [$where, $params] = $this->attendanceScopedWhere($filters, $scope);
        return $this->db()->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, b.name AS branch_name,
                    a.attendance_date, s.end_time AS scheduled_out, a.check_out_at, a.early_leave_minutes,
                    a.work_minutes, a.verification_status
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE a.early_leave_minutes > 0 AND {$where}
             ORDER BY a.attendance_date DESC, a.early_leave_minutes DESC",
            $params
        );
    }

    /**
     * Calculated overtime (attendance.overtime_minutes, auto-derived at
     * check-out from shift rules) is deliberately shown alongside — never
     * merged with — Approved overtime (overtime_requests.status='approved'):
     * these are two independent numbers in this codebase (PayrollService
     * only ever pays the approved figure), so collapsing them here would
     * hide exactly the reconciliation gap HR needs to see.
     */
    private function overtimeRows(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        [$where, $params] = $this->attendanceScopedWhere($filters, $scope);
        return $this->db()->fetchAll(
            "SELECT e.id AS employee_id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, a.attendance_date, a.expected_work_minutes AS regular_minutes,
                    a.work_minutes AS worked_minutes, a.overtime_minutes AS calculated_overtime_minutes,
                    ot.requested_minutes, ot.approved_minutes, ot.status AS approval_status,
                    ru.name AS approved_by_name
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN overtime_requests ot ON ot.employee_id = a.employee_id AND ot.overtime_date = a.attendance_date AND ot.deleted_at IS NULL
             LEFT JOIN users ru ON ru.id = ot.reviewed_by
             WHERE a.overtime_minutes > 0 AND {$where}
             ORDER BY a.attendance_date DESC, a.overtime_minutes DESC",
            $params
        );
    }

    /**
     * Exception queue: incomplete/duplicate/flagged records and open
     * regularization requests — everything HR needs to chase down, not a
     * simple "no check-in" list (see AttendanceCalculationService's
     * docblock for why no-record days are never assumed absent).
     */
    private function missingAttendanceRows(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        [$where, $params] = $this->attendanceScopedWhere($filters, $scope);

        $incomplete = $this->db()->fetchAll(
            "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    d.name AS department_name, a.attendance_date, a.check_in_at, a.check_out_at,
                    a.status, a.verification_status,
                    CASE WHEN a.check_in_at IS NULL THEN 'Missing Check-In'
                         WHEN a.check_out_at IS NULL THEN 'Missing Check-Out'
                         ELSE 'Flagged' END AS issue_type
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             LEFT JOIN departments d ON d.id = e.department_id
             WHERE {$where} AND (
                a.status = 'missing_checkout'
                OR (a.check_in_at IS NOT NULL AND a.check_out_at IS NULL AND a.status NOT IN ('on_leave','holiday','weekend'))
                OR a.verification_status = 'flagged'
             )
             ORDER BY a.attendance_date DESC",
            $params
        );

        $duplicates = $this->db()->fetchAll(
            "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    a.attendance_date, COUNT(*) AS record_count
             FROM attendance a
             INNER JOIN employees e ON e.id = a.employee_id
             WHERE {$where}
             GROUP BY a.employee_id, a.attendance_date
             HAVING COUNT(*) > 1",
            $params
        );

        $pendingCorrections = $this->db()->fetchAll(
            "SELECT e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    a.attendance_date, ac.reason, ac.created_at AS requested_at
             FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id
             INNER JOIN employees e ON e.id = ac.employee_id
             WHERE ac.status = 'pending' AND {$where} AND ac.deleted_at IS NULL",
            $params
        );

        return ['incomplete' => $incomplete, 'duplicates' => $duplicates, 'pending_corrections' => $pendingCorrections];
    }

    private function correctionRows(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        $params = $scope['params'];
        $clauses = [$scope['sql'], 'ac.deleted_at IS NULL'];
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'e.id = :employee_id';
            $params['employee_id'] = (int) $filters['employee_id'];
        }
        $where = implode(' AND ', $clauses);

        return $this->db()->fetchAll(
            "SELECT ac.id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS employee_name,
                    a.attendance_date, ac.previous_check_in_at, ac.previous_check_out_at,
                    ac.requested_check_in_at, ac.requested_check_out_at, ac.previous_status,
                    ac.reason, ac.status, ac.reviewed_by, ac.reviewed_at, ac.review_notes,
                    ac.created_at AS requested_at, cu.name AS requested_by_name, ru.name AS reviewed_by_name
             FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id
             INNER JOIN employees e ON e.id = ac.employee_id
             LEFT JOIN users cu ON cu.id = ac.created_by
             LEFT JOIN users ru ON ru.id = ac.reviewed_by
             WHERE {$where}
             ORDER BY (ac.status = 'pending') DESC, ac.created_at DESC
             LIMIT 200",
            $params
        );
    }

    /**
     * Printable monthly matrix: one row per employee, one column per day of
     * the range, using the same per-day classification as the employee
     * drill-down (bounded cost: employees-in-scope x days-in-range, exactly
     * what gets rendered — never more than what a monthly print view needs).
     */
    private function monthlySheet(array $filters): array
    {
        $scope = $this->calc->employeeScopeSql('e');
        $params = $scope['params'];
        $clauses = [$scope['sql'], 'e.deleted_at IS NULL'];
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }
        $where = implode(' AND ', $clauses);

        $employees = $this->db()->fetchAll(
            "SELECT e.id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS name
             FROM employees e WHERE {$where} ORDER BY e.first_name ASC LIMIT 300",
            $params
        );

        $dates = [];
        for ($c = strtotime($filters['from']); $c <= strtotime($filters['to']); $c += 86400) {
            $dates[] = date('Y-m-d', $c);
        }

        $codeMap = [
            'present' => 'P', 'late' => 'L', 'half_day' => 'H', 'absent' => 'A',
            'paid_leave' => 'PL', 'unpaid_leave' => 'UL', 'sick_leave' => 'SL', 'holiday' => 'HO',
            'rest_day' => 'R', 'work_from_home' => 'WFH', 'official_duty' => 'OD',
            'missing_attendance' => 'MA', 'pending_regularization' => 'PR', 'scheduled' => '',
        ];

        $matrix = [];
        foreach ($employees as $emp) {
            $breakdown = $this->calc->employeeDailyBreakdown((int) $emp['id'], $filters['from'], $filters['to']);
            $byDate = array_column($breakdown['days'], null, 'date');
            $cells = [];
            foreach ($dates as $d) {
                $cells[] = $codeMap[$byDate[$d]['status'] ?? ''] ?? '';
            }
            $matrix[] = [
                'employee_code' => $emp['employee_code'],
                'employee_name' => $emp['name'],
                'cells' => $cells,
                'summary' => $breakdown['summary'],
            ];
        }

        return ['dates' => $dates, 'employees' => $matrix, 'legend' => $codeMap];
    }

    private function employeeDeviceInfo(int $employeeId): array
    {
        return $this->db()->fetchAll(
            "SELECT device_name, browser, operating_system, status, last_ip, last_seen_at, approved_at
             FROM employee_attendance_devices WHERE employee_id = :eid ORDER BY last_seen_at DESC LIMIT 5",
            ['eid' => $employeeId]
        );
    }

    /**
     * @param array{sql:string,params:array<string,mixed>} $scope
     * @return array{0:string,1:array<string,mixed>}
     */
    private function attendanceScopedWhere(array $filters, array $scope): array
    {
        $params = array_merge($scope['params'], ['from' => $filters['from'], 'to' => $filters['to']]);
        $clauses = [$scope['sql'], 'a.attendance_date BETWEEN :from AND :to', 'a.deleted_at IS NULL'];
        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :department_id';
            $params['department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'e.id = :employee_id';
            $params['employee_id'] = (int) $filters['employee_id'];
        }
        if (!empty($filters['shift_id'])) {
            $clauses[] = 'a.shift_id = :shift_id';
            $params['shift_id'] = (int) $filters['shift_id'];
        }
        return [implode(' AND ', $clauses), $params];
    }

    private function db(): Database
    {
        return Database::getInstance();
    }

    public function approveCorrection(int $id): void
    {
        $this->authorize('attendance.correct');
        $correction = $this->db()->fetch(
            "SELECT ac.*, a.attendance_date FROM attendance_corrections ac
             INNER JOIN attendance a ON a.id = ac.attendance_id WHERE ac.id = :id AND ac.deleted_at IS NULL",
            ['id' => $id]
        );
        if (!$correction || $correction['status'] !== 'pending') {
            flash('error', 'Correction request not found or already processed.');
            $this->redirect('/admin/reports/attendance?section=corrections');
            return;
        }

        $result = (new AttendanceService())->adminCorrect((int) $correction['attendance_id'], [
            'check_in_at' => $correction['requested_check_in_at'],
            'check_out_at' => $correction['requested_check_out_at'],
            'reason' => 'Regularization approved: ' . $correction['reason'],
        ], (int) $this->auth->id());

        if ($result['success']) {
            $this->db()->update('attendance_corrections', [
                'status' => 'approved',
                'reviewed_by' => $this->auth->id(),
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => (string) $this->request->input('review_notes', ''),
            ], 'id = :id', ['id' => $id]);
            flash('success', 'Correction approved and applied to the attendance record.');
        } else {
            flash('error', $result['message'] ?? 'Failed to apply correction.');
        }
        $this->redirect('/admin/reports/attendance?section=corrections');
    }

    public function rejectCorrection(int $id): void
    {
        $this->authorize('attendance.correct');
        $correction = $this->db()->fetch(
            'SELECT * FROM attendance_corrections WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$correction || $correction['status'] !== 'pending') {
            flash('error', 'Correction request not found or already processed.');
            $this->redirect('/admin/reports/attendance?section=corrections');
            return;
        }

        $this->db()->update('attendance_corrections', [
            'status' => 'rejected',
            'reviewed_by' => $this->auth->id(),
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => (string) $this->request->input('review_notes', 'Rejected'),
        ], 'id = :id', ['id' => $id]);
        flash('success', 'Correction request rejected.');
        $this->redirect('/admin/reports/attendance?section=corrections');
    }

    public function leave(): void
    {
        $this->authorize('reports.view');

        $rawFrom = $this->request->input('from');
        $rawTo = $this->request->input('to');
        if ($rawFrom && $rawTo && strtotime((string) $rawTo) !== false
            && strtotime((string) $rawFrom) !== false
            && strtotime((string) $rawTo) < strtotime((string) $rawFrom)
        ) {
            flash('warning', 'The "From" date was after the "To" date, so the range was reversed automatically.');
        }

        $filters = $this->reports->normalizeFilters($this->request->all());
        $analytics = $this->reports->leaveAnalytics($filters);

        $compareTrend = null;
        if (!empty($filters['compare'])) {
            $prevFilters = $filters;
            $prevFilters['from'] = $filters['prev_from'];
            $prevFilters['to'] = $filters['prev_to'];
            $compareTrend = $this->reports->leaveAnalytics($prevFilters)['trend'];
        }

        $this->view('admin/reports/leave', [
            'title' => 'Leave Analytics',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'analytics' => $analytics,
            'kpi' => $this->reports->leaveKpiSummary($analytics['byStatus'], $filters),
            'compareTrend' => $compareTrend,
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    public function payroll(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $periodId = (int) ($this->request->input('period_id') ?: 0) ?: null;

        $this->view('admin/reports/payroll', [
            'title' => 'Payroll Analytics',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'analytics' => $this->reports->payrollAnalytics($filters, $periodId),
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    public function employees(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->view('admin/reports/employees', [
            'title' => 'Workforce Analytics',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'analytics' => $this->reports->workforceAnalytics($filters),
            'periodAnalytics' => $this->reports->workforcePeriodAnalytics($filters),
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    public function loans(): void
    {
        $this->authorize('reports.view');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $this->view('admin/reports/loans', [
            'title' => 'Loans & Advances Analytics',
            'filters' => $filters,
            'options' => $this->reports->filterOptions(),
            'analytics' => $this->reports->loanAnalytics($filters),
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    public function documents(): void
    {
        $this->authorize('reports.view');
        $this->view('admin/reports/documents', [
            'title' => 'Document Analytics',
            'filters' => $this->reports->normalizeFilters($this->request->all()),
            'options' => $this->reports->filterOptions(),
            'analytics' => $this->reports->documentAnalytics(),
            'viewMode' => $this->request->input('view', 'table'),
        ]);
    }

    public function export(string $type): void
    {
        $this->authorize('reports.export');
        $filters = $this->reports->normalizeFilters($this->request->all());
        $format = $this->request->input('format', 'csv');

        if ($type === 'leave' && ($format === 'csv' || $format === 'excel')) {
            $this->exportLeaveCsv($this->reports->leaveAnalytics($filters), $filters);
        }

        if ($type === 'attendance' && ($format === 'csv' || $format === 'excel')) {
            $this->exportAttendanceCsv(
                $this->calc->bulkSummary($filters)['rows'],
                $this->reports->attendanceTable($filters, 1, 5000)['data'],
                $filters
            );
        }

        $rows = match ($type) {
            'attendance' => $this->reports->attendanceTable($filters, 1, 5000)['data'],
            'leave' => $this->reports->leaveAnalytics($filters)['byType'],
            'payroll' => $this->reports->payrollAnalytics($filters, (int) ($this->request->input('period_id') ?: 0) ?: null)['rows'],
            'loans' => $this->reports->loanAnalytics($filters)['rows'],
            'employees' => $this->reports->workforceAnalytics($filters)['byDepartment'],
            'documents' => $this->reports->documentAnalytics()['rows'],
            default => [],
        };

        if ($format === 'csv' || $format === 'excel') {
            $this->exportCsv($type, $rows, $filters);
        }

        flash('warning', 'PDF export uses the print view. Use browser Print → Save as PDF.');
        $this->redirect('/admin/reports/' . ($type === 'overview' ? '' : $type) . '?' . http_build_query([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'view' => 'table',
        ]));
    }

    /**
     * Attendance CSV: a per-employee monthly summary section (with remote/WFH
     * day count) followed by the full day-by-day detail rows.
     */
    private function exportAttendanceCsv(array $summary, array $detail, array $filters): void
    {
        $filename = sprintf('attendance-report-%s-to-%s.csv', $filters['from'], $filters['to']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        $write = static function (array $row) use ($out) {
            fputcsv($out, $row, ',', '"', '\\');
        };

        $write(['Report', 'Attendance Analytics']);
        $write(['Organization', config('app.name')]);
        $write(['Period', $filters['from'] . ' to ' . $filters['to']]);
        $write(['Generated At', date('Y-m-d H:i:s')]);
        $write(['Generated By', csv_safe($this->user()['name'] ?? '')]);
        $write([]);

        $write(['Employee Monthly Summary']);
        $write(['Employee Code', 'Employee Name', 'Department', 'Designation', 'Branch', 'Shift',
            'Scheduled Working Days', 'Present', 'Absent', 'Paid Leave', 'Unpaid Leave', 'Half Days',
            'Late Days', 'Early Departures', 'Holidays', 'Rest Days', 'Missing Attendance',
            'Worked Hours', 'Required Hours', 'Overtime Hours', 'Undertime Hours', 'Attendance %']);
        foreach ($summary as $row) {
            $write([
                csv_safe($row['employee_code'] ?? ''),
                csv_safe($row['employee_name'] ?? ''),
                csv_safe($row['department'] ?? ''),
                csv_safe($row['designation'] ?? ''),
                csv_safe($row['branch'] ?? ''),
                csv_safe($row['shift'] ?? ''),
                $row['scheduled_working_days'],
                $row['present_days'],
                $row['absent_days'],
                $row['paid_leave_days'],
                $row['unpaid_leave_days'],
                $row['half_days'],
                $row['late_days'],
                $row['early_departures'],
                $row['holiday_days'],
                $row['rest_days'],
                $row['missing_attendance'],
                round($row['worked_minutes'] / 60, 1),
                round($row['required_minutes'] / 60, 1),
                round($row['overtime_minutes'] / 60, 1),
                round($row['undertime_minutes'] / 60, 1),
                $row['attendance_pct'],
            ]);
        }
        $write([]);

        $write(['Daily Detail']);
        if ($detail) {
            $write(['Date', 'Employee', 'Code', 'Department', 'Branch', 'Shift', 'Check In', 'Check Out', 'Work Hours', 'Overtime', 'Status', 'Verification', 'Remote']);
            foreach ($detail as $r) {
                $write([
                    csv_safe($r['attendance_date'] ?? ''),
                    csv_safe($r['employee_name'] ?? ''),
                    csv_safe($r['employee_code'] ?? ''),
                    csv_safe($r['department_name'] ?? ''),
                    csv_safe($r['branch_name'] ?? ''),
                    csv_safe($r['shift_name'] ?? ''),
                    $r['check_in_at'] ? date(config('app.time_format', 'g:i A'), strtotime((string) $r['check_in_at'])) : '',
                    $r['check_out_at'] ? date(config('app.time_format', 'g:i A'), strtotime((string) $r['check_out_at'])) : '',
                    $r['work_minutes'] ? round((int) $r['work_minutes'] / 60, 2) : '0',
                    $r['overtime_minutes'] ? round((int) $r['overtime_minutes'] / 60, 2) : '0',
                    csv_safe($r['status'] ?? ''),
                    csv_safe($r['verification_status'] ?? ''),
                    ((int) ($r['is_remote'] ?? 0)) ? 'Yes' : 'No',
                ]);
            }
        }

        fclose($out);
        exit;
    }

    /**
     * Leave gets its own CSV writer because the visible page shows five
     * different slices of data (status/type/trend/department/balances) and
     * the generic exportCsv() only handles one flat table — exporting just
     * byType would silently drop most of what the analytics screen shows.
     */
    private function exportLeaveCsv(array $analytics, array $filters): void
    {
        $filename = sprintf('leave-report-%s-to-%s.csv', $filters['from'], $filters['to']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        $write = static function (array $row) use ($out) {
            fputcsv($out, $row, ',', '"', '\\');
        };
        $writeTable = static function (array $rows) use ($write) {
            if (!$rows) {
                $write(['No records for this section.']);
                return;
            }
            $write(array_keys($rows[0]));
            foreach ($rows as $row) {
                $write(array_map(
                    static fn ($v) => csv_safe(is_scalar($v) || $v === null ? $v : json_encode($v)),
                    $row
                ));
            }
        };

        $write(['Report', 'Leave Analytics']);
        $write(['Organization', config('app.name')]);
        $write(['Period', $filters['from'] . ' to ' . $filters['to']]);
        $write(['Generated At', date('Y-m-d H:i:s')]);
        $write(['Generated By', csv_safe($this->user()['name'] ?? '')]);
        $write([]);

        $write(['Status Summary']);
        $writeTable($analytics['byStatus']);
        $write([]);

        $write(['Leave Type Breakdown']);
        $writeTable($analytics['byType']);
        $write([]);

        $write(['Monthly Trend']);
        $writeTable($analytics['trend']);
        $write([]);

        $write(['Department Breakdown']);
        $writeTable($analytics['byDepartment']);

        fclose($out);
        exit;
    }

    private function exportCsv(string $type, array $rows, array $filters): void
    {
        $filename = sprintf('%s-report-%s-to-%s.csv', $type, $filters['from'], $filters['to']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['Report', $type], ',', '"', '\\');
        fputcsv($out, ['Organization', config('app.name')], ',', '"', '\\');
        fputcsv($out, ['Period', $filters['from'] . ' to ' . $filters['to']], ',', '"', '\\');
        fputcsv($out, ['Generated At', date('Y-m-d H:i:s')], ',', '"', '\\');
        fputcsv($out, ['Generated By', csv_safe($this->user()['name'] ?? '')], ',', '"', '\\');
        fputcsv($out, [], ',', '"', '\\');

        if ($rows) {
            fputcsv($out, array_keys($rows[0]), ',', '"', '\\');
            foreach ($rows as $row) {
                fputcsv($out, array_map(static function ($v) {
                    return csv_safe(is_scalar($v) || $v === null ? $v : json_encode($v));
                }, $row), ',', '"', '\\');
            }
        }
        fclose($out);
        exit;
    }
}
