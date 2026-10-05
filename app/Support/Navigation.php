<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Resolves workspace navigation from config/navigation.php.
 */
final class Navigation
{
    private static ?array $config = null;

    public static function config(): array
    {
        if (self::$config === null) {
            self::$config = require dirname(__DIR__, 2) . '/config/navigation.php';
        }

        return self::$config;
    }

    public static function scope(): string
    {
        try {
            return auth()->isAdmin() ? 'admin' : 'employee';
        } catch (\Throwable) {
            return 'admin';
        }
    }

    public static function currentUri(): string
    {
        return parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    }

    public static function currentQuery(): array
    {
        $query = [];
        parse_str($_SERVER['QUERY_STRING'] ?? '', $query);

        return $query;
    }

    public static function workspaces(?string $scope = null): array
    {
        $scope = $scope ?? self::scope();
        $items = self::config()[$scope]['workspaces'] ?? [];
        $visible = [];

        foreach ($items as $ws) {
            if (!self::workspaceVisible($ws)) {
                continue;
            }
            $ws['children'] = self::visibleChildren($ws['children'] ?? []);
            // If parent landing URL is not permitted, fall back to first visible child
            if (!empty($ws['children'])) {
                $needsFallback = !empty($ws['match_any_perm'])
                    && (empty($ws['perm']) || !can((string) $ws['perm']));
                if (!$needsFallback && !empty($ws['perm']) && !can((string) $ws['perm'])) {
                    $needsFallback = true;
                }
                if ($needsFallback) {
                    $ws['url'] = $ws['children'][0]['url'] ?? $ws['url'];
                }
            }
            $visible[] = $ws;
        }

        return $visible;
    }

    public static function quickActions(?string $scope = null): array
    {
        $scope = $scope ?? self::scope();
        $items = self::config()[$scope]['quick_actions'] ?? [];

        return array_values(array_filter($items, static function (array $item): bool {
            if (!empty($item['perm']) && !can((string) $item['perm'])) {
                return false;
            }

            return true;
        }));
    }

    public static function mobileNav(?string $scope = null): array
    {
        $scope = $scope ?? self::scope();
        $items = self::config()[$scope]['mobile_nav'] ?? [];

        return array_values(array_filter($items, static function (array $item): bool {
            if (array_key_exists('perm', $item) && $item['perm'] !== null && !can((string) $item['perm'])) {
                return false;
            }

            return true;
        }));
    }

    public static function activeWorkspace(?string $scope = null): ?array
    {
        $uri = self::currentUri();
        $workspaces = self::workspaces($scope);
        $best = null;
        $bestLen = -1;

        foreach ($workspaces as $ws) {
            if (!self::workspaceMatches($ws, $uri)) {
                continue;
            }
            $len = self::matchStrength($ws, $uri);
            if ($len > $bestLen) {
                $best = $ws;
                $bestLen = $len;
            }
        }

        return $best;
    }

    public static function activeChild(?array $workspace = null): ?array
    {
        $workspace = $workspace ?? self::activeWorkspace();
        if (!$workspace) {
            return null;
        }

        foreach ($workspace['children'] ?? [] as $child) {
            if (self::childIsActive($child)) {
                return $child;
            }
        }

        return null;
    }

    public static function breadcrumbs(?string $scope = null): array
    {
        $ws = self::activeWorkspace($scope);
        if (!$ws) {
            return [];
        }

        $crumbs = [
            [
                'label' => $ws['label'],
                'url' => $ws['url'],
            ],
        ];

        $child = self::activeChild($ws);
        if ($child && ($child['url'] ?? '') !== ($ws['url'] ?? '')) {
            $crumbs[] = [
                'label' => $child['label'],
                'url' => $child['url'],
            ];
        }

        return $crumbs;
    }

    public static function childIsActive(array $child): bool
    {
        $uri = self::currentUri();
        $query = self::currentQuery();
        $url = (string) ($child['url'] ?? '');
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $linkQuery = parse_url($url, PHP_URL_QUERY);
        $mode = $child['match'] ?? ($linkQuery ? 'query' : 'prefix');
        $discriminators = self::config()['nav_discriminators'] ?? ['status', 'scope', 'panel'];

        if ($mode === 'query') {
            return self::isExactPath($uri, $path)
                && self::queryMatches($linkQuery, $query)
                && !self::hasForeignDiscriminators($query, $linkQuery, $discriminators);
        }

        if ($mode === 'exact') {
            return self::isExactPath($uri, $path)
                && self::queryMatches($linkQuery, $query)
                && !self::hasForeignDiscriminators($query, $linkQuery, $discriminators);
        }

        if ($mode === 'list') {
            if (!self::isListPath($uri, $path)) {
                return false;
            }
            if (self::isExactPath($uri, $path)
                && self::hasForeignDiscriminators($query, $linkQuery, $discriminators)
            ) {
                return false;
            }

            return self::queryMatches($linkQuery, $query);
        }

        // prefix
        if (!($uri === $path || str_starts_with($uri, rtrim($path, '/') . '/'))) {
            return false;
        }

        return self::queryMatches($linkQuery, $query);
    }

    public static function linkIsActive(array $item): bool
    {
        if (!empty($item['match']) && is_string($item['match'])) {
            return self::childIsActive($item);
        }

        $uri = self::currentUri();
        $url = (string) ($item['url'] ?? '');
        $path = parse_url($url, PHP_URL_PATH) ?: $url;

        return $uri === $path || str_starts_with($uri, rtrim($path, '/') . '/');
    }

    public static function mobileItemActive(array $item): bool
    {
        $uri = self::currentUri();
        foreach ($item['match'] ?? [] as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, rtrim($prefix, '/') . '/')) {
                return true;
            }
        }
        if (!empty($item['url']) && $item['url'] !== '#') {
            $path = parse_url((string) $item['url'], PHP_URL_PATH) ?: $item['url'];

            return $uri === $path;
        }

        return false;
    }

    private static function workspaceVisible(array $ws): bool
    {
        if (!empty($ws['match_any_perm'])) {
            foreach ($ws['children'] ?? [] as $child) {
                if (empty($child['perm']) || can((string) $child['perm'])) {
                    return true;
                }
            }

            return !empty($ws['perm']) && can((string) $ws['perm']);
        }

        if (!empty($ws['perm']) && !can((string) $ws['perm'])) {
            // Still show if any child is allowed
            foreach ($ws['children'] ?? [] as $child) {
                if (empty($child['perm']) || can((string) $child['perm'])) {
                    return true;
                }
            }

            return false;
        }

        return true;
    }

    private static function visibleChildren(array $children): array
    {
        return array_values(array_filter($children, static function (array $child): bool {
            if (!empty($child['perm']) && !can((string) $child['perm'])) {
                return false;
            }

            return true;
        }));
    }

    private static function workspaceMatches(array $ws, string $uri): bool
    {
        foreach ($ws['exclude'] ?? [] as $ex) {
            if ($uri === $ex || str_starts_with($uri, rtrim($ex, '/') . '/')) {
                // Special case: reports workspace excludes sibling-owned reports,
                // but those URIs should not activate Reports when exclude is set.
                if (($ws['key'] ?? '') === 'reports') {
                    return false;
                }
            }
        }

        // Prefer explicit match prefixes
        foreach ($ws['match'] ?? [] as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, rtrim($prefix, '/') . '/')) {
                // Reports workspace: skip excluded report subpaths so Attendance/Leave/etc. win
                if (($ws['key'] ?? '') === 'reports') {
                    foreach ($ws['exclude'] ?? [] as $ex) {
                        if ($uri === $ex || str_starts_with($uri, rtrim($ex, '/') . '/')) {
                            return false;
                        }
                    }
                }

                // Chat settings: prefer chat workspace over administration
                if (($ws['key'] ?? '') === 'administration' && str_starts_with($uri, '/admin/chat')) {
                    return false;
                }

                return true;
            }
        }

        return false;
    }

    private static function matchStrength(array $ws, string $uri): int
    {
        $best = 0;
        foreach ($ws['match'] ?? [] as $prefix) {
            if ($uri === $prefix || str_starts_with($uri, rtrim($prefix, '/') . '/')) {
                $best = max($best, strlen($prefix));
            }
        }

        return $best;
    }

    private static function isExactPath(string $uri, string $path): bool
    {
        return $uri === $path;
    }

    private static function isListPath(string $uri, string $path): bool
    {
        if ($uri === $path) {
            return true;
        }
        $prefix = rtrim($path, '/') . '/';
        if (!str_starts_with($uri, $prefix)) {
            return false;
        }
        $rest = substr($uri, strlen($prefix));
        $first = explode('/', $rest, 2)[0];
        $reserved = self::config()['reserved_under'][$path] ?? [];
        if (in_array($first, $reserved, true)) {
            return false;
        }

        return ctype_digit($first);
    }

    private static function queryMatches(?string $queryString, array $actual): bool
    {
        if ($queryString === null || $queryString === '') {
            return true;
        }
        parse_str($queryString, $needed);
        foreach ($needed as $k => $v) {
            if ((string) ($actual[$k] ?? '') !== (string) $v) {
                return false;
            }
        }

        return true;
    }

    private static function hasForeignDiscriminators(array $actual, ?string $ownQuery, array $keys): bool
    {
        $own = [];
        if ($ownQuery) {
            parse_str($ownQuery, $own);
        }
        foreach ($keys as $key) {
            if (!array_key_exists($key, $actual) || $actual[$key] === '' || $actual[$key] === null) {
                continue;
            }
            if (array_key_exists($key, $own) && (string) $own[$key] === (string) $actual[$key]) {
                continue;
            }

            return true;
        }

        return false;
    }
}
