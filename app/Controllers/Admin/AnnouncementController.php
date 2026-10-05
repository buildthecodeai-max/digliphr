<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;
use App\Services\AuditService;

class AnnouncementController extends Controller
{
    public function index(): void
    {
        $this->authorize('announcements.view');
        $scope = $this->tenant->sql('company_id', 'announcement_company');
        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM announcements WHERE deleted_at IS NULL AND (' . $scope['sql'] . ')
             ORDER BY created_at DESC LIMIT 200',
            $scope['params']
        );
        $this->view('admin/announcements/index', ['title' => 'Announcements', 'rows' => $rows]);
    }

    public function create(): void
    {
        $this->authorize('announcements.manage');
        $this->view('admin/announcements/form', [
            'title' => 'New Announcement',
            'announcement' => null,
            'companies' => $this->companies(),
        ]);
    }

    public function store(): void
    {
        $this->authorize('announcements.manage');
        $data = $this->validate([
            'title' => 'required|min:3|max:200',
            'body' => 'required|min:5',
            'publish_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $id = Database::getInstance()->insert('announcements', [
            'company_id' => $this->resolveCompanyId((int) ($data['company_id'] ?? 0)),
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $this->request->input('audience', 'all'),
            'publish_at' => $data['publish_at'] ?? date('Y-m-d H:i:s'),
            'expires_at' => $data['expires_at'] ?: null,
            'is_published' => $this->request->input('is_published') ? 1 : 0,
            'created_by' => $this->user()['id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        (new AuditService())->log('create', 'announcements', $id, null, $data);
        flash('success', 'Announcement created.');
        $this->redirect('/admin/announcements');
    }

    public function edit(int $id): void
    {
        $this->authorize('announcements.manage');
        $row = Database::getInstance()->fetch(
            'SELECT * FROM announcements WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$row) {
            flash('error', 'Announcement not found.');
            $this->redirect('/admin/announcements');
            return;
        }
        $this->tenant->assertCompany((int) $row['company_id']);

        $this->view('admin/announcements/form', [
            'title' => 'Edit Announcement',
            'announcement' => $row,
            'companies' => $this->companies(),
        ]);
    }

    public function update(int $id): void
    {
        $this->authorize('announcements.manage');
        $row = Database::getInstance()->fetch(
            'SELECT * FROM announcements WHERE id = :id AND deleted_at IS NULL',
            ['id' => $id]
        );
        if (!$row) {
            flash('error', 'Announcement not found.');
            $this->redirect('/admin/announcements');
            return;
        }
        $this->tenant->assertCompany((int) $row['company_id']);

        $data = $this->validate([
            'title' => 'required|min:3|max:200',
            'body' => 'required|min:5',
            'publish_at' => 'nullable|date',
            'expires_at' => 'nullable|date',
            'company_id' => 'nullable|exists:companies,id',
        ]);

        $payload = [
            'company_id' => $this->resolveCompanyId((int) ($data['company_id'] ?? $row['company_id'])),
            'title' => $data['title'],
            'body' => $data['body'],
            'audience' => $this->request->input('audience', 'all'),
            'publish_at' => $data['publish_at'] ?? $row['publish_at'],
            'expires_at' => $data['expires_at'] ?: null,
            'is_published' => $this->request->input('is_published') ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        Database::getInstance()->update('announcements', $payload, 'id = :id', ['id' => $id]);
        (new AuditService())->log('update', 'announcements', $id, $row, $payload);
        flash('success', 'Announcement updated.');
        $this->redirect('/admin/announcements');
    }

    public function destroy(int $id): void
    {
        $this->authorize('announcements.manage');
        $row = Database::getInstance()->fetch('SELECT * FROM announcements WHERE id = :id', ['id' => $id]);
        if (!$row) {
            throw new \App\Exceptions\HttpException('Announcement not found.', 404);
        }
        $this->tenant->assertCompany((int) $row['company_id']);
        Database::getInstance()->update('announcements', [
            'deleted_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
        (new AuditService())->log('delete', 'announcements', $id, $row);
        flash('success', 'Announcement deleted.');
        $this->redirect('/admin/announcements');
    }

    private function companies(): array
    {
        return $this->tenant->companies(true);
    }

    private function resolveCompanyId(int $companyId): int
    {
        $resolved = $this->tenant->resolveCompanyId($companyId > 0 ? $companyId : null);
        if ($resolved !== null) {
            return $resolved;
        }
        $companies = $this->tenant->companies(true);
        if ($companies === []) {
            throw new \App\Exceptions\HttpException('No active company is available.', 422);
        }
        return (int) $companies[0]['id'];
    }
}
