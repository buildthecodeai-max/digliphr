<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\LeaveService;
use RuntimeException;

class LeaveController extends Controller
{
    private LeaveRequest $leaveRequests;
    private LeaveType $leaveTypes;
    private LeaveBalance $leaveBalances;
    private LeaveService $leaveService;

    public function __construct($request, $response)
    {
        parent::__construct($request, $response);
        $this->leaveRequests = new LeaveRequest();
        $this->leaveTypes = new LeaveType();
        $this->leaveBalances = new LeaveBalance();
        $this->leaveService = new LeaveService();
    }

    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $page = max(1, (int) $this->request->input('page', 1));
        $paginator = $this->leaveRequests->search(['employee_id' => (int) $employee['id']], $page);
        $balances = $this->leaveBalances->forEmployee((int) $employee['id']);

        $this->view('employee.leave.index', [
            'title' => 'My Leave',
            'requests' => $paginator['data'],
            'paginator' => $paginator,
            'balances' => $balances,
        ], 'layouts/employee');
    }

    public function create(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $leaveTypes = $this->leaveTypes->forCompany((int) $employee['company_id']);

        $this->view('employee.leave.form', [
            'title' => 'Apply for Leave',
            'leaveTypes' => $leaveTypes,
            'balances' => $this->leaveBalances->forEmployee((int) $employee['id']),
        ], 'layouts/employee');
    }

    public function store(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $data = $this->validate([
            'leave_type_id' => 'required|exists:leave_types,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'is_half_day' => 'nullable|boolean',
            'half_day_type' => 'nullable|in:first_half,second_half',
            'reason' => 'required|min:5|max:1000',
            'contact_during_leave' => 'nullable|max:255',
            'emergency_contact' => 'nullable|max:255',
        ]);

        $data['employee_id'] = (int) $employee['id'];
        $data['is_half_day'] = isset($data['is_half_day']) ? 1 : 0;

        try {
            $id = $this->leaveService->submitRequest($data, (int) $this->user()['id']);
            flash('success', 'Leave request submitted successfully.');
            $this->redirect('/employee/leave/' . $id);
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
            $this->redirect('/employee/leave/create');
        }
    }

    public function show(int $id): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $request = $this->leaveRequests->findDetailed($id);
        if (!$request || (int) $request['employee_id'] !== (int) $employee['id']) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }

        $this->view('employee.leave.show', [
            'title' => 'Leave Request #' . $id,
            'leaveRequest' => $request,
            'approvals' => $this->leaveRequests->getApprovals($id),
        ], 'layouts/employee');
    }

    public function cancel(int $id): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $request = $this->leaveRequests->find($id);
        if (!$request || (int) $request['employee_id'] !== (int) $employee['id']) {
            throw new \App\Exceptions\HttpException('Leave request not found.', 404);
        }

        try {
            $this->leaveService->cancel($id, (int) $this->user()['id'], $this->request->input('reason'));
            flash('success', 'Leave request cancelled.');
        } catch (RuntimeException $e) {
            flash('error', $e->getMessage());
        }

        $this->redirect('/employee/leave/' . $id);
    }
}
