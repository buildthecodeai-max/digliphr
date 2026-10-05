<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
use App\Services\AuditService;
use App\Services\EmploymentAgreementService;
use App\Services\FileUploadService;

class DocumentController extends Controller
{
    public function index(): void
    {
        $this->authorize('documents.view');
        $scope = $this->tenant->sql('e.company_id', 'document_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT d.*, CONCAT(e.first_name, " ", e.last_name) AS employee_name, e.employee_code
             FROM employee_documents d
             INNER JOIN employees e ON e.id = d.employee_id
             WHERE d.deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY d.created_at DESC
             LIMIT 300',
            $scope['params']
        );

        $employeeScope = $this->tenant->sql('company_id', 'document_employee_company');
        $employees = Database::getInstance()->fetchAll(
            'SELECT id, first_name, last_name, employee_code FROM employees
             WHERE deleted_at IS NULL AND (' . $employeeScope['sql'] . ') ORDER BY first_name',
            $employeeScope['params']
        );

        $this->view('admin/documents/index', [
            'title' => 'Employee Documents',
            'rows' => $rows,
            'employees' => $employees,
            'csrfToken' => Session::csrfToken(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('documents.manage');

        $employeeId = (int) $this->request->input('employee_id');
        $this->tenant->employee($employeeId);
        $title = trim((string) $this->request->input('title'));
        $type = trim((string) $this->request->input('document_type', 'other'));

        if ($employeeId < 1 || $title === '') {
            flash('error', 'Employee and title are required.');
            $this->redirect('/admin/documents');
        }

        $file = $this->request->file('document');
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('error', 'Please upload a document file.');
            $this->redirect('/admin/documents');
        }

        $uploader = new FileUploadService();
        $saved = $uploader->storeUpload($file, config('app.paths.employee_documents'), [
            'allowed_mimes' => [
                'application/pdf',
                'image/jpeg',
                'image/png',
                'text/plain',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            ],
            'max_size' => (int) config('app.upload_max_size', 5242880),
        ]);

        if (!($saved['success'] ?? false)) {
            flash('error', $saved['message'] ?? 'Upload failed.');
            $this->redirect('/admin/documents');
        }

        $id = Database::getInstance()->insert('employee_documents', [
            'employee_id' => $employeeId,
            'document_type' => $type,
            'title' => $title,
            'description' => $this->request->input('description'),
            'filename' => $saved['filename'],
            'original_filename' => $saved['original_filename'] ?? $file['name'],
            'path' => $saved['filename'],
            'mime_type' => $saved['mime_type'] ?? null,
            'file_size' => $saved['size'] ?? null,
            'issue_date' => $this->request->input('issue_date') ?: null,
            'expiry_date' => $this->request->input('expiry_date') ?: null,
            'uploaded_by' => $this->user()['id'] ?? null,
        ]);

        (new AuditService())->log('create', 'employee_documents', $id, null, ['title' => $title]);
        flash('success', 'Document uploaded successfully.');
        $this->redirect('/admin/documents');
    }

    public function destroy(int $id): void
    {
        $this->authorize('documents.manage');
        $db = Database::getInstance();
        $document = $db->fetch(
            'SELECT employee_id, document_type, title FROM employee_documents WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$document) {
            throw new \App\Exceptions\HttpException('Document not found.', 404);
        }
        $emp = $db->fetch(
            'SELECT id, company_id FROM employees WHERE id = :id LIMIT 1',
            ['id' => $document['employee_id']]
        );
        if (!$emp || !$this->tenant->canAccessCompany((int) $emp['company_id'])) {
            throw new \App\Exceptions\HttpException('Document not found.', 404);
        }

        $db->beginTransaction();
        try {
            $db->update('employee_documents', [
                'deleted_at' => date('Y-m-d H:i:s'),
            ], 'id = :id AND deleted_at IS NULL', ['id' => $id]);

            $agreementReopened = (new EmploymentAgreementService())->reopenForDeletedDocument($id);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            throw $e;
        }

        (new AuditService())->log('delete', 'employee_documents', $id, $document, [
            'employment_agreement_reopened' => $agreementReopened,
        ]);
        flash(
            'success',
            $agreementReopened
                ? 'Employment agreement deleted. The employee must sign it again at their next login.'
                : 'Document deleted.'
        );
        $this->redirect('/admin/documents');
    }
}
