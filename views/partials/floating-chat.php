<?php
/**
 * Floating Team Chat FAB — admin / employee shells.
 * Hidden without chat.access, and when already on /chat.
 */
if (!can('chat.access')) {
    return;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if ($path === '/chat' || str_starts_with($path, '/chat/')) {
    return;
}
?>
<a href="/chat"
   class="floating-chat-fab"
   id="floatingChatFab"
   aria-label="Open Team Chat"
   title="Team Chat">
    <i data-lucide="messages-square" aria-hidden="true"></i>
    <span class="floating-chat-badge d-none" data-chat-unread-badge aria-hidden="true">0</span>
</a>
