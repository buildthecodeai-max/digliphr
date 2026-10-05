<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

class AuditController extends Controller
{
    public function index(): void
    {
        $this->authorize('audit.view');
        $db = Database::getInstance();
        $page = max(1, (int) $this->request->input('page', 1));
        $perPage = 50;
        $offset = ($page - 1) * $perPage;
        $module = $this->request->input('module');
        $action = $this->request->input('action');

        $clauses = ['1=1'];
        $params = [];
        if (!$this->tenant->isGlobal()) {
            $scope = $this->tenant->sql('a.company_id', 'audit_company');
            $clauses[] = '(' . $scope['sql'] . ')';
            $params = array_merge($params, $scope['params']);
        }
        if ($module) {
            $clauses[] = 'a.table_name LIKE :module';
            $params['module'] = '%' . $module . '%';
        }
        if ($action) {
            $clauses[] = 'a.action = :action';
            $params['action'] = $action;
        }
        $where = implode(' AND ', $clauses);

        $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM audit_logs a WHERE {$where}", $params);
        $rows = $db->fetchAll(
            "SELECT a.*, a.table_name AS module, u.name AS user_name, u.email AS user_email
             FROM audit_logs a
             LEFT JOIN users u ON u.id = a.user_id
             WHERE {$where}
             ORDER BY a.id DESC
             LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $this->view('admin/audit/index', [
            'title' => 'Audit Logs',
            'rows' => $rows,
            'paginator' => [
                'data' => $rows,
                'total' => $total,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => max(1, (int) ceil($total / $perPage)),
            ],
            'filters' => ['module' => $module, 'action' => $action],
        ]);
    }
}
