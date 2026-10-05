<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Session;

class AuditService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function log(
        string $action,
        string $module,
        ?int $recordId = null,
        mixed $previous = null,
        mixed $new = null,
        ?int $userId = null
    ): void {
        try {
            $actorId = $userId ?? Session::get('user_id');
            $companyId = null;
            if (is_array($new) && !empty($new['company_id'])) {
                $companyId = (int) $new['company_id'];
            } elseif (is_array($previous) && !empty($previous['company_id'])) {
                $companyId = (int) $previous['company_id'];
            } elseif ($actorId) {
                $companyId = $this->db->fetchColumn(
                    'SELECT company_id FROM employees
                     WHERE user_id = :uid AND company_id IS NOT NULL AND deleted_at IS NULL LIMIT 1',
                    ['uid' => $actorId]
                ) ?: $this->db->fetchColumn(
                    'SELECT company_id FROM user_roles
                     WHERE user_id = :uid AND company_id IS NOT NULL LIMIT 1',
                    ['uid' => $actorId]
                );
                $companyId = $companyId ? (int) $companyId : null;
            }
            $mappedAction = match (true) {
                in_array($action, ['create', 'update', 'delete', 'restore', 'login', 'logout', 'export', 'import'], true) => $action,
                default => 'other',
            };

            $this->db->insert('audit_logs', [
                'company_id' => $companyId,
                'user_id' => $actorId,
                'table_name' => $module,
                'record_id' => $recordId,
                'action' => $mappedAction,
                'old_values' => $previous !== null ? json_encode($previous, JSON_UNESCAPED_UNICODE) : null,
                'new_values' => $new !== null ? json_encode($new, JSON_UNESCAPED_UNICODE) : null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'url' => substr($_SERVER['REQUEST_URI'] ?? '', 0, 500),
                'method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Never break the main flow due to audit logging
        }
    }

    public function activity(string $description, string $module = 'general', ?int $recordId = null): void
    {
        try {
            $this->db->insert('activity_logs', [
                'user_id' => Session::get('user_id'),
                'description' => $description,
                'log_name' => $module,
                'subject_id' => $recordId,
                'event' => $module,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // ignore
        }
    }
}
