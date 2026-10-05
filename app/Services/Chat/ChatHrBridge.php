<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;
use App\Services\NotificationService;

/**
 * Minimal HR integration points: private DM tips for leave / payslip events.
 */
final class ChatHrBridge
{
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function notifyLeaveDecision(int $employeeId, string $status, ?string $note = null): void
    {
        $employee = $this->db->fetch(
            'SELECT id, user_id, company_id, first_name, last_name FROM employees WHERE id = :id AND deleted_at IS NULL',
            ['id' => $employeeId]
        );
        if (!$employee || empty($employee['user_id'])) {
            return;
        }

        $title = 'Leave ' . $status;
        $message = 'Your leave request was ' . $status . ($note ? ': ' . $note : '.');
        (new NotificationService())->notify(
            (int) $employee['user_id'],
            $title,
            $message,
            '/chat',
            'info'
        );

        $this->postSystemDmToUser(
            (int) $employee['company_id'],
            (int) $employee['user_id'],
            $title . ' — ' . $message
        );
    }

    public function notifyPayslipReady(int $employeeId, ?string $periodLabel = null): void
    {
        $employee = $this->db->fetch(
            'SELECT id, user_id, company_id FROM employees WHERE id = :id AND deleted_at IS NULL',
            ['id' => $employeeId]
        );
        if (!$employee || empty($employee['user_id'])) {
            return;
        }

        $label = $periodLabel ? ' for ' . $periodLabel : '';
        $text = 'Your payslip' . $label . ' is ready. Open Payslips to download.';
        (new NotificationService())->notify(
            (int) $employee['user_id'],
            'Payslip ready',
            $text,
            '/employee/payslips',
            'info'
        );

        $this->postSystemDmToUser((int) $employee['company_id'], (int) $employee['user_id'], $text);
    }

    private function postSystemDmToUser(int $companyId, int $userId, string $body): void
    {
        try {
            $hrUser = $this->db->fetch(
                'SELECT u.id FROM users u
                 INNER JOIN user_roles ur ON ur.user_id = u.id
                 INNER JOIN roles r ON r.id = ur.role_id
                 WHERE r.slug IN (\'hr_manager\', \'company_admin\', \'super_admin\')
                   AND u.deleted_at IS NULL AND u.is_active = 1 AND u.id <> :uid
                 ORDER BY FIELD(r.slug, \'hr_manager\', \'company_admin\', \'super_admin\')
                 LIMIT 1',
                ['uid' => $userId]
            );
            if (!$hrUser) {
                return;
            }

            $conversations = new ConversationService();
            $result = $conversations->findOrCreateDirect($companyId, (int) $hrUser['id'], $userId);
            if (empty($result['success']) || empty($result['conversation']['id'])) {
                return;
            }

            (new MessageService())->post($companyId, (int) $hrUser['id'], $body, [
                'conversation_id' => (int) $result['conversation']['id'],
                'message_type' => 'system',
            ]);
        } catch (\Throwable) {
            // Never break HR flows because of chat bridge
        }
    }
}
