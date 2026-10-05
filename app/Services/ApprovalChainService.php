<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Exceptions\HttpException;

final class ApprovalChainService
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function all(int $companyId): array
    {
        $chains = $this->db->fetchAll('SELECT * FROM approval_chains WHERE company_id=:cid ORDER BY module,name', ['cid' => $companyId]);
        foreach ($chains as &$chain) $chain['steps'] = $this->db->fetchAll('SELECT * FROM approval_chain_steps WHERE approval_chain_id=:id ORDER BY step_order', ['id' => $chain['id']]);
        return $chains;
    }

    public function save(int $companyId, ?int $id, array $data, int $userId): int
    {
        if (!in_array($data['module'] ?? '', ['leave','attendance','overtime','loan','payroll'], true)) throw new HttpException('Invalid approval module.', 422);
        $payload = ['company_id' => $companyId, 'module' => $data['module'], 'name' => trim((string) ($data['name'] ?? '')), 'is_active' => !empty($data['is_active']) ? 1 : 0, 'escalation_hours' => max(1, (int) ($data['escalation_hours'] ?? 24))];
        if ($payload['name'] === '') throw new HttpException('Approval chain name is required.', 422);
        if ($id) {
            $row = $this->db->fetch('SELECT id FROM approval_chains WHERE id=:id AND company_id=:cid', ['id' => $id, 'cid' => $companyId]);
            if (!$row) throw new HttpException('Approval chain not found.', 404);
            $this->db->update('approval_chains', $payload, 'id=:id', ['id' => $id]);
        } else {
            $id = $this->db->insert('approval_chains', $payload + ['created_by' => $userId]);
        }
        $this->db->delete('approval_chain_steps', 'approval_chain_id=:id', ['id' => $id]);
        $labels = (array) ($data['step_label'] ?? []);
        $types = (array) ($data['approver_type'] ?? []);
        $roles = (array) ($data['role_slug'] ?? []);
        $users = (array) ($data['user_id'] ?? []);
        $reminders = (array) ($data['reminder_hours'] ?? []);
        foreach ($labels as $index => $label) {
            $label = trim((string) $label);
            if ($label === '') continue;
            $type = in_array($types[$index] ?? '', ['role','manager','user'], true) ? $types[$index] : 'role';
            $this->db->insert('approval_chain_steps', ['approval_chain_id' => $id, 'step_order' => $index + 1, 'approver_type' => $type, 'role_slug' => $type === 'role' ? ($roles[$index] ?: null) : null, 'user_id' => $type === 'user' ? ((int) ($users[$index] ?? 0) ?: null) : null, 'label' => $label, 'reminder_hours' => max(1, (int) ($reminders[$index] ?? 24))]);
        }
        return $id;
    }

    public function delete(int $id, int $companyId): void
    {
        $this->db->delete('approval_chains', 'id=:id AND company_id=:cid', ['id' => $id, 'cid' => $companyId]);
    }

    public function sendReminders(int $companyId, array $items): int
    {
        $sent = 0; $notifications = new NotificationService();
        foreach ($items as $item) {
            $chain = $this->db->fetch('SELECT * FROM approval_chains WHERE company_id=:cid AND module=:module AND is_active=1 ORDER BY id LIMIT 1', ['cid' => $companyId, 'module' => $item['module']]);
            if (!$chain || $item['age_hours'] < (int) $chain['escalation_hours']) continue;
            $step = $this->db->fetch('SELECT * FROM approval_chain_steps WHERE approval_chain_id=:id ORDER BY step_order LIMIT 1', ['id' => $chain['id']]);
            if (!$step) continue;
            $recipients = $this->recipients($companyId, $step, $item);
            foreach ($recipients as $uid) {
                $recent = $this->db->fetchColumn('SELECT COUNT(*) FROM approval_reminders WHERE company_id=:cid AND module=:module AND record_id=:rid AND recipient_user_id=:uid AND reminded_at > DATE_SUB(NOW(), INTERVAL 20 HOUR)', ['cid' => $companyId, 'module' => $item['module'], 'rid' => $item['record_id'], 'uid' => $uid]);
                if ($recent) continue;
                $notifications->notify($uid, 'Approval reminder: ' . $item['title'], $item['employee_name'] . ' · pending ' . $item['age_hours'] . ' hours', $item['url'], 'approval_reminder');
                $this->db->insert('approval_reminders', ['company_id' => $companyId, 'module' => $item['module'], 'record_id' => $item['record_id'], 'recipient_user_id' => $uid]);
                $sent++;
            }
        }
        return $sent;
    }

    private function recipients(int $companyId, array $step, array $item): array
    {
        if ($step['approver_type'] === 'user' && $step['user_id']) return [(int) $step['user_id']];
        if ($step['approver_type'] === 'role' && $step['role_slug']) {
            return array_map('intval', array_column($this->db->fetchAll('SELECT DISTINCT ur.user_id FROM user_roles ur INNER JOIN roles r ON r.id=ur.role_id INNER JOIN users u ON u.id=ur.user_id WHERE ur.company_id=:cid AND r.slug=:slug AND u.is_active=1 AND u.deleted_at IS NULL', ['cid' => $companyId, 'slug' => $step['role_slug']]), 'user_id'));
        }
        $employee = !empty($item['employee_id']) ? $this->db->fetch('SELECT m.user_id FROM employees e INNER JOIN employees m ON m.id=e.reporting_manager_id WHERE e.company_id=:cid AND e.id=:eid LIMIT 1', ['cid' => $companyId, 'eid' => $item['employee_id']]) : null;
        return !empty($employee['user_id']) ? [(int) $employee['user_id']] : [];
    }
}
