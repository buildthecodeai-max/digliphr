<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;

final class EmployeeWorkflowService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function forEmployee(int $employeeId): array
    {
        $workflows = $this->db->fetchAll('SELECT * FROM employee_workflows WHERE employee_id = :id ORDER BY id DESC', ['id' => $employeeId]);
        foreach ($workflows as &$workflow) {
            $workflow['tasks'] = $this->db->fetchAll('SELECT * FROM employee_workflow_tasks WHERE workflow_id = :id ORDER BY sort_order, id', ['id' => $workflow['id']]);
        }
        return $workflows;
    }

    public function start(int $companyId, int $employeeId, string $type, ?string $targetDate, int $userId): int
    {
        if (!in_array($type, ['onboarding', 'offboarding'], true)) throw new HttpException('Invalid workflow type.', 422);
        $active = $this->db->fetch('SELECT id FROM employee_workflows WHERE employee_id = :eid AND workflow_type = :type AND status IN ("pending","in_progress")', ['eid' => $employeeId, 'type' => $type]);
        if ($active) return (int) $active['id'];
        $id = $this->db->insert('employee_workflows', ['company_id' => $companyId, 'employee_id' => $employeeId, 'workflow_type' => $type, 'status' => 'in_progress', 'target_date' => $targetDate ?: null, 'started_by' => $userId]);
        $tasks = $type === 'onboarding' ? $this->onboardingTasks() : $this->offboardingTasks();
        foreach ($tasks as $index => [$category, $title, $description]) {
            $this->db->insert('employee_workflow_tasks', ['workflow_id' => $id, 'category' => $category, 'title' => $title, 'description' => $description, 'due_date' => $targetDate ?: null, 'sort_order' => $index]);
        }
        return $id;
    }

    public function setTaskStatus(int $taskId, int $companyId, string $status, int $userId): void
    {
        if (!in_array($status, ['pending','completed','skipped'], true)) throw new HttpException('Invalid task status.', 422);
        $task = $this->db->fetch('SELECT t.*, w.company_id, w.id workflow_id FROM employee_workflow_tasks t INNER JOIN employee_workflows w ON w.id=t.workflow_id WHERE t.id=:id', ['id' => $taskId]);
        if (!$task || (int) $task['company_id'] !== $companyId) throw new HttpException('Workflow task not found.', 404);
        $this->db->update('employee_workflow_tasks', ['status' => $status, 'completed_by' => $status === 'completed' ? $userId : null, 'completed_at' => $status === 'completed' ? date('Y-m-d H:i:s') : null], 'id = :id', ['id' => $taskId]);
        $remaining = (int) $this->db->fetchColumn('SELECT COUNT(*) FROM employee_workflow_tasks WHERE workflow_id = :id AND status = "pending"', ['id' => $task['workflow_id']]);
        $this->db->update('employee_workflows', ['status' => $remaining === 0 ? 'completed' : 'in_progress', 'completed_by' => $remaining === 0 ? $userId : null, 'completed_at' => $remaining === 0 ? date('Y-m-d H:i:s') : null], 'id = :id', ['id' => $task['workflow_id']]);
    }

    private function onboardingTasks(): array
    {
        return [['people','Verify personal and employment details','Confirm names, contacts, joining date, department, designation, manager, and shift.'],['access','Create login and application access','Issue the employee account and required role access.'],['payroll','Collect bank and tax information','Verify salary, payment method, bank information, and payroll eligibility.'],['policy','Share policies and collect acknowledgements','Provide handbook, leave, attendance, and monitoring policies.'],['equipment','Assign equipment and workplace','Issue devices, access cards, and a workstation where applicable.'],['manager','Schedule manager introduction','Confirm first-week goals and reporting cadence.']];
    }

    private function offboardingTasks(): array
    {
        return [['people','Confirm last working date','Record resignation or termination details and final date.'],['handover','Complete work handover','Transfer projects, documents, and responsibilities.'],['access','Disable accounts and revoke access','Schedule application and physical access removal.'],['equipment','Recover company assets','Collect devices, cards, keys, and other assigned assets.'],['payroll','Prepare final settlement','Review unpaid salary, leave encashment, loans, and deductions.'],['exit','Complete exit interview and documents','Capture feedback and issue experience or relieving letters.']];
    }
}
