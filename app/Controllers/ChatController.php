<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\AutoChannelService;
use App\Services\Chat\ChannelService;
use App\Services\Chat\ChatSettingsService;
use App\Services\Chat\ChatSupport;
use App\Services\Chat\ConversationService;
use App\Services\Chat\DocumentService;
use App\Services\Chat\MessageService;
use App\Services\Chat\PresenceService;

class ChatController extends Controller
{
    private function ensureChatInstalled(): void
    {
        if (!ChatSupport::viewsInstalled()) {
            Session::flash(
                'error',
                'Team Chat UI files are missing on the server. Extract dist/upload-chat-complete-live.zip fully (views/layouts/chat.php and views/chat/*), then reload.'
            );
            $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
        }

        $missing = ChatSupport::missingTables();
        if ($missing !== []) {
            Session::flash(
                'error',
                ChatSupport::migrationHint() . ' Missing tables: ' . implode(', ', $missing) . '.'
            );
            $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
        }
    }

    private function context(): array
    {
        // Prefer a friendly flash over a blank 403 when chat was never migrated.
        $this->ensureChatInstalled();
        $this->authorize('chat.access');
        $user = $this->user();
        $employee = $this->employee();
        $companyId = ChatSupport::resolveCompanyId($user, $employee);
        try {
            $auto = new AutoChannelService();
            $auto->bootstrapCompany($companyId, (int) ($user['id'] ?? 0), false);
            // Admins without employee rows and employees share the same sync path (users.id).
            if ($user) {
                $auto->syncUser($companyId, (int) $user['id'], $employee);
            }
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                Session::flash('error', ChatSupport::migrationHint());
                $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
            }
            // Chat must remain usable even if auto-provision fails
        }
        return [
            'user' => $user,
            'employee' => $employee,
            'company_id' => $companyId,
            'user_id' => (int) ($user['id'] ?? 0),
        ];
    }

    public function index(): void
    {
        try {
            $ctx = $this->context();
            $settings = (new ChatSettingsService())->all($ctx['company_id']);

            $this->view('chat.index', [
                'title' => 'Team Chat',
                'companyId' => $ctx['company_id'],
                'userId' => $ctx['user_id'],
                'pollingInterval' => max(3, min(10, (int) ($settings['polling_interval_seconds'] ?? 5))),
                'initialChannelId' => (int) ($this->request->input('channel') ?? 0),
                'initialDmId' => (int) ($this->request->input('dm') ?? 0),
                'initialMsgId' => (int) ($this->request->input('msg') ?? 0),
                'canCreateChannel' => $this->auth->can('chat.create_channel'),
                'canManageChannel' => $this->auth->can('chat.manage_channel'),
                'canMassMention' => $this->auth->can('chat.use_mass_mentions'),
                'canUpload' => $this->auth->can('chat.upload_file'),
                'chatBoot' => true,
            ], 'layouts/chat');
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                Session::flash('error', ChatSupport::migrationHint() . ' Detail: ' . ChatSupport::formatThrowable($e));
            } else {
                Session::flash(
                    'error',
                    'Team Chat failed to open: ' . ChatSupport::formatThrowable($e)
                    . '. Re-extract dist/upload-chat-complete-live.zip and run database/migrations/2026_07_28_team_chat.sql.'
                );
            }
            $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
        }
    }

    public function overview(): void
    {
        $ctx = $this->context();
        try {
            $channels = new ChannelService();
            $conversations = new ConversationService();
            $channelList = $channels->listForUser($ctx['company_id'], $ctx['user_id']);
            $dmList = $conversations->listForUser($ctx['company_id'], $ctx['user_id']);
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                Session::flash('error', ChatSupport::migrationHint());
                $this->redirect($this->auth->isAdmin() ? '/admin/dashboard' : '/employee/dashboard');
            }
            throw $e;
        }

        $unreadChannels = 0;
        $unreadDms = 0;
        foreach ($channelList as $ch) {
            $unreadChannels += (int) ($ch['unread_count'] ?? 0);
        }
        foreach ($dmList as $dm) {
            $unreadDms += (int) ($dm['unread_count'] ?? 0);
        }

        $metrics = [
            [
                'label' => 'Channels',
                'value' => number_format(count($channelList)),
                'sub' => $unreadChannels . ' unread',
                'href' => '/chat?panel=channels',
                'tone' => 'pink',
                'icon' => 'hash',
            ],
            [
                'label' => 'Direct Messages',
                'value' => number_format(count($dmList)),
                'sub' => $unreadDms . ' unread',
                'href' => '/chat?panel=dms',
                'tone' => 'purple',
                'icon' => 'message-circle',
            ],
            [
                'label' => 'Unread Total',
                'value' => number_format($unreadChannels + $unreadDms),
                'sub' => 'Across conversations',
                'href' => '/chat',
                'tone' => 'orange',
                'icon' => 'bell',
            ],
            [
                'label' => 'Open Chat',
                'value' => '→',
                'sub' => 'Jump to inbox',
                'href' => '/chat',
                'tone' => 'mint',
                'icon' => 'messages-square',
            ],
        ];

        $this->view('chat.overview', [
            'title' => 'Chat Overview',
            'metrics' => $metrics,
            'channels' => array_slice($channelList, 0, 6),
            'conversations' => array_slice($dmList, 0, 6),
        ], 'layouts/chat');
    }

    public function files(): void
    {
        $ctx = $this->context();
        $this->authorize('chat.download_file');
        $q = trim((string) $this->request->input('q', ''));
        $files = (new AttachmentService())->library($ctx['company_id'], $ctx['user_id'], $q);

        $this->view('chat.files', [
            'title' => 'Chat Files',
            'files' => $files,
            'q' => $q,
        ], 'layouts/chat');
    }

    public function saved(): void
    {
        $ctx = $this->context();
        $items = (new MessageService())->savedForUser($ctx['user_id'], $ctx['company_id']);
        $this->view('chat.saved', [
            'title' => 'Saved Messages',
            'items' => $items,
        ], 'layouts/chat');
    }

    public function mentions(): void
    {
        $ctx = $this->context();
        $items = (new MessageService())->mentionsForUser($ctx['user_id'], $ctx['company_id']);
        $this->view('chat.mentions', [
            'title' => 'Mentions',
            'items' => $items,
        ], 'layouts/chat');
    }

    public function notifications(): void
    {
        $ctx = $this->context();
        $settings = new ChatSettingsService();
        if ($this->request->method() === 'POST') {
            $settings->savePreferences($ctx['company_id'], $ctx['user_id'], $this->request->all());
            flash('success', 'Notification preferences saved.');
            $this->redirect('/chat/notifications');
        }
        $prefs = $settings->getOrCreatePreferences($ctx['company_id'], $ctx['user_id']);
        $this->view('chat.notifications', [
            'title' => 'Chat Notifications',
            'prefs' => $prefs,
        ], 'layouts/chat');
    }

    public function documents(): void
    {
        $ctx = $this->context();
        $docs = new DocumentService();
        $this->view('chat.documents', [
            'title' => 'Shared Documents',
            'documents' => $docs->listDocuments($ctx['company_id']),
            'categories' => $docs->categories($ctx['company_id']),
            'canManage' => $this->auth->can('chat.manage_settings') || $this->auth->can('documents.manage'),
        ], 'layouts/chat');
    }

    public function storeDocument(): void
    {
        $ctx = $this->context();
        if (!$this->auth->can('chat.manage_settings') && !$this->auth->can('documents.manage')) {
            $this->authorize('chat.manage_settings');
        }
        $result = (new DocumentService())->createOrVersion(
            $ctx['company_id'],
            $ctx['user_id'],
            trim((string) $this->request->input('title', '')),
            trim((string) $this->request->input('description', '')) ?: null,
            $this->request->input('category_id') ? (int) $this->request->input('category_id') : null,
            (bool) $this->request->input('requires_acknowledgement'),
            $_FILES['file'] ?? null,
            $this->request->input('document_id') ? (int) $this->request->input('document_id') : null,
            trim((string) $this->request->input('change_notes', '')) ?: null
        );
        flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Done.');
        $this->redirect('/chat/documents');
    }

    public function acknowledgeDocument(int $id): void
    {
        $ctx = $this->context();
        $result = (new DocumentService())->acknowledge($ctx['company_id'], $ctx['user_id'], $id);
        flash($result['success'] ? 'success' : 'error', $result['message'] ?? 'Done.');
        $this->redirect('/chat/documents');
    }
}
