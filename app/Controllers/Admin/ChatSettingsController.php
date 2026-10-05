<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Chat\AutoChannelService;
use App\Services\Chat\ChatSettingsService;
use App\Services\Chat\ChatSupport;

class ChatSettingsController extends Controller
{
    public function index(): void
    {
        $this->authorize('chat.manage_settings');
        $companyId = ChatSupport::resolveCompanyId($this->user(), $this->employee());
        $settings = (new ChatSettingsService())->all($companyId);

        $this->view('admin.chat.settings', [
            'title' => 'Chat Settings',
            'settings' => $settings,
            'companyId' => $companyId,
        ]);
    }

    public function save(): void
    {
        $this->authorize('chat.manage_settings');
        $companyId = ChatSupport::resolveCompanyId($this->user(), $this->employee());
        (new ChatSettingsService())->save($companyId, [
            'polling_interval_seconds' => max(3, min(10, (int) $this->request->input('polling_interval_seconds', 5))),
            'max_upload_bytes' => max(1048576, min(20971520, (int) $this->request->input('max_upload_bytes', 10485760))),
            'allow_mass_mentions' => $this->request->input('allow_mass_mentions') ? '1' : '0',
        ]);
        flash('success', 'Chat settings saved.');
        $this->redirect('/admin/chat/settings');
    }

    public function rebuildChannels(): void
    {
        $this->authorize('chat.manage_settings');
        $companyId = ChatSupport::resolveCompanyId($this->user(), $this->employee());
        (new AutoChannelService())->bootstrapCompany($companyId, (int) ($this->user()['id'] ?? 0), true);
        flash('success', 'System channels rebuilt and memberships synced.');
        $this->redirect('/admin/chat/settings');
    }
}
