<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class AssetController extends Controller
{
    public function index(): void
    {
        $employee = $this->employee();
        if (!$employee) {
            throw new \App\Exceptions\HttpException('Employee profile not found.', 404);
        }

        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM employee_assets WHERE employee_id = :eid AND deleted_at IS NULL ORDER BY assigned_date DESC',
            ['eid' => $employee['id']]
        );

        $this->view('employee/assets/index', [
            'title' => 'My Assets',
            'rows' => $rows,
        ], 'layouts/employee');
    }
}
