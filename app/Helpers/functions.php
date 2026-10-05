<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Session;
use App\Core\View;
use App\Services\AuthService;

if (!function_exists('app')) {
    function app(): Application
    {
        return Application::getInstance();
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Application::getInstance()->config($key, $default);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return View::escape($value);
    }
}

if (!function_exists('asset')) {
    function asset(string $path): string
    {
        // Relative asset URLs work on any host/port (8001, 8088, etc.).
        // Include the file modification time so CSS/JS changes are not hidden
        // behind a stale browser cache during upgrades.
        $relative = ltrim($path, '/');
        $file = dirname(__DIR__, 2) . '/public/assets/' . $relative;
        $version = is_file($file) ? (string) filemtime($file) : '';
        return '/assets/' . $relative . ($version !== '' ? '?v=' . $version : '');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return $path === '' ? '/' : '/' . $path;
    }
}

if (!function_exists('route')) {
    function route(string $name, array $params = []): string
    {
        return Application::getInstance()->router()->url($name, $params);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): never
    {
        Application::getInstance()->response()->redirect($url);
    }
}

if (!function_exists('old')) {
    function old(string $key, mixed $default = ''): mixed
    {
        $old = Session::get('_flash')['old'] ?? Session::flash('old') ?? [];
        return $old[$key] ?? $default;
    }
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return Session::csrfToken();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
    }
}

if (!function_exists('method_field')) {
    function method_field(string $method): string
    {
        return '<input type="hidden" name="_method" value="' . e(strtoupper($method)) . '">';
    }
}

if (!function_exists('auth')) {
    function auth(): AuthService
    {
        return new AuthService();
    }
}

if (!function_exists('can')) {
    function can(string $permission): bool
    {
        return auth()->can($permission);
    }
}

if (!function_exists('flash')) {
    function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }
}

if (!function_exists('format_date')) {
    function format_date(?string $date, ?string $format = null): string
    {
        if (!$date) {
            return '—';
        }
        $format = $format ?? config('app.date_format', 'Y-m-d');
        return date($format, strtotime($date));
    }
}

if (!function_exists('format_datetime')) {
    function format_datetime(?string $datetime, ?string $format = null): string
    {
        if (!$datetime) {
            return '—';
        }
        $format = $format ?? config('app.datetime_format', 'Y-m-d H:i');
        return date($format, strtotime($datetime));
    }
}

if (!function_exists('format_money')) {
    function format_money(float|int|string|null $amount, ?string $currency = null): string
    {
        $currency = $currency ?? config('app.currency', 'PKR');
        return $currency . ' ' . number_format((float) $amount, 2);
    }
}

if (!function_exists('format_minutes')) {
    function format_minutes(?int $minutes): string
    {
        if ($minutes === null) {
            return '—';
        }
        $hours = intdiv(abs($minutes), 60);
        $mins = abs($minutes) % 60;
        return sprintf('%02d:%02d', $hours, $mins);
    }
}

if (!function_exists('status_badge')) {
    function status_badge(string $status): string
    {
        $map = [
            'active' => 'success',
            'inactive' => 'secondary',
            'present' => 'success',
            'absent' => 'danger',
            'late' => 'warning',
            'half_day' => 'info',
            'on_leave' => 'primary',
            'holiday' => 'secondary',
            'weekend' => 'secondary',
            'remote' => 'info',
            'manual' => 'dark',
            'missing_checkout' => 'warning',
            'pending' => 'warning',
            'approved' => 'success',
            'rejected' => 'danger',
            'cancelled' => 'secondary',
            'draft' => 'secondary',
            'locked' => 'dark',
            'paid' => 'success',
            'unpaid' => 'danger',
            'verified' => 'success',
            'pending_review' => 'warning',
            'outside_radius' => 'danger',
            'low_gps_accuracy' => 'warning',
            'rejected_attendance' => 'danger',
            'productive' => 'success',
            'neutral' => 'secondary',
            'distracting' => 'warning',
            'blocked' => 'danger',
            'working' => 'success',
            'break' => 'info',
            'off_hours' => 'secondary',
            'unscheduled' => 'warning',
            'paid_leave' => 'primary',
            'unpaid_leave' => 'danger',
            'sick_leave' => 'primary',
            'rest_day' => 'secondary',
            'work_from_home' => 'info',
            'official_duty' => 'dark',
            'missing_attendance' => 'warning',
            'pending_regularization' => 'warning',
            'scheduled' => 'light',
        ];

        $class = $map[strtolower($status)] ?? 'secondary';
        $label = ucwords(str_replace('_', ' ', $status));
        return '<span class="badge bg-' . e($class) . '">' . e($label) . '</span>';
    }
}

if (!function_exists('ui')) {
    /**
     * Render a shared UI component from views/partials/ui/ and echo it inline.
     * These are the app's single source of truth for buttons, tabs, cards,
     * inputs, badges and empty states — see views/partials/ui/*.php for the
     * data contract of each one.
     *
     *   <?php ui('button', ['label' => 'Save', 'variant' => 'primary']); ?>
     */
    function ui(string $component, array $data = []): void
    {
        echo View::partial('partials.ui.' . $component, $data);
    }
}

if (!function_exists('csv_safe')) {
    /**
     * Neutralise spreadsheet formula injection before a value is written to CSV.
     * Excel and Sheets execute a cell starting with = + - @ TAB or CR as a formula,
     * so such values are prefixed with an apostrophe. Numbers pass through untouched
     * so numeric columns still sort and sum.
     */
    function csv_safe(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        $string = (string) $value;
        if ($string === '' || is_numeric($string)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $string) === 1 ? "'" . $string : $value;
    }
}

if (!function_exists('sparkline_svg')) {
    /**
     * Minimal dependency-free inline sparkline (no Chart.js instance needed
     * for a handful of pixels). Returns '' for <2 points so callers can
     * safely `if ($svg)` and omit the sparkline rather than draw a flat/
     * meaningless single-point line.
     */
    function sparkline_svg(array $values, int $width = 64, int $height = 20): string
    {
        $values = array_values(array_map('floatval', $values));
        if (count($values) < 2) {
            return '';
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;
        $count = count($values);
        $points = [];
        foreach ($values as $i => $v) {
            $x = $count > 1 ? ($i / ($count - 1)) * $width : 0;
            $y = $range > 0 ? $height - (($v - $min) / $range) * $height : $height / 2;
            $points[] = round($x, 1) . ',' . round($y, 1);
        }

        return '<svg class="ds-sparkline" viewBox="0 0 ' . $width . ' ' . $height . '" width="' . $width . '" height="' . $height . '" preserveAspectRatio="none" aria-hidden="true" focusable="false">'
            . '<polyline points="' . e(implode(' ', $points)) . '" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" /></svg>';
    }
}

if (!function_exists('haversine_distance')) {
    function haversine_distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return round($earthRadius * $c, 2);
    }
}

if (!function_exists('setting')) {
    function setting(string $key, mixed $default = null): mixed
    {
        static $cache = null;
        if ($cache === null) {
            try {
                $rows = \App\Core\Database::getInstance()->fetchAll('SELECT `key`, `value` FROM system_settings');
                $cache = [];
                foreach ($rows as $row) {
                    $cache[$row['key']] = $row['value'];
                }
            } catch (Throwable) {
                $cache = [];
            }
        }
        return $cache[$key] ?? $default;
    }
}

if (!function_exists('paginate_links')) {
    function paginate_links(array $paginator, string $baseUrl): string
    {
        if (($paginator['last_page'] ?? 1) <= 1) {
            return '';
        }

        $current = $paginator['current_page'];
        $last = $paginator['last_page'];
        $html = '<nav><ul class="pagination pagination-sm mb-0">';

        for ($i = max(1, $current - 2); $i <= min($last, $current + 2); $i++) {
            $active = $i === $current ? ' active' : '';
            $sep = str_contains($baseUrl, '?') ? '&' : '?';
            $html .= '<li class="page-item' . $active . '"><a class="page-link" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a></li>';
        }

        $html .= '</ul></nav>';
        return $html;
    }
}

if (!function_exists('nav_scope')) {
    function nav_scope(): string
    {
        return \App\Support\Navigation::scope();
    }
}

if (!function_exists('nav_workspaces')) {
    function nav_workspaces(?string $scope = null): array
    {
        return \App\Support\Navigation::workspaces($scope);
    }
}

if (!function_exists('nav_active_workspace')) {
    function nav_active_workspace(?string $scope = null): ?array
    {
        return \App\Support\Navigation::activeWorkspace($scope);
    }
}

if (!function_exists('nav_breadcrumbs')) {
    function nav_breadcrumbs(?string $scope = null): array
    {
        return \App\Support\Navigation::breadcrumbs($scope);
    }
}

if (!function_exists('nav_quick_actions')) {
    function nav_quick_actions(?string $scope = null): array
    {
        return \App\Support\Navigation::quickActions($scope);
    }
}

if (!function_exists('nav_mobile')) {
    function nav_mobile(?string $scope = null): array
    {
        return \App\Support\Navigation::mobileNav($scope);
    }
}
