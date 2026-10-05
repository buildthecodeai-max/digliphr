<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class OvertimeController extends Controller
{
    public function index(): void
    {
        $this->authorize('overtime.view');
        $scope = $this->tenant->sql('o.company_id', 'overtime_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT o.*, CONCAT(e.first_name, " ", e.last_name) AS employee_name, e.employee_code
             FROM overtime_requests o
             INNER JOIN employees e ON e.id = o.employee_id
             WHERE o.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY o.overtime_date DESC, o.id DESC
             LIMIT 300',
            $scope['params']
        );
        $this->view('admin/overtime/index', ['title' => 'Overtime Requests', 'rows' => $rows]);
    }

    public function approve(int $id): void
    {
        $this->authorize('overtime.approve');
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM overtime_requests WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$row) {
            flash('error', 'Overtime request not found.');
            $this->redirect('/admin/overtime');
        }
        $this->tenant->assertCompany((int) $row['company_id']);

        $db->update('overtime_requests', [
            'status' => 'approved',
            'approved_minutes' => $row['approved_minutes'] ?? $row['requested_minutes'],
            'reviewed_by' => $this->user()['id'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $this->request->input('notes'),
        ], 'id = :id', ['id' => $id]);

        (new AuditService())->log('approve', 'overtime', $id);
        flash('success', 'Overtime approved.');
        $this->redirect('/admin/overtime');
    }

    public function destroy(int $id): void
    {
        $this->authorize('overtime.approve');
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM overtime_requests WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$row) {
            flash('error', 'Overtime request not found.');
            $this->redirect('/admin/overtime');
        }
        $this->tenant->assertCompany((int) $row['company_id']);
        $db->update('overtime_requests', ['deleted_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => $id]);
        (new AuditService())->log('delete', 'overtime', $id, $row, null);
        flash('success', 'Overtime request deleted.');
        $this->redirect('/admin/overtime');
    }

    public function reject(int $id): void
    {
        $this->authorize('overtime.approve');
        $db = Database::getInstance();
        $row = $db->fetch('SELECT * FROM overtime_requests WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
        if (!$row) {
            throw new \App\Exceptions\HttpException('Overtime request not found.', 404);
        }
        $this->tenant->assertCompany((int) $row['company_id']);
        $db->update('overtime_requests', [
            'status' => 'rejected',
            'reviewed_by' => $this->user()['id'] ?? null,
            'reviewed_at' => date('Y-m-d H:i:s'),
            'review_notes' => $this->request->input('reason') ?: 'Rejected by reviewer',
        ], 'id = :id', ['id' => $id]);

        (new AuditService())->log('reject', 'overtime', $id);
        flash('success', 'Overtime rejected.');
        $this->redirect('/admin/overtime');
    }
}
