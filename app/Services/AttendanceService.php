<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Shift;

class AttendanceService
{
    private Database $db;
    private Attendance $attendance;
    private Employee $employees;
    private Shift $shifts;
    private FileUploadService $uploader;
    private AuditService $audit;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->attendance = new Attendance();
        $this->employees = new Employee();
        $this->shifts = new Shift();
        $this->uploader = new FileUploadService();
        $this->audit = new AuditService();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function checkIn(int $employeeId, array $payload, ?int $performedBy = null): array
    {
        $context = $this->loadEmployeeContext($employeeId);
        if (!$context['success']) {
            return $context;
        }

        $employee = $context['data']['employee'];
        $shift = $context['data']['shift'];
        $branch = $context['data']['branch'];
        $security = new AttendanceSecurityService();
        $ip = (new ClientIpService())->resolve($_SERVER);
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $rawToken = isset($_COOKIE[AttendanceSecurityService::COOKIE]) ? (string) $_COOKIE[AttendanceSecurityService::COOKIE] : null;
        $upload = null;
        try {
            $this->db->beginTransaction();

            // Serialize every check-in for this employee. The second concurrent
            // request waits here, then sees the attendance created by the first.
            $lockedEmployee = $this->db->fetch(
                'SELECT id, company_id, employment_status FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1 FOR UPDATE',
                ['id' => $employeeId]
            );
            if (!$lockedEmployee || !in_array($lockedEmployee['employment_status'] ?? '', ['active', 'probation'], true)) {
                $this->db->rollBack();
                return ['success' => false, 'message' => 'Attendance is not allowed for your employment status.'];
            }

            if ($this->attendance->findActive($employeeId)) {
                $security->recordCheckInResult($employee, $performedBy, null, $rawToken, $ip, $userAgent, 'CHECK_IN_ALREADY_ACTIVE', 'blocked', 'Employee already has an active check-in');
                $this->db->commit();
                return ['success' => false, 'message' => 'You are already checked in.', 'code' => 'ATTENDANCE_ALREADY_ACTIVE'];
            }

            $deviceValidation = $security->validateCheckIn($employee, $performedBy, $rawToken, $ip, $userAgent);
            if (!$deviceValidation['success']) {
                $this->db->commit();
                return $deviceValidation;
            }

            $validation = $this->validatePayload($payload, 'check_in');
            if (!$validation['success']) {
                $this->db->rollBack();
                return $validation;
            }

            $now = new \DateTime('now');
            $attendanceDate = $this->resolveAttendanceDate($shift, $now);
            $existing = $this->attendance->findToday($employeeId, $attendanceDate);
            if ($existing && !empty($existing['check_in_at'])) {
                $security->recordCheckInResult($employee, $performedBy, $deviceValidation['device_id'], $rawToken, $ip, $userAgent, 'CHECK_IN_ALREADY_ACTIVE', 'blocked', 'Employee already checked in for this shift date');
                $this->db->commit();
                return ['success' => false, 'message' => 'You are already checked in.', 'code' => 'ATTENDANCE_ALREADY_ACTIVE', 'device_cookie_token' => $deviceValidation['device_cookie_token']];
            }

            $isRemote = $this->isRemoteAttendance($employee, $payload);
            $lat = (float) $payload['latitude'];
            $lng = (float) $payload['longitude'];
            $accuracy = (float) $payload['accuracy'];
            $geo = $this->evaluateLocation($branch, $lat, $lng, $accuracy, $isRemote);
            $checkInAt = $now->format('Y-m-d H:i:s');
            $status = $this->determineCheckInStatus($shift, $checkInAt, $isRemote);
            $verificationStatus = $this->determineVerificationStatus($geo, $isRemote, false);
            $expectedWork = $this->calculateExpectedWorkMinutes($shift);
            $lateMinutes = $this->calculateLateMinutes($shift, $checkInAt, $isRemote);

            $upload = $this->optionalImageUpload($payload);
            if ($upload !== null && empty($upload['success'])) {
                $this->db->rollBack();
                return ['success' => false, 'message' => $upload['message'] ?? 'Failed to save attendance image.'];
            }

            $attendanceData = [
                'uuid' => $this->generateUuid(),
                'employee_id' => $employeeId,
                'company_id' => (int) $employee['company_id'],
                'branch_id' => $branch['id'] ?? $employee['branch_id'] ?? null,
                'shift_id' => $shift['id'] ?? null,
                'attendance_date' => $attendanceDate,
                'check_in_at' => $checkInAt,
                'original_check_in_at' => $checkInAt,
                'status' => $status,
                'verification_status' => $verificationStatus,
                'expected_work_minutes' => $expectedWork,
                'late_minutes' => $lateMinutes,
                'is_remote' => $isRemote ? 1 : 0,
                'is_manual' => 0,
                'source' => $payload['source'] ?? 'web',
                'remarks' => $this->buildGeoRemarks($geo),
                'created_by' => $performedBy,
                'updated_by' => $performedBy,
            ];

            if ($existing) {
                $attendanceId = (int) $existing['id'];
                unset($attendanceData['uuid'], $attendanceData['employee_id'], $attendanceData['company_id'], $attendanceData['attendance_date']);
                $this->attendance->update($attendanceId, $attendanceData);
            } else {
                $attendanceId = $this->attendance->create($attendanceData);
            }

            if ($upload !== null) {
                $this->saveImage($attendanceId, 'check_in', $upload, $checkInAt);
            }
            $this->saveLocation($attendanceId, 'check_in', $lat, $lng, $accuracy, $geo, $branch, $payload, $checkInAt);

            $record = $this->attendance->findDetailed($attendanceId);
            $this->logAttendanceAudit(
                $attendanceId,
                $employeeId,
                'check_in',
                null,
                $record,
                $performedBy
            );
            $this->audit->log('check_in', 'attendance', $attendanceId, null, [
                'employee_id' => $employeeId,
                'status' => $status,
                'verification_status' => $verificationStatus,
            ], $performedBy);
            $security->recordCheckInResult($employee, $performedBy, $deviceValidation['device_id'], $rawToken ?: $deviceValidation['device_cookie_token'], $ip, $userAgent, 'CHECK_IN_SUCCESS', 'success');

            $this->db->commit();

            $monitoring = $this->startMonitoringSafely($employeeId, $attendanceId, $payload);

            return [
                'success' => true,
                'message' => 'Checked in successfully.',
                'device_cookie_token' => $deviceValidation['device_cookie_token'],
                'data' => [
                    'attendance' => $record,
                    'flags' => [
                        'outside_radius' => $geo['outside_radius'],
                        'low_gps_accuracy' => $geo['low_gps_accuracy'],
                        'is_remote' => $isRemote,
                    ],
                    'monitoring' => $monitoring,
                ],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($upload !== null && isset($upload['relative_path'])) {
                $this->uploader->deleteFile($upload['relative_path']);
            }

            return [
                'success' => false,
                'message' => 'Failed to record check-in. Please try again.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function checkOut(int $employeeId, array $payload, ?int $performedBy = null): array
    {
        $validation = $this->validatePayload($payload, 'check_out');
        if (!$validation['success']) {
            return $validation;
        }

        $active = $this->attendance->findActive($employeeId);
        if (!$active) {
            return [
                'success' => false,
                'message' => 'No active check-in found. Please check in first.',
            ];
        }

        $context = $this->loadEmployeeContext($employeeId);
        if (!$context['success']) {
            return $context;
        }

        $employee = $context['data']['employee'];
        $shift = $context['data']['shift'] ?? $this->shifts->find((int) ($active['shift_id'] ?? 0)) ?? [];
        $branch = $context['data']['branch'];

        $isRemote = (bool) ($active['is_remote'] ?? false) || $this->isRemoteAttendance($employee, $payload);
        $lat = (float) $payload['latitude'];
        $lng = (float) $payload['longitude'];
        $accuracy = (float) $payload['accuracy'];
        $geo = $this->evaluateLocation($branch, $lat, $lng, $accuracy, $isRemote);

        $upload = $this->optionalImageUpload($payload);
        if ($upload !== null && empty($upload['success'])) {
            return [
                'success' => false,
                'message' => $upload['message'] ?? 'Failed to save attendance image.',
            ];
        }

        $now = new \DateTime('now');
        $checkOutAt = $now->format('Y-m-d H:i:s');
        $checkInAt = (string) $active['check_in_at'];

        $breakMinutes = $this->sumBreakMinutes((int) $active['id']);
        $workMinutes = max(0, (int) floor((strtotime($checkOutAt) - strtotime($checkInAt)) / 60) - $breakMinutes);
        $expectedWork = (int) ($active['expected_work_minutes'] ?? $this->calculateExpectedWorkMinutes($shift));
        $lateMinutes = (int) ($active['late_minutes'] ?? $this->calculateLateMinutes($shift, $checkInAt, $isRemote));
        $earlyLeave = $this->calculateEarlyLeaveMinutes($shift, $checkOutAt);
        $overtime = $this->calculateOvertimeMinutes($shift, $workMinutes, $expectedWork);

        $status = $this->determineCheckOutStatus(
            (string) ($active['status'] ?? 'present'),
            $shift,
            $workMinutes,
            $expectedWork,
            $isRemote
        );

        $verificationStatus = $this->determineVerificationStatus(
            $geo,
            $isRemote,
            (bool) ($active['is_manual'] ?? false),
            (string) ($active['verification_status'] ?? 'pending')
        );

        $remarks = trim(($active['remarks'] ?? '') . ' ' . $this->buildGeoRemarks($geo));

        try {
            $this->db->beginTransaction();

            $update = [
                'check_out_at' => $checkOutAt,
                'original_check_out_at' => $active['original_check_out_at'] ?? $checkOutAt,
                'status' => $status,
                'verification_status' => $verificationStatus,
                'work_minutes' => $workMinutes,
                'late_minutes' => $lateMinutes,
                'early_leave_minutes' => $earlyLeave,
                'overtime_minutes' => $overtime,
                'break_minutes' => $breakMinutes,
                'remarks' => trim($remarks) ?: null,
                'updated_by' => $performedBy,
            ];

            $this->attendance->update((int) $active['id'], $update);
            if ($upload !== null) {
                $this->saveImage((int) $active['id'], 'check_out', $upload, $checkOutAt);
            }
            $this->saveLocation((int) $active['id'], 'check_out', $lat, $lng, $accuracy, $geo, $branch, $payload, $checkOutAt);

            $record = $this->attendance->findDetailed((int) $active['id']);
            $this->logAttendanceAudit(
                (int) $active['id'],
                $employeeId,
                'check_out',
                $active,
                $record,
                $performedBy
            );
            $this->audit->log('check_out', 'attendance', (int) $active['id'], $active, $update, $performedBy);

            $this->db->commit();

            $monitoring = $this->stopMonitoringSafely($employeeId, (int) $active['id']);

            return [
                'success' => true,
                'message' => 'Check-out recorded successfully.',
                'data' => [
                    'attendance' => $record,
                    'summary' => [
                        'work_minutes' => $workMinutes,
                        'late_minutes' => $lateMinutes,
                        'early_leave_minutes' => $earlyLeave,
                        'overtime_minutes' => $overtime,
                        'break_minutes' => $breakMinutes,
                    ],
                    'flags' => [
                        'outside_radius' => $geo['outside_radius'],
                        'low_gps_accuracy' => $geo['low_gps_accuracy'],
                    ],
                    'monitoring' => $monitoring,
                ],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            if ($upload !== null && isset($upload['relative_path'])) {
                $this->uploader->deleteFile($upload['relative_path']);
            }

            return [
                'success' => false,
                'message' => 'Failed to record check-out. Please try again.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function todayStatus(int $employeeId): array
    {
        $context = $this->loadEmployeeContext($employeeId);
        if (!$context['success']) {
            return $context;
        }

        $shift = $context['data']['shift'];
        $now = new \DateTime('now');
        $attendanceDate = $this->resolveAttendanceDate($shift, $now);

        $today = $this->attendance->findToday($employeeId, $attendanceDate);
        $active = $this->attendance->findActive($employeeId);

        $record = $active ?: $today;
        $images = [];
        $locations = [];

        if ($record) {
            $images = $this->attendance->images((int) $record['id']);
            $locations = $this->attendance->locations((int) $record['id']);
        }

        $canCheckIn = !$active && (!$today || empty($today['check_in_at']));
        $canCheckOut = (bool) $active;

        return [
            'success' => true,
            'message' => 'Today status loaded.',
            'data' => [
                'attendance_date' => $attendanceDate,
                'shift' => $shift,
                'attendance' => $record,
                'images' => $images,
                'locations' => $locations,
                'can_check_in' => $canCheckIn,
                'can_check_out' => $canCheckOut,
                'is_checked_in' => (bool) $active,
                'remote_allowed' => (bool) ($context['data']['employee']['remote_attendance_allowed'] ?? false),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function requestCorrection(int $employeeId, array $payload, ?int $createdBy = null): array
    {
        $attendanceId = (int) ($payload['attendance_id'] ?? 0);
        $reason = trim((string) ($payload['reason'] ?? ''));

        if ($attendanceId <= 0) {
            return ['success' => false, 'message' => 'Attendance record is required.'];
        }
        if ($reason === '') {
            return ['success' => false, 'message' => 'Reason is required for correction request.'];
        }

        $record = $this->attendance->find($attendanceId);
        if (!$record || (int) $record['employee_id'] !== $employeeId) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        $pending = $this->db->fetch(
            'SELECT id FROM attendance_corrections
             WHERE attendance_id = :aid AND employee_id = :eid AND status = :status AND deleted_at IS NULL
             LIMIT 1',
            ['aid' => $attendanceId, 'eid' => $employeeId, 'status' => 'pending']
        );

        if ($pending) {
            return ['success' => false, 'message' => 'A pending correction request already exists for this record.'];
        }

        $requestedCheckIn = $this->normalizeDateTime($payload['requested_check_in_at'] ?? null);
        $requestedCheckOut = $this->normalizeDateTime($payload['requested_check_out_at'] ?? null);

        if ($requestedCheckIn === null && $requestedCheckOut === null) {
            return ['success' => false, 'message' => 'Provide at least a requested check-in or check-out time.'];
        }

        $correctionId = $this->db->insert('attendance_corrections', [
            'attendance_id' => $attendanceId,
            'employee_id' => $employeeId,
            'requested_check_in_at' => $requestedCheckIn,
            'requested_check_out_at' => $requestedCheckOut,
            'previous_check_in_at' => $record['check_in_at'],
            'previous_check_out_at' => $record['check_out_at'],
            'previous_status' => $record['status'],
            'reason' => $reason,
            'status' => 'pending',
            'created_by' => $createdBy,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $this->logAttendanceAudit(
            $attendanceId,
            $employeeId,
            'correction_requested',
            null,
            ['correction_id' => $correctionId, 'reason' => $reason],
            $createdBy
        );
        $this->audit->log('correction_requested', 'attendance', $attendanceId, null, [
            'correction_id' => $correctionId,
        ], $createdBy);

        return [
            'success' => true,
            'message' => 'Correction request submitted successfully.',
            'data' => ['correction_id' => $correctionId],
        ];
    }

    /**
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    private function loadEmployeeContext(int $employeeId): array
    {
        $employee = $this->employees->findDetailed($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee profile not found.'];
        }

        if (!in_array($employee['employment_status'] ?? '', ['active', 'probation'], true)) {
            return ['success' => false, 'message' => 'Attendance is not allowed for your employment status.'];
        }

        $shift = $this->shifts->findForEmployee($employeeId);
        $branchId = (int) ($employee['office_branch_id'] ?? $employee['branch_id'] ?? 0);
        $branch = null;

        if ($branchId > 0) {
            $branch = $this->db->fetch(
                'SELECT * FROM branches WHERE id = :id AND deleted_at IS NULL LIMIT 1',
                ['id' => $branchId]
            );
        }

        return [
            'success' => true,
            'message' => 'Context loaded.',
            'data' => [
                'employee' => $employee,
                'shift' => $shift ?? [],
                'branch' => $branch,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, errors?: array<string, string>}
     */
    private function validatePayload(array $payload, string $type): array
    {
        $errors = [];

        if (!isset($payload['latitude']) || !is_numeric($payload['latitude'])) {
            $errors['latitude'] = 'Latitude is required.';
        }
        if (!isset($payload['longitude']) || !is_numeric($payload['longitude'])) {
            $errors['longitude'] = 'Longitude is required.';
        }
        if (!isset($payload['accuracy']) || !is_numeric($payload['accuracy'])) {
            $errors['accuracy'] = 'GPS accuracy is required.';
        }

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => ucfirst(str_replace('_', ' ', $type)) . ' validation failed.',
                'errors' => $errors,
            ];
        }

        return ['success' => true, 'message' => 'Valid.'];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null Null when no image was provided; otherwise upload result.
     */
    private function optionalImageUpload(array $payload): ?array
    {
        $image = trim((string) ($payload['image'] ?? ''));
        if ($image === '') {
            return null;
        }

        return $this->uploader->saveBase64Image($image);
    }

    /**
     * @param array<string, mixed> $employee
     * @param array<string, mixed> $payload
     */
    private function isRemoteAttendance(array $employee, array $payload): bool
    {
        if (!empty($payload['is_remote']) && (int) $employee['remote_attendance_allowed'] === 1) {
            return true;
        }

        return ($employee['work_location_type'] ?? 'office') === 'remote'
            && (int) ($employee['remote_attendance_allowed'] ?? 0) === 1;
    }

    /**
     * @param array<string, mixed>|null $branch
     * @return array{distance: float|null, is_within_radius: bool|null, outside_radius: bool, low_gps_accuracy: bool}
     */
    private function evaluateLocation(
        ?array $branch,
        float $lat,
        float $lng,
        float $accuracy,
        bool $isRemote
    ): array {
        $threshold = (int) config('app.attendance.gps_accuracy_threshold', 50);
        $lowGps = $accuracy <= 0 || $accuracy > $threshold;

        if ($isRemote || !$branch || $branch['latitude'] === null || $branch['longitude'] === null) {
            return [
                'distance' => null,
                'is_within_radius' => $isRemote ? null : null,
                'outside_radius' => false,
                'low_gps_accuracy' => $lowGps,
            ];
        }

        $distance = haversine_distance(
            (float) $branch['latitude'],
            (float) $branch['longitude'],
            $lat,
            $lng
        );

        $radius = (float) ($branch['attendance_radius'] ?? config('app.attendance.default_radius', 100));
        $within = $distance <= $radius;

        return [
            'distance' => $distance,
            'is_within_radius' => $within,
            'outside_radius' => !$within,
            'low_gps_accuracy' => $lowGps,
        ];
    }

    private function determineCheckInStatus(array $shift, string $checkInAt, bool $isRemote): string
    {
        if ($isRemote) {
            return 'remote';
        }

        if (empty($shift)) {
            return 'present';
        }

        $lateMinutes = $this->calculateLateMinutes($shift, $checkInAt, false);
        if ($lateMinutes > 0) {
            $halfDayAfter = (int) ($shift['half_day_after_minutes'] ?? 0);
            if ($halfDayAfter > 0 && $lateMinutes >= $halfDayAfter) {
                return 'half_day';
            }
            return 'late';
        }

        return 'present';
    }

    private function determineCheckOutStatus(
        string $currentStatus,
        array $shift,
        int $workMinutes,
        int $expectedWork,
        bool $isRemote
    ): string {
        if ($isRemote) {
            return 'remote';
        }

        if ($currentStatus === 'half_day') {
            return 'half_day';
        }

        if ($expectedWork > 0 && $workMinutes < (int) floor($expectedWork / 2)) {
            return 'half_day';
        }

        if ($currentStatus === 'late') {
            return 'late';
        }

        return 'present';
    }

    private function determineVerificationStatus(
        array $geo,
        bool $isRemote,
        bool $isManual,
        string $existing = 'pending'
    ): string {
        if ($isManual) {
            return 'pending';
        }

        if ($geo['outside_radius'] || $geo['low_gps_accuracy']) {
            return 'flagged';
        }

        if ($isRemote) {
            return 'auto_verified';
        }

        return $existing === 'flagged' ? 'flagged' : 'auto_verified';
    }

    private function calculateLateMinutes(array $shift, string $checkInAt, bool $isRemote): int
    {
        if ($isRemote || empty($shift['start_time'])) {
            return 0;
        }

        $date = date('Y-m-d', strtotime($checkInAt));
        $shiftStart = strtotime($date . ' ' . $shift['start_time']);
        $grace = (int) ($shift['grace_minutes'] ?? 0);
        $allowed = $shiftStart + ($grace * 60);
        $actual = strtotime($checkInAt);

        if ($actual <= $allowed) {
            return 0;
        }

        return (int) floor(($actual - $allowed) / 60);
    }

    private function calculateEarlyLeaveMinutes(array $shift, string $checkOutAt): int
    {
        if (empty($shift['end_time'])) {
            return 0;
        }

        $date = date('Y-m-d', strtotime($checkOutAt));
        $shiftEnd = strtotime($date . ' ' . $shift['end_time']);

        if (!empty($shift['is_overnight'])) {
            $start = strtotime($date . ' ' . ($shift['start_time'] ?? '00:00:00'));
            if ($shiftEnd <= $start) {
                $shiftEnd += 86400;
            }
        }

        $grace = (int) ($shift['early_leave_grace_minutes'] ?? 0);
        $allowed = $shiftEnd - ($grace * 60);
        $actual = strtotime($checkOutAt);

        if ($actual >= $allowed) {
            return 0;
        }

        return (int) floor(($allowed - $actual) / 60);
    }

    private function calculateOvertimeMinutes(array $shift, int $workMinutes, int $expectedWork): int
    {
        $threshold = (int) ($shift['overtime_after_minutes'] ?? 0);
        $baseline = max($expectedWork, 0) + $threshold;

        if ($workMinutes <= $baseline) {
            return 0;
        }

        return $workMinutes - $baseline;
    }

    private function calculateExpectedWorkMinutes(array $shift): int
    {
        if (!empty($shift['expected_work_minutes'])) {
            return (int) $shift['expected_work_minutes'];
        }

        if (empty($shift['start_time']) || empty($shift['end_time'])) {
            return 480;
        }

        $start = strtotime('1970-01-01 ' . $shift['start_time']);
        $end = strtotime('1970-01-01 ' . $shift['end_time']);

        if (!empty($shift['is_overnight']) && $end <= $start) {
            $end += 86400;
        }

        $minutes = (int) max(0, ($end - $start) / 60 - (int) ($shift['break_minutes'] ?? 0));
        return $minutes > 0 ? $minutes : 480;
    }

    private function resolveAttendanceDate(array $shift, \DateTime $now): string
    {
        if (empty($shift['is_overnight'])) {
            return $now->format('Y-m-d');
        }

        $currentTime = $now->format('H:i:s');
        $endTime = $shift['end_time'] ?? '06:00:00';

        if ($currentTime <= $endTime) {
            $clone = clone $now;
            return $clone->modify('-1 day')->format('Y-m-d');
        }

        return $now->format('Y-m-d');
    }

    private function sumBreakMinutes(int $attendanceId): int
    {
        $total = $this->db->fetchColumn(
            'SELECT COALESCE(SUM(duration_minutes), 0) FROM attendance_breaks
             WHERE attendance_id = :id AND break_end_at IS NOT NULL',
            ['id' => $attendanceId]
        );

        return (int) $total;
    }

    /**
     * @param array<string, mixed> $geo
     */
    private function buildGeoRemarks(array $geo): string
    {
        $parts = [];
        if ($geo['outside_radius']) {
            $dist = $geo['distance'] !== null ? round($geo['distance'], 1) . 'm' : 'unknown';
            $parts[] = "outside_radius ({$dist})";
        }
        if ($geo['low_gps_accuracy']) {
            $parts[] = 'low_gps_accuracy';
        }

        return implode('; ', $parts);
    }

    /**
     * @param array<string, mixed> $upload
     */
    private function saveImage(int $attendanceId, string $type, array $upload, string $capturedAt): void
    {
        $this->db->insert('attendance_images', [
            'attendance_id' => $attendanceId,
            'type' => $type,
            'filename' => $upload['filename'],
            'original_filename' => $upload['original_filename'] ?? null,
            'path' => $upload['relative_path'],
            'mime_type' => $upload['mime_type'],
            'file_size' => $upload['file_size'],
            'captured_at' => $capturedAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param array<string, mixed>|null $branch
     * @param array<string, mixed> $geo
     * @param array<string, mixed> $payload
     */
    private function saveLocation(
        int $attendanceId,
        string $type,
        float $lat,
        float $lng,
        float $accuracy,
        array $geo,
        ?array $branch,
        array $payload,
        string $capturedAt
    ): void {
        $this->db->insert('attendance_locations', [
            'attendance_id' => $attendanceId,
            'type' => $type,
            'latitude' => $lat,
            'longitude' => $lng,
            'accuracy' => $accuracy,
            'distance_meters' => $geo['distance'],
            'ip_address' => (new ClientIpService())->resolve($_SERVER),
            'device_info' => substr((string) ($payload['device_info'] ?? ''), 0, 500) ?: null,
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'is_within_radius' => $geo['is_within_radius'] === null ? null : ($geo['is_within_radius'] ? 1 : 0),
            'branch_id' => $branch['id'] ?? null,
            'captured_at' => $capturedAt,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * @param mixed $old
     * @param mixed $new
     */
    private function logAttendanceAudit(
        int $attendanceId,
        int $employeeId,
        string $action,
        mixed $old,
        mixed $new,
        ?int $performedBy
    ): void {
        try {
            $this->db->insert('attendance_audit_logs', [
                'attendance_id' => $attendanceId,
                'employee_id' => $employeeId,
                'action' => $action,
                'old_value' => $old !== null ? json_encode($old, JSON_UNESCAPED_UNICODE) : null,
                'new_value' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => (new ClientIpService())->resolve($_SERVER),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'performed_by' => $performedBy,
                'performed_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Do not break attendance flow
        }
    }

    private function generateUuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    private function normalizeDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $ts = strtotime((string) $value);
        return $ts ? date('Y-m-d H:i:s', $ts) : null;
    }

    /**
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function approve(int $attendanceId, ?int $approvedBy, ?string $notes = null): array
    {
        $record = $this->attendance->find($attendanceId);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        $this->attendance->update($attendanceId, [
            'verification_status' => 'verified',
            'approved_by' => $approvedBy,
            'approved_at' => date('Y-m-d H:i:s'),
            'admin_notes' => $notes ?? $record['admin_notes'],
            'updated_by' => $approvedBy,
        ]);

        $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'approved', $record, [
            'verification_status' => 'verified',
            'admin_notes' => $notes,
        ], $approvedBy);
        $this->audit->log('approve', 'attendance', $attendanceId, $record, ['verification_status' => 'verified'], $approvedBy);

        return [
            'success' => true,
            'message' => 'Attendance approved successfully.',
            'data' => ['attendance' => $this->attendance->findDetailed($attendanceId)],
        ];
    }

    /**
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function reject(int $attendanceId, ?int $rejectedBy, ?string $notes = null): array
    {
        $record = $this->attendance->find($attendanceId);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        $this->attendance->update($attendanceId, [
            'verification_status' => 'rejected',
            'admin_notes' => $notes ?? $record['admin_notes'],
            'updated_by' => $rejectedBy,
        ]);

        $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'rejected', $record, [
            'verification_status' => 'rejected',
            'admin_notes' => $notes,
        ], $rejectedBy);
        $this->audit->log('reject', 'attendance', $attendanceId, $record, ['verification_status' => 'rejected'], $rejectedBy);

        return [
            'success' => true,
            'message' => 'Attendance rejected.',
            'data' => ['attendance' => $this->attendance->findDetailed($attendanceId)],
        ];
    }

    /**
     * Full admin update of an attendance record with recalculation + audit trail.
     *
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function adminUpdate(int $attendanceId, array $payload, ?int $adjustedBy): array
    {
        $record = $this->attendance->find($attendanceId);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        if ((int) ($record['is_locked'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'This attendance record is locked.'];
        }

        $reason = trim((string) ($payload['reason'] ?? ''));
        if ($reason === '') {
            return ['success' => false, 'message' => 'A change reason is required.', 'errors' => ['reason' => ['Required']]];
        }

        $checkIn = $this->normalizeDateTime($payload['check_in_at'] ?? $record['check_in_at']);
        $checkOut = $this->normalizeDateTime($payload['check_out_at'] ?? $record['check_out_at']);

        if ($checkIn && $checkOut && strtotime($checkOut) < strtotime($checkIn)) {
            return ['success' => false, 'message' => 'Check-out cannot be earlier than check-in.'];
        }

        $allowedStatuses = ['present', 'absent', 'late', 'remote', 'half_day', 'manual', 'on_leave', 'holiday', 'weekend', 'missing_checkout'];
        $status = (string) ($payload['status'] ?? $record['status']);
        if (!in_array($status, $allowedStatuses, true)) {
            return ['success' => false, 'message' => 'Invalid attendance status.'];
        }

        $allowedVerification = ['pending', 'verified', 'rejected', 'pending_review', 'outside_radius', 'low_gps_accuracy'];
        $verification = (string) ($payload['verification_status'] ?? $record['verification_status']);
        if (!in_array($verification, $allowedVerification, true)) {
            $verification = (string) $record['verification_status'];
        }

        $shiftId = isset($payload['shift_id']) && $payload['shift_id'] !== ''
            ? (int) $payload['shift_id']
            : (int) ($record['shift_id'] ?? 0);
        $branchId = isset($payload['branch_id']) && $payload['branch_id'] !== ''
            ? (int) $payload['branch_id']
            : (int) ($record['branch_id'] ?? 0);
        $attendanceDate = (string) ($payload['attendance_date'] ?? $record['attendance_date']);
        if (strtotime($attendanceDate) === false) {
            return ['success' => false, 'message' => 'Invalid attendance date.'];
        }

        // Prevent duplicate date for same employee when date changes
        if ($attendanceDate !== (string) $record['attendance_date']) {
            $dup = $this->attendance->findToday((int) $record['employee_id'], $attendanceDate);
            if ($dup && (int) $dup['id'] !== $attendanceId) {
                return ['success' => false, 'message' => 'Another attendance record already exists for this date.'];
            }
        }

        $shift = $shiftId > 0 ? ($this->shifts->find($shiftId) ?? []) : [];
        $isRemote = array_key_exists('is_remote', $payload)
            ? !empty($payload['is_remote'])
            : (bool) ($record['is_remote'] ?? false);

        $breakMinutes = isset($payload['break_minutes'])
            ? max(0, (int) $payload['break_minutes'])
            : (int) ($record['break_minutes'] ?? 0);

        $expectedWork = (int) ($record['expected_work_minutes'] ?? $this->calculateExpectedWorkMinutes($shift));
        $workMinutes = isset($payload['work_minutes']) && $payload['work_minutes'] !== ''
            ? max(0, (int) $payload['work_minutes'])
            : 0;
        $lateMinutes = isset($payload['late_minutes']) && $payload['late_minutes'] !== ''
            ? max(0, (int) $payload['late_minutes'])
            : null;
        $earlyLeave = isset($payload['early_leave_minutes']) && $payload['early_leave_minutes'] !== ''
            ? max(0, (int) $payload['early_leave_minutes'])
            : null;
        $overtime = isset($payload['overtime_minutes']) && $payload['overtime_minutes'] !== ''
            ? max(0, (int) $payload['overtime_minutes'])
            : null;

        if ($checkIn && $checkOut && (!isset($payload['work_minutes']) || $payload['work_minutes'] === '')) {
            $workMinutes = max(0, (int) floor((strtotime($checkOut) - strtotime($checkIn)) / 60) - $breakMinutes);
        }
        if ($lateMinutes === null) {
            $lateMinutes = $checkIn ? $this->calculateLateMinutes($shift, $checkIn, $isRemote) : 0;
        }
        if ($earlyLeave === null) {
            $earlyLeave = ($checkOut && $shift) ? $this->calculateEarlyLeaveMinutes($shift, $checkOut) : 0;
        }
        if ($overtime === null) {
            $overtime = $this->calculateOvertimeMinutes($shift, $workMinutes, $expectedWork);
        }

        $update = [
            'attendance_date' => $attendanceDate,
            'shift_id' => $shiftId ?: null,
            'branch_id' => $branchId ?: null,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $status,
            'verification_status' => $verification,
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeave,
            'break_minutes' => $breakMinutes,
            'overtime_minutes' => $overtime,
            'expected_work_minutes' => $expectedWork,
            'is_remote' => $isRemote ? 1 : 0,
            'remarks' => trim((string) ($payload['remarks'] ?? $record['remarks'] ?? '')) ?: null,
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? $record['admin_notes'] ?? '')) ?: null,
            'updated_by' => $adjustedBy,
        ];

        try {
            $this->db->beginTransaction();
            $this->attendance->update($attendanceId, $update);

            $this->db->insert('attendance_adjustments', [
                'attendance_id' => $attendanceId,
                'employee_id' => (int) $record['employee_id'],
                'adjustment_type' => 'other',
                'field_name' => 'admin_update',
                'old_value' => json_encode([
                    'attendance_date' => $record['attendance_date'],
                    'check_in_at' => $record['check_in_at'],
                    'check_out_at' => $record['check_out_at'],
                    'status' => $record['status'],
                    'verification_status' => $record['verification_status'],
                    'work_minutes' => $record['work_minutes'],
                    'late_minutes' => $record['late_minutes'],
                    'overtime_minutes' => $record['overtime_minutes'],
                ], JSON_UNESCAPED_UNICODE),
                'new_value' => json_encode([
                    'attendance_date' => $update['attendance_date'],
                    'check_in_at' => $update['check_in_at'],
                    'check_out_at' => $update['check_out_at'],
                    'status' => $update['status'],
                    'verification_status' => $update['verification_status'],
                    'work_minutes' => $update['work_minutes'],
                    'late_minutes' => $update['late_minutes'],
                    'overtime_minutes' => $update['overtime_minutes'],
                    'reason' => $reason,
                ], JSON_UNESCAPED_UNICODE),
                'reason' => $reason,
                'adjusted_by' => $adjustedBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'admin_update', $record, $update, $adjustedBy);
            $this->audit->log('update', 'attendance', $attendanceId, $record, array_merge($update, ['reason' => $reason]), $adjustedBy);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Attendance updated successfully.',
                'data' => ['attendance' => $this->attendance->findDetailed($attendanceId)],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to update attendance.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Soft-archive an attendance record with required reason + audit.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function softDelete(int $attendanceId, string $reason, ?int $deletedBy): array
    {
        $record = $this->attendance->find($attendanceId);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        if (!empty($record['deleted_at'])) {
            return ['success' => false, 'message' => 'Attendance is already archived.'];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Archive reason is required.', 'errors' => ['reason' => ['Archive reason is required.']]];
        }

        if ((int) ($record['is_locked'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'Locked attendance cannot be archived.'];
        }

        $now = date('Y-m-d H:i:s');

        try {
            $this->db->beginTransaction();
            $this->db->update('attendance', [
                'deleted_at' => $now,
                'deleted_by' => $deletedBy,
                'deletion_reason' => $reason,
                'updated_by' => $deletedBy,
                'updated_at' => $now,
                'admin_notes' => trim(($record['admin_notes'] ?? '') . "\n[ARCHIVED] " . $reason),
            ], 'id = :id', ['id' => $attendanceId]);

            $this->db->insert('attendance_adjustments', [
                'attendance_id' => $attendanceId,
                'employee_id' => (int) $record['employee_id'],
                'adjustment_type' => 'other',
                'field_name' => 'deleted_at',
                'old_value' => null,
                'new_value' => substr($now, 0, 255),
                'reason' => $reason,
                'adjusted_by' => $deletedBy,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'archive', $record, [
                'deleted_at' => $now,
                'deleted_by' => $deletedBy,
                'deletion_reason' => $reason,
            ], $deletedBy);
            $this->audit->log('delete', 'attendance', $attendanceId, $record, ['reason' => $reason], $deletedBy);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Record archived successfully.',
                'data' => ['record_id' => $attendanceId],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to archive attendance: ' . $e->getMessage()];
        }
    }

    /**
     * Restore an archived attendance record.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function restore(int $attendanceId, ?int $restoredBy, ?string $reason = null): array
    {
        $record = $this->db->fetch('SELECT * FROM attendance WHERE id = :id LIMIT 1', ['id' => $attendanceId]);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }
        if (empty($record['deleted_at'])) {
            return ['success' => false, 'message' => 'Attendance is not archived.'];
        }

        $employee = $this->db->fetch(
            'SELECT id FROM employees WHERE id = :id AND deleted_at IS NULL LIMIT 1',
            ['id' => (int) $record['employee_id']]
        );
        if (!$employee) {
            return ['success' => false, 'message' => 'Attendance cannot be restored because the employee no longer exists.'];
        }

        $duplicate = $this->db->fetch(
            'SELECT id FROM attendance
             WHERE employee_id = :eid AND attendance_date = :d AND deleted_at IS NULL AND id <> :id
             LIMIT 1',
            [
                'eid' => (int) $record['employee_id'],
                'd' => $record['attendance_date'],
                'id' => $attendanceId,
            ]
        );
        if ($duplicate) {
            return [
                'success' => false,
                'message' => 'Attendance cannot be restored because an active attendance record already exists for this employee and date.',
            ];
        }

        $now = date('Y-m-d H:i:s');

        try {
            $this->db->beginTransaction();
            $this->db->update('attendance', [
                'deleted_at' => null,
                'deleted_by' => null,
                'deletion_reason' => null,
                'updated_by' => $restoredBy,
                'updated_at' => $now,
            ], 'id = :id', ['id' => $attendanceId]);

            $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'restore', $record, [
                'restored_at' => $now,
                'reason' => $reason,
            ], $restoredBy);
            $this->audit->log('restore', 'attendance', $attendanceId, $record, ['reason' => $reason], $restoredBy);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Attendance restored successfully.',
                'data' => ['record_id' => $attendanceId],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to restore attendance: ' . $e->getMessage()];
        }
    }

    /**
     * Permanently delete an already-archived attendance record.
     *
     * @return array{success: bool, message: string, data?: array<string, mixed>}
     */
    public function permanentlyDelete(int $attendanceId, string $reason, ?int $deletedBy): array
    {
        $record = $this->db->fetch('SELECT * FROM attendance WHERE id = :id LIMIT 1', ['id' => $attendanceId]);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }
        if (empty($record['deleted_at'])) {
            return ['success' => false, 'message' => 'Only archived attendance can be permanently deleted.'];
        }

        $reason = trim($reason);
        if ($reason === '') {
            return ['success' => false, 'message' => 'Permanent deletion reason is required.', 'errors' => ['reason' => ['Permanent deletion reason is required.']]];
        }

        if ((int) ($record['is_locked'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'Locked attendance cannot be permanently deleted.'];
        }

        $openCorrection = $this->db->fetch(
            "SELECT id FROM attendance_corrections
             WHERE attendance_id = :id AND status = 'pending' AND deleted_at IS NULL LIMIT 1",
            ['id' => $attendanceId]
        );
        if ($openCorrection) {
            return ['success' => false, 'message' => 'Cannot permanently delete attendance with unresolved correction requests.'];
        }

        try {
            $this->db->beginTransaction();
            $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'permanently_delete', $record, [
                'reason' => $reason,
            ], $deletedBy);
            $this->audit->log('delete', 'attendance', $attendanceId, $record, [
                'permanent' => true,
                'reason' => $reason,
            ], $deletedBy);

            $this->db->delete('attendance_adjustments', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->delete('attendance_locations', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->delete('attendance_images', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->delete('attendance_corrections', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->delete('attendance_breaks', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->delete('attendance_audit_logs', 'attendance_id = :id', ['id' => $attendanceId]);
            $this->db->query(
                'UPDATE overtime_requests SET attendance_id = NULL WHERE attendance_id = :id',
                ['id' => $attendanceId]
            );
            $this->db->delete('attendance', 'id = :id', ['id' => $attendanceId]);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Attendance permanently deleted.',
                'data' => ['record_id' => $attendanceId],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Failed to permanently delete attendance: ' . $e->getMessage()];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function adminCorrect(int $attendanceId, array $payload, ?int $adjustedBy): array
    {
        // Prefer the fuller update path when rich fields are submitted
        if (
            isset($payload['attendance_date'])
            || isset($payload['verification_status'])
            || isset($payload['shift_id'])
            || isset($payload['branch_id'])
            || isset($payload['work_minutes'])
            || isset($payload['late_minutes'])
            || isset($payload['overtime_minutes'])
            || isset($payload['early_leave_minutes'])
            || isset($payload['break_minutes'])
        ) {
            return $this->adminUpdate($attendanceId, $payload, $adjustedBy);
        }

        $record = $this->attendance->find($attendanceId);
        if (!$record) {
            return ['success' => false, 'message' => 'Attendance record not found.'];
        }

        if ((int) ($record['is_locked'] ?? 0) === 1) {
            return ['success' => false, 'message' => 'This attendance record is locked.'];
        }

        $checkIn = $this->normalizeDateTime($payload['check_in_at'] ?? $record['check_in_at']);
        $checkOut = $this->normalizeDateTime($payload['check_out_at'] ?? $record['check_out_at']);
        $reason = trim((string) ($payload['reason'] ?? 'Admin correction'));

        if ($checkIn === null) {
            return ['success' => false, 'message' => 'Check-in time is required.'];
        }

        if ($checkOut !== null && strtotime($checkOut) < strtotime($checkIn)) {
            return ['success' => false, 'message' => 'Check-out cannot be earlier than check-in.'];
        }

        $shift = $this->shifts->find((int) ($record['shift_id'] ?? 0)) ?? [];
        $isRemote = (bool) ($record['is_remote'] ?? false);

        $breakMinutes = (int) ($record['break_minutes'] ?? 0);
        $workMinutes = 0;
        $earlyLeave = 0;
        $overtime = 0;

        if ($checkOut !== null) {
            $workMinutes = max(0, (int) floor((strtotime($checkOut) - strtotime($checkIn)) / 60) - $breakMinutes);
            $expectedWork = (int) ($record['expected_work_minutes'] ?? $this->calculateExpectedWorkMinutes($shift));
            $earlyLeave = $this->calculateEarlyLeaveMinutes($shift, $checkOut);
            $overtime = $this->calculateOvertimeMinutes($shift, $workMinutes, $expectedWork);
        }

        $lateMinutes = $this->calculateLateMinutes($shift, $checkIn, $isRemote);
        $status = $payload['status'] ?? $this->determineCheckInStatus($shift, $checkIn, $isRemote);

        if ($checkOut !== null) {
            $status = $this->determineCheckOutStatus(
                $status,
                $shift,
                $workMinutes,
                (int) ($record['expected_work_minutes'] ?? $this->calculateExpectedWorkMinutes($shift)),
                $isRemote
            );
        }

        $update = [
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'status' => $status,
            'verification_status' => 'verified',
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeave,
            'overtime_minutes' => $overtime,
            'admin_notes' => trim((string) ($payload['admin_notes'] ?? $record['admin_notes'] ?? '')) ?: null,
            'updated_by' => $adjustedBy,
        ];

        try {
            $this->db->beginTransaction();
            $this->attendance->update($attendanceId, $update);

            $this->db->insert('attendance_adjustments', [
                'attendance_id' => $attendanceId,
                'employee_id' => (int) $record['employee_id'],
                'adjustment_type' => 'other',
                'field_name' => 'check_in_at,check_out_at',
                'old_value' => json_encode([
                    'check_in_at' => $record['check_in_at'],
                    'check_out_at' => $record['check_out_at'],
                    'status' => $record['status'],
                ]),
                'new_value' => json_encode([
                    'check_in_at' => $checkIn,
                    'check_out_at' => $checkOut,
                    'status' => $status,
                ]),
                'reason' => $reason,
                'adjusted_by' => $adjustedBy,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->logAttendanceAudit($attendanceId, (int) $record['employee_id'], 'admin_correct', $record, $update, $adjustedBy);
            $this->audit->log('update', 'attendance', $attendanceId, $record, $update, $adjustedBy);
            $this->db->commit();

            return [
                'success' => true,
                'message' => 'Attendance corrected successfully.',
                'data' => ['attendance' => $this->attendance->findDetailed($attendanceId)],
            ];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            return [
                'success' => false,
                'message' => 'Failed to correct attendance.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array{success: bool, message: string, data?: array<string, mixed>, errors?: array<string, mixed>}
     */
    public function manualEntry(array $payload, ?int $createdBy): array
    {
        $employeeId = (int) ($payload['employee_id'] ?? 0);
        $attendanceDate = (string) ($payload['attendance_date'] ?? '');

        if ($employeeId <= 0 || $attendanceDate === '') {
            return ['success' => false, 'message' => 'Employee and attendance date are required.'];
        }

        $existing = $this->attendance->findToday($employeeId, $attendanceDate);
        if ($existing) {
            return ['success' => false, 'message' => 'Attendance already exists for this date. Use correct instead.'];
        }

        $employee = $this->employees->findDetailed($employeeId);
        if (!$employee) {
            return ['success' => false, 'message' => 'Employee not found.'];
        }

        $shift = $this->shifts->findForEmployee($employeeId, $attendanceDate);
        $checkIn = $this->normalizeDateTime($payload['check_in_at'] ?? null);
        $checkOut = $this->normalizeDateTime($payload['check_out_at'] ?? null);
        $isRemote = !empty($payload['is_remote']);
        $status = $payload['status'] ?? ($isRemote ? 'remote' : 'manual');

        $expectedWork = $this->calculateExpectedWorkMinutes($shift ?? []);
        $lateMinutes = $checkIn ? $this->calculateLateMinutes($shift ?? [], $checkIn, $isRemote) : 0;
        $workMinutes = 0;
        $earlyLeave = 0;
        $overtime = 0;

        if ($checkIn && $checkOut) {
            $workMinutes = max(0, (int) floor((strtotime($checkOut) - strtotime($checkIn)) / 60));
            $earlyLeave = $this->calculateEarlyLeaveMinutes($shift ?? [], $checkOut);
            $overtime = $this->calculateOvertimeMinutes($shift ?? [], $workMinutes, $expectedWork);
        }

        $id = $this->attendance->create([
            'uuid' => $this->generateUuid(),
            'employee_id' => $employeeId,
            'company_id' => (int) $employee['company_id'],
            'branch_id' => $employee['branch_id'] ?? null,
            'shift_id' => $shift['id'] ?? $employee['shift_id'] ?? null,
            'attendance_date' => $attendanceDate,
            'check_in_at' => $checkIn,
            'check_out_at' => $checkOut,
            'original_check_in_at' => $checkIn,
            'original_check_out_at' => $checkOut,
            'status' => $status,
            'verification_status' => 'verified',
            'work_minutes' => $workMinutes,
            'late_minutes' => $lateMinutes,
            'early_leave_minutes' => $earlyLeave,
            'overtime_minutes' => $overtime,
            'expected_work_minutes' => $expectedWork,
            'is_remote' => $isRemote ? 1 : 0,
            'is_manual' => 1,
            'source' => 'manual',
            'remarks' => $payload['remarks'] ?? null,
            'admin_notes' => $payload['admin_notes'] ?? null,
            'created_by' => $createdBy,
            'updated_by' => $createdBy,
        ]);

        $this->logAttendanceAudit($id, $employeeId, 'manual_entry', null, ['attendance_id' => $id], $createdBy);
        $this->audit->log('manual_entry', 'attendance', $id, null, ['employee_id' => $employeeId], $createdBy);

        return [
            'success' => true,
            'message' => 'Manual attendance entry created.',
            'data' => ['attendance' => $this->attendance->findDetailed($id)],
        ];
    }

    /**
     * Hook AFTER attendance commit — failures never roll back check-in.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function startMonitoringSafely(int $employeeId, int $attendanceId, array $payload = []): array
    {
        try {
            $deviceId = isset($payload['monitoring_device_id']) ? (int) $payload['monitoring_device_id'] : null;
            $result = (new \App\Services\Monitoring\MonitoringSessionService())
                ->startAfterCheckIn($employeeId, $attendanceId, $deviceId ?: null);
            return $result;
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Monitoring could not start.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }

    /**
     * Hook AFTER attendance checkout commit — failures never roll back checkout.
     *
     * @return array<string, mixed>
     */
    private function stopMonitoringSafely(int $employeeId, int $attendanceId): array
    {
        try {
            return (new \App\Services\Monitoring\MonitoringSessionService())
                ->stopAfterCheckOut($employeeId, $attendanceId, 'checkout');
        } catch (\Throwable $e) {
            try {
                $employee = $this->employees->find($employeeId);
                if ($employee) {
                    (new \App\Services\Monitoring\MonitoringAlertService())->createActionRequired(
                        (int) $employee['company_id'],
                        $employeeId,
                        null,
                        null,
                        'failed_stop_after_checkout',
                        'Monitoring failed to stop',
                        'Monitoring did not stop cleanly after checkout. Please restart or update the desktop agent.',
                        (int) ($employee['user_id'] ?? 0) ?: null
                    );
                }
            } catch (\Throwable) {
                // ignore nested alert failures
            }

            return [
                'success' => false,
                'message' => 'Monitoring could not stop cleanly.',
                'errors' => ['exception' => $e->getMessage()],
            ];
        }
    }
}
