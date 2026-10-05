<?php
/**
 * Static regression checks for chat messages 500 (Request::query) + heal presence.
 * Run: php scripts/chat_messages_regression_check.php
 */
declare(strict_types=1);
$root = dirname(__DIR__);
$fail = 0;
function check(bool $ok, string $msg): void {
    global $fail;
    echo ($ok ? '[OK] ' : '[FAIL] ') . $msg . PHP_EOL;
    if (!$ok) { $fail++; }
}
$ctrl = file_get_contents($root . '/app/Controllers/Api/ChatApiController.php') ?: '';
$req = file_get_contents($root . '/app/Core/Request.php') ?: '';
$routes = file_get_contents($root . '/routes/api.php') ?: '';
$ms = file_get_contents($root . '/app/Services/Chat/MessageService.php') ?: '';
$cs = file_get_contents($root . '/app/Services/Chat/ChannelService.php') ?: '';
check(str_contains($req, 'function query('), 'Request::query() exists');
check(!preg_match('/\$this->request->query\(/', $ctrl), 'ChatApiController does not call Request::query()');
check((bool) preg_match('/\$this->request->input\([\'"]channel_id[\'"]\)/', $ctrl), 'messages/poll use input(channel_id)');
check(str_contains($routes, '/chat/messages'), 'api route chat/messages registered');
check(str_contains($routes, '/chat/poll'), 'api route chat/poll registered');
check(str_contains($ms, 'function listMessages('), 'listMessages defined');
check(str_contains($cs, 'employees.id') || str_contains($cs, 'channel_member_healed'), 'Channel membership heal present');
check(str_contains($ms, "'chat_message'"), 'chat_message notifications on send');
exit($fail > 0 ? 1 : 0);
