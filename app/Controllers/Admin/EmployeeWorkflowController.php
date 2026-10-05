<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\EmployeeWorkflowService;

class EmployeeWorkflowController extends Controller
{
    public function show(int $employeeId): void
    {
        $this->authorize('employees.view');
        $employee = $this->tenant->employee($employeeId);
        $detail = (new \App\Models\Employee())->findDetailed($employeeId);
        $this->view('admin/employees/workflows', ['title' => 'Employee Workflows', 'employee' => $detail, 'workflows' => (new EmployeeWorkflowService())->forEmployee($employeeId)]);
    }

    public function start(int $employeeId): void
    {
        $this->authorize('employees.update');
        $employee = $this->tenant->employee($employeeId);
        $type = (string) $this->request->input('workflow_type');
        $id = (new EmployeeWorkflowService())->start((int) $employee['company_id'], $employeeId, $type, $this->request->input('target_date'), (int) $this->auth->id());
        flash('success', ucfirst($type) . ' workflow started.');
        $this->redirect('/admin/employees/' . $employeeId . '/workflows#workflow-' . $id);
    }

    public function task(int $employeeId, int $taskId): void
    {
        $this->authorize('employees.update');
        $employee = $this->tenant->employee($employeeId);
        (new EmployeeWorkflowService())->setTaskStatus($taskId, (int) $employee['company_id'], (string) $this->request->input('status', 'completed'), (int) $this->auth->id());
        flash('success', 'Workflow task updated.');
        $this->redirect('/admin/employees/' . $employeeId . '/workflows');
    }
}
