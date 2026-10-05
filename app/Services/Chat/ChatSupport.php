<?php

declare(strict_types=1);

namespace App\Services\Chat;

use App\Core\Database;

final class ChatSupport
{
    /** Core tables required for Team Chat to boot without SQL fatals. */
    public const REQUIRED_TABLES = [
        'chat_roles',
        'chat_channels',
        'chat_channel_members',
        'chat_conversations',
        'chat_conversation_members',
        'chat_messages',
        'chat_presence',
        'chat_settings',
    ];

    public static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /** @return list<string> */
    public static function missingTables(): array
    {
        $db = Database::getInstance();
        $missing = [];
        foreach (self::REQUIRED_TABLES as $table) {
            if (!$db->tableExists($table)) {
                $missing[] = $table;
            }
        }
        return $missing;
    }

    public static function isInstalled(): bool
    {
        return self::missingTables() === [];
    }

    public static function migrationHint(): string
    {
        return 'Team Chat is not fully installed. In phpMyAdmin run database/migrations/2026_07_28_team_chat.sql, then confirm table chat_channels exists. If /chat returns 403, also run 2026_07_31_chat_permissions_fix.sql.';
    }

    /** True when chat layout + main view are present on disk. */
    public static function viewsInstalled(): bool
    {
        $views = rtrim((string) config('app.paths.views'), '/');
        return is_file($views . '/layouts/chat.php') && is_file($views . '/chat/index.php');
    }

    public static function isSchemaThrowable(\Throwable $e): bool
    {
        $msg = $e->getMessage();
        return str_contains($msg, "doesn't exist")
            || str_contains($msg, 'Base table or view not found')
            || str_contains($msg, 'Unknown column')
            || str_contains($msg, 'SQLSTATE[42S02]')
            || str_contains($msg, 'SQLSTATE[42S22]')
            || str_contains($msg, 'SQLSTATE[42000]')
            || str_contains($msg, 'SQLSTATE[HY093]');
    }

    public static function formatThrowable(\Throwable $e): string
    {
        return $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
    }

    public static function resolveCompanyId(?array $user, ?array $employee): int
    {
        if ($employee && !empty($employee['company_id'])) {
            return (int) $employee['company_id'];
        }

        $db = Database::getInstance();
        if ($user) {
            $row = $db->fetch(
                'SELECT company_id FROM user_roles WHERE user_id = :uid AND company_id IS NOT NULL LIMIT 1',
                ['uid' => (int) $user['id']]
            );
            if ($row && !empty($row['company_id'])) {
                return (int) $row['company_id'];
            }
        }

        $isGlobal = $user && (
            !empty($user['is_super_admin'])
            || in_array('super_admin', (array) ($user['roles'] ?? []), true)
        );
        if ($isGlobal) {
            $company = $db->fetch(
                'SELECT id FROM companies
                 WHERE deleted_at IS NULL AND is_active = 1 ORDER BY id ASC LIMIT 1'
            );
            if ($company) {
                return (int) $company['id'];
            }
        }

        throw new \RuntimeException('No company is assigned to this chat account.');
    }

    public static function sanitizeMessage(string $body): string
    {
        $body = str_replace("\0", '', $body);
        $body = trim($body);
        $body = preg_replace("/\r\n|\r/", "\n", $body) ?? $body;
        if (mb_strlen($body) > 10000) {
            $body = mb_substr($body, 0, 10000);
        }
        return $body;
    }

    public static function renderBodyHtml(string $body): string
    {
        $escaped = htmlspecialchars($body, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $escaped = preg_replace(
            '/@([a-zA-Z0-9._-]{2,64})/',
            '<span class="chat-mention">@$1</span>',
            $escaped
        ) ?? $escaped;
        return nl2br($escaped, false);
    }

    public static function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? $slug;
        $slug = trim($slug, '-');
        return $slug !== '' ? $slug : 'channel';
    }

    public static function now(): string
    {
        return date('Y-m-d H:i:s');
    }

    /**
     * Structured chat delivery diagnostics (no message bodies / tokens).
     * Enabled when APP_DEBUG is true or env CHAT_DEBUG=1.
     *
     * @param array<string,mixed> $context
     */
    public static function debugLog(string $key, array $context = []): void
    {
        $enabled = false;
        try {
            $enabled = (bool) config('app.debug', false);
        } catch (\Throwable) {
            $enabled = false;
        }
        if (!$enabled) {
            $env = getenv('CHAT_DEBUG') ?: ($_ENV['CHAT_DEBUG'] ?? '');
            $enabled = in_array(strtolower((string) $env), ['1', 'true', 'yes', 'on'], true);
        }
        if (!$enabled) {
            return;
        }

        // Never log message bodies or secrets.
        unset($context['body'], $context['body_html'], $context['token'], $context['password'], $context['authorization']);

        $line = json_encode([
            'key' => $key,
            'ts' => self::now(),
            'context' => $context,
        ], JSON_UNESCAPED_SLASHES);
        if ($line === false) {
            return;
        }
        error_log($line);
    }

    /** @return list<string> */
    public static function allowedMimes(): array
    {
        return [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'text/plain', 'text/csv',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/zip',
        ];
    }

    public static function previewType(string $mime): string
    {
        if (str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($mime === 'application/pdf') {
            return 'pdf';
        }
        if (str_starts_with($mime, 'text/')) {
            return 'text';
        }
        if (in_array($mime, [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ], true)) {
            return 'office';
        }
        return 'none';
    }

    public static function initials(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $letters = '';
        foreach (array_slice($parts, 0, 2) as $part) {
            $letters .= mb_strtoupper(mb_substr($part, 0, 1));
        }
        return $letters !== '' ? $letters : '?';
    }
}
