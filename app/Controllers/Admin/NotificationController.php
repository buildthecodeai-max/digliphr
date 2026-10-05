<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

class NotificationController extends Controller
{
    public function index(): void
    {
        $this->authorize('notifications.view');
        $userId = $this->user()['id'] ?? 0;
        $rows = Database::getInstance()->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 100',
            ['uid' => $userId]
        );
        $this->view('admin/announcements/notifications', [
            'title' => 'Notifications',
            'rows' => $rows,
        ]);
    }
}
