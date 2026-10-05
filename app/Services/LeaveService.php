<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use RuntimeException;

class LeaveService
{
    private Database $db;
    private LeaveRequest $leaveRequests;
    private LeaveBalance $leaveBalances;
    private LeaveType $leaveTypes;
    private Holiday $holidays;
    private Employee $employees;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->leaveRequests = new LeaveRequest();
        $this->leaveBalances = new LeaveBalance();
        $this->leaveTypes = new LeaveType();
        $this->holidays = new Holiday();
        $this->employees = new Employee();
    }

    /**
     * @return array{calendar_days: float, weekend_days: float, holiday_days: float, chargeable_days: float}
     */
    public function calculateLeaveDays(
        int $companyId,
        string $startDate,
        string $endDate,
        bool $isHalfDay = false,
        ?int $branchId = null,
        ?int $leavePolicyId = null
    ): array {
        $policy = $this->getLeavePolicy($leavePolicyId);
        $includeWeekends = (bool) ($policy['include_weekends'] ?? false);
        $includeHolidays = (bool) ($policy['include_holidays'] ?? false);

        $holidayDates = $this->holidays->datesInRange($companyId, $startDate, $endDate, $branchId);
        $holidayLookup = array_flip($holidayDates);

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);
        $calendarDays = 0.0;
        $weekendDays = 0.0;
        $holidayDays = 0.0;

        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            $calendarDays++;
            $dayOfWeek = (int) $date->format('N');
            $isWeekend = $dayOfWeek >= 6;
            $isHoliday = isset($holidayLookup[$dateStr]);

            if ($isWeekend) {
                $weekendDays++;
            }
            if ($isHoliday) {
                $holidayDays++;
            }
        }

        $chargeable = $calendarDays;
        if (!$includeWeekends) {
            $chargeable -= $weekendDays;
        }
        if (!$includeHolidays) {
            $chargeable -= $holidayDays;
        }
        $chargeable = max(0, $chargeable);

        if ($isHalfDay && $startDate === $endDate) {
            $chargeable = min($chargeable, 0.5);
            $calendarDays = 0.5;
        }

        return [
            'calendar_days' => round($calendarDays, 2),
            'weekend_days' => round($weekendDays, 2),
            'holiday_days' => round($holidayDays, 2),
            'chargeable_days' => round($chargeable, 2),
        ];
    }

    public function submitRequest(array $data, int $userId): int
    {
        $employee = $this->employees->findDetailed((int) $data['employee_id']);
        if (!$employee) {
            throw new RuntimeException('Employee not found.');
        }

        $leaveType = $this->leaveTypes->find((int) $data['leave_type_id']);
        if (!$leaveType || (int) $leaveType['company_id'] !== (int) $employee['company_id']) {
            throw new RuntimeException('Invalid leave type.');
        }

        $isHalfDay = !empty($data['is_half_day']);
        $days = $this->calculateLeaveDays(
            (int) $employee['company_id'],
            $data['start_date'],
            $data['end_date'],
            $isHalfDay,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        if ($days['chargeable_days'] <= 0) {
            throw new RuntimeException('No chargeable leave days in the selected range.');
        }

        $year = (int) date('Y', strtotime($data['start_date']));
        $balance = $this->leaveBalances->ensureBalance(
            (int) $employee['id'],
            (int) $leaveType['id'],
            $year
        );

        $available = (float) $balance['closing_balance'];
        if (!(int) $leaveType['allow_negative_balance'] && $available < $days['chargeable_days']) {
            throw new RuntimeException('Insufficient leave balance.');
        }

        $this->db->beginTransaction();

        try {
            $requestId = $this->leaveRequests->create([
                'uuid' => $this->generateUuid(),
                'company_id' => (int) $employee['company_id'],
                'employee_id' => (int) $employee['id'],
                'leave_type_id' => (int) $leaveType['id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_half_day' => $isHalfDay ? 1 : 0,
                'half_day_type' => $isHalfDay ? ($data['half_day_type'] ?? null) : null,
                'reason' => $data['reason'],
                'status' => (int) $leaveType['requires_approval'] ? 'pending' : 'approved',
                'calendar_days' => $days['calendar_days'],
                'weekend_days' => $days['weekend_days'],
                'holiday_days' => $days['holiday_days'],
                'chargeable_days' => $days['chargeable_days'],
                'handover_employee_id' => !empty($data['handover_employee_id']) ? (int) $data['handover_employee_id'] : null,
                'handover_notes' => $data['handover_notes'] ?? null,
                'contact_during_leave' => $data['contact_during_leave'] ?? null,
                'emergency_contact' => $data['emergency_contact'] ?? null,
                'applied_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            if ((int) $leaveType['requires_approval']) {
                $this->leaveBalances->update((int) $balance['id'], [
                    'pending' => round((float) $balance['pending'] + $days['chargeable_days'], 2),
                ]);
                $this->leaveBalances->recalculateClosing((int) $balance['id']);
                $this->createApprovalChain($requestId, $employee);
            } else {
                $this->finalizeApproval($requestId, $userId);
            }

            (new AuditService())->log('create', 'leave', $requestId, null, $data, $userId);
            $this->db->commit();

            return $requestId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approve(int $requestId, int $userId, ?string $comments = null): void
    {
        $request = $this->leaveRequests->findDetailed($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('Leave request cannot be approved.');
        }

        $approval = $this->db->fetch(
            'SELECT * FROM leave_approvals
             WHERE leave_request_id = :id AND approver_id = :approver AND status = :status
             ORDER BY level ASC LIMIT 1',
            ['id' => $requestId, 'approver' => $userId, 'status' => 'pending']
        );

        // Admin/HR override: permission already checked in controller; allow approving the next pending level.
        if (!$approval) {
            $approval = $this->db->fetch(
                'SELECT * FROM leave_approvals
                 WHERE leave_request_id = :id AND status = :status
                 ORDER BY level ASC LIMIT 1',
                ['id' => $requestId, 'status' => 'pending']
            );
        }

        $this->db->beginTransaction();

        try {
            if ($approval) {
                $this->db->update('leave_approvals', [
                    'status' => 'approved',
                    'comments' => $comments,
                    'acted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ], 'id = :id', ['id' => $approval['id']]);
            }

            $pendingCount = (int) $this->db->fetchColumn(
                'SELECT COUNT(*) FROM leave_approvals
                 WHERE leave_request_id = :id AND status = :status',
                ['id' => $requestId, 'status' => 'pending']
            );

            if ($pendingCount === 0) {
                $this->finalizeApproval($requestId, $userId);
            }

            (new AuditService())->log('approve', 'leave', $requestId, null, ['comments' => $comments], $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function reject(int $requestId, int $userId, ?string $comments = null): void
    {
        $request = $this->leaveRequests->find($requestId);
        if (!$request || $request['status'] !== 'pending') {
            throw new RuntimeException('Leave request cannot be rejected.');
        }

        $this->db->beginTransaction();

        try {
            $this->leaveRequests->update($requestId, [
                'status' => 'rejected',
                'updated_by' => $userId,
            ]);

            $this->db->update(
                'leave_approvals',
                [
                    'status' => 'rejected',
                    'comments' => $comments,
                    'acted_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ],
                'leave_request_id = :id AND status = :pending_status',
                ['id' => $requestId, 'pending_status' => 'pending']
            );

            $this->releasePendingBalance($request);
            (new AuditService())->log('reject', 'leave', $requestId, null, ['comments' => $comments], $userId);
            $this->db->commit();

            try {
                (new \App\Services\Chat\ChatHrBridge())->notifyLeaveDecision((int) $request['employee_id'], 'rejected', $comments);
            } catch (\Throwable) {
            }
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cancel(int $requestId, int $userId, ?string $reason = null): void
    {
        $request = $this->leaveRequests->find($requestId);
        if (!$request || !in_array($request['status'], ['pending', 'approved'], true)) {
            throw new RuntimeException('Leave request cannot be cancelled.');
        }

        $wasApproved = $request['status'] === 'approved';

        $this->db->beginTransaction();

        try {
            $this->leaveRequests->update($requestId, [
                'status' => 'cancelled',
                'cancelled_at' => date('Y-m-d H:i:s'),
                'cancellation_reason' => $reason,
                'updated_by' => $userId,
            ]);

            if ($wasApproved) {
                $this->restoreUsedBalance($request);
                $this->removeLeaveAttendance($request);
            } else {
                $this->releasePendingBalance($request);
            }

            $this->db->update(
                'leave_approvals',
                ['status' => 'skipped', 'updated_at' => date('Y-m-d H:i:s')],
                'leave_request_id = :id AND status = :pending_status',
                ['id' => $requestId, 'pending_status' => 'pending']
            );

            (new AuditService())->log('cancel', 'leave', $requestId, null, ['reason' => $reason], $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function extendLeave(int $requestId, string $newEndDate, string $reason, int $userId, bool $autoApprove = false): int
    {
        $request = $this->leaveRequests->findDetailed($requestId);
        if (!$request || !in_array($request['status'], ['approved', 'pending'], true)) {
            throw new RuntimeException('Leave request cannot be extended.');
        }

        if ($newEndDate <= $request['end_date']) {
            throw new RuntimeException('New end date must be after the current end date.');
        }

        $employee = $this->employees->findDetailed((int) $request['employee_id']);
        $extensionStart = (new \DateTime($request['end_date']))->modify('+1 day')->format('Y-m-d');

        $extraDays = $this->calculateLeaveDays(
            (int) $request['company_id'],
            $extensionStart,
            $newEndDate,
            false,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        if ($extraDays['chargeable_days'] <= 0) {
            throw new RuntimeException('No additional chargeable days in the extension range.');
        }

        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->ensureBalance(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );

        if (!(int) ($this->leaveTypes->find((int) $request['leave_type_id'])['allow_negative_balance'] ?? 0)
            && (float) $balance['closing_balance'] < $extraDays['chargeable_days']) {
            throw new RuntimeException('Insufficient leave balance for extension.');
        }

        $this->db->beginTransaction();

        try {
            $extensionId = $this->db->insert('leave_extensions', [
                'leave_request_id' => $requestId,
                'employee_id' => (int) $request['employee_id'],
                'previous_end_date' => $request['end_date'],
                'new_end_date' => $newEndDate,
                'additional_days' => $extraDays['chargeable_days'],
                'reason' => $reason,
                'status' => $autoApprove ? 'approved' : 'pending',
                'reviewed_by' => $autoApprove ? $userId : null,
                'reviewed_at' => $autoApprove ? date('Y-m-d H:i:s') : null,
                'created_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            if ($autoApprove) {
                $this->applyExtension($request, $newEndDate, $extraDays, $userId);
            }

            (new AuditService())->log('extend', 'leave', $requestId, null, [
                'new_end_date' => $newEndDate,
                'additional_days' => $extraDays['chargeable_days'],
            ], $userId);

            $this->db->commit();

            return $extensionId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function approveExtension(int $extensionId, int $userId, ?string $notes = null): void
    {
        $extension = $this->db->fetch(
            'SELECT * FROM leave_extensions WHERE id = :id AND deleted_at IS NULL',
            ['id' => $extensionId]
        );

        if (!$extension || $extension['status'] !== 'pending') {
            throw new RuntimeException('Extension cannot be approved.');
        }

        $request = $this->leaveRequests->findDetailed((int) $extension['leave_request_id']);
        if (!$request) {
            throw new RuntimeException('Leave request not found.');
        }

        $employee = $this->employees->findDetailed((int) $request['employee_id']);
        $extensionStart = (new \DateTime($request['end_date']))->modify('+1 day')->format('Y-m-d');
        $extraDays = $this->calculateLeaveDays(
            (int) $request['company_id'],
            $extensionStart,
            $extension['new_end_date'],
            false,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        $this->db->beginTransaction();

        try {
            $this->db->update('leave_extensions', [
                'status' => 'approved',
                'reviewed_by' => $userId,
                'reviewed_at' => date('Y-m-d H:i:s'),
                'review_notes' => $notes,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $extensionId]);

            $this->applyExtension($request, $extension['new_end_date'], $extraDays, $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function finalizeApproval(int $requestId, int $userId): void
    {
        $request = $this->leaveRequests->find($requestId);
        if (!$request) {
            return;
        }

        $this->leaveRequests->update($requestId, [
            'status' => 'approved',
            'updated_by' => $userId,
        ]);

        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->ensureBalance(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );

        $chargeable = (float) $request['chargeable_days'];
        $this->leaveBalances->update((int) $balance['id'], [
            'pending' => max(0, round((float) $balance['pending'] - $chargeable, 2)),
            'used' => round((float) $balance['used'] + $chargeable, 2),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);

        $this->updateAttendanceOnApproval($request, $userId);

        try {
            (new \App\Services\Chat\ChatHrBridge())->notifyLeaveDecision((int) $request['employee_id'], 'approved');
        } catch (\Throwable) {
        }
    }

    private function applyExtension(array $request, string $newEndDate, array $extraDays, int $userId): void
    {
        $this->leaveRequests->update((int) $request['id'], [
            'end_date' => $newEndDate,
            'calendar_days' => round((float) $request['calendar_days'] + $extraDays['calendar_days'], 2),
            'weekend_days' => round((float) $request['weekend_days'] + $extraDays['weekend_days'], 2),
            'holiday_days' => round((float) $request['holiday_days'] + $extraDays['holiday_days'], 2),
            'chargeable_days' => round((float) $request['chargeable_days'] + $extraDays['chargeable_days'], 2),
            'updated_by' => $userId,
        ]);

        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->ensureBalance(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );

        $this->leaveBalances->update((int) $balance['id'], [
            'used' => round((float) $balance['used'] + $extraDays['chargeable_days'], 2),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);

        $updatedRequest = $this->leaveRequests->find((int) $request['id']);
        if ($updatedRequest && $updatedRequest['status'] === 'approved') {
            $extensionStart = (new \DateTime($request['end_date']))->modify('+1 day')->format('Y-m-d');
            $this->markAttendanceForRange($updatedRequest, $extensionStart, $newEndDate, $userId);
        }
    }

    private function releasePendingBalance(array $request): void
    {
        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->findForEmployee(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );

        if (!$balance) {
            return;
        }

        $this->leaveBalances->update((int) $balance['id'], [
            'pending' => max(0, round((float) $balance['pending'] - (float) $request['chargeable_days'], 2)),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);
    }

    private function restoreUsedBalance(array $request): void
    {
        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->findForEmployee(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );

        if (!$balance) {
            return;
        }

        $this->leaveBalances->update((int) $balance['id'], [
            'used' => max(0, round((float) $balance['used'] - (float) $request['chargeable_days'], 2)),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);
    }

    private function updateAttendanceOnApproval(array $request, int $userId): void
    {
        $this->markAttendanceForRange($request, $request['start_date'], $request['end_date'], $userId);
    }

    private function markAttendanceForRange(array $request, string $startDate, string $endDate, int $userId): void
    {
        $employee = $this->employees->find((int) $request['employee_id']);
        if (!$employee) {
            return;
        }

        $days = $this->calculateLeaveDays(
            (int) $request['company_id'],
            $startDate,
            $endDate,
            (bool) $request['is_half_day'] && $startDate === $endDate,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        $policy = $this->getLeavePolicy($employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null);
        $includeWeekends = (bool) ($policy['include_weekends'] ?? false);
        $includeHolidays = (bool) ($policy['include_holidays'] ?? false);
        $holidayDates = array_flip(
            $this->holidays->datesInRange(
                (int) $request['company_id'],
                $startDate,
                $endDate,
                $employee['branch_id'] ? (int) $employee['branch_id'] : null
            )
        );

        $start = new \DateTime($startDate);
        $end = new \DateTime($endDate);

        for ($date = clone $start; $date <= $end; $date->modify('+1 day')) {
            $dateStr = $date->format('Y-m-d');
            $dayOfWeek = (int) $date->format('N');
            $isWeekend = $dayOfWeek >= 6;
            $isHoliday = isset($holidayDates[$dateStr]);

            if (!$includeWeekends && $isWeekend) {
                continue;
            }
            if (!$includeHolidays && $isHoliday) {
                continue;
            }

            $status = 'on_leave';
            if ((bool) $request['is_half_day'] && $startDate === $endDate) {
                $status = 'half_day';
            }

            $existing = $this->db->fetch(
                'SELECT id FROM attendance WHERE employee_id = :eid AND attendance_date = :d LIMIT 1',
                ['eid' => $request['employee_id'], 'd' => $dateStr]
            );

            $payload = [
                'status' => $status,
                'leave_request_id' => (int) $request['id'],
                'approved_by' => $userId,
                'approved_at' => date('Y-m-d H:i:s'),
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
                'source' => 'system',
            ];

            if ($existing) {
                $this->db->update('attendance', $payload, 'id = :id', ['id' => $existing['id']]);
            } else {
                $this->db->insert('attendance', array_merge($payload, [
                    'uuid' => $this->generateUuid(),
                    'employee_id' => (int) $request['employee_id'],
                    'company_id' => (int) $request['company_id'],
                    'branch_id' => $employee['branch_id'] ?? null,
                    'shift_id' => $employee['shift_id'] ?? null,
                    'attendance_date' => $dateStr,
                    'verification_status' => 'auto_verified',
                    'created_by' => $userId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]));
            }
        }
    }

    private function removeLeaveAttendance(array $request): void
    {
        $this->db->update(
            'attendance',
            [
                'status' => 'absent',
                'leave_request_id' => null,
                'updated_at' => date('Y-m-d H:i:s'),
            ],
            'leave_request_id = :id',
            ['id' => (int) $request['id']]
        );
    }

    private function createApprovalChain(int $requestId, array $employee): void
    {
        $levels = [];
        $level = 1;

        if ($this->db->tableExists('approval_chains')) {
            $chain = $this->db->fetch(
                'SELECT * FROM approval_chains
                 WHERE company_id = :cid AND module = "leave" AND is_active = 1
                 ORDER BY id LIMIT 1',
                ['cid' => $employee['company_id']]
            );
            if ($chain) {
                $steps = $this->db->fetchAll('SELECT * FROM approval_chain_steps WHERE approval_chain_id = :id ORDER BY step_order', ['id' => $chain['id']]);
                foreach ($steps as $step) {
                    $approverId = null;
                    if ($step['approver_type'] === 'manager' && !empty($employee['reporting_manager_id'])) {
                        $approverId = $this->db->fetchColumn('SELECT user_id FROM employees WHERE id = :id AND company_id = :cid AND deleted_at IS NULL', ['id' => $employee['reporting_manager_id'], 'cid' => $employee['company_id']]);
                    } elseif ($step['approver_type'] === 'user' && !empty($step['user_id'])) {
                        $approverId = $this->db->fetchColumn('SELECT id FROM users WHERE id = :id AND is_active = 1 AND deleted_at IS NULL', ['id' => $step['user_id']]);
                    } elseif ($step['approver_type'] === 'role' && !empty($step['role_slug'])) {
                        $approverId = $this->db->fetchColumn(
                            'SELECT u.id FROM users u INNER JOIN user_roles ur ON ur.user_id = u.id INNER JOIN roles r ON r.id = ur.role_id
                             WHERE ur.company_id = :cid AND r.slug = :slug AND u.is_active = 1 AND u.deleted_at IS NULL ORDER BY u.id LIMIT 1',
                            ['cid' => $employee['company_id'], 'slug' => $step['role_slug']]
                        );
                    }
                    if ($approverId) $levels[] = ['level' => $level++, 'approver_id' => (int) $approverId];
                }
            }
        }

        if (empty($levels) && !empty($employee['reporting_manager_id'])) {
            $manager = $this->employees->find((int) $employee['reporting_manager_id']);
            if ($manager && !empty($manager['user_id'])) {
                $levels[] = ['level' => $level++, 'approver_id' => (int) $manager['user_id']];
            }
        }

        if (empty($levels)) {
            $hrUser = $this->db->fetch(
                'SELECT u.id FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.slug IN ("hr_manager", "company_admin", "super_admin")
                 AND (ur.company_id = :company_id OR (r.slug = "super_admin" AND ur.company_id IS NULL))
                 AND u.is_active = 1
                 AND u.deleted_at IS NULL
                 LIMIT 1',
                ['company_id' => $employee['company_id']]
            );
            if ($hrUser) {
                $levels[] = ['level' => 1, 'approver_id' => (int) $hrUser['id']];
            }
        }

        if (empty($levels)) {
            throw new RuntimeException('No approver configured for this leave request.');
        }

        foreach ($levels as $row) {
            $this->db->insert('leave_approvals', [
                'leave_request_id' => $requestId,
                'approver_id' => $row['approver_id'],
                'level' => $row['level'],
                'status' => 'pending',
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    private function getLeavePolicy(?int $leavePolicyId): array
    {
        if (!$leavePolicyId) {
            return ['include_weekends' => 0, 'include_holidays' => 0];
        }

        return $this->db->fetch(
            'SELECT include_weekends, include_holidays FROM leave_policies WHERE id = :id AND deleted_at IS NULL',
            ['id' => $leavePolicyId]
        ) ?? ['include_weekends' => 0, 'include_holidays' => 0];
    }

    public function adminCreate(array $data, int $userId): int
    {
        $employee = $this->employees->findDetailed((int) $data['employee_id']);
        if (!$employee) {
            throw new RuntimeException('Employee not found.');
        }

        $leaveType = $this->leaveTypes->find((int) $data['leave_type_id']);
        if (!$leaveType || (int) $leaveType['company_id'] !== (int) $employee['company_id']) {
            throw new RuntimeException('Invalid leave type.');
        }

        $isHalfDay = !empty($data['is_half_day']);
        $days = $this->calculateLeaveDays(
            (int) $employee['company_id'],
            $data['start_date'],
            $data['end_date'],
            $isHalfDay,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        if ($days['chargeable_days'] <= 0) {
            throw new RuntimeException('No chargeable leave days in the selected range.');
        }

        $year = (int) date('Y', strtotime($data['start_date']));
        $balance = $this->leaveBalances->ensureBalance(
            (int) $employee['id'],
            (int) $leaveType['id'],
            $year
        );

        $available = (float) $balance['closing_balance'];
        if (!(int) $leaveType['allow_negative_balance'] && $available < $days['chargeable_days']) {
            throw new RuntimeException('Insufficient leave balance (' . number_format($available, 1) . ' days remaining).');
        }

        $this->db->beginTransaction();
        try {
            $requestId = $this->leaveRequests->create([
                'uuid' => $this->generateUuid(),
                'company_id' => (int) $employee['company_id'],
                'employee_id' => (int) $employee['id'],
                'leave_type_id' => (int) $leaveType['id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'],
                'is_half_day' => $isHalfDay ? 1 : 0,
                'half_day_type' => $isHalfDay ? ($data['half_day_type'] ?? null) : null,
                'reason' => $data['reason'],
                'status' => 'pending',
                'calendar_days' => $days['calendar_days'],
                'weekend_days' => $days['weekend_days'],
                'holiday_days' => $days['holiday_days'],
                'chargeable_days' => $days['chargeable_days'],
                'applied_at' => date('Y-m-d H:i:s'),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);

            // Always auto-approve admin-created leave
            $this->finalizeApproval($requestId, $userId);

            (new AuditService())->log('create', 'leave', $requestId, null, $data, $userId);
            $this->db->commit();

            return $requestId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function adminUpdate(int $requestId, array $data, int $userId): void
    {
        $request = $this->leaveRequests->findDetailed($requestId);
        if (!$request) {
            throw new RuntimeException('Leave request not found.');
        }
        if (!empty($request['deleted_at'])) {
            throw new RuntimeException('Archived leave cannot be edited. Restore it first.');
        }
        if (in_array($request['status'], ['cancelled', 'rejected', 'withdrawn'], true)) {
            throw new RuntimeException('This leave request cannot be edited in its current status.');
        }

        $startDate = (string) ($data['start_date'] ?? $request['start_date']);
        $endDate = (string) ($data['end_date'] ?? $request['end_date']);
        if ($startDate === '' || $endDate === '') {
            throw new RuntimeException('Start date and end date are required.');
        }
        if ($endDate < $startDate) {
            throw new RuntimeException('End date cannot precede start date.');
        }

        $leaveTypeId = (int) ($data['leave_type_id'] ?? $request['leave_type_id']);
        $leaveType = $this->leaveTypes->find($leaveTypeId);
        if (!$leaveType) {
            throw new RuntimeException('Leave type must exist.');
        }

        $employee = $this->employees->findDetailed((int) $request['employee_id']);
        if (!$employee) {
            throw new RuntimeException('Employee must exist.');
        }

        $isHalfDay = !empty($data['is_half_day']);
        $halfDayType = $isHalfDay ? ($data['half_day_type'] ?? $request['half_day_type'] ?? 'first_half') : null;
        if ($isHalfDay && !in_array($halfDayType, ['first_half', 'second_half'], true)) {
            throw new RuntimeException('Half-day leave must use a valid half-day option.');
        }
        if ($isHalfDay && $startDate !== $endDate) {
            throw new RuntimeException('Half-day leave must be for a single day.');
        }

        $days = $this->calculateLeaveDays(
            (int) $request['company_id'],
            $startDate,
            $endDate,
            $isHalfDay,
            $employee['branch_id'] ? (int) $employee['branch_id'] : null,
            $employee['leave_policy_id'] ? (int) $employee['leave_policy_id'] : null
        );

        $overlap = $this->db->fetch(
            "SELECT id FROM leave_requests
             WHERE employee_id = :eid AND deleted_at IS NULL AND id <> :id
               AND status IN ('pending','approved')
               AND start_date <= :end AND end_date >= :start
             LIMIT 1",
            [
                'eid' => (int) $request['employee_id'],
                'id' => $requestId,
                'start' => $startDate,
                'end' => $endDate,
            ]
        );
        if ($overlap) {
            throw new RuntimeException('Leave cannot overlap conflicting approved or pending leave.');
        }

        $lockedPayroll = $this->db->fetch(
            "SELECT pp.id FROM payroll_periods pp
             WHERE pp.company_id = :cid AND pp.deleted_at IS NULL
               AND pp.status IN ('locked','paid')
               AND pp.start_date <= :end AND pp.end_date >= :start
             LIMIT 1",
            [
                'cid' => (int) $request['company_id'],
                'start' => $startDate,
                'end' => $endDate,
            ]
        );
        if ($lockedPayroll && $request['status'] === 'approved') {
            throw new RuntimeException('Cannot amend approved leave that falls in a locked or paid payroll period.');
        }

        $reason = trim((string) ($data['amendment_reason'] ?? $data['reason_note'] ?? ''));
        $wasApproved = $request['status'] === 'approved';
        if ($wasApproved && $reason === '') {
            throw new RuntimeException('Amendment reason is required for approved leave.');
        }

        $payload = [
            'leave_type_id' => $leaveTypeId,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'is_half_day' => $isHalfDay ? 1 : 0,
            'half_day_type' => $halfDayType,
            'reason' => trim((string) ($data['reason'] ?? $request['reason'])),
            'handover_employee_id' => $data['handover_employee_id'] !== '' && $data['handover_employee_id'] !== null
                ? (int) $data['handover_employee_id']
                : null,
            'handover_notes' => $data['handover_notes'] ?? $request['handover_notes'],
            'calendar_days' => $days['calendar_days'],
            'weekend_days' => $days['weekend_days'],
            'holiday_days' => $days['holiday_days'],
            'chargeable_days' => $days['chargeable_days'],
            'updated_by' => $userId,
        ];

        if (!empty($data['status']) && can('leave.approve') && in_array($data['status'], ['draft', 'pending', 'approved'], true)) {
            $payload['status'] = $data['status'];
        }

        $this->db->beginTransaction();
        try {
            if ($wasApproved) {
                $this->restoreUsedBalance($request);
                $this->removeLeaveAttendance($request);
            } elseif ($request['status'] === 'pending') {
                $this->releasePendingBalance($request);
            }

            $this->leaveRequests->update($requestId, $payload);
            $updated = $this->leaveRequests->find($requestId);

            if (($updated['status'] ?? '') === 'approved') {
                $this->consumeUsedBalance($updated);
                $this->updateAttendanceOnApproval($updated, $userId);
            } elseif (($updated['status'] ?? '') === 'pending') {
                $this->reservePendingBalance($updated);
            }

            $balanceAdjustment = round((float) $days['chargeable_days'] - (float) $request['chargeable_days'], 2);
            $this->db->insert('leave_amendments', [
                'leave_request_id' => $requestId,
                'previous_values' => json_encode($request, JSON_UNESCAPED_UNICODE),
                'new_values' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'reason' => $reason !== '' ? $reason : 'Admin edit',
                'balance_adjustment' => $balanceAdjustment,
                'changed_by' => $userId,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            (new AuditService())->log('update', 'leave', $requestId, $request, $payload, $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function archive(int $requestId, string $reason, int $userId): void
    {
        $request = $this->db->fetch('SELECT * FROM leave_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$request) {
            throw new RuntimeException('Leave request not found.');
        }
        if (!empty($request['deleted_at'])) {
            throw new RuntimeException('Leave is already archived.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Archive reason is required.');
        }

        if ($request['status'] === 'approved') {
            $lockedPayroll = $this->db->fetch(
                "SELECT pp.id FROM payroll_periods pp
                 WHERE pp.company_id = :cid AND pp.deleted_at IS NULL
                   AND pp.status IN ('locked','paid')
                   AND pp.start_date <= :end AND pp.end_date >= :start
                 LIMIT 1",
                [
                    'cid' => (int) $request['company_id'],
                    'start' => $request['start_date'],
                    'end' => $request['end_date'],
                ]
            );
            if ($lockedPayroll) {
                throw new RuntimeException('Cannot archive leave included in locked or paid payroll without reopening payroll.');
            }
        }

        $now = date('Y-m-d H:i:s');
        $this->db->beginTransaction();
        try {
            if ($request['status'] === 'approved') {
                $this->restoreUsedBalance($request);
                $this->removeLeaveAttendance($request);
            } elseif ($request['status'] === 'pending') {
                $this->releasePendingBalance($request);
            }

            $this->db->update('leave_requests', [
                'deleted_at' => $now,
                'deleted_by' => $userId,
                'deletion_reason' => $reason,
                'updated_by' => $userId,
                'updated_at' => $now,
            ], 'id = :id', ['id' => $requestId]);

            (new AuditService())->log('delete', 'leave', $requestId, $request, ['reason' => $reason], $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function restoreArchived(int $requestId, int $userId): void
    {
        $request = $this->db->fetch('SELECT * FROM leave_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$request || empty($request['deleted_at'])) {
            throw new RuntimeException('Archived leave request not found.');
        }

        $employee = $this->employees->find((int) $request['employee_id']);
        if (!$employee) {
            throw new RuntimeException('Leave cannot be restored because the employee no longer exists.');
        }

        $overlap = $this->db->fetch(
            "SELECT id FROM leave_requests
             WHERE employee_id = :eid AND deleted_at IS NULL AND id <> :id
               AND status IN ('pending','approved')
               AND start_date <= :end AND end_date >= :start
             LIMIT 1",
            [
                'eid' => (int) $request['employee_id'],
                'id' => $requestId,
                'start' => $request['start_date'],
                'end' => $request['end_date'],
            ]
        );
        if ($overlap) {
            throw new RuntimeException('Leave cannot be restored because an overlapping active leave request already exists.');
        }

        $this->db->beginTransaction();
        try {
            $this->db->update('leave_requests', [
                'deleted_at' => null,
                'deleted_by' => null,
                'deletion_reason' => null,
                'updated_by' => $userId,
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id = :id', ['id' => $requestId]);

            $restored = $this->leaveRequests->find($requestId);
            if ($restored && $restored['status'] === 'approved') {
                $this->consumeUsedBalance($restored);
                $this->updateAttendanceOnApproval($restored, $userId);
            } elseif ($restored && $restored['status'] === 'pending') {
                $this->reservePendingBalance($restored);
            }

            (new AuditService())->log('restore', 'leave', $requestId, $request, null, $userId);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function permanentlyDelete(int $requestId, string $reason, int $userId): void
    {
        $request = $this->db->fetch('SELECT * FROM leave_requests WHERE id = :id LIMIT 1', ['id' => $requestId]);
        if (!$request || empty($request['deleted_at'])) {
            throw new RuntimeException('Only archived leave can be permanently deleted.');
        }
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('Permanent deletion reason is required.');
        }

        $this->db->beginTransaction();
        try {
            (new AuditService())->log('delete', 'leave', $requestId, $request, ['permanent' => true, 'reason' => $reason], $userId);

            $this->db->query(
                'UPDATE attendance SET leave_request_id = NULL WHERE leave_request_id = :id',
                ['id' => $requestId]
            );
            $this->db->delete('leave_amendments', 'leave_request_id = :id', ['id' => $requestId]);
            $this->db->delete('leave_extensions', 'leave_request_id = :id', ['id' => $requestId]);
            $this->db->delete('leave_approvals', 'leave_request_id = :id', ['id' => $requestId]);
            $this->db->delete('leave_attachments', 'leave_request_id = :id', ['id' => $requestId]);
            $this->db->delete('leave_requests', 'id = :id', ['id' => $requestId]);
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function consumeUsedBalance(array $request): void
    {
        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->findForEmployee(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );
        if (!$balance) {
            return;
        }
        $this->leaveBalances->update((int) $balance['id'], [
            'used' => round((float) $balance['used'] + (float) $request['chargeable_days'], 2),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);
    }

    private function reservePendingBalance(array $request): void
    {
        $year = (int) date('Y', strtotime($request['start_date']));
        $balance = $this->leaveBalances->findForEmployee(
            (int) $request['employee_id'],
            (int) $request['leave_type_id'],
            $year
        );
        if (!$balance) {
            return;
        }
        $this->leaveBalances->update((int) $balance['id'], [
            'pending' => round((float) $balance['pending'] + (float) $request['chargeable_days'], 2),
        ]);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);
    }

    private function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0xffff)
        );
    }
}
