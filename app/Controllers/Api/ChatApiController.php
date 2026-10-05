<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\Chat\AttachmentService;
use App\Services\Chat\AutoChannelService;
use App\Services\Chat\ChannelService;
use App\Services\Chat\ChatSettingsService;
use App\Services\Chat\ChatSupport;
use App\Services\Chat\ConversationService;
use App\Services\Chat\MessageService;
use App\Services\Chat\PresenceService;
use App\Services\NotificationService;

class ChatApiController extends Controller
{
    private int $companyId = 0;
    private int $userId = 0;

    public function __construct(Request $request, Response $response)
    {
        parent::__construct($request, $response);
    }

    private function migrationError(): void
    {
        $missing = ChatSupport::missingTables();
        $this->jsonError(
            ChatSupport::migrationHint() . ($missing !== [] ? ' Missing: ' . implode(', ', $missing) . '.' : ''),
            ['missing_tables' => $missing],
            503,
            'MIGRATION_REQUIRED'
        );
    }

    private function boot(): void
    {
        $user = $this->user();
        if (!$user) {
            $this->jsonError('Unauthenticated.', null, 401);
        }
        if (!ChatSupport::isInstalled()) {
            $this->migrationError();
        }
        $this->authorize('chat.access');
        $this->userId = (int) $user['id'];
        $this->companyId = ChatSupport::resolveCompanyId($user, $this->employee());
        try {
            $auto = new AutoChannelService();
            $auto->bootstrapCompany($this->companyId, $this->userId, false);
            // Sync current user into org-wide defaults (+ branch/dept when applicable).
            $auto->syncUser($this->companyId, $this->userId, $this->employee());
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                $this->migrationError();
            }
        }
        try {
            (new PresenceService())->heartbeat(
                $this->companyId,
                $this->userId,
                $this->request->input('channel_id') ? (int) $this->request->input('channel_id') : null,
                $this->request->input('conversation_id') ? (int) $this->request->input('conversation_id') : null
            );
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                $this->migrationError();
            }
        }
    }

    public function status(): void
    {
        $user = $this->user();
        if (!$user) {
            $this->jsonError('Unauthenticated.', null, 401);
        }
        $missing = ChatSupport::missingTables();
        $this->jsonSuccess('Chat status', [
            'installed' => $missing === [],
            'views_installed' => ChatSupport::viewsInstalled(),
            'missing_tables' => $missing,
            'has_chat_access' => $this->auth->can('chat.access'),
            'hint' => $missing === []
                ? ($this->auth->can('chat.access')
                    ? 'OK'
                    : 'Tables OK but chat.access missing — run 2026_07_31_chat_permissions_fix.sql')
                : ChatSupport::migrationHint(),
        ]);
    }

    public function bootstrap(): void
    {
        $this->boot();
        try {
            $channels = new ChannelService();
            $conversations = new ConversationService();
            $settings = (new ChatSettingsService())->all($this->companyId);
            $presence = new PresenceService();
            $manageChannel = $this->auth->can('chat.manage_channel');
            $channelList = $this->withEffectiveCanPost(
                $channels->listForUser($this->companyId, $this->userId),
                $manageChannel
            );

            $this->jsonSuccess('Chat bootstrap', [
                'user_id' => $this->userId,
                'company_id' => $this->companyId,
                'channels' => $channelList,
                'discoverable' => $this->auth->can('chat.join_public_channel')
                    ? $channels->discoverablePublic($this->companyId, $this->userId)
                    : [],
                'conversations' => $conversations->listForUser($this->companyId, $this->userId),
                'online' => $presence->onlineUsers($this->companyId),
                'members' => $this->directoryPayload($conversations, $presence),
                'settings' => $settings,
                'permissions' => [
                    'create_channel' => $this->auth->can('chat.create_channel'),
                    'manage_channel' => $manageChannel,
                    'direct_message' => $this->auth->can('chat.direct_message'),
                    'post_message' => $this->auth->can('chat.post_message'),
                    'upload_file' => $this->auth->can('chat.upload_file'),
                    'pin_message' => $this->auth->can('chat.pin_message'),
                    'mass_mentions' => $this->auth->can('chat.use_mass_mentions'),
                    'delete_any' => $this->auth->can('chat.delete_any_message'),
                ],
            ]);
        } catch (\Throwable $e) {
            if (ChatSupport::isSchemaThrowable($e)) {
                $this->migrationError();
            }
            throw $e;
        }
    }

    public function poll(): void
    {
        $this->boot();
        $channelId = $this->request->input('channel_id') ? (int) $this->request->input('channel_id') : null;
        $conversationId = $this->request->input('conversation_id') ? (int) $this->request->input('conversation_id') : null;
        $afterId = $this->request->input('after_id') ? (int) $this->request->input('after_id') : null;
        $threadParentId = $this->request->input('thread_parent_id') ? (int) $this->request->input('thread_parent_id') : null;

        if ($channelId) {
            (new ChannelService())->healChannelMembers($channelId, $this->companyId);
        }

        $messages = (new MessageService())->listMessages(
            $this->companyId,
            $this->userId,
            $channelId,
            $conversationId,
            $afterId,
            null,
            50,
            $threadParentId
        );

        // Viewing this room via poll: advance last_read so unread badges clear without full refresh.
        if ($messages && !$threadParentId) {
            $ids = array_map(static fn ($m) => (int) $m['id'], $messages);
            $lastId = max($ids);
            (new MessageService())->markReadReceipts($this->userId, $ids);
            if ($channelId) {
                (new ChannelService())->markRead($channelId, $this->userId, $lastId);
            }
            if ($conversationId) {
                (new ConversationService())->markRead($conversationId, $this->userId, $lastId);
            }
            (new NotificationService())->markChatTargetRead($this->userId, $channelId, $conversationId);
        }

        $channels = new ChannelService();
        $conversations = new ConversationService();
        $presence = new PresenceService();
        $manageChannel = $this->auth->can('chat.manage_channel');

        if ($channelId) {
            $channels->healChannelMembers($channelId, $this->companyId);
        }

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'poll',
            'viewer_user_id' => $this->userId,
            'company_id' => $this->companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'after_id' => $afterId,
            'returned_count' => count($messages),
            'returned_ids' => array_map(static fn ($m) => (int) $m['id'], $messages),
            'participant_user_ids' => $conversationId
                ? $conversations->memberUserIds($conversationId)
                : ($channelId ? $channels->memberUserIds($channelId) : []),
        ]);
        if ($channelId) {
            ChatSupport::debugLog('channel_message_delivery', [
                'event' => 'poll',
                'viewer_user_id' => $this->userId,
                'company_id' => $this->companyId,
                'channel_id' => $channelId,
                'after_id' => $afterId,
                'returned_count' => count($messages),
                'returned_ids' => array_map(static fn ($m) => (int) $m['id'], $messages),
                'participant_user_ids' => $channels->memberUserIds($channelId),
            ]);
        }

        $this->jsonSuccess('Poll', [
            'user_id' => $this->userId, // always authenticated users.id for frontend isOwnMessage
            'company_id' => $this->companyId,
            'messages' => $messages,
            'channels' => $this->withEffectiveCanPost(
                $channels->listForUser($this->companyId, $this->userId),
                $manageChannel
            ),
            'conversations' => $conversations->listForUser($this->companyId, $this->userId),
            'typing' => $presence->typingFor($this->companyId, $channelId, $conversationId, $this->userId),
            'online' => $presence->onlineUsers($this->companyId),
            'pins' => ($channelId || $conversationId)
                ? (new MessageService())->pinsForTarget($this->companyId, $channelId, $conversationId)
                : [],
            'server_time' => date('c'),
        ]);
    }

    public function messages(): void
    {
        $this->boot();
        $channelId = $this->request->input('channel_id') ? (int) $this->request->input('channel_id') : null;
        $conversationId = $this->request->input('conversation_id') ? (int) $this->request->input('conversation_id') : null;
        $beforeId = $this->request->input('before_id') ? (int) $this->request->input('before_id') : null;
        $threadParentId = $this->request->input('thread_parent_id') ? (int) $this->request->input('thread_parent_id') : null;

        $messages = (new MessageService())->listMessages(
            $this->companyId,
            $this->userId,
            $channelId,
            $conversationId,
            null,
            $beforeId,
            50,
            $threadParentId
        );

        if ($messages) {
            $ids = array_map(static fn ($m) => (int) $m['id'], $messages);
            (new MessageService())->markReadReceipts($this->userId, $ids);
            $lastId = max($ids);
            if ($channelId) {
                (new ChannelService())->markRead($channelId, $this->userId, $lastId);
            }
            if ($conversationId) {
                (new ConversationService())->markRead($conversationId, $this->userId, $lastId);
            }
        }

        // Opening a room marks related bell notifications as read.
        if ($channelId || $conversationId) {
            (new NotificationService())->markChatTargetRead($this->userId, $channelId, $conversationId);
        }

        $this->jsonSuccess('Messages', ['messages' => $messages]);
    }

    public function send(): void
    {
        $this->boot();
        $this->authorize('chat.post_message');

        $body = (string) $this->request->input('body', '');
        $channelId = $this->request->input('channel_id') ? (int) $this->request->input('channel_id') : null;
        $conversationId = $this->request->input('conversation_id') ? (int) $this->request->input('conversation_id') : null;
        $threadParentId = $this->request->input('thread_parent_id') ? (int) $this->request->input('thread_parent_id') : null;

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'api_send',
            'sender_user_id' => $this->userId,
            'company_id' => $this->companyId,
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'has_employee_row' => (bool) $this->employee(),
        ]);

        $result = (new MessageService())->post($this->companyId, $this->userId, $body, [
            'channel_id' => $channelId,
            'conversation_id' => $conversationId,
            'thread_parent_id' => $threadParentId,
            'allow_mass_mentions' => $this->auth->can('chat.use_mass_mentions'),
            'force_announcement_post' => $this->auth->can('chat.manage_channel'),
            'requires_acknowledgement' => (bool) $this->request->input('requires_acknowledgement'),
            'allow_empty' => !empty($_FILES['file']['name']),
        ]);

        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }

        $message = $result['data'];
        if (!empty($_FILES['file']['name'])) {
            $this->authorize('chat.upload_file');
            $max = (int) ((new ChatSettingsService())->get($this->companyId, 'max_upload_bytes', '10485760'));
            $upload = (new AttachmentService())->storeForMessage(
                $this->companyId,
                $this->userId,
                (int) $message['id'],
                $_FILES['file'],
                $max
            );
            if (!$upload['success']) {
                $this->jsonError($upload['message'] ?? 'Upload failed.', null, 422);
            }
            $message = (new MessageService())->listMessages(
                $this->companyId,
                $this->userId,
                $channelId,
                $conversationId,
                (int) $message['id'] - 1,
                null,
                1,
                $threadParentId
            )[0] ?? $message;
        }

        $this->jsonSuccess('Sent', ['message' => $message]);
    }

    public function editMessage(int $id): void
    {
        $this->boot();
        $this->authorize('chat.edit_own_message');
        $result = (new MessageService())->edit(
            $this->companyId,
            $this->userId,
            $id,
            (string) $this->request->input('body', ''),
            $this->auth->can('chat.delete_any_message')
        );
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess('Updated', ['message' => $result['data']]);
    }

    public function deleteMessage(int $id): void
    {
        $this->boot();
        $canAny = $this->auth->can('chat.delete_any_message');
        if (!$canAny) {
            $this->authorize('chat.delete_own_message');
        }
        $result = (new MessageService())->softDelete($this->companyId, $this->userId, $id, $canAny);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess('Deleted');
    }

    public function react(int $id): void
    {
        $this->boot();
        $result = (new MessageService())->toggleReaction(
            $this->companyId,
            $this->userId,
            $id,
            (string) $this->request->input('emoji', '👍')
        );
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function pin(int $id): void
    {
        $this->boot();
        $this->authorize('chat.pin_message');
        $result = (new MessageService())->pin($this->companyId, $this->userId, $id);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function saveMessage(int $id): void
    {
        $this->boot();
        $result = (new MessageService())->toggleSaved($this->userId, $id, $this->companyId);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], $result['data'] ?? null);
    }

    public function acknowledge(int $id): void
    {
        $this->boot();
        $result = (new MessageService())->acknowledgeMessage($this->companyId, $this->userId, $id);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message']);
    }

    public function createChannel(): void
    {
        $this->boot();
        $this->authorize('chat.create_channel');
        $name = trim((string) $this->request->input('name', ''));
        if ($name === '') {
            $this->jsonError('Channel name required.', null, 422);
        }
        $type = (string) $this->request->input('channel_type', 'public');
        $channel = (new ChannelService())->create(
            $this->companyId,
            $this->userId,
            $name,
            $type,
            trim((string) $this->request->input('description', '')) ?: null
        );
        $this->jsonSuccess('Channel created', ['channel' => $channel]);
    }

    public function joinChannel(int $id): void
    {
        $this->boot();
        $this->authorize('chat.join_public_channel');
        $result = (new ChannelService())->joinPublic($this->companyId, $id, $this->userId);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $this->jsonSuccess($result['message'], ['channel' => $result['channel']]);
    }

    public function inviteMembers(int $id): void
    {
        $this->boot();
        $this->authorize('chat.invite_members');
        $channels = new ChannelService();
        if (!$channels->ensureMembership($id, $this->userId) && !$this->auth->can('chat.manage_channel')) {
            $this->jsonError('Forbidden.', null, 403);
        }
        $userIds = $this->request->input('user_ids', []);
        if (!is_array($userIds)) {
            $userIds = array_filter(array_map('intval', explode(',', (string) $userIds)));
        }
        foreach ($userIds as $uid) {
            $uid = (int) $uid;
            if ($uid > 0) {
                $channels->addMember($id, $uid, 3, $this->userId);
            }
        }
        $this->jsonSuccess('Members invited', ['members' => $channels->members($id)]);
    }

    public function removeMember(int $id, int $userId): void
    {
        $this->boot();
        $this->authorize('chat.remove_members');
        (new ChannelService())->removeMember($id, $userId);
        $this->jsonSuccess('Member removed');
    }

    public function startDm(): void
    {
        $this->boot();
        $this->authorize('chat.direct_message');
        $conversations = new ConversationService();

        // Never confuse employees.id with users.id:
        // - user_id → strict users.id peer check
        // - employee_id → map employees.id → users.id
        $asUserId = (int) $this->request->input('user_id', 0);
        $asEmployeeId = (int) $this->request->input('employee_id', 0);
        if ($asUserId > 0) {
            $otherId = $conversations->resolveUserId($this->companyId, $asUserId);
            // Legacy clients sometimes put employees.id in user_id — fall back only if strict fails.
            if ($otherId <= 0) {
                $otherId = $conversations->resolveEmployeeId($this->companyId, $asUserId);
            }
        } elseif ($asEmployeeId > 0) {
            $otherId = $conversations->resolveEmployeeId($this->companyId, $asEmployeeId);
        } else {
            $otherId = 0;
        }

        ChatSupport::debugLog('admin_chat_delivery', [
            'event' => 'start_dm',
            'sender_user_id' => $this->userId,
            'company_id' => $this->companyId,
            'input_user_id' => $asUserId,
            'input_employee_id' => $asEmployeeId,
            'resolved_peer_user_id' => $otherId,
        ]);

        $result = $conversations->findOrCreateDirect($this->companyId, $this->userId, $otherId);
        if (!$result['success']) {
            $this->jsonError($result['message'], null, 422);
        }
        $conversation = $result['conversation'];
        $conversationId = (int) ($conversation['id'] ?? 0);
        if ($conversationId > 0) {
            $conversations->assertDirectPeers($conversationId, $this->companyId, $this->userId, $otherId);
            $conversation = $conversations->get($conversationId, $this->companyId) ?? $conversation;
        }
        $this->jsonSuccess('DM ready', [
            'conversation' => $conversation,
            'participant_user_ids' => $conversationId > 0 ? $conversations->memberUserIds($conversationId) : [],
        ]);
    }

    public function users(): void
    {
        $this->boot();
        $q = trim((string) $this->request->input('q', ''));
        $users = $this->directoryPayload(
            new ConversationService(),
            new PresenceService(),
            $q
        );
        $this->jsonSuccess('Users', [
            'users' => $users,
            'members' => $users,
        ]);
    }

    /**
     * Org member directory for Start DM / Members modal.
     * Route: GET /api/chat/members → membersDirectory
     * Returns users.id (never employees.id) with online flags.
     */
    public function membersDirectory(): void
    {
        $this->boot();
        $q = trim((string) $this->request->input('q', ''));
        $members = $this->directoryPayload(
            new ConversationService(),
            new PresenceService(),
            $q
        );
        $this->jsonSuccess('Members', [
            'members' => $members,
            'users' => $members,
        ]);
    }

    public function search(): void
    {
        $this->boot();
        $q = trim((string) $this->request->input('q', ''));
        $messages = (new MessageService())->search($this->companyId, $this->userId, $q);
        $files = $this->auth->can('chat.download_file')
            ? (new AttachmentService())->library($this->companyId, $this->userId, $q)
            : [];
        $this->jsonSuccess('Search', ['messages' => $messages, 'files' => $files]);
    }

    public function typing(): void
    {
        $this->boot();
        (new PresenceService())->setTyping(
            $this->companyId,
            $this->userId,
            $this->request->input('channel_id') ? (int) $this->request->input('channel_id') : null,
            $this->request->input('conversation_id') ? (int) $this->request->input('conversation_id') : null
        );
        $this->jsonSuccess('Typing');
    }

    public function members(int $id): void
    {
        $this->boot();
        $channels = new ChannelService();
        if (!$channels->ensureMembership($id, $this->userId)) {
            $this->jsonError('Forbidden.', null, 403);
        }
        $this->jsonSuccess('Members', ['members' => $channels->members($id)]);
    }

    /**
     * @param list<array<string,mixed>> $channels
     * @return list<array<string,mixed>>
     */
    private function withEffectiveCanPost(array $channels, bool $manageChannel): array
    {
        foreach ($channels as &$ch) {
            if ($manageChannel) {
                $ch['can_post'] = 1;
            } else {
                $ch['can_post'] = !empty($ch['can_post']) ? 1 : 0;
            }
        }
        unset($ch);
        return $channels;
    }

    /** @return list<array<string,mixed>> */
    private function directoryPayload(
        ConversationService $conversations,
        PresenceService $presence,
        string $q = ''
    ): array {
        $users = $conversations->directoryUsers($this->companyId, $this->userId, $q, $q === '' ? 100 : 40);
        $onlineIds = [];
        foreach ($presence->onlineUsers($this->companyId) as $row) {
            $onlineIds[(int) $row['user_id']] = true;
        }
        foreach ($users as &$user) {
            $uid = (int) $user['id'];
            $user['online'] = isset($onlineIds[$uid]);
            $user['user_id'] = $uid; // explicit alias — never employees.id
        }
        unset($user);
        return $users;
    }
}
