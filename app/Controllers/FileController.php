<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Exceptions\HttpException;
use App\Services\AuditService;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\ChannelService;
use App\Services\Chat\ConversationService;

class FileController extends Controller
{
    public function serve(string $type, int $id): void
    {
        $db = Database::getInstance();
        $user = $this->user();
        if (!$user) {
            throw new HttpException('Unauthenticated.', 401);
        }

        $path = null;
        $mime = 'application/octet-stream';
        $filename = 'file';

        switch ($type) {
            case 'attendance-image':
                if (!$this->auth->can('attendance.images')) {
                    throw new HttpException('Forbidden.', 403);
                }
                $row = $db->fetch(
                    'SELECT ai.*, a.company_id
                     FROM attendance_images ai
                     INNER JOIN attendance a ON a.id = ai.attendance_id
                     WHERE ai.id = :id AND ai.deleted_at IS NULL',
                    ['id' => $id]
                );
                if (!$row) {
                    throw new HttpException('File not found.', 404);
                }
                $this->tenant->assertCompany((int) $row['company_id']);
                $path = config('app.paths.attendance_images') . '/' . ltrim($row['stored_name'] ?? $row['filename'] ?? '', '/');
                if (!is_file($path) && !empty($row['path'])) {
                    $path = $row['path'];
                    if (!str_starts_with($path, '/')) {
                        $path = config('app.paths.storage') . '/' . ltrim($path, '/');
                    }
                }
                $mime = $row['mime_type'] ?? 'image/jpeg';
                $filename = $row['original_name'] ?? $row['filename'] ?? 'attendance.jpg';
                (new AuditService())->log('view', 'attendance_images', (int) $id);
                break;

            case 'document':
                $row = $db->fetch('SELECT * FROM employee_documents WHERE id = :id AND deleted_at IS NULL', ['id' => $id]);
                if (!$row) {
                    throw new HttpException('File not found.', 404);
                }
                $employee = $this->employee();
                $canManage = $this->auth->can('documents.view') || $this->auth->can('documents.manage');
                $isOwner = $employee && (int) $employee['id'] === (int) $row['employee_id'];
                if (!$canManage && !$isOwner) {
                    throw new HttpException('Forbidden.', 403);
                }
                if ($canManage && !$isOwner) {
                    $this->tenant->employee((int) $row['employee_id']);
                }
                $path = $row['path'] ?? '';
                if ($path && !str_starts_with($path, '/')) {
                    $path = config('app.paths.employee_documents') . '/' . ltrim($path, '/');
                } elseif (!$path) {
                    $path = config('app.paths.employee_documents') . '/' . ltrim($row['filename'] ?? '', '/');
                }
                $mime = $row['mime_type'] ?? 'application/octet-stream';
                $filename = $row['original_filename'] ?? $row['title'] ?? 'document';
                (new AuditService())->log('view', 'employee_documents', (int) $id);
                break;

            case 'payslip':
                $row = $db->fetch('SELECT * FROM payslips WHERE id = :id', ['id' => $id]);
                if (!$row) {
                    throw new HttpException('File not found.', 404);
                }
                $employee = $this->employee();
                $canView = $this->auth->can('payslips.view');
                $isOwner = $employee && (int) $employee['id'] === (int) ($row['employee_id'] ?? 0);
                if (!$canView && !$isOwner) {
                    throw new HttpException('Forbidden.', 403);
                }
                if ($canView && !$isOwner) {
                    $this->tenant->employee((int) ($row['employee_id'] ?? 0));
                }
                $path = $row['file_path'] ?? (config('app.paths.payslips') . '/' . ($row['filename'] ?? ''));
                $mime = 'application/pdf';
                $filename = $row['filename'] ?? 'payslip.pdf';
                break;

            case 'chat-attachment':
                if (!$this->auth->can('chat.download_file') && !$this->auth->can('chat.access')) {
                    throw new HttpException('Forbidden.', 403);
                }
                $attachments = new AttachmentService();
                $row = $attachments->find($id);
                if (!$row) {
                    throw new HttpException('File not found.', 404);
                }
                $message = $db->fetch('SELECT * FROM chat_messages WHERE id = :id', ['id' => $row['message_id']]);
                $uid = (int) ($user['id'] ?? 0);
                $allowed = false;
                if ($message) {
                    if (!empty($message['channel_id'])) {
                        $allowed = (new ChannelService())->ensureMembership((int) $message['channel_id'], $uid);
                    } elseif (!empty($message['conversation_id'])) {
                        $allowed = (new ConversationService())->ensureMembership((int) $message['conversation_id'], $uid);
                    }
                }
                if (!$allowed && (int) $row['uploaded_by'] !== $uid) {
                    throw new HttpException('Forbidden.', 403);
                }
                $path = $attachments->absolutePath($row);
                $mime = $row['mime_type'] ?? 'application/octet-stream';
                $filename = $row['original_filename'] ?? 'attachment';
                $attachments->logDownload($id, $uid);
                (new AuditService())->log('view', 'chat_attachments', (int) $id);
                break;

            case 'monitoring-screenshot':
                $row = $db->fetch(
                    'SELECT * FROM monitoring_screenshots WHERE id = :id AND deleted_at IS NULL',
                    ['id' => $id]
                );
                if (!$row || ($row['upload_status'] ?? '') === 'deleted') {
                    throw new HttpException('File not found.', 404);
                }
                $authz = new \App\Services\Monitoring\MonitoringAuthorizationService($this->auth);
                if (!$authz->canAccessScreenshot($row)) {
                    throw new HttpException('Forbidden.', 403);
                }
                $wantThumb = $this->request->input('thumb') === '1' || $this->request->input('thumbnail') === '1';
                $path = (new \App\Services\Monitoring\MonitoringScreenshotService())->absolutePath($row, $wantThumb);
                if (!$path || !is_file($path)) {
                    $path = (new \App\Services\Monitoring\MonitoringScreenshotService())->absolutePath($row, false);
                }
                if (!$path || !is_file($path)) {
                    throw new HttpException('File not found on disk.', 404);
                }
                $mime = $row['mime_type'] ?? 'image/jpeg';
                $filename = 'screenshot-' . ($row['capture_id'] ?? $id) . '.jpg';
                (new \App\Services\Monitoring\MonitoringScreenshotService())->markViewed((int) $id);
                (new AuditService())->log('view', 'monitoring_screenshots', (int) $id);
                break;

            default:
                throw new HttpException('Invalid file type.', 400);
        }

        if (!$path || !is_file($path)) {
            throw new HttpException('File not found on disk.', 404);
        }

        if ($this->request->input('download') === '1') {
            $this->response->download($path, $filename, $mime);
        }

        $this->response->file($path, $mime);
    }
}
