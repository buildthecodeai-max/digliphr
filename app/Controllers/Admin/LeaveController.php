<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\AuditService;
use App\Services\LeaveService;
use RuntimeException;

class LeaveController extends Controller
{
    private LeaveRequest $leaveRequests;
    private LeaveType $leaveTypes;
    private LeaveBalance $leaveBalances;
    private Holiday $holidays;
    private LeaveService $leaveService;
    private Employee $employees;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->leaveRequests = new LeaveRequest();
        $this->leaveTypes = new LeaveType();
        $this->leaveBalances = new LeaveBalance();
        $this->holidays = new Holiday();
        $this->leaveService = new LeaveService();
        $this->employees = new Employee();
    }

    public function index(): void
    {
        $this->authorize('leave.view');

        $filters = [
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'status' => $this->request->input('status'),
            'employee_id' => $this->request->input('employee_id'),
            'leave_type_id' => $this->request->input('leave_type_id'),
            'from_date' => $this->request->input('from_date'),
            'to_date' => $this->request->input('to_date'),
        ];
        $page = max(1, (int) $this->request->input('page', 1));
        $paginator = $this->leaveRequests->search(array_filter($filters), $page);
        $companyId = $filters['company_id'];

        $this->view('admin.leave.index', [
            'title' => 'Leave Requests',
            'requests' => $paginator['data'],
            'paginator' => $paginator,
            'filters' => $filters,
            'leaveTypes' => $companyId ? $this->leaveTypes->forCompany((int) $companyId) : $this->tenantLeaveTypes(),
            'savedFilters' => $companyId ? (new \App\Services\SavedFilterService())->forUser((int) $companyId, (int) $this->auth->id(), 'leave') : [],
        ]);
    }

    public function show(int $id): void
    {
        $this->authorize('leave.view');

        $request = $this->leaveRequests->findDetailed($id, can('leave.view_archived'));
        if (!$request) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }
        $this->tenant->assertCompany((int) $request['company_id']);
        if (!empty($request['deleted_at']) && !can('leave.view_archived')) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }

        $this->view('admin.leave.show', [
            'title' => 'Leave Request #' . $id,
            'leaveRequest' => $request,
            'approvals' => $this->leaveRequests->getApprovals($id),
            'extensions' => $this->leaveRequests->getExtensions($id),
            'amendments' => can('leave.view_history') ? $this->leaveRequests->getAmendments($id) : [],
            'isArchived' => !empty($request['deleted_at']),
        ]);
    }

    public function pending(): void
    {
        $this->authorize('leave.view');
        $this->renderLeaveList(['status' => 'pending'], 'Pending Approvals');
    }

    public function approved(): void
    {
        $this->authorize('leave.view');
        $this->renderLeaveList(['status' => 'approved'], 'Approved Leave');
    }

    public function archived(): void
    {
        $this->authorize('leave.view_archived');
        $filters = [
            'archived' => 1,
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'employee_id' => $this->request->input('employee_id'),
            'leave_type_id' => $this->request->input('leave_type_id'),
            'from_date' => $this->request->input('from_date'),
            'to_date' => $this->request->input('to_date'),
            'q' => $this->request->input('q'),
        ];
        $page = max(1, (int) $this->request->input('page', 1));
        $paginator = $this->leaveRequests->search(array_filter($filters) + ['archived' => 1], $page);

        $this->view('admin.leave.archived', [
            'title' => 'Archived Leave',
            'requests' => $paginator['data'],
            'paginator' => $paginator,
            'filters' => $filters,
            'leaveTypes' => $filters['company_id'] ? $this->leaveTypes->forCompany((int) $filters['company_id']) : $this->tenantLeaveTypes(),
            'savedFilters' => !empty($filters['company_id']) ? (new \App\Services\SavedFilterService())->forUser((int) $filters['company_id'], (int) $this->auth->id(), 'leave') : [],
        ]);
    }

    public function edit(int $id): void
    {
        $this->authorize('leave.edit');
        $request = $this->leaveRequests->findDetailed($id);
        if (!$request) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }
        $this->tenant->assertCompany((int) $request['company_id']);
        if (in_array($request['status'], ['cancelled', 'rejected', 'withdrawn'], true)) {
            flash('error', 'This leave request cannot be edited.');
            $this->redirect('/admin/leave/' . $id);
            return;
        }

        $this->view('admin.leave.edit', [
            'title' => 'Edit Leave #' . $id,
            'leaveRequest' => $request,
            'leaveTypes' => $this->leaveTypes->forCompany((int) $request['company_id']),
            'employees' => $this->employees->search(['company_id' => (int) $request['company_id'], 'status' => 'active'], 1, 500)['data'],
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('leave.edit');
        $this->assertLeaveAccess($id);

        try {
            $this->leaveService->adminUpdate($id, $this->request->all(), (int) $this->user()['id']);
            flash('success', 'Leave request updated.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/admin/leave/' . $id . '/edit');
            return;
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function cancel(int $id): void
    {
        $this->authorize('leave.cancel');
        $this->assertLeaveAccess($id);

        try {
            $this->leaveService->cancel($id, (int) $this->user()['id'], $this->request->input('reason'));
            flash('success', 'Leave request cancelled.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function archive(int $id): void
    {
        $this->authorize('leave.archive');
        $this->assertLeaveAccess($id);

        try {
            $this->leaveService->archive($id, (string) $this->request->input('reason', ''), (int) $this->user()['id']);
            flash('success', 'Leave archived successfully.');
            $this->redirect('/admin/leave/archived');
            return;
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function restore(int $id): void
    {
        $this->authorize('leave.restore');
        $this->assertLeaveAccess($id, true);

        try {
            $this->leaveService->restoreArchived($id, (int) $this->user()['id']);
            flash('success', 'Leave restored successfully.');
            $this->redirect('/admin/leave/' . $id);
            return;
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/archived');
    }

    public function forceDestroy(int $id = 0): void
    {
        $this->authorize('leave.permanently_delete');

        if ($id <= 0) {
            $id = (int) $this->request->input('leave_id', 0);
        }
        $this->assertLeaveAccess($id, true);
        if ($id <= 0) {
            flash('error', 'Unable to determine which leave record to delete.');
            $this->redirect('/admin/leave/archived');
            return;
        }

        try {
            $this->leaveService->permanentlyDelete($id, (string) $this->request->input('reason', ''), (int) $this->user()['id']);
            flash('success', 'Leave permanently deleted.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/admin/leave/archived');
            return;
        }

        $this->redirect('/admin/leave/archived');
    }

    /**
     * @param array<string, mixed> $forcedFilters
     */
    private function renderLeaveList(array $forcedFilters, string $title): void
    {
        $filters = array_merge([
            'company_id' => $this->tenant->resolveCompanyId($this->request->input('company_id')),
            'status' => $this->request->input('status'),
            'employee_id' => $this->request->input('employee_id'),
            'leave_type_id' => $this->request->input('leave_type_id'),
            'from_date' => $this->request->input('from_date'),
            'to_date' => $this->request->input('to_date'),
        ], $forcedFilters);
        $page = max(1, (int) $this->request->input('page', 1));
        $paginator = $this->leaveRequests->search(array_filter($filters), $page);

        $this->view('admin.leave.index', [
            'title' => $title,
            'requests' => $paginator['data'],
            'paginator' => $paginator,
            'filters' => $filters,
            'leaveTypes' => $filters['company_id'] ? $this->leaveTypes->forCompany((int) $filters['company_id']) : $this->tenantLeaveTypes(),
        ]);
    }

    public function approve(int $id): void
    {
        $this->authorize('leave.approve');
        $this->assertLeaveAccess($id);

        try {
            $this->leaveService->approve($id, (int) $this->user()['id'], $this->request->input('comments'));
            flash('success', 'Leave request approved.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function reject(int $id): void
    {
        $this->authorize('leave.reject');
        $this->assertLeaveAccess($id);

        try {
            $this->leaveService->reject($id, (int) $this->user()['id'], $this->request->input('comments'));
            flash('success', 'Leave request rejected.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function extend(int $id): void
    {
        $this->authorize('leave.extend');
        $this->assertLeaveAccess($id);

        $data = $this->validate([
            'new_end_date' => 'required|date',
            'reason' => 'required|min:5|max:1000',
        ]);

        try {
            $this->leaveService->extendLeave(
                $id,
                $data['new_end_date'],
                $data['reason'],
                (int) $this->user()['id'],
                true
            );
            flash('success', 'Leave extended successfully.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/admin/leave/' . $id);
    }

    public function types(): void
    {
        $this->authorize('leave.types');

        $scope = $this->tenant->sql('lt.company_id', 'leave_type_company');
        $this->view('admin.leave.types', [
            'title' => 'Leave Types',
            'leaveTypes' => $this->leaveTypes->raw(
                'SELECT lt.*, c.name AS company_name
                 FROM leave_types lt
                 LEFT JOIN companies c ON c.id = lt.company_id
                 WHERE lt.deleted_at IS NULL AND (' . $scope['sql'] . ')
                 ORDER BY lt.sort_order ASC, lt.name ASC',
                $scope['params']
            ),
        ]);
    }

    public function createType(): void
    {
        $this->authorize('leave.types');
        $this->view('admin.leave.type_form', [
            'title' => 'Add Leave Type',
            'leaveType' => null,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function storeType(): void
    {
        $this->authorize('leave.types');

        $data = $this->validateLeaveTypeRules();
        $id = $this->leaveTypes->create($data);
        (new AuditService())->log('create', 'leave_types', $id, null, $data);

        flash('success', 'Leave type created.');
        $this->redirect('/admin/leave/types');
    }

    public function editType(int $id): void
    {
        $this->authorize('leave.types');

        $leaveType = $this->leaveTypes->find($id);
        if (!$leaveType) {
            throw new \App\Exceptions\HttpException('Leave type not found.', 404);
        }
        $this->tenant->assertCompany((int) $leaveType['company_id']);

        $this->view('admin.leave.type_form', [
            'title' => 'Edit Leave Type',
            'leaveType' => $leaveType,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function updateType(int $id): void
    {
        $this->authorize('leave.types');

        $leaveType = $this->leaveTypes->find($id);
        if (!$leaveType) {
            throw new \App\Exceptions\HttpException('Leave type not found.', 404);
        }
        $this->tenant->assertCompany((int) $leaveType['company_id']);

        $data = $this->validateLeaveTypeRules();
        $this->leaveTypes->update($id, $data);
        (new AuditService())->log('update', 'leave_types', $id, $leaveType, $data);

        flash('success', 'Leave type updated.');
        $this->redirect('/admin/leave/types');
    }

    public function destroyType(int $id): void
    {
        $this->authorize('leave.types');

        $leaveType = $this->leaveTypes->find($id);
        if (!$leaveType) {
            throw new \App\Exceptions\HttpException('Leave type not found.', 404);
        }
        $this->tenant->assertCompany((int) $leaveType['company_id']);

        $this->leaveTypes->delete($id);
        flash('success', 'Leave type deleted.');
        $this->redirect('/admin/leave/types');
    }

    public function allocate(): void
    {
        $this->authorize('leave.edit');

        $companies = $this->tenant->companies(true);
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if (!$companyId && count($companies) === 1) {
            $companyId = (int) $companies[0]['id'];
        }
        $year = (int) ($this->request->input('year') ?: date('Y'));

        $this->view('admin.leave.allocate', [
            'title'     => 'Leave Allocation',
            'companies' => $companies,
            'companyId' => $companyId,
            'year'      => $year,
        ]);
    }

    public function runAllocation(): void
    {
        $this->authorize('leave.edit');

        $companyId = (int) $this->request->input('company_id');
        $year      = (int) $this->request->input('year', (string) date('Y'));
        $action    = $this->request->input('action', 'yearly');

        $this->tenant->assertCompany($companyId);

        $svc = new \App\Services\LeaveAllocationService();

        if ($action === 'monthly') {
            $month = (int) $this->request->input('month', (string) date('n'));
            $count = $svc->runMonthlyAccrual($companyId, $year, $month);
            flash('success', "Monthly accrual applied to {$count} employee leave balances.");
        } else {
            $count = $svc->allocateAllEmployees($companyId, $year);
            flash('success', "Default balances allocated for {$count} employee leave entries.");
        }

        $this->redirect('/admin/leave/allocate?company_id=' . $companyId . '&year=' . $year);
    }

    public function createManual(): void
    {
        $this->authorize('leave.create');

        $companies = $this->tenant->companies(true);
        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        // Auto-select when there is only one company
        if (!$companyId && count($companies) === 1) {
            $companyId = (int) $companies[0]['id'];
        }
        $employees = $companyId
            ? $this->employees->search(['company_id' => $companyId, 'status' => 'active'], 1, 500)['data']
            : [];

        $this->view('admin.leave.manual_form', [
            'title' => 'Record Leave for Employee',
            'employees' => $employees,
            'leaveTypes' => $companyId ? $this->leaveTypes->forCompany((int) $companyId) : $this->tenantLeaveTypes(),
            'companies' => $companies,
            'companyId' => $companyId,
            'preEmployee' => (int) $this->request->input('employee_id', 0),
        ]);
    }

    public function storeManual(): void
    {
        $this->authorize('leave.create');

        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|in:first_half,second_half',
            'reason' => 'required|min:3|max:1000',
        ]);

        $data['is_half_day'] = !empty($data['is_half_day']) ? 1 : 0;

        try {
            $id = $this->leaveService->adminCreate($data, (int) $this->user()['id']);
            flash('success', 'Leave recorded and approved successfully.');
            $this->redirect('/admin/leave/' . $id);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/admin/leave/create');
        }
    }

    public function balances(): void
    {
        $this->authorize('leave.balances');

        $employeeId = (int) $this->request->input('employee_id', 0);
        if ($employeeId) {
            $this->tenant->employee($employeeId);
        }
        $year = (int) $this->request->input('year', (int) date('Y'));
        $balances = $employeeId ? $this->leaveBalances->forEmployee($employeeId, $year) : [];

        $this->view('admin.leave.balances', [
            'title' => 'Leave Balances',
            'balances' => $balances,
            'employeeId' => $employeeId,
            'year' => $year,
            'employees' => $this->employees->raw(
                'SELECT id, employee_code, CONCAT(first_name, " ", last_name) AS name
                 FROM employees WHERE deleted_at IS NULL AND (' . $this->tenant->sql('company_id', 'balance_employee_company')['sql'] . ')
                 ORDER BY first_name ASC LIMIT 500',
                $this->tenant->sql('company_id', 'balance_employee_company')['params']
            ),
            'leaveTypes' => $this->tenantLeaveTypes(),
        ]);
    }

    public function updateBalance(): void
    {
        $this->authorize('leave.balances');

        $data = $this->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_type_id' => 'required|exists:leave_types,id',
            'year' => 'required|integer',
            'opening_balance' => 'nullable|numeric|min:0',
            'accrued' => 'nullable|numeric|min:0',
            'adjusted' => 'nullable|numeric',
            'used' => 'nullable|numeric|min:0',
            'notes' => 'nullable|max:500',
        ]);
        $employee = $this->tenant->employee((int) $data['employee_id']);
        $leaveType = $this->tenant->record('leave_types', (int) $data['leave_type_id']);
        if ((int) $employee['company_id'] !== (int) $leaveType['company_id']) {
            throw new \App\Exceptions\HttpException('Employee and leave type must belong to the same company.', 422);
        }

        $balance = $this->leaveBalances->ensureBalance(
            (int) $data['employee_id'],
            (int) $data['leave_type_id'],
            (int) $data['year']
        );

        $num = static fn($v, $fallback) => ($v !== null && $v !== '') ? (float) $v : (float) $fallback;
        $updates = [
            'opening_balance' => $num($data['opening_balance'] ?? null, $balance['opening_balance']),
            'accrued'         => $num($data['accrued'] ?? null,          $balance['accrued']),
            'adjusted'        => $num($data['adjusted'] ?? null,         $balance['adjusted']),
            'notes'           => $data['notes'] ?? $balance['notes'],
        ];
        if (isset($data['used']) && $data['used'] !== '') {
            $updates['used'] = (float) $data['used'];
        }
        $this->leaveBalances->update((int) $balance['id'], $updates);
        $this->leaveBalances->recalculateClosing((int) $balance['id']);

        flash('success', 'Leave balance updated.');
        $this->redirect('/admin/leave/balances?employee_id=' . $data['employee_id'] . '&year=' . $data['year']);
    }

    public function holidays(): void
    {
        $this->authorize('holidays.view');

        $companyId = $this->tenant->resolveCompanyId($this->request->input('company_id'));
        if ($companyId === null) {
            $companyId = (int) (($this->tenant->companies(true)[0]['id'] ?? 0));
        }
        $this->view('admin.leave.holidays', [
            'title' => 'Holidays',
            'holidays' => $this->holidays->forCompany($companyId),
            'companyId' => $companyId,
            'companies' => $this->tenant->companies(true),
        ]);
    }

    public function createHoliday(): void
    {
        $this->authorize('holidays.create');

        $this->view('admin.leave.holiday_form', [
            'title' => 'Add Holiday',
            'holiday' => null,
            'companies' => $this->tenant->companies(true),
            'branches' => $this->tenantBranches(),
        ]);
    }

    public function storeHoliday(): void
    {
        $this->authorize('holidays.create');

        $data = $this->validateHolidayRules();
        $data['created_by'] = $this->user()['id'] ?? null;
        $id = $this->holidays->create($data);
        (new AuditService())->log('create', 'holidays', $id, null, $data);

        flash('success', 'Holiday created.');
        $this->redirect('/admin/leave/holidays?company_id=' . $data['company_id']);
    }

    public function editHoliday(int $id): void
    {
        $this->authorize('holidays.update');

        $holiday = $this->holidays->find($id);
        if (!$holiday) {
            throw new \App\Exceptions\HttpException('Holiday not found.', 404);
        }
        $this->tenant->assertCompany((int) $holiday['company_id']);

        $this->view('admin.leave.holiday_form', [
            'title' => 'Edit Holiday',
            'holiday' => $holiday,
            'companies' => $this->tenant->companies(true),
            'branches' => $this->tenantBranches(),
        ]);
    }

    public function updateHoliday(int $id): void
    {
        $this->authorize('holidays.update');

        $holiday = $this->holidays->find($id);
        if (!$holiday) {
            throw new \App\Exceptions\HttpException('Holiday not found.', 404);
        }
        $this->tenant->assertCompany((int) $holiday['company_id']);

        $data = $this->validateHolidayRules();
        $this->holidays->update($id, $data);
        (new AuditService())->log('update', 'holidays', $id, $holiday, $data);

        flash('success', 'Holiday updated.');
        $this->redirect('/admin/leave/holidays?company_id=' . $data['company_id']);
    }

    public function destroyHoliday(int $id): void
    {
        $this->authorize('holidays.delete');

        $holiday = $this->holidays->find($id);
        if (!$holiday) {
            throw new \App\Exceptions\HttpException('Holiday not found.', 404);
        }
        $this->tenant->assertCompany((int) $holiday['company_id']);

        $this->holidays->delete($id);
        flash('success', 'Holiday deleted.');
        $this->redirect('/admin/leave/holidays?company_id=' . $holiday['company_id']);
    }

    private function validateLeaveTypeRules(): array
    {
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'name' => 'required|min:2|max:150',
            'code' => 'required|min:2|max:50',
            'description' => 'nullable|max:1000',
            'is_paid' => 'nullable|boolean',
            'requires_approval' => 'nullable|boolean',
            'requires_attachment' => 'nullable|boolean',
            'allow_half_day' => 'nullable|boolean',
            'allow_negative_balance' => 'nullable|boolean',
            'max_days_per_request' => 'nullable|numeric|min:0',
            'min_days_per_request' => 'nullable|numeric|min:0',
            'notice_days' => 'nullable|integer|min:0',
            'gender_restriction' => 'nullable|in:all,male,female,other',
            'color' => 'nullable|max:20',
            'sort_order' => 'nullable|integer',
            'is_active' => 'nullable|boolean',
            'default_days' => 'nullable|numeric|min:0',
            'accrual_type' => 'nullable|in:yearly,monthly',
        ]);
        $this->tenant->assertCompany((int) $data['company_id']);
        $data['default_days'] = isset($data['default_days']) && $data['default_days'] !== '' ? (float) $data['default_days'] : 0;
        $data['accrual_type'] = $data['accrual_type'] ?? 'yearly';

        foreach (['is_paid', 'requires_approval', 'requires_attachment', 'allow_half_day', 'allow_negative_balance', 'is_active'] as $flag) {
            $data[$flag] = isset($data[$flag]) ? 1 : 0;
        }

        return $data;
    }

    private function validateHolidayRules(): array
    {
        $data = $this->validate([
            'company_id' => 'required|exists:companies,id',
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|min:2|max:200',
            'holiday_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:holiday_date',
            'type' => 'required|in:public,optional,company,restricted',
            'is_paid' => 'nullable|boolean',
            'is_recurring' => 'nullable|boolean',
            'description' => 'nullable|max:1000',
        ]);
        $this->tenant->assertCompany((int) $data['company_id']);
        if (!empty($data['branch_id'])) {
            $branch = $this->tenant->record('branches', (int) $data['branch_id']);
            if ((int) $branch['company_id'] !== (int) $data['company_id']) {
                throw new \App\Exceptions\HttpException('Branch must belong to the selected company.', 422);
            }
        }

        $data['is_paid'] = isset($data['is_paid']) ? 1 : 0;
        $data['is_recurring'] = isset($data['is_recurring']) ? 1 : 0;
        $data['branch_id'] = !empty($data['branch_id']) ? (int) $data['branch_id'] : null;

        return $data;
    }

    private function assertLeaveAccess(int $id, bool $includingArchived = false): array
    {
        $request = $this->leaveRequests->findDetailed($id, $includingArchived);
        if (!$request) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }
        $this->tenant->assertCompany((int) $request['company_id']);
        return $request;
    }

    private function tenantLeaveTypes(): array
    {
        $scope = $this->tenant->sql('company_id', 'leave_type_list_company');
        return $this->leaveTypes->raw(
            'SELECT * FROM leave_types WHERE deleted_at IS NULL AND (' . $scope['sql'] . ') ORDER BY name',
            $scope['params']
        );
    }

    private function tenantBranches(): array
    {
        $scope = $this->tenant->sql('company_id', 'holiday_branch_company');
        return (new \App\Models\Branch())->raw(
            'SELECT * FROM branches WHERE deleted_at IS NULL AND (' . $scope['sql'] . ') ORDER BY name',
            $scope['params']
        );
    }
}
