<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/** Sends one in-app reminder per administrator at 14 and 7 days before a birthday. */
final class BirthdayReminderService
{
    private const REMINDER_DAYS = [14, 7];

    private Database $db;
    private NotificationService $notifications;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->notifications = new NotificationService();
    }

    public function sendDueReminders(): int
    {
        $sent = 0;

        foreach (self::REMINDER_DAYS as $daysUntil) {
            $employees = $this->db->fetchAll(
                'SELECT id, company_id, first_name, last_name, date_of_birth
                 FROM employees
                 WHERE date_of_birth IS NOT NULL
                   AND employment_status IN ("active", "probation")
                   AND deleted_at IS NULL
                   AND DATE_FORMAT(date_of_birth, "%m-%d") = DATE_FORMAT(DATE_ADD(CURDATE(), INTERVAL ' . (int) $daysUntil . ' DAY), "%m-%d")'
            );

            foreach ($employees as $employee) {
                $recipients = $this->administratorIds((int) $employee['company_id']);
                $name = trim((string) $employee['first_name'] . ' ' . (string) $employee['last_name']);
                $birthday = date('F j', strtotime((string) $employee['date_of_birth']));
                $type = 'birthday_reminder_' . $daysUntil . 'd';
                $actionUrl = '/admin/employees/' . (int) $employee['id'];

                foreach ($recipients as $userId) {
                    if ($this->alreadySentToday($userId, $type, $actionUrl)) {
                        continue;
                    }
                    $this->notifications->notify(
                        $userId,
                        'Upcoming employee birthday',
                        sprintf('%s has a birthday on %s (%d days away).', $name, $birthday, $daysUntil),
                        $actionUrl,
                        $type
                    );
                    $sent++;
                }
            }
        }

        return $sent;
    }

    /** @return list<int> */
    private function administratorIds(int $companyId): array
    {
        $rows = $this->db->fetchAll(
            'SELECT DISTINCT u.id
             FROM users u
             LEFT JOIN user_roles ur ON ur.user_id = u.id
             LEFT JOIN roles r ON r.id = ur.role_id
             WHERE u.is_active = 1 AND u.deleted_at IS NULL
               AND (
                    u.is_super_admin = 1
                    OR (
                        r.slug IN ("super_admin", "company_admin", "hr_manager")
                        AND (ur.company_id = :company_id OR ur.company_id IS NULL)
                    )
               )',
            ['company_id' => $companyId]
        );

        return array_map('intval', array_column($rows, 'id'));
    }

    private function alreadySentToday(int $userId, string $type, string $actionUrl): bool
    {
        return (bool) $this->db->fetchColumn(
            'SELECT COUNT(*) FROM notifications
             WHERE user_id = :user_id AND type = :type AND action_url = :action_url
               AND created_at >= CURDATE()',
            ['user_id' => $userId, 'type' => $type, 'action_url' => $actionUrl]
        );
    }
}
