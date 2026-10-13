<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Database;

class NotificationController extends Controller
{
    public function index(): void
    {
        $userId = $this->user()['id'] ?? 0;
        $db = Database::getInstance();
        $rows = $db->fetchAll(
            'SELECT * FROM notifications WHERE user_id = :uid ORDER BY created_at DESC LIMIT 300',
            ['uid' => $userId]
        );
        $this->view('admin/announcements/notifications', [
            'title' => 'Notifications',
            'rows' => $rows,
        ]);
    }
}
