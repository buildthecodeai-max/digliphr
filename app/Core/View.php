<?php

declare(strict_types=1);

namespace App\Core;

use RuntimeException;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = null): string
    {
        $viewPath = config('app.paths.views') . '/' . str_replace('.', '/', $view) . '.php';

        if (!file_exists($viewPath)) {
            throw new RuntimeException("View [{$view}] not found.");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        include $viewPath;
        $content = ob_get_clean() ?: '';

        if ($layout === null) {
            return $content;
        }

        $layoutPath = config('app.paths.views') . '/' . str_replace('.', '/', $layout) . '.php';
        if (!file_exists($layoutPath)) {
            throw new RuntimeException("Layout [{$layout}] not found.");
        }

        ob_start();
        include $layoutPath;
        return ob_get_clean() ?: '';
    }

    public static function partial(string $partial, array $data = []): string
    {
        return self::render($partial, $data, null);
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
