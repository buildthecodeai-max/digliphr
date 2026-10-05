<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Models\Employee;
use App\Services\AuditService;

class ProfileController extends Controller
{
    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $detailed = (new Employee())->findDetailed((int) $employee['id']);
        $this->view('employee/profile/index', [
            'title' => 'My Profile',
            'employee' => $detailed,
        ], 'layouts/employee');
    }

    public function update(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $data = $this->validate([
            'phone' => 'nullable|max:30',
            'alternate_phone' => 'nullable|max:30',
            'personal_email' => 'nullable|email|max:191',
            'current_address' => 'nullable|max:1000',
            'permanent_address' => 'nullable|max:1000',
        ]);

        $model = new Employee();
        $before = $model->find((int) $employee['id']);
        $model->update((int) $employee['id'], $data);
        (new AuditService())->log('update', 'employees', (int) $employee['id'], $before, $data);

        flash('success', 'Profile updated.');
        $this->redirect('/employee/profile');
    }
}
