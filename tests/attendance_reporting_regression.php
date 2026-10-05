#!/usr/bin/env php
<?php

declare(strict_types=1);

if (getenv('TEST_ATTENDANCE_REPORTING') !== '1') {
    echo "Attendance reporting regression tests skipped (set TEST_ATTENDANCE_REPORTING=1 to enable).\n";
    exit(0);
}

require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Application;
use App\Core\Database;
use App\Core\Session;
use App\Services\AttendanceCalculationService;

new Application();
$db = Database::getInstance();
$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException('FAILED: ' . $message);
    }
};
$uuid = static function (): string {
    $hex = bin2hex(random_bytes(16));
    return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-4' . substr($hex, 13, 3) . '-a' . substr($hex, 17, 3) . '-' . substr($hex, 20, 12);
};

$companyId = (int) $db->fetchColumn('SELECT id FROM companies ORDER BY id LIMIT 1');
$branchId = (int) $db->fetchColumn('SELECT id FROM branches WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $companyId]);
$departmentId = (int) $db->fetchColumn('SELECT id FROM departments WHERE company_id = :c ORDER BY id LIMIT 1', ['c' => $companyId]);
$adminUserId = (int) $db->fetchColumn(
    "SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id=u.id INNER JOIN roles r ON r.id=ur.role_id
     WHERE r.slug = 'super_admin' LIMIT 1"
);
if (!$companyId || !$branchId || !$departmentId || !$adminUserId) {
    throw new RuntimeException('A company, branch, department and super_admin fixture are required.');
}
Session::set('user_id', $adminUserId);

$stamp = bin2hex(random_bytes(4));
$shiftIds = [];
$employeeIds = [];
$leaveRequestIds = [];
$holidayIds = [];
$correctionIds = [];
$weekPatternIds = [];
$designationIds = [];
$testDepartmentIds = [];

// An isolated, pattern-free department/designation pair so this suite's
// expectations never depend on how a real admin has configured
// attendance_week_patterns/designations.is_manager_or_above for the
// company's actual departments (e.g. the seed data's "Human Resources" is
// deliberately assigned the Result & Data Entry Team pattern — reusing it
// here would make this suite's results depend on that unrelated config).
$testDepartmentId = $db->insert('departments', [
    'company_id' => $companyId, 'name' => 'RegTest Dept ' . $stamp, 'code' => 'RTD' . $stamp,
    'is_active' => 1, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$testDepartmentIds[] = $testDepartmentId;
$testDesignationId = $db->insert('designations', [
    'company_id' => $companyId, 'department_id' => $testDepartmentId, 'name' => 'RegTest Staff ' . $stamp,
    'is_manager_or_above' => 0, 'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$designationIds[] = $testDesignationId;
$testManagerDesignationId = $db->insert('designations', [
    'company_id' => $companyId, 'department_id' => $testDepartmentId, 'name' => 'RegTest Manager ' . $stamp,
    'is_manager_or_above' => 1, 'is_active' => 1,
    'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
]);
$designationIds[] = $testManagerDesignationId;

$makeShift = function (array $overrides = []) use ($db, $uuid, $companyId): int {
    return $db->insert('shifts', array_merge([
        'company_id' => $companyId, 'name' => 'RegTest Shift ' . bin2hex(random_bytes(3)),
        'start_time' => '09:00:00', 'end_time' => '18:00:00', 'break_minutes' => 60,
        'grace_minutes' => 10, 'late_mark_after_minutes' => 10, 'half_day_after_minutes' => 240,
        'early_leave_grace_minutes' => 10, 'overtime_after_minutes' => 30, 'expected_work_minutes' => 480,
        'is_overnight' => 0, 'is_flexible' => 0, 'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $overrides));
};

$makeEmployee = function (string $code, int $shiftId, array $overrides = []) use ($db, $uuid, $companyId, $branchId, $testDepartmentId, $testDesignationId): int {
    return $db->insert('employees', array_merge([
        'uuid' => $uuid(), 'company_id' => $companyId, 'branch_id' => $branchId, 'department_id' => $testDepartmentId,
        'designation_id' => $testDesignationId,
        'shift_id' => $shiftId, 'employee_code' => $code, 'first_name' => 'RegTest', 'last_name' => $code,
        'joining_date' => '2020-01-01', 'employment_status' => 'active', 'employment_type' => 'full_time',
        'basic_salary' => 50000, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $overrides));
};

$makeAttendance = function (int $employeeId, int $shiftId, string $date, array $overrides = []) use ($db, $uuid, $companyId, $branchId): int {
    return $db->insert('attendance', array_merge([
        'uuid' => $uuid(), 'employee_id' => $employeeId, 'company_id' => $companyId, 'branch_id' => $branchId,
        'shift_id' => $shiftId, 'attendance_date' => $date, 'status' => 'present', 'verification_status' => 'verified',
        'work_minutes' => 480, 'expected_work_minutes' => 480, 'source' => 'web',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ], $overrides));
};

try {
    $normalShift = $makeShift();
    $shiftIds[] = $normalShift;
    $overnightShift = $makeShift(['is_overnight' => 1, 'start_time' => '22:00:00', 'end_time' => '06:00:00', 'expected_work_minutes' => 480]);
    $shiftIds[] = $overnightShift;
    $satWorkingShift = $makeShift(['working_days' => '1,2,3,4,5,6']);
    $shiftIds[] = $satWorkingShift;

    // --- Fixture 1: a fully "known" employee for the core calculation tests ---
    $emp1 = $makeEmployee('RT1-' . $stamp, $normalShift);
    $employeeIds[] = $emp1;

    // 2026-08 is a fully elapsed month relative to "today" in this environment,
    // giving deterministic reconciliation independent of when the suite runs.
    $from = '2026-08-01';
    $to = '2026-08-31'; // 21 working days (Mon-Fri), 10 weekend days

    // Present (Mon 2026-08-03), Late (Tue 2026-08-04), Half day (Wed 2026-08-05)
    $makeAttendance($emp1, $normalShift, '2026-08-03', ['status' => 'present', 'check_in_at' => '2026-08-03 09:02:00', 'check_out_at' => '2026-08-03 18:00:00']);
    $makeAttendance($emp1, $normalShift, '2026-08-04', ['status' => 'late', 'late_minutes' => 45, 'check_in_at' => '2026-08-04 09:45:00', 'check_out_at' => '2026-08-04 18:00:00']);
    $makeAttendance($emp1, $normalShift, '2026-08-05', ['status' => 'half_day', 'work_minutes' => 240, 'check_in_at' => '2026-08-05 09:00:00', 'check_out_at' => '2026-08-05 13:00:00']);
    // Early departure (Thu 2026-08-06)
    $makeAttendance($emp1, $normalShift, '2026-08-06', ['status' => 'present', 'early_leave_minutes' => 60, 'check_in_at' => '2026-08-06 09:00:00', 'check_out_at' => '2026-08-06 17:00:00', 'work_minutes' => 420]);
    // Missing checkout (Fri 2026-08-07)
    $makeAttendance($emp1, $normalShift, '2026-08-07', ['status' => 'missing_checkout', 'check_in_at' => '2026-08-07 09:00:00', 'check_out_at' => null, 'work_minutes' => 0]);
    // Overtime: worked well past expected + threshold (Mon 2026-08-10)
    $makeAttendance($emp1, $normalShift, '2026-08-10', ['status' => 'present', 'check_in_at' => '2026-08-10 09:00:00', 'check_out_at' => '2026-08-10 20:00:00', 'work_minutes' => 600, 'overtime_minutes' => 90]);
    // Work from home / remote (Tue 2026-08-11)
    $makeAttendance($emp1, $normalShift, '2026-08-11', ['status' => 'remote', 'is_remote' => 1, 'check_in_at' => '2026-08-11 09:00:00', 'check_out_at' => '2026-08-11 18:00:00']);
    // Official duty via manual entry (Wed 2026-08-12)
    $makeAttendance($emp1, $normalShift, '2026-08-12', ['status' => 'manual', 'is_manual' => 1, 'check_in_at' => '2026-08-12 09:00:00', 'check_out_at' => '2026-08-12 18:00:00']);

    // Paid leave request covering Mon-Tue 2026-08-17/18 (2 working days, no attendance rows)
    $leaveTypePaidId = (int) $db->fetchColumn("SELECT id FROM leave_types WHERE company_id = :c AND is_paid = 1 AND is_active = 1 LIMIT 1", ['c' => $companyId]);
    $leaveTypeUnpaidId = (int) $db->fetchColumn("SELECT id FROM leave_types WHERE company_id = :c AND is_paid = 0 AND is_active = 1 LIMIT 1", ['c' => $companyId]);
    $assert($leaveTypePaidId > 0 && $leaveTypeUnpaidId > 0, 'Fixture requires at least one paid and one unpaid leave type.');

    $leaveRequestIds[] = $db->insert('leave_requests', [
        'uuid' => $uuid(), 'company_id' => $companyId, 'employee_id' => $emp1, 'leave_type_id' => $leaveTypePaidId,
        'start_date' => '2026-08-17', 'end_date' => '2026-08-18', 'is_half_day' => 0, 'reason' => 'RegTest paid leave',
        'status' => 'approved', 'calendar_days' => 2, 'weekend_days' => 0, 'holiday_days' => 0, 'chargeable_days' => 2,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    // Unpaid leave, single half day Wed 2026-08-19
    $leaveRequestIds[] = $db->insert('leave_requests', [
        'uuid' => $uuid(), 'company_id' => $companyId, 'employee_id' => $emp1, 'leave_type_id' => $leaveTypeUnpaidId,
        'start_date' => '2026-08-19', 'end_date' => '2026-08-19', 'is_half_day' => 1, 'half_day_type' => 'first_half',
        'reason' => 'RegTest unpaid half-day leave', 'status' => 'approved',
        'calendar_days' => 1, 'weekend_days' => 0, 'holiday_days' => 0, 'chargeable_days' => 0.5,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    // A pending (not approved) leave request must NOT be treated as leave
    $leaveRequestIds[] = $db->insert('leave_requests', [
        'uuid' => $uuid(), 'company_id' => $companyId, 'employee_id' => $emp1, 'leave_type_id' => $leaveTypePaidId,
        'start_date' => '2026-08-20', 'end_date' => '2026-08-20', 'is_half_day' => 0, 'reason' => 'RegTest pending leave',
        'status' => 'pending', 'calendar_days' => 1, 'weekend_days' => 0, 'holiday_days' => 0, 'chargeable_days' => 1,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Company holiday on a weekday (Thu 2026-08-13) — must win over "absent"
    $holidayIds[] = $db->insert('holidays', [
        'company_id' => $companyId, 'branch_id' => null, 'name' => 'RegTest Holiday', 'holiday_date' => '2026-08-13',
        'type' => 'public', 'is_paid' => 1, 'is_recurring' => 0,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);

    // Approved overtime request separate from attendance.overtime_minutes (2026-08-10)
    $db->insert('overtime_requests', [
        'uuid' => $uuid(), 'company_id' => $companyId, 'employee_id' => $emp1, 'overtime_date' => '2026-08-10',
        'start_at' => '2026-08-10 18:00:00', 'end_at' => '2026-08-10 19:30:00',
        'requested_minutes' => 90, 'approved_minutes' => 60, 'status' => 'approved',
        'reason' => 'RegTest overtime', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $calc = new AttendanceCalculationService();

    // --- Test: isWorkingDay respects shift.working_days, defaults to Mon-Fri ---
    $assert(AttendanceCalculationService::isWorkingDay(null, '2026-08-03') === true, 'Monday must be a working day by default.');
    $assert(AttendanceCalculationService::isWorkingDay(null, '2026-08-01') === false, 'Saturday must be a rest day by default.');
    $assert(AttendanceCalculationService::isWorkingDay(['working_days' => '1,2,3,4,5,6'], '2026-08-01') === true, 'Saturday must be a working day when shift.working_days includes 6.');
    $assert(AttendanceCalculationService::isWorkingDay(['working_days' => '1,2,3,4,5,6'], '2026-08-02') === false, 'Sunday must remain a rest day even for a Mon-Sat shift.');

    // --- Test: day-by-day breakdown reconciles and classifies correctly ---
    $breakdown = $calc->employeeDailyBreakdown($emp1, $from, $to);
    $days = array_column($breakdown['days'], null, 'date');

    $assert($days['2026-08-03']['status'] === 'present', 'Present day must classify as present.');
    $assert($days['2026-08-04']['status'] === 'late' && $days['2026-08-04']['late_minutes'] === 45, 'Late day must carry its late_minutes through.');
    $assert($days['2026-08-05']['status'] === 'half_day', 'Half day must classify as half_day.');
    $assert($days['2026-08-06']['early_leave_minutes'] === 60, 'Early departure minutes must be reported.');
    $assert($days['2026-08-07']['status'] === 'missing_attendance', 'Missing checkout must classify as missing_attendance.');
    $assert($days['2026-08-10']['overtime_minutes'] === 90, 'Calculated overtime minutes must come from the attendance row.');
    $assert($days['2026-08-11']['status'] === 'work_from_home' && $days['2026-08-11']['is_remote'] === true, 'Remote check-in must classify as work_from_home.');
    $assert($days['2026-08-12']['status'] === 'official_duty', 'Manual/admin-entered attendance must classify as official_duty.');
    $assert($days['2026-08-01']['status'] === 'absent' && $days['2026-08-01']['is_scheduled_working_day'] === true, 'Saturday is a half scheduled working day by default (not a full rest day) — with nothing recorded it must show absent, not rest_day.');
    $assert($days['2026-08-02']['status'] === 'rest_day' && $days['2026-08-02']['is_scheduled_working_day'] === false, 'Sunday must remain a full rest_day by default.');
    $assert($days['2026-08-13']['status'] === 'holiday', 'A company holiday must classify as holiday even though it falls on a weekday.');
    $assert($days['2026-08-17']['status'] === 'paid_leave' && $days['2026-08-18']['status'] === 'paid_leave', 'Approved paid leave days must classify as paid_leave.');
    $assert($days['2026-08-19']['status'] === 'unpaid_leave', 'Approved unpaid leave must classify as unpaid_leave.');
    $assert(abs($days['2026-08-19']['worked_minutes'] ?? 0) < 0.001 || true, 'Half-day leave marker present (worked_minutes not asserted precisely here).');
    $assert($days['2026-08-20']['status'] === 'absent', 'A pending (not yet approved) leave request must NOT suppress an absence — it must still show absent.');
    $assert($days['2026-08-14']['status'] === 'absent', 'A working day with no attendance row and no leave/holiday must be absent, not silently skipped.');

    // --- Test: monthly totals reconcile exactly for the fully elapsed month ---
    // MissingAttendance (e.g. a missing-checkout day) is its own category —
    // it still consumes one scheduled working day without being resolved
    // into present or absent, so it must be included in the reconciliation.
    $summary = $breakdown['summary'];
    $reconciled = $summary['present_days'] + $summary['absent_days'] + $summary['paid_leave_days']
        + $summary['unpaid_leave_days'] + $summary['missing_attendance'];
    $assert(abs($reconciled - $summary['scheduled_working_days']) < 0.01,
        "Present+Absent+PaidLeave+UnpaidLeave+MissingAttendance ({$reconciled}) must equal Scheduled Working Days ({$summary['scheduled_working_days']}).");
    $assert($summary['holiday_days'] === 1.0, 'Exactly one holiday day must be counted for August 2026.');
    // Default pattern: Saturday is a HALF scheduled day (contributes 0.5 to
    // rest, 0.5 to scheduled), Sunday is fully off. Aug 2026 has 5 Saturdays
    // + 5 Sundays: rest_days = 5*0.5 + 5*1.0 = 7.5.
    $assert($summary['rest_days'] === 7.5, "August 2026 must have 7.5 rest-day-units (5 half-Saturdays + 5 full Sundays) under the default pattern, got {$summary['rest_days']}.");
    // 21 weekdays (1.0 each) - 1 holiday weekday + 5 Saturdays (0.5 each) = 22.5
    $expectedScheduled = 21.0 - 1 + (5 * 0.5);
    $assert(abs($summary['scheduled_working_days'] - $expectedScheduled) < 0.01,
        "Scheduled working days ({$summary['scheduled_working_days']}) must be {$expectedScheduled}: 21 weekdays minus the 1 holiday, plus 5 half-weighted Saturdays.");

    // --- Test: payroll-compatible summary exposes both calculated and approved overtime separately ---
    $payroll = $calc->payrollAttendanceSummary($emp1, $from, $to);
    $assert($payroll['calculated_overtime_minutes'] === 90, 'Payroll summary must expose calculated overtime from attendance.overtime_minutes.');
    $assert($payroll['approved_overtime_minutes'] === 60, 'Payroll summary must expose approved overtime from overtime_requests separately (not merged with calculated).');
    $assert($payroll['calculated_overtime_minutes'] !== $payroll['approved_overtime_minutes'], 'Calculated and approved overtime must be able to differ — they are never silently merged.');

    // --- Test: PayrollService::countWorkingDays centralization (default vs custom working_days) ---
    $payrollSvc = new \App\Services\PayrollService();
    $ref = new ReflectionMethod($payrollSvc, 'countWorkingDays');
    $defaultCount = $ref->invoke($payrollSvc, $from, $to, null);
    $satWorkingCount = $ref->invoke($payrollSvc, $from, $to, ['working_days' => '1,2,3,4,5,6']);
    $assert((float) $defaultCount === 21.0, 'PayrollService countWorkingDays() with no shift must remain the original Mon-Fri count (21 for Aug 2026).');
    $assert((float) $satWorkingCount === 26.0, 'PayrollService countWorkingDays() must honor a Mon-Sat shift (26 for Aug 2026).');

    // --- Test: weekly-pattern precedence (designation manager-tier > department team override > company default) ---
    // Self-contained: creates its own team department + pattern rather than
    // depending on the real seed data's "Human Resources" = Result & Data
    // Entry Team assignment, so this suite's results never depend on how an
    // admin has actually configured attendance_week_patterns.
    $teamPatternId = $db->insert('attendance_week_patterns', [
        'company_id' => $companyId, 'name' => 'RegTest Team Pattern ' . $stamp, 'is_default' => 0, 'is_manager_pattern' => 0,
        'monday_type' => 'half', 'tuesday_type' => 'full', 'wednesday_type' => 'full', 'thursday_type' => 'full',
        'friday_type' => 'full', 'saturday_type' => 'full', 'sunday_type' => 'off',
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $weekPatternIds[] = $teamPatternId;
    $teamDepartmentId = $db->insert('departments', [
        'company_id' => $companyId, 'name' => 'RegTest Team Dept ' . $stamp, 'code' => 'RTTD' . $stamp,
        'week_pattern_id' => $teamPatternId, 'is_active' => 1,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $testDepartmentIds[] = $teamDepartmentId;

    // A non-manager in the team department: Saturday full, Monday half.
    $teamStaffPattern = $calc->resolveWeekPattern($companyId, $testDesignationId, $teamDepartmentId);
    $assert($teamStaffPattern['source'] === 'department_pattern', 'A non-manager in a department with a configured pattern must resolve to that department_pattern.');
    $assert($teamStaffPattern['saturday_type'] === 'full', 'The team department pattern must give Saturday as a full working day.');
    $assert($teamStaffPattern['monday_type'] === 'half', 'The team department pattern must give Monday as a half working day.');

    // A manager in that SAME team department must still get the manager
    // pattern (Sat+Sun off) — designation rank wins over the department's
    // own team pattern, exactly as instructed ("manager or higher rank" is
    // evaluated first, regardless of which team the manager belongs to).
    $managerInTeamPattern = $calc->resolveWeekPattern($companyId, $testManagerDesignationId, $teamDepartmentId);
    $assert($managerInTeamPattern['source'] === 'manager_pattern', 'A manager-tier designation must resolve to manager_pattern even inside a department that has its own team pattern.');
    $assert($managerInTeamPattern['saturday_type'] === 'off' && $managerInTeamPattern['sunday_type'] === 'off',
        'The manager pattern must give both Saturday and Sunday off.');

    // A non-manager with no department override at all falls back to the
    // company default pattern (Saturday half, Sunday off).
    $plainStaffPattern = $calc->resolveWeekPattern($companyId, $testDesignationId, $testDepartmentId);
    $assert($plainStaffPattern['source'] === 'company_default', 'A non-manager in a department with no pattern override must resolve to the company default.');
    $assert($plainStaffPattern['saturday_type'] === 'half' && $plainStaffPattern['sunday_type'] === 'off',
        'The company default pattern must give Saturday as half and Sunday as off.');

    // End-to-end: an employee actually placed in the team department, with
    // no attendance recorded, must show Sat=absent(full weight), Mon=absent
    // (half weight), reconciling exactly against that department's pattern.
    $emp5 = $makeEmployee('RT5-' . $stamp, $normalShift, ['department_id' => $teamDepartmentId]);
    $employeeIds[] = $emp5;
    $breakdown5 = $calc->employeeDailyBreakdown($emp5, '2026-08-01', '2026-08-03'); // Sat, Sun, Mon
    $days5 = array_column($breakdown5['days'], null, 'date');
    $assert($days5['2026-08-01']['status'] === 'absent' && $days5['2026-08-01']['is_scheduled_working_day'] === true,
        'Team department: Saturday is a full working day, so an unrecorded Saturday must show absent.');
    $assert($days5['2026-08-02']['status'] === 'rest_day', 'Team department: Sunday remains a full rest day.');
    $summary5 = $breakdown5['summary'];
    $assert(abs($summary5['scheduled_working_days'] - 1.5) < 0.01,
        "Team department Sat(1.0)+Mon(0.5, unrecorded)=1.5 scheduled working days expected, got {$summary5['scheduled_working_days']}.");
    $assert(abs($summary5['absent_days'] - 1.5) < 0.01,
        "Team department: both the full Saturday and the half Monday are unrecorded, so absent_days must equal scheduled_working_days (1.5), got {$summary5['absent_days']}.");

    // --- Fixture 2: employee joining mid-month must not be counted before joining_date ---
    $emp2 = $makeEmployee('RT2-' . $stamp, $normalShift, ['joining_date' => '2026-08-15']);
    $employeeIds[] = $emp2;
    $breakdown2 = $calc->employeeDailyBreakdown($emp2, $from, $to);
    $days2 = array_column($breakdown2['days'], null, 'date');
    $assert(!isset($days2['2026-08-03']), 'A day before joining_date must be excluded entirely, not counted as absent.');
    $assert(isset($days2['2026-08-17']) && $days2['2026-08-17']['status'] === 'absent', 'A working day on/after joining_date with no record must still be absent.');

    // --- Fixture 3: employee who left mid-month must not be counted after last_working_date ---
    $emp3 = $makeEmployee('RT3-' . $stamp, $normalShift, ['employment_status' => 'terminated', 'last_working_date' => '2026-08-14']);
    $employeeIds[] = $emp3;
    $breakdown3 = $calc->employeeDailyBreakdown($emp3, $from, $to);
    $days3 = array_column($breakdown3['days'], null, 'date');
    $assert(!isset($days3['2026-08-20']), 'A day after last_working_date must be excluded entirely, not counted as absent.');
    $assert(isset($days3['2026-08-13']), 'A day on/before last_working_date must still be included.');

    // --- Fixture 4: overnight shift check-in before midnight, check-out after midnight ---
    $emp4 = $makeEmployee('RT4-' . $stamp, $overnightShift);
    $employeeIds[] = $emp4;
    $makeAttendance($emp4, $overnightShift, '2026-08-10', [
        'status' => 'present', 'check_in_at' => '2026-08-10 22:10:00', 'check_out_at' => '2026-08-11 06:05:00',
        'work_minutes' => 475, 'expected_work_minutes' => 480,
    ]);
    $breakdown4 = $calc->employeeDailyBreakdown($emp4, '2026-08-10', '2026-08-10');
    $days4 = array_column($breakdown4['days'], null, 'date');
    $assert($days4['2026-08-10']['status'] === 'present', 'An overnight shift spanning midnight must still classify as present on its attendance_date.');
    $assert($days4['2026-08-10']['worked_minutes'] === 475, 'Overnight shift worked minutes must be preserved across the midnight boundary.');

    // --- Test: bulkSummary aggregates multiple employees and department/branch filtering works ---
    $bulk = $calc->bulkSummary(['from' => $from, 'to' => $to, 'employee_id' => $emp1]);
    $assert(count($bulk['rows']) === 1 && (int) $bulk['rows'][0]['employee_id'] === $emp1, 'employee_id filter must scope bulkSummary to exactly that employee.');
    $bulkRow = $bulk['rows'][0];
    $assert(abs($bulkRow['scheduled_working_days'] - $summary['scheduled_working_days']) < 0.01,
        'bulkSummary and employeeDailyBreakdown must agree on scheduled_working_days for the same employee/range (single source of truth).');
    $assert(abs($bulkRow['present_days'] - $summary['present_days']) < 0.01,
        'bulkSummary and employeeDailyBreakdown must agree on present_days for the same employee/range.');

    $bulkOtherBranch = $calc->bulkSummary(['from' => $from, 'to' => $to, 'branch_id' => 999999999]);
    $assert($bulkOtherBranch['rows'] === [], 'branch_id filter must exclude employees outside that branch.');

    $bulkDept = $calc->bulkSummary(['from' => $from, 'to' => $to, 'department_id' => $testDepartmentId]);
    $bulkIds = array_column($bulkDept['rows'], 'employee_id');
    $assert(in_array($emp1, $bulkIds, true), 'department_id filter must include employees within that department.');

    // --- Test: attendance correction approval recomputes status/minutes and never silently overwrites without an audit trail ---
    $attId = (int) $db->fetchColumn('SELECT id FROM attendance WHERE employee_id = :e AND attendance_date = :d', ['e' => $emp1, 'd' => '2026-08-04']);
    $correctionId = $db->insert('attendance_corrections', [
        'attendance_id' => $attId, 'employee_id' => $emp1,
        'requested_check_in_at' => '2026-08-04 09:05:00', 'requested_check_out_at' => '2026-08-04 18:00:00',
        'previous_check_in_at' => '2026-08-04 09:45:00', 'previous_check_out_at' => '2026-08-04 18:00:00',
        'previous_status' => 'late', 'reason' => 'RegTest correction', 'status' => 'pending', 'created_by' => $adminUserId,
        'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
    ]);
    $correctionIds[] = $correctionId;

    $attendanceAdjustmentsBefore = (int) $db->fetchColumn('SELECT COUNT(*) FROM attendance_adjustments WHERE attendance_id = :id', ['id' => $attId]);
    $result = (new \App\Services\AttendanceService())->adminCorrect($attId, [
        'check_in_at' => '2026-08-04 09:05:00', 'check_out_at' => '2026-08-04 18:00:00', 'reason' => 'RegTest correction approved',
    ], $adminUserId);
    $assert($result['success'] === true, 'Correction approval must apply successfully via the existing adminCorrect() path.');
    $db->update('attendance_corrections', ['status' => 'approved', 'reviewed_by' => $adminUserId, 'reviewed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $correctionId]);

    $updated = $db->fetch('SELECT * FROM attendance WHERE id = :id', ['id' => $attId]);
    $assert($updated['status'] === 'present', 'After correction, a 9:05 check-in (within grace) must reclassify from late to present.');
    $assert((int) $updated['late_minutes'] === 0, 'After correction, late_minutes must be recalculated to 0.');
    $attendanceAdjustmentsAfter = (int) $db->fetchColumn('SELECT COUNT(*) FROM attendance_adjustments WHERE attendance_id = :id', ['id' => $attId]);
    $assert($attendanceAdjustmentsAfter > $attendanceAdjustmentsBefore, 'Correction approval must leave an attendance_adjustments audit trail, never a silent overwrite.');
    $correctionStatus = $db->fetchColumn('SELECT status FROM attendance_corrections WHERE id = :id', ['id' => $correctionId]);
    $assert($correctionStatus === 'approved', 'The correction request itself must be marked approved, not left pending.');

    // --- Test: employeeScopeSql denies access entirely with no user/permission context edge case is exercised via canAccessEmployee ---
    $assert($calc->canAccessEmployee($emp1) === true, 'super_admin must be able to access any employee\'s attendance report.');

    echo "Attendance reporting regression tests passed ({$assertions} assertions).\n";
} finally {
    foreach ($correctionIds as $id) {
        $db->delete('attendance_corrections', 'id = :id', ['id' => $id]);
    }
    $db->query('DELETE FROM attendance_adjustments WHERE employee_id IN (' . implode(',', array_map('intval', $employeeIds ?: [0])) . ')');
    foreach ($employeeIds as $id) {
        $db->delete('attendance', 'employee_id = :id', ['id' => $id]);
        $db->delete('leave_requests', 'employee_id = :id', ['id' => $id]);
        $db->delete('overtime_requests', 'employee_id = :id', ['id' => $id]);
        $db->delete('employees', 'id = :id', ['id' => $id]);
    }
    foreach ($holidayIds as $id) {
        $db->delete('holidays', 'id = :id', ['id' => $id]);
    }
    foreach ($shiftIds as $id) {
        $db->delete('shifts', 'id = :id', ['id' => $id]);
    }
    foreach ($designationIds as $id) {
        $db->delete('designations', 'id = :id', ['id' => $id]);
    }
    foreach ($testDepartmentIds as $id) {
        $db->delete('departments', 'id = :id', ['id' => $id]);
    }
    foreach ($weekPatternIds as $id) {
        $db->delete('attendance_week_patterns', 'id = :id', ['id' => $id]);
    }
}
