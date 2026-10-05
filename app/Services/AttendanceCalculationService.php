<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Shift;

/**
 * Single source of truth for attendance reporting math.
 *
 * Every existing formula it needs already lives elsewhere and is reused
 * as-is: per-record late/overtime/early-leave/expected-work minutes are
 * computed once at check-in/out time by AttendanceService and stored on the
 * `attendance` row, so this class never recomputes them — it only (a)
 * classifies days that have NO attendance row (leave / holiday / rest day /
 * absent, in the same precedence LeaveService already uses when it excludes
 * weekends and holidays from chargeable leave) and (b) aggregates. Report
 * KPI cards, the employee summary table, the employee drill-down, and the
 * payroll-compatible export all call the methods here, so they can never
 * drift apart the way Dashboard/Reports/Payroll previously did (Payroll
 * re-derived its own present/absent/working-day counts with independent
 * raw SQL in PayrollService::finalizeAttendance()/countWorkingDays()).
 *
 * Normalized report statuses (adapted from the existing `attendance.status`
 * enum + is_remote/leave/holiday context rather than a duplicate enum):
 *   present, late, half_day, absent, paid_leave, unpaid_leave, holiday,
 *   rest_day (existing DB value: weekend), work_from_home (existing DB
 *   value: remote), official_duty (existing DB value: manual),
 *   missing_attendance (existing DB value: missing_checkout, or a working
 *   day today/past with no record at all), pending_regularization
 *   (an attendance_corrections row is still pending for that date).
 */
class AttendanceCalculationService
{
    private Database $db;
    private AuthService $auth;
    private TenantContext $tenant;

    public function __construct(?AuthService $auth = null)
    {
        $this->db = Database::getInstance();
        $this->auth = $auth ?? new AuthService();
        $this->tenant = new TenantContext($this->auth);
    }

    /**
     * Whether $date is a working day for $shift. NULL working_days (every
     * shift today) preserves the exact Mon-Fri behavior LeaveService,
     * cron/run.php and PayrollService each hardcode independently today.
     *
     * This shift-only helper stays in place for PayrollService (which has no
     * easy access to designation/department context in countWorkingDays()).
     * Everywhere that resolves a *specific employee's* schedule — the report
     * engine's own day classification — uses resolveWeekPattern()/
     * dayTypeFromPattern() below instead, since a plain working/not-working
     * boolean can't express "Saturday is a half day."
     */
    public static function isWorkingDay(?array $shift, string $date): bool
    {
        $iso = (int) date('N', strtotime($date));
        $configured = trim((string) ($shift['working_days'] ?? ''));
        if ($configured === '') {
            return $iso <= 5;
        }
        $days = array_map('intval', explode(',', $configured));
        return in_array($iso, $days, true);
    }

    private static array $patternCache = [];

    /**
     * Resolves the effective weekly schedule pattern for an employee, with
     * precedence: designation.is_manager_or_above (company's manager
     * pattern) > department.week_pattern_id (a team-specific override, e.g.
     * "Result & Data Entry Team") > the company's default pattern > a
     * built-in fallback (Mon-Fri full, Saturday half, Sunday off) for a
     * company that hasn't configured any patterns yet.
     *
     * @return array{monday_type:string,tuesday_type:string,wednesday_type:string,thursday_type:string,friday_type:string,saturday_type:string,sunday_type:string,source:string}
     */
    public function resolveWeekPattern(int $companyId, ?int $designationId, ?int $departmentId): array
    {
        $cacheKey = "{$companyId}:{$designationId}:{$departmentId}";
        if (isset(self::$patternCache[$cacheKey])) {
            return self::$patternCache[$cacheKey];
        }

        $fallback = [
            'monday_type' => 'full', 'tuesday_type' => 'full', 'wednesday_type' => 'full',
            'thursday_type' => 'full', 'friday_type' => 'full', 'saturday_type' => 'half',
            'sunday_type' => 'off', 'source' => 'built_in_default',
        ];

        if ($designationId) {
            $isManager = (bool) $this->db->fetchColumn(
                'SELECT is_manager_or_above FROM designations WHERE id = :id AND deleted_at IS NULL',
                ['id' => $designationId]
            );
            if ($isManager) {
                $managerPattern = $this->db->fetch(
                    'SELECT monday_type, tuesday_type, wednesday_type, thursday_type, friday_type, saturday_type, sunday_type
                     FROM attendance_week_patterns WHERE company_id = :cid AND is_manager_pattern = 1 AND deleted_at IS NULL LIMIT 1',
                    ['cid' => $companyId]
                );
                if ($managerPattern) {
                    $managerPattern['source'] = 'manager_pattern';
                    return self::$patternCache[$cacheKey] = $managerPattern;
                }
            }
        }

        if ($departmentId) {
            $deptPattern = $this->db->fetch(
                'SELECT p.monday_type, p.tuesday_type, p.wednesday_type, p.thursday_type, p.friday_type, p.saturday_type, p.sunday_type
                 FROM departments d INNER JOIN attendance_week_patterns p ON p.id = d.week_pattern_id AND p.deleted_at IS NULL
                 WHERE d.id = :did',
                ['did' => $departmentId]
            );
            if ($deptPattern) {
                $deptPattern['source'] = 'department_pattern';
                return self::$patternCache[$cacheKey] = $deptPattern;
            }
        }

        $defaultPattern = $this->db->fetch(
            'SELECT monday_type, tuesday_type, wednesday_type, thursday_type, friday_type, saturday_type, sunday_type
             FROM attendance_week_patterns WHERE company_id = :cid AND is_default = 1 AND deleted_at IS NULL LIMIT 1',
            ['cid' => $companyId]
        );
        if ($defaultPattern) {
            $defaultPattern['source'] = 'company_default';
            return self::$patternCache[$cacheKey] = $defaultPattern;
        }

        return self::$patternCache[$cacheKey] = $fallback;
    }

    /**
     * 'full' | 'half' | 'off' for $date under $pattern (as returned by
     * resolveWeekPattern()).
     */
    public static function dayTypeFromPattern(array $pattern, string $date): string
    {
        $columns = [
            1 => 'monday_type', 2 => 'tuesday_type', 3 => 'wednesday_type', 4 => 'thursday_type',
            5 => 'friday_type', 6 => 'saturday_type', 7 => 'sunday_type',
        ];
        $iso = (int) date('N', strtotime($date));
        return $pattern[$columns[$iso]] ?? 'full';
    }

    /**
     * LEFT JOIN clauses wiring up the designation + 3-tier pattern lookup for
     * $employeeAlias, using the fixed aliases dayWeightSql() expects
     * (dsg/mgr_pat/dept_pat/def_pat). Callers add this to their FROM clause
     * once, then use dayWeightSql() anywhere they need the resulting weight.
     */
    public function patternJoinSql(string $employeeAlias = 'e'): string
    {
        return "LEFT JOIN designations dsg ON dsg.id = {$employeeAlias}.designation_id AND dsg.deleted_at IS NULL
            LEFT JOIN attendance_week_patterns mgr_pat ON mgr_pat.company_id = {$employeeAlias}.company_id
                AND mgr_pat.is_manager_pattern = 1 AND mgr_pat.deleted_at IS NULL
            LEFT JOIN departments dept_for_pat ON dept_for_pat.id = {$employeeAlias}.department_id
            LEFT JOIN attendance_week_patterns dept_pat ON dept_pat.id = dept_for_pat.week_pattern_id AND dept_pat.deleted_at IS NULL
            LEFT JOIN attendance_week_patterns def_pat ON def_pat.company_id = {$employeeAlias}.company_id
                AND def_pat.is_default = 1 AND def_pat.deleted_at IS NULL";
    }

    /**
     * Numeric SQL expression (1 / 0.5 / 0) for how much of $dateExpr counts
     * toward a "scheduled working day" — mirrors resolveWeekPattern() +
     * dayTypeFromPattern()'s precedence (designation manager-pattern >
     * department pattern > company default > built-in Mon-Fri-full/Sat-half/
     * Sun-off fallback) exactly, so the bulk SQL path and the per-employee
     * PHP path can never diverge. Requires patternJoinSql() to already be
     * joined into the same query using the same employee alias.
     */
    public function dayWeightSql(string $dateExpr = 'cal.d'): string
    {
        $col = static fn (string $alias, string $day) => "{$alias}.{$day}_type";
        $byWeekday = static function (string $alias) use ($col, $dateExpr): string {
            return "CASE WEEKDAY({$dateExpr})
                WHEN 0 THEN {$col($alias, 'monday')} WHEN 1 THEN {$col($alias, 'tuesday')}
                WHEN 2 THEN {$col($alias, 'wednesday')} WHEN 3 THEN {$col($alias, 'thursday')}
                WHEN 4 THEN {$col($alias, 'friday')} WHEN 5 THEN {$col($alias, 'saturday')}
                ELSE {$col($alias, 'sunday')} END";
        };
        $type = "CASE
            WHEN dsg.is_manager_or_above = 1 AND mgr_pat.id IS NOT NULL THEN {$byWeekday('mgr_pat')}
            WHEN dept_pat.id IS NOT NULL THEN {$byWeekday('dept_pat')}
            WHEN def_pat.id IS NOT NULL THEN {$byWeekday('def_pat')}
            ELSE (CASE WHEN WEEKDAY({$dateExpr}) = 5 THEN 'half' WHEN WEEKDAY({$dateExpr}) = 6 THEN 'off' ELSE 'full' END)
        END";

        return "(CASE ({$type}) WHEN 'full' THEN 1.0 WHEN 'half' THEN 0.5 ELSE 0.0 END)";
    }

    /**
     * SQL fragment + params restricting which employees the current viewer
     * may include in an attendance report, mirroring
     * MonitoringAuthorizationService::employeeScopeSql() (same
     * reporting_manager_id/department_id columns) so scoping rules don't
     * fork into a second implementation.
     *
     * @return array{sql: string, params: array<string, mixed>}
     */
    public function employeeScopeSql(string $alias = 'e'): array
    {
        $user = $this->auth->user();
        if (!$user) {
            return ['sql' => '1 = 0', 'params' => []];
        }

        $tenantScope = $this->tenant->sql("{$alias}.company_id", 'att_scope_company');
        $clauses = [$tenantScope['sql']];
        $params = $tenantScope['params'];

        if ($this->auth->hasRole('super_admin') || $this->auth->can('attendance.report.all_employees')) {
            return ['sql' => implode(' AND ', $clauses), 'params' => $params];
        }

        $me = $this->auth->employee();

        if ($this->auth->can('attendance.report.branch') && $me && !empty($me['branch_id'])) {
            $clauses[] = "{$alias}.branch_id = :att_scope_branch_id";
            $params['att_scope_branch_id'] = (int) $me['branch_id'];
            return ['sql' => implode(' AND ', $clauses), 'params' => $params];
        }

        if ($this->auth->can('attendance.report.department') && $me) {
            if (!empty($me['department_id'])) {
                $clauses[] = "({$alias}.department_id = :att_scope_dept_id OR {$alias}.reporting_manager_id = :att_scope_mgr_id)";
                $params['att_scope_dept_id'] = (int) $me['department_id'];
                $params['att_scope_mgr_id'] = (int) $me['id'];
            } else {
                $clauses[] = "{$alias}.reporting_manager_id = :att_scope_mgr_id";
                $params['att_scope_mgr_id'] = (int) $me['id'];
            }
            return ['sql' => implode(' AND ', $clauses), 'params' => $params];
        }

        // No report-scoping permission at all: viewer may only ever see their own record.
        if ($me) {
            $clauses[] = "{$alias}.id = :att_scope_self_id";
            $params['att_scope_self_id'] = (int) $me['id'];
            return ['sql' => implode(' AND ', $clauses), 'params' => $params];
        }

        return ['sql' => '1 = 0', 'params' => []];
    }

    /**
     * Whether the current viewer may see this specific employee's attendance
     * report/drill-down, per employeeScopeSql(). Every entry point that
     * accepts an employee_id from the request (report tabs, exports, the
     * detail drill-down) must call this before returning any data for it —
     * bulkSummary()/absenceRows() etc. already bake the scope into their own
     * SQL WHERE, but a single-employee lookup like employeeDailyBreakdown()
     * does not, so it is not safe to call with a request-supplied id without
     * this check first.
     */
    public function canAccessEmployee(int $employeeId): bool
    {
        $scope = $this->employeeScopeSql('e');
        if ($scope['sql'] === '1 = 0') {
            return false;
        }
        $row = $this->db->fetch(
            "SELECT e.id FROM employees e WHERE e.id = :eid AND e.deleted_at IS NULL AND ({$scope['sql']}) LIMIT 1",
            array_merge(['eid' => $employeeId], $scope['params'])
        );
        return $row !== null;
    }

    /**
     * The SQL CASE fragment that decides whether a calendar date is a
     * working day for an employee, given a LEFT JOINed shift alias. Used by
     * bulkSummary()'s gap-day query.
     */
    private function workingDayCase(string $dateExpr, string $shiftAlias): string
    {
        return "CASE WHEN {$shiftAlias}.working_days IS NOT NULL AND {$shiftAlias}.working_days <> ''
                    THEN FIND_IN_SET(WEEKDAY({$dateExpr}) + 1, {$shiftAlias}.working_days) > 0
                    ELSE WEEKDAY({$dateExpr}) < 5
                END";
    }

    /**
     * Efficient, SQL-first per-employee summary for a date range: powers the
     * report Overview KPI cards and the Employee Summary table in one pass,
     * for however many employees match $filters. Two queries total
     * (existing attendance rows aggregated; then a single recursive-CTE
     * calendar spine for the *gap* days with no attendance row) — never
     * loops days in PHP per employee, so cost does not grow with employee
     * count beyond the two aggregate queries.
     *
     * @param array{from:string,to:string,branch_id?:int|string|null,department_id?:int|string|null,shift_id?:int|string|null,employee_id?:int|string|null,company_id?:int|string|null} $filters
     * @return array{rows: array<int, array<string, mixed>>, totals: array<string, mixed>}
     */
    public function bulkSummary(array $filters): array
    {
        $from = $filters['from'];
        $to = $filters['to'];
        $scope = $this->employeeScopeSql('e');
        $params = $scope['params'];
        $clauses = [$scope['sql'], 'e.deleted_at IS NULL'];

        if (!empty($filters['branch_id'])) {
            $clauses[] = 'e.branch_id = :f_branch_id';
            $params['f_branch_id'] = (int) $filters['branch_id'];
        }
        if (!empty($filters['department_id'])) {
            $clauses[] = 'e.department_id = :f_department_id';
            $params['f_department_id'] = (int) $filters['department_id'];
        }
        if (!empty($filters['shift_id'])) {
            $clauses[] = 'e.shift_id = :f_shift_id';
            $params['f_shift_id'] = (int) $filters['shift_id'];
        }
        if (!empty($filters['employee_id'])) {
            $clauses[] = 'e.id = :f_employee_id';
            $params['f_employee_id'] = (int) $filters['employee_id'];
        }
        $employeeWhere = implode(' AND ', $clauses);

        $employees = $this->db->fetchAll(
            "SELECT e.id, e.employee_code, CONCAT(e.first_name,' ',e.last_name) AS name,
                    e.joining_date, e.last_working_date, e.branch_id, e.department_id, e.shift_id,
                    d.name AS department_name, b.name AS branch_name, dg.name AS designation_name,
                    s.name AS shift_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN designations dg ON dg.id = e.designation_id
             LEFT JOIN shifts s ON s.id = e.shift_id
             WHERE {$employeeWhere}
             ORDER BY e.first_name ASC, e.last_name ASC",
            $params
        );

        if (!$employees) {
            return ['rows' => [], 'totals' => $this->emptyTotals()];
        }

        $ids = array_map(static fn ($e) => (int) $e['id'], $employees);
        $idPlaceholders = implode(',', $ids);

        // 1) Aggregate existing attendance rows per employee.
        $recorded = $this->db->fetchAll(
            "SELECT
                a.employee_id,
                SUM(a.status IN ('present','manual')) AS present_days,
                SUM(a.status = 'late') AS late_days,
                SUM(a.status = 'half_day') AS half_days,
                SUM(a.status = 'remote' OR a.is_remote = 1) AS wfh_days,
                SUM(a.status = 'manual') AS official_duty_days,
                SUM(a.status = 'absent') AS recorded_absent_days,
                SUM(a.status = 'on_leave') AS recorded_leave_days,
                SUM(a.status = 'on_leave' AND lt.is_paid = 1) AS recorded_paid_leave_days,
                SUM(a.status = 'on_leave' AND lt.is_paid = 0) AS recorded_unpaid_leave_days,
                SUM(a.status = 'on_leave' AND lt.id IS NULL) AS recorded_unlinked_leave_days,
                SUM(a.status = 'holiday') AS recorded_holiday_days,
                SUM(a.status = 'weekend') AS recorded_restday_days,
                SUM(a.status = 'missing_checkout') AS missing_checkout_days,
                SUM(a.early_leave_minutes > 0) AS early_departure_days,
                COALESCE(SUM(a.work_minutes), 0) AS worked_minutes,
                COALESCE(SUM(a.overtime_minutes), 0) AS overtime_minutes,
                COALESCE(SUM(a.expected_work_minutes), 0) AS required_minutes,
                COALESCE(SUM(a.late_minutes), 0) AS late_minutes_total,
                SUM(a.check_in_at IS NOT NULL AND a.check_out_at IS NULL AND a.status NOT IN ('on_leave','holiday','weekend')) AS missing_checkout_open,
                COUNT(*) AS recorded_days
             FROM attendance a
             LEFT JOIN leave_requests lr ON lr.id = a.leave_request_id
             LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE a.employee_id IN ({$idPlaceholders})
               AND a.attendance_date BETWEEN :from AND :to
               AND a.deleted_at IS NULL
             GROUP BY a.employee_id",
            ['from' => $from, 'to' => $to]
        );
        $recordedByEmployee = [];
        foreach ($recorded as $row) {
            $recordedByEmployee[(int) $row['employee_id']] = $row;
        }

        // 2) Classify the gap days (no attendance row) via one calendar-spine query.
        // dayWeight is 1/0.5/0 (full/half/off) from the employee's resolved
        // weekly pattern (manager tier > department override > company
        // default > built-in Mon-Fri-full/Sat-half/Sun-off) — see
        // dayWeightSql()'s docblock. A holiday always wins outright; a
        // half-weighted day splits between its scheduled portion (leave/
        // absent, weighted by dayWeight) and its structural rest portion
        // (1 - dayWeight, folded into rest_days) so every calendar day still
        // sums to exactly 1 across holiday+rest+scheduled.
        $dayWeight = $this->dayWeightSql('cal.d');
        $patternJoins = $this->patternJoinSql('e');
        $today = date('Y-m-d');
        $gaps = $this->db->fetchAll(
            "WITH RECURSIVE cal AS (
                SELECT DATE(:from2) AS d
                UNION ALL SELECT d + INTERVAL 1 DAY FROM cal WHERE d < DATE(:to2)
            )
            SELECT
                e.id AS employee_id,
                SUM(hol.id IS NOT NULL) AS holiday_days,
                SUM(CASE WHEN hol.id IS NULL THEN (1 - ({$dayWeight})) ELSE 0 END) AS rest_days,
                SUM(CASE WHEN hol.id IS NULL AND lr.id IS NOT NULL AND lt.is_paid = 1 AND cal.d <= :today0a
                    THEN ({$dayWeight}) ELSE 0 END) AS paid_leave_days_raw,
                SUM(CASE WHEN hol.id IS NULL AND lr.id IS NOT NULL AND lt.is_paid = 0 AND cal.d <= :today0b
                    THEN ({$dayWeight}) ELSE 0 END) AS unpaid_leave_days_raw,
                SUM(CASE WHEN hol.id IS NULL AND ({$dayWeight}) = 1 AND lr.id IS NOT NULL AND lr.is_half_day = 1
                              AND lt.is_paid = 1 AND cal.d <= :today0c THEN 0.5 ELSE 0 END) AS paid_half_leave_adjustment,
                SUM(CASE WHEN hol.id IS NULL AND ({$dayWeight}) = 1 AND lr.id IS NOT NULL AND lr.is_half_day = 1
                              AND lt.is_paid = 0 AND cal.d <= :today0d THEN 0.5 ELSE 0 END) AS unpaid_half_leave_adjustment,
                SUM(CASE WHEN hol.id IS NULL AND lr.id IS NULL AND cal.d < :today1
                    THEN ({$dayWeight}) ELSE 0 END) AS absent_days,
                SUM(CASE WHEN hol.id IS NULL AND lr.id IS NULL AND cal.d = :today2 AND ({$dayWeight}) > 0
                    THEN 1 ELSE 0 END) AS pending_today_days,
                SUM(CASE WHEN hol.id IS NULL AND cal.d <= :today3
                    THEN ({$dayWeight}) ELSE 0 END) AS scheduled_working_days,
                COUNT(*) AS calendar_days_in_scope
            FROM cal
            CROSS JOIN employees e
            LEFT JOIN attendance a ON a.employee_id = e.id AND a.attendance_date = cal.d AND a.deleted_at IS NULL
            {$patternJoins}
            LEFT JOIN holidays hol ON hol.company_id = e.company_id
                AND (hol.branch_id IS NULL OR hol.branch_id = e.branch_id)
                AND cal.d BETWEEN hol.holiday_date AND COALESCE(hol.end_date, hol.holiday_date)
                AND hol.deleted_at IS NULL
            LEFT JOIN leave_requests lr ON lr.employee_id = e.id AND lr.status = 'approved'
                AND cal.d BETWEEN lr.start_date AND lr.end_date AND lr.deleted_at IS NULL
            LEFT JOIN leave_types lt ON lt.id = lr.leave_type_id
            WHERE e.id IN ({$idPlaceholders})
              AND a.id IS NULL
              AND cal.d >= e.joining_date
              AND (e.last_working_date IS NULL OR cal.d <= e.last_working_date)
            GROUP BY e.id",
            ['from2' => $from, 'to2' => $to, 'today0a' => $today, 'today0b' => $today, 'today0c' => $today,
                'today0d' => $today, 'today1' => $today, 'today2' => $today, 'today3' => $today]
        );
        $gapsByEmployee = [];
        foreach ($gaps as $row) {
            $gapsByEmployee[(int) $row['employee_id']] = $row;
        }

        $rows = [];
        $totals = $this->emptyTotals();

        foreach ($employees as $emp) {
            $eid = (int) $emp['id'];
            $rec = $recordedByEmployee[$eid] ?? [];
            $gap = $gapsByEmployee[$eid] ?? [];

            $present = (float) ($rec['present_days'] ?? 0) + (float) ($rec['late_days'] ?? 0)
                + (float) ($rec['wfh_days'] ?? 0) + (float) ($rec['half_days'] ?? 0) * 0.5;
            // A half-day leave gap (no attendance row) only proves half the
            // day is leave — the other half counts as absent, not present,
            // since there is no attendance record showing it was worked.
            // paid_leave_days_raw/unpaid_leave_days_raw count every matching
            // day as a full 1 regardless of is_half_day; the *_half_leave_adjustment
            // sums already carry the 0.5-per-occurrence correction (split by
            // paid/unpaid so a mix of both in one range can't cross-cancel).
            $paidHalfAdj = (float) ($gap['paid_half_leave_adjustment'] ?? 0);
            $unpaidHalfAdj = (float) ($gap['unpaid_half_leave_adjustment'] ?? 0);
            $absent = (float) ($rec['recorded_absent_days'] ?? 0) + (float) ($gap['absent_days'] ?? 0)
                + (float) ($rec['half_days'] ?? 0) * 0.5 + $paidHalfAdj + $unpaidHalfAdj;
            $paidLeave = max(0, (float) ($gap['paid_leave_days_raw'] ?? 0) - $paidHalfAdj)
                + (float) ($rec['recorded_paid_leave_days'] ?? 0) + (float) ($rec['recorded_unlinked_leave_days'] ?? 0);
            $unpaidLeave = max(0, (float) ($gap['unpaid_leave_days_raw'] ?? 0) - $unpaidHalfAdj)
                + (float) ($rec['recorded_unpaid_leave_days'] ?? 0);
            $leaveTotal = $paidLeave + $unpaidLeave;
            $holidayDays = (float) ($rec['recorded_holiday_days'] ?? 0) + (float) ($gap['holiday_days'] ?? 0);
            $restDays = (float) ($rec['recorded_restday_days'] ?? 0) + (float) ($gap['rest_days'] ?? 0);
            $scheduledWorkingDays = (float) ($gap['scheduled_working_days'] ?? 0)
                + (float) ($rec['present_days'] ?? 0) + (float) ($rec['late_days'] ?? 0) + (float) ($rec['half_days'] ?? 0)
                + (float) ($rec['wfh_days'] ?? 0) + (float) ($rec['recorded_absent_days'] ?? 0)
                + (float) ($rec['recorded_leave_days'] ?? 0) + (float) ($rec['missing_checkout_days'] ?? 0);
            $workedMinutes = (int) ($rec['worked_minutes'] ?? 0);
            $requiredMinutes = (int) ($rec['required_minutes'] ?? 0);
            $overtimeMinutes = (int) ($rec['overtime_minutes'] ?? 0);
            $undertimeMinutes = max(0, $requiredMinutes - $workedMinutes - $overtimeMinutes);
            $missingAttendance = (int) ($rec['missing_checkout_days'] ?? 0) + (int) ($rec['missing_checkout_open'] ?? 0);
            $attendancePct = $scheduledWorkingDays > 0 ? round(($present / $scheduledWorkingDays) * 100, 1) : 0.0;

            $row = [
                'employee_id' => $eid,
                'employee_code' => $emp['employee_code'],
                'employee_name' => $emp['name'],
                'department' => $emp['department_name'],
                'designation' => $emp['designation_name'],
                'branch' => $emp['branch_name'],
                'shift' => $emp['shift_name'],
                'scheduled_working_days' => round($scheduledWorkingDays, 1),
                'present_days' => round($present, 1),
                'absent_days' => round($absent, 1),
                'paid_leave_days' => round($paidLeave, 1),
                'unpaid_leave_days' => round($unpaidLeave, 1),
                'leave_days' => round($leaveTotal, 1),
                'half_days' => (float) ($rec['half_days'] ?? 0),
                'late_days' => (float) ($rec['late_days'] ?? 0),
                'early_departures' => (float) ($rec['early_departure_days'] ?? 0),
                'holiday_days' => round($holidayDays, 1),
                'rest_days' => round($restDays, 1),
                'missing_attendance' => $missingAttendance,
                'worked_minutes' => $workedMinutes,
                'required_minutes' => $requiredMinutes,
                'overtime_minutes' => $overtimeMinutes,
                'undertime_minutes' => $undertimeMinutes,
                'attendance_pct' => $attendancePct,
                'not_checked_in_today' => (float) ($gap['pending_today_days'] ?? 0) > 0,
            ];
            $rows[] = $row;

            foreach (['present_days', 'absent_days', 'paid_leave_days', 'unpaid_leave_days', 'leave_days',
                'half_days', 'late_days', 'early_departures', 'holiday_days', 'rest_days',
                'missing_attendance', 'worked_minutes', 'required_minutes', 'overtime_minutes',
                'undertime_minutes', 'scheduled_working_days'] as $key) {
                $totals[$key] += $row[$key];
            }
            $totals['employee_count']++;
            if ($row['not_checked_in_today']) {
                $totals['not_checked_in_today']++;
            }
        }

        $totals['attendance_pct'] = $totals['scheduled_working_days'] > 0
            ? round(($totals['present_days'] / $totals['scheduled_working_days']) * 100, 1) : 0.0;
        $totals['absence_pct'] = $totals['scheduled_working_days'] > 0
            ? round(($totals['absent_days'] / $totals['scheduled_working_days']) * 100, 1) : 0.0;
        $totals['late_pct'] = $totals['present_days'] > 0
            ? round(($totals['late_days'] / $totals['present_days']) * 100, 1) : 0.0;
        $totals['avg_worked_hours'] = $totals['employee_count'] > 0
            ? round(($totals['worked_minutes'] / 60) / $totals['employee_count'], 1) : 0.0;

        return ['rows' => $rows, 'totals' => $totals];
    }

    private function emptyTotals(): array
    {
        return [
            'employee_count' => 0, 'present_days' => 0.0, 'absent_days' => 0.0,
            'paid_leave_days' => 0.0, 'unpaid_leave_days' => 0.0, 'leave_days' => 0.0,
            'half_days' => 0.0, 'late_days' => 0.0, 'early_departures' => 0.0,
            'holiday_days' => 0.0, 'rest_days' => 0.0, 'missing_attendance' => 0,
            'worked_minutes' => 0, 'required_minutes' => 0, 'overtime_minutes' => 0,
            'undertime_minutes' => 0, 'scheduled_working_days' => 0.0,
            'not_checked_in_today' => 0, 'attendance_pct' => 0.0, 'absence_pct' => 0.0,
            'late_pct' => 0.0, 'avg_worked_hours' => 0.0,
        ];
    }

    /**
     * Day-by-day breakdown for exactly one employee — the "how were these
     * totals calculated" drill-down. Bounded to the requested range (a
     * month is ~31 rows) so, unlike bulkSummary(), it resolves the *exact*
     * shift for each date via Shift::findForEmployee() (correctly handling
     * shift_rosters/shift_assignments overrides and mid-range shift
     * changes) rather than the employee's primary shift_id fast path.
     *
     * @return array{employee: array, days: array<int, array<string, mixed>>, summary: array<string, mixed>}
     */
    public function employeeDailyBreakdown(int $employeeId, string $from, string $to): array
    {
        $employee = $this->db->fetch(
            'SELECT e.*, d.name AS department_name, b.name AS branch_name, dg.name AS designation_name
             FROM employees e
             LEFT JOIN departments d ON d.id = e.department_id
             LEFT JOIN branches b ON b.id = e.branch_id
             LEFT JOIN designations dg ON dg.id = e.designation_id
             WHERE e.id = :id',
            ['id' => $employeeId]
        );
        if (!$employee) {
            return ['employee' => [], 'days' => [], 'summary' => []];
        }

        $attendanceRows = $this->db->fetchAll(
            "SELECT a.*, s.name AS shift_name, s.start_time AS shift_start, s.end_time AS shift_end
             FROM attendance a
             LEFT JOIN shifts s ON s.id = a.shift_id
             WHERE a.employee_id = :eid AND a.attendance_date BETWEEN :from AND :to AND a.deleted_at IS NULL",
            ['eid' => $employeeId, 'from' => $from, 'to' => $to]
        );
        $byDate = [];
        foreach ($attendanceRows as $row) {
            $byDate[$row['attendance_date']] = $row;
        }

        $leaveRows = $this->db->fetchAll(
            "SELECT lr.start_date, lr.end_date, lr.is_half_day, lr.half_day_type, lt.name AS leave_type_name, lt.is_paid
             FROM leave_requests lr INNER JOIN leave_types lt ON lt.id = lr.leave_type_id
             WHERE lr.employee_id = :eid AND lr.status = 'approved'
               AND lr.start_date <= :to AND lr.end_date >= :from AND lr.deleted_at IS NULL",
            ['eid' => $employeeId, 'from' => $from, 'to' => $to]
        );

        $holidayRows = $this->db->fetchAll(
            "SELECT holiday_date, COALESCE(end_date, holiday_date) AS end_date, name
             FROM holidays
             WHERE company_id = :cid AND (branch_id IS NULL OR branch_id = :bid)
               AND holiday_date <= :to AND COALESCE(end_date, holiday_date) >= :from AND deleted_at IS NULL",
            ['cid' => $employee['company_id'], 'bid' => $employee['branch_id'], 'from' => $from, 'to' => $to]
        );

        $pendingCorrections = $this->db->fetchAll(
            "SELECT attendance_id FROM attendance_corrections
             WHERE employee_id = :eid AND status = 'pending' AND deleted_at IS NULL",
            ['eid' => $employeeId]
        );
        $pendingByAttendanceId = array_fill_keys(array_map(static fn ($r) => (int) $r['attendance_id'], $pendingCorrections), true);

        $shiftModel = new Shift();
        $pattern = $this->resolveWeekPattern(
            (int) $employee['company_id'],
            $employee['designation_id'] !== null ? (int) $employee['designation_id'] : null,
            $employee['department_id'] !== null ? (int) $employee['department_id'] : null
        );
        $today = date('Y-m-d');
        $days = [];
        $summary = ['scheduled_working_days' => 0.0, 'present_days' => 0.0, 'absent_days' => 0.0,
            'paid_leave_days' => 0.0, 'unpaid_leave_days' => 0.0, 'late_days' => 0, 'half_days' => 0.0,
            'worked_minutes' => 0, 'required_minutes' => 0, 'overtime_minutes' => 0, 'undertime_minutes' => 0,
            'holiday_days' => 0.0, 'rest_days' => 0.0, 'missing_attendance' => 0];

        $joining = $employee['joining_date'];
        $lastWorking = $employee['last_working_date'];

        for ($cursor = strtotime($from); $cursor <= strtotime($to); $cursor += 86400) {
            $date = date('Y-m-d', $cursor);
            if ($date < $joining || ($lastWorking && $date > $lastWorking)) {
                continue;
            }

            $row = $byDate[$date] ?? null;
            $shift = $row ? [
                'start_time' => $row['shift_start'] ?? null,
                'end_time' => $row['shift_end'] ?? null,
            ] : ($shiftModel->findForEmployee($employeeId, $date) ?? []);

            $leaveToday = null;
            foreach ($leaveRows as $lr) {
                if ($date >= $lr['start_date'] && $date <= $lr['end_date']) {
                    $leaveToday = $lr;
                    break;
                }
            }
            $holidayToday = null;
            foreach ($holidayRows as $h) {
                if ($date >= $h['holiday_date'] && $date <= $h['end_date']) {
                    $holidayToday = $h;
                    break;
                }
            }

            $dayType = self::dayTypeFromPattern($pattern, $date);
            $dayWeight = match ($dayType) { 'full' => 1.0, 'half' => 0.5, default => 0.0 };
            $isWorking = $dayType !== 'off';
            $classification = $this->classifyDay($row, $leaveToday, $holidayToday, $isWorking, $date, $today);

            $hasPendingCorrection = !empty($row['id']) && isset($pendingByAttendanceId[(int) $row['id']]);
            if ($hasPendingCorrection) {
                // Flag for display only — keep the original category so
                // present/absent/leave totals still reconcile; a pending
                // correction changes what the day *might* become, not what
                // it currently is.
                $classification['label'] .= ' (Pending Regularization)';
            }

            $worked = (int) ($row['work_minutes'] ?? 0);
            $required = (int) ($row['expected_work_minutes'] ?? 0);
            $overtime = (int) ($row['overtime_minutes'] ?? 0);
            $undertime = $row ? max(0, $required - $worked - $overtime) : 0;

            $days[] = [
                'date' => $date,
                'day_name' => date('D', $cursor),
                'is_scheduled_working_day' => $isWorking,
                'shift_name' => $row['shift_name'] ?? ($shift['name'] ?? null),
                'scheduled_in' => $shift['start_time'] ?? null,
                'scheduled_out' => $shift['end_time'] ?? null,
                'check_in_at' => $row['check_in_at'] ?? null,
                'check_out_at' => $row['check_out_at'] ?? null,
                'status' => $classification['category'],
                'status_label' => $classification['label'],
                'late_minutes' => (int) ($row['late_minutes'] ?? 0),
                'early_leave_minutes' => (int) ($row['early_leave_minutes'] ?? 0),
                'worked_minutes' => $worked,
                'overtime_minutes' => $overtime,
                'undertime_minutes' => $undertime,
                'leave_type' => $leaveToday['leave_type_name'] ?? null,
                'source' => $row['source'] ?? null,
                'verification_status' => $row['verification_status'] ?? null,
                'is_remote' => (bool) ($row['is_remote'] ?? false),
                'remarks' => $row['remarks'] ?? $row['admin_notes'] ?? null,
                'attendance_id' => $row['id'] ?? null,
                'has_pending_correction' => $hasPendingCorrection,
            ];

            // A future date with no attendance row yet is not knowable as
            // present/absent/leave — it is shown in the day-by-day table
            // (category 'scheduled') but deliberately excluded from every
            // summary bucket, including the working-days denominator, so
            // Present+Absent+Leave+Holiday+RestDay always reconciles exactly
            // against Scheduled Working Days for the elapsed portion of the
            // period (see AttendanceCalculationService::bulkSummary() for
            // the same rule applied to the multi-employee aggregate).
            //
            // A "half" scheduled day (e.g. Saturday under the standard
            // pattern) splits into a structural rest portion (1-dayWeight,
            // always counted here — like a holiday/rest day it's a calendar
            // fact, not something that becomes "unknown" in the future) and
            // a working portion (dayWeight) that behaves exactly like a full
            // day, just scaled — resolved below only when the date has
            // actually elapsed. A real attendance row is ground truth
            // regardless of the day's scheduled weight, so it always counts
            // as a flat 1, matching how a recorded row is treated everywhere
            // else in this class.
            if ($classification['category'] === 'holiday') {
                $summary['holiday_days'] += 1;
            } else {
                $summary['rest_days'] += (1 - $dayWeight);

                if ($classification['category'] === 'scheduled' || $classification['category'] === 'rest_day') {
                    // future date, or a pure off day — working portion is
                    // either not yet due or doesn't exist; nothing more to add.
                } elseif (in_array($classification['category'], ['paid_leave', 'sick_leave', 'unpaid_leave'], true)) {
                    // A half-day leave request only discounts the leave amount
                    // when the day would otherwise be fully scheduled — on an
                    // already-half-scheduled day, the leave simply consumes
                    // that day's whole working portion.
                    $leaveAmount = ($dayWeight === 1.0 && $classification['is_half']) ? 0.5 : $dayWeight;
                    $summary['scheduled_working_days'] += $dayWeight;
                    $bucket = $classification['category'] === 'unpaid_leave' ? 'unpaid_leave_days' : 'paid_leave_days';
                    $summary[$bucket] += $leaveAmount;
                    $summary['absent_days'] += ($dayWeight - $leaveAmount);
                } elseif ($row) {
                    // Recorded attendance row: ground truth, flat 1 regardless
                    // of the day's scheduled weight.
                    $summary['scheduled_working_days'] += 1;
                    if (in_array($classification['category'], ['present', 'late', 'work_from_home', 'official_duty'], true)) {
                        $summary['present_days'] += 1;
                    } elseif ($classification['category'] === 'half_day') {
                        $summary['present_days'] += 0.5;
                        $summary['absent_days'] += 0.5;
                        $summary['half_days'] += 1;
                    } elseif ($classification['category'] === 'absent') {
                        $summary['absent_days'] += 1;
                    } elseif ($classification['category'] === 'missing_attendance') {
                        $summary['missing_attendance']++;
                    }
                } else {
                    // Gap day (no attendance row): scale by the day's weight.
                    $summary['scheduled_working_days'] += $dayWeight;
                    if ($classification['category'] === 'absent') {
                        $summary['absent_days'] += $dayWeight;
                    } elseif ($classification['category'] === 'missing_attendance') {
                        // "Not checked in yet today" — a flag, not summed
                        // elsewhere, so left as a plain occurrence count
                        // (mirrors bulkSummary()'s pending_today_days).
                        $summary['missing_attendance']++;
                    }
                }
            }
            if ($classification['category'] === 'late') {
                $summary['late_days']++;
            }
            $summary['worked_minutes'] += $worked;
            $summary['required_minutes'] += $required;
            $summary['overtime_minutes'] += $overtime;
            $summary['undertime_minutes'] += $undertime;
        }

        $summary['attendance_pct'] = $summary['scheduled_working_days'] > 0
            ? round(($summary['present_days'] / $summary['scheduled_working_days']) * 100, 1) : 0.0;

        return ['employee' => $employee, 'days' => $days, 'summary' => $summary];
    }

    /**
     * @return array{label: string, category: string, is_half: bool}
     */
    private function classifyDay(?array $attendanceRow, ?array $leave, ?array $holiday, bool $isWorking, string $date, string $today): array
    {
        if ($attendanceRow) {
            $status = (string) $attendanceRow['status'];
            $isRemote = (bool) ($attendanceRow['is_remote'] ?? false);
            return match (true) {
                $status === 'remote' || $isRemote => ['label' => 'Work From Home', 'category' => 'work_from_home', 'is_half' => false],
                $status === 'manual' => ['label' => 'Official Duty', 'category' => 'official_duty', 'is_half' => false],
                $status === 'missing_checkout' => ['label' => 'Missing Checkout', 'category' => 'missing_attendance', 'is_half' => false],
                $status === 'half_day' => ['label' => 'Half Day', 'category' => 'half_day', 'is_half' => false],
                $status === 'late' => ['label' => 'Late', 'category' => 'late', 'is_half' => false],
                $status === 'holiday' => ['label' => 'Holiday', 'category' => 'holiday', 'is_half' => false],
                $status === 'weekend' => ['label' => 'Rest Day', 'category' => 'rest_day', 'is_half' => false],
                $status === 'on_leave' => [
                    'label' => ($leave['leave_type_name'] ?? 'Leave') . (!empty($leave['is_half_day']) ? ' (Half Day)' : ''),
                    'category' => $leave && empty($leave['is_paid']) ? 'unpaid_leave'
                        : (($leave && stripos((string) ($leave['leave_type_name'] ?? ''), 'sick') !== false) ? 'sick_leave' : 'paid_leave'),
                    'is_half' => (bool) ($leave['is_half_day'] ?? false),
                ],
                $status === 'absent' => ['label' => 'Absent', 'category' => 'absent', 'is_half' => false],
                default => ['label' => ucfirst(str_replace('_', ' ', $status)), 'category' => 'present', 'is_half' => false],
            };
        }

        if ($holiday) {
            return ['label' => 'Holiday: ' . $holiday['name'], 'category' => 'holiday', 'is_half' => false];
        }

        if (!$isWorking) {
            return ['label' => 'Rest Day', 'category' => 'rest_day', 'is_half' => false];
        }

        if ($leave) {
            $isHalf = (bool) ($leave['is_half_day'] ?? false);
            $paid = (bool) ($leave['is_paid'] ?? true);
            $typeName = (string) ($leave['leave_type_name'] ?? 'Leave');
            $isSick = stripos($typeName, 'sick') !== false;
            return [
                'label' => $typeName . ($isHalf ? ' (Half Day)' : ''),
                'category' => $isSick ? 'sick_leave' : ($paid ? 'paid_leave' : 'unpaid_leave'),
                'is_half' => $isHalf,
            ];
        }

        if ($date > $today) {
            return ['label' => 'Scheduled', 'category' => 'scheduled', 'is_half' => false];
        }

        if ($date === $today) {
            return ['label' => 'Not Checked In', 'category' => 'missing_attendance', 'is_half' => false];
        }

        return ['label' => 'Absent', 'category' => 'absent', 'is_half' => false];
    }

    /**
     * Payroll-compatible attendance summary for one employee: the same
     * present/absent/leave/holiday/weekend/working-day shape
     * PayrollService::finalizeAttendance()+reconcileLeave() already produce
     * (so a payroll run's numbers reconcile with what the attendance report
     * shows), but gap-filled correctly instead of only counting rows that
     * happen to already exist in `attendance` (today, if the mark_absences
     * cron has not run for a date, payroll silently sees 0 absences for it).
     */
    public function payrollAttendanceSummary(int $employeeId, string $startDate, string $endDate): array
    {
        $breakdown = $this->employeeDailyBreakdown($employeeId, $startDate, $endDate);
        $summary = $breakdown['summary'];

        $overtimeApproved = $this->db->fetch(
            "SELECT COALESCE(SUM(COALESCE(approved_minutes, requested_minutes)), 0) AS minutes
             FROM overtime_requests
             WHERE employee_id = :eid AND status = 'approved'
               AND overtime_date BETWEEN :start AND :end AND deleted_at IS NULL",
            ['eid' => $employeeId, 'start' => $startDate, 'end' => $endDate]
        );

        return [
            'working_days' => $summary['scheduled_working_days'] ?? 0.0,
            'present_days' => $summary['present_days'] ?? 0.0,
            'absent_days' => $summary['absent_days'] ?? 0.0,
            'paid_leave_days' => $summary['paid_leave_days'] ?? 0.0,
            'unpaid_leave_days' => $summary['unpaid_leave_days'] ?? 0.0,
            'leave_days' => ($summary['paid_leave_days'] ?? 0.0) + ($summary['unpaid_leave_days'] ?? 0.0),
            'holiday_days' => $summary['holiday_days'] ?? 0.0,
            'weekend_days' => $summary['rest_days'] ?? 0.0,
            'half_days' => $summary['half_days'] ?? 0.0,
            'required_minutes' => $summary['required_minutes'] ?? 0,
            'worked_minutes' => $summary['worked_minutes'] ?? 0,
            'calculated_overtime_minutes' => $summary['overtime_minutes'] ?? 0,
            'approved_overtime_minutes' => (int) ($overtimeApproved['minutes'] ?? 0),
            'undertime_minutes' => $summary['undertime_minutes'] ?? 0,
            'payable_days' => ($summary['present_days'] ?? 0.0) + ($summary['paid_leave_days'] ?? 0.0)
                + ($summary['holiday_days'] ?? 0.0),
        ];
    }
}
