<?php

declare(strict_types=1);

namespace App\Controllers\Employee;

use App\Core\Controller;
use App\Core\Database;

class NotificationController extends Controller
{
    public function index(): void
    {
        $userId = $this->user()['id'] ?? 0;
        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 100',
            ['uid' => $userId]
        );

        Database::getInstance()->query(
            'UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = :uid AND is_read = 0',
            ['uid' => $userId]
        );

        $this->view('employee/profile/notifications', [
            'title' => 'Notifications',
            'rows' => $rows,
        ], 'layouts/employee');
    }
}
