<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class DocumentController extends Controller
{
    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM employee_documents
             WHERE employee_id = :eid AND deleted_at IS NULL
             ORDER BY created_at DESC',
            ['eid' => $employee['id']]
        );

        $this->view('employee/documents/index', [
            'title' => 'My Documents',
            'rows' => $rows,
        ], 'layouts/employee');
    }
}
